<?php

declare(strict_types=1);

namespace App\Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Names the arguments of a call that passes more than one, so the call site says what each
 * value means instead of relying on position.
 */
final class NameArgumentsRector extends AbstractRector
{
    /**
     * A single argument reads fine on its own; naming it is noise.
     */
    private const int MIN_ARGS = 2;

    public function __construct(
        private readonly ReflectionResolver $reflectionResolver,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Name the arguments of calls that pass more than one',
            [new CodeSample('$mailer->send($user, $subject);', '$mailer->send(recipient: $user, subject: $subject);')],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class, New_::class, FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof CallLike || $node->isFirstClassCallable()) {
            return null;
        }

        // array_values keeps the positions integers; the Arg objects stay the same.
        $args = array_values($node->getArgs());
        if (count($args) < self::MIN_ARGS) {
            return null;
        }

        $reflection = $this->reflectionResolver->resolveFunctionLikeReflectionFromCall($node);
        if (! $reflection instanceof MethodReflection && ! $reflection instanceof FunctionReflection) {
            return null;
        }

        if (! $this->acceptsNamedArguments($reflection)) {
            return null;
        }

        $parameters = ParametersAcceptorSelector::combineAcceptors($reflection->getVariants())
            ->getParameters();

        $namesToApply = $this->resolveArgNamesToApply($args, $parameters);
        if ($namesToApply === []) {
            return null;
        }

        foreach ($namesToApply as $position => $name) {
            $args[$position]->name = new Identifier($name);
        }

        return $node;
    }

    /**
     * PHPUnit and others carry @no-named-arguments on the class, for the same reason Symfony
     * gives: parameter names are not part of what they keep stable.
     */
    private function acceptsNamedArguments(MethodReflection|FunctionReflection $reflection): bool
    {
        if (! $reflection instanceof MethodReflection) {
            return true;
        }

        foreach ($reflection->getDeclaringClass()->getAncestors() as $ancestor) {
            $docComment = $ancestor->getNativeReflection()
                ->getDocComment();

            if (is_string($docComment) && str_contains(haystack: $docComment, needle: '@no-named-arguments')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Naming one argument forces every later positional one to be named too, so anything that
     * cannot be named -- a spread, a variadic tail, an argument past the last parameter --
     * leaves the whole call untouched rather than producing invalid PHP.
     *
     * @param array<int, Arg>            $args
     * @param array<ParameterReflection> $parameters
     *
     * @return array<int, string>
     */
    private function resolveArgNamesToApply(array $args, array $parameters): array
    {
        $namesToApply = [];

        foreach ($args as $position => $arg) {
            if ($arg->name instanceof Identifier) {
                continue;
            }

            if ($arg->unpack) {
                return [];
            }

            $parameter = $parameters[$position] ?? null;
            if (! $parameter instanceof ParameterReflection || $parameter->isVariadic()) {
                return [];
            }

            $namesToApply[$position] = $parameter->getName();
        }

        return $namesToApply;
    }
}

<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Basic\NoTrailingCommaInSinglelineFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayListItemNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\CodingStandard\Fixer\Spacing\StandaloneLinePromotedPropertyFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // Recipe code. Anything reformatted here comes back with the next recipes:update.
        __DIR__ . '/config/bundles.php',
        __DIR__ . '/config/reference.php',
        __DIR__ . '/config/preload.php',
        __DIR__ . '/public/index.php',
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/tests/bootstrap.php',
        // Conflicts with StandaloneLineConstructorParamFixer, which is the wider read.
        StandaloneLinePromotedPropertyFixer::class,
        // They break even short arrays apart, including inside #[Route].
        ArrayListItemNewlineFixer::class,
        ArrayOpenerAndCloserNewlineFixer::class,
        StandaloneLineInMultilineArrayFixer::class,
    ])
    ->withPreparedSets(psr12: true, common: true, cleanCode: true)
    ->withSets([
        __DIR__ . '/vendor/symplify/coding-standard/config/symplify.php',
    ])
    ->withRules([
        NoUnusedImportsFixer::class,
        // LineLengthFixer can leave a trailing comma behind.
        NoTrailingCommaInSinglelineFixer::class,
        // ECS 13 dropped SetList::STRICT; these are the three fixers it held.
        DeclareStrictTypesFixer::class,
        StrictComparisonFixer::class,
        StrictParamFixer::class,
    ])
    // 80, not 120: long signatures break, short ones are never pulled back together.
    ->withConfiguredRule(LineLengthFixer::class, [
        LineLengthFixer::INLINE_SHORT_LINES => false,
        LineLengthFixer::LINE_LENGTH => 80,
    ])
    // Once a call is multiline, every argument gets its own line.
    ->withConfiguredRule(MethodArgumentSpaceFixer::class, [
        'on_multiline' => 'ensure_fully_multiline',
        'keep_multiple_spaces_after_comma' => false,
    ])
    ->withConfiguredRule(TrailingCommaInMultilineFixer::class, [
        'elements' => ['arguments', 'parameters', 'arrays', 'array_destructuring', 'match'],
    ])
    // Keeps @throws: it names the exception the caller is expected to catch.
    ->withConfiguredRule(GeneralPhpdocAnnotationRemoveFixer::class, [
        'annotations' => ['author', 'package', 'group', 'covers', 'category'],
    ]);

<?php

declare(strict_types=1);

use App\Utils\Rector\NameArgumentsRector;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\NoSetupWithParentCallOverrideRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        'src',
        'tests',
    ])
    // Recipe code: what is changed here comes back with the next recipes:update.
    ->withSkip([
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/tests/bootstrap.php',
        // PHPStan rejects the $this->assertSame() this produces.
        PreferPHPUnitThisCallRector::class,
        // It strips #[Override] off a setUp() that does more. Psalm rejects that.
        NoSetupWithParentCallOverrideRector::class,
    ])
    // Every call with more than one argument, no exceptions -- see AGENTS.md on upgrades.
    ->withRules([NameArgumentsRector::class])
    ->withSets([LevelSetList::UP_TO_PHP_85])
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withImportNames();

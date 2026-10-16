<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true)
    // Volt single-file components are not standalone classes Rector should touch.
    ->withSkip([
        __DIR__.'/resources',
        __DIR__.'/bootstrap/cache',
    ]);

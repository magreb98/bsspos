<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_84)
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/app-modules',
    ])
    ->withSkipPath(__DIR__ . '/vendor')
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        LaravelSetList::LARAVEL_130,
    ]);

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest uses PHPUnit\Framework\TestCase by default. Class-based PHPUnit tests
| in this directory are converted with `vendor/bin/pest --drift`.
|
*/

// Preserve the caller's budget without bounding mutation generation between tests.
$previousExecutionLimit = 0;

pest()->in(__DIR__)
    ->beforeEach(function () use (&$previousExecutionLimit): void {
        // Bound each small, local fixture so a parser regression cannot loop forever.
        $previousExecutionLimit = (int) ini_get('max_execution_time');
        set_time_limit(2);
    })
    ->afterEach(function () use (&$previousExecutionLimit): void {
        // Restore the process budget for later tests and mutation-tool generation.
        set_time_limit($previousExecutionLimit);
    });

<?php

/**
 * Guard the temporary class-constant coverage patch in the mutation tool.
 * Remove this guard when the upstream plugin supplies equivalent mapping.
 *
 * @command  composer test -- tests/ConstantMutationCoverageTest.php
 */

declare(strict_types=1);

use Pest\Mutate\Mutation;
use Pest\Mutate\Mutators\Number\DecrementInteger;
use Pest\Mutate\Support\MutationGenerator;
use Symfony\Component\Finder\SplFileInfo;

test('maps constant mutations to their class while retaining executable mutation ranges', function (): void {
    // Keep a method before the constant to require both ends of the class range.
    $source = <<<'PHP'
        <?php
        class CoveredConstants
        {
            public function value(): int { return self::VALUE; }
            #[Threshold(17)]
            public const int VALUE = 7;
            public function other(): int { return 11; }
        }
        class OtherConstants
        {
            public const int VALUE = 13;
        }
        PHP;
    $path = tempnam(sys_get_temp_dir(), 'constant-mutation-');
    if ($path === false) {
        throw new RuntimeException('Cannot create mutation fixture.');
    }

    try {
        // Generate genuine plugin mutants, rather than matching patched source text.
        file_put_contents($path, $source);
        $mutations = new MutationGenerator()->generate(
            new SplFileInfo($path, '', basename($path)),
            [DecrementInteger::class],
        );

        // Leave attributes and methods unchanged; map each initializer to its class.
        expect(array_map(
            static fn (Mutation $mutation): array => [$mutation->startLine, $mutation->endLine],
            $mutations,
        ))->toEqualCanonicalizing([[5, 5], [2, 8], [7, 7], [9, 12]]);
        expect(implode("\n", array_map(
            static fn (Mutation $mutation): string => $mutation->modifiedSource(),
            $mutations,
        )))->toContain('Threshold(16)', 'VALUE = 6', 'return 10;', 'VALUE = 12');
    } finally {
        // Do not leave generated fixtures in the system temporary directory.
        unlink($path);
    }
});

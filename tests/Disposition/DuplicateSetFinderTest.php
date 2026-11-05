<?php

/**
 * Exercise duplicate grouping and public resolution, including malformed inputs.
 *
 * Equivalent mutants are annotated narrowly: two membership-map boolean values
 * are read only by isset; the guarded suffix comparison stays unequal when its
 * hyphen replacement changes or disappears; three array_values calls only
 * discard keys that consumers ignore or usort later reindexes.
 * Linux mutation validation: 173 mutants tested; every killable mutant is dead.
 *
 * @command  composer test -- tests/Disposition/DuplicateSetFinderTest.php
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\DuplicateSetFinder;

/**
 * The duplicate rule decided on `UAMS-Web/uams-statamic#46`: which posts form a set, and which
 * member each set keeps at which slug.
 *
 * @param  list<array{0: int, 1: string, 2?: int, 3?: string, 4?: string, 5?: string}>  $rows  id, slug, words, status, date, title
 * @return list<array{id: int, slug: string, status: string, date: string, title: string, words: int}>
 */
function finderPosts(array $rows): array
{
    return array_map(fn (array $row): array => [
        'id' => $row[0],
        'slug' => $row[1],
        'words' => $row[2] ?? 100,
        'status' => $row[3] ?? 'publish',
        'date' => $row[4] ?? '2009-01-06 10:00:00',
        'title' => $row[5] ?? 'Turnage Named Chairman',
    ], $rows);
}

/**
 * @param  list<array{0: int, 1: string, 2?: int, 3?: string, 4?: string, 5?: string}>  $rows
 * @param  list<array{date: string, slug: string}>  $excluded
 * @return list<array{canonical_slug: string, keep: array{post_id: int, slug: string, date: string}, drop: list<array{post_id: int, slug: string, date: string}>}>
 */
function finderSets(array $rows, array $excluded = []): array
{
    return DuplicateSetFinder::find(finderPosts($rows), $excluded);
}

test('groups a hyphenated slug with its concatenated and -N forms on the same day', function (): void {
    $sets = finderSets([[1, 'turnage-named-chairman'], [2, 'turnagenamedchairman'], [3, 'turnage-named-chairman-2'], [4, 'another-story']]);

    expect($sets)->toHaveCount(1);
    expect(array_column($sets[0]['drop'], 'post_id'))->toBe([2, 3]);
});

test('does not group the same slug form on different days', function (): void {
    expect(finderSets([[1, 'turnage-named-chairman', 100, 'publish', '2009-01-06 10:00:00'], [2, 'turnagenamedchairman', 100, 'publish', '2009-01-07 10:00:00']]))->toBe([]);
});

test('keeps apart two posts joined only by stripping a real trailing number', function (): void {
    $sets = finderSets([
        [1, 'workshop-to-be-held-june-30', 100, 'publish', '2016-06-08 09:00:00', 'Workshop to be held June 30'],
        [2, 'workshop-to-be-held-june-28', 100, 'publish', '2016-06-08 09:30:00', 'Workshop to be held June 28'],
    ]);

    expect($sets)->toBe([]);
});

test('still groups -N forms whose titles match when no member has the unsuffixed slug', function (): void {
    $sets = finderSets([[1, 'super-bowl-champ-visits-2'], [2, 'super-bowl-champ-visits-3']]);

    expect($sets)->toHaveCount(1);
});

test('still groups a -N form beside its unsuffixed slug when the titles differ', function (): void {
    $sets = finderSets([[1, 'cancer-imaging-archive', 100, 'publish', '2015-12-04 09:00:00', 'Cancer Imaging Archive for National Cancer Institute'], [2, 'cancer-imaging-archive-2', 100, 'publish', '2015-12-04 09:00:00', 'Cancer Imaging Archive for NCI']]);

    expect($sets)->toHaveCount(1);
});

test('folds case, whitespace and curly quotes when comparing titles', function (): void {
    $sets = finderSets([
        [1, 'patients-day-2', 100, 'publish', '2009-01-06 10:00:00', "Patients\u{2019}  Day"],
        [2, 'patients-day-3', 100, 'publish', '2009-01-06 10:00:00', "patients' day"],
    ]);

    expect($sets)->toHaveCount(1);
});

test('leaves out a set with any member in the excluded list', function (): void {
    expect(finderSets([[1, 'turnage-named-chairman'], [2, 'turnagenamedchairman']], [['date' => '2009-01-06', 'slug' => 'turnagenamedchairman']]))->toBe([]);
});

test('keeps the most complete published member and gives it the unsuffixed slug', function (): void {
    $set = finderSets([[1, 'turnage-named-chairman', 50], [2, 'turnage-named-chairman-2', 400], [3, 'turnagenamedchairman', 390]])[0];

    expect($set['keep']['post_id'])->toBe(2);
    expect($set['canonical_slug'])->toBe('turnage-named-chairman');
});

test('treats word counts within 2% as equal and then prefers the member at the canonical slug', function (int $otherWords, int $kept): void {
    $set = finderSets([[1, 'turnage-named-chairman', 100], [2, 'turnagenamedchairman', $otherWords]])[0];

    expect($set['keep']['post_id'])->toBe($kept);
})->with([
    'within 2%' => [102, 1],
    'more than 2% fuller' => [103, 2],
]);

test('breaks a remaining tie by the earliest member', function (): void {
    $set = finderSets([
        [1, 'turnage-named-chairman-2', 100, 'publish', '2009-01-06 15:00:00'],
        [2, 'turnage-named-chairman-3', 100, 'publish', '2009-01-06 11:00:00'],
        [3, 'turnage-named-chairman', 10, 'publish', '2009-03-01 10:00:00'],
    ])[0];

    expect($set['keep']['post_id'])->toBe(2);
});

test('never keeps a draft', function (): void {
    $set = finderSets([[1, 'turnage-named-chairman', 50], [2, 'turnage-named-chairman-2', 900, 'draft']])[0];

    expect($set['keep']['post_id'])->toBe(1);
    expect(array_column($set['drop'], 'post_id'))->toBe([2]);
});

test('strips the -N when no member and no live post holds the unsuffixed slug', function (): void {
    $set = finderSets([[1, 'julep-cup-jaunt-2', 300], [2, 'julepcupjaunt', 200]])[0];

    expect($set['canonical_slug'])->toBe('julep-cup-jaunt');
});

test('keeps the -N when a live post elsewhere holds the unsuffixed slug', function (): void {
    $set = finderSets([
        [1, 'defensive-driving-course-4', 100, 'publish', '2009-08-24 10:00:00'],
        [2, 'defensive-driving-course-5', 100, 'publish', '2009-08-24 11:00:00'],
        [3, 'defensive-driving-course', 100, 'publish', '2009-05-01 10:00:00'],
    ])[0];

    expect($set['keep']['post_id'])->toBe(1);
    expect($set['canonical_slug'])->toBe('defensive-driving-course-4');
});

test('a draft holding the unsuffixed slug elsewhere does not stop the strip', function (): void {
    $set = finderSets([
        [1, 'julep-cup-jaunt-2', 300],
        [2, 'julepcupjaunt', 200],
        [3, 'julep-cup-jaunt', 100, 'draft', '2008-01-01 10:00:00'],
    ])[0];

    expect($set['canonical_slug'])->toBe('julep-cup-jaunt');
});

test('gives a kept concatenated post the lowest-numbered hyphenated slug when the unsuffixed one is taken', function (): void {
    $set = finderSets([
        [1, 'turnagenamedchairman', 500],
        [2, 'turnage-named-chairman-3', 100],
        [3, 'turnage-named-chairman-2', 100],
        [4, 'turnage-named-chairman', 100, 'publish', '2008-01-01 10:00:00'],
    ])[0];

    expect($set['keep']['post_id'])->toBe(1);
    expect($set['canonical_slug'])->toBe('turnage-named-chairman-2');
});

test('orders sets by kept day and then canonical slug and sorts dropped ids', function (): void {
    // Supply groups and members in the opposite of their documented output order.
    $sets = finderSets([
        [9, 'z-story', 100, 'publish', '2011-02-03 12:00:00'],
        [8, 'zstory', 80, 'publish', '2011-02-03 13:00:00'],
        [7, 'z-story-2', 70, 'publish', '2011-02-03 14:00:00'],
        [6, 'a-story', 100, 'publish', '2011-02-03 10:00:00'],
        [5, 'astory', 80, 'publish', '2011-02-03 11:00:00'],
        [4, 'old-story', 100, 'publish', '2009-01-06 10:00:00'],
        [3, 'oldstory', 80, 'publish', '2009-01-06 11:00:00'],
    ]);

    expect(array_column($sets, 'canonical_slug'))->toBe(['old-story', 'a-story', 'z-story'])
        ->and($sets[2]['keep'])->toBe(['post_id' => 9, 'slug' => 'z-story', 'date' => '2011-02-03'])
        ->and($sets[2]['drop'])->toBe([
            ['post_id' => 7, 'slug' => 'z-story-2', 'date' => '2011-02-03'],
            ['post_id' => 8, 'slug' => 'zstory', 'date' => '2011-02-03'],
        ]);
});

test('continues after singleton and excluded groups', function (): void {
    $sets = finderSets([
        [1, 'singleton'], [2, 'excluded-story'], [3, 'excludedstory'],
        [4, 'kept-story'], [5, 'keptstory'],
    ], [['date' => '2009-01-06', 'slug' => 'excluded-story']]);

    expect(array_column($sets, 'canonical_slug'))->toBe(['kept-story']);
});

test('keeps valid members after an unrelated numeric suffix fails the guard', function (): void {
    $sets = finderSets([
        [1, 'story-30', 100, 'publish', '2009-01-06 10:00:00', 'Different title'],
        [2, 'sto-ry-2'], [3, 'sto-ry-3'],
    ]);

    expect($sets)->toBe([[
        'canonical_slug' => 'sto-ry',
        'keep' => ['post_id' => 2, 'slug' => 'sto-ry-2', 'date' => '2009-01-06'],
        'drop' => [['post_id' => 3, 'slug' => 'sto-ry-3', 'date' => '2009-01-06']],
    ]]);
});

test('does not emit a duplicate set with no published member', function (): void {
    expect(finderSets([[1, 'draft-story', 100, 'draft'], [2, 'draftstory', 100, 'private']]))->toBe([]);
});

test('keeps an unhyphenated fallback and breaks complete ties by id', function (): void {
    expect(finderSets([[9, 'same'], [2, 'same']]))->toBe([[
        'canonical_slug' => 'same',
        'keep' => ['post_id' => 2, 'slug' => 'same', 'date' => '2009-01-06'],
        'drop' => [['post_id' => 9, 'slug' => 'same', 'date' => '2009-01-06']],
    ]]);
});

test('includes the exact two-percent completeness boundary', function (): void {
    $set = finderSets([[1, 'exact-boundary', 98], [2, 'exactboundary', 100]])[0];

    expect($set['keep']['post_id'])->toBe(1);
});

test('chooses the shortest hyphenated base and keeps the first equally short base', function (string $first, string $second, string $canonical): void {
    $sets = finderSets([[1, $first], [2, $second]]);

    expect($sets[0]['canonical_slug'])->toBe($canonical);
})->with([
    'shorter later' => ['a-b-c', 'ab-c', 'ab-c'],
    'equal lengths' => ['ab-c', 'a-bc', 'ab-c'],
]);

test('sorts fallback suffixes by length before lexical order', function (): void {
    $sets = finderSets([
        [1, 'examplestory', 500], [2, 'example-story-10'], [3, 'example-story-2'],
        [4, 'example-story', 100, 'publish', '2008-01-01 10:00:00'],
    ]);

    expect($sets[0]['canonical_slug'])->toBe('example-story-2');
});

test('folds every curly quote kind and surrounding whitespace in matching titles', function (): void {
    $sets = finderSets([
        [1, 'quote-story-2', 100, 'publish', '2009-01-06 10:00:00', " \t\u{2018}Day\u{2019} \u{201C}ONE\u{201D}\n"],
        [2, 'quote-story-3', 100, 'publish', '2009-01-06 10:00:00', "'day' \"one\""],
    ]);

    expect($sets)->toHaveCount(1);
});

test('does not conflate dates whose years differ only in the first digit', function (): void {
    expect(finderSets([
        [1, 'year-story', 100, 'publish', '1009-01-06 10:00:00'],
        [2, 'yearstory', 100, 'publish', '2009-01-06 10:00:00'],
    ]))->toBe([]);
});

test('groups the same calendar day with either timestamp delimiter', function (): void {
    // Supply different delimiters immediately after the ten-character date.
    $sets = finderSets([
        [1, 'day-story', 100, 'publish', '2009-01-06T10:00:00'],
        [2, 'daystory', 100, 'publish', '2009-01-06 10:00:00'],
    ]);

    // Pin the membership, not merely the number of groups.
    expect($sets)->toBe([[
        'canonical_slug' => 'day-story',
        'keep' => ['post_id' => 1, 'slug' => 'day-story', 'date' => '2009-01-06'],
        'drop' => [['post_id' => 2, 'slug' => 'daystory', 'date' => '2009-01-06']],
    ]]);
});

test('keeps arbitrary date and slug boundaries independent', function (
    string $firstSlug,
    string $firstDate,
    string $secondSlug,
    string $secondDate,
): void {
    // Build two real groups whose undelimited or reordered keys would collide.
    $sets = finderSets([
        [1, $firstSlug, 100, 'publish', $firstDate],
        [2, $firstSlug, 100, 'publish', $firstDate],
        [3, $secondSlug, 100, 'publish', $secondDate],
        [4, $secondSlug, 100, 'publish', $secondDate],
    ]);

    // Index by canonical slug so date ordering does not hide merged membership.
    $bySlug = array_column($sets, null, 'canonical_slug');
    expect($sets)->toHaveCount(2)
        ->and($bySlug[$firstSlug])->toBe([
            'canonical_slug' => $firstSlug,
            'keep' => ['post_id' => 1, 'slug' => $firstSlug, 'date' => $firstDate],
            'drop' => [['post_id' => 2, 'slug' => $firstSlug, 'date' => $firstDate]],
        ])
        ->and($bySlug[$secondSlug])->toBe([
            'canonical_slug' => $secondSlug,
            'keep' => ['post_id' => 3, 'slug' => $secondSlug, 'date' => $secondDate],
            'drop' => [['post_id' => 4, 'slug' => $secondSlug, 'date' => $secondDate]],
        ]);
})->with([
    'removed delimiter or inner swap' => ['ab', 'c2020', 'abc', '2020'],
    'day moved before slug' => ['ab', '2020c', 'cab', '2020'],
]);

test('preserves empty regex fallbacks when PCRE exhausts its backtrack budget', function (): void {
    // Build fixtures before changing the process-wide regex budget.
    $posts = finderPosts([[1, 'first-story-2'], [2, 'second-story-3']]);
    $previous = (string) ini_get('pcre.backtrack_limit');

    // Exercise both public grouping and resolution while regex operations fail.
    try {
        ini_set('pcre.backtrack_limit', '0');
        $key = DuplicateSetFinder::groupKey('first-story-2');
        $error = preg_last_error();
        $sets = DuplicateSetFinder::find($posts, []);
    } finally {
        ini_set('pcre.backtrack_limit', $previous);
    }

    // Restore PCRE before invoking any assertion or test-runner machinery.
    expect($error)->toBe(PREG_BACKTRACK_LIMIT_ERROR)
        ->and($key)->toBe('')
        ->and($sets)->toBe([[
            'canonical_slug' => '',
            'keep' => ['post_id' => 1, 'slug' => 'first-story-2', 'date' => '2009-01-06'],
            'drop' => [['post_id' => 2, 'slug' => 'second-story-3', 'date' => '2009-01-06']],
        ]]);
});

test('folds malformed UTF8 titles to the empty regex fallback for suffix matches', function (): void {
    // Force Unicode whitespace folding to fail for both otherwise different titles.
    $sets = finderSets([
        [1, 'invalid-title-2', 100, 'publish', '2009-01-06 10:00:00', "first\xFF"],
        [2, 'invalid-title-3', 100, 'publish', '2009-01-06 10:00:00', "second\xFE"],
    ]);

    // Pin the observable group produced by the defensive string cast.
    expect($sets)->toBe([[
        'canonical_slug' => 'invalid-title',
        'keep' => ['post_id' => 1, 'slug' => 'invalid-title-2', 'date' => '2009-01-06'],
        'drop' => [['post_id' => 2, 'slug' => 'invalid-title-3', 'date' => '2009-01-06']],
    ]]);
});

test('requires exact slug membership before bypassing the suffix title guard', function (): void {
    expect(finderSets([
        [1, '0e0-2', 100, 'publish', '2009-01-06 10:00:00', 'Title A'],
        [2, '-0e0', 100, 'publish', '2009-01-06 10:00:00', 'Title B'],
    ]))->toBe([]);
});

test('does not treat numeric-looking member slugs as evidence for an occupied base', function (): void {
    // Block the shorter base on another day while retaining the matching-title suffix.
    $sets = finderSets([
        [1, '-0e0', 500],
        [2, '0e0-2', 100],
        [3, '0e0', 100, 'publish', '2008-01-01 10:00:00'],
    ]);

    // Keep the fuller post at its own slug rather than the occupied numeric base.
    expect($sets)->toBe([[
        'canonical_slug' => '-0e0',
        'keep' => ['post_id' => 1, 'slug' => '-0e0', 'date' => '2009-01-06'],
        'drop' => [['post_id' => 2, 'slug' => '0e0-2', 'date' => '2009-01-06']],
    ]]);
});

test('orders equally complete canonical members by timestamp then id across input permutations', function (
    int $first,
    int $second,
    int $third,
    int $fourth,
): void {
    // Put the earliest timestamp and lowest tied id away from either input edge.
    $rows = [
        1 => [1, 'canonical-story', 100, 'publish', '2009-01-06 09:00:00'],
        2 => [2, 'canonical-story', 100, 'publish', '2009-01-06 09:00:00'],
        3 => [3, 'canonical-story', 100, 'publish', '2009-01-06 10:00:00'],
        4 => [4, 'canonical-story', 100, 'publish', '2009-01-06 11:00:00'],
    ];

    // Permute equally ranked canonical slugs to expose asymmetric comparators.
    $sets = finderSets(array_map(fn (int $id): array => $rows[$id], [$first, $second, $third, $fourth]));
    expect($sets)->toBe([[
        'canonical_slug' => 'canonical-story',
        'keep' => ['post_id' => 1, 'slug' => 'canonical-story', 'date' => '2009-01-06'],
        'drop' => [
            ['post_id' => 2, 'slug' => 'canonical-story', 'date' => '2009-01-06'],
            ['post_id' => 3, 'slug' => 'canonical-story', 'date' => '2009-01-06'],
            ['post_id' => 4, 'slug' => 'canonical-story', 'date' => '2009-01-06'],
        ],
    ]]);
})->with([
    'early tied member first internally' => [3, 1, 2, 4],
    'early tied member second internally' => [3, 2, 1, 4],
    'reversed edges and internal tie' => [4, 2, 1, 3],
    'reversed edges' => [4, 1, 2, 3],
]);

test('keeps the shorter fallback suffix when it already precedes the longer one', function (): void {
    // Reverse the existing length-order fixture so an always-positive sort fails.
    $sets = finderSets([
        [1, 'examplestory', 500], [2, 'example-story-2'], [3, 'example-story-10'],
        [4, 'example-story', 100, 'publish', '2008-01-01 10:00:00'],
    ]);

    // Pin the fallback selection independently of the most-complete member.
    expect($sets[0]['canonical_slug'])->toBe('example-story-2')
        ->and($sets[0]['keep']['post_id'])->toBe(1)
        ->and(array_column($sets[0]['drop'], 'post_id'))->toBe([2, 3]);
});

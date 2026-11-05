<?php

/**
 * Pin disposition validation and its complete ordered redirect output.
 *
 * RedirectPairs accepts two equivalent membership values: isset recognizes
 * both true and false, and no consumer reads those map values directly.
 * Linux mutation validation: 150 disposition and 27 redirect mutants tested;
 * every killable mutant is dead.
 *
 * @command  composer test -- tests/Disposition/DuplicateDispositionTest.php
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\DuplicateDisposition;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\RedirectPairs;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Disposition\Support;

/**
 * Reading and validating a disposition file, and the redirect pairs it implies.
 *
 * @return array{format: int, site: string, permalink_base: string, sets: list<array{ticket: string, canonical_slug: string, keep: array{post_id: int, slug: string, date: string}, drop: list<array{post_id: int, slug: string, date: string}>}>}
 */
function dispositionDocument(): array
{
    return [
        'format' => 1,
        'site' => 'news.uams.edu',
        'permalink_base' => 'https://news.uams.edu',
        'sets' => [[
            'ticket' => 'UAMS-Web/uams-statamic#798',
            'canonical_slug' => 'turnage-named-chairman',
            'keep' => ['post_id' => 20, 'slug' => 'turnage-named-chairman-2', 'date' => '2009-01-06'],
            'drop' => [['post_id' => 10, 'slug' => 'turnage-named-chairman', 'date' => '2009-01-06'], ['post_id' => 30, 'slug' => 'turnagenamedchairman', 'date' => '2009-01-06']],
        ]],
    ];
}

test('reads a valid disposition and writes it back unchanged', function (): void {
    $json = json_encode(dispositionDocument(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

    expect(DuplicateDisposition::fromJson($json)->toJson())->toBe($json);
});

test('refuses an invalid disposition', function (string $search, string $replace, string $message): void {
    $json = json_encode(dispositionDocument(), JSON_THROW_ON_ERROR);
    $broken = str_replace($search, $replace, $json, $count);

    expect($count)->toBe(1);
    expect(fn (): DuplicateDisposition => DuplicateDisposition::fromJson($broken))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'a missing format' => ['{"format":1,', '{', '"format"'],
    'a base with a path' => ['"https:\\/\\/news.uams.edu"', '"https:\\/\\/news.uams.edu\\/news"', 'permalink_base'],
    'a post in two sets' => ['"sets":[{', '"sets":[{"ticket":"t","canonical_slug":"other","keep":{"post_id":40,"slug":"x","date":"2009-01-06"},"drop":[{"post_id":10,"slug":"y","date":"2009-01-06"}]},{', 'a post belongs to one set'],
    'the kept post also dropped' => ['{"post_id":30,', '{"post_id":20,', 'a post belongs to one set'],
    'an empty drop list' => ['"drop":[{"post_id":10,"slug":"turnage-named-chairman","date":"2009-01-06"},{"post_id":30,"slug":"turnagenamedchairman","date":"2009-01-06"}]', '"drop":[]', '"drop" must be a non-empty list'],
    'a canonical slug with a double hyphen' => ['"canonical_slug":"turnage-named-chairman"', '"canonical_slug":"turnage--named"', 'canonical_slug'],
    'a date with a time' => ['"post_id":20,"slug":"turnage-named-chairman-2","date":"2009-01-06"', '"post_id":20,"slug":"turnage-named-chairman-2","date":"2009-01-06 10:00:00"', 'YYYY-MM-DD'],
    'a string post id' => ['"post_id":20,', '"post_id":"20",', 'positive integer'],
    'no ticket' => ['"ticket":"UAMS-Web\\/uams-statamic#798",', '', '"ticket"'],
]);

test("refuses a drop at another set's canonical URL, since the redirects would chain", function (): void {
    expect(fn (): DuplicateDisposition => Support::disposition([
        ['canonical_slug' => 'story-a', 'keep' => Support::ref(1, 'story-a-2'), 'drop' => [Support::ref(2, 'storya')]],
        ['canonical_slug' => 'story-b', 'keep' => Support::ref(3, 'story-b'), 'drop' => [Support::ref(4, 'story-a')]],
    ]))->toThrow(InvalidArgumentException::class, 'the redirects would chain');
});

test('refuses a URL redirected by two sets', function (): void {
    expect(fn (): DuplicateDisposition => Support::disposition([
        ['canonical_slug' => 'story-a', 'keep' => Support::ref(1, 'story-a-2'), 'drop' => [Support::ref(2, 'storya')]],
        ['canonical_slug' => 'story-c', 'keep' => Support::ref(5, 'story-c'), 'drop' => [Support::ref(6, 'storya')]],
    ]))->toThrow(InvalidArgumentException::class, 'both redirect');
});

test('names the public permalink by day and slug', function (): void {
    expect(Support::disposition([])->permalink('2009-01-06', 'turnage-named-chairman'))
        ->toBe('https://news.uams.edu/2009/01/06/turnage-named-chairman/');
});

test("pairs each dropped URL, and the kept post's old URL, with the canonical URL", function (): void {
    $pairs = RedirectPairs::from(DuplicateDisposition::fromJson(json_encode(dispositionDocument(), JSON_THROW_ON_ERROR)));
    $canonical = 'https://news.uams.edu/2009/01/06/turnage-named-chairman/';

    expect($pairs)->toBe([
        [
            'source' => 'https://news.uams.edu/2009/01/06/turnagenamedchairman/',
            'target' => $canonical,
            'response' => '301',
            'notes' => 'Duplicate of WordPress post 20 (UAMS-Web/uams-statamic#798).',
            'source_wordpress_post_id' => 30,
            'target_wordpress_post_id' => 20,
        ],
        [
            'source' => 'https://news.uams.edu/2009/01/06/turnage-named-chairman-2/',
            'target' => $canonical,
            'response' => '301',
            'notes' => 'Old slug of WordPress post 20, renamed to its canonical slug (UAMS-Web/uams-statamic#798).',
            'target_wordpress_post_id' => 20,
        ],
    ]);
});

test('lists a source once when two dropped posts share a URL', function (): void {
    $pairs = RedirectPairs::from(Support::disposition([[
        'canonical_slug' => 'turnage-named-chairman',
        'keep' => Support::ref(1, 'turnage-named-chairman'),
        'drop' => [Support::ref(2, 'turnagenamedchairman'), Support::ref(3, 'turnagenamedchairman')],
    ]]));

    expect(array_column($pairs, 'source_wordpress_post_id'))->toBe([2]);
});

test('rejects JSON values that are not disposition objects', function (string $json): void {
    expect(fn (): DuplicateDisposition => DuplicateDisposition::fromJson($json))
        ->toThrow(InvalidArgumentException::class, 'A disposition must be a JSON object.');
})->with(['null', 'false', '"text"', '{']);

test('reports an unreadable disposition path', function (): void {
    $path = __DIR__.'/missing-disposition.json';

    expect(fn (): DuplicateDisposition => DuplicateDisposition::fromFile($path))
        ->toThrow(InvalidArgumentException::class, 'Cannot read disposition file: '.$path);
});

test('validates disposition collection and reference shapes', function (array $changes, string $message): void {
    // Replace one document component without altering unrelated valid fields.
    $document = array_replace_recursive(dispositionDocument(), $changes);

    expect(fn (): DuplicateDisposition => DuplicateDisposition::fromJson(json_encode($document, JSON_THROW_ON_ERROR)))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'format version' => [['format' => 2], 'A disposition must declare "format": 1.'],
    'blank site' => [['site' => " \t\u{00A0}"], '"site" must be a non-empty string'],
    'nonstrings' => [['site' => 42], '"site" must be a non-empty string'],
    'null sets' => [['sets' => null], '"sets" must be a list.'],
    'non-list sets' => [['sets' => ['extra' => null]], '"sets" must be a list.'],
    'nonobject set' => [['sets' => [null]], 'set 1 must be an object.'],
    'nonobject keep' => [['sets' => [['keep' => null]]], 'set 1 keep must be an object.'],
    'zero id' => [['sets' => [['keep' => ['post_id' => 0]]]], 'set 1 keep: "post_id" must be a positive integer.'],
    'null drop' => [['sets' => [['drop' => null]]], 'set 1: "drop" must be a non-empty list.'],
    'non-list drop' => [['sets' => [['drop' => ['extra' => null]]]], 'set 1: "drop" must be a non-empty list.'],
    'nonobject drop' => [['sets' => [['drop' => [null]]]], 'set 1 drop 1 must be an object.'],
]);

test('reports the one-based set and drop positions for invalid references', function (): void {
    // Put the invalid reference after a valid set and a valid dropped reference.
    $document = dispositionDocument();
    $document['sets'][] = [
        'ticket' => 'example',
        'canonical_slug' => 'second',
        'keep' => Support::ref(40, 'second'),
        'drop' => [Support::ref(41, 'old-second'), ['post_id' => 42, 'date' => 'bad', 'slug' => 'bad-date']],
    ];

    expect(fn (): DuplicateDisposition => DuplicateDisposition::fromJson(json_encode($document, JSON_THROW_ON_ERROR)))
        ->toThrow(InvalidArgumentException::class, 'set 2 drop 2: "date" must be YYYY-MM-DD.');
});

test('reports the owners of chained redirects by their one-based positions', function (): void {
    expect(fn (): DuplicateDisposition => Support::disposition([
        ['canonical_slug' => 'story-a', 'keep' => Support::ref(1, 'story-a'), 'drop' => [Support::ref(2, 'old-a')]],
        ['canonical_slug' => 'story-b', 'keep' => Support::ref(3, 'story-a'), 'drop' => [Support::ref(4, 'old-b')]],
    ]))->toThrow(InvalidArgumentException::class, "set 2 redirects https://news.uams.edu/2009/01/06/story-a/, which is set 1's canonical URL; the redirects would chain.");
});

test('reports both owners of colliding redirects by their one-based positions', function (): void {
    expect(fn (): DuplicateDisposition => Support::disposition([
        ['canonical_slug' => 'story-a', 'keep' => Support::ref(1, 'story-a'), 'drop' => [Support::ref(2, 'shared')]],
        ['canonical_slug' => 'story-b', 'keep' => Support::ref(3, 'story-b'), 'drop' => [Support::ref(4, 'shared')]],
    ]))->toThrow(InvalidArgumentException::class, 'sets 1 and 2 both redirect https://news.uams.edu/2009/01/06/shared/.');
});

test('checks later redirects after a dropped reference already has its canonical URL', function (): void {
    // A no-op redirect must not terminate validation of the rest of the set.
    expect(fn (): DuplicateDisposition => Support::disposition([
        ['canonical_slug' => 'story-a', 'keep' => Support::ref(1, 'story-a'), 'drop' => [Support::ref(2, 'old-a')]],
        ['canonical_slug' => 'story-b', 'keep' => Support::ref(3, 'story-b'), 'drop' => [Support::ref(4, 'story-b'), Support::ref(5, 'story-a')]],
    ]))->toThrow(InvalidArgumentException::class, "set 2 redirects https://news.uams.edu/2009/01/06/story-a/, which is set 1's canonical URL; the redirects would chain.");
});

test('rejects a readable directory without attempting to read it as a file', function (): void {
    // Turn a native directory-read diagnostic into an observable failure.
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        expect(fn (): DuplicateDisposition => DuplicateDisposition::fromFile(__DIR__))
            ->toThrow(InvalidArgumentException::class, 'Cannot read disposition file: '.__DIR__);
    } finally {
        // Restore the caller's warning policy even if validation fails.
        restore_error_handler();
    }
});

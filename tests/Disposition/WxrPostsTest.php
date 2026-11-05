<?php

/**
 * Exercise WXR records, HTML word counts and CDATA-aware item boundaries.
 *
 * Accepted equivalents: preg_split limits -2, -1 and 0 are unlimited; the
 * different opening bytes of CDATA and closing-item markers prevent equal
 * offsets; successful field matches always include capture 1; failed matches
 * have no captures, so removing the early return still yields an empty string.
 * Moving the cursor three bytes before rather than after ]]> cannot find a
 * marker there: neither searched marker can overlap the CDATA terminator.
 * The second capture default is not equivalent: empty CDATA needs it. Nor is
 * the regex result cast equivalent: a PCRE resource limit can produce null.
 * Linux mutation validation: 87 mutants tested; every killable mutant is dead.
 *
 * @command  composer test -- tests/Disposition/WxrPostsTest.php
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\WxrPosts;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Disposition\Support;

/**
 * Read disposable exports through the public file API and remove them afterward.
 *
 * @param  string  ...$documents  Export bytes, in file order.
 * @return list<array{id: int, slug: string, status: string, date: string, title: string, words: int}>
 */
function wxrPostsReadFixture(string ...$documents): array
{
    // Allocate independent files even when another test process is running.
    $paths = [];

    try {
        // Preserve the caller's document order without sharing fixed paths.
        foreach ($documents as $document) {
            $path = tempnam(sys_get_temp_dir(), 'wxr-posts-');
            if ($path === false) {
                throw new RuntimeException('Could not create a WXR posts fixture');
            }

            // Register the path before writing so a failed write is cleaned up.
            $paths[] = $path;
            if (file_put_contents($path, $document) !== \strlen($document)) {
                throw new RuntimeException('Could not write a WXR posts fixture');
            }
        }

        // Exercise file reading rather than a private parser entry point.
        return WxrPosts::read($paths);
    } finally {
        // Remove every temporary fixture on both success and failure.
        foreach ($paths as $path) {
            unlink($path);
        }
    }
}

test('reads complete decoded records in file order after non-post items', function (): void {
    // Put a non-post before the first post to distinguish continue from break.
    $first = Support::wxr([
        ['id' => 9, 'slug' => 'image', 'type' => 'attachment'],
        [
            'id' => 42,
            'slug' => 'a-story',
            'status' => 'draft',
            'date' => '2026-11-05 12:34:56',
            'title' => '&apos;A&apos;&Tab;&amp; B',
            'body' => '&lt;p&gt;One&Tab;two&lt;/p&gt;',
        ],
        ['id' => 10, 'slug' => 'page', 'type' => 'page'],
    ]);

    // Check every field and type, including a second export and multiline CDATA.
    expect(wxrPostsReadFixture($first, Support::wxr([
        ['id' => 7, 'slug' => 'later', 'title' => "A\nsecond story", 'body' => '<p>Three words here.</p>'],
    ])))->toBe([
        [
            'id' => 42,
            'slug' => 'a-story',
            'status' => 'draft',
            'date' => '2026-11-05 12:34:56',
            'title' => "'A'\t& B",
            'words' => 2,
        ],
        [
            'id' => 7,
            'slug' => 'later',
            'status' => 'publish',
            'date' => '2009-01-06 10:00:00',
            'title' => "A\nsecond story",
            'words' => 3,
        ],
    ]);
});

test('reads an item at byte zero with plain fields and literal item markup in CDATA', function (): void {
    // Keep the opening item at zero and the closing-item literal inside CDATA.
    $document = '<item><wp:post_type>post</wp:post_type><wp:post_id>0012</wp:post_id>'
        .'<wp:post_name>plain</wp:post_name><wp:status>private</wp:status>'
        .'<wp:post_date>2020-01-02 03:04:05</wp:post_date><title>Fish &amp; Chips</title>'
        .'<content:encoded><![CDATA[one</item>two<p>three</p>]]></content:encoded></item>';

    // Check both CDATA skipping and the plain-text capture branch through read.
    expect(wxrPostsReadFixture($document))->toBe([
        [
            'id' => 12,
            'slug' => 'plain',
            'status' => 'private',
            'date' => '2020-01-02 03:04:05',
            'title' => 'Fish & Chips',
            'words' => 3,
        ],
    ]);
});

test('does not borrow missing fields from a later item', function (): void {
    // Give the first item a nonzero start so a wrong slice length reaches later fields.
    $document = str_repeat('x', 512)
        .'<item><wp:post_type>post</wp:post_type></item>'
        .'<item><wp:post_type>post</wp:post_type><wp:post_id>5</wp:post_id>'
        .'<title><![CDATA[]]></title><wp:post_name></wp:post_name>'
        .'<content:encoded><![CDATA[]]></content:encoded></item>';

    // Missing, empty plain-text and empty CDATA fields all retain empty defaults.
    expect(wxrPostsReadFixture($document))->toBe([
        ['id' => 0, 'slug' => '', 'status' => '', 'date' => '', 'title' => '', 'words' => 0],
        ['id' => 5, 'slug' => '', 'status' => '', 'date' => '', 'title' => '', 'words' => 0],
    ]);
});

test('returns no records for no paths or for documents without posts', function (): void {
    expect(WxrPosts::read([]))->toBe([])
        ->and(wxrPostsReadFixture('', '<rss></rss>', '<item><title>No type</title></item>'))->toBe([]);
});

test('reports a failed file read with its path', function (): void {
    // Derive a nonexistent child of a unique file without assuming permissions.
    $path = tempnam(sys_get_temp_dir(), 'wxr-posts-missing-');
    if ($path === false) {
        throw new RuntimeException('Could not reserve a missing WXR path');
    }

    // Consume only the expected native warning, leaving unrelated errors visible.
    set_error_handler(static fn (int $severity, string $message): bool => $severity === E_WARNING
        && str_starts_with($message, 'file_get_contents('.$path.'/missing.xml):'));

    try {
        // Assert the reader's explicit exception after the failed native read.
        expect(fn (): array => WxrPosts::read([$path.'/missing.xml']))
            ->toThrow(RuntimeException::class, 'Cannot read WXR file: '.$path.'/missing.xml');
    } finally {
        // Restore the handler and release the path even if the assertion fails.
        restore_error_handler();
        unlink($path);
    }
});

test('counts words after replacing tags with spaces', function (
    string $html,
    int $words,
): void {
    expect(WxrPosts::wordCount($html))->toBe($words);
})->with([
    'empty' => ['', 0],
    'only tags' => ['<p></p>', 0],
    'only whitespace' => [" \t\r\n\u{00A0}", 0],
    'one word' => ['word', 1],
    'adjacent tags separate words' => ['<p>one</p><p>two</p>', 2],
    'mixed Unicode whitespace' => ["\u{00A0}one\t two\nthree\u{2003}four\u{00A0}", 4],
    'malformed UTF-8 makes splitting fail' => ["one\xFFtwo", 0],
]);

test('handles an actual regex replacement failure without passing null to trimming', function (): void {
    // Force the fixed replacement regex to exhaust its PCRE resource budget.
    $previous = ini_set('pcre.backtrack_limit', '0');

    try {
        // Preserve the cast's null-to-empty fallback rather than assuming equivalence.
        $words = WxrPosts::wordCount('<p>one</p>');
    } finally {
        // Restore the process setting before the next test or mutation child.
        if ($previous !== false) {
            ini_set('pcre.backtrack_limit', $previous);
        }
    }

    // Assert only after restoring PCRE for the test runner's own regex use.
    expect($words)->toBe(0);
});

test('finds exact exclusive item bounds at or after the supplied offset', function (): void {
    // Include an earlier closing tag to distinguish forward from backward scanning.
    $prefix = '</item>xx';
    $first = '<item><![CDATA[a</item>b]]><![CDATA[c</item>d]]></item>';
    $second = '<item></item>';
    $contents = $prefix.$first.$second.'<![CDATA[after]]>';
    $start = \strlen($prefix);
    $end = $start + \strlen($first);

    // Verify boundaries, inclusive search starts, advancement and trailing CDATA.
    expect(WxrPosts::nextItem($contents, 0))->toBe([$start, $end])
        ->and(WxrPosts::nextItem($contents, $start))->toBe([$start, $end])
        ->and(WxrPosts::nextItem($contents, $start + 1))->toBe([$end, $end + \strlen($second)])
        ->and(WxrPosts::nextItem($contents, $end))->toBe([$end, $end + \strlen($second)])
        ->and(WxrPosts::nextItem($contents, $end + \strlen($second)))->toBeNull()
        ->and(WxrPosts::nextItem('<item></item>', 0))->toBe([0, 13]);
});

test('returns null for incomplete or absent item boundaries', function (string $contents): void {
    expect(WxrPosts::nextItem($contents, 0))->toBeNull();
})->with([
    'empty document' => [''],
    'closing tag only' => ['</item>'],
    'opening tag only' => ['<item>'],
    'unclosed CDATA before a closing item' => ['<item><![CDATA[unfinished</item>'],
    'only closing item is inside CDATA' => ['<item><![CDATA[</item>]]>'],
]);

test('keeps complete records before an incomplete trailing item', function (): void {
    expect(wxrPostsReadFixture('<item><wp:post_type>post</wp:post_type></item><item>'))->toBe([
        ['id' => 0, 'slug' => '', 'status' => '', 'date' => '', 'title' => '', 'words' => 0],
    ]);
});

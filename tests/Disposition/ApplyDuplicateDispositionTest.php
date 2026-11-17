<?php

/**
 * Pin byte-exact post removal, canonical renaming, and refusal diagnostics.
 *
 * Accepted equivalents: loose versus strict one-byte whitespace membership;
 * either non-newline default retains the same end offset; the rewrite guard's
 * conditions always agree; absent field null and empty values are treated alike;
 * successful field matches always populate the first capture.
 * Linux mutation validation: 119 mutants tested; every killable mutant is dead.
 *
 * @command  composer test -- tests/Disposition/ApplyDuplicateDispositionTest.php
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\ApplyDuplicateDisposition;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\WxrPosts;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Disposition\Support;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * The whole-post drop for `UAMS-Web/uams-statamic#46`: posts the disposition marks are removed,
 * a kept post takes its canonical slug, and nothing else in the export changes.
 *
 * @return array{wxr: string, dropped: list<int>, renamed: list<int>}
 */
function dispositionApply(string $wxr): array
{
    $disposition = Support::disposition([
        [
            'canonical_slug' => 'turnage-named-chairman',
            'keep' => Support::ref(20, 'turnage-named-chairman-2'),
            'drop' => [Support::ref(10, 'turnage-named-chairman'), Support::ref(30, 'turnagenamedchairman')],
        ],
    ]);

    return new ApplyDuplicateDisposition($disposition)->apply($wxr);
}

function dispositionExport(): string
{
    return Support::wxr([
        ['id' => 10, 'slug' => 'turnage-named-chairman', 'body' => '<p>Short.</p>'],
        ['id' => 20, 'slug' => 'turnage-named-chairman-2', 'body' => '<p>The full story.</p>'],
        ['id' => 30, 'slug' => 'turnagenamedchairman'],
        ['id' => 40, 'slug' => 'an-unrelated-story'],
        ['id' => 10, 'slug' => 'image-10', 'type' => 'attachment'],
    ]);
}

test('drops the posts it marks and renames the kept post to the canonical slug', function (): void {
    $result = dispositionApply(dispositionExport());
    $posts = WxrPosts::read([dispositionTempFile($result['wxr'])]);

    expect($result['dropped'])->toBe([10, 30]);
    expect($result['renamed'])->toBe([20]);
    expect(array_column($posts, 'slug', 'id'))->toBe([20 => 'turnage-named-chairman', 40 => 'an-unrelated-story']);
});

test('leaves every other item byte-for-byte, including a non-post item sharing a dropped id', function (): void {
    $result = dispositionApply(dispositionExport());

    expect($result['wxr'])->toContain('<wp:post_name><![CDATA[an-unrelated-story]]></wp:post_name>');
    expect($result['wxr'])->toContain('<wp:post_name><![CDATA[image-10]]></wp:post_name>');
    expect($result['wxr'])->toContain('<p>The full story.</p>');
    expect($result['wxr'])->not->toContain('<p>Short.</p>');
    expect(substr_count($result['wxr'], '<item>'))->toBe(3);
});

test('leaves no blank line where a dropped item was', function (): void {
    $result = dispositionApply(dispositionExport());

    expect($result['wxr'])->not->toContain("\n\n\t<item>");
    expect($result['wxr'])->not->toContain("</item>\n\n");
});

test('changes nothing when applied a second time', function (): void {
    $once = dispositionApply(dispositionExport());
    $twice = dispositionApply($once['wxr']);

    expect($twice['wxr'])->toBe($once['wxr']);
    expect($twice['dropped'])->toBe([]);
    expect($twice['renamed'])->toBe([]);
});

test('skips a post the export does not hold, since an export can be split across files', function (): void {
    $result = dispositionApply(Support::wxr([
        ['id' => 20, 'slug' => 'turnage-named-chairman-2'],
        ['id' => 40, 'slug' => 'an-unrelated-story'],
    ]));

    expect($result['dropped'])->toBe([]);
    expect($result['renamed'])->toBe([20]);
});

test('refuses a post whose slug in the export is not the one recorded', function (int $id, string $slug): void {
    dispositionApply(Support::wxr([['id' => $id, 'slug' => $slug]]));
})->with([
    'a dropped post' => [10, 'a-different-story'],
    'a kept post' => [20, 'a-different-story'],
])->throws(RuntimeException::class, 'the disposition records');

test('drops a post whose body contains a literal </item>', function (): void {
    $result = dispositionApply(Support::wxr([
        ['id' => 10, 'slug' => 'turnage-named-chairman', 'body' => '<p>Ends early</item> or not.</p>'],
        ['id' => 40, 'slug' => 'an-unrelated-story'],
    ]));

    expect($result['dropped'])->toBe([10]);
    expect($result['wxr'])->not->toContain('Ends early');
    expect(substr_count($result['wxr'], '<item>'))->toBe(1);
});

test('leaves no blank line where a dropped item was in a CRLF export', function (): void {
    $crlf = str_replace("\n", "\r\n", dispositionExport());
    $result = dispositionApply($crlf);

    expect($result['dropped'])->toBe([10, 30]);
    expect($result['wxr'])->not->toContain("\r\n\r\n\t<item>");
    expect($result['wxr'])->not->toContain("</item>\r\n\r\n");
});

test('the body rules alone never drop a post', function (): void {
    $cleaned = Transform::withDefaultRules()->apply(dispositionExport());

    expect(substr_count($cleaned, '<item>'))->toBe(5);
});

test('removes only the dropped item and its indentation and following newline', function (string $indent, string $newline): void {
    // Place a dropped item between bytes that must survive unchanged.
    $item = '<item><wp:post_id>10</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>turnage-named-chairman</wp:post_name></item>';
    $wxr = 'before'.$newline.$indent.$item.$newline.'after';

    // Compare every byte, including indentation and line endings.
    expect(dispositionApply($wxr))->toBe([
        'wxr' => 'before'.$newline.'after',
        'dropped' => [10],
        'renamed' => [],
    ]);
})->with(['space' => [' '], 'tab' => ["\t"], 'mixed' => [" \t "]])
    ->with(['LF' => ["\n"], 'CRLF' => ["\r\n"], 'no newline' => ['']]);

test('drops an item at the start or end without consuming neighboring bytes', function (string $before, string $after): void {
    $item = '<item><wp:post_id>10</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>turnage-named-chairman</wp:post_name></item>';

    expect(dispositionApply($before.$item.$after)['wxr'])->toBe($before.$after);
})->with([
    'at start' => ['', 'tail'],
    'at start with trailing space' => ['', ' '],
    'at start with trailing tab' => ['', "\t"],
    'at end' => ['head', ''],
    'entire input' => ['', ''],
]);

test('leaves posts with missing or nonnumeric ids and missing types untouched', function (string $fields): void {
    $wxr = '<item>'.$fields.'<wp:post_name>turnage-named-chairman</wp:post_name></item>';

    expect(dispositionApply($wxr))->toBe(['wxr' => $wxr, 'dropped' => [], 'renamed' => []]);
})->with([
    'missing id' => ['<wp:post_type>post</wp:post_type>'],
    'nonnumeric id' => ['<wp:post_id>10suffix</wp:post_id><wp:post_type>post</wp:post_type>'],
    'missing type' => ['<wp:post_id>10</wp:post_id>'],
]);

test('reports a missing or empty slug as empty rather than inventing a value', function (string $slug): void {
    expect(fn (): array => dispositionApply('<item><wp:post_id>10</wp:post_id><wp:post_type>post</wp:post_type>'.$slug.'</item>'))
        ->toThrow(RuntimeException::class, 'Post 10 has slug ""');
})->with(['missing' => [''], 'empty CDATA' => ['<wp:post_name><![CDATA[]]></wp:post_name>']]);

test('compares numeric slugs as strings rather than as numeric values', function (): void {
    $disposition = Support::disposition([[
        'canonical_slug' => 'canonical',
        'keep' => Support::ref(1, '01'),
        'drop' => [Support::ref(2, 'other')],
    ]]);

    expect(fn (): array => new ApplyDuplicateDisposition($disposition)->apply(Support::wxr([['id' => 1, 'slug' => '1']])))
        ->toThrow(RuntimeException::class, 'Post 1 has slug "1"');
});

test('renames only the first slug field of a kept item', function (): void {
    $item = '<item><wp:post_id>20</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>turnage-named-chairman-2</wp:post_name><wp:post_name>second</wp:post_name></item>';

    expect(dispositionApply($item)['wxr'])->toBe(str_replace(
        '<wp:post_name>turnage-named-chairman-2</wp:post_name>',
        '<wp:post_name><![CDATA[turnage-named-chairman]]></wp:post_name>',
        $item,
    ));
});

test('rewrites the slug in the kept post link and nothing else in it', function (string $link, string $expected): void {
    $item = '<item><link>'.$link.'</link><wp:post_id>20</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>turnage-named-chairman-2</wp:post_name></item>';

    expect(dispositionApply($item)['wxr'])->toBe(
        '<item><link>'.$expected.'</link><wp:post_id>20</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name><![CDATA[turnage-named-chairman]]></wp:post_name></item>',
    );
})->with([
    'trailing slash' => ['https://uams-news.test/2015/01/09/turnage-named-chairman-2/', 'https://uams-news.test/2015/01/09/turnage-named-chairman/'],
    'no trailing slash' => ['https://uams-news.test/2015/01/09/turnage-named-chairman-2', 'https://uams-news.test/2015/01/09/turnage-named-chairman'],
    'query string' => ['https://uams-news.test/2015/01/09/turnage-named-chairman-2/?x=1', 'https://uams-news.test/2015/01/09/turnage-named-chairman/?x=1'],
    'fragment' => ['https://uams-news.test/2015/01/09/turnage-named-chairman-2#top', 'https://uams-news.test/2015/01/09/turnage-named-chairman#top'],
    'CDATA' => ['<![CDATA[https://uams-news.test/2015/01/09/turnage-named-chairman-2/]]>', '<![CDATA[https://uams-news.test/2015/01/09/turnage-named-chairman/]]>'],
]);

test('leaves a kept post link alone when its last path segment is not the replaced slug', function (string $link): void {
    $item = '<item><link>'.$link.'</link><wp:post_id>20</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>turnage-named-chairman-2</wp:post_name></item>';

    expect(dispositionApply($item)['wxr'])->toContain('<link>'.$link.'</link>');
})->with([
    'a ?p= permalink' => ['https://uams-news.test/?p=20'],
    'the slug as a prefix of the segment' => ['https://uams-news.test/2015/turnage-named-chairman-2-extra/'],
    'the slug as a suffix of the segment' => ['https://uams-news.test/2015/x-turnage-named-chairman-2/'],
    'the slug in the host' => ['https://turnage-named-chairman-2.test/2015/other/'],
    'a different slug' => ['https://uams-news.test/2015/01/09/something-else/'],
]);

test('rewrites only the first matching link of a kept item and only the kept item', function (): void {
    $result = dispositionApply(
        '<item><link>https://n.test/a/turnage-named-chairman-2/</link><link>https://n.test/b/turnage-named-chairman-2/</link><wp:post_id>20</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>turnage-named-chairman-2</wp:post_name></item>'
        .'<item><link>https://n.test/c/an-unrelated-story/</link><wp:post_id>40</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>an-unrelated-story</wp:post_name></item>',
    );

    expect($result['wxr'])->toContain('<link>https://n.test/a/turnage-named-chairman/</link><link>https://n.test/b/turnage-named-chairman-2/</link>');
    expect($result['wxr'])->toContain('<link>https://n.test/c/an-unrelated-story/</link>');
});

test('leaves a kept post at its canonical slug byte-identical, link included', function (): void {
    $item = '<item><link>https://n.test/a/turnage-named-chairman/</link><wp:post_id>20</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name><![CDATA[turnage-named-chairman]]></wp:post_name></item>';

    expect(dispositionApply($item))->toBe(['wxr' => $item, 'dropped' => [], 'renamed' => []]);
});

test('treats a slug with regex special characters as literal text in the link', function (): void {
    $disposition = Support::disposition([[
        'canonical_slug' => 'canonical',
        'keep' => Support::ref(1, 'a.b+c'),
        'drop' => [Support::ref(2, 'other')],
    ]]);
    $item = '<item><link>https://n.test/aXbbc/</link><link>https://n.test/a.b+c/</link><wp:post_id>1</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>a.b+c</wp:post_name></item>';

    expect(new ApplyDuplicateDisposition($disposition)->apply($item)['wxr'])->toContain('<link>https://n.test/aXbbc/</link><link>https://n.test/canonical/</link>');
});

function dispositionTempFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'wxr-disposition-');
    if ($path === false) {
        throw new RuntimeException('Could not create a temporary file');
    }

    file_put_contents($path, $contents);
    register_shutdown_function(static fn (): bool => @unlink($path));

    return $path;
}

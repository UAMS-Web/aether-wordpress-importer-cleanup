<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\RestoreSplitCaptionCloser;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/restore-split-caption-closer';
    $beforeFiles = glob($fixtureDir.'/*.before.xml');

    if ($beforeFiles === false) {
        return;
    }

    foreach ($beforeFiles as $beforeFile) {
        $afterFile = str_replace('.before.xml', '.after.xml', $beforeFile);

        if (! file_exists($afterFile)) {
            continue;
        }

        $label = basename($beforeFile, '.before.xml');

        yield $label => [$beforeFile, $afterFile];
    }
});
test('fixture round trip', function (string $beforeFile, string $afterFile) {
    $transform = new Transform([
        new RestoreSplitCaptionCloser,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function restoreSplitCaptionCloserBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new RestoreSplitCaptionCloser)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('restore split caption closer rewrites matching markup', function (string $html, string $expected) {
    expect(restoreSplitCaptionCloserBody($html))->toBe($expected);
})->with([
    'closer moves' => ['<p>[caption]<img src="a"> One</p><p>[/caption]Two</p>', '<p>[caption]<img src="a"> One[/caption]</p><p>Two</p>'],
    'whitespace between paragraphs' => ['<p>[caption]a</p>   <p>[/caption]b</p>', '<p>[caption]a[/caption]</p>   <p>b</p>'],
    'leading whitespace in second' => ['<p>[caption]a</p><p>  [/caption]b</p>', '<p>[caption]a[/caption]</p><p>  b</p>'],
    'previous not paragraph then valid' => ['<div>a</div><p>[/caption]x</p><p>y</p><p>[/caption]z</p>', '<div>a</div><p>[/caption]x</p><p>y[/caption]</p><p>z</p>'],
    'two splits' => ['<p>[caption]a</p><p>[/caption]b</p><p>[caption]c</p><p>[/caption]d</p>', '<p>[caption]a[/caption]</p><p>b</p><p>[caption]c[/caption]</p><p>d</p>'],
    'closer only' => ['<p>a</p><p>[/caption]</p>', '<p>a[/caption]</p><p></p>'],
    'previous after blank text' => ["<p>a</p>\n\n  <p>[/caption]b</p>", "<p>a[/caption]</p>\n\n  <p>b</p>"],
]);

test('restore split caption closer leaves other markup alone', function (string $html) {
    expect(restoreSplitCaptionCloserBody($html))->toBe($html);
})->with([
    'previous not paragraph' => ['<div>a</div><p>[/caption]x</p>'],
    'text between' => ['<p>a</p>text<p>[/caption]x</p>'],
    'first paragraph' => ['<p>[/caption]x</p>'],
    'closer not leading' => ['<p>a</p><p>x[/caption]</p>'],
    'leading element' => ['<p>a</p><p><b>y</b>[/caption]</p>'],
    'comment before' => ['<p>a</p><p><!-- c -->[/caption]x</p>'],
    'whitespace then element' => ['<p>a</p><p> <b>y</b></p>'],
    'empty paragraph' => ['<p>a</p><p></p>'],
]);

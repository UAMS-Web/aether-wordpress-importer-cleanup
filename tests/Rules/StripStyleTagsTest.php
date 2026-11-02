<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripStyleTags;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-style-tags';
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
        new StripStyleTags,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function stripStyleTagsBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new StripStyleTags)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('strip style tags rewrites matching markup', function (string $html, string $expected) {
    expect(stripStyleTagsBody($html))->toBe($expected);
})->with([
    'style removed' => ['<p>a</p><style>p{color:red}</style><p>b</p>', '<p>a</p><p>b</p>'],
    'style upper' => ['<p>a</p><STYLE>x</STYLE><p>b</p>', '<p>a</p><p>b</p>'],
    'nested style' => ['<div>a<style>x</style>b</div>', '<div>ab</div>'],
    'two styles' => ['<style>1</style><p>a</p><style>2</style>', '<p>a</p>'],
]);

test('strip style tags leaves other markup alone', function (string $html) {
    expect(stripStyleTagsBody($html))->toBe($html);
})->with([
    'style attr kept' => ['<p style="color:red">a</p>'],
    'no style' => ['<p>a</p>'],
]);

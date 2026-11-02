<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapUnderlines;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-underlines';
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
        new UnwrapUnderlines,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapUnderlinesBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new UnwrapUnderlines)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('unwrap underlines rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapUnderlinesBody($html))->toBe($expected);
})->with([
    'u' => ['<p>a<u>b</u>c</p>', '<p>abc</p>'],
    'nested u' => ['<p><u>a<u>b</u></u></p>', '<p>ab</p>'],
    'u with element' => ['<p><u><b>a</b></u></p>', '<p><b>a</b></p>'],
    'empty u' => ['<p>a<u></u>b</p>', '<p>ab</p>'],
    'two u' => ['<p><u>a</u> <u>b</u></p>', '<p>a b</p>'],
]);

test('unwrap underlines leaves other markup alone', function (string $html) {
    expect(unwrapUnderlinesBody($html))->toBe($html);
})->with([
    'no u' => ['<p>a</p>'],
]);

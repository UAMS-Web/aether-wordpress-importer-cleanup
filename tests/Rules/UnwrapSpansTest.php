<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapSpans;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-spans';
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
        new UnwrapSpans,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapSpansBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapSpans)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap spans rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapSpansBody($html))->toBe($expected);
})->with([
    'span' => ['<p>a<span>b</span>c</p>', '<p>abc</p>'],
    'span attrs' => ['<p><span class="x" style="color:red">b</span></p>', '<p>b</p>'],
    'nested span' => ['<p><span>a<span>b</span></span></p>', '<p>ab</p>'],
    'two spans' => ['<p><span>a</span> <span>b</span></p>', '<p>a b</p>'],
    'empty span' => ['<p>a<span></span>b</p>', '<p>ab</p>'],
    'span with element' => ['<p><span><b>a</b></span></p>', '<p><b>a</b></p>'],
]);

test('unwrap spans leaves other markup alone', function (string $html) {
    expect(unwrapSpansBody($html))->toBe($html);
})->with([
    'no span' => ['<p>a</p>'],
]);

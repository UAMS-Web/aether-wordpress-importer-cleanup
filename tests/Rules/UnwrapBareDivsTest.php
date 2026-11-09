<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapBareDivs;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-bare-divs';
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
        new UnwrapBareDivs,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapBareDivsBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapBareDivs)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap bare divs rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapBareDivsBody($html))->toBe($expected);
})->with([
    'bare div' => ['<div>a</div>', 'a'],
    'nested bare' => ['<div><div>a</div>b</div>', 'ab'],
    'bare in attr' => ['<div class="c"><div>a</div></div>', '<div class="c">a</div>'],
    'bare mixed children' => ['<div>a<b>b</b>c</div>', 'a<b>b</b>c'],
    'empty bare' => ['<p>x</p><div></div><p>y</p>', '<p>x</p><p>y</p>'],
]);

test('unwrap bare divs leaves other markup alone', function (string $html) {
    expect(unwrapBareDivsBody($html))->toBe($html);
})->with([
    'class div kept' => ['<div class="c">a</div>'],
    'id div kept' => ['<div id="i">a</div>'],
    'no div' => ['<p>a</p>'],
]);

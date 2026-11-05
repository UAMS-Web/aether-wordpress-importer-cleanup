<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapRemainingDivs;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-remaining-divs';
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
    // CollapseExtraLineBreaks pairs with this rule to keep newlines
    // bounded; testing in isolation is misleading because the
    // inserted blank-line padding would otherwise stack.
    $transform = new Transform([
        new UnwrapRemainingDivs,
        new CollapseExtraLineBreaks,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapRemainingDivsBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapRemainingDivs)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap remaining divs rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapRemainingDivsBody($html))->toBe($expected);
})->with([
    'bare div' => ['<div>a</div>', "\n\na\n\n"],
    'class div' => ['<div class="c">a</div>', "\n\na\n\n"],
    'nested' => ['<div><div>a</div>b</div>', "\n\n\n\na\n\nb\n\n"],
    'with markup' => ['<div><b>a</b> c</div>', "\n\n<b>a</b> c\n\n"],
    'between paragraphs' => ['<p>x</p><div>a</div><p>y</p>', "<p>x</p>\n\na\n\n<p>y</p>"],
    'empty div' => ['<div></div>', "\n\n\n\n"],
    'two divs' => ['<div>a</div><div>b</div>', "\n\na\n\n\n\nb\n\n"],
]);

test('unwrap remaining divs leaves other markup alone', function (string $html) {
    expect(unwrapRemainingDivsBody($html))->toBe($html);
})->with([
    'no div' => ['<p>a</p>'],
]);

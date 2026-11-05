<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapEmptyBolds;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-empty-bolds';
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
        new UnwrapEmptyBolds,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapEmptyBoldsBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapEmptyBolds)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap empty bolds rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapEmptyBoldsBody($html))->toBe($expected);
})->with([
    'empty b' => ['<p>a<b></b>c</p>', '<p>ac</p>'],
    'whitespace b' => ['<p>a<b>  </b>c</p>', '<p>a  c</p>'],
    'tab newline b' => ["<p>a<b>\t\n</b>c</p>", "<p>a\t\nc</p>"],
    'two b' => ['<p><b> </b>x<b>y</b><b></b></p>', '<p> x<b>y</b></p>'],
    'nested empty' => ['<p><b><b> </b></b></p>', '<p><b> </b></p>'],
]);

test('unwrap empty bolds leaves other markup alone', function (string $html) {
    expect(unwrapEmptyBoldsBody($html))->toBe($html);
})->with([
    'text b kept' => ['<p>a<b>x</b>c</p>'],
    'b with element kept' => ['<p><b><i>x</i></b></p>'],
    'b with empty element kept' => ['<p><b><br></b></p>'],
    'b ws then element' => ['<p><b> <i></i></b></p>'],
    'b element then text' => ['<p><b><i></i> x</b></p>'],
    'no b' => ['<p>a</p>'],
]);

<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\TrimLineEdges;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/trim-line-edges';
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
        new TrimLineEdges,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function trimLineEdgesBody(string $html): string
{
    $document = Fixture::document($html);

    (new TrimLineEdges)->apply($document);

    return Fixture::bodyHtml($document);
}

test('trim line edges rewrites matching markup', function (string $html, string $expected) {
    expect(trimLineEdgesBody($html))->toBe($expected);
})->with([
    'trailing ws before newline' => ["<p>a  \nb</p>", "<p>a\nb</p>"],
    'leading ws after newline' => ["<p>a\n  b</p>", "<p>a\nb</p>"],
    'tabs' => ["<p>a\t\nb</p>", "<p>a\nb</p>"],
    'nbsp' => ["<p>a\u{a0}\nb</p>", "<p>a\nb</p>"],
    'nbsp after' => ["<p>a\n\u{a0}b</p>", "<p>a\nb</p>"],
    'adjacent text merged' => ["<p>a<!-- c -->  \nb</p>", "<p>a<!-- c -->\nb</p>"],
    'span siblings' => ["<p>x:\n\n<b>y</b>  \nz</p>", "<p>x:\n\n<b>y</b>\nz</p>"],
]);

test('trim line edges leaves other markup alone', function (string $html) {
    expect(trimLineEdgesBody($html))->toBe($html);
})->with([
    'no newline kept' => ['<p>a  b</p>'],
    'plain' => ['<p>a</p>'],
]);

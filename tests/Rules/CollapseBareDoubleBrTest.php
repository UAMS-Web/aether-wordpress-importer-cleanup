<?php

declare(strict_types=1);
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseBareDoubleBr;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/collapse-bare-double-br';
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
        new CollapseBareDoubleBr,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

test('whitespace around a double br run is trimmed and the text nodes are merged', function () {
    $document = HTMLDocument::createFromString('<html><body>Foo  <br> <br>  Bar</body></html>', LIBXML_NOERROR);

    (new CollapseBareDoubleBr)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);
    expect($body?->textContent)->toBe("Foo\n\nBar")
        ->and($body?->childNodes->length)->toBe(1);
});

test('a double br run at the start of the body is replaced', function () {
    $document = HTMLDocument::createFromString('<html><body><br><br>Bar</body></html>', LIBXML_NOERROR);

    (new CollapseBareDoubleBr)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);
    expect($body?->textContent)->toBe("\n\nBar")
        ->and($document->getElementsByTagName('br')->length)->toBe(0);
});

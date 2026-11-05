<?php

declare(strict_types=1);
use Dom\Element;
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\LiftTrailingBr;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/lift-trailing-br';
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
        new LiftTrailingBr,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

test('a trailing br is not lifted out of a skipped parent', function (string $tag) {
    // HTML5 drops a cell outside a table, so give td the table it needs.
    $markup = $tag === 'td' ? '<table><tbody><tr><td>x<br></td></tr></tbody></table>' : "<div><{$tag}>x<br></{$tag}></div>";
    $document = Fixture::document($markup);

    (new LiftTrailingBr)->apply($document);

    $parent = $document->getElementsByTagName($tag)->item(0);
    expect($parent?->getElementsByTagName('br')->length)->toBe(1);
})->with(['p', 'li', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']);

test('whitespace after a lifted br is dropped from the old parent', function () {
    $document = HTMLDocument::createFromString("<html><body><div><b>foo<br>  \n </b>x</div></body></html>", LIBXML_NOERROR);

    (new LiftTrailingBr)->apply($document);

    $bold = $document->getElementsByTagName('b')->item(0);
    expect($bold?->textContent)->toBe('foo')
        ->and($bold?->nextSibling)->toBeInstanceOf(Element::class)
        ->and($bold?->nextSibling instanceof Element ? $bold->nextSibling->localName : null)->toBe('br');
});

test('a trailing br in the body is not lifted out of it', function () {
    $document = HTMLDocument::createFromString('<html><body>x<br></body></html>', LIBXML_NOERROR);

    (new LiftTrailingBr)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);
    expect($body?->getElementsByTagName('br')->length)->toBe(1);
});

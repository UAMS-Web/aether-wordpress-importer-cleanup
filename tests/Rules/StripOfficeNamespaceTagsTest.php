<?php

declare(strict_types=1);
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripOfficeNamespaceTags;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-office-namespace-tags';
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
        new StripOfficeNamespaceTags,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * Builds `<p>a{children}b</p>` by hand: prefixed tag names are dropped by some libxml builds when parsed, but
 * createElement() keeps them on every platform, which is what the rule has to handle.
 *
 * @param  list<string>  $tagNames  Element names appended to the paragraph, in order, each holding the text `x`.
 * @param  bool  $nested  Whether each element after the first is nested inside the one before it.
 */
function stripOfficeNamespaceTagsParagraph(array $tagNames, bool $nested = false): string
{
    $document = HTMLDocument::createEmpty();
    $paragraph = $document->createElement('p');
    $document->appendChild($paragraph);
    $paragraph->appendChild($document->createTextNode('a'));

    $container = $paragraph;
    foreach ($tagNames as $tagName) {
        $element = Fixture::element($document, $tagName, 'x');
        $container->appendChild($element);
        if ($nested) {
            $container = $element;
        }
    }

    $paragraph->appendChild($document->createTextNode('b'));

    (new StripOfficeNamespaceTags)->apply($document);

    return $document->saveHtml($paragraph);
}

test('strip office namespace tags removes prefixed elements', function (string $tagName) {
    expect(stripOfficeNamespaceTagsParagraph([$tagName]))->toBe('<p>ab</p>');
})->with([
    'o:p' => ['o:p'],
    'o:lock' => ['o:lock'],
    'v:shape' => ['v:shape'],
    'v:path' => ['v:path'],
    'upper case V' => ['V:Shape'],
    'upper case O' => ['O:P'],
]);

test('strip office namespace tags removes a whole subtree', function () {
    expect(stripOfficeNamespaceTagsParagraph(['v:shape', 'v:path'], nested: true))->toBe('<p>ab</p>');
});

test('strip office namespace tags removes every prefixed element in a paragraph', function () {
    expect(stripOfficeNamespaceTagsParagraph(['o:p', 'v:shape', 'b']))->toBe('<p>a<b>x</b>b</p>');
});

test('strip office namespace tags leaves other elements alone', function (string $tagName) {
    expect(stripOfficeNamespaceTagsParagraph([$tagName]))->toBe("<p>a<{$tagName}>x</{$tagName}>b</p>");
})->with([
    'other prefix' => ['w:span'],
    'unprefixed' => ['b'],
    'prefix inside the name' => ['xo:p'],
]);

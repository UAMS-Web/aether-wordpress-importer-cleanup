<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\MergeAdjacentLists;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/merge-adjacent-lists';
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
        new MergeAdjacentLists,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized body of `$html` after the rule has run
 */
$mergeLists = function (string $html): string {
    $document = new DOMDocument;
    $document->loadHTML('<html><body>'.$html.'</body></html>');

    (new MergeAdjacentLists)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);

    return (string) $document->saveHTML($body);
};

test('adjacent unordered and ordered lists each merge', function (string $tag) use ($mergeLists) {
    expect($mergeLists("<{$tag}><li>a</li></{$tag}><{$tag}><li>b</li></{$tag}>"))
        ->toBe("<body><{$tag}><li>a</li><li>b</li></{$tag}></body>");
})->with(['ul', 'ol']);

test('lists of different types are not merged and later pairs still are', function () use ($mergeLists) {
    expect($mergeLists('<ul><li>a</li></ul><ol><li>x</li></ol><ul><li>b</li></ul><ul><li>c</li></ul>'))
        ->toBe('<body><ul><li>a</li></ul><ol><li>x</li></ol><ul><li>b</li><li>c</li></ul></body>');
});

test('a list followed by text is left alone and later pairs still merge', function () use ($mergeLists) {
    expect($mergeLists('<ul><li>a</li></ul>text<ul><li>b</li></ul><ul><li>c</li></ul>'))
        ->toBe('<body><ul><li>a</li></ul>text<ul><li>b</li><li>c</li></ul></body>');
});

test('an empty element between lists keeps them apart', function () use ($mergeLists) {
    expect($mergeLists('<ul><li>a</li></ul><hr><ul><li>b</li></ul>'))
        ->toBe('<body><ul><li>a</li></ul><hr><ul><li>b</li></ul></body>');
});

test('whitespace between lists and at the start of the second list is dropped', function () use ($mergeLists) {
    expect($mergeLists("<ul>\n<li>a</li>\n</ul>\n<ul>\n<li>b</li>\n</ul>"))
        ->toBe("<body><ul>\n<li>a</li>\n<li>b</li>\n</ul>\n</body>");
});

test('leading text in the second list is kept', function () use ($mergeLists) {
    expect($mergeLists('<ul><li>a</li></ul><ul>keep<li>b</li></ul>'))
        ->toBe('<body><ul><li>a</li>keep<li>b</li></ul></body>');
});

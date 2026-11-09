<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\AddMissingCaptionAttributes;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/add-missing-caption-attributes';
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
        new AddMissingCaptionAttributes,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized body of `$html` after the rule has run
 */
$addAttributes = function (string $html): string {
    $document = Fixture::document($html);

    (new AddMissingCaptionAttributes)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);

    return $document->saveHtml($body);
};

test('every missing opener attribute is prepended in canonical order', function (string $opener, string $expected) use ($addAttributes) {
    expect($addAttributes($opener))->toBe("<body>{$expected}</body>");
})->with([
    'none present' => ['[caption]', '[caption id="" align="aligncenter" width="300"]'],
    'width present' => ['[caption width="5"]', '[caption id="" align="aligncenter" width="5"]'],
    'align present' => ['[caption align="x"]', '[caption id="" width="300" align="x"]'],
    'id present' => ['[caption id="7"]', '[caption align="aligncenter" width="300" id="7"]'],
    'id and width present' => ['[caption id="7" width="5"]', '[caption align="aligncenter" id="7" width="5"]'],
    'align and width present' => ['[caption align="x" width="5"]', '[caption id="" align="x" width="5"]'],
    'id and align present' => ['[caption id="7" align="x"]', '[caption width="300" id="7" align="x"]'],
    'all present' => ['[caption id="7" align="x" width="5"]', '[caption id="7" align="x" width="5"]'],
    'a longer name is not the attribute' => ['[caption data-id="7"]', '[caption id="" align="aligncenter" width="300" data-id="7"]'],
]);

test('text around openers and closers is preserved', function () use ($addAttributes) {
    expect($addAttributes('a [caption]b[/caption] c [caption id="1" align="x" width="2"]d[/caption] e'))
        ->toBe('<body>a [caption id="" align="aligncenter" width="300"]b[/caption] c [caption id="1" align="x" width="2"]d[/caption] e</body>');
});

test('a closer before the first opener is handled in order', function () use ($addAttributes) {
    expect($addAttributes('x[/caption]y[caption]z'))
        ->toBe('<body>x[/caption]y[caption id="" align="aligncenter" width="300"]z</body>');
});

test('an opener with no closing bracket passes through unchanged', function () use ($addAttributes) {
    expect($addAttributes('a [caption id="1" b'))->toBe('<body>a [caption id="1" b</body>');
});

test('an image inside a caption gets a default width and the state follows the markers', function () use ($addAttributes) {
    expect($addAttributes('<img src="a">[caption id="1" align="x" width="2"]<img src="b"><img src="c" width="9">[/caption]<img src="d">'))
        ->toBe('<body><img src="a">[caption id="1" align="x" width="2"]<img src="b" width="300"><img src="c" width="9">[/caption]<img src="d"></body>');
});

test('caption state persists across element boundaries', function () use ($addAttributes) {
    expect($addAttributes('<p>[caption id="1" align="x" width="2"]</p><div><img src="a"></div><p>[/caption]</p><img src="b">'))
        ->toBe('<body><p>[caption id="1" align="x" width="2"]</p><div><img src="a" width="300"></div><p>[/caption]</p><img src="b"></body>');
});

test('a comment inside a caption is left alone', function () use ($addAttributes) {
    expect($addAttributes('[caption]<!-- c --><img src="a">[/caption]'))
        ->toBe('<body>[caption id="" align="aligncenter" width="300"]<!-- c --><img src="a" width="300">[/caption]</body>');
});

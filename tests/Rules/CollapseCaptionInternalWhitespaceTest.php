<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseCaptionInternalWhitespace;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/collapse-caption-internal-whitespace';
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
        new CollapseCaptionInternalWhitespace,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized body of `$html` after the rule has run
 */
$collapseCaption = function (string $html): string {
    $document = Fixture::document($html);

    (new CollapseCaptionInternalWhitespace)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);

    return $document->saveHtml($body);
};

test('breaks and whitespace runs inside a caption collapse to one space and outside text is kept', function (string $html, string $expected) use ($collapseCaption) {
    expect($collapseCaption($html))->toBe("<body>{$expected}</body>");
})->with([
    'break and newline' => ["[caption id=\"1\"]a<br>\n b  c[/caption]  x   y", '[caption id="1"]a b c[/caption]  x   y'],
    'break outside a caption' => ['a<br>b  c[caption]d[/caption]', 'a<br>b  c[caption]d[/caption]'],
    'break after a closer' => ['[caption]a[/caption]<br>b', '[caption]a[/caption]<br>b'],
    'closer before opener' => ['x   y[/caption]p   q[caption]r   s', 'x   y[/caption]p   q[caption]r s'],
    'break after closer then opener' => ['x[/caption]y[caption]<br>z', 'x[/caption]y[caption] z'],
    'opener attributes are not normalized' => ['[caption  id="1"   align="x"]a  b[/caption]', '[caption  id="1"   align="x"]a b[/caption]'],
    'malformed opener passes through' => ['[caption id="1"  a   b', '[caption id="1"  a   b'],
    'malformed opener after text' => ['pre  x [caption id="1"  a   b', 'pre  x [caption id="1"  a   b'],
    'markup inside a caption' => ['[caption]<b>a   b</b><i>c<br>d</i>[/caption] e  f', '[caption]<b>a b</b><i>c d</i>[/caption] e  f'],
    'two captions' => ['[caption]a  b[/caption] m  n [caption]c  d[/caption]', '[caption]a b[/caption] m  n [caption]c d[/caption]'],
    'comment inside a caption' => ['[caption]a<!-- c -->b<br>c[/caption]', '[caption]a<!-- c -->b c[/caption]'],
    'every whitespace kind' => ["[caption]a\t\r\n  b[/caption]", '[caption]a b[/caption]'],
]);

test('caption state carries across paragraphs', function () use ($collapseCaption) {
    expect($collapseCaption('<p>[caption]one</p><p>two<br>three  four</p><p>[/caption]five  six</p>'))
        ->toBe('<body><p>[caption]one</p><p>two three four</p><p>[/caption]five  six</p></body>');
});

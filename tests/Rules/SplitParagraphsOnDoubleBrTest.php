<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\SplitParagraphsOnDoubleBr;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/split-paragraphs-on-double-br';
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
        new SplitParagraphsOnDoubleBr,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function splitParagraphsOnDoubleBrBody(string $html): string
{
    $document = Fixture::document($html);

    (new SplitParagraphsOnDoubleBr)->apply($document);

    return Fixture::bodyHtml($document);
}

test('split paragraphs on double br rewrites matching markup', function (string $html, string $expected) {
    expect(splitParagraphsOnDoubleBrBody($html))->toBe($expected);
})->with([
    'double br splits' => ['<p>One<br><br>Two</p>', "<p>One</p>\n<p>Two</p>"],
    'three brs split once' => ['<p>One<br><br><br>Two</p>', "<p>One</p>\n<p>Two</p>"],
    'whitespace between brs' => ['<p>One<br> <br>Two</p>', "<p>One</p>\n<p>Two</p>"],
    'leading whitespace group dropped' => ['<p>   <br><br>x<br><br>y</p>', "<p>x</p>\n<p>y</p>"],
    'trailing whitespace node trimmed' => ['<p><b>x</b> <br><br>y</p>', "<p><b>x</b></p>\n<p>y</p>"],
    'leading whitespace trimmed' => ['<p>One<br><br>   Two</p>', "<p>One</p>\n<p>Two</p>"],
    'trailing whitespace trimmed' => ['<p>A  <br><br>B  </p>', "<p>A</p>\n<p>B</p>"],
    'empty element at group start' => ['<p>A<br><br><i></i>B</p>', "<p>A</p>\n<p><i></i>B</p>"],
    'three groups' => ['<p>A<br><br>B<br><br>C</p>', "<p>A</p>\n<p>B</p>\n<p>C</p>"],
    'markup in groups' => ['<p><b>A</b> one<br><br><i>B</i> two</p>', "<p><b>A</b> one</p>\n<p><i>B</i> two</p>"],
    'in div' => ['<div><p>A<br><br>B</p></div>', "<div><p>A</p>\n<p>B</p></div>"],
    'two paragraphs' => ['<p>A<br><br>B</p><p>C<br><br>D</p>', "<p>A</p>\n<p>B</p><p>C</p>\n<p>D</p>"],
    'single br then double' => ['<p>A<br>B<br><br>C</p>', "<p>A<br>B</p>\n<p>C</p>"],
]);

test('split paragraphs on double br leaves other markup alone', function (string $html) {
    expect(splitParagraphsOnDoubleBrBody($html))->toBe($html);
})->with([
    'single br kept' => ['<p>One<br>Two</p>'],
    'only brs' => ['<p><br><br></p>'],
    'trailing double br' => ['<p>One<br><br></p>'],
    'leading double br' => ['<p><br><br>One</p>'],
    'no br' => ['<p>One</p>'],
    'break outside paragraph' => ['A<br><br>B'],
]);

test('split paragraphs leaves no empty text node at the edges of a new paragraph', function () {
    $document = Fixture::document('<p><b>A</b> <br><br> <b>B</b></p>');

    (new SplitParagraphsOnDoubleBr)->apply($document);

    $paragraphs = $document->getElementsByTagName('p');
    expect($paragraphs->length)->toBe(2)
        ->and($paragraphs->item(0)?->childNodes->length)->toBe(1)
        ->and($paragraphs->item(1)?->childNodes->length)->toBe(1);
});

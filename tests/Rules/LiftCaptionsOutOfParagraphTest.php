<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\LiftCaptionsOutOfParagraph;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/lift-captions-out-of-paragraph';
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
    // CollapseExtraLineBreaks pairs with this rule -- the lift
    // emits stacked `\n\n` text nodes around the chunk sequence
    // that the collapse normalizes back to a single blank line.
    $transform = new Transform([
        new LiftCaptionsOutOfParagraph,
        new CollapseExtraLineBreaks,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

test('captions are lifted out of their paragraph', function (string $html, string $expected) {
    $document = new DOMDocument;
    $document->loadHTML('<html><body>'.$html.'</body></html>');

    (new LiftCaptionsOutOfParagraph)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);
    expect($document->saveHTML($body))->toBe("<body>{$expected}</body>");
})->with([
    'docblock example' => ["<p>\n[caption]<img src=\"a\"> Foo[/caption]\nLorem ipsum.\n[caption]<img src=\"b\"> Baz[/caption]\n</p>", "\n\n[caption]<img src=\"a\"> Foo[/caption]\n\n<p>Lorem ipsum.</p>\n\n[caption]<img src=\"b\"> Baz[/caption]\n\n"],
    'stacked captions take one newline' => ["<p>[caption]a[/caption] [caption]b[/caption]</p>", "\n\n[caption]a[/caption]\n[caption]b[/caption]\n\n"],
    'whitespace between captions is dropped' => ["<p>[caption]a[/caption]\n  \n[caption]b[/caption]</p>", "\n\n[caption]a[/caption]\n[caption]b[/caption]\n\n"],
    'a break splits prose' => ["<p>Intro<br>More [caption]x[/caption]</p>", "\n\n<p>Intro</p>\n\n<p>More</p>\n\n[caption]x[/caption]\n\n"],
    'an uppercase break splits prose' => ["<p>A<BR>B[caption]x[/caption]</p>", "\n\n<p>A</p>\n\n<p>B</p>\n\n[caption]x[/caption]\n\n"],
    'a break inside a caption stays' => ["<p>[caption]a<br>b[/caption]</p>", "\n\n[caption]a<br>b[/caption]\n\n"],
    'text after a closer becomes prose' => ["<p>[caption]x[/caption] tail</p>", "\n\n[caption]x[/caption]\n\n<p>tail</p>\n\n"],
    'leading whitespace around an element is trimmed' => ["<p>  <i>x</i>  [caption]c[/caption]</p>", "\n\n<p><i>x</i></p>\n\n[caption]c[/caption]\n\n"],
    'a caption spans text and elements' => ["<p>pre [caption]<img src=\"a\"> a <b>b</b> c[/caption] post</p>", "\n\n<p>pre</p>\n\n[caption]<img src=\"a\"> a <b>b</b> c[/caption]\n\n<p>post</p>\n\n"],
    'an element after a caption starts prose' => ["<p>[caption]x[/caption]<b>y</b></p>", "\n\n[caption]x[/caption]\n\n<p><b>y</b></p>\n\n"],
    'text and elements share one prose chunk' => ["<p>one <b>two</b> three [caption]x[/caption]</p>", "\n\n<p>one <b>two</b> three</p>\n\n[caption]x[/caption]\n\n"],
    'markers inside child elements are not lifted' => ["<p><b>[caption]</b>x<b>[/caption]</b></p>", "\n\n<p><b>[caption]</b>x<b>[/caption]</b></p>\n\n"],
]);

test('a paragraph with unbalanced markers is left alone', function () {
    $document = new DOMDocument;
    $document->loadHTML('<html><body><p>[caption]x</p></body></html>');

    (new LiftCaptionsOutOfParagraph)->apply($document);

    expect($document->saveHTML($document->getElementsByTagName('body')->item(0)))->toBe('<body><p>[caption]x</p></body>');
});

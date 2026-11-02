<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapCaptionOnlyParagraph;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-caption-only-paragraph';
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
    // CollapseExtraLineBreaks pairs with this rule -- the unwrap
    // emits `\n\n` text nodes before and after each removed `<p>`
    // that the collapse normalizes back to a single blank line.
    $transform = new Transform([
        new UnwrapCaptionOnlyParagraph,
        new CollapseExtraLineBreaks,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapCaptionOnlyParagraphBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new UnwrapCaptionOnlyParagraph)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('unwrap caption only paragraph rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapCaptionOnlyParagraphBody($html))->toBe($expected);
})->with([
    'caption' => ['<p>[caption id="1"]<img src="a.jpg"> text[/caption]</p>', "\n\n[caption id=\"1\"]<img src=\"a.jpg\"> text[/caption]\n\n"],
    'padded' => ['<p>  [caption]x[/caption]  </p>', "\n\n  [caption]x[/caption]  \n\n"],
    'between paragraphs' => ['<p>a</p><p>[caption]x[/caption]</p><p>b</p>', "<p>a</p>\n\n[caption]x[/caption]\n\n<p>b</p>"],
    'two paragraphs' => ['<p>[caption]x[/caption]</p><p>[caption]y[/caption]</p>', "\n\n[caption]x[/caption]\n\n\n\n[caption]y[/caption]\n\n"],
    'with markup' => ['<p>[caption]<a href="u"><img src="a.jpg"></a> <b>t</b>[/caption]</p>', "\n\n[caption]<a href=\"u\"><img src=\"a.jpg\"></a> <b>t</b>[/caption]\n\n"],
]);

test('unwrap caption only paragraph leaves other markup alone', function (string $html) {
    expect(unwrapCaptionOnlyParagraphBody($html))->toBe($html);
})->with([
    'leading text' => ['<p>a [caption]x[/caption]</p>'],
    'trailing text' => ['<p>[caption]x[/caption] a</p>'],
    'two captions' => ['<p>[caption]x[/caption][caption]y[/caption]</p>'],
    'nested caption' => ['<p>[caption][caption]x[/caption]</p>'],
    'two closers' => ['<p>[caption]x[/caption][/caption]</p>'],
    'no closer' => ['<p>[caption]x</p>'],
    'no opener' => ['<p>x[/caption]</p>'],
    'plain paragraph' => ['<p>hello</p>'],
]);

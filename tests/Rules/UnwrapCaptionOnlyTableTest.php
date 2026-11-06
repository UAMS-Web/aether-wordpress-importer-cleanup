<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapCaptionOnlyTable;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-caption-only-table';
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
        new UnwrapCaptionOnlyTable,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapCaptionOnlyTableBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapCaptionOnlyTable)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap caption only table rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapCaptionOnlyTableBody($html))->toBe($expected);
})->with([
    'single caption cell' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td></tr></tbody></table>', "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n"],
    'padded caption cell' => ["<table><tbody><tr><td>  \n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption] </td></tr></tbody></table>", "\n  \n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption] \n"],
    'two caption cells' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td><td>[caption]second[/caption]</td></tr></tbody></table>', "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n\n[caption]second[/caption]\n"],
    'caption and empty cell' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td><td></td></tr></tbody></table>', "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n"],
    'caption and br cell' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td><td><br></td></tr></tbody></table>', "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n"],
    'caption and whitespace cell' => ["<table><tbody><tr><td>[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]</td><td> \n </td></tr></tbody></table>", "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n"],
    'caption and nbsp cell' => ["<table><tbody><tr><td>[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]</td><td>\u{a0}</td></tr></tbody></table>", "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n"],
    'empty cell first' => ['<table><tbody><tr><td></td><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td></tr></tbody></table>', "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n"],
    'whitespace then markup in cell' => ["<table><tbody><tr><td> \n <b>[caption]x[/caption]</b></td></tr></tbody></table>", "\n<b>[caption]x[/caption]</b>\n"],
    'caption in two rows' => ['<table><tbody><tr><td>[caption]a[/caption]</td></tr><tr><td>[caption]b[/caption]</td></tr></tbody></table>', "\n[caption]a[/caption]\n\n[caption]b[/caption]\n"],
    'between paragraphs' => ['<p>a</p><table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td></tr></tbody></table><p>b</p>', "<p>a</p>\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n<p>b</p>"],
    'two tables' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td></tr></tbody></table><table><tbody><tr><td>[caption]x[/caption]</td></tr></tbody></table>', "\n[caption id=\"1\"]<img src=\"i.jpg\"> text[/caption]\n\n[caption]x[/caption]\n"],
]);

test('unwrap caption only table leaves other markup alone', function (string $html) {
    expect(unwrapCaptionOnlyTableBody($html))->toBe($html);
})->with([
    'only empty cells' => ['<table><tbody><tr><td></td><td><br></td></tr></tbody></table>'],
    'only one empty cell' => ['<table><tbody><tr><td></td></tr></tbody></table>'],
    'cell with image only' => ['<table><tbody><tr><td><img src="i.jpg"></td></tr></tbody></table>'],
    'caption and image cell' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td><td><img src="i.jpg"></td></tr></tbody></table>'],
    'caption and span cell' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td><td><span></span></td></tr></tbody></table>'],
    'caption and text cell' => ['<table><tbody><tr><td>[caption id="1"]<img src="i.jpg"> text[/caption]</td><td>plain</td></tr></tbody></table>'],
    'no opener' => ['<table><tbody><tr><td>x[/caption]</td></tr></tbody></table>'],
    'no closer' => ['<table><tbody><tr><td>[caption]x</td></tr></tbody></table>'],
    'text before opener' => ['<table><tbody><tr><td>a [caption]x[/caption]</td></tr></tbody></table>'],
    'text after closer' => ['<table><tbody><tr><td>[caption]x[/caption] a</td></tr></tbody></table>'],
    'two openers' => ['<table><tbody><tr><td>[caption][caption]x[/caption]</td></tr></tbody></table>'],
    'two closers' => ['<table><tbody><tr><td>[caption]x[/caption][/caption]</td></tr></tbody></table>'],
    'two captions in a cell' => ['<table><tbody><tr><td>[caption]x[/caption][caption]y[/caption]</td></tr></tbody></table>'],
    'no cells' => ['<table><tbody><tr><th>[caption]x[/caption]</th></tr></tbody></table>'],
    'plain table' => ['<table><tbody><tr><td>a</td></tr></tbody></table>'],
    'nested table' => ['<table><tbody><tr><td>[caption]<table><tbody><tr><td>x</td></tr></tbody></table>[/caption]</td></tr></tbody></table>'],
]);

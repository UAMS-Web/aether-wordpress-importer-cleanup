<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapCaptionImageAnchors;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-caption-image-anchors';
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
        new UnwrapCaptionImageAnchors,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapCaptionImageAnchorsBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new UnwrapCaptionImageAnchors)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('unwrap caption image anchors rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapCaptionImageAnchorsBody($html))->toBe($expected);
})->with([
    'anchor in caption' => ['[caption id="1"]<a href="u"><img src="i.jpg"></a> text[/caption]', '[caption id="1"]<img src="i.jpg"> text[/caption]'],
    'anchor with whitespace' => ["[caption]<a href=\"u\"> \n<img src=\"i.jpg\"> </a>[/caption]", "[caption] \n<img src=\"i.jpg\"> [/caption]"],
    'closer then opener in one text' => ['[/caption] [caption <a href="u"><img src="i.jpg"></a>', '[/caption] [caption <img src="i.jpg">'],
    'opener closer opener in one text' => ['[caption]a[/caption]b[caption]<a href="u"><img src="i.jpg"></a>', '[caption]a[/caption]b[caption]<img src="i.jpg">'],
    'two openers in one text' => ['[caption][caption]<a href="u"><img src="i.jpg"></a>[/caption]', '[caption][caption]<img src="i.jpg">[/caption]'],
    'anchor nested in caption element' => ['[caption]<b><a href="u"><img src="i.jpg"></a></b>[/caption]', '[caption]<b><img src="i.jpg"></b>[/caption]'],
    'comment before anchor' => ['[caption]<!-- c --><a href="u"><img src="i.jpg"></a>[/caption]', '[caption]<!-- c --><img src="i.jpg">[/caption]'],
    'two anchors' => ['[caption]<a href="1"><img src="i.jpg"></a> <a href="2"><img src="i.jpg"></a>[/caption]', '[caption]<img src="i.jpg"> <img src="i.jpg">[/caption]'],
]);

test('unwrap caption image anchors leaves other markup alone', function (string $html) {
    expect(unwrapCaptionImageAnchorsBody($html))->toBe($html);
})->with([
    'anchor outside caption' => ['<a href="u"><img src="i.jpg"></a>'],
    'anchor before caption' => ['<a href="u"><img src="i.jpg"></a>[caption]x[/caption]'],
    'anchor after caption' => ['[caption]x[/caption]<a href="u"><img src="i.jpg"></a>'],
    'anchor after closer without opener' => ['[/caption]<a href="u"><img src="i.jpg"></a>'],
    'opener then closer in one text' => ['[caption] [/caption] <a href="u"><img src="i.jpg"></a>'],
    'two closers in one text' => ['[caption]x[/caption][/caption]<a href="u"><img src="i.jpg"></a>'],
    'caption state spans elements' => ['[caption]<b>x</b>[/caption]<a href="u"><img src="i.jpg"></a>'],
    'anchor with two images' => ['[caption]<a href="u"><img src="i.jpg"><img src="i.jpg"></a>[/caption]'],
    'anchor with image then text' => ['[caption]<a href="u"><img src="i.jpg"> x</a>[/caption]'],
    'anchor with text then image' => ['[caption]<a href="u">x <img src="i.jpg"></a>[/caption]'],
    'anchor with image and element' => ['[caption]<a href="u"><img src="i.jpg"><b>x</b></a>[/caption]'],
    'anchor with element only' => ['[caption]<a href="u"><b>x</b></a>[/caption]'],
    'anchor with comment' => ['[caption]<a href="u"><!-- c --><img src="i.jpg"></a>[/caption]'],
    'empty anchor' => ['[caption]<a href="u"></a>[/caption]'],
    'text-only anchor' => ['[caption]<a href="u">x</a>[/caption]'],
    'non-anchor in caption' => ['[caption]<span><img src="i.jpg"></span>[/caption]'],
    'no caption' => ['<p>plain</p>'],
]);

test('unwrap caption image anchors matches element names regardless of case', function () {
    $document = new DOMDocument('1.0', 'UTF-8');
    $body = $document->createElement('body');
    $document->appendChild($body);
    $body->appendChild($document->createTextNode('[caption]'));
    $anchor = $document->createElement('A');
    $anchor->appendChild($document->createElement('IMG'));
    $body->appendChild($anchor);
    $body->appendChild($document->createTextNode('[/caption]'));

    (new UnwrapCaptionImageAnchors)->apply($document);

    expect($anchor->parentNode)->toBeNull()
        ->and($body->getElementsByTagName('IMG')->length)->toBe(1);
});

test('unwrap caption image anchors ignores a document without a body', function () {
    $document = new DOMDocument;

    (new UnwrapCaptionImageAnchors)->apply($document);

    expect($document->documentElement)->toBeNull();
});

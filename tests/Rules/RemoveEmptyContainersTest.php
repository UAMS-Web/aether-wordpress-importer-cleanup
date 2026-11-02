<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\RemoveEmptyContainers;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/remove-empty-containers';
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
        new RemoveEmptyContainers,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function removeEmptyContainersBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new RemoveEmptyContainers)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('remove empty containers rewrites matching markup', function (string $html, string $expected) {
    expect(removeEmptyContainersBody($html))->toBe($expected);
})->with([
    'empty paragraph' => ['<p></p>', ''],
    'whitespace paragraph' => ['<p>   </p>', ''],
    'nbsp paragraph' => ['<p>&nbsp;</p>', ''],
    'breaks only' => ['<p><strong><br><br>  </strong></p>', ''],
    'deep nesting' => ['<div><p><strong>  </strong></p></div>', ''],
    'text sibling kept' => ['<p></p><p>x</p>', '<p>x</p>'],
    'empty span in text' => ['a<span></span>b', 'ab'],
    'empty list item' => ['<ul><li></li><li>x</li></ul>', '<ul><li>x</li></ul>'],
]);

test('remove empty containers leaves other markup alone', function (string $html) {
    expect(removeEmptyContainersBody($html))->toBe($html);
})->with([
    'text kept' => ['<p>x</p>'],
    'image kept' => ['<p><img src="a"></p>'],
    'rule kept' => ['<div><hr></div>'],
    'anchor kept' => ['<p><a name="x"></a></p>'],
    'empty table kept' => ['<div><table><tr><td></td></tr></table></div>'],
    'cell kept' => ['<table><tr><td> </td><td>x</td></tr></table>'],
    'only whitespace body' => ['   '],
]);

test('remove empty containers keeps every preserved element', function (string $tag) {
    $document = new DOMDocument;
    $document->loadHTML('<html><body></body></html>');
    $body = $document->getElementsByTagName('body')->item(0);
    $body?->appendChild($document->createElement($tag));

    (new RemoveEmptyContainers)->apply($document);

    expect($document->getElementsByTagName($tag)->length)->toBe(1);
})->with(['head', 'title', 'br', 'hr', 'img', 'input', 'embed', 'source', 'track', 'wbr', 'col', 'colgroup', 'link', 'meta', 'base', 'param', 'area', 'iframe', 'object', 'audio', 'video', 'svg', 'canvas', 'button', 'select', 'textarea', 'a', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption']);

test('remove empty containers keeps the document structure of an empty page', function () {
    $document = new DOMDocument;
    $document->loadHTML('<html><head><title></title></head><body></body></html>');

    (new RemoveEmptyContainers)->apply($document);

    expect($document->getElementsByTagName('html')->length)->toBe(1)
        ->and($document->getElementsByTagName('head')->length)->toBe(1)
        ->and($document->getElementsByTagName('title')->length)->toBe(1)
        ->and($document->getElementsByTagName('body')->length)->toBe(1);
});

test('remove empty containers keeps a container that wraps content only', function (string $tag) {
    $document = new DOMDocument;
    $document->loadHTML('<html><body><div></div></body></html>');
    $div = $document->getElementsByTagName('div')->item(0);
    $div?->appendChild($document->createElement($tag));

    (new RemoveEmptyContainers)->apply($document);

    expect($document->getElementsByTagName('div')->length)->toBe(1);
})->with(['img', 'iframe', 'embed', 'object', 'audio', 'video', 'svg', 'canvas', 'input', 'button', 'select', 'textarea', 'hr', 'a', 'table']);

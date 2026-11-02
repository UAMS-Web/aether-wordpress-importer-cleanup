<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripTableAttributes;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-table-attributes';
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
        new StripTableAttributes,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function stripTableAttributesBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new StripTableAttributes)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('strip table attributes rewrites matching markup', function (string $html, string $expected) {
    expect(stripTableAttributesBody($html))->toBe($expected);
})->with([
    'table all' => ['<table height="1" width="2" border="3" cellpadding="4" cellspacing="5"><tbody><tr><td>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'table height' => ['<table height="1"><tbody><tr><td>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'table width' => ['<table width="1"><tbody><tr><td>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'table border' => ['<table border="1"><tbody><tr><td>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'table cellpadding' => ['<table cellpadding="1"><tbody><tr><td>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'table cellspacing' => ['<table cellspacing="1"><tbody><tr><td>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'td valign' => ['<table><tbody><tr><td valign="top">x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'ul type' => ['<ul type="disc"><li>x</li></ul>', '<ul><li>x</li></ul>'],
    'two tables' => ['<table width="1"><tbody><tr><td>x</td></tr></tbody></table><table width="2"><tbody><tr><td>y</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table><table><tbody><tr><td>y</td></tr></tbody></table>'],
    'ul second' => ['<ul type="a"><li>x</li></ul><ul type="b"><li>y</li></ul>', '<ul><li>x</li></ul><ul><li>y</li></ul>'],
]);

test('strip table attributes leaves other markup alone', function (string $html) {
    expect(stripTableAttributesBody($html))->toBe($html);
})->with([
    'ol type kept' => ['<ol type="a"><li>x</li></ol>'],
    'td width kept' => ['<table><tbody><tr><td width="5">x</td></tr></tbody></table>'],
    'table valign kept' => ['<table valign="top"><tbody><tr><td>x</td></tr></tbody></table>'],
    'ul valign kept' => ['<ul valign="top"><li>x</li></ul>'],
    'other attrs kept' => ['<table class="c" id="i"><tbody><tr><td class="d">x</td></tr></tbody></table>'],
]);

test('strip table attributes uses a custom attribute map', function () {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body><p class="a" id="b">x</p><table width="1"><tbody><tr><td>y</td></tr></tbody></table></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new StripTableAttributes(['p' => ['class']]))->apply($document);

    expect($document->saveHTML($document->getElementsByTagName('p')->item(0)))->toBe('<p id="b">x</p>')
        ->and($document->saveHTML($document->getElementsByTagName('table')->item(0)))->toContain('width="1"');
});

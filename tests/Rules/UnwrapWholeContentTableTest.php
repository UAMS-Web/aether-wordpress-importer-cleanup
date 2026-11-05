<?php

declare(strict_types=1);
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapWholeContentTable;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-whole-content-table';
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
    // emits stacked `\n\n` text nodes around each removed wrapper
    // that the collapse normalizes back to a single blank line.
    $transform = new Transform([
        new UnwrapWholeContentTable,
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
function unwrapWholeContentTableBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapWholeContentTable)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap whole content table rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapWholeContentTableBody($html))->toBe($expected);
})->with([
    'tbody table' => ['<table><tbody><tr><td>a</td></tr></tbody></table>', "\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n"],
    'direct rows' => ['<table><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td></tr></tbody></table>', "\n\n\n\n\n\n\n\na\n\n\n\nb\n\n\n\n\n\n\n\nc\n\n\n\n\n\n\n\n"],
    'whitespace around' => ["  \n<table><tbody><tr><td>a</td></tr></tbody></table>\n  ", "  \n\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n\n  "],
    'markup in cells' => ['<table><tbody><tr><td><b>a</b> c</td><td><p>d</p></td></tr></tbody></table>', "\n\n\n\n\n\n\n\n<b>a</b> c\n\n\n\n<p>d</p>\n\n\n\n\n\n\n\n"],
    'nested table kept' => ['<table><tbody><tr><td><table><tbody><tr><td>n</td></tr></tbody></table></td></tr></tbody></table>', "\n\n\n\n\n\n\n\n<table><tbody><tr><td>n</td></tr></tbody></table>\n\n\n\n\n\n\n\n"],
    'nested table with sibling cell' => ['<table><tbody><tr><td><table><tbody><tr><td>n</td></tr></tbody></table></td><td>m</td></tr></tbody></table>', "\n\n\n\n\n\n\n\n<table><tbody><tr><td>n</td></tr></tbody></table>\n\n\n\nm\n\n\n\n\n\n\n\n"],
    'structure inside wrapper' => ['<table><tbody><tr><td><div><span>a</span></div></td></tr></tbody></table>', "\n\n\n\n\n\n\n\n<div><span>a</span></div>\n\n\n\n\n\n\n\n"],
    'thead kept' => ['<table><thead><tr><th>h</th></tr></thead><tbody><tr><td>a</td></tr></tbody></table>', "\n\n<thead>\n\n<th>h</th>\n\n</thead>\n\n\n\n\n\na\n\n\n\n\n\n\n\n"],
    'th kept' => ['<table><tbody><tr><th>h</th><td>a</td></tr></tbody></table>', "\n\n\n\n\n\n<th>h</th>\n\na\n\n\n\n\n\n\n\n"],
    'tfoot rows' => ['<table><tbody><tr><td>a</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>', "\n\n\n\n\n\n\n\na\n\n\n\n\n\n<tfoot>\n\n\n\nf\n\n\n\n</tfoot>\n\n"],
    'comment inside table' => ['<table><tbody><!-- c --><tr><td>a</td></tr></tbody></table>', "\n\n\n\n<!-- c -->\n\n\n\na\n\n\n\n\n\n\n\n"],
    'attributes' => ['<table class="t"><tbody class="b"><tr class="r"><td class="c">a</td></tr></tbody></table>', "\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n"],
    'empty table' => ['<table></table>', "\n\n\n\n"],
]);

test('unwrap whole content table leaves other markup alone', function (string $html) {
    expect(unwrapWholeContentTableBody($html))->toBe($html);
})->with([
    'text beside table' => ['text<table><tbody><tr><td>a</td></tr></tbody></table>'],
    'text after table' => ['<table><tbody><tr><td>a</td></tr></tbody></table>text'],
    'two tables' => ['<table><tbody><tr><td>a</td></tr></tbody></table><table><tbody><tr><td>b</td></tr></tbody></table>'],
    'element beside table' => ['<p>x</p><table><tbody><tr><td>a</td></tr></tbody></table>'],
    'comment beside table' => ['<!-- c --><table><tbody><tr><td>a</td></tr></tbody></table>'],
    'only a paragraph' => ['<p>a</p>'],
    'only a div' => ['<div><table><tbody><tr><td>a</td></tr></tbody></table></div>'],
    'only whitespace' => [" \n "],
    'empty body' => [''],
    'only text' => ['plain'],
]);

test('unwrap whole content table matches element names regardless of case', function () {
    $document = HTMLDocument::createEmpty();
    $body = $document->createElement('body');
    $document->appendChild($body);
    $table = $document->createElement('TABLE');
    $body->appendChild($table);
    $tbody = $document->createElement('TBODY');
    $table->appendChild($tbody);
    $row = $document->createElement('TR');
    $tbody->appendChild($row);
    $row->appendChild(Fixture::element($document, 'TD', 'a'));

    (new UnwrapWholeContentTable)->apply($document);

    expect($body->getElementsByTagName('TABLE')->length)->toBe(0)
        ->and($body->getElementsByTagName('TD')->length)->toBe(0)
        ->and(trim((string) $body->textContent))->toBe('a');
});

test('unwrap whole content table ignores a document without a body', function () {
    $document = HTMLDocument::createEmpty();

    (new UnwrapWholeContentTable)->apply($document);

    expect($document->documentElement)->toBeNull();
});

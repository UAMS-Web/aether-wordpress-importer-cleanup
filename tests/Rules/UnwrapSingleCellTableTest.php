<?php

declare(strict_types=1);
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapSingleCellTable;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-single-cell-table';
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
        new UnwrapSingleCellTable,
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
function unwrapSingleCellTableBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapSingleCellTable)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap single cell table rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapSingleCellTableBody($html))->toBe($expected);
})->with([
    'tbody table' => ['<table><tbody><tr><td>a</td></tr></tbody></table>', "\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n"],
    'markup in cell' => ['<table><tbody><tr><td><b>a</b> c<br>d</td></tr></tbody></table>', "\n\n\n\n\n\n\n\n<b>a</b> c<br>d\n\n\n\n\n\n\n\n"],
    'empty cell' => ['<table><tbody><tr><td></td></tr></tbody></table>', "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n"],
    'attributes kept off' => ['<table class="t" width="9"><tbody><tr><td class="c">a</td></tr></tbody></table>', "\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n"],
    'between paragraphs' => ['<p>x</p><table><tbody><tr><td>a</td></tr></tbody></table><p>y</p>', "<p>x</p>\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n<p>y</p>"],
    'rejected then accepted' => ['<table><tbody><tr><td>a</td><td>b</td></tr></tbody></table><table><tbody><tr><td>c</td></tr></tbody></table>', "<table><tbody><tr><td>a</td><td>b</td></tr></tbody></table>\n\n\n\n\n\n\n\nc\n\n\n\n\n\n\n\n"],
    'accepted then rejected' => ['<table><tbody><tr><td>c</td></tr></tbody></table><table><tbody><tr><td>a</td><td>b</td></tr></tbody></table>', "\n\n\n\n\n\n\n\nc\n\n\n\n\n\n\n\n<table><tbody><tr><td>a</td><td>b</td></tr></tbody></table>"],
    'nested single cell' => ['<table><tbody><tr><td><table><tbody><tr><td>a</td></tr></tbody></table></td></tr></tbody></table>', "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n"],
    'single cell in multi' => ['<table><tbody><tr><td><table><tbody><tr><td>a</td></tr></tbody></table></td><td>b</td></tr></tbody></table>', "<table><tbody><tr><td>\n\n\n\n\n\n\n\na\n\n\n\n\n\n\n\n</td><td>b</td></tr></tbody></table>"],
]);

test('unwrap single cell table leaves other markup alone', function (string $html) {
    expect(unwrapSingleCellTableBody($html))->toBe($html);
})->with([
    'two tbodies' => ['<table><tbody><tr><td>a</td></tr></tbody><tbody><tr><td>b</td></tr></tbody></table>'],
    'with thead' => ['<table><thead><tr><td>h</td></tr></thead><tbody><tr><td>a</td></tr></tbody></table>'],
    'with tfoot' => ['<table><tbody><tr><td>a</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>'],
    'thead only' => ['<table><thead><tr><td>h</td></tr></thead></table>'],
    'tfoot only' => ['<table><tfoot><tr><td>f</td></tr></tfoot></table>'],
    'two rows' => ['<table><tbody><tr><td>a</td></tr><tr><td>b</td></tr></tbody></table>'],
    'two cells' => ['<table><tbody><tr><td>a</td><td>b</td></tr></tbody></table>'],
    'th cell' => ['<table><tbody><tr><th>a</th></tr></tbody></table>'],
    'no rows' => ['<table><tbody></tbody></table>'],
    'empty table' => ['<table></table>'],
    'no cells' => ['<table><tbody><tr></tr></tbody></table>'],
]);

test('unwrap single cell table matches child element names regardless of case', function () {
    $document = HTMLDocument::createEmpty();
    $body = $document->createElement('body');
    $document->appendChild($body);
    $table = $document->createElement('table');
    $body->appendChild($table);
    $tbody = $document->createElement('TBODY');
    $table->appendChild($tbody);
    $row = $document->createElement('TR');
    $tbody->appendChild($row);
    $row->appendChild(Fixture::element($document, 'TD', 'a'));

    (new UnwrapSingleCellTable)->apply($document);

    expect($body->getElementsByTagName('table')->length)->toBe(0)
        ->and(trim((string) $body->textContent))->toBe('a');
});

test('unwrap single cell table keeps a table that has both a body and direct rows', function () {
    $document = Fixture::document('<table><tbody><tr><td>a</td></tr></tbody></table>');
    $table = Fixture::first($document, 'table');
    $row = $document->createElement('tr');
    $cell = $document->createElement('td');
    $cell->appendChild($document->createTextNode('b'));
    $row->appendChild($cell);
    $table->appendChild($row);
    $before = Fixture::bodyHtml($document);

    (new UnwrapSingleCellTable)->apply($document);

    expect(Fixture::bodyHtml($document))->toBe($before);
});

<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\TrimBlockEdges;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/trim-block-edges';
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
        new TrimBlockEdges,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function trimBlockEdgesBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new TrimBlockEdges)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('trim block edges rewrites matching markup', function (string $html, string $expected) {
    expect(trimBlockEdgesBody($html))->toBe($expected);
})->with([
    'p' => ["<p> \n x \n</p>", '<p>x</p>'],
    'li' => ['<ul><li> x </li></ul>', '<ul><li>x</li></ul>'],
    'td' => ['<table><tbody><tr><td> x </td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
    'h1' => ['<h1> x </h1>', '<h1>x</h1>'],
    'h2' => ['<h2> x </h2>', '<h2>x</h2>'],
    'h3' => ['<h3> x </h3>', '<h3>x</h3>'],
    'h4' => ['<h4> x </h4>', '<h4>x</h4>'],
    'h5' => ['<h5> x </h5>', '<h5>x</h5>'],
    'h6' => ['<h6> x </h6>', '<h6>x</h6>'],
    'leading br' => ['<p><br><br>x</p>', '<p>x</p>'],
    'trailing br' => ['<p>x<br><br></p>', '<p>x</p>'],
    'ws then br' => ['<p> <br> x <br> </p>', '<p>x</p>'],
    'nbsp edges' => ["<p>\u{a0}x\u{a0}</p>", '<p>x</p>'],
    'only ws' => ['<p>   </p>', '<p></p>'],
    'two blocks' => ['<p> a </p><p> b </p>', '<p>a</p><p>b</p>'],
]);

test('trim block edges leaves other markup alone', function (string $html) {
    expect(trimBlockEdgesBody($html))->toBe($html);
})->with([
    'div kept' => ['<div> x </div>'],
    'inner br kept' => ['<p>a<br>b</p>'],
    'element first' => ['<p><b> x </b></p>'],
]);

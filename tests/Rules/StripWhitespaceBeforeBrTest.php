<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripWhitespaceBeforeBr;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-whitespace-before-br';
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
        new StripWhitespaceBeforeBr,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function stripWhitespaceBeforeBrBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new StripWhitespaceBeforeBr)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('strip whitespace before br rewrites matching markup', function (string $html, string $expected) {
    expect(stripWhitespaceBeforeBrBody($html))->toBe($expected);
})->with([
    'trailing spaces' => ['<p>a  <br>b</p>', '<p>a<br>b</p>'],
    'tabs newlines' => ["<p>a\t\n\r <br>b</p>", '<p>a<br>b</p>'],
    'only whitespace' => ['<p> <br>b</p>', '<p><br>b</p>'],
    'element then later br' => ['<p><b>x</b><br>y  <br>z</p>', '<p><b>x</b><br>y<br>z</p>'],
    'two brs' => ['<p>a <br>b <br>c</p>', '<p>a<br>b<br>c</p>'],
]);

test('strip whitespace before br leaves other markup alone', function (string $html) {
    expect(stripWhitespaceBeforeBrBody($html))->toBe($html);
})->with([
    'after element' => ['<p><b>x</b><br>y</p>'],
    'no space' => ['<p>a<br>b</p>'],
    'leading kept' => ['<p>  a<br>b</p>'],
    'nbsp kept' => ["<p>a\u{a0}<br>b</p>"],
    'first child br' => ['<p><br>b</p>'],
]);

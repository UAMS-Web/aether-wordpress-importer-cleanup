<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\PromoteBoldToInnerStrong;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/promote-bold-to-inner-strong';
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
        new PromoteBoldToInnerStrong,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function promoteBoldToInnerStrongBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new PromoteBoldToInnerStrong)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('promote bold to inner strong rewrites matching markup', function (string $html, string $expected) {
    expect(promoteBoldToInnerStrongBody($html))->toBe($expected);
})->with([
    'b then p' => ['<b></b><p>x</p>', '<p><strong>x</strong></p>'],
    'whitespace between' => ['<b> </b>   <p>x <i>y</i></p>', '<p><strong>x <i>y</i></strong></p>'],
    'non-p after then pair' => ['<b></b><div>x</div><b></b><p>y</p>', '<b></b><div>x</div><p><strong>y</strong></p>'],
    'b with element then pair' => ['<b><i>x</i></b><p>y</p><b></b><p>z</p>', '<b><i>x</i></b><p>y</p><p><strong>z</strong></p>'],
    'two pairs' => ['<b></b><p>a</p><b></b><p>b</p>', '<p><strong>a</strong></p><p><strong>b</strong></p>'],
    'comment in b' => ['<b> <!-- c --> </b><p>x</p>', '<p><strong>x</strong></p>'],
    'p with attributes' => ['<b></b><p class="k">x</p>', '<p class="k"><strong>x</strong></p>'],
]);

test('promote bold to inner strong leaves other markup alone', function (string $html) {
    expect(promoteBoldToInnerStrongBody($html))->toBe($html);
})->with([
    'b with text' => ['<b>t</b><p>x</p>'],
    'b with element' => ['<b><i>x</i></b><p>y</p>'],
    'nothing after' => ['<b></b>'],
    'text after' => ['<b></b>tail<p>x</p>'],
    'b inside p' => ['<p><b></b></p>'],
    'whitespace then non-p' => ['<b></b>  <span>x</span>'],
]);

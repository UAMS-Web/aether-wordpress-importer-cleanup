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
    $document = Fixture::document($html);

    (new PromoteBoldToInnerStrong)->apply($document);

    return Fixture::bodyHtml($document);
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
    'p nested in b by an HTML5 parser' => ["<b>\n<p>x</p>\n</b>", "<p><strong>x</strong></p>\n"],
    'two p nested in one b' => ['<b><p>a</p> <p>b</p></b>', '<p><strong>a</strong></p> <p><strong>b</strong></p>'],
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
    'b with a p and text' => ['<b>t<p>x</p></b>'],
    'b with a p and another element' => ['<b><p>x</p><i>y</i></b>'],
    'whitespace then non-p' => ['<b></b>  <span>x</span>'],
]);

<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\ReplaceCurlyQuotes;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/replace-curly-quotes';
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
        new ReplaceCurlyQuotes,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function replaceCurlyQuotesBody(string $html): string
{
    $document = Fixture::document($html);

    (new ReplaceCurlyQuotes)->apply($document);

    return Fixture::bodyHtml($document);
}

test('replace curly quotes rewrites matching markup', function (string $html, string $expected) {
    expect(replaceCurlyQuotesBody($html))->toBe($expected);
})->with([
    'right double' => ["a \u{201d} b", 'a " b'],
    'left double' => ["a \u{201c} b", 'a " b'],
    'right single' => ["a \u{2019} b", 'a \' b'],
    'left single' => ["a \u{2018} b", 'a \' b'],
    'mixed' => ["\u{201c}it\u{2019}s\u{201d} and \u{2018}x\u{2019}", '"it\'s" and \'x\''],
    'nested element text' => ["<p>\u{201c}a\u{201d}<b>\u{2018}b\u{2019}</b></p>", '<p>"a"<b>\'b\'</b></p>'],
    'attribute untouched' => ["<a title=\"\u{201c}x\u{201d}\">\u{201c}y\u{201d}</a>", "<a title=\"\u{201c}x\u{201d}\">\"y\"</a>"],
]);

test('replace curly quotes leaves other markup alone', function (string $html) {
    expect(replaceCurlyQuotesBody($html))->toBe($html);
})->with([
    'plain text' => ['it\'s "fine"'],
    'empty' => [''],
    'en dash kept' => ["a \u{2013} b"],
]);

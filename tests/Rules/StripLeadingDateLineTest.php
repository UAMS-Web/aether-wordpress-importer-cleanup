<?php

declare(strict_types=1);
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripLeadingDateLine;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-leading-date-line';
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
        new StripLeadingDateLine,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function stripLeadingDateLineBody(string $html): string
{
    $document = Fixture::document($html);

    (new StripLeadingDateLine)->apply($document);

    return Fixture::bodyHtml($document);
}

test('strip leading date line rewrites matching markup', function (string $html, string $expected) {
    expect(stripLeadingDateLineBody($html))->toBe($expected);
})->with([
    'abbreviated month' => ['Nov. 12, 2002 The foo.', ' The foo.'],
    'full month' => ['November 12, 2002 The foo.', ' The foo.'],
    'ordinal' => ['Nov. 1st, 2002 The foo.', ' The foo.'],
    'ordinal th' => ['Nov. 12th, 2002 The foo.', ' The foo.'],
    'uppercase' => ['NOV. 12, 2002 The foo.', ' The foo.'],
    'pipe separator' => ['Nov. 12, 2002 | The foo.', 'The foo.'],
    'em dash separator' => ["Nov. 12, 2002 \u{2014} The foo.", 'The foo.'],
    'en dash separator' => ["Nov. 12, 2002 \u{2013} The foo.", 'The foo.'],
    'hyphen separator' => ['Nov. 12, 2002 - The foo.', 'The foo.'],
    'date only' => ['Nov. 12, 2002', ''],
    'date only then element' => ['Nov. 12, 2002<p>The foo.</p>', '<p>The foo.</p>'],
    'leading whitespace' => ['   Nov. 12, 2002 The foo.', ' The foo.'],
    'in paragraph' => ['<p>Nov. 12, 2002 The foo.</p>', '<p> The foo.</p>'],
    'paragraph date only' => ['<p>Nov. 12, 2002</p><p>The foo.</p>', '<p>The foo.</p>'],
    'paragraph date only with whitespace' => ["<p>Nov. 12, 2002</p>\n  <p>The foo.</p>", '<p>The foo.</p>'],
    'whitespace before paragraph' => ["  \n <p>Nov. 12, 2002</p> \n <p>The foo.</p>", '<p>The foo.</p>'],
    'paragraph with markup after' => ['<p>Nov. 12, 2002<b>x</b></p>', '<p><b>x</b></p>'],
    'paragraph with leading whitespace' => ['<p>  Nov. 12, 2002 The foo.</p>', '<p> The foo.</p>'],
    'one digit day' => ['Nov. 2, 2002 The foo.', ' The foo.'],
    'sept' => ['Sept. 4, 2002 x', ' x'],
    'sep' => ['Sep. 4, 2002 x', ' x'],
    'january' => ['January 4, 2002 x', ' x'],
    'jan' => ['Jan. 4, 2002 x', ' x'],
    'feb' => ['Feb. 4, 2002 x', ' x'],
    'february' => ['February 4, 2002 x', ' x'],
    'march' => ['March 4, 2002 x', ' x'],
    'april' => ['April 4, 2002 x', ' x'],
    'may' => ['May 4, 2002 x', ' x'],
    'june' => ['June 4, 2002 x', ' x'],
    'july' => ['July 4, 2002 x', ' x'],
    'aug' => ['Aug. 4, 2002 x', ' x'],
    'august' => ['August 4, 2002 x', ' x'],
    'october' => ['October 4, 2002 x', ' x'],
    'oct' => ['Oct. 4, 2002 x', ' x'],
    'december' => ['December 4, 2002 x', ' x'],
    'dec' => ['Dec. 4, 2002 x', ' x'],
    'september' => ['September 4, 2002 x', ' x'],
    'november' => ['November 4, 2002 x', ' x'],
    'nd and rd' => ['Nov. 2nd, 2002 x', ' x'],
    'rd' => ['Nov. 3rd, 2002 x', ' x'],
]);

test('strip leading date line leaves other markup alone', function (string $html) {
    expect(stripLeadingDateLineBody($html))->toBe($html);
})->with([
    'paragraph starting with element' => ['<p><b>Nov. 12, 2002</b> x</p>'],
    'empty paragraph' => ['<p></p>'],
    'not at start' => ['The foo. Nov. 12, 2002'],
    'no year' => ['Nov. 12 The foo.'],
    'three digit day' => ['Nov. 123, 2002 The foo.'],
    'second paragraph untouched' => ['<p>x</p><p>Nov. 12, 2002 y</p>'],
    'heading untouched' => ['<h1>Nov. 12, 2002 y</h1>'],
    'empty body' => [''],
    'idempotent text' => ['The foo.'],
]);

test('strip leading date line ignores a document without a body', function () {
    $document = HTMLDocument::createEmpty();

    (new StripLeadingDateLine)->apply($document);

    expect($document->documentElement)->toBeNull();
});

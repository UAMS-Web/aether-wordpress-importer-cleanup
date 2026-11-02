<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripOrdinalSupTags;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-ordinal-sup-tags';
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
        new StripOrdinalSupTags,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function stripOrdinalSupTagsBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    (new StripOrdinalSupTags)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('strip ordinal sup tags rewrites matching markup', function (string $html, string $expected) {
    expect(stripOrdinalSupTagsBody($html))->toBe($expected);
})->with([
    'th' => ['<p>the 17<sup>th</sup> city</p>', '<p>the 17th city</p>'],
    'st' => ['<p>1<sup>st</sup></p>', '<p>1st</p>'],
    'nd' => ['<p>2<sup>nd</sup></p>', '<p>2nd</p>'],
    'rd' => ['<p>3<sup>rd</sup></p>', '<p>3rd</p>'],
    'upper' => ['<p>4<sup>TH</sup></p>', '<p>4TH</p>'],
    'padded' => ['<p>4<sup> th </sup></p>', '<p>4 th </p>'],
    'letter before' => ['<p>a7<sup>th</sup></p>', '<p>a7th</p>'],
    'two' => ['<p>1<sup>st</sup> and 2<sup>nd</sup></p>', '<p>1st and 2nd</p>'],
    'nested sup' => ['<p>5<sup><em>th</em></sup></p>', '<p>5<em>th</em></p>'],
]);

test('strip ordinal sup tags leaves other markup alone', function (string $html) {
    expect(stripOrdinalSupTagsBody($html))->toBe($html);
})->with([
    'letter last' => ['<p>7a<sup>th</sup></p>'],
    'no previous' => ['<p><sup>th</sup></p>'],
    'element before' => ['<p><b>7</b><sup>th</sup></p>'],
    'br before' => ['<p>7<br><sup>th</sup></p>'],
    'not ordinal' => ['<p>7<sup>2+</sup></p>'],
    'not digit' => ['<p>x<sup>th</sup></p>'],
    'comment before' => ['<p>7<!-- c --><sup>th</sup></p>'],
]);

test('strip ordinal sup tags looks past an empty text node', function () {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body><p>7<sup>th</sup></p></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $paragraph = $document->getElementsByTagName('p')->item(0);
    $sup = $document->getElementsByTagName('sup')->item(0);
    $paragraph?->insertBefore($document->createTextNode(''), $sup);

    (new StripOrdinalSupTags)->apply($document);

    expect($document->saveHTML($paragraph))->toBe('<p>7th</p>');
});

test('strip ordinal sup tags stops at an empty text node with nothing before it', function () {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="UTF-8"?><body><p><sup>th</sup></p></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $paragraph = $document->getElementsByTagName('p')->item(0);
    $paragraph?->insertBefore($document->createTextNode(''), $document->getElementsByTagName('sup')->item(0));

    (new StripOrdinalSupTags)->apply($document);

    expect($document->saveHTML($paragraph))->toBe('<p><sup>th</sup></p>');
});

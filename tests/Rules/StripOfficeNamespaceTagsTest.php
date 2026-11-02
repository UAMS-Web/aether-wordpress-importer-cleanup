<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripOfficeNamespaceTags;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-office-namespace-tags';
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
        new StripOfficeNamespaceTags,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function stripOfficeNamespaceTagsBody(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_use_internal_errors($previous);

    (new StripOfficeNamespaceTags)->apply($document);

    $out = '';
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
        $out .= $document->saveHTML($child);
    }

    return $out;
}

test('strip office namespace tags rewrites matching markup', function (string $html, string $expected) {
    expect(stripOfficeNamespaceTagsBody($html))->toBe($expected);
})->with([
    'o p' => ['<p>a<o:p>x</o:p>b</p>', '<p>ab</p>'],
    'v shape nested' => ['<p>a<v:shape><v:path>y</v:path></v:shape>b</p>', '<p>ab</p>'],
    'upper case' => ['<p>a<V:Shape>z</V:Shape><O:P>q</O:P>b</p>', '<p>ab</p>'],
    'o lock' => ['<p>a<o:lock>1</o:lock>b</p>', '<p>ab</p>'],
    'v inside o' => ['<div><o:p><v:f>1</v:f></o:p><p>t</p></div>', '<div><p>t</p></div>'],
]);

test('strip office namespace tags leaves other markup alone', function (string $html) {
    expect(stripOfficeNamespaceTagsBody($html))->toBe($html);
})->with([
    'other prefix' => ['<p>a<w:span>keep</w:span>b</p>'],
    'plain' => ['<p>a<b>keep</b>b</p>'],
]);

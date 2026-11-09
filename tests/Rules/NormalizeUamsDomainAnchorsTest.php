<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\NormalizeUamsDomainAnchors;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/normalize-uams-domain-anchors';
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
        new NormalizeUamsDomainAnchors,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function normalizeUamsDomainAnchorsBody(string $html): string
{
    $document = Fixture::document($html);

    (new NormalizeUamsDomainAnchors)->apply($document);

    return Fixture::bodyHtml($document);
}

test('normalize uams domain anchors rewrites matching markup', function (string $html, string $expected) {
    expect(normalizeUamsDomainAnchorsBody($html))->toBe($expected);
})->with([
    'uams.edu' => ['<a href="http://www.uams.edu/">uams.edu</a>', '<a href="https://uams.edu/">uams.edu</a>'],
    'www.uams.edu' => ['<a href="http://x.example/">www.uams.edu</a>', '<a href="https://uams.edu/">uams.edu</a>'],
    'uamshealth.com' => ['<a href="https://news.uams.edu/">uamshealth.com</a>', '<a href="https://uamshealth.com/">uamshealth.com</a>'],
    'www.uamshealth.com' => ['<a href="https://news.uams.edu/">www.uamshealth.com</a>', '<a href="https://uamshealth.com/">uamshealth.com</a>'],
    'uppercase and padded text' => ['<a href="http://x.example/">  WWW.UAMS.EDU  </a>', '<a href="https://uams.edu/">uams.edu</a>'],
    'no href' => ['<a class="content">uams.edu</a>', '<a class="content" href="https://uams.edu/">uams.edu</a>'],
    'nested then plain' => ['<a href="http://x.example/"><b>uams.edu</b></a> <a href="http://y.example/">uams.edu</a>', '<a href="http://x.example/"><b>uams.edu</b></a> <a href="https://uams.edu/">uams.edu</a>'],
    'unknown then known' => ['<a href="http://x.example/">other</a> <a href="http://y.example/">uams.edu</a>', '<a href="http://x.example/">other</a> <a href="https://uams.edu/">uams.edu</a>'],
    'two known' => ['<a href="http://x.example/">uams.edu</a> and <a href="http://y.example/">www.uamshealth.com</a>', '<a href="https://uams.edu/">uams.edu</a> and <a href="https://uamshealth.com/">uamshealth.com</a>'],
]);

test('normalize uams domain anchors leaves other markup alone', function (string $html) {
    expect(normalizeUamsDomainAnchorsBody($html))->toBe($html);
})->with([
    'multiple text nodes' => ['<a href="http://x.example/">uams<!-- c -->.edu</a>'],
    'other text' => ['<a href="http://x.example/">uams.edu/news</a>'],
    'other host' => ['<a href="http://x.example/">example.com</a>'],
    'nested element' => ['<a href="http://x.example/"><strong>uams.edu</strong></a>'],
    'no anchors' => ['<p>uams.edu</p>'],
    'empty anchor' => ['<a href="http://x.example/"></a>'],
]);

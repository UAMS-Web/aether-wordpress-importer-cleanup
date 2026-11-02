<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\ConvertYoutubeEmbedToUrl;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/convert-youtube-embed-to-url';
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
        new ConvertYoutubeEmbedToUrl,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

test('a non-youtube embed does not stop later youtube embeds from converting', function () {
    $document = new DOMDocument;
    $document->loadHTML('<html><body><embed src="http://example.com/a.swf"><embed src="http://www.youtube.com/v/abc123"></body></html>');

    (new ConvertYoutubeEmbedToUrl)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);
    expect($body?->textContent)->toBe("\nhttps://www.youtube.com/watch?v=abc123\n")
        ->and($document->getElementsByTagName('embed')->length)->toBe(1);
});

<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripLeadingBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/strip-leading-breaks';
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
        new StripLeadingBreaks,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

test('strip leading breaks removes alternating whitespace text and breaks', function () {
    $document = Fixture::document('<p>x</p>');
    $body = Fixture::first($document, 'body');
    $paragraph = Fixture::first($document, 'p');
    $body->insertBefore($document->createTextNode('  '), $paragraph);
    $body->insertBefore($document->createElement('br'), $paragraph);
    $body->insertBefore($document->createTextNode("\n"), $paragraph);
    $body->insertBefore($document->createElement('br'), $paragraph);

    (new StripLeadingBreaks)->apply($document);

    expect(Fixture::bodyHtml($document))->toBe('<p>x</p>');
});

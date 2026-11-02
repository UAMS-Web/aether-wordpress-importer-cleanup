<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapSmartTags;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-smart-tags';
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
        new UnwrapSmartTags,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * Builds `<p>a{element}b</p>` by hand: prefixed tag names are dropped by some libxml builds when parsed, but
 * createElement() keeps them on every platform, which is what the rule has to handle.
 *
 * @param  list<string>  $tagNames  Element names appended to the paragraph, in order, each holding the text `x`.
 * @param  bool  $nested  Whether each element after the first is nested inside the one before it.
 */
function unwrapSmartTagsParagraph(array $tagNames, bool $nested = false): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $paragraph = $document->createElement('p');
    $document->appendChild($paragraph);
    $paragraph->appendChild($document->createTextNode('a'));

    $container = $paragraph;
    foreach ($tagNames as $tagName) {
        $element = $document->createElement($tagName, 'x');
        $container->appendChild($element);
        if ($nested) {
            $container = $element;
        }
    }

    $paragraph->appendChild($document->createTextNode('b'));

    (new UnwrapSmartTags)->apply($document);

    return (string) $document->saveHTML($paragraph);
}

test('unwrap smart tags keeps the content of st1 to st9 elements', function (string $tagName) {
    expect(unwrapSmartTagsParagraph([$tagName]))->toBe('<p>axb</p>');
})->with([
    'st1' => ['st1:place'],
    'st2' => ['st2:place'],
    'st3' => ['st3:place'],
    'st4' => ['st4:place'],
    'st5' => ['st5:place'],
    'st6' => ['st6:place'],
    'st7' => ['st7:place'],
    'st8' => ['st8:place'],
    'st9' => ['st9:place'],
    'upper case' => ['ST1:Place'],
]);

test('unwrap smart tags unwraps a nested smart tag', function () {
    expect(unwrapSmartTagsParagraph(['st1:place', 'st2:city'], nested: true))->toBe('<p>axxb</p>');
});

test('unwrap smart tags unwraps every smart tag in a paragraph', function () {
    expect(unwrapSmartTagsParagraph(['st1:a', 'b', 'st1:c']))->toBe('<p>ax<b>x</b>xb</p>');
});

test('unwrap smart tags leaves other elements alone', function (string $tagName) {
    expect(unwrapSmartTagsParagraph([$tagName]))->toBe("<p>a<{$tagName}>x</{$tagName}>b</p>");
})->with([
    'st0' => ['st0:place'],
    'st10' => ['st10:place'],
    'other prefix' => ['o:p'],
    'inside the name' => ['xst1:place'],
    'unprefixed' => ['b'],
]);

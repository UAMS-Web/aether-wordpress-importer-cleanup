<?php

declare(strict_types=1);
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\NormalizeCityDateline;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/normalize-city-dateline';
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
        new NormalizeCityDateline,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function normalizeCityDatelineBody(string $html): string
{
    $document = Fixture::document($html);

    (new NormalizeCityDateline)->apply($document);

    return Fixture::bodyHtml($document);
}

test('normalize city dateline rewrites matching markup', function (string $html, string $expected) {
    expect(normalizeCityDatelineBody($html))->toBe($expected);
})->with([
    'city BATESVILLE' => ['BATESVILLE - The foo.', "BATESVILLE, Ark. \u{2014} The foo."],
    'city CLARENDON' => ['CLARENDON - The foo.', "CLARENDON, Ark. \u{2014} The foo."],
    'city EL DORADO' => ['EL DORADO - The foo.', "EL DORADO, Ark. \u{2014} The foo."],
    'city FAYETTEVILLE' => ['FAYETTEVILLE - The foo.', "FAYETTEVILLE, Ark. \u{2014} The foo."],
    'city FORREST CITY' => ['FORREST CITY - The foo.', "FORREST CITY, Ark. \u{2014} The foo."],
    'city FORT SMITH' => ['FORT SMITH - The foo.', "FORT SMITH, Ark. \u{2014} The foo."],
    'city GRAVETTE' => ['GRAVETTE - The foo.', "GRAVETTE, Ark. \u{2014} The foo."],
    'city HELENA' => ['HELENA - The foo.', "HELENA, Ark. \u{2014} The foo."],
    'city JACKSONVILLE' => ['JACKSONVILLE - The foo.', "JACKSONVILLE, Ark. \u{2014} The foo."],
    'city JONESBORO' => ['JONESBORO - The foo.', "JONESBORO, Ark. \u{2014} The foo."],
    'city LITTLE ROCK' => ['LITTLE ROCK - The foo.', "LITTLE ROCK, Ark. \u{2014} The foo."],
    'city MAGNOLIA' => ['MAGNOLIA - The foo.', "MAGNOLIA, Ark. \u{2014} The foo."],
    'city MALVERN' => ['MALVERN - The foo.', "MALVERN, Ark. \u{2014} The foo."],
    'city MARIANNA' => ['MARIANNA - The foo.', "MARIANNA, Ark. \u{2014} The foo."],
    'city MARVELL' => ['MARVELL - The foo.', "MARVELL, Ark. \u{2014} The foo."],
    'city MOUNTAIN HOME' => ['MOUNTAIN HOME - The foo.', "MOUNTAIN HOME, Ark. \u{2014} The foo."],
    'city PINE BLUFF' => ['PINE BLUFF - The foo.', "PINE BLUFF, Ark. \u{2014} The foo."],
    'city SPRINGDALE' => ['SPRINGDALE - The foo.', "SPRINGDALE, Ark. \u{2014} The foo."],
    'city TEXARKANA' => ['TEXARKANA - The foo.', "TEXARKANA, Ark. \u{2014} The foo."],
    'city VAN BUREN' => ['VAN BUREN - The foo.', "VAN BUREN, Ark. \u{2014} The foo."],
    'city WARREN' => ['WARREN - The foo.', "WARREN, Ark. \u{2014} The foo."],
    'hyphen' => ['LITTLE ROCK - The foo.', "LITTLE ROCK, Ark. \u{2014} The foo."],
    'en dash' => ["LITTLE ROCK \u{2013} The foo.", "LITTLE ROCK, Ark. \u{2014} The foo."],
    'em dash' => ["LITTLE ROCK \u{2014} The foo.", "LITTLE ROCK, Ark. \u{2014} The foo."],
    'with ARK.' => ['LITTLE ROCK, ARK. - The foo.', "LITTLE ROCK, Ark. \u{2014} The foo."],
    'with Ark.' => ["LITTLE ROCK, Ark. \u{2013} The foo.", "LITTLE ROCK, Ark. \u{2014} The foo."],
    'space before comma' => ["LITTLE ROCK , ARK. \u{2014} The foo.", "LITTLE ROCK, Ark. \u{2014} The foo."],
    'comma without space' => ['LITTLE ROCK,ARK. - The foo.', "LITTLE ROCK, Ark. \u{2014} The foo."],
    'tabs and extra spaces' => ['LITTLE ROCK   ,   ARK.   -   The foo.', "LITTLE ROCK, Ark. \u{2014} The foo."],
    'second occurrence kept' => ['LITTLE ROCK - one. PINE BLUFF - two.', "LITTLE ROCK, Ark. \u{2014} one. PINE BLUFF - two."],
    'in paragraph' => ['<p>LITTLE ROCK - The foo.</p>', "<p>LITTLE ROCK, Ark. \u{2014} The foo.</p>"],
    'in h1' => ['<h1>PINE BLUFF - The foo.</h1>', "<h1>PINE BLUFF, Ark. \u{2014} The foo.</h1>"],
    'in h2' => ['<h2>PINE BLUFF - The foo.</h2>', "<h2>PINE BLUFF, Ark. \u{2014} The foo.</h2>"],
    'in h3' => ['<h3>PINE BLUFF - The foo.</h3>', "<h3>PINE BLUFF, Ark. \u{2014} The foo.</h3>"],
    'in h4' => ['<h4>PINE BLUFF - The foo.</h4>', "<h4>PINE BLUFF, Ark. \u{2014} The foo.</h4>"],
    'in h5' => ['<h5>PINE BLUFF - The foo.</h5>', "<h5>PINE BLUFF, Ark. \u{2014} The foo.</h5>"],
    'in h6' => ['<h6>PINE BLUFF - The foo.</h6>', "<h6>PINE BLUFF, Ark. \u{2014} The foo.</h6>"],
    'paragraph then text' => ['<p>LITTLE ROCK - one</p>PINE BLUFF - two', "<p>LITTLE ROCK, Ark. \u{2014} one</p>PINE BLUFF - two"],
    'regex metacharacter text' => ['LITTLE ROCK - (a+b)* $1 \\1', "LITTLE ROCK, Ark. \u{2014} (a+b)* \$1 \\1"],
]);

test('normalize city dateline leaves other markup alone', function (string $html) {
    expect(normalizeCityDatelineBody($html))->toBe($html);
})->with([
    'no space before dash' => ['LITTLE ROCK- The foo.'],
    'no space after dash' => ['LITTLE ROCK -The foo.'],
    'ark lowercase' => ['LITTLE ROCK, ark. - The foo.'],
    'ARK without period' => ['LITTLE ROCK, ARK - The foo.'],
    'mid text' => ['Today LITTLE ROCK - The foo.'],
    'lowercase city' => ['Little Rock - The foo.'],
    'unknown city' => ['SMALLTOWN - The foo.'],
    'city prefix' => ['LITTLE ROCKY - The foo.'],
    'city only' => ['LITTLE ROCK'],
    'comma only' => ['LITTLE ROCK, The foo.'],
    'in div' => ['<div>LITTLE ROCK - The foo.</div>'],
    'in nested paragraph' => ['<p><b>LITTLE ROCK - The foo.</b></p>'],
    'not first node' => ['<p>x</p><p>LITTLE ROCK - The foo.</p>'],
    'element then text' => ['<b>x</b>LITTLE ROCK - The foo.'],
    'empty paragraph' => ['<p></p>'],
    'empty body' => [''],
]);

test('normalize city dateline matches only the cities it is given', function () {
    $document = HTMLDocument::createFromString('<body>FOO - one</body>', LIBXML_NOERROR);
    (new NormalizeCityDateline(['FOO']))->apply($document);

    $other = HTMLDocument::createFromString('<body>LITTLE ROCK - one</body>', LIBXML_NOERROR);
    (new NormalizeCityDateline(['FOO']))->apply($other);

    expect($document->getElementsByTagName('body')->item(0)?->textContent)->toBe("FOO, Ark. \u{2014} one")
        ->and($other->getElementsByTagName('body')->item(0)?->textContent)->toBe('LITTLE ROCK - one');
});

test('normalize city dateline ignores a document without a body', function () {
    $document = HTMLDocument::createEmpty();

    (new NormalizeCityDateline)->apply($document);

    expect($document->documentElement)->toBeNull();
});

test('normalize city dateline treats configured city names literally', function () {
    $document = Fixture::document('A.B - x');
    (new NormalizeCityDateline(['A.B']))->apply($document);
    expect(Fixture::bodyHtml($document))->toBe("A.B, Ark. \u{2014} x");

    $other = Fixture::document('AXB - x');
    (new NormalizeCityDateline(['A.B']))->apply($other);
    expect(Fixture::bodyHtml($other))->toBe('AXB - x');
});

<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapDefinitionLists;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-definition-lists';
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
    // CollapseExtraLineBreaks pairs with this rule to keep newlines
    // bounded; testing in isolation is misleading because the
    // inserted blank-line padding would otherwise stack.
    $transform = new Transform([
        new UnwrapDefinitionLists,
        new CollapseExtraLineBreaks,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function unwrapDefinitionListsBody(string $html): string
{
    $document = Fixture::document($html);

    (new UnwrapDefinitionLists)->apply($document);

    return Fixture::bodyHtml($document);
}

test('unwrap definition lists rewrites matching markup', function (string $html, string $expected) {
    expect(unwrapDefinitionListsBody($html))->toBe($expected);
})->with([
    'dl' => ['<dl><dt>a</dt><dd>b</dd></dl>', "\n\n\n\na\n\n\n\nb\n\n\n\n"],
    'dt only' => ['<dt>a</dt>', "\n\na\n\n"],
    'dd only' => ['<dd>a</dd>', "\n\na\n\n"],
    'dl only' => ['<dl>a</dl>', "\n\na\n\n"],
    'nested dl' => ['<dl><dt>a</dt><dd><dl><dt>b</dt></dl></dd></dl>', "\n\n\n\na\n\n\n\n\n\n\n\nb\n\n\n\n\n\n\n\n"],
    'with markup' => ['<dl><dt><b>a</b></dt><dd>b <i>c</i></dd></dl>', "\n\n\n\n<b>a</b>\n\n\n\nb <i>c</i>\n\n\n\n"],
    'between paragraphs' => ['<p>x</p><dl><dt>a</dt></dl><p>y</p>', "<p>x</p>\n\n\n\na\n\n\n\n<p>y</p>"],
    'empty dl' => ['<dl></dl>', "\n\n\n\n"],
]);

test('unwrap definition lists leaves other markup alone', function (string $html) {
    expect(unwrapDefinitionListsBody($html))->toBe($html);
})->with([
    'no dl' => ['<p>a</p>'],
]);

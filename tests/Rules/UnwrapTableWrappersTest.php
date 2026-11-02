<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseExtraLineBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapTableWrappers;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-table-wrappers';
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
    // CollapseExtraLineBreaks pairs with this rule -- the unwrap
    // emits stacked `\n\n` text nodes around the table that the
    // collapse normalizes back to a single blank line.
    $transform = new Transform([
        new UnwrapTableWrappers,
        new CollapseExtraLineBreaks,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * Builds a tree from a nested spec, applies the rule, and serializes the result as `name(child,child)` with text
 * as JSON strings. The DOM is built by hand because the HTML parser will not nest a table inside a `<p>`.
 *
 * A spec is a string (a text node) or a list whose first item is an element name and whose rest are child specs.
 *
 * @param  array<mixed>  $spec
 */
function unwrapTableWrappersRun(array $spec): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->appendChild(unwrapTableWrappersBuild($document, $spec));

    (new UnwrapTableWrappers)->apply($document);

    return $document->documentElement === null ? '' : unwrapTableWrappersDump($document->documentElement);
}

/**
 * @param  array<mixed>  $spec
 */
function unwrapTableWrappersBuild(DOMDocument $document, array $spec): DOMElement
{
    $element = $document->createElement((string) array_shift($spec));
    foreach ($spec as $child) {
        $element->appendChild(is_array($child) ? unwrapTableWrappersBuild($document, $child) : $document->createTextNode((string) $child));
    }

    return $element;
}

function unwrapTableWrappersDump(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return json_encode($node->nodeValue, JSON_THROW_ON_ERROR);
    }

    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = unwrapTableWrappersDump($child);
    }

    return $node->nodeName.'('.implode(',', $children).')';
}

test('unwrap table wrappers pads the table and drops a wrapper', function (string $wrapper) {
    expect(unwrapTableWrappersRun(['body', [$wrapper, ['table', 'x']]]))->toBe('body("\n\n",table("x"),"\n\n")');
})->with([
    'p' => ['p'],
    'strong' => ['strong'],
    'em' => ['em'],
    'upper case P' => ['P'],
    'upper case STRONG' => ['STRONG'],
]);

test('unwrap table wrappers keeps text around the table inside the wrapper', function () {
    expect(unwrapTableWrappersRun(['body', ['p', 'a', ['table', 'x'], 'b']]))->toBe('body("a","\n\n",table("x"),"\n\n","b")');
});

test('unwrap table wrappers drops every wrapper in a chain and stops at other elements', function () {
    expect(unwrapTableWrappersRun(['body', ['div', ['p', ['strong', ['em', ['table', 'x']]]]]]))->toBe('body(div("\n\n",table("x"),"\n\n"))');
});

test('unwrap table wrappers drops a wrapper shared by two tables once', function () {
    expect(unwrapTableWrappersRun(['body', ['p', ['table', 'x'], ['table', 'y']]]))->toBe('body("\n\n",table("x"),"\n\n","\n\n",table("y"),"\n\n")');
});

test('unwrap table wrappers leaves tables whose parent is not a wrapper alone', function (string $parent) {
    expect(unwrapTableWrappersRun(['body', [$parent, ['table', 'x']]]))->toBe('body('.$parent.'(table("x")))');
})->with([
    'div' => ['div'],
    'td' => ['td'],
    'b' => ['b'],
]);

test('unwrap table wrappers handles a table that is a direct child of the body', function () {
    expect(unwrapTableWrappersRun(['body', ['table', 'x']]))->toBe('body(table("x"))');
});

test('unwrap table wrappers keeps looking after a table whose parent is not a wrapper', function () {
    expect(unwrapTableWrappersRun(['body', ['div', ['table', 'x']], ['p', ['table', 'y']]]))
        ->toBe('body(div(table("x")),"\n\n",table("y"),"\n\n")');
});

test('unwrap table wrappers keeps looking after a root table', function () {
    expect(unwrapTableWrappersRun(['table', ['p', ['table', 'x']]]))->toBe('table("\n\n",table("x"),"\n\n")');
});

test('unwrap table wrappers stops climbing at a wrapper that is the document element', function () {
    expect(unwrapTableWrappersRun(['p', ['table', 'x']]))->toBe('table("x")');
});

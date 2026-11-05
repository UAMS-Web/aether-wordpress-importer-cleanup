<?php

declare(strict_types=1);
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapEmbedOnlyParagraph;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/unwrap-embed-only-paragraph';
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
        new UnwrapEmbedOnlyParagraph,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * Builds a `<body>` holding one `<p>` from a list of children, applies the rule, and lists what the body holds
 * afterward as node names (`#text:<value>` for text). The DOM is built by hand because libxml versions disagree
 * on whether a parsed `<embed>` is void.
 *
 * @param  array<mixed>  $children  Each `embed`, `br`, `b` or `comment` becomes that node, anything else becomes a text node.
 * @param  string  $embedName  The node name given to embed children.
 * @return list<string>
 */
function unwrapEmbedOnlyParagraphBody(array $children, string $embedName = 'embed'): array
{
    $document = HTMLDocument::createEmpty();
    $body = $document->createElement('body');
    $document->appendChild($body);
    $body->appendChild(Fixture::element($document, 'p', 'before'));

    $paragraph = $document->createElement('p');
    $body->appendChild($paragraph);
    foreach ($children as $item) {
        $child = is_string($item) ? $item : '';
        if ($child === 'comment') {
            $paragraph->appendChild($document->createComment('c'));
        } elseif ($child === 'embed') {
            $paragraph->appendChild($document->createElement($embedName));
        } elseif ($child === 'br' || $child === 'b') {
            $paragraph->appendChild($document->createElement($child));
        } else {
            $paragraph->appendChild($document->createTextNode($child));
        }
    }

    $body->appendChild(Fixture::element($document, 'p', 'after'));

    (new UnwrapEmbedOnlyParagraph)->apply($document);

    $names = [];
    foreach ($body->childNodes as $node) {
        $names[] = $node instanceof Text ? '#text:'.json_encode($node->nodeValue) : ($node instanceof Element ? $node->localName : $node->nodeName);
    }

    return $names;
}

test('unwrap embed only paragraph replaces a qualifying paragraph with its embeds', function (array $children, array $expected) {
    expect(unwrapEmbedOnlyParagraphBody($children))->toBe(['p', ...$expected, 'p']);
})->with([
    'one embed' => [['embed'], ['#text:"\n"', 'embed', '#text:"\n"']],
    'embed and whitespace' => [[" \n", 'embed', ' '], ['#text:"\n"', 'embed', '#text:"\n"']],
    'embed and br' => [['embed', 'br'], ['#text:"\n"', 'embed', '#text:"\n"']],
    'br first' => [['br', 'embed'], ['#text:"\n"', 'embed', '#text:"\n"']],
    'two embeds' => [['embed', 'embed'], ['#text:"\n"', 'embed', '#text:"\n"', '#text:"\n"', 'embed', '#text:"\n"']],
    'two embeds with whitespace' => [['embed', ' ', 'embed'], ['#text:"\n"', 'embed', '#text:"\n"', '#text:"\n"', 'embed', '#text:"\n"']],
]);

test('unwrap embed only paragraph leaves other paragraphs alone', function (array $children) {
    expect(unwrapEmbedOnlyParagraphBody($children))->toBe(['p', 'p', 'p']);
})->with([
    'no children' => [[]],
    'only whitespace' => [[' ']],
    'only br' => [['br']],
    'text and embed' => [['x', 'embed']],
    'element and embed' => [['b', 'embed']],
    'comment and embed' => [['comment', 'embed']],
]);

test('unwrap embed only paragraph matches an embed created with an upper case name, which an HTML document lowercases', function () {
    expect(unwrapEmbedOnlyParagraphBody(['embed'], 'EMBED'))->toBe(['p', '#text:"\n"', 'embed', '#text:"\n"', 'p']);
});

test('unwrap embed only paragraph ignores a document without paragraphs', function () {
    $document = HTMLDocument::createEmpty();
    $body = $document->createElement('body');
    $document->appendChild($body);

    (new UnwrapEmbedOnlyParagraph)->apply($document);

    expect($body->childNodes->length)->toBe(0);
});

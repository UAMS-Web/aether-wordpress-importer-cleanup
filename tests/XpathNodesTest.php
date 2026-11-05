<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Query results retain ordinary nodes after namespace declarations are skipped.
 *
 * @command  composer test -- tests/XpathNodesTest.php
 */
test('yields ordinary nodes in order even after namespace declarations', function (): void {
    // Mix namespace declarations with document nodes in one result.
    $document = new DOMDocument;
    $document->loadXML('<root xmlns:example="urn:example"><first/><second/></root>');

    $xpath = new DOMXPath($document);

    // Keep both ordinary nodes rather than stopping at a namespace declaration.
    $nodes = iterator_to_array(XpathNodes::query($xpath, '/root/namespace::* | /root/*'));
    expect(array_map(fn (DOMNode $node): string => $node->nodeName, $nodes))->toBe(['first', 'second']);
});

test('returns an empty iterator for an invalid XPath expression', function (): void {
    // Capture libxml's expected diagnostic without changing the caller's mode.
    $document = new DOMDocument;
    $document->loadXML('<root/>');

    $previous = libxml_use_internal_errors(true);

    try {
        expect(iterator_to_array(XpathNodes::query(new DOMXPath($document), '[')))->toBe([]);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
});

<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Tests;

use Dom\Element;
use Dom\HTMLDocument;
use RuntimeException;

final class Fixture
{
    public static function contents(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read fixture: {$path}");
        }

        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    /**
     * A document whose `<body>` holds `$html`, parsed the way the pipeline parses a post body.
     */
    public static function document(string $html): HTMLDocument
    {
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head></head><body></body></html>', LIBXML_NOERROR);
        $shell = $document->body;
        if ($shell === null) {
            throw new RuntimeException('The HTML5 parser returned no body for the fragment shell.');
        }

        $shell->innerHTML = $html;

        return $document;
    }

    /**
     * The serialized children of the document's `<body>`.
     */
    public static function bodyHtml(HTMLDocument $document): string
    {
        // A rule under test may remove the body, which reads back as null.
        $body = $document->body;

        return $body === null ? '' : $body->innerHTML;
    }

    /**
     * An element named `$name` holding the text `$text`, created in `$document`.
     *
     * Dom\Document::createElement() takes no value argument, unlike DOMDocument's.
     */
    public static function element(HTMLDocument $document, string $name, string $text): Element
    {
        $element = $document->createElement($name);
        $element->textContent = $text;

        return $element;
    }

    /**
     * The first `$tag` element in `$document`, or a failure when there is none.
     */
    public static function first(HTMLDocument $document, string $tag): Element
    {
        $found = $document->getElementsByTagName($tag)->item(0);
        if (! $found instanceof Element) {
            throw new RuntimeException("No <{$tag}> element in the fixture document.");
        }

        return $found;
    }
}

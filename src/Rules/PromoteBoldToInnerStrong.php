<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Rewrites the legacy `<b><p>…</p></b>` "speaker block" markup into valid
 * `<p><strong>…</strong></p>`.
 *
 * The HTML parser auto-closes inline `<b>` immediately before any block
 * element like `<p>`, so by the time the DOM is built the markup
 *
 *     <b>
 *     <p>X</p>
 *     </b>
 *
 * arrives as three sibling nodes — an empty `<b>`, a whitespace text node,
 * a `<p>`, then a second whitespace text node "after" what was the closing
 * `</b>`. This rule detects that arrangement: an empty (whitespace-only)
 * `<b>` immediately followed by a `<p>` (skipping whitespace between).
 * It wraps the `<p>`'s contents in a `<strong>` and discards the orphan
 * `<b>` along with the whitespace bridging it to the `<p>`.
 *
 * An HTML5 parser (the one this pipeline uses) does not auto-close `<b>`;
 * it keeps `<b>` open and nests the `<p>` elements inside it, so the same
 * legacy markup arrives as a `<b>` holding only whitespace and `<p>`
 * elements. That shape is promoted the same way: each `<p>`'s contents go
 * inside a `<strong>`, the `<p>` elements replace the `<b>`, and the
 * whitespace before the first one is dropped.
 *
 * `<b>` elements with real inline content (`<b>bold text</b>` inside a
 * paragraph) are left untouched — those are valid HTML and a blanket
 * `<b>` → `<strong>` rename is a separate concern.
 */
final class PromoteBoldToInnerStrong implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        // Snapshot first; we mutate the tree as we iterate.
        $bolds = [];
        foreach (XpathNodes::query($xpath, '//b') as $element) {
            if ($element instanceof Element) {
                $bolds[] = $element;
            }
        }

        foreach ($bolds as $bold) {
            if ($this->holdsOnlyParagraphs($bold)) {
                $this->promoteNestedParagraphs($bold);

                continue;
            }

            if (! $this->isWhitespaceOnly($bold)) {
                continue;
            }

            $paragraph = $this->nextElementSiblingSkippingWhitespace($bold);

            if (! $paragraph instanceof Element || $paragraph->localName !== 'p') {
                continue;
            }

            $parent = $bold->parentNode;
            // A b element found by XPath always has a parent, so the guard only narrows the type.
            // @pest-mutate-ignore: ContinueToBreak
            if (! $parent instanceof Node) {
                continue;
            }

            // Wrap the paragraph's contents in <strong>.
            $strong = $document->createElement('strong');
            while ($paragraph->firstChild instanceof Node) {
                $strong->appendChild($paragraph->firstChild);
            }

            $paragraph->appendChild($strong);

            // Drop the orphan <b> and any whitespace between it and <p>.
            $cursor = $bold->nextSibling;
            while ($cursor instanceof Node && $cursor !== $paragraph) {
                $toRemove = $cursor;
                $cursor = $cursor->nextSibling;
                $parent->removeChild($toRemove);
            }

            $parent->removeChild($bold);
        }
    }

    /**
     * True iff `$bold` holds at least one `<p>` and nothing else but blank text and comments.
     */
    private function holdsOnlyParagraphs(Element $bold): bool
    {
        $paragraphs = 0;
        foreach ($bold->childNodes as $child) {
            if ($child instanceof Element) {
                if ($child->localName !== 'p') {
                    return false;
                }

                $paragraphs++;
            } elseif ($child instanceof Text && trim((string) $child->textContent) !== '') {
                return false;
            }
        }

        return $paragraphs > 0;
    }

    /**
     * Wrap each paragraph's contents in `<strong>` and replace `$bold` with the paragraphs.
     */
    private function promoteNestedParagraphs(Element $bold): void
    {
        $parent = $bold->parentNode;
        // A b element found by XPath always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Node) {
            return;
        }

        $document = $bold->ownerDocument;
        // An element in a document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $seenParagraph = false;
        while ($bold->firstChild instanceof Node) {
            $child = $bold->firstChild;

            if (! $child instanceof Element) {
                // Blank text before the first paragraph is dropped with the b; later text stays between paragraphs.
                if ($seenParagraph) {
                    $parent->insertBefore($child, $bold);
                } else {
                    $bold->removeChild($child);
                }

                continue;
            }

            $seenParagraph = true;
            $strong = $document->createElement('strong');
            while ($child->firstChild instanceof Node) {
                $strong->appendChild($child->firstChild);
            }

            $child->appendChild($strong);
            $parent->insertBefore($child, $bold);
        }

        $parent->removeChild($bold);
    }

    private function isWhitespaceOnly(Element $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof Element) {
                return false;
            }

            if ($child instanceof Text && trim((string) $child->textContent) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nextElementSiblingSkippingWhitespace(Node $node): ?Node
    {
        $next = $node->nextSibling;

        while ($next instanceof Text && trim((string) $next->textContent) === '') {
            $next = $next->nextSibling;
        }

        return $next;
    }
}

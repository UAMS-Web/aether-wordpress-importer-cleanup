<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;

/**
 * Unwrap every `<p>` whose meaningful content is exactly one or more
 * `<embed>` elements — leaving a blank line before and after each
 * unwrapped embed.
 *
 *     <p><embed src="…" /></p>
 *
 * becomes (at the parent level)
 *
 *     \n<embed src="…" />\n
 *
 * Multiple embeds inside the same `<p>` each get their own `\n`
 * padding, so a `<p><embed/><embed/></p>` unwraps to
 * `\n<embed>\n\n<embed>\n` — one blank line between them.
 *
 * Match criteria. Every child of the `<p>` is one of:
 *
 *   - an `<embed>` element,
 *   - a whitespace-only text node,
 *   - a `<br>` element.
 *
 * And the `<p>` contains at least one `<embed>`.
 *
 * Mixed-content paragraphs (`<p>Lead. <embed /> Trail.</p>`) are left
 * alone.
 *
 * Same blank-line bookkeeping as the other unwrap rules
 * (`UnwrapRemainingDivs`, `UnwrapCaptionOnlyParagraph`, …); the late
 * `CollapseExtraLineBreaks` rule collapses any 3+ consecutive `\n`
 * runs back to 2 so adjacent embeds and surrounding padding normalize
 * to a single blank line of separation.
 */
final class UnwrapEmbedOnlyParagraph implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $candidates = [];
        foreach ($document->getElementsByTagName('p') as $p) {
            if ($this->qualifies($p)) {
                $candidates[] = $p;
            }
        }

        foreach ($candidates as $p) {
            $this->unwrap($p);
        }
    }

    private function qualifies(Element $p): bool
    {
        $hasEmbed = false;

        foreach ($p->childNodes as $child) {
            if ($child instanceof Element && $child->localName === 'embed') {
                $hasEmbed = true;

                continue;
            }

            if ($child instanceof Text && trim($child->nodeValue ?? '') === '') {
                continue;
            }

            if ($child instanceof Element && $child->localName === 'br') {
                continue;
            }

            return false;
        }

        return $hasEmbed;
    }

    private function unwrap(Element $p): void
    {
        $parent = $p->parentNode;
        // A p found by XPath always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Node) {
            return;
        }

        $document = $p->ownerDocument;
        // An element in a document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $embeds = [];
        foreach ($p->childNodes as $child) {
            // Every element child of a qualifying paragraph is an embed or a br, and a text child never has the node name
            // embed, so the type check is redundant; libxml lowercases HTML node names, so strtolower changes nothing.
            // @pest-mutate-ignore: InstanceOfToTrue
            // @pest-mutate-ignore: UnwrapStrtolower
            if ($child instanceof Element && $child->localName === 'embed') {
                $embeds[] = $child;
            }
        }

        foreach ($embeds as $embed) {
            $parent->insertBefore($document->createTextNode("\n"), $p);
            $parent->insertBefore($embed, $p);
            $parent->insertBefore($document->createTextNode("\n"), $p);
        }

        $parent->removeChild($p);
    }
}

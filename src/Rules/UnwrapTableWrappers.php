<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Lift every `<table>` out of a `<p>`, `<strong>`, or `<em>` wrapper,
 * leaving a blank line before the opening `<table>` and after the
 * closing `</table>`.
 *
 *     <p><table>…</table></p>
 *     <strong><table>…</table></strong>
 *     <em><table>…</table></em>
 *
 * all become
 *
 *     <table>…</table>
 *
 * with two `\n` characters (one blank line) of padding on each side.
 *
 * Why each of those three. `<p>` cannot legally contain a `<table>`
 * — the HTML parser implicitly closes the `<p>` when it sees a
 * `<table>`, so this case is normally an authoring artifact from
 * the legacy editor's serializer. `<strong>` and `<em>` are inline
 * elements and similarly cannot contain block-level structure;
 * when they do, the destination CMS interprets the markup
 * unpredictably.
 *
 * Both the padding and the wrapper-unwrap are done in two passes:
 * first the `\n\n` text nodes are inserted around each affected
 * `<table>` while the wrapper is still in place, then the wrapper
 * is unwrapped. Multiple-level wrappers (`<p><strong><table></strong></p>`)
 * are handled by walking the ancestor chain from each table — all
 * consecutive `<p>`/`<strong>`/`<em>` ancestors get collected and
 * unwrapped.
 *
 * Other content inside the wrapper survives. `<p>Lead text<table>…</table>
 * trail text</p>` becomes `Lead text` + blank line + `<table>` + blank
 * line + `trail text` at the wrapper's parent level — paragraphs of
 * mixed content that included a table lose their paragraph context,
 * but the table boundary is now clean and `CollapseExtraLineBreaks`
 * (later in the pipeline) normalizes any `\n` runs back down.
 *
 * Runs before `UnwrapWholeContentTable` so that a body whose only
 * meaningful child is a `<p><table>…</table></p>` becomes eligible
 * for whole-content-table unwrapping once this rule has lifted the
 * table out of the paragraph.
 */
final class UnwrapTableWrappers implements Rule
{
    /**
     * @return list<string>
     */
    private static function wrapperTags(): array
    {
        return ['p', 'strong', 'em'];
    }

    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $tables = [];
        foreach (XpathNodes::query($xpath, '//table') as $table) {
            // //table yields only elements, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($table instanceof Element) {
                $tables[] = $table;
            }
        }

        $wrappers = [];

        foreach ($tables as $table) {
            $parent = $table->parentNode;
            if (! $parent instanceof Element) {
                continue;
            }

            if (! $this->isWrapper($parent)) {
                continue;
            }

            $this->padAroundTable($table);

            $cursor = $parent;
            while ($cursor instanceof Element && $this->isWrapper($cursor)) {
                $wrappers[spl_object_hash($cursor)] = $cursor;
                $cursor = $cursor->parentNode;
            }
        }

        foreach ($wrappers as $wrapper) {
            $this->unwrapElement($wrapper);
        }
    }

    private function isWrapper(Element $element): bool
    {
        return in_array($element->localName, self::wrapperTags(), true);
    }

    private function padAroundTable(Element $table): void
    {
        $parent = $table->parentNode;
        // A table found by XPath always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Node) {
            return;
        }

        $document = $table->ownerDocument;
        // An element in a document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $table);

        // A null reference node appends, which is what a table with no next sibling needs.
        $parent->insertBefore($document->createTextNode("\n\n"), $table->nextSibling);
    }

    private function unwrapElement(Element $element): void
    {
        $parent = $element->parentNode;
        // A wrapper collected while walking up from a table always has a parent, so the guard only narrows the type.
        // A document cannot hold the padding text the wrapper carries, so a wrapper that is the document element stays.
        if (! $parent instanceof Element) {
            return;
        }

        while ($element->firstChild instanceof Node) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}

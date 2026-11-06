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
 * Unwrap every `<table>` whose structure contains exactly one `<tr>`
 * and exactly one `<td>` — leaving a blank line before and after each
 * unwrapped element.
 *
 * The legacy news.uams.edu exports use single-cell tables as
 * presentational containers (typically wrapping a centered header
 * `<p align="center">`-style title block at the top of an article).
 * The wrapper carries no semantic value in the destination CMS, so
 * peel it away and let the cell's contents stand at the table's
 * parent level.
 *
 * Structure recognition. The table may have a `<tbody>` between
 * itself and the row (the libxml HTML parser inserts one when the
 * source has bare `<table><tr>`), so the qualifying shape is:
 *
 *   `<table>` → (`<tbody>` →)? single `<tr>` → single `<td>`
 *
 * Tables with multiple rows, multiple cells, two `<tbody>`s, or
 * `<thead>`/`<tfoot>` siblings are NOT considered single-cell and
 * are left alone.
 *
 * Nested tables. A nested `<table>` inside the qualifying cell
 * survives intact along with its own descendants — only the outer
 * table chain (and its directly-owned tbody/tr/td) gets unwrapped.
 *
 * Blank-line bookkeeping. Same pattern as `UnwrapWholeContentTable`:
 * each unwrapped element emits `\n\n` text nodes on both sides.
 * `CollapseExtraLineBreaks` (later in the pipeline) collapses any
 * resulting runs of 3+ consecutive `\n` back down to 2 so the
 * stacked padding from peeling table/tbody/tr/td in succession
 * contributes exactly one normalized blank line above and below the
 * cell's content.
 *
 * Runs after `UnwrapWholeContentTable` so the whole-content special
 * case (which handles multi-cell tables too) fires first when both
 * rules would apply.
 */
final class UnwrapSingleCellTable implements Rule
{
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

        foreach ($tables as $table) {
            $chain = $this->qualifyingChain($table);
            if ($chain === null) {
                continue;
            }

            foreach ($chain as $element) {
                $this->unwrapWithBlankLines($element);
            }
        }
    }

    /**
     * Return the chain of elements to unwrap (table, optional tbody,
     * tr, td) if the table qualifies as single-cell; otherwise null.
     *
     * @return list<Element>|null
     */
    private function qualifyingChain(Element $table): ?array
    {
        $tbodies = $this->childElementsByTag($table, 'tbody');
        $directRows = $this->childElementsByTag($table, 'tr');

        if (count($tbodies) > 1) {
            return null;
        }

        if ($tbodies !== [] && $directRows !== []) {
            return null;
        }

        if ($this->hasChildElement($table, 'thead')
            || $this->hasChildElement($table, 'tfoot')
        ) {
            return null;
        }

        $tbody = $tbodies[0] ?? null;
        $rowOwner = $tbody ?? $table;
        $rows = $this->childElementsByTag($rowOwner, 'tr');

        if (count($rows) !== 1) {
            return null;
        }

        $row = $rows[0];
        $cells = $this->childElementsByTag($row, 'td');

        if (count($cells) !== 1) {
            return null;
        }

        $chain = [$table];
        if ($tbody !== null) {
            $chain[] = $tbody;
        }

        $chain[] = $row;
        $chain[] = $cells[0];

        return $chain;
    }

    /**
     * Return every direct child element of `$parent` whose tag name
     * matches `$tagName`, a lowercase tag name (the element's own name is
     * compared case-insensitively).
     *
     * @return list<Element>
     */
    private function childElementsByTag(Element $parent, string $tagName): array
    {
        $result = [];

        foreach ($parent->childNodes as $child) {
            // A non-element node is named #text, #comment or similar and never equals a tag name, so the type check is redundant.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($child instanceof Element && $child->localName === $tagName) {
                $result[] = $child;
            }
        }

        return $result;
    }

    private function hasChildElement(Element $parent, string $tagName): bool
    {
        return $this->childElementsByTag($parent, $tagName) !== [];
    }

    private function unwrapWithBlankLines(Element $element): void
    {
        $parent = $element->parentNode;
        // An element in the unwrapped chain always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Node) {
            return;
        }

        $document = $element->ownerDocument;
        // An element in a document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $element);

        while ($element->firstChild instanceof Node) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $element);
        $parent->removeChild($element);
    }
}

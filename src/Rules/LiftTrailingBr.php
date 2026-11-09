<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Lifts every trailing `<br>` out of its parent element so the closing
 * tag of the parent precedes the `<br>`, with no whitespace between.
 *
 * Legacy content frequently emits
 *
 *     <b>foo<br>
 *     </b>
 *
 * — a `<br>` as the last visible child of `<b>`, often with intervening
 * whitespace before the closing tag. This rule rewrites that to
 *
 *     <b>foo</b><br>
 *
 * by removing the trailing whitespace inside the parent and re-parenting
 * the `<br>` as the parent's next sibling. Applied iteratively: stacked
 * trailing `<br>`s (e.g. `<b>foo<br><br></b>`) and nested cases (e.g.
 * `<b>foo<i>bar<br></i></b>`) flatten all the way out until stable.
 *
 * Skips lifting out of `<body>` (the synthetic envelope) — there's
 * nowhere outside to put it — and skips block-level parents (`<p>`,
 * `<li>`, `<h1>`–`<h6>`) so the trailing `<br>` doesn't end up between
 * paragraphs. Trailing `<br>`s in block tags are handled instead by
 * `TrimBlockEdges`, which strips them outright.
 */
final class LiftTrailingBr implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        do {
            $changed = false;

            $brs = [];
            foreach (XpathNodes::query($xpath, '//br') as $br) {
                // The query selects only elements, so the guard only narrows the type.
                // @pest-mutate-ignore: InstanceOfToTrue
                if ($br instanceof Element) {
                    $brs[] = $br;
                }
            }

            // Process trailing-most first so a chain of trailing `<br>`s
            // (or a nested case) collapses out one layer per pass.
            // The order only saves passes: visiting forward lifts the same brs into the same positions, one layer later.
            // @pest-mutate-ignore: UnwrapArrayReverse
            foreach (array_reverse($brs) as $br) {
                if ($this->liftIfTrailing($br)) {
                    $changed = true;
                }
            }
        } while ($changed);
    }

    private function liftIfTrailing(Element $br): bool
    {
        $parent = $br->parentNode;

        // A br found by //br in an HTML body always has an element parent, so the guard only narrows the type.
        // @pest-mutate-ignore: FalseToTrue
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Element) {
            return false;
        }

        if (in_array($parent->localName, ['body', 'p', 'li', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            return false;
        }

        if (! $this->isLastNonWhitespaceChild($br)) {
            return false;
        }

        $grandparent = $parent->parentNode;
        // An element below body always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: FalseToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $grandparent instanceof Node) {
            return false;
        }

        $this->dropFollowingWhitespace($br, $parent);

        // insertBefore moves a node that is already attached, so no separate detach is needed.
        $grandparent->insertBefore($br, $parent->nextSibling);

        return true;
    }

    private function isLastNonWhitespaceChild(Element $br): bool
    {
        $cursor = $br->nextSibling;

        while ($cursor instanceof Node) {
            if ($cursor instanceof Text && trim(NodeText::content($cursor)) === '') {
                $cursor = $cursor->nextSibling;

                continue;
            }

            return false;
        }

        return true;
    }

    private function dropFollowingWhitespace(Element $br, Element $parent): void
    {
        $cursor = $br->nextSibling;

        while ($cursor instanceof Node) {
            $toRemove = $cursor;
            $cursor = $cursor->nextSibling;
            $parent->removeChild($toRemove);
        }
    }
}

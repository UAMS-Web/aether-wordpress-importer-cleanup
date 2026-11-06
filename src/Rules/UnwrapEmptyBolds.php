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
 * Unwraps `<b>` elements whose only content is whitespace text.
 *
 * Legacy WordPress content sometimes contains accidental `<b>` shells
 * holding nothing but a single space — e.g. `<b> </b>` between a sentence
 * and the following quote. These add no semantic value and break syntax
 * highlighters / clean serialization. This rule replaces each such `<b>`
 * with its whitespace content directly.
 *
 * `<b>` elements with real text content (`<b>bold word</b>`) are left
 * untouched. `<b>` shells that wrap a `<p>` (the parser-auto-closed
 * "speaker block" arrangement) should be handled by
 * `PromoteBoldToInnerStrong` first; this rule runs after that one and
 * cleans up the remainder.
 */
final class UnwrapEmptyBolds implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $bolds = [];
        foreach (XpathNodes::query($xpath, '//b') as $element) {
            // The query selects only elements, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($element instanceof Element) {
                $bolds[] = $element;
            }
        }

        foreach ($bolds as $bold) {
            if (! $this->isWhitespaceOnly($bold)) {
                continue;
            }

            $parent = $bold->parentNode;
            // A b element found by XPath always has a parent, so the guard only narrows the type and the continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $parent instanceof Node) {
                continue;
            }

            while ($bold->firstChild instanceof Node) {
                $parent->insertBefore($bold->firstChild, $bold);
            }

            $parent->removeChild($bold);
        }
    }

    private function isWhitespaceOnly(Element $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof Element) {
                return false;
            }

            if ($child instanceof Text && trim(NodeText::content($child)) !== '') {
                return false;
            }
        }

        return true;
    }
}

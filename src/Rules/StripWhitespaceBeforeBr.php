<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Strip breaking whitespace (ASCII space, tab, line break, CR) from the
 * end of any text node that's immediately followed by a `<br>` element.
 *
 * Legacy editors often planted indentation and line breaks right before
 * `<br>` tags as a side-effect of how the source was wrapped:
 *
 *     Foo
 *         <br>
 *
 * `<br>` already provides the visual break, so the leading whitespace
 * on the `<br>` line is redundant. NBSP (`U+00A0`) is preserved as
 * non-breaking content.
 */
final class StripWhitespaceBeforeBr implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        foreach (XpathNodes::query($xpath, '//br') as $br) {
            // //br yields only elements, so the guard only narrows the type and the continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $br instanceof Element) {
                continue;
            }

            $prev = $br->previousSibling;
            // A <br> directly after an element must be skipped, and later <br> elements must still be processed.
            if (! $prev instanceof Text) {
                continue;
            }

            $prev->nodeValue = rtrim(NodeText::content($prev), " \t\n\r");
        }
    }
}

<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\HTMLDocument;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Collapse runs of 3+ consecutive `\n` characters down to exactly 2.
 *
 * Two consecutive `\n`s (one blank line) is the conventional separator
 * between paragraphs in plain-text content and is preserved. Anything
 * more is excess whitespace from the legacy editor and gets collapsed.
 *
 * `$document->normalize()` runs first so a newline run split across
 * adjacent text-node siblings (e.g. the `\n\n` text nodes
 * `UnwrapRemainingDivs` inserts before and after each div) becomes a
 * single text node in which the regex can see the full run.
 */
final class CollapseExtraLineBreaks implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $document->normalize();

        $xpath = new XPath($document);

        foreach (XpathNodes::query($xpath, '//text()') as $textNode) {
            // The guard only narrows the type: //text() yields text and CDATA nodes, both Text, so continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $textNode instanceof Text) {
                continue;
            }

            // preg_replace returns null only when PCRE fails, and null empties a node exactly as an empty string does.
            // @pest-mutate-ignore: RemoveStringCast
            $textNode->nodeValue = (string) preg_replace(
                '/\n{3,}/',
                "\n\n",
                NodeText::content($textNode),
            );
        }
    }
}

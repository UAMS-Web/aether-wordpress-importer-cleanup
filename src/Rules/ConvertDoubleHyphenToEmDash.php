<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\HTMLDocument;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Replace the typographic shorthand ` -- ` (space + two hyphens + space)
 * with ` — ` (space + em dash + space) in every text node.
 *
 * The legacy editor lacked a smart-punctuation pass, so writers typed
 * double-hyphens as a stand-in for an em dash. Only the spaced form is
 * converted — unspaced `foo--bar` patterns (e.g. command-line flags or
 * compound terms) are left alone.
 */
final class ConvertDoubleHyphenToEmDash implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        foreach (XpathNodes::query($xpath, '//text()') as $textNode) {
            // The guard only narrows the type: //text() yields text and CDATA nodes, both Text, so continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            if (! $textNode instanceof Text) {
                continue;
            }

            $textNode->nodeValue = str_replace(
                ' -- ',
                " \u{2014} ",
                $textNode->nodeValue ?? '',
            );
        }
    }
}

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
 * Replace MS-Word "smart" curly quotes with straight ASCII equivalents
 * in every text node.
 *
 *   ” (U+201D right double curly) → "
 *   “ (U+201C left double curly)  → "
 *   ’ (U+2019 right single curly) → '
 *   ‘ (U+2018 left single curly)  → '
 *
 * Operates on text nodes only — attribute values are left alone.
 */
final class ReplaceCurlyQuotes implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        foreach (XpathNodes::query($xpath, '//text()') as $textNode) {
            // //text() yields only text nodes, so the guard only narrows the type and the continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $textNode instanceof Text) {
                continue;
            }

            $textNode->nodeValue = strtr(NodeText::content($textNode), [
                "\u{201D}" => '"',
                "\u{201C}" => '"',
                "\u{2019}" => "'",
                "\u{2018}" => "'",
            ]);
        }
    }
}

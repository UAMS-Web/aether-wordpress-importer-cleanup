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
 * Collapse runs of 2+ horizontal whitespace characters (ASCII space,
 * tab, and NBSP) in every text node.
 *
 * Two outcomes per run:
 *
 * - If the run contains at least one NBSP (`U+00A0`), it collapses to
 *   a single NBSP. Legacy press releases planted patterns like
 *   `Fax:  &nbsp;&#160;(501) 686-5067` where the NBSPs signal an
 *   intentional non-breaking gap; we keep the non-breaking semantics
 *   but drop the redundancy.
 * - If the run is purely breaking whitespace (only spaces and/or tabs),
 *   it collapses to a single space.
 *
 * Newlines are left alone — line structure is preserved.
 */
final class CollapseHorizontalWhitespace implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        foreach (XpathNodes::query($xpath, '//text()') as $textNode) {
            // The guard only narrows the type: //text() yields text and CDATA nodes, both Text, so continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $textNode instanceof Text) {
                continue;
            }

            // preg_replace_callback returns null only when PCRE fails, and null empties a node exactly as '' does.
            // @pest-mutate-ignore: RemoveStringCast
            $textNode->nodeValue = (string) preg_replace_callback(
                '/[ \t\x{00A0}]{2,}/u',
                static fn (array $match): string => str_contains($match[0], "\u{00A0}") ? "\u{00A0}" : ' ',
                NodeText::content($textNode),
            );
        }
    }
}

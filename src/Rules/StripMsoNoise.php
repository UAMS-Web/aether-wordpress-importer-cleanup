<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\NodeList;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Strips Microsoft-Word-paste noise from HTML fragments.
 *
 * Removes:
 * - `class` attribute tokens matching `Mso*` (e.g. `MsoNormal`,
 *   `MsoNormalTable`); other tokens on the same element are preserved
 * - inline `style` attributes (these are almost always Word-injected)
 * - `<font>` legacy presentation tags (unwrapped; their children are
 *   preserved in place)
 *
 * `<o:p>` Office placeholder tags are stripped earlier by
 * `Transform::stripOfficeParagraphTags()` at the raw-string level — they
 * can't be reliably matched via XPath because libxml's HTML parser handles
 * namespaced tags inconsistently across versions.
 *
 * Table structure is preserved. The aggressive "unwrap MsoNormalTable
 * into paragraphs" transform is a separate rule.
 */
final class StripMsoNoise implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $this->stripMsoClassTokens($xpath);
        $this->removeInlineStyles($xpath);
        $this->unwrapFontTags($xpath);
    }

    private function stripMsoClassTokens(XPath $xpath): void
    {
        foreach (XpathNodes::query($xpath, '//*[@class]') as $element) {
            // The query selects only elements, so the guard only narrows the type and the continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            if (! $element instanceof Element) {
                continue;
            }

            // Remove Mso-prefixed tokens; preserve everything else
            $original = ($element->getAttribute('class') ?? '');
            $cleaned = (string) preg_replace('/\bMso[A-Za-z0-9_-]*\b/i', '', $original);
            $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

            if ($cleaned === '') {
                $element->removeAttribute('class');
            } else {
                $element->setAttribute('class', $cleaned);
            }
        }
    }

    private function removeInlineStyles(XPath $xpath): void
    {
        foreach (XpathNodes::query($xpath, '//*[@style]') as $element) {
            if ($element instanceof Element) {
                $element->removeAttribute('style');
            }
        }
    }

    private function unwrapFontTags(XPath $xpath): void
    {
        // Iterate a fresh snapshot since removing elements mutates the live
        // NodeList in-flight.
        $fontElements = [];
        foreach (XpathNodes::query($xpath, '//font') as $element) {
            // The query selects only elements, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($element instanceof Element) {
                $fontElements[] = $element;
            }
        }

        foreach ($fontElements as $font) {
            $parent = $font->parentNode;
            // A font element found by XPath always has a parent, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            // @pest-mutate-ignore: ContinueToBreak
            if (! $parent instanceof Node) {
                continue;
            }

            while ($font->firstChild instanceof Node) {
                $parent->insertBefore($font->firstChild, $font);
            }

            $parent->removeChild($font);
        }
    }
}

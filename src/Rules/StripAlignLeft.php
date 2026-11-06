<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Remove every `align="left"` attribute, regardless of which tag carries it.
 *
 * Left alignment is the default for LTR content, so an explicit
 * `align="left"` adds nothing visually — and the `align` attribute is
 * deprecated in HTML5 anyway. Other values (`right`, `center`,
 * `justify`) are preserved since they DO change rendering.
 *
 * Case-insensitive on the value (`align="LEFT"` → stripped too) via
 * XPath's `translate()` fold.
 */
final class StripAlignLeft implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $query = "//*[translate(@align, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='left']";

        $elements = [];
        foreach (XpathNodes::query($xpath, $query) as $element) {
            // The query selects only elements, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($element instanceof Element) {
                $elements[] = $element;
            }
        }

        foreach ($elements as $element) {
            $element->removeAttribute('align');
        }
    }
}

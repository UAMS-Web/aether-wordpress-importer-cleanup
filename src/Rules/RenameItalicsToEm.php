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
 * Renames every `<i>` element to its semantic equivalent `<em>`,
 * preserving children and attributes.
 *
 * `<i>` is presentational ("italic"); `<em>` is semantic ("emphasis").
 * Modern markup prefers the semantic form.
 */
final class RenameItalicsToEm implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $italics = [];
        foreach (XpathNodes::query($xpath, '//i') as $element) {
            if ($element instanceof Element) {
                $italics[] = $element;
            }
        }

        foreach ($italics as $italic) {
            $em = $document->createElement('em');

            foreach ($italic->attributes as $attr) {
                $em->setAttribute($attr->name, $attr->value);
            }

            while ($italic->firstChild instanceof Node) {
                $em->appendChild($italic->firstChild);
            }

            $italic->parentNode?->replaceChild($em, $italic);
        }
    }
}

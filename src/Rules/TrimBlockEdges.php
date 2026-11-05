<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Strip leading and trailing whitespace and `<br>` tags from inside
 * `<p>`, `<li>`, `<td>`, and heading tags (`<h1>` through `<h6>`).
 *
 * The legacy editor liked to plant a stray `<br>` plus indentation
 * immediately before the closing tag of a paragraph, list item, or
 * table cell — a cosmetic side-effect of how the editor handled the
 * trailing-Enter keystroke. Strip those edges down to the first/last
 * real content.
 *
 * Targets block tags only. Inline tags (`<b>`, `<i>`, `<strong>`, …) are
 * left for `LiftTrailingBr` to handle, since lifting a trailing `<br>`
 * out of an inline element keeps the line break intact between the
 * inline phrase and the surrounding paragraph.
 */
final class TrimBlockEdges implements Rule
{
    /**
     * @return list<string>
     */
    private static function blockTags(): array
    {
        return ['p', 'li', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    }

    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);
        $query = implode(
            ' | ',
            array_map(static fn (string $tag): string => '//'.$tag, self::blockTags()),
        );

        $blocks = [];
        foreach (XpathNodes::query($xpath, $query) as $block) {
            if ($block instanceof Element) {
                $blocks[] = $block;
            }
        }

        foreach ($blocks as $block) {
            $this->trim($block);
        }
    }

    private function trim(Element $block): void
    {
        while (($first = $block->firstChild) instanceof Node) {
            if ($this->isStrippable($first)) {
                $block->removeChild($first);

                continue;
            }

            break;
        }

        while (($last = $block->lastChild) instanceof Node) {
            if ($this->isStrippable($last)) {
                $block->removeChild($last);

                continue;
            }

            break;
        }

        $first = $block->firstChild;
        if ($first instanceof Text) {
            $first->nodeValue = mb_ltrim($first->nodeValue ?? '');
        }

        $last = $block->lastChild;
        if ($last instanceof Text) {
            $last->nodeValue = mb_rtrim($last->nodeValue ?? '');
        }
    }

    private function isStrippable(Node $node): bool
    {
        if ($node instanceof Text && mb_trim($node->nodeValue ?? '') === '') {
            return true;
        }

        return $node instanceof Element && $node->localName === 'br';
    }
}

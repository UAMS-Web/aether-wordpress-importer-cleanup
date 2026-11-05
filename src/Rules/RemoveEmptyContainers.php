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
 * Recursively remove every container element whose visible content is
 * empty — that is, whose descendants are only whitespace (including
 * NBSPs), `<br>` tags, and other empty containers.
 *
 * The legacy MS-Word paste plants nested spacer markup like:
 *
 *     <p align="justify"><strong><br><br>  </strong></p>
 *
 * The `<strong>` here contains only `<br>`s and whitespace, so it's
 * stripped first; that leaves an empty `<p>`, which is then stripped on
 * the next pass. The rule loops until stable so arbitrary nesting
 * (`<div><p><strong>  </strong></p></div>`) collapses all the way out.
 *
 * Preserved (never removed):
 * - `<body>`, `<html>`, `<head>`, `<title>` — document/envelope.
 * - Void/atomic elements (`<br>`, `<hr>`, `<img>`, …) — they ARE
 *   content on their own merits.
 * - Content-producing non-void (`<iframe>`, `<object>`, `<audio>`,
 *   `<video>`, `<svg>`, `<canvas>`).
 * - Interactive (`<button>`, `<select>`, `<textarea>`).
 * - `<a>` — anchors carry semantic meaning (href, name) even with no
 *   inner text.
 * - Table structure (`<table>`, `<thead>`, `<tbody>`, `<tfoot>`,
 *   `<tr>`, `<th>`, `<td>`, `<caption>`, `<col>`, `<colgroup>`) —
 *   removing cells shifts columns.
 *
 * An element is treated as "empty" if `mb_trim($el->textContent)` is
 * the empty string AND no descendant is itself a content element
 * (those above plus `<table>`) — so a `<div>` wrapping a still-empty
 * table isn't dropped just because the text content rolls up to "".
 */
final class RemoveEmptyContainers implements Rule
{
    /**
     * @return list<string>
     */
    private static function preserved(): array
    {
        return [
            'body', 'html', 'head', 'title',
            'br', 'hr', 'img', 'input', 'embed', 'source', 'track', 'wbr',
            'col', 'colgroup', 'link', 'meta', 'base', 'param', 'area',
            'iframe', 'object', 'audio', 'video', 'svg', 'canvas',
            'button', 'select', 'textarea',
            'a',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        ];
    }

    /**
     * @return list<string>
     */
    private static function contentDescendants(): array
    {
        return [
            'img', 'iframe', 'embed', 'object', 'audio', 'video', 'svg',
            'canvas', 'input', 'button', 'select', 'textarea', 'hr', 'a',
            'table',
        ];
    }

    public function apply(HTMLDocument $document): void
    {
        $preserved = self::preserved();

        do {
            $changed = false;
            $xpath = new XPath($document);

            $candidates = [];
            foreach (XpathNodes::query($xpath, '//*') as $element) {
                // //* yields only elements, so the guard only narrows the type and the continue is unreachable.
                // @pest-mutate-ignore: ContinueToBreak
                if (! $element instanceof Element) {
                    continue;
                }

                if (in_array($element->localName, $preserved, true)) {
                    continue;
                }

                if (! $this->isEmpty($element)) {
                    continue;
                }

                $candidates[] = $element;
            }

            foreach ($candidates as $element) {
                $parent = $element->parentNode;
                if ($parent instanceof Node) {
                    $parent->removeChild($element);
                    $changed = true;
                }
            }
        } while ($changed);
    }

    private function isEmpty(Element $element): bool
    {
        if (mb_trim((string) $element->textContent) !== '') {
            return false;
        }

        return array_all(self::contentDescendants(), fn (string $tag): bool => $element->getElementsByTagName($tag)->length <= 0);
    }
}

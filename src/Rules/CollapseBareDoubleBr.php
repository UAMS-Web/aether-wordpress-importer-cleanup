<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;

/**
 * Replace runs of 2+ consecutive body-level `<br>` tags (with optional
 * whitespace between them) with a single blank-line text node (`\n\n`).
 *
 * Legacy posts that pre-date the visual editor's paragraph splitting
 * stored "paragraph breaks" as bare `<br><br>` sequences at body level
 * — no wrapping `<p>` at all. Those become a single blank line in plain
 * text; subsequent whitespace normalization (`CollapseExtraLineBreaks`)
 * keeps things tidy if the surrounding text contributes its own
 * newlines.
 *
 * Scoped to direct children of `<body>` — `<br><br>` inside a `<p>` is
 * left for `SplitParagraphsOnDoubleBr` to handle (that rule splits the
 * paragraph at the run); `<br><br>` inside a `<li>` or heading stays
 * untouched as a deliberate line break.
 */
final class CollapseBareDoubleBr implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof Element) {
            return;
        }

        $children = iterator_to_array($body->childNodes);
        $count = count($children);
        $i = 0;
        // Merging adjacent text nodes never changes the serialized output, so a run that always merges is equivalent.
        // @pest-mutate-ignore: FalseToTrue
        $touched = false;

        while ($i < $count) {
            $child = $children[$i];
            if (! $child instanceof Element || $child->localName !== 'br') {
                $i++;

                continue;
            }

            $run = [$i];
            $j = $i + 1;
            $brCount = 1;

            while ($j < $count) {
                $next = $children[$j];
                if ($next instanceof Text && mb_trim(NodeText::content($next)) === '') {
                    $run[] = $j;
                    $j++;

                    continue;
                }

                if ($next instanceof Element && $next->localName === 'br') {
                    $run[] = $j;
                    $brCount++;
                    $j++;

                    continue;
                }

                break;
            }

            if ($brCount >= 2) {
                $firstRunNode = $children[$run[0]];
                $lastRunNode = $children[$run[count($run) - 1]];

                $prev = $firstRunNode->previousSibling;
                $next = $lastRunNode->nextSibling;

                if ($prev instanceof Text) {
                    $prev->nodeValue = mb_rtrim(NodeText::content($prev));
                }

                if ($next instanceof Text) {
                    $next->nodeValue = mb_ltrim(NodeText::content($next));
                }

                $replacement = $document->createTextNode("\n\n");
                $body->insertBefore($replacement, $firstRunNode);

                foreach ($run as $idx) {
                    $body->removeChild($children[$idx]);
                }

                $touched = true;
            }

            $i = $j;
        }

        if ($touched) {
            $body->normalize();
        }
    }
}

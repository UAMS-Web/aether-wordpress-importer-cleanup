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
 * Unwraps every `<span>` element in the fragment, preserving its children
 * in place.
 *
 * The legacy news.uams.edu / inside.uams.edu corpus uses `<span>` almost
 * exclusively as a presentational shim from the WordPress editor or from
 * Word-paste — `class="content"`, `style="font-size: 13px; …"`, etc. —
 * never as a meaningful inline anchor. Unwrapping is a safe blanket
 * transform for this corpus.
 */
final class UnwrapSpans implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        // Snapshot first; removing elements mutates the live NodeList.
        $spans = [];
        foreach (XpathNodes::query($xpath, '//span') as $element) {
            if ($element instanceof Element) {
                $spans[] = $element;
            }
        }

        foreach ($spans as $span) {
            $parent = $span->parentNode;
            // A span found by XPath always has a parent, so the guard only narrows the type and the continue is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            if (! $parent instanceof Node) {
                continue;
            }

            while ($span->firstChild instanceof Node) {
                $parent->insertBefore($span->firstChild, $span);
            }

            $parent->removeChild($span);
        }
    }
}

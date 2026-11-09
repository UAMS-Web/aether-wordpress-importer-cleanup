<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;

/**
 * Strips a leading "publication date" prefix from the post body.
 *
 * Legacy press releases on news.uams.edu open with a date stamp like
 * `Nov. 12, 2002` (sometimes uppercased, sometimes followed by a separator
 * like ` | ` or ` — ` and a city tag). The date can appear either as a
 * bare text node at the start of `<body>` or as leading text inside the
 * first `<p>`. This rule handles both arrangements.
 *
 * If the leading text contains meaningful content AFTER the date and
 * separator, only the date prefix is removed; the remainder stays in
 * place. If the date emptied out its containing `<p>`, that `<p>` is
 * removed too.
 *
 * Case-insensitive. American format only — `Month [DD], YYYY` with
 * optional ordinal suffix and optional trailing separator. Run AFTER
 * `UnwrapSpans` so any presentational wrappers are gone. Idempotent.
 */
final class StripLeadingDateLine implements Rule
{
    /**
     * Matches the leading date pattern with optional ordinal day suffix
     * (`12th`, `1st`, …) and optional trailing separator (` — `, ` - `,
     * ` | `, ` – `). Anchored to the start of the input; no end anchor,
     * so meaningful text after the date is preserved.
     */
    private const string DATE_PATTERN =
        '/^\s*(?:Jan\.?|January|Feb\.?|February|March|April|May|June|July|Aug\.?|August|Sept\.?|Sep\.?|September|Oct\.?|October|Nov\.?|November|Dec\.?|December)\s+\d{1,2}(?:st|nd|rd|th)?,\s+\d{4}(?:\s+[\|–—-]\s+)?/iu';

    public function apply(HTMLDocument $document): void
    {
        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body instanceof Element) {
            return;
        }

        $first = $this->firstNonWhitespaceChild($body);

        if ($first instanceof Text) {
            $this->stripDateFrom($first);
            $this->trimLeadingWhitespaceTextNodes($body);
        } elseif ($first instanceof Element && $first->localName === 'p') {
            $leadingText = $this->firstNonWhitespaceChild($first);

            if (! $leadingText instanceof Text) {
                return;
            }

            $this->stripDateFrom($leadingText);

            if ($this->isEffectivelyEmpty($first)) {
                // The paragraph was just read from the body, so it always has a parent.
                // @pest-mutate-ignore: RemoveNullSafeOperator
                $first->parentNode?->removeChild($first);
                $this->trimLeadingWhitespaceTextNodes($body);
            }
        }
    }

    private function stripDateFrom(Text $textNode): void
    {
        if (preg_match(self::DATE_PATTERN, NodeText::content($textNode), $matches) !== 1) {
            return;
        }

        $remainder = substr(NodeText::content($textNode), strlen($matches[0]));

        if (trim($remainder) === '') {
            // A text node read from a parent always has one.
            // @pest-mutate-ignore: RemoveNullSafeOperator
            $textNode->parentNode?->removeChild($textNode);
        } else {
            $textNode->nodeValue = $remainder;
        }
    }

    private function firstNonWhitespaceChild(Node $parent): ?Node
    {
        $cursor = $parent->firstChild;

        while ($cursor instanceof Text && trim(NodeText::content($cursor)) === '') {
            $cursor = $cursor->nextSibling;
        }

        return $cursor;
    }

    private function trimLeadingWhitespaceTextNodes(Node $parent): void
    {
        $cursor = $parent->firstChild;

        while ($cursor instanceof Text && trim(NodeText::content($cursor)) === '') {
            $toRemove = $cursor;
            $cursor = $cursor->nextSibling;
            $parent->removeChild($toRemove);
        }
    }

    private function isEffectivelyEmpty(Element $element): bool
    {
        foreach ($element->childNodes as $child) {
            // The paragraph was read by its first non-whitespace child, so a whitespace text child is never followed by
            // another child after the date is stripped: continuing or breaking returns the same result.
            // @pest-mutate-ignore: ContinueToBreak
            if ($child instanceof Text && trim(NodeText::content($child)) === '') {
                continue;
            }

            return false;
        }

        return true;
    }
}

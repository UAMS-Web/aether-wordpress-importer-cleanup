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
 * Unwrap every `<p>` whose entire content (after trimming whitespace)
 * is exactly one `[caption …]…[/caption]` shortcode pair — leaving
 * a blank line before the opening `<p>` and after the closing `</p>`.
 *
 *     <p>[caption id="…" align="…"]<img src="…" alt="…"> Caption text.[/caption]</p>
 *
 * becomes
 *
 *     [caption id="…" align="…"]<img src="…" alt="…"> Caption text.[/caption]
 *
 * with `\n\n` text nodes of padding on both sides.
 *
 * Why. WordPress's `wpautop` filter wraps a standalone caption
 * shortcode in a paragraph when the editor serializes the post,
 * which carries through to the WXR export. The destination CMS
 * parses `[caption]` at the body level natively and doesn't need
 * the `<p>` wrapper — keeping it would force the shortcode to
 * render inside an extra paragraph block.
 *
 * Match criteria. The `<p>`'s concatenated text content must:
 * - Start with `[caption` (after leading whitespace trimmed).
 * - End with `[/caption]` (after trailing whitespace trimmed).
 * - Contain exactly one `[caption` opener and one `[/caption]`
 *   closer.
 *
 * Element children inside the `<p>` (the caption's `<img>`, any
 * surviving inline markup in the caption text) are preserved and
 * emerge at the `<p>`'s parent level. Paragraphs that mix a caption
 * with surrounding prose (`<p>Lead. [caption]…[/caption] Trail.</p>`)
 * or stack multiple captions in one paragraph are left alone.
 *
 * Blank-line bookkeeping. Same pattern as `UnwrapRemainingDivs`/
 * `UnwrapDefinitionLists`/`UnwrapSingleCellTable`: each unwrapped
 * `<p>` emits `\n\n` text nodes on both sides.
 * `CollapseExtraLineBreaks` (later in the pipeline) collapses any
 * 3+ consecutive `\n` runs back to 2 so adjacent unwrapped
 * paragraphs or already-blank-line-adjacent content end up with
 * exactly one normalized blank line of separation.
 */
final class UnwrapCaptionOnlyParagraph implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $paragraphs = [];
        foreach (XpathNodes::query($xpath, '//p') as $p) {
            // //p yields only elements, so the type check only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($p instanceof Element && $this->isCaptionOnlyParagraph($p)) {
                $paragraphs[] = $p;
            }
        }

        foreach ($paragraphs as $p) {
            $this->unwrapElement($p);
        }
    }

    private function isCaptionOnlyParagraph(Element $p): bool
    {
        $text = mb_trim((string) $p->textContent);

        if (! str_starts_with($text, '[caption')) {
            return false;
        }

        if (! str_ends_with($text, '[/caption]')) {
            return false;
        }

        // `[/caption]` does not start with `[caption`, so the two
        // substrings can be counted independently.
        if (substr_count($text, '[caption') !== 1) {
            return false;
        }

        return substr_count($text, '[/caption]') === 1;
    }

    private function unwrapElement(Element $element): void
    {
        $parent = $element->parentNode;
        // A p found by XPath always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Node) {
            return;
        }

        $document = $element->ownerDocument;
        // An element in a document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $element);

        while ($element->firstChild instanceof Node) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $element);
        $parent->removeChild($element);
    }
}

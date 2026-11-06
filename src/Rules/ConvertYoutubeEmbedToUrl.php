<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Convert every `<embed>` whose `src` matches the legacy WordPress
 * YouTube-embed shape (`http://www.youtube.com/v/{id}…` /
 * `https://www.youtube.com/v/{id}…`) into a standalone YouTube watch
 * URL — leaving a blank line before and after the converted URL.
 *
 *     <embed src="http://www.youtube.com/v/wc3wToFuBww&hl=en&fs=1&rel=0" width="425" height="344" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" />
 *
 * becomes
 *
 *     \nhttps://www.youtube.com/watch?v=wc3wToFuBww\n
 *
 * The destination CMS auto-converts a bare YouTube URL on its own
 * line into an embed; `\n` padding ensures the URL sits on its own
 * line.
 *
 * Match. `src` starts with `http://www.youtube.com/v/` or
 * `https://www.youtube.com/v/`, followed by a YouTube video ID
 * (one or more of `A-Za-z0-9_-`). Trailing `&hl=…`, `&fs=1`, `?rel=0`,
 * etc. are dropped; the canonical watch URL contains only `v=<id>`.
 *
 * Non-YouTube `<embed>` elements (other src patterns, no src
 * attribute) are left alone.
 *
 * Same blank-line bookkeeping as `UnwrapEmbedOnlyParagraph` — the
 * late `CollapseExtraLineBreaks` rule collapses any 3+ consecutive
 * `\n` runs back to 2.
 */
final class ConvertYoutubeEmbedToUrl implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $candidates = [];
        foreach (XpathNodes::query($xpath, '//embed[@src]') as $embed) {
            // The guard only narrows the type: //embed[@src] yields only elements, so the branch is unreachable.
            // @pest-mutate-ignore: ContinueToBreak
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $embed instanceof Element) {
                continue;
            }

            $videoId = $this->extractVideoId(NodeText::attribute($embed, 'src'));
            if ($videoId === null) {
                continue;
            }

            $candidates[] = [$embed, $videoId];
        }

        foreach ($candidates as [$embed, $videoId]) {
            $this->convert($embed, $videoId);
        }
    }

    private function extractVideoId(string $src): ?string
    {
        if (preg_match('#^https?://www\.youtube\.com/v/([A-Za-z0-9_-]+)#', $src, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function convert(Element $embed, string $videoId): void
    {
        $parent = $embed->parentNode;
        // An embed selected from the document always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parent instanceof Node) {
            return;
        }

        $document = $embed->ownerDocument;
        // An element in a loaded document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $url = 'https://www.youtube.com/watch?v='.$videoId;
        $parent->insertBefore($document->createTextNode("\n".$url."\n"), $embed);
        $parent->removeChild($embed);
    }
}

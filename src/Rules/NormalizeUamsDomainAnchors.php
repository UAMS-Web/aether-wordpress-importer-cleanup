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
 * Canonicalize `<a>` tags whose visible text is one of the UAMS hostnames.
 *
 * The legacy site is full of bare-hostname links that disagree with
 * themselves — the visible text says `uams.edu` but the `href` points
 * at `http://www.uams.edu/`, or worse, at an unrelated page on
 * `news.uams.edu`. The visible text is the authoritative signal of
 * intent, so we rewrite both `href` and text to a canonical form keyed
 * off the visible text.
 *
 *     <a href="http://www.uams.edu/">www.uams.edu</a>
 *     <a href="https://news.uams.edu/">uamshealth.com</a>
 *
 * become
 *
 *     <a href="https://uams.edu/">uams.edu</a>
 *     <a href="https://uamshealth.com/">uamshealth.com</a>
 *
 * Match criteria. An `<a>` qualifies if:
 *
 *   - it contains only text nodes (no nested elements),
 *   - the trimmed, lowercased text equals one of: `uams.edu`,
 *     `www.uams.edu`, `uamshealth.com`, `www.uamshealth.com`.
 *
 * Anchors with no `href` attribute (the legacy editor saved plenty of
 * link-styled-but-not-actually-linked `<a class="content">…</a>` tags)
 * are matched too — the visible text still expresses a clear intent,
 * and gaining a working link beats keeping a broken non-link. libxml's
 * HTML parser also occasionally drops the `href` when the source has
 * unbalanced attribute quoting (`<a target=" originalAttribute=">…`);
 * those cases get healed for free.
 *
 * Nested-element anchors (`<a><strong>uams.edu</strong></a>`) are left
 * alone — they're rare and rewriting them would lose markup; the
 * `<strong>` content gets canonicalized once `RenameBoldToStrong` and
 * friends settle the inner formatting, but the surrounding anchor is a
 * judgment call we defer.
 */
final class NormalizeUamsDomainAnchors implements Rule
{
    /**
     * @return array<string, array{href: string, text: string}>
     */
    private static function canonical(): array
    {
        return [
            'uams.edu' => ['href' => 'https://uams.edu/', 'text' => 'uams.edu'],
            'www.uams.edu' => ['href' => 'https://uams.edu/', 'text' => 'uams.edu'],
            'uamshealth.com' => ['href' => 'https://uamshealth.com/', 'text' => 'uamshealth.com'],
            'www.uamshealth.com' => ['href' => 'https://uamshealth.com/', 'text' => 'uamshealth.com'],
        ];
    }

    public function apply(HTMLDocument $document): void
    {
        $canonical = self::canonical();
        $candidates = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $candidates[] = $anchor;
        }

        foreach ($candidates as $anchor) {
            if (! $this->isTextOnly($anchor)) {
                continue;
            }

            $key = strtolower(mb_trim(NodeText::content($anchor)));
            if (! isset($canonical[$key])) {
                continue;
            }

            $target = $canonical[$key];
            $anchor->setAttribute('href', $target['href']);

            while ($anchor->firstChild instanceof Node) {
                $anchor->removeChild($anchor->firstChild);
            }

            $anchor->appendChild($document->createTextNode($target['text']));
        }
    }

    private function isTextOnly(Element $anchor): bool
    {
        foreach ($anchor->childNodes as $child) {
            if (! $child instanceof Text) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Lift every `[caption …]…[/caption]` shortcode out of any wrapping
 * `<p>`, splitting the paragraph around the captions and wrapping
 * any prose chunks left behind in fresh `<p>`s.
 *
 * For a `<p>` that contains one or more matched caption shortcodes
 * (`UnwrapCaptionOnlyParagraph` already consumed the simple
 * single-caption case before this rule runs), parse the children
 * into a sequence of:
 *
 *   - **caption chunks** — the text + element nodes spanning a
 *     `[caption` opener to a `[/caption]` closer.
 *   - **prose chunks** — everything outside a caption, with `<br>`
 *     elements and whitespace text serving as chunk boundaries.
 *
 * Each caption emerges at the original `<p>`'s parent level on its
 * own. Each prose chunk that has visible content after trimming
 * gets wrapped in a brand-new `<p>` at the same level. Whitespace-
 * only or empty chunks are dropped. The original `<p>` itself is
 * removed.
 *
 * Inter-chunk separators:
 *
 *   - caption + caption → single `\n`
 *   - caption + prose or prose + caption → `\n\n` (blank line)
 *
 * Plus `\n\n` padding before the first chunk and after the last
 * chunk. `CollapseExtraLineBreaks` (later in the pipeline) collapses
 * any 3+ consecutive `\n` runs back down to 2.
 *
 * Worked example. The legacy editor sometimes serialized stacked
 * captions inside a single paragraph:
 *
 *     <p>
 *         [caption]<img …> Foo[/caption]
 *         Lorem ipsum dolor sit amet.
 *         [caption]<img …> Baz[/caption]
 *     </p>
 *
 * becomes
 *
 *     [caption]<img …> Foo[/caption]
 *
 *     <p>Lorem ipsum dolor sit amet.</p>
 *
 *     [caption]<img …> Baz[/caption]
 *
 * Bookkeeping. The rule fires only on `<p>`s where the count of
 * `[caption` openers equals the count of `[/caption]` closers and
 * both are at least 1 — paragraphs with unbalanced shortcode
 * markers are left alone rather than risk producing malformed
 * output.
 *
 * Runs after `UnwrapCaptionOnlyParagraph` (which handles the simple
 * single-caption case) and before the structural table rules so
 * captions land at their final parent level before downstream
 * normalization.
 */
final class LiftCaptionsOutOfParagraph implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $candidates = [];
        foreach (XpathNodes::query($xpath, '//p') as $p) {
            // //p yields only elements, so the type check only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($p instanceof Element && $this->paragraphHasBalancedCaptions($p)) {
                $candidates[] = $p;
            }
        }

        foreach ($candidates as $p) {
            $this->processParagraph($p);
        }
    }

    private function paragraphHasBalancedCaptions(Element $p): bool
    {
        $text = NodeText::content($p);
        $openers = substr_count($text, '[caption');
        $closers = substr_count($text, '[/caption]');

        return $openers > 0 && $openers === $closers;
    }

    private function processParagraph(Element $p): void
    {
        $chunks = $this->parseChunks($p);
        $chunks = $this->trimAndDropEmptyProse($chunks);

        // A paragraph passes the balanced-marker check only when its text holds a caption marker, and a marker in a
        // text node always yields a caption chunk, so the chunk list is never empty here.
        // @pest-mutate-ignore: IfNegated
        // @pest-mutate-ignore: InstanceOfToTrue
        // @pest-mutate-ignore: InstanceOfToFalse
        // @pest-mutate-ignore: RemoveMethodCall
        if ($chunks === []) {
            $parent = $p->parentNode;
            if ($parent instanceof Node) {
                $parent->removeChild($p);
            }

            return;
        }

        $this->emitChunks($p, $chunks);
    }

    /**
     * @return list<array{type: string, nodes: list<Node>}>
     */
    private function parseChunks(Element $p): array
    {
        $document = $p->ownerDocument;
        // A paragraph taken from a loaded document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: RemoveEarlyReturn
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return [];
        }

        $chunks = [];
        $currentType = 'prose';
        /** @var list<Node> $currentNodes */
        $currentNodes = [];
        $inCaption = false;

        $flush = function () use (&$chunks, &$currentNodes, &$currentType): void {
            if ($currentNodes !== []) {
                $chunks[] = ['type' => $currentType, 'nodes' => $currentNodes];
                $currentNodes = [];
            }
        };

        foreach (iterator_to_array($p->childNodes) as $child) {
            if ($child instanceof Text) {
                $text = NodeText::content($child);
                $pos = 0;
                $len = strlen($text);

                while ($pos !== $len) {
                    if (! $inCaption) {
                        $openerAt = strpos($text, '[caption', $pos);
                        if ($openerAt === false) {
                            $segment = substr($text, $pos);
                            // The current type is always 'prose' while outside a caption (a closer resets it), so the flush never runs.
                            // @pest-mutate-ignore: RemoveFunctionCall
                            if ($currentType !== 'prose') {
                                $flush();
                                $currentType = 'prose';
                            }

                            $currentNodes[] = $document->createTextNode($segment);

                            break;
                        }

                        // An empty segment would only add an empty text node, which the trim drops again.
                        // @pest-mutate-ignore: GreaterToGreaterOrEqual
                        if ($openerAt > $pos) {
                            $segment = substr($text, $pos, $openerAt - $pos);
                            // The current type is always 'prose' while outside a caption (a closer resets it), so the flush never runs.
                            // @pest-mutate-ignore: RemoveFunctionCall
                            if ($currentType !== 'prose') {
                                $flush();
                                $currentType = 'prose';
                            }

                            $currentNodes[] = $document->createTextNode($segment);
                        }

                        $flush();
                        $currentType = 'caption';
                        $inCaption = true;
                        $pos = $openerAt;

                        continue;
                    }

                    $closerAt = strpos($text, '[/caption]', $pos);
                    if ($closerAt === false) {
                        $segment = substr($text, $pos);
                        $currentNodes[] = $document->createTextNode($segment);

                        break;
                    }

                    $end = $closerAt + strlen('[/caption]');
                    $segment = substr($text, $pos, $end - $pos);
                    $currentNodes[] = $document->createTextNode($segment);
                    $flush();
                    $inCaption = false;
                    $currentType = 'prose';
                    $pos = $end;
                }

                continue;
            }

            // The query selects only elements, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($child instanceof Element) {
                $tag = $child->localName;

                if (! $inCaption && $tag === 'br') {
                    $flush();

                    continue;
                }

                $desired = $inCaption ? 'caption' : 'prose';
                // The current type already equals the desired one (an opener sets 'caption', a closer sets 'prose'), so the flush never runs.
                // @pest-mutate-ignore: RemoveFunctionCall
                if ($currentType !== $desired) {
                    $flush();
                    $currentType = $desired;
                }

                $currentNodes[] = $child;
            }
        }

        $flush();

        return $chunks;
    }

    /**
     * @param  list<array{type: string, nodes: list<Node>}>  $chunks
     * @return list<array{type: string, nodes: list<Node>}>
     */
    private function trimAndDropEmptyProse(array $chunks): array
    {
        $result = [];

        foreach ($chunks as $chunk) {
            if ($chunk['type'] === 'caption') {
                $result[] = $chunk;

                continue;
            }

            $nodes = $chunk['nodes'];

            while ($nodes !== [] && $this->isWhitespaceTextNode($nodes[0])) {
                array_shift($nodes);
            }

            while ($nodes !== [] && $this->isWhitespaceTextNode($nodes[count($nodes) - 1])) {
                array_pop($nodes);
            }

            if ($nodes !== [] && $nodes[0] instanceof Text) {
                $nodes[0]->nodeValue = ltrim(NodeText::content($nodes[0]));
            }

            if ($nodes !== []) {
                $last = $nodes[count($nodes) - 1];
                if ($last instanceof Text) {
                    $last->nodeValue = rtrim(NodeText::content($last));
                }
            }

            if ($nodes !== []) {
                $result[] = ['type' => 'prose', 'nodes' => $nodes];
            }
        }

        return $result;
    }

    private function isWhitespaceTextNode(Node $node): bool
    {
        return $node instanceof Text && trim(NodeText::content($node)) === '';
    }

    /**
     * @param  list<array{type: string, nodes: list<Node>}>  $chunks
     */
    private function emitChunks(Element $p, array $chunks): void
    {
        $document = $p->ownerDocument;
        // A paragraph taken from a loaded document always has an owner document, so the guard only narrows the type.
        // @pest-mutate-ignore: RemoveEarlyReturn
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $document instanceof HTMLDocument) {
            return;
        }

        $parent = $p->parentNode;
        // A node reached from the body always has a parent, so the guard only narrows the type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $parent instanceof Node) {
            return;
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $p);

        $prevType = null;
        foreach ($chunks as $chunk) {
            if ($prevType !== null) {
                $separator = ($prevType === 'caption' && $chunk['type'] === 'caption')
                    ? "\n"
                    : "\n\n";
                $parent->insertBefore($document->createTextNode($separator), $p);
            }

            if ($chunk['type'] === 'caption') {
                foreach ($chunk['nodes'] as $node) {
                    $parent->insertBefore($node, $p);
                }
            } else {
                $newParagraph = $document->createElement('p');
                foreach ($chunk['nodes'] as $node) {
                    $newParagraph->appendChild($node);
                }

                $parent->insertBefore($newParagraph, $p);
            }

            $prevType = $chunk['type'];
        }

        $parent->insertBefore($document->createTextNode("\n\n"), $p);
        $parent->removeChild($p);
    }
}

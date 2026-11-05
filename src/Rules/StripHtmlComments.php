<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup\Rules;

use Dom\Comment;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\XPath;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\XpathNodes;

/**
 * Remove every HTML comment node from the document.
 *
 * WordPress block-editor markup litters posts with paired comments
 * like `<!-- wp:paragraph -->` … `<!-- /wp:paragraph -->`, plus the
 * occasional editor-side note. None of those render — strip them all,
 * leaving the surrounding HTML intact.
 *
 * Runs first in the pipeline so subsequent rules never have to think
 * about comment siblings or interleaved comment-and-text nodes.
 */
final class StripHtmlComments implements Rule
{
    public function apply(HTMLDocument $document): void
    {
        $xpath = new XPath($document);

        $comments = [];
        foreach (XpathNodes::query($xpath, '//comment()') as $comment) {
            // //comment() yields only comment nodes, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($comment instanceof Comment) {
                $comments[] = $comment;
            }
        }

        foreach ($comments as $comment) {
            $parent = $comment->parentNode;
            // A comment found by XPath always has a parent, so the guard only narrows the type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($parent instanceof Node) {
                $parent->removeChild($comment);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup;

use Dom\Element;
use Dom\Node;

/**
 * String-typed reads of `Dom\` nodes.
 *
 * The `Dom\` classes type `textContent` and `getAttribute()` as nullable, while
 * every rule needs a string to trim, split or compare. These two readers are
 * the one place that turns a null into an empty string.
 */
final class NodeText
{
    /**
     * The text content of `$node`, or an empty string when the node reports none.
     */
    public static function content(Node $node): string
    {
        // textContent is never null on the element, text and comment nodes the rules read, so the cast only narrows the type.
        // @pest-mutate-ignore: RemoveStringCast
        return (string) $node->textContent;
    }

    /**
     * The value of attribute `$name` on `$element`, or an empty string when it is absent.
     */
    public static function attribute(Element $element, string $name): string
    {
        return $element->getAttribute($name) ?? '';
    }
}

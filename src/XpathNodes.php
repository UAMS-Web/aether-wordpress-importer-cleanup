<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\WordPressImporter\Cleanup;

use Dom\Node;
use Dom\XPath;
use DOMNameSpaceNode;
use DOMNode;
use DOMXPath;
use EmptyIterator;
use Iterator;

final class XpathNodes
{
    /**
     * The prefix bound to the XHTML namespace on every `Dom\XPath` query this helper runs.
     */
    private const string HTML_PREFIX = 'h';

    /**
     * The namespace `Dom\HTMLDocument` puts every HTML element in.
     */
    private const string HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    /**
     * Run an XPath query and yield every matching node except namespace declarations.
     *
     * `DOMXPath::query()` returns false on invalid expressions; this helper
     * treats that as an empty result so callers can foreach safely.
     *
     * Over a `Dom\HTMLDocument`, elements live in the XHTML namespace, so an
     * unprefixed name test such as `//table` matches nothing. A `Dom\XPath`
     * expression is therefore written with plain HTML names and rewritten here to
     * the bound prefix (`//h:table`); see `htmlExpression()`.
     *
     * @return ($xpath is XPath ? Iterator<int, Node> : Iterator<int, DOMNode>)
     */
    public static function query(DOMXPath|XPath $xpath, string $expression): Iterator
    {
        if ($xpath instanceof XPath) {
            $xpath->registerNamespace(self::HTML_PREFIX, self::HTML_NAMESPACE);
            $expression = self::htmlExpression($expression);
        }

        $matches = $xpath->query($expression);
        if ($matches === false) {
            return new EmptyIterator;
        }

        foreach ($matches as $node) {
            // Only DOMXPath reaches namespace-declaration nodes: Dom\XPath refuses the namespace axis with a DOMException.
            if ($node instanceof DOMNameSpaceNode) {
                continue;
            }

            yield $node;
        }
    }

    /**
     * Prefix every element name test in an XPath expression with the XHTML prefix.
     *
     * A name test is a bare name that follows `/`, `//`, `|` or `(` or opens the
     * expression, and is not followed by `(` (a node-type test or function, such as
     * `text()`) or `:` (already prefixed, or an axis). `@attribute` names, `*` and
     * predicates are left alone.
     */
    private static function htmlExpression(string $expression): string
    {
        // preg_replace returns null only when PCRE fails on a resource limit, and no test of this pattern lowers one.
        // @pest-mutate-ignore: RemoveStringCast
        return (string) preg_replace(
            '/(^|[\/|(])([A-Za-z][A-Za-z0-9_-]*)(?![A-Za-z0-9_:(-])/',
            '$1'.self::HTML_PREFIX.':$2',
            $expression,
        );
    }
}

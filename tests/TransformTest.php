<?php

/**
 * Public WXR transformation contracts independent of the default rule list.
 *
 * Accepted equivalents in Transform: FalseToTrue on the saveXML false guard
 * compares a native serialization string against true instead of false; neither
 * comparison matches on the public path. Rules receive only the HTML wrapper,
 * not the outer WXR document, so cannot inject a failing outer serializer.
 * IncrementInteger on the dateline replacement limit is equivalent because
 * the start-anchored pattern can match at most once per subject.
 * The final body closer remains mutation-visible: libxml versions differ in
 * whether an unclosed script consumes that closing tag as raw text.
 * Linux mutation validation: 136 mutants tested; every killable mutant is dead.
 *
 * @command  composer test -- tests/TransformTest.php
 */

declare(strict_types=1);

use Dom\Element;
use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

test('apply exposes the explicitly closed envelope to rules for an unclosed script', function (): void {
    // Observe parser text before any serializer can normalize the fragment.
    $rule = new readonly class('hello') implements Rule
    {
        /**
         * @param  string  $expected  Script text the HTML5 parser keeps for an unclosed script.
         */
        public function __construct(private string $expected) {}

        public function apply(HTMLDocument $document): void
        {
            expect($document->getElementsByTagName('script')->item(0)?->textContent)
                ->toBe($this->expected);
        }
    };

    // Keep the malformed fragment inside valid WXR CDATA.
    new Transform([$rule])->apply(
        '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><content:encoded><![CDATA[<script>hello]]></content:encoded></item></channel></rss>'
    );
});

test('apply preserves compact XML and existing interelement whitespace', function (): void {
    // Keep both compact and deliberately spaced structures in the same export.
    $wxr = '<rss><channel><untouched><a/><b/></untouched>  <item><title>Headline</title></item></channel></rss>';

    // Require serialization without pretty-print indentation or whitespace loss.
    expect(new Transform([])->apply($wxr))->toBe(
        "<?xml version=\"1.0\"?>\n<rss><channel><untouched><a/><b/></untouched>  <item><title><![CDATA[Headline]]></title></item></channel></rss>\n"
    );
});

test('apply registers WXR namespaces independently of root declarations and element prefixes', function (): void {
    // Declare aliases only on the item, leaving the root without WXR namespaces.
    $wxr = <<<'XML'
        <rss><channel><item xmlns:c="http://purl.org/rss/1.0/modules/content/" xmlns:e="http://wordpress.org/export/1.2/excerpt/" xmlns:w="http://wordpress.org/export/1.2/">
        <c:encoded><![CDATA[  <b>Body</b>  ]]></c:encoded>
        <e:encoded><![CDATA[&lt;b&gt;Excerpt&lt;/b&gt;]]></e:encoded>
        <w:postmeta><w:meta_key>_uams_summary</w:meta_key><w:meta_value><![CDATA[  &lt;b&gt;Summary&lt;/b&gt;  ]]></w:meta_value></w:postmeta>
        <w:postmeta><w:meta_key>other</w:meta_key><w:meta_value><![CDATA[  &lt;b&gt;Untouched&lt;/b&gt;  ]]></w:meta_value></w:postmeta>
        </item></channel></rss>
        XML;

    // Check all three namespace-dependent paths and the unrelated metadata control.
    $actual = new Transform([])->apply($wxr);
    expect($actual)
        ->toContain('<c:encoded><![CDATA[<b>Body</b>]]></c:encoded>')
        ->toContain('<e:encoded><![CDATA[Excerpt]]></e:encoded>')
        ->toContain('<w:meta_value><![CDATA[&lt;b&gt;Summary&lt;/b&gt;]]></w:meta_value>')
        ->toContain('<w:meta_value><![CDATA[  &lt;b&gt;Untouched&lt;/b&gt;  ]]></w:meta_value>');
});

test('apply decodes HTML5 entities and attribute quotes before excerpt and summary rules', function (string $element): void {
    // Require quote decoding before parsing an otherwise unquoted attribute.
    $rule = new class implements Rule
    {
        public int $calls = 0;

        public function apply(HTMLDocument $document): void
        {
            // Require the documented synthetic html5 shell around the body.
            expect($document->doctype?->name)->toBe('html');
            expect($document->body?->localName)->toBe('body');

            // Only the first pass sees the decoded markup; a repeat pass sees its plain-text output.
            if ($this->calls++ > 0) {
                return;
            }

            // Observe the exact HTML handed to the configured rules.
            $paragraph = $document->getElementsByTagName('p')->item(0);
            expect($paragraph)->toBeInstanceOf(Element::class);
            expect($paragraph?->getAttribute('title'))->toBe('two words');
            expect($paragraph?->textContent)->toBe("'quoted' \"double\" :");
        }
    };

    // Keep the encoded markup inside CDATA, as legacy exports do.
    $encoded = '&lt;p title=&quot;two words&quot;&gt;&apos;quoted&apos; &quot;double&quot; &colon;&lt;/p&gt;';
    $node = $element === 'excerpt'
        ? '<excerpt:encoded><![CDATA['.$encoded.']]></excerpt:encoded>'
        : '<wp:postmeta><wp:meta_key>_uams_summary</wp:meta_key><wp:meta_value><![CDATA['.$encoded.']]></wp:meta_value></wp:postmeta>';
    $wxr = '<rss xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:wp="http://wordpress.org/export/1.2/"><channel><item>'.$node.'</item></channel></rss>';

    // Confirm the field-specific serialization after the rule has observed decoding.
    $actual = new Transform([$rule])->apply($wxr);
    expect($rule->calls)->toBeGreaterThan(0)
        ->and($actual)->toContain($element === 'excerpt'
        ? '<excerpt:encoded><![CDATA[\'quoted\' "double" :]]></excerpt:encoded>'
        : '<wp:meta_value><![CDATA[&lt;p title="two words"&gt;\'quoted\' "double" :&lt;/p&gt;]]></wp:meta_value>');
})->with(['excerpt', 'summary']);

test('apply returns the original fragment when a configured rule removes the synthetic body', function (): void {
    // Exercise the public extension point that can legitimately remove the envelope.
    $rule = new class implements Rule
    {
        public function apply(HTMLDocument $document): void
        {
            // Remove only the synthetic body, leaving no serializable fragment.
            $body = $document->getElementsByTagName('body')->item(0);
            if ($body instanceof Element) {
                $body->parentNode?->removeChild($body);
            }
        }
    };
    $wxr = '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><content:encoded><![CDATA[  <b>Original</b>  ]]></content:encoded></item></channel></rss>';

    // Preserve even the surrounding spaces in the fallback, rather than trimming.
    expect(new Transform([$rule])->apply($wxr))
        ->toContain('<content:encoded><![CDATA[  <b>Original</b>  ]]></content:encoded>');
});

test('apply normalizes carriage returns introduced by configured rules', function (string $newline): void {
    // Insert line endings after parsing so XML input normalization cannot mask them.
    $rule = new readonly class($newline) implements Rule
    {
        /**
         * @param  string  $newline  Line ending inserted into the parsed fragment.
         */
        public function __construct(private string $newline) {}

        public function apply(HTMLDocument $document): void
        {
            // Add a literal line ending for the outer serializer to normalize.
            $body = $document->getElementsByTagName('body')->item(0);
            // Only fill an empty body, so repeated pipeline passes settle.
            if ($body?->firstChild === null) {
                $body?->appendChild($document->createTextNode('first'.$this->newline.'second'));
            }
        }
    };
    $wxr = '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><content:encoded/></item></channel></rss>';

    // Require one LF, not a retained CR or a doubled line break.
    expect(new Transform([$rule])->apply($wxr))
        ->toContain("<content:encoded><![CDATA[first\nsecond]]></content:encoded>");
})->with(['CRLF' => ["\r\n"], 'CR' => ["\r"]]);

test('apply reduces title line endings to single spaces', function (string $newline): void {
    // Add line endings after HTML parsing to isolate title normalization.
    $rule = new readonly class($newline) implements Rule
    {
        /**
         * @param  string  $newline  Line ending inserted into the title.
         */
        public function __construct(private string $newline) {}

        public function apply(HTMLDocument $document): void
        {
            $body = $document->getElementsByTagName('body')->item(0);
            // Only fill an empty body, so repeated pipeline passes settle.
            if ($body?->firstChild === null) {
                $body?->appendChild($document->createTextNode('first'.$this->newline.'second'));
            }
        }
    };

    // Check all supported line endings without default whitespace rules.
    expect(new Transform([$rule])->apply('<rss><channel><item><title/></item></channel></rss>'))
        ->toContain('<title><![CDATA[first second]]></title>');
})->with(['CRLF' => ["\r\n"], 'LF' => ["\n"], 'CR' => ["\r"]]);

test('apply preserves empty-string regex failure fallbacks under PCRE resource exhaustion', function (
    string $element,
    string $fragment,
): void {
    // Exercise the public transform with a real regex error, not a mocked function.
    $previousLimit = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '0');

    try {
        $actual = new Transform([])->apply(
            '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><channel><item><'.$element.'><![CDATA['.$fragment.']]></'.$element.'></item></channel></rss>'
        );
    } finally {
        // Restore PCRE before assertions and the next test's regex-based runner work.
        ini_set('pcre.backtrack_limit', (string) $previousLimit);
    }

    expect($actual)->toContain('<'.$element.'><![CDATA[]]></'.$element.'>');
})->with([
    'title breaks' => ['title', 'one<br>two'],
    'title spaces' => ['title', 'one  two'],
    'excerpt dateline' => ['excerpt:encoded', "LITTLE ROCK, Ark. \u{2014} News"],
    'paired VML' => ['content:encoded', '<v:shape>drawing</v:shape>'],
    'self-closing VML' => ['content:encoded', '<v:shape/>'],
    'paired Office' => ['content:encoded', '<o:p>drawing</o:p>'],
    'self-closing Office' => ['content:encoded', '<o:p/>'],
    'opening smart tag' => ['content:encoded', '<st1:city>City'],
    'closing smart tag' => ['content:encoded', 'City</st1:city>'],
]);

test('apply rejects invalid UTF-8 after the invalid-character regex fails', function (): void {
    // Invalid UTF-8 makes the Unicode regex return null before XML parsing begins.
    expect(fn (): string => new Transform([])->apply("<rss>\x80</rss>"))
        ->toThrow(InvalidArgumentException::class, 'WXR input is empty.');
});

test('apply handles PCRE exhaustion after a custom rule has produced a fragment', function (
    string $element,
    string $fragment,
    string $expected,
): void {
    // Insert exact text bytes so the serializer's escaping cannot change the expectation.
    $rule = new readonly class($fragment) implements Rule
    {
        /**
         * @param  string  $fragment  Raw HTML bytes inserted after parsing.
         */
        public function __construct(private string $fragment) {}

        public function apply(HTMLDocument $document): void
        {
            $body = $document->getElementsByTagName('body')->item(0);
            // Only fill an empty body, so repeated pipeline passes settle.
            if ($body?->firstChild === null) {
                $body?->appendChild($document->createTextNode($this->fragment));
            }
            ini_set('pcre.backtrack_limit', '0');
        }
    };
    $previousLimit = ini_get('pcre.backtrack_limit');

    try {
        // Start with an empty field so only the rule's fragment is serialized.
        $actual = new Transform([$rule])->apply(
            '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><'.$element.'/></item></channel></rss>'
        );
    } finally {
        // Restore PCRE before the runner or assertions use it again.
        ini_set('pcre.backtrack_limit', (string) $previousLimit);
    }

    expect($actual)->toContain('<'.$element.'><![CDATA['.$expected.']]></'.$element.'>');
})->with([
    'CRLF is one space without requiring regex collapse' => ['title', "first\r\nsecond", 'first second'],
]);

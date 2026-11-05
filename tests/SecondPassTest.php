<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * Wrap one body in the smallest WXR that carries it as `<content:encoded>`.
 */
function secondPassWxr(string $body): string
{
    return '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><content:encoded><![CDATA['
        .$body.']]></content:encoded></item></channel></rss>';
}

/**
 * Pull the `<content:encoded>` text back out of the smallest WXR.
 */
function secondPassBody(string $wxr): string
{
    preg_match('/<!\[CDATA\[(.*)\]\]><\/content:encoded>/s', $wxr, $matches);

    return $matches[1] ?? throw new RuntimeException('No content:encoded body in the transformed WXR.');
}

dataset('secondPassBodies', function () {
    $paths = glob(__DIR__.'/fixtures/second-pass/*.html');

    if ($paths === false) {
        return;
    }

    foreach ($paths as $path) {
        yield basename($path, '.html') => [$path];
    }
});

dataset('everyBeforeFixture', function () {
    $paths = glob(__DIR__.'/fixtures/*/*.before.xml');

    if ($paths === false) {
        return;
    }

    foreach ($paths as $path) {
        yield basename(dirname($path)).'/'.basename($path, '.before.xml') => [$path];
    }
});

test('a second pass over cleaned output changes nothing', function (string $path): void {
    $transform = Transform::withDefaultRules();

    $once = $transform->apply(secondPassWxr(Fixture::contents($path)));
    $twice = $transform->apply($once);

    expect($twice)->toBe($once);
})->with('secondPassBodies');

test('a second pass over a cleaned fixture changes nothing', function (string $path): void {
    $transform = Transform::withDefaultRules();

    $once = $transform->apply(Fixture::contents($path));

    expect($transform->apply($once))->toBe($once);
})->with('everyBeforeFixture');

test('a second pass does not strip a dateline that sits inside a layout table', function (): void {
    $transform = Transform::withDefaultRules();
    $body = Fixture::contents(__DIR__.'/fixtures/second-pass/news-16095.html');

    $once = secondPassBody($transform->apply(secondPassWxr($body)));

    // The dateline is gone after the first pass, so a second pass has none left to remove.
    expect($once)->not->toContain('OCT. 25, 2001 |')
        ->and($once)->not->toContain('<table>');
});

test('a bare city dateline gains its state in the first pass, not the second', function (): void {
    $transform = Transform::withDefaultRules();
    $body = Fixture::contents(__DIR__.'/fixtures/second-pass/news-121375.html');

    $once = secondPassBody($transform->apply(secondPassWxr($body)));

    expect($once)->toStartWith('LITTLE ROCK, Ark. '."\u{2014}".' ');
});

test('adjacent lists merge in the first pass', function (): void {
    $transform = Transform::withDefaultRules();
    $body = Fixture::contents(__DIR__.'/fixtures/second-pass/adjacent-lists-after-br.html');

    expect(secondPassBody($transform->apply(secondPassWxr($body))))
        ->toBe('<ul><li>One</li><li>Two</li></ul>');
});

test('caption markup is wrapped in a shortcode once, and a second pass leaves it alone', function (): void {
    $transform = Transform::withDefaultRules();
    $body = Fixture::contents(__DIR__.'/fixtures/second-pass/news-16139.html');

    $once = $transform->apply(secondPassWxr($body));

    // Pass 1 already holds the shortcode text, so pass 2 has no cutline left to caption.
    expect(substr_count($once, '[caption '))->toBe(substr_count($transform->apply($once), '[caption '))
        ->and($transform->apply($once))->toBe($once);
});

/**
 * A rule that adds one paragraph per pass until the body holds `$target` of them.
 */
function growsUntil(int $target): Rule
{
    return new readonly class($target) implements Rule
    {
        /**
         * @param  int  $target  Paragraph count at which the body stops changing.
         */
        public function __construct(private int $target) {}

        public function apply(HTMLDocument $document): void
        {
            $body = $document->getElementsByTagName('body')->item(0);

            if ($body !== null && $document->getElementsByTagName('p')->length < $this->target) {
                $body->appendChild(Fixture::element($document, 'p', 'again'));
            }
        }
    };
}

test('apply settles on the last allowed pass', function (): void {
    // Passes 1 to 4 each add a paragraph and pass 5 changes nothing, the most the cap allows.
    $wxr = new Transform([growsUntil(4)])->apply(secondPassWxr(''));

    expect(substr_count($wxr, '<p>again</p>'))->toBe(4);
});

test('apply stops with a clear error when the rules never settle', function (): void {
    // Passes 1 to 5 each add a paragraph, so the output is still changing when the cap is reached.
    expect(fn (): string => new Transform([growsUntil(5)])->apply(secondPassWxr('')))
        ->toThrow(RuntimeException::class, 'did not settle after 5 passes');
});

test('an excerpt dateline is stripped in the first pass, not left for the second', function (string $dateline): void {
    $transform = Transform::withDefaultRules();
    $wxr = '<rss xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><channel><item><excerpt:encoded><![CDATA['
        .$dateline.' An event to launch a volunteer group will be held Jan. 27.]]></excerpt:encoded></item></channel></rss>';

    $once = $transform->apply($wxr);

    expect($once)->toContain('<![CDATA[An event to launch a volunteer group will be held Jan. 27.]]>')
        ->and($transform->apply($once))->toBe($once);
})->with([
    'en dash' => ["LITTLE ROCK \u{2013}"],
    'hyphen' => ['LITTLE ROCK -'],
]);

test('a void embed is serialized without a closer, whatever closers the source carries', function (string $html, string $expected): void {
    // HTML5 serializes embed as a void element, so no closer survives the round trip.
    $wxr = secondPassWxr($html);

    expect(new Transform([])->apply($wxr))->toContain('<![CDATA['.$expected.']]>');
})->with([
    'empty pair' => ['<embed src="a.swf"></embed>', '<embed src="a.swf">'],
    'two embeds' => ['<embed src="a.swf"><embed src="b.swf"></embed></embed>', '<embed src="a.swf"><embed src="b.swf">'],
    'closer with whitespace and case' => ['<embed src="a.swf"></EMBED >', '<embed src="a.swf">'],
]);

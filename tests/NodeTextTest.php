<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\NodeText;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;

/**
 * Both readers return strings for every node the rules hand them.
 *
 * @command  composer test -- tests/NodeTextTest.php
 */
test('content returns the text of an element and of a text node', function (): void {
    $paragraph = Fixture::first(Fixture::document('<p>a<b>b</b></p>'), 'p');
    $text = $paragraph->firstChild;

    expect(NodeText::content($paragraph))->toBe('ab')
        ->and($text)->not->toBeNull()
        ->and($text === null ? null : NodeText::content($text))->toBe('a');
});

test('attribute returns the value of a present attribute', function (): void {
    $paragraph = Fixture::first(Fixture::document('<p class="x y">a</p>'), 'p');

    expect(NodeText::attribute($paragraph, 'class'))->toBe('x y');
});

test('attribute returns an empty string for an absent attribute and for an empty one', function (string $html): void {
    $paragraph = Fixture::first(Fixture::document($html), 'p');

    expect(NodeText::attribute($paragraph, 'class'))->toBe('');
})->with([
    'absent' => ['<p>a</p>'],
    'empty' => ['<p class="">a</p>'],
]);

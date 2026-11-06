<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\AddMissingCaptionAttributes;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseBareDoubleBr;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CollapseCaptionInternalWhitespace;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\NormalizeCityDateline;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\ReplaceBareBrWithBlankLine;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripLeadingBreaks;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\StripLeadingDateLine;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapCaptionImageAnchors;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\UnwrapWholeContentTable;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;

/**
 * A rule can run after another rule has removed the synthetic body, and must leave the document alone.
 *
 * @command  composer test -- tests/RulesWithoutBodyTest.php
 */
test('a rule that works on the body does nothing once the body is gone', function (Rule $rule): void {
    $document = Fixture::document('<p>x</p>');
    $body = Fixture::first($document, 'body');
    $body->remove();

    $before = $document->saveHtml();
    $rule->apply($document);

    expect($document->saveHtml())->toBe($before);
})->with([
    'AddMissingCaptionAttributes' => [new AddMissingCaptionAttributes],
    'CollapseBareDoubleBr' => [new CollapseBareDoubleBr],
    'CollapseCaptionInternalWhitespace' => [new CollapseCaptionInternalWhitespace],
    'NormalizeCityDateline' => [new NormalizeCityDateline],
    'StripLeadingDateLine' => [new StripLeadingDateLine],
    'UnwrapCaptionImageAnchors' => [new UnwrapCaptionImageAnchors],
    'UnwrapWholeContentTable' => [new UnwrapWholeContentTable],
    'ReplaceBareBrWithBlankLine' => [new ReplaceBareBrWithBlankLine],
    'StripLeadingBreaks' => [new StripLeadingBreaks],
]);

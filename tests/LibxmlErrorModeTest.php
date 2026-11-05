<?php

/**
 * Libxml error capture, queue cleanup, and caller-mode restoration contracts.
 *
 * @command  composer test -- tests/LibxmlErrorModeTest.php
 */

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rule;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

afterEach(function (): void {
    libxml_use_internal_errors(false);
    libxml_clear_errors();
});

test('apply leaves libxml internal-errors mode as it found it', function (bool $mode): void {
    $wxr = (string) file_get_contents(__DIR__.'/fixtures/full-pipeline/01-press-release.before.xml');
    expect($wxr)->not->toBe('');

    libxml_use_internal_errors($mode);

    Transform::withDefaultRules()->apply($wxr);

    expect(libxml_use_internal_errors())->toBe($mode);
})->with([
    'off' => [false],
    'on' => [true],
]);

test('apply clears XML parse errors even when no HTML fields are processed', function (): void {
    // Establish that malformed XML really populates the caller's error queue.
    libxml_use_internal_errors(true);
    $probe = new DOMDocument;
    $probe->loadXML('<rss>');

    expect(libxml_get_errors())->not->toBeEmpty();
    libxml_clear_errors();

    // Require the transform to capture and clear its own XML parsing errors.
    new Transform([])->apply('<rss>');
    expect(libxml_get_errors())->toBe([]);
    expect(libxml_use_internal_errors())->toBeTrue();
});

test('apply captures malformed XML warnings and restores disabled internal errors', function (): void {
    // Record emitted PHP warnings without allowing them to escape the test.
    $warnings = [];
    libxml_use_internal_errors(false);
    set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    // Exercise XML parsing with no later HTML parse that could hide the result.
    try {
        new Transform([])->apply('<rss>');
    } finally {
        restore_error_handler();
    }

    // Require silent capture and restoration of the caller's disabled mode.
    expect($warnings)->toBe([]);
    expect(libxml_use_internal_errors())->toBeFalse();
});

test('apply clears HTML parser errors before invoking rules and restores caller mode', function (bool $mode): void {
    // Prove the malformed fragment generates a libxml HTML parser diagnostic.
    libxml_use_internal_errors(true);
    $probe = new DOMDocument;
    $probe->loadHTML('<?xml encoding="UTF-8"?><body><p>Text</wrong></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    expect(libxml_get_errors())->not->toBeEmpty();
    libxml_clear_errors();
    libxml_use_internal_errors($mode);

    // Observe cleanup at the rule boundary, before another field can clear errors.
    $rule = new readonly class($mode) implements Rule
    {
        /**
         * @param  bool  $mode  Caller error mode expected at the rule boundary.
         */
        public function __construct(private bool $mode) {}

        public function apply(DOMDocument $document): void
        {
            expect(libxml_get_errors())->toBe([]);
            expect(libxml_use_internal_errors())->toBe($this->mode);
        }
    };
    $wxr = '<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><content:encoded><![CDATA[<p>Text</wrong>]]></content:encoded></item></channel></rss>';

    // Require cleanup and mode restoration after the entire public call too.
    new Transform([$rule])->apply($wxr);
    expect(libxml_get_errors())->toBe([]);
    expect(libxml_use_internal_errors())->toBe($mode);
})->with(['off' => [false], 'on' => [true]]);

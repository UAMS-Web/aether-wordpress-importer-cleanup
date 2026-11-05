<?php

declare(strict_types=1);

/**
 * Throwaway check for UAMS-Web/aether-wordpress-importer-cleanup#49: round-trip every
 * `<content:encoded>` of the given WXR files through `Dom\HTMLDocument` (the HTML5 parser
 * bundled with PHP 8.4, not libxml's HTML parser) and print one SHA-256 per file, so the
 * lines from different operating systems can be compared byte for byte.
 *
 * Usage: php scripts/htmldocument-roundtrip-check.php <file.xml>...
 */
function roundTrip(string $html): string
{
    $document = Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body></body></html>', LIBXML_NOERROR);
    $body = $document->body;
    if ($body === null) {
        throw new RuntimeException('Parsed document has no body.');
    }

    $body->innerHTML = $html;

    return str_replace(["\r\n", "\r"], "\n", $body->innerHTML);
}

echo 'PHP ', PHP_VERSION, ' ', PHP_OS_FAMILY, ' libxml ', LIBXML_DOTTED_VERSION, "\n";

$status = 0;
foreach (array_slice($argv, 1) as $path) {
    $xml = new DOMDocument;
    if (! @$xml->load($path, LIBXML_PARSEHUGE | LIBXML_NOCDATA)) {
        fwrite(STDERR, "cannot load {$path}\n");
        $status = 2;

        continue;
    }

    $digest = hash_init('sha256');
    $items = 0;
    $empty = 0;
    foreach ($xml->getElementsByTagName('item') as $item) {
        $id = '';
        $content = null;
        foreach ($item->childNodes as $child) {
            if ($child->nodeName === 'wp:post_id') {
                $id = $child->textContent;
            }
            if ($child->nodeName === 'content:encoded') {
                $content = $child->textContent;
            }
        }
        if ($content === null) {
            continue;
        }
        $items++;
        $out = roundTrip(str_replace(["\r\n", "\r"], "\n", $content));
        if ($out === '' && trim($content) !== '') {
            $empty++;
        }
        hash_update($digest, $id."\t".hash('sha256', $out)."\n");
    }

    printf("%s items=%d empty-from-nonempty=%d sha256=%s\n", $path, $items, $empty, hash_final($digest));
}

exit($status);

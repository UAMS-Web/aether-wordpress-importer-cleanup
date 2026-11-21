<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\ApplyDuplicateDisposition;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\DuplicateDisposition;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Disposition\RedirectPairs;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Disposition\Support;

/**
 * The committed news.uams.edu disposition and its redirect pairs (`UAMS-Web/uams-statamic#46`).
 *
 * The 140 sets of #46 were built from the news export by `bin/build-duplicate-disposition`; the 35
 * sets confirmed on `UAMS-Web/uams-statamic-archive#1639` were added to them from that ticket's
 * decisions. These tests keep the committed files consistent with each other and with the rules a
 * disposition must meet.
 */
function newsDisposition(): DuplicateDisposition
{
    return DuplicateDisposition::fromFile(dirname(__DIR__, 2).'/dispositions/news-uams-edu.json');
}

test('the news disposition is valid and holds the 140 sets #798 records and the 35 confirmed on #1639', function (): void {
    $disposition = newsDisposition();
    $byTicket = array_count_values(array_column($disposition->sets, 'ticket'));
    $dropsFor = fn (string $ticket): int => array_sum(array_map(
        fn (array $set): int => $set['ticket'] === $ticket ? \count($set['drop']) : 0,
        $disposition->sets,
    ));

    expect($disposition->site)->toBe('news.uams.edu');
    expect($byTicket)->toEqual(['UAMS-Web/uams-statamic#46' => 140, 'UAMS-Web/uams-statamic-archive#1639' => 35]);
    expect($dropsFor('UAMS-Web/uams-statamic#46'))->toBe(150);
    expect($dropsFor('UAMS-Web/uams-statamic-archive#1639'))->toBe(55);
});

test('the committed redirect pairs are exactly what the disposition implies', function (): void {
    $committed = file_get_contents(dirname(__DIR__, 2).'/dispositions/news-uams-edu.redirects.json');

    expect($committed)->toBe(RedirectPairs::toJson(newsDisposition()));
});

test('no redirect target is itself a redirect source, and no source repeats', function (): void {
    $pairs = RedirectPairs::from(newsDisposition());
    $sources = array_column($pairs, 'source');

    expect(array_values(array_intersect(array_column($pairs, 'target'), $sources)))->toBe([]);
    expect(\count(array_unique($sources)))->toBe(\count($sources));
});

// Only the member half of the URL rule can be checked here: whether a published post elsewhere
// holds the unsuffixed slug needs the export, which is not in this repository. The finder tests
// cover that half, and the build was checked against the export (#798).
test('a canonical slug keeps a -N only when no member holds the unsuffixed slug', function (): void {
    foreach (newsDisposition()->sets as $set) {
        if (preg_match('/^(.*)-\d+$/', $set['canonical_slug'], $match) !== 1) {
            continue;
        }

        $memberSlugs = array_column([$set['keep'], ...$set['drop']], 'slug');
        expect($memberSlugs)->not->toContain($match[1]);
    }
});

/**
 * The committed disposition applied to the members of real sets, as they stand in the news export:
 * a duplicate set, a split set, a distinct set and a set whose text comes from a non-canonical member
 * (`UAMS-Web/uams-statamic-archive#1639`).
 *
 * @param  list<array{id: int, slug: string, date: string}>  $members
 * @return array{slugs: array<int, string>, dropped: list<int>, pairs: list<array<string, mixed>>}
 */
function applyCommittedTo(array $members): array
{
    $wxr = Support::wxr(array_map(fn (array $member): array => [
        'id' => $member['id'],
        'slug' => $member['slug'],
        'date' => $member['date'].' 10:00:00',
    ], $members));
    $result = new ApplyDuplicateDisposition(newsDisposition())->apply($wxr);

    preg_match_all('#<wp:post_id>(\d+)</wp:post_id>.*?<wp:post_name><!\[CDATA\[([^\]]*)\]\]></wp:post_name>#s', $result['wxr'], $found, PREG_SET_ORDER);

    return [
        'slugs' => array_column(array_map(fn (array $match): array => [(int) $match[1], $match[2]], $found), 1, 0),
        'dropped' => $result['dropped'],
        'pairs' => RedirectPairs::from(newsDisposition()),
    ];
}

/**
 * @param  list<array<string, mixed>>  $pairs
 * @return list<array<string, mixed>>
 */
function pairsFrom(array $pairs, string $sourceUrl): array
{
    return array_values(array_filter($pairs, fn (array $pair): bool => $pair['source'] === $sourceUrl));
}

test('a confirmed duplicate set keeps its canonical post and redirects every other member to it', function (): void {
    $result = applyCommittedTo([
        ['id' => 12292, 'slug' => 'patient-receives-stents-and-bypass-a-uams-first', 'date' => '2009-01-06'],
        ['id' => 16870, 'slug' => 'patientreceivesstentsandbypassauamsfirst', 'date' => '2009-01-06'],
        ['id' => 27898, 'slug' => 'patient-receives-stents-and-bypass-a-uams-first-2', 'date' => '2015-06-30'],
    ]);
    $target = 'https://news.uams.edu/2009/01/06/patient-receives-stents-and-bypass-a-uams-first/';

    expect($result['dropped'])->toBe([16870, 27898]);
    expect($result['slugs'])->toBe([12292 => 'patient-receives-stents-and-bypass-a-uams-first']);

    foreach (['https://news.uams.edu/2009/01/06/patientreceivesstentsandbypassauamsfirst/' => 16870, 'https://news.uams.edu/2015/06/30/patient-receives-stents-and-bypass-a-uams-first-2/' => 27898] as $source => $postId) {
        $rows = pairsFrom($result['pairs'], $source);
        expect($rows)->toHaveCount(1);
        expect($rows[0]['target'])->toBe($target);
        expect($rows[0]['response'])->toBe('301');
        expect($rows[0]['source_wordpress_post_id'])->toBe($postId);
        expect($rows[0]['target_wordpress_post_id'])->toBe(12292);
    }
});

test('a split set drops only its duplicate pair and keeps the distinct post, unredirected', function (): void {
    $result = applyCommittedTo([
        ['id' => 12222, 'slug' => 'uams-department-of-orthopaedics-reaches-out-to-homeless', 'date' => '2008-11-24'],
        ['id' => 16806, 'slug' => 'uamsdepartmentoforthopaedicsreachesouttohomeless', 'date' => '2008-11-24'],
        ['id' => 12595, 'slug' => 'uams-department-of-orthopaedics-reaches-out-to-homeless-2', 'date' => '2009-12-01'],
    ]);
    $distinctUrl = 'https://news.uams.edu/2009/12/01/uams-department-of-orthopaedics-reaches-out-to-homeless-2/';

    expect($result['dropped'])->toBe([16806]);
    expect($result['slugs'])->toBe([
        12222 => 'uams-department-of-orthopaedics-reaches-out-to-homeless',
        12595 => 'uams-department-of-orthopaedics-reaches-out-to-homeless-2',
    ]);
    expect(pairsFrom($result['pairs'], $distinctUrl))->toBe([]);
    expect(array_column($result['pairs'], 'target'))->not->toContain($distinctUrl);
});

test('a distinct set is left whole: no member is dropped or redirected', function (): void {
    $result = applyCommittedTo([
        ['id' => 14455, 'slug' => 'multiplemyeloma', 'date' => '2004-05-03'],
        ['id' => 17866, 'slug' => 'multiplemyeloma-2', 'date' => '2010-09-27'],
    ]);

    expect($result['dropped'])->toBe([]);
    expect($result['slugs'])->toBe([14455 => 'multiplemyeloma', 17866 => 'multiplemyeloma-2']);
    expect(pairsFrom($result['pairs'], 'https://news.uams.edu/2004/05/03/multiplemyeloma/'))->toBe([]);
    expect(pairsFrom($result['pairs'], 'https://news.uams.edu/2010/09/27/multiplemyeloma-2/'))->toBe([]);
});

test('a set whose text comes from a non-canonical member keeps that text under the canonical slug', function (): void {
    $slug = 'uams-sponsored-midsouth-summit-black-expo-celebrates-black-history-month-with-health-screenings-and-more';
    $result = applyCommittedTo([
        ['id' => 11572, 'slug' => $slug, 'date' => '2005-02-04'],
        ['id' => 11928, 'slug' => $slug.'-2', 'date' => '2007-02-12'],
    ]);
    $canonical = "https://news.uams.edu/2007/02/12/{$slug}/";

    expect($result['dropped'])->toBe([11572]);
    expect($result['slugs'])->toBe([11928 => $slug]);

    foreach (["https://news.uams.edu/2005/02/04/{$slug}/", "https://news.uams.edu/2007/02/12/{$slug}-2/"] as $source) {
        $rows = pairsFrom($result['pairs'], $source);
        expect($rows)->toHaveCount(1);
        expect($rows[0]['target'])->toBe($canonical);
    }
});

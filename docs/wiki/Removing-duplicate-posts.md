# Removing duplicate posts

Written against `main` after v2.1.0.

Some sites carry the same story more than once, under a hyphenated slug and a concatenated or `-N` slug. A **disposition** names, for each such set, the one post to keep and the posts to drop. This guide applies one, rebuilds the news.uams.edu sets from an export, and writes the redirects the dropped URLs need.

## Apply a disposition

```
php bin/wxr-cleanup --disposition=dispositions/news-uams-edu.json input.xml output.xml
```

The drop works on whole `<item>` blocks, before the body rules run and never inside them. The importer also runs the body rules on single post bodies ([`UAMS-Web/wordpress-importer-archive#1303`](https://github.com/UAMS-Web/wordpress-importer-archive/issues/1303)), and a body alone has no post to drop. Dropped posts are never imported, so nothing is deleted from the live site or from any content repository. Each dropped post's URL gets a redirect pair instead (below).

Applying a disposition:

- **Drops** each `post` item it marks to drop.
- **Renames** each kept post to its set's canonical slug, when its slug differs.
- **Checks before acting.** A post whose slug in the export differs from the slug the disposition records stops the run, so a disposition applied to the wrong site's export, or to an export taken after a post was edited, drops nothing.
- **Skips** a post the export lacks. One site's posts can be split across several export files (`.000.xml`, `.001.xml`, ...), and each file is cleaned on its own.
- **Is idempotent.** A kept post already at its canonical slug is left alone, so a second run changes nothing.
- **Touches only `post` items.** An attachment or page sharing a dropped post's id is left in place.

## Rebuild the news.uams.edu sets

`dispositions/news-uams-edu.json` holds 175 sets: 140 decided by rule on [`UAMS-Web/uams-statamic#46`](https://github.com/UAMS-Web/uams-statamic/issues/46), and 35 that an editor confirmed one at a time on [`UAMS-Web/uams-statamic-archive#1639`](https://github.com/UAMS-Web/uams-statamic-archive/issues/1639) (35 duplicates, 55 dropped posts; the ten sets recorded as distinct are not in the file, and two sets are split, so their distinct member is kept and not redirected). Each set names its ticket. `bin/build-duplicate-disposition` rebuilds the first 140 from the news export by the rule in `DuplicateSetFinder`:

- **Group** posts whose slugs match once hyphens and any trailing `-N` are removed, and whose publish dates fall on the same day.
- **Guard:** a trailing `-N` is removed for grouping only when the set also has a member with that slug minus the number, or when the members' titles match (case, whitespace and curly quotes folded).
- **Exclude** a set when any member's permalink is in `dispositions/news-uams-edu.excluded.txt`. That file holds the permalinks [`UAMS-Web/uams-statamic-archive#1639`](https://github.com/UAMS-Web/uams-statamic-archive/issues/1639) reviews and [`UAMS-Web/uams-statamic-archive#2810`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2810) applies.
- **URL:** the hyphenated slug with its `-N` removed, when a member holds that slug or no published post does. Otherwise the kept post keeps its own slug.
- **Text and date:** the most complete published member, where word counts within 2% are equal, then the member already at the canonical slug, then the earliest.

```
php bin/build-duplicate-disposition --site=news.uams.edu --ticket='UAMS-Web/uams-statamic#46' \
    --excluded=dispositions/news-uams-edu.excluded.txt dispositions/news-uams-edu.json \
    wordpress-export_news-uams-edu.000.xml wordpress-export_news-uams-edu.001.xml wordpress-export_news-uams-edu.002.xml
```

It prints `Read N posts; wrote M sets to <file>.` Rebuilding after the export changes rewrites the whole file, and the rebuild does not know the 35 sets confirmed on #1639: it leaves them out, because their permalinks are in the excluded list. Put them back (every set whose `ticket` is `UAMS-Web/uams-statamic-archive#1639`) before committing, and review the diff.

## Write the redirect pairs

```
php bin/wxr-redirect-pairs dispositions/news-uams-edu.json dispositions/news-uams-edu.redirects.json
```

This writes the redirects a disposition implies, derived from the disposition alone rather than from an export. `uams-statamic`'s `content:generate-migration-redirects --dedup-redirects=<file>` reads the file ([`UAMS-Web/uams-statamic-archive#2943`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2943)) and turns each row into one `301`, from the old URL straight to the kept post's Statamic URL.

The committed `dispositions/news-uams-edu.redirects.json` must equal this script's output, and `NewsDispositionTest` fails when it drifts. So regenerate it, and commit it, whenever the disposition changes.

## Reference

- [Disposition file](Disposition-file.md): every key of the disposition, the rules a file must meet, and the redirect pair rows.
- [Commands](Commands.md): the arguments and exit status of all three commands.
- [Troubleshooting](Troubleshooting.md): the slug-mismatch stop and the other disposition errors.

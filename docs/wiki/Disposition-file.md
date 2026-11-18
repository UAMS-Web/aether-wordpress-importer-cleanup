# Disposition file

Written against `main` after v2.1.0.

A disposition file tells `wxr-cleanup --disposition` which duplicate posts to drop and which to keep, and `wxr-redirect-pairs` reads the same file to write the redirects the drops need. There is one file per site, `dispositions/<site>.json`. [`UAMS-Web/aether-wordpress-importer#89`](https://github.com/UAMS-Web/aether-wordpress-importer/issues/89) reads the same file for posts captured through the migration API.

## Example

```json
{
    "format": 1,
    "site": "news.uams.edu",
    "permalink_base": "https://news.uams.edu",
    "sets": [
        {
            "ticket": "UAMS-Web/uams-statamic#46",
            "canonical_slug": "former-student-delivers-inaugural-yasargil-lecture-at-uams",
            "keep": {"post_id": 12240, "slug": "former-student-delivers-inaugural-yasargil-lecture-at-uams", "date": "2008-10-06"},
            "drop": [
                {"post_id": 16831, "slug": "formerstudentdeliversinauguralyasargillectureatuams", "date": "2008-10-06"}
            ]
        }
    ]
}
```

## Keys

| key | meaning |
| --- | --- |
| `format` | `1`. A file with any other value is refused. |
| `site` | The site's public host. |
| `permalink_base` | The `https` scheme and host every public permalink starts with, with no path. Permalinks are `{permalink_base}/YYYY/MM/DD/{slug}/`. |
| `sets[].ticket` | The ticket that decided the set, as `owner/repo#N`. |
| `sets[].canonical_slug` | The slug the kept post ends up with. Lowercase words joined by single hyphens. |
| `sets[].keep` | The kept post: its WordPress `post_id` (a positive integer), its `slug` as exported, and its publish day as `YYYY-MM-DD`. The kept post's text and date are the ones that survive. |
| `sets[].drop` | A non-empty list of the posts to drop, in the same shape. |

## Rules a file must meet

A file breaking any of these is refused before anything is applied:

- A post id appears in one set only, kept or dropped.
- No set redirects a URL that is another set's canonical URL, since the redirects would chain.
- No two sets redirect the same URL.

## Redirect pairs

`wxr-redirect-pairs` turns a disposition into a JSON list of redirect rows:

```json
[
    {
        "source": "https://news.uams.edu/2008/10/06/formerstudentdeliversinauguralyasargillectureatuams/",
        "target": "https://news.uams.edu/2008/10/06/former-student-delivers-inaugural-yasargil-lecture-at-uams/",
        "response": "301",
        "notes": "Duplicate of WordPress post 12240 (UAMS-Web/uams-statamic#46).",
        "source_wordpress_post_id": 16831,
        "target_wordpress_post_id": 12240
    }
]
```

- **Rows:** one for each dropped post, and one for a kept post's old URL when its slug changes. A kept post's row has no `source_wordpress_post_id`, because that post is not dropped.
- **URLs:** `source` and `target` are public permalinks. `target` is always the canonical URL, never another row's `source`.
- **Post ids:** `target_wordpress_post_id` lets the redirect generator find the kept entry by its WordPress post id ([`UAMS-Web/uams-statamic-archive#2960`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2960)). The exports' own links use a local host, and some use the `?p=` form, so a URL alone cannot find it.
- **Skipped:** a dropped post whose URL equals the canonical URL gets no row, since that URL still resolves. Each source is listed once.

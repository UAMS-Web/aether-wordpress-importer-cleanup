# Commands

Written against `main` after v2.1.0.

The package ships three commands in `bin/`. In a clone of this repository, run them with `php bin/<command>`: Composer does not link a package's own scripts into `vendor/bin` there. A project that installs the package through Composer gets them as `vendor/bin/<command>`.

Each command writes an error to stderr as a line starting `Error:` and exits `1`, and exits `0` on success. The 5-pass cap in `wxr-cleanup` is reported the same way (see [Troubleshooting](Troubleshooting.md#cleanup-rules-did-not-settle-after-5-passes)).

## `wxr-cleanup`

Cleans one WXR file.

```
wxr-cleanup [--disposition=<file.json>] <input.xml> [output.xml]
```

| Argument | Effect |
| --- | --- |
| `<input.xml>` | The raw WXR file. Required, and must be readable. |
| `[output.xml]` | Where the cleaned WXR is written. Its directory must exist, and it must not be the input file. Without it, the cleaned WXR goes to stdout. |
| `--disposition=<file.json>` | Drops the duplicate posts a [disposition file](Disposition-file.md) names, and renames the kept ones, before the body rules run. |

An unknown `--` option, more than two paths, or no path at all prints the usage and exits `1`.

**What it cleans.** Each item's `<title>`, `<content:encoded>`, `<excerpt:encoded>` and `_uams_summary` postmeta value; every other WXR element is left untouched. The rules and their order are on [Cleanup rules](Cleanup-rules.md).

**Running it twice changes nothing.** Each field is cleaned again over its own output until a pass changes nothing, so a second run over cleaned output is a no-op. A rule can expose structure that only a later pass lets an earlier rule act on, such as a layout table unwrapped before its dateline can be stripped, and the repeat resolves that inside the first run. The cap is 5 passes; see [Troubleshooting](Troubleshooting.md) for what happens past it. The reasoning is in [`docs/DECISIONS.md`](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/blob/main/docs/DECISIONS.md).

**Lines it prints to stderr:**

| Line | When |
| --- | --- |
| `Disposition: dropped N post(s), renamed M.` | With `--disposition`, after the drops and before the body rules. |
| `Warning: N post(s) had a body that cleaned to nothing: wp:post_id 12206.` | A post's `<content:encoded>` held text but cleaned to an empty body. The file is still written and the exit status stays `0`: inspect the named posts before importing. |
| `Environment: PHP <version>, libxml <version>, tool commit <sha>.` | Every run. The commit comes from the checkout the tool runs from, or from Composer's installed reference when it is a dependency, and reads `unknown` when neither is available. |

An unclosed `<o:p>` or `<v:*>` tag loses only the tag, not the text after it. Elements that open and close, or that self-close, are still removed with their content.

### From PHP

```php
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

$cleaned = Transform::withDefaultRules()->apply($wxr);
```

`Transform::withDefaultRules()` runs the same pipeline as the command. `Transform::applyReporting()` returns the cleaned WXR under `wxr` and the labels of posts whose body cleaned to nothing under `emptied`.

## `build-duplicate-disposition`

Rebuilds a site's disposition file from its export parts.

```
build-duplicate-disposition --site=<host> --ticket=<owner/repo#N> --excluded=<urls.txt> <output.json> <export.xml>...
```

| Argument | Effect |
| --- | --- |
| `--site=<host>` | The site's public host, written as the file's `site`. |
| `--ticket=<owner/repo#N>` | The ticket that decided the sets, written on each set. |
| `--excluded=<urls.txt>` | One permalink per line; a set with any member listed here is left out. A line that is not a permalink stops the run. |
| `<output.json>` | The disposition file to write. It is rewritten whole. |
| `<export.xml>...` | Every export part of the site. |

It prints `Read N posts; wrote M sets to <output.json>.` The grouping rule is described in [Removing duplicate posts](Removing-duplicate-posts.md#rebuild-the-newsuamsedu-sets).

## `wxr-redirect-pairs`

Writes the redirects a disposition implies.

```
wxr-redirect-pairs <disposition.json> [pairs.json]
```

Without `pairs.json`, the rows go to stdout. The row format is on [Disposition file](Disposition-file.md#redirect-pairs).

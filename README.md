# aether-wordpress-importer-cleanup

Deterministic CLI tool to transform WordPress XML exports (WXR) for [news.uams.edu](https://news.uams.edu/) and [inside.uams.edu](https://inside.uams.edu/news-archive/), stripping legacy `<table>` markup and Microsoft-Word-paste noise so the Statamic importer in [`uams-web/uams-statamic`](https://github.com/UAMS-Web/uams-statamic) consumes clean content.

The live WordPress sites are **not modified by this tool.** Cleanup runs offline as a transform on the WXR files in [`uams-web/wordpress-importer-exports`](https://github.com/UAMS-Web/wordpress-importer-exports). Re-runnable, fixture-tested, idempotent. Originating ticket: [`uams-web/uams-statamic-archive#475`](https://github.com/UAMS-Web/uams-statamic-archive/issues/475).

> [!WARNING]
> **Not a general-purpose WordPress tool.** This cleaner was built for the post bodies of news.uams.edu and the inside.uams.edu news archive, as those sites stood in May and June 2026. Every rule encodes that content's specific legacy markup, Word-paste patterns, datelines and domains. It is published for reference and for the UAMS migration only. Running it on any other site, or on these sites' later content, is unsupported and may damage the content.

## Renamed from `wordpress-wxr-cleanup`

The repository, the Composer package and the PHP namespace were renamed together. A consumer changes three things:

| Was | Now |
| --- | --- |
| Package `uams-web/wordpress-wxr-cleanup` | `uams-web/aether-wordpress-importer-cleanup` |
| Namespace `UamsWeb\WxrCleanup` | `UAMSWeb\Aether\WordPressImporter\Cleanup` |
| Remote `UAMS-Web/wordpress-wxr-cleanup` | `UAMS-Web/aether-wordpress-importer-cleanup` (the old URL redirects) |

An existing checkout updates its remote with `git remote set-url origin https://github.com/UAMS-Web/aether-wordpress-importer-cleanup.git`. The `wxr-cleanup`, `wxr-redirect-pairs` and `build-duplicate-disposition` commands keep their names.

## Setup

- PHP 8.4+
- `ext-dom`, `ext-libxml` (both ship with stock PHP)

From a clone of this repo:

```
composer install
```

Composer does not symlink this package's own `bin/wxr-cleanup` into `vendor/bin` when you're working in the repo itself — invoke the script with PHP (see Usage). If another project installs this package via Composer, `vendor/bin/wxr-cleanup` is available there.

## Usage

```
php bin/wxr-cleanup input.xml output.xml
```

Reads a raw WXR file, applies every configured cleanup rule, and writes the cleaned WXR to `output.xml`. Omit the output path to write to stdout (for piping). The Statamic importer is pointed at the cleaned output; the raw WXR stays in `wordpress-importer-exports` as the source artifact.

### From PHP

```php
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

$cleaned = Transform::withDefaultRules()->apply($wxr);
```

`Transform::withDefaultRules()` runs the same pipeline as the CLI. The rule order is defined once, in `Transform::defaultRules()`, and the CLI, the full-pipeline test and any library consumer all use it. `DefaultRulesTest` pins that order and fails if a rule class in `src/Rules/` is left out of it.

What the command prints to stderr, its exit status, and why a second run changes nothing are on the wiki's [Commands](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Commands) page.

## Documentation

The [wiki](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki) holds the long-form documentation:

- [Cleaning an export](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Cleaning-an-export): clean an export file and publish it with its environment.
- [Removing duplicate posts](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Removing-duplicate-posts): apply a duplicate disposition, rebuild the news.uams.edu sets, and write their redirect pairs.
- [Commands](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Commands), [Cleanup rules](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Cleanup-rules) and [Disposition file](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Disposition-file): the reference.
- [Troubleshooting](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Troubleshooting): each error and warning the commands print.

The pages are written in [`docs/wiki/`](docs/wiki/) and published by the `Publish wiki` workflow; edit them there through a pull request.

## Tests

```
composer test
```

Every rule pairs with a corpus of `tests/fixtures/<rule>/*.before.xml` / `*.after.xml` files. The test for each rule runs the transform against every `*.before.xml` and asserts byte-equality with the corresponding `*.after.xml`. New observed dirty patterns turn into new fixture pairs.

### Mutation testing

```
composer mutate -- --class="UAMSWeb\\Aether\\WordPressImporter\\Cleanup\\Rules\\CollapseBareDoubleBr" --no-cache
```

Use `composer mutate`, never a bare `vendor/bin/pest --mutate`. It passes relative `--path` values (`src`), and on Windows that matters twice. `phpunit.xml` hands the mutate plugin an absolute `src` path, and the plugin's `FileFinder` treats a drive-letter path (`D:\...`) as relative and prepends the working directory, so it finds no files and prints `0 Mutations for 0 Files created`. A bare `vendor/bin/pest` also reaches each mutant's child process with forward slashes, which `cmd.exe` cannot launch, so every mutant can score as killed without running. The `@php` prefix in the script normalizes the separator.

A run that worked prints a non-zero `Files created` count and, on a class with weak tests, some `UNTESTED` survivors. Measured on Windows 11, PHP 8.4.27 with PCOV, `pest-plugin-mutate` 4.0.1: `CollapseBareDoubleBr` reports `0 Mutations for 0 Files created` without `--path=src` and `74 Mutations for 1 Files created` with it. macOS is not measured.

### Adding a fixture

1. Author a `<id>.before.xml` containing the dirty WXR snippet you want to cover.
2. Run `php bin/wxr-cleanup tests/fixtures/<rule>/<id>.before.xml tests/fixtures/<rule>/<id>.after.xml`.
3. Read the captured `.after.xml` and verify the transform did what you expected.
4. Run `composer test` and confirm the new pair passes.

## Pipeline

Cleaned WXR is published in `uams-web/wordpress-importer-exports` beside the raw exports, with the environment that produced it. The steps are on the wiki's [Cleaning an export](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/wiki/Cleaning-an-export) page.

## Local CI

```bash
node .claude/skills/local-ci/local-ci.mjs --dry-run
node .claude/skills/local-ci/local-ci.mjs --all
```

The same checks run remotely on every pull request (`.github/workflows/ci.yml`). `main` requires three checks to pass before a merge: `CI` (the aggregate of the jobs in `ci.yml`), `Attribution footer` and `Closing keywords` (the two pull-request body checks). The local runner gives the same verdict before a push.

## License

Released under the [MIT License](LICENSE). Copyright (c) 2026 University of Arkansas for Medical Sciences.

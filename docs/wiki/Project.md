# Project

Written against `main` after v2.1.0.

The tool is developed in [`UAMS-Web/aether-wordpress-importer-cleanup`](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup). It was started on [`UAMS-Web/uams-statamic-archive#475`](https://github.com/UAMS-Web/uams-statamic-archive/issues/475) for the UAMS move from WordPress to Statamic, and is published for reference and for that migration only.

## Development

The [README](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup#readme) keeps the development notes: running the tests, mutation testing, adding a fixture for a new dirty pattern, and the local CI runner. Every rule has a corpus of `tests/fixtures/<rule>/*.before.xml` and `*.after.xml` pairs, and its test asserts that each `before` file cleans to its `after` file byte for byte.

Design decisions, with the alternatives that were rejected, are in [`docs/DECISIONS.md`](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/blob/main/docs/DECISIONS.md).

## Reporting a problem

Open an issue in the [tracker](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/issues). Include the command you ran, every stderr line it printed (the `Environment:` line names the PHP, libxml and tool versions), and the `wp:post_id` of a post that shows the problem.

## Releases

Releases are listed on the [Releases](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/releases) page. These pages are published from `docs/wiki/` on each release, and by hand.

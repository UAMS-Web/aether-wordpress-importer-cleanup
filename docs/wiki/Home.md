# aether-wordpress-importer-cleanup

This tool cleans the WordPress XML exports (WXR) of news.uams.edu and the inside.uams.edu news archive before the Statamic importer in [`UAMS-Web/uams-statamic`](https://github.com/UAMS-Web/uams-statamic) reads them. It strips legacy table layouts and Microsoft Word paste noise, and it can drop duplicate posts. It runs offline on export files and never changes a live site.

**Warning:** this is not a general-purpose WordPress tool. Every rule encodes the legacy markup of those two sites as they stood in May and June 2026. Running it on another site, or on their later content, is unsupported and may damage the content.

This wiki holds the walkthrough, the task guides, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup#readme) keeps the overview, the requirements, installation and the development notes.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup#setup): PHP 8.4 and `composer install`.
- [Cleaning an export](Cleaning-an-export.md): clean one export file and publish the result.

## Guides

- [Removing duplicate posts](Removing-duplicate-posts.md): apply a disposition, rebuild the news.uams.edu sets and write their redirects.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per error or warning the commands print, with its cause and fix.

## Reference

- [Commands](Commands.md): `wxr-cleanup`, `build-duplicate-disposition` and `wxr-redirect-pairs`, their arguments, output and exit status.
- [Cleanup rules](Cleanup-rules.md): every rule, in the order the pipeline runs it.
- [Disposition file](Disposition-file.md): the duplicate disposition format and the redirect pairs written from it.

## Project

- [Project](Project.md): where the tool is developed, how it is tested, and how to report a problem.

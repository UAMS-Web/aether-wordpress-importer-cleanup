# Cleaning an export

Written against `main` after v2.1.0.

At the end of this walkthrough you have a cleaned WXR file, and the environment line that has to go with it when it is published. The raw exports and the cleaned files both live in [`UAMS-Web/wordpress-importer-exports`](https://github.com/UAMS-Web/wordpress-importer-exports); the Statamic importer reads the cleaned ones.

**Note:** the live WordPress sites are never touched. Cleaning is an offline transform from one file to another.

1. **Install the tool.** From a clone of this repository, with PHP 8.4, run `composer install`. See [Installation (README)](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup#setup).

2. **Clean one export file.**

   ```
   php bin/wxr-cleanup wordpress-export_example-site.000.xml cleaned/wordpress-export_example-site.000.xml
   ```

   The input and output must be different files, and the output's directory must already exist. Leave out the output path to write the cleaned WXR to stdout instead.

3. **For a news.uams.edu export, pass the disposition too.** It drops the duplicate posts before the body rules run:

   ```
   php bin/wxr-cleanup --disposition=dispositions/news-uams-edu.json wordpress-export_news-uams-edu.000.xml cleaned/wordpress-export_news-uams-edu.000.xml
   ```

   The run prints `Disposition: dropped N post(s), renamed M.` to stderr. Clean each export part (`.000.xml`, `.001.xml`, ...) on its own; a part that lacks some of the disposition's posts skips them. [Removing duplicate posts](Removing-duplicate-posts.md) explains the disposition.

4. **Read stderr.** Every run ends with one line naming the environment that produced the file:

   ```
   Environment: PHP 8.4.27, libxml 2.11.9, tool commit 2816d690119ecb9fb6f2850243b6ff33ebc5e112.
   ```

   A `Warning: N post(s) had a body that cleaned to nothing: ...` line names posts to inspect before importing; the file is still written and the exit status is still `0`. Any `Error:` line means nothing was written; see [Troubleshooting](Troubleshooting.md).

5. **Publish the cleaned file with its environment.** Commit the cleaned file to `wordpress-importer-exports` beside its raw export, and copy the `Environment:` line into that commit's message. Cleaned output can differ between PHP and libxml versions (attribute serialization is the known case, tracked in [`#6`](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/issues/6)), so the commit that added a file is where its environment is found (`git log -1 -- <file>`). A later re-clean can then be compared against the environment that made the file it replaces.

**Note:** the cleaned parts published in `wordpress-importer-exports` at `6563e05a2` carry no recorded environment, so a difference between a fresh clean and those files cannot be attributed to a PHP or libxml version.

Running the tool again over its own output changes nothing, so a cleaned file can be re-cleaned safely.

## Going further

- [Removing duplicate posts](Removing-duplicate-posts.md): what a disposition drops and renames, and the redirects it implies.
- [Commands](Commands.md): every argument, output line and exit status.
- [Cleanup rules](Cleanup-rules.md): what each rule changes.

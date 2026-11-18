# Troubleshooting

Written against `main` after v2.1.0.

- [Warning: N post(s) had a body that cleaned to nothing](#warning-n-posts-had-a-body-that-cleaned-to-nothing)
- [Post N has slug "..." in this export, but the disposition records "..."](#post-n-has-slug--in-this-export-but-the-disposition-records-)
- [Cleanup rules did not settle after 5 passes](#cleanup-rules-did-not-settle-after-5-passes)
- [A disposition file is refused](#a-disposition-file-is-refused)
- [Error: input and output must be different files](#error-input-and-output-must-be-different-files)
- [A re-clean differs from a published cleaned file](#a-re-clean-differs-from-a-published-cleaned-file)

## Warning: N post(s) had a body that cleaned to nothing

```
Warning: 1 post(s) had a body that cleaned to nothing: wp:post_id 12206.
```

**Cause:** the post's `<content:encoded>` held text, but every part of it was markup the rules remove, so the cleaned body is empty.

**Fix:** none is needed to finish the run. The file is written and the exit status is `0`. Open the named posts in the raw export and decide, before importing, whether the original body held real content a rule removed.

## Post N has slug "..." in this export, but the disposition records "..."

```
Error: Post 16831 has slug "example-slug" in this export, but the disposition records "formerstudentdeliversinauguralyasargillectureatuams". Refusing to act on a post the disposition may not mean.
```

**Cause:** the disposition was written against a different export: another site's, or one taken after the post was edited. The run stops before dropping or renaming anything.

**Fix:** check that the disposition matches the site of the export. If the export is newer, rebuild the disposition from it ([Removing duplicate posts](Removing-duplicate-posts.md#rebuild-the-newsuamsedu-sets)) and review the diff.

## Cleanup rules did not settle after 5 passes

```
Cleanup rules did not settle after 5 passes; a rule keeps undoing another rule's work.
```

**Cause:** a field was still changing after 5 cleanup passes, so one rule is undoing another rule's work on some input. The run stops with an uncaught `RuntimeException` carrying this message, and no output is written, rather than writing a half-settled body.

**Fix:** report it with the post that triggers it ([Project](Project.md#reporting-a-problem)). The two rules need their order or their match conditions changed; raising the cap would only hide the loop.

## A disposition file is refused

```
Error: A disposition must declare "format": 1.
```

**Cause:** the file breaks one of the rules on [Disposition file](Disposition-file.md#rules-a-file-must-meet). The message names the set and the key, for example `set 2: "drop" must be a non-empty list.` or `sets 1 and 3 both redirect <url>.`

**Fix:** correct the named set. Nothing was applied, so the run can simply be repeated.

## Error: input and output must be different files

**Cause:** the output path resolves to the input file. Cleaning in place would lose the raw export.

**Fix:** write to another file, then replace the original yourself if that is really intended. The raw export is the source artifact and normally stays as it is.

## A re-clean differs from a published cleaned file

**Cause:** cleaned output can differ between PHP and libxml versions; attribute serialization is the known case ([`#6`](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/issues/6)).

**Fix:** compare the `Environment:` line of the new run with the one in the commit message that published the old file (`git log -1 -- <file>` in `wordpress-importer-exports`). The parts published at `6563e05a2` carry no recorded environment, so a difference against those cannot be attributed to a version.

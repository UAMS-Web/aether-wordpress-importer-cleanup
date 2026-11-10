# Temporary development patches

The patches target `pestphp/pest-plugin-mutate` `v5.0.2`
(`d0c87110416b699413cc073b8dcc92e440d81931`), which Pest `^5.3` installs.

## Mutation run correctness (seven shared patches)

The seven mutation-run patches come from `uams-web/patches-pest-plugin-mutate`
`v1.0.0`, a `require-dev` dependency. `patches.json` lists them by their
`vendor/uams-web/patches-pest-plugin-mutate/patches/` path, so they are not
copied into this directory and nothing this repository installs reaches its
consumers. On Windows the stock plugin counts a mutant as killed when its test
process never ran a test, so a mutation run scores 100% whatever the suite
detects (UAMS-Web/uams-claude-skills#544; upstream pestphp/pest#1937, fixed by
the unmerged pestphp/pest-plugin-mutate#45). The patches launch each mutant
child correctly, stop silent `@pest-mutate-ignore` parsing defects, and keep a
filtered or parallel run from scoring every mutant killed. A patch is changed
or retired in a release of the package (UAMS-Web/uams-claude-skills#548), or
when an installable upstream release carries the fix.

## Class-constant mutation coverage

`pest-class-constant-coverage.patch` was written for `v4.0.1`
(`d9b32b60b2385e1688a68cc227594738ec26d96c`) and still applies to the `v5.0.2`
plugin. With it removed, `tests/ConstantMutationCoverageTest.php` fails on
`v5.0.2` (944 tests, 1 failed) and passes with it, so the patch is kept. It
supports the coverage work in #11 without changing application behavior.

The stock plugin selects tests using coverage on a mutant's source lines.
Class-constant initializers have no executable lines, so their mutants are
reported as uncovered without starting a test process. The patch maps these
initializer mutants to the containing class's range. Tests exercising that
class then run against the mutant. Executable expressions and constant
attributes retain their original ranges. The printer consequently reports a
constant mutant at the class's first line, rather than its initializer line.

This is a class-local workaround, not general constant-use analysis. A class
whose constants are only accessed externally and which has no executable
coverage can still be reported uncovered. No uncovered mutant is counted as
killed by the patch.

Registration lives in root-only `patches.json`, not `extra.patches`, so this
library's development patch does not propagate to its Composer consumers.
`patches.lock.json` records its checksum. After changing the patch, run
`composer patches-relock` and reinstall `pestphp/pest-plugin-mutate`; a warm
`composer install` does not reapply it. Validate with `--no-cache`, because the
mutation cache does not include patch contents in its key.

The generator guard is `tests/ConstantMutationCoverageTest.php`. It must fail
with the stock plugin and pass with the patch. The behavioral check is a real
mutation run over `DuplicateDisposition` and `DuplicateSetFinder`, including
their four constant mutations. Read the nonzero generated count and require
no uncovered, untested or timed-out mutants, not merely a successful exit.

### Retirement

Retire this patch only when an installable upstream release compatible with
the repository's Pest constraints executes these constant mutants correctly.
No upstream report or pull request has been published for this patch.
The repository-local follow-ups are #34 for the approved upstream report,
#35 for the approved upstream fix, and #36 for patch retirement. They authorize
no publication outside this repository without explicit approval.

1. Remove the registration and patch before updating the package, so an
   overlapping upstream fix cannot break installation.
2. Remove the generator guard. Remove `patches.json`, `patches.lock.json`,
   this README and `cweagans/composer-patches` plus its `allow-plugins` entry
   when no other patch needs them.
3. Install the fixed package and run Composer validation and the ordinary
   parallel suite from a cold vendor tree.
4. Run the same constant mutation checks with cache disabled. Confirm actual
   kills, a nonzero mutant count and no uncovered or timed-out constants.

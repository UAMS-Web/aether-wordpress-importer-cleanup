# Decision log

The canonical record of why the project is the way it is: the reasoning and the rejected alternatives behind decisions that have closed. It guards against silently reopening a settled decision.

**Consult it before acting.** Before proposing a change, reopening a question or picking an approach, check here first. The path under consideration may already be a decided-and-recorded one, or an explicitly rejected one. If it is, follow the record, or make the case to revisit it.

**When a decision closes, record the verdict in both places.** Capture the what, the why and the rejected alternatives here (the cross-ticket index) and in the GitHub issue's `## Decision` section (the per-ticket record), keeping the two consistent. Put the full reasoning in the relevant `docs/` companion when the decision touches a documented area. The issue tracker stays the system of record for a single ticket's status.

## How to add an entry

Entries run oldest first, one per closed decision, so a new entry is appended to the end of the file. Keep it terse; the companion or the issue carries the depth.

Every entry heading is `## YYYY-MM-DD`, a space, and a short title. Leave exactly one blank line above each heading. The check (`decision-log` skill) fails on a heading dated earlier than the one before it, and on a heading that sits directly under the previous line.

```markdown
## YYYY-MM-DD <short decision title> (<#issue or area>)

**Decision.** <what was decided, in a sentence or two>

**Why.** <the reasoning that settled it>

**Rejected.** <the alternatives considered, and why each lost>

**Companion.** <link to the docs/ companion or the GitHub issue holding the full reasoning>
```

---

## 2026-11-05 Repeat the cleanup of each field until it stops changing (UAMS-Web/aether-wordpress-importer-cleanup#31)

**Decision.** `Transform` runs each field's whole cleanup (content, title, excerpt, summary) again over its own output until a pass changes nothing, with a cap of 5 passes. Exceeding the cap throws, naming the cap, instead of returning a half-settled body. A second `wxr-cleanup` run over cleaned output is therefore a no-op, and the README's "idempotent" is true by construction.

**Why.** Measured on the news.uams.edu exports at 71b69d0, a second pass changed 0 of 21,066, 240 of 11,171 and 2 of 4,294 bodies, and a third pass changed none of them, so the pipeline settles in one repeat. The cause is rule order inside one pass: about eight rules (`TrimBlockEdges`, `SplitParagraphsOnDoubleBr`, `NormalizeCityDateline`, `MergeAdjacentLists`, `CaptionizeImageCutline`, `TrimLineEdges`, `UnwrapWholeContentTable`, `RemoveEmptyContainers`) act on structure that an earlier rule only exposes during the pass, and 48 of the 242 items needed two or more of them. No rule is wrong in isolation. A repeat also covers excerpts, where the dateline is normalized in one pass and stripped in the next. Applied to all 253 other exports in the exports repository, the loop never hit the cap and a second run was byte-identical.

**Rejected.** (B) Re-ordering or fixing each of the eight rules so one pass reaches the fixed point: large, risks new first-pass changes in places nobody measured, and 48 items depend on rule combinations. (C) Dropping the claim and listing the non-idempotent rules in the README: no code change, but it leaves the ticket's no-dateline-removal and no-`, Ark.` criteria failing as written and leaves operators with an export that changes when re-run.

**Consequence.** First-pass output for the news export now equals what a second pass used to produce: some bodies lose a leading `OCT. 25, 2001 |` style dateline, bare city datelines gain `, Ark.` and an em dash, adjacent lists merge, and a cutline is captioned. Three bodies (16139, 16142, 16207) keep a `[caption]` shortcode directly inside a table cell at the fixed point; that is the caption representation by design, and a second pass leaves it alone. A rule that keeps undoing another rule's work now fails loudly rather than silently producing a second-pass difference. The pipeline costs roughly one extra pass of run time. Issue #49 (byte-identical serialization) also edits this method and is blocked by #48.

**Companion.** [#31](https://github.com/UAMS-Web/aether-wordpress-importer-cleanup/issues/31).

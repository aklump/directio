<!--
id: syntax
tags: ''
-->

# Directio Syntax

Each Directio _task_ is a Markdown heading whose last token is a `{ … }` attribute block, like so:

```markdown
## Foo Task {#foo}
...
```

A task's body runs from its heading down to the next heading of the same or a higher level, or to the end of the document — there is no closing tag to match. Both [ATX](https://spec.commonmark.org/0.31.2/#atx-headings) (`#` through `######`) and [setext](https://spec.commonmark.org/0.31.2/#setext-headings) (a line of text underlined with `===` or `---`) headings are recognized. A heading found inside a fenced code block (fenced by ```` ``` ```` or `~~~`) is never a task.

A completed task is represented by one slight change. (Look familiar?, i.e., [Markdown task lists](https://www.markdownguide.org/extended-syntax/#task-lists))

```markdown
## Foo Task {x #foo}
```

## Alternate Syntax

Instead of `x` you can use boolean attributes (`done`, `complete`) like this:

```markdown
## Foo Task {done #foo}
```

```markdown
## Foo Task {complete #foo}
```

## Rules

* The `{ … }` block must be the last token on the heading line.
* Every task must carry an `id`, either as `#value` shorthand or as `id=value` / `id="value"`.
* Items inside the block are whitespace-separated. Each one is `#name` (shorthand for `id=name`), `name=value`, `name="quoted value"`, or a bare `name` flag.
* An unquoted value may not contain whitespace, `"`, `{`, or `}`; wrap it in double quotes (`name="quoted value"`) when it needs to. There is no escape for `"` itself, so a value can never contain one.
* Item order is of no significance. If a key appears twice, the later one wins.
* Tasks may not nest — a task's body may not contain another task's heading.

## Special Attributes

| Meaning             | Attributes              | Notes                                          | Example               |
|----------------------|--------------------------|-------------------------------------------------|------------------------|
| task identification  | `id`, `name`             | Must be unique within an initialized project.    | `#foo`, `id=foo`       |
| Redo after a period  | `redo`                   | A `DateInterval` period.                        | `redo=P1M`, `redo=P1D` |
| Completed            | `done`, `complete`, `x`  |                                                   | `done`                 |
| Incompleted          |                          | Tasks are assumed incomplete.                    |                        |

A completed task with no `redo` is hidden for good. A completed task with a `redo` period is hidden until that period has passed since completion.

## Converting Older Documents

Documents written in the retired `<directio id="foo">…</directio>` element markup can be rewritten onto this syntax in place:

```bash
./directio convert /foo/bar/document.md
```

`directio convert` prints a summary and asks to confirm before overwriting the file. It preserves every task's `id` and other attributes, so SQLite state recorded under the old syntax still applies afterward.

Under the new syntax a task's body runs to the next heading at its level or higher, where the old close tag could end it anywhere. Wherever that would change what `update` later removes, the converter refuses and lists the lines rather than guessing: content after a close tag before such a heading, a task with no heading of its own whose body has a level-1 heading, or a task whose heading is deeper than the task before it. Fix those spots in the source and run it again.

Convert the imported copies too — every document under `.directio/imported/` — or `update` and `fixtures` will refuse them until you do.

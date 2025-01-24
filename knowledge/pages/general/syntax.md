<!--
id: syntax
tags: ''
-->

# Directio Syntax

Each Directio _task_ is demarcated using two, specialized HTML comments, like so:

```html
<!-- directio [] id=foo -->

# Foo Task
...

<!-- /directio -->
```

A completed task is represented by one slight change. (Look familiar?, i.e., [Markdown task lists](https://www.markdownguide.org/extended-syntax/#task-lists))

```html
<!-- directio [x] id=foo -->

# Foo Task
...

<!-- /directio -->
```

## Alternate Syntax

Instead of `[x]` you can use boolean attributes (`done, complete`) like this:

```html
<!-- directio done id=foo -->
...
```

```html
<!-- directio complete id=foo -->
...
```

## Rules

* Tasks must be wrapped with both an open `<!-- directio id=foo -->` and close comment `<!-- /directio -->`.
* Comment start "`<!-- `" should be followed by a single space.
* Comment end "` -->`" should be proceeded by a single space.
* Open comments must have an `id` attribute.
* Close comments must not have any attributes.
* Attribute order is of no significance.
* Attribute names must not contain spaces.
    * CORRECT: `<!-- directio fooBar=value -->`
    * incorrect: `<!-- directio foo bar=value -->`
* Attributes values with spaces must be wrapped by double quotes.
    * `<!-- directio id=value foo="another value" -->`
* Attributes may be name only (boolean true), e.g. `complete` as in `<!-- directio id=foo complete -->`.

## Special Attributes

| Meaning                  | Attributes                | Notes                                                   | Example                       |
|--------------------------|---------------------------|---------------------------------------------------------|-------------------------------|
| task identification      | `id`, `name`              | Must be unique within an initialized project.           | `foo`                         |
| Complete only for a time | `expires`                 | datetime, or date period                                | `P1M`, `2025-01-23T15:23:37Z` |
| Completed                | `done`, `complete`, `[x]` |                                                         |                               |
| Incompleted              | `[]`, `[ ]`               | Optional, for clarity, as tasks are assumed incomplete. |                               |

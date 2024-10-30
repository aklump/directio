# Directio Syntax

Directio tasks are demarkated using HTML comments like so:

```html
<!-- directio [] id=foo -->

# Foo Task
...

<!-- /directio -->
```

A completed task (borrowing from [Markdown task lists](https://www.markdownguide.org/extended-syntax/#task-lists)) is represented by one slight change:

```html
<!-- directio [x] id=foo -->

# Foo Task
...

<!-- /directio -->
```

## Alternatives

Instead of `[x]` you can use boolean attributes (`done, complete`) like this:

```html
<!-- directio done id=foo -->
...
```

```html
<!-- directio complete id=foo -->
...
```

## Requirements

* Tasks must be wrapped with both an open `<!-- directio id=foo -->` and close comment `<!-- /directio -->`.
* Open comments must have an `id` attribute.
* Comment tags should be separated by a space:
    * `<!-- directio -->` and not `<!--directio-->`
    * `<!-- /directio -->` and not `<!--/directio-->`
* Attribute order is of no significance.
* Attribute names must not contain spaces.
    * `<!-- directio fooBar=value -->` and not `<!-- directio foo bar=value -->`
* Attributes values with spaces must be wrapped by double quotes.
    * `<!-- directio id=value foo="another value" -->`
* Attributes may be name only (boolean true), e.g. `complete` as in `<!-- directio id=foo complete -->`.

## Special Attributes

| Meaning                  | Attributes          | Notes                                           |
|--------------------------|---------------------|-------------------------------------------------|
| task identification      | id, name            | Must be unique within an initialized project.   |
| Complete only for a time | expires             | datetime, or date period                        |
| Completed                | done, complete, [x] |                                                 |
| Incompleted              | [], []              | Tasks are assumed incomplete; for clarity only. |

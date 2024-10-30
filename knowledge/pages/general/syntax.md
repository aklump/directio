# Directio Syntax

Directio tasks are demarkated using HTML comments like so:

```html
<!-- directio [] id=foo -->

# Foo Task
...

<!-- /directio -->
```

A completed task is represented by one slight change:

```html
<!-- directio [x] id=foo -->

# Foo Task
...

<!-- /directio -->
```

## Requirements

* Tasks must be wrapped with both an open `<!-- directio id=foo -->` and close comment `<!-- /directio -->`.
* Open comments must have an `id` attribute.
* Comment tags should be separated by a space:
    * `<!-- directio -->` and not `<!--directio-->`
    * `<!-- /directio -->` and not `<!--/directio-->`
* Attribute names must not contain spaces.
    * `<!-- directio fooBar=value -->` and not `<!-- directio foo bar=value -->`
* Attributes values with spaces must be wrapped by double quotes.
    * `<!-- directio id=value foo="another value" -->`
* Attributes may be name only (boolean true), e.g. `complete` as in `<!-- directio id=foo complete -->`.

## Special Attributes

| Meaning                  | Attributes               | Notes                                           |
|--------------------------|--------------------------|-------------------------------------------------|
| task identification      | id, name                 | Must be unique within an initialized project.   |
| Complete only for a time | expires                  | datetime, or date period                        |
| Completed                | complete, done, [x], [X] |                                                 |
| Incompleted              | [], []                   | Tasks are assumed incomplete; for clarity only. |

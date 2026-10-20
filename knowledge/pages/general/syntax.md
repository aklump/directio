<!--
id: syntax
tags: ''
-->

# Directio Syntax

Each Directio _task_ is demarcated by an HTML element, `<directio>…</directio>`, like so:

```html
<directio id="foo">

# Foo Task
...

</directio>
```

A completed task is represented by one slight change. (Look familiar?, i.e., [Markdown task lists](https://www.markdownguide.org/extended-syntax/#task-lists))

```html
<directio x id="foo">

# Foo Task
...

</directio>
```

## Alternate Syntax

Instead of `x` you can use boolean attributes (`done, complete`) like this:

```html
<directio done id="foo">
...
```

```html
<directio complete id="foo">
...
```

## Rules

* Tasks must be wrapped with both an open `<directio id="foo">` and close tag `</directio>`.
* Open tags must have an `id` attribute.
* Attribute order is of no significance.
* Attributes must be wrapped by double quotes.
    * `<directio id="value" foo="another value">`
* Boolean attributes should be name only, e.g. `complete` as in `<directio id="foo" complete>`.

## Special Attributes

| Meaning                  | Attributes              | Notes                                         | Example                       |
|--------------------------|-------------------------|-----------------------------------------------|-------------------------------|
| task identification      | `id`, `name`            | Must be unique within an initialized project. | `foo`                         |
| Redo after a period      | `redo`                  | A `DateInterval` period.                      | `P1M`, `P1D`                  |
| Completed                | `done`, `complete`, `x` |                                               |                               |
| Incompleted              |             | Tasks are assumed incomplete.                 |                               |

A completed task with no `redo` is hidden for good. A completed task with a `redo` period is hidden until that period has passed since completion.

---
id: completion
---

# Completing Tasks

Give the following task markup:

```html
<!-- directio id=foo -->
This content will expire on January 1, 2024.
<!-- /directio -->
```

To mark this complete you do two things:

1. Add the completion markup to the open tag.
2. Run the `update` command.

## Completion Markup

Any of the following can be used to indicate completion of a task.

```html
<!-- directio id=foo complete -->
<!-- directio id=foo done -->
<!-- directio [x] id=foo -->
<!-- directio [X] id=foo -->
```

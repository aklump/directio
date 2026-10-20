<!--
id: completion
tags: ''
-->

# Completing Tasks

Given the following task markup:

```markdown
## Making Dessert {#dessert}

- Measure the eggs and sugar into a bowl
- Whip for 5 minutes until incorporated
- Continue mixing the cookies...
```

To mark this complete you do two things:

1. Add the completion flag to the heading's `{ … }` block.
2. Run the `update` command.

## Completion Markup

Any of the following can be used to indicate completion of a task.

```markdown
## Making Dessert {#dessert complete}
## Making Dessert {#dessert done}
## Making Dessert {x #dessert}
```

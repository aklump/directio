<!--
id: completion
tags: ''
-->

# Completing Tasks

Give the following task markup:

```html
<!-- directio id=dessert -->
## Making Dessert

- Measure the eggs and sugar into a bowl
- Whip for 5 minutes until incorporated
- Continue mixing the cookies...
<!-- /directio -->
```

To mark this complete you do two things:

1. Add the completion markup to the open tag.
2. Run the `update` command.

## Completion Markup

Any of the following can be used to indicate completion of a task.

```html
<!-- directio id=dessert complete -->
<!-- directio id=dessert done -->
<!-- directio [x] id=dessert -->
```

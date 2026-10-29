<!--
id: only
tags: ''
-->

# Keeping Only Some Tasks

Give tasks any attribute you like to group them, e.g. `security`:

```markdown
## Pre config changes {#pre_config_changes redo=P1D security}
## Bump dependencies {#bump}
## Audit permissions {#audit security=high}
```

Then, in an imported project, keep only that group:

```bash
./vendor/bin/directio only security
```

Every task in `.directio/imported/` that lacks the attribute is removed; `bump` above goes, the other two stay. `with` is an alias: `directio with security`.

* `name` matches a task that has the attribute with any value, or as a bare flag.
* `name=value` matches only that value, e.g. `directio only security=high`.
* Several attributes keep a task that has any of them: `directio only security perf`.

Nothing is recorded in the database, so removed tasks are not marked done and come back the next time you import the source document. The command lists what it will remove and asks before writing; `-n` skips the question.

It changes nothing when no task matches, so a typo can't empty your documents. It warns before removing a task that is marked done but not yet saved by `update`, since that completion would be lost.

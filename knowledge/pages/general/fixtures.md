<!--
id: fixtures
tags: ''
-->

# Fixtures

Fixtures allow you to run automated setup or teardown logic as part of your task workflow.

## Where to Save Fixture Classes

Fixture classes should be stored in the `.directio/src/Fixture` directory of your project. They should follow the PSR-4 namespacing convention and be under the `AKlump\Directio\Fixture` namespace.

{{ snippet.MyFixture_php|fenced }}
{{ snippet.AbstractFixture_php|fenced }}

### Registering the Namespace

To make your fixtures discoverable, you must add the `AKlump\Directio` namespace to your project's `composer.json` file under the `autoload` or `autoload-dev` section.

```json
{
  "autoload": {
    "psr-4": {
      "AKlump\\Directio\\": ".directio/src/"
    }
  }
}
```

After updating `composer.json`, be sure to run `composer dump-autoload`.

### Available Runtime Options

You can see usage in the example above.

{{ snippet.fixtures_runtime_options|outdent|fenced }}

## How to Use Fixtures in Directio Documents

You can reference a fixture in your document using the `fixture` attribute in a `<directio>` tag.

```html
<!-- <directio fixture="my_fixture_id" /> -->
```

When you run the `fixtures` command, Directio will scan the imported documents for these tags and execute the corresponding fixtures in the order they appear.

### Skipping Completed Fixtures

If a `<directio>` tag has the `done` attribute, the fixture will be skipped. This happens automatically once a fixture has successfully run. If you need to re-run a fixture, you must remove the `done` attribute from the tag in the document.

## Running Fixtures

The current working directory of all fixtures will be the project directory, which is to say the parent of `.directio`.

Execute the following command to run all fixtures referenced in your documents. If the fixture succeeds the <directio/> tag will receive `done`.

```bash
./directio fixtures
```

### Options

- `--filter={id}`: Filter fixtures by ID. You can use a string or a regular expression.
- `--flush`: Rebuild the fixture cache before running.

## On Success

You will be asked if you want to mark the fixture as done in the Directio document.  The default is `Yes`.

To make a certain fixture default to `No` add this method like this:

```php
  /**
   * {@inheritdoc}
   */
  public function onSuccess(bool $silent = FALSE, bool $mark_as_done_default = TRUE) {
    parent::onSuccess($silent, FALSE);
  }
```

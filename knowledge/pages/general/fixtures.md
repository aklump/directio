# Fixtures

Fixtures allow you to run automated setup or teardown logic as part of your task workflow.

## Where to Save Fixture Classes

Fixture classes should be stored in the `.directio/src/Fixture` directory of your project. They should follow the PSR-4 namespacing convention and be under the `AKlump\Directio\Fixture` namespace.

Example: `.directio/src/Fixture/MyFixture.php`

```php
<?php

namespace AKlump\Directio\Fixture;

use AKlump\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Fixture;

#[Fixture(id: 'my_fixture_id')]
class MyFixture extends AbstractFixture {

  public function __invoke(): void {
    // Your fixture logic here
  }
}
```

## How to Use Fixtures in Documents

You can reference a fixture in your document using the `fixture` attribute in a `<directio>` tag.

```html
<!-- <directio fixture="my_fixture_id" /> -->
```

When you run the `fixtures` command, Directio will scan the imported documents for these tags and execute the corresponding fixtures in the order they appear.

## Registering the Namespace

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

## Running Fixtures

Execute the following command to run all fixtures referenced in your documents:

```bash
./directio fixtures
```

### Options

- `--filter={id}`: Filter fixtures by ID. You can use a string or a regular expression.
- `--flush`: Rebuild the fixture cache before running.

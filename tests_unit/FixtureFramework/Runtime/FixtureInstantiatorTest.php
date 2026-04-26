<?php

namespace AKlump\Directio\Tests\Unit\FixtureFramework\Runtime;

use AKlump\Directio\FixtureFramework\Runtime\FixtureInstantiator;
use AKlump\FixtureFramework\FixtureInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \AKlump\Directio\FixtureFramework\Runtime\FixtureInstantiator
 *
 * @uses \AKlump\FixtureFramework\Runtime\FixtureInstantiator
 * @uses \AKlump\FixtureFramework\Runtime\RunOptions
 */
class FixtureInstantiatorTest extends TestCase {

  public function testCreateFixture() {
    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);
    $instantiator = new FixtureInstantiator(new \AKlump\FixtureFramework\Runtime\RunOptions([]), $input, $output);

    // We need a dummy fixture class that can be instantiated with ($input, $output)
    $fixtureClass = get_class(new class($input, $output) implements FixtureInterface {
      public function __construct(InputInterface $input, OutputInterface $output) {
      }
      public function __invoke(): void {
      }
      public function setFixtureDefinition(array $definition): void {
      }
      public function fixture(): array {
          return [];
      }
      public function id(): string {
          return '';
      }
      public function description(): string {
          return '';
      }
      public function setRunOptions(\AKlump\FixtureFramework\Runtime\RunOptions $options): void {
      }
      public function options(): \AKlump\FixtureFramework\Runtime\RunOptions {
          return new \AKlump\FixtureFramework\Runtime\RunOptions([]);
      }
      public function setRunContext(\AKlump\FixtureFramework\Runtime\RunContext $context): void {
      }
      public function runContext(): \AKlump\FixtureFramework\Runtime\RunContext {
          return new \AKlump\FixtureFramework\Runtime\RunContext();
      }
      public function onSuccess(bool $silent = FALSE) {
      }
      public function onFailure(\AKlump\FixtureFramework\Exception\FixtureException $e, bool $silent = FALSE) {
      }
    });

    // We need to use Reflection to call the protected createFixture method or use iterate/create
    // But parent::__invoke() calls createFixture.
    $store = $this->createMock(\AKlump\FixtureFramework\Runtime\RunContextStore::class);
    $fixture = $instantiator(['class' => $fixtureClass, 'id' => 'foo'], $store);
    $this->assertInstanceOf($fixtureClass, $fixture);
  }
}

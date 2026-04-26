<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\Config\Names;
use AKlump\Directio\Helper\ApplyStateToDocument;
use AKlump\Directio\IO\GetLogsDirectory;
use AKlump\Directio\IO\GetResultFilename;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\ReadState;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\DocumentInterface;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use AKlump\Directio\HTMLElementStyle;
use DateTimeInterface;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use AKlump\LocalTimezone\LocalTimezone;

class ImportCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'import';

  protected static $defaultDescription = 'Import document, applying project task state';

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  private InputInterface $input;

  private string $directioDirectory;

  private DateTimeInterface $now;

  private string $openTagPattern;

  protected function configure() {
    $this->addArgument('source document', InputArgument::REQUIRED, 'The source document to be filtered.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->now = date_create('now', LocalTimezone::get());
    $this->output = $output;
    $this->input = $input;
    $this->openTagPattern = (new HTMLElementStyle())->getOpenTagPattern();

    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $this->directioDirectory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;

    $logs_dir = (new GetLogsDirectory($this->directioDirectory))();
    if (is_dir($logs_dir) && count(array_diff(scandir($logs_dir), ['.', '..'])) > 0) {
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion('Delete existing logs? ', FALSE);
      if ($helper->ask($input, $output, $question)) {
        $this->deleteDirectory($logs_dir, TRUE);
        $this->output->writeln('<info>Logs deleted.</info>');
      }
    }

    $source = $input->getArgument('source document');
    if (is_dir($source)) {
      return $this->importFromDirectory($source);
    }

    if (basename($source) === AbstractFixture::YAML_OPTIONS_FILENAME) {
      return $this->importOptionsFile($source) ? Command::SUCCESS : Command::FAILURE;
    }

    if ($this->isFixtureFile($source)) {
      $this->importOptionsFile(dirname($source) . DIRECTORY_SEPARATOR . AbstractFixture::YAML_OPTIONS_FILENAME);

      return $this->importFixture($source) ? Command::SUCCESS : Command::FAILURE;
    }

    if ($this->hasDirectioMarkup($source)) {
      return $this->importDocumentWithMarkup($source);
    }

    $document_path = $source;
    try {
      $document = (new ReadDocument())($document_path);
      (new ValidateTaskSyntax())($document->getContent());
      $state = (new ReadState())($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);

      // We only validate ID collisions for documents WITHOUT markup (legacy import).
      // If it has markup, it's handled by importDocumentWithMarkup, which also does this.
      // But wait, if it's a single file import of a plain doc, we want to check.
      $this->tryValidateIncomingIdsDoNotAlreadyExists($document, $document_path);
      $document = (new ApplyStateToDocument($this->now))($state, $document);
    }
    catch (Exception $exception) {
      $output->writeln(sprintf("<error>%s</error>", $exception->getMessage()));
      $output->writeln('');

      return Command::FAILURE;
    }
    if (!$this->createNewDocument($document_path, $document)) {
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  private function isFixtureFile(string $path): bool {
    if (!is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
      return FALSE;
    }
    $content = file_get_contents($path);

    // We use a regex to avoid loading the class, which might fail if dependencies are missing.
    $has_attribute = preg_match('/#\[\\\\?AKlump\\\\FixtureFramework\\\\Fixture\s*\(|#\[Fixture\s*\(/', $content) === 1;
    if (!$has_attribute) {
      return FALSE;
    }

    return preg_match('/namespace\s+AKlump\\\\Directio\\\\Fixture\b/', $content) === 1;
  }

  private function hasDirectioMarkup(string $path): bool {
    if (!is_file($path)) {
      return FALSE;
    }
    $content = file_get_contents($path);

    return preg_match('/' . str_replace('/', '\/', $this->openTagPattern) . '/', $content) === 1;
  }

  private function importFromDirectory(string $directory): int {
    $found_count = 0;
    $imported_count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
      if ($file->isDir()) {
        continue;
      }
      $path = $file->getPathname();
      if ($this->isFixtureFile($path)) {
        $found_count++;
        if ($this->importFixture($path)) {
          $imported_count++;
        }
      }
      elseif (basename($path) === AbstractFixture::YAML_OPTIONS_FILENAME) {
        $found_count++;
        if ($this->importOptionsFile($path)) {
          $imported_count++;
        }
      }
      elseif ($this->hasDirectioMarkup($path)) {
        $found_count++;
        if ($this->importDocumentWithMarkup($path) === Command::SUCCESS) {
          $imported_count++;
        }
      }
    }

    $shortpath_directory = (new GetShortPath())($directory);
    if ($found_count === 0) {
      $this->output->writeln(sprintf('<comment>No fixtures or documents with directio markup found in %s</comment>', $shortpath_directory));
    }
    elseif ($imported_count === 0) {
      $this->output->writeln(sprintf('<comment>All of the %d items found in %s were skipped or failed.</comment>', $found_count, $shortpath_directory));
    }
    else {
      $this->output->writeln(sprintf('<info>Imported %d of %d items found in %s.</info>', $imported_count, $found_count, $shortpath_directory));
    }

    return Command::SUCCESS;
  }

  private function importDocumentWithMarkup(string $path): int {
    try {
      $document = (new ReadDocument())($path);
      (new ValidateTaskSyntax())($document->getContent());
      $state = (new ReadState())($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);

      $this->tryValidateIncomingIdsDoNotAlreadyExists($document, $path);
      $document = (new ApplyStateToDocument($this->now))($state, $document);
    }
    catch (Exception $exception) {
      $this->output->writeln(sprintf("<error>%s</error>", $exception->getMessage()));
      $this->output->writeln('');

      return Command::FAILURE;
    }
    if (!$this->createNewDocument($path, $document)) {
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  private function importFixture(string $path): bool {
    $target_dir = $this->directioDirectory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Fixture';
    if (!file_exists($target_dir)) {
      mkdir($target_dir, 0755, TRUE);
    }
    $target_path = $target_dir . DIRECTORY_SEPARATOR . basename($path);

    $shortpath = (new GetShortPath())($target_path);
    if (file_exists($target_path)) {
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion(sprintf('Fixture "%s" already exists. Overwrite? ', basename($target_path)), FALSE);
      if (!$helper->ask($this->input, $this->output, $question)) {
        $message = sprintf("<error>Failed to import \"%s\" because it already exists.</error>", basename($target_path));
        $this->output->writeln(sprintf($message));
        $this->output->writeln('');

        return FALSE;
      }
    }

    if (!copy($path, $target_path)) {
      $this->output->writeln(sprintf('<error>Failed to copy fixture to %s</error>', $shortpath));

      return FALSE;
    }

    $this->output->writeln(sprintf('<info>Fixture imported to %s</info>', $shortpath));

    return TRUE;
  }

  private function createNewDocument($document_path, DocumentInterface $document): bool {
    $imported_doc_path = $this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . (new GetResultFilename($this->now))($document_path);
    $parent = dirname($imported_doc_path);
    if (!file_exists($parent)) {
      mkdir($parent, 0755, TRUE);
    }

    $shortpath = (new GetShortPath())($imported_doc_path);
    if (file_exists($imported_doc_path)) {
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion(sprintf('Document "%s" already exists. Overwrite? ', basename($imported_doc_path)), FALSE);
      if (!$helper->ask($this->input, $this->output, $question)) {
        return FALSE;
      }
    }
    (new WriteDocument())($imported_doc_path, $document);
    $this->output->writeln(sprintf('<info>File imported to %s</info>', $shortpath));

    return TRUE;
  }

  private function tryValidateIncomingIdsDoNotAlreadyExists(DocumentInterface $importing_document, string $document_path): void {
    $new_ids = $importing_document->getIds();
    $read_document = new ReadDocument();
    $imported_files = glob($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    $invalid = FALSE;
    foreach ($imported_files as $imported_path) {
      $existing_document = $read_document($imported_path);
      $existing_ids = $existing_document->getIds();
      $duplicated_ids = array_intersect($new_ids, $existing_ids);
      if ($duplicated_ids) {
        if (!$invalid) {
          $this->output->writeln('The following tasks have already been imported:');
        }
        $invalid = TRUE;
        $shortpath_imported = (new GetShortPath())($imported_path);
        $this->output->writeln(sprintf('<info>├── %s</info>', $shortpath_imported));
        $this->output->write(array_map(function ($line) {
          return "<comment>│   ├── $line</comment>";
        }, $duplicated_ids), TRUE);
      }
    }

    if ($invalid) {
      $shortpath_document_path = (new GetShortPath())($document_path);
      $this->output->writeln(sprintf('<info>Try deleting or moving "%s" if it is no longer in use.</info>', $shortpath_document_path));
      $message = sprintf('Failed to import "%s" due to ID collision.', $shortpath_document_path);
      throw new RuntimeException($message);
    }
  }

  private function deleteDirectory(string $dir, bool $keep_root = FALSE): void {
    if (!is_dir($dir)) {
      return;
    }
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
      $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
      $todo($fileinfo->getRealPath());
    }

    if (!$keep_root) {
      rmdir($dir);
    }
  }

  private function importOptionsFile(string $path): bool {
    if (!is_file($path)) {
      return FALSE;
    }
    $target_path = $this->directioDirectory . DIRECTORY_SEPARATOR . basename($path);
    if (file_exists($target_path)) {
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion(sprintf('Options file "%s" already exists. Overwrite? ', basename($target_path)), FALSE);
      if (!$helper->ask($this->input, $this->output, $question)) {
        return FALSE;
      }
    }

    if (!copy($path, $target_path)) {
      $shortpath = (new GetShortPath())($target_path);
      $this->output->writeln(sprintf('<error>Failed to copy options file to %s</error>', $shortpath));

      return FALSE;
    }

    $shortpath = (new GetShortPath())($target_path);
    $this->output->writeln(sprintf('<info>Options imported to %s</info>', $shortpath));

    return TRUE;
  }

}

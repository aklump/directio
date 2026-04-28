<?php

namespace AKlump\Directio\Fixture;

use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Exception\FixtureException;

/**
 * Base class for synchronizing a local file with a remote or local source.
 *
 * This fixture searches for instances of a target filename within specified
 * directories and updates them with the content from a source URL or file path.
 * A backup is automatically created for each file before it is updated.
 *
 * @example
 * ```php
 * #[Fixture(id: 'upgrade_npm_deps_script')]
 * class UpgradeNpmDepsScript extends AbstractFileSync {
 *
 *   protected function getSource(): string {
 *     return 'https://gist.githubusercontent.com/aklump/9f8ca7a03490b62eb1f59802318d1ca2/raw';
 *   }
 *
 *   protected function getTargetFilename(): string {
 *     return 'upgrade_npm_deps.sh';
 *   }
 * }
 * ```
 */
abstract class AbstractFileSync extends AbstractFixture {

  /**
   * Returns the source URL or local file path to fetch content from.
   *
   * @return string
   */
  abstract protected function getSource(): string;

  /**
   * Returns the filename to search for and update in the project.
   *
   * @return string
   */
  abstract protected function getTargetFilename(): string;

  /**
   * Returns an array of directories to search for the target filename.
   *
   * @return array
   *   An array of directory paths. Defaults to [getcwd()].
   */
  protected function getSearchDirectories(): array {
    return [getcwd()];
  }

  /**
   * Returns an array of directory names to ignore during the search.
   *
   * @return array
   *   An array of directory names. Defaults to ['vendor', 'node_modules'].
   */
  protected function getIgnoredDirectories(): array {
    return ['vendor', 'node_modules'];
  }


  /**
   * Returns the extension to use for the backup file.
   *
   * @return string
   *   The backup extension, including the leading dot. Defaults to '.' . time().
   */
  protected function getBackupExtension(): string {
    return '.' . time();
  }

  /**
   * Determines if an update is required by comparing current and new content.
   *
   * @param string $current_content
   *   The current content of the local file.
   * @param string $new_content
   *   The content fetched from the source.
   *
   * @return bool
   *   TRUE if an update is required, FALSE otherwise.
   */
  protected function isUpdateRequired(string $current_content, string $new_content): bool {
    return $current_content !== $new_content;
  }

  /**
   * Executes the synchronization process.
   *
   * @throws \AKlump\FixtureFramework\Exception\FixtureException
   *   If the source content cannot be fetched.
   */
  public function __invoke(): void {
    $source = $this->getSource();
    $target = $this->getTargetFilename();

    $new_content = @file_get_contents($source);
    if ($new_content === FALSE) {
      throw new FixtureException("Unable to fetch the latest content from: $source");
    }

    $files = [];
    $ignored_directories = $this->getIgnoredDirectories();
    foreach ($this->getSearchDirectories() as $search_dir) {
      if (!is_dir($search_dir)) {
        continue;
      }
      $directory = new \RecursiveDirectoryIterator($search_dir, \FilesystemIterator::SKIP_DOTS);
      $filter = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($ignored_directories) {
        if ($iterator->hasChildren()) {
          return !in_array($current->getFilename(), $ignored_directories);
        }

        return TRUE;
      });
      $iterator = new \RecursiveIteratorIterator($filter);
      foreach ($iterator as $info) {
        if ($info->getFilename() === $target) {
          $files[] = $info->getPathname();
        }
      }
    }
    $files = array_unique($files);

    if (empty($files)) {
      $this->io()->writeln("<error>No instances of $target were found.</error>");

      return;
    }

    $updated_count = 0;
    foreach ($files as $file) {
      $current_content = file_get_contents($file);
      $short_file = $this->shortPath($file);
      if ($this->isUpdateRequired($current_content, $new_content)) {
        $backup_file = $file . $this->getBackupExtension();
        if (!copy($file, $backup_file)) {
          $this->io()->writeln("<error>Failed to create backup for $short_file. Skipping update.</error>");
          continue;
        }
        $short_backup_file = $this->shortPath($backup_file);
        $this->io()->warning("Updated: $short_file (Backup: " . basename($short_backup_file) . ")");

        file_put_contents($file, $new_content);
        $updated_count++;
      }
      else {
        $this->io()->writeln("<info>Up to date: $short_file</info>");
      }
    }

    if ($updated_count === 0) {
      $this->io()->writeln("<info>All files are already up to date.</info>");
    }
  }
}

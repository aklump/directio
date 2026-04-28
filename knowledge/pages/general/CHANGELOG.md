<!--
id: changelog
tags: ''
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `ImportCommand` now prompts the user to flush the fixture cache directory (`.cache`) if it's not empty, similar to the existing logs directory handling.
- Created `AKlump\Directio\IO\GetCacheDirectory` to provide a centralized and consistent way to retrieve and initialize the `.cache` directory path.
- Added an optional argument to `FixturesCommand` (alias `do`) that acts as a fixture ID filter, allowing `directio do check_pages_runner` in addition to `directio do --filter=check_pages_runner`.
- Created `AKlump\Directio\Exception\AuthenticationRequiredException` for fine-grained authentication failure handling.
- Integrated `Symfony\Component\Stopwatch\Stopwatch` into `MHTMLTrait::downloadAsMhtml` to print the total generation time for the MHTML archive.
- Added an optional cache directory parameter to `MHTMLTrait::downloadAsMhtml` to speed up repeat calls by caching discovered assets.
- Created `MHTMLTrait` for generating MHTML archives from web pages and their assets.
- Added `DrupalReports` fixture to facilitate report archival.

### Fixed
- Fixed a bug in `MHTMLTrait` where incorrect `Content-Type` headers were being captured from redirects, causing assets (like SVGs) to be incorrectly labeled as `text/html`.
- Improved image display in MHTML archives by ensuring correct `Content-Type` and charset for all assets.
- Reduced MHTML filesize by using `quoted-printable` encoding for text-based assets (CSS, JS, SVG) instead of `base64`.
- Added `Snapshot-Content-Location` header to MHTML archives for better browser compatibility.

### Changed
- `WriteDocument::__invoke` now trims whitespace from the document content before writing it to the file.
- `AbstractFixture::shouldRun()` now automatically returns `true` (skipping the confirmation prompt) if a `filter` option is used in the command input.
- `MHTMLTrait::downloadAsMhtml` now validates the main response and throws a `FixtureException` if it detects a 403 Access Denied or an unauthorized redirect to a login page (common when a session cookie is missing or expired).
- Refined `MHTMLTrait::downloadAsMhtml` to output a single consolidated message: "Downloaded {url} to {path} in {time}ms".
- Removed redundant "Downloaded" message from `DrupalReports` fixture.
- Refined `MHTMLTrait::downloadAsMhtml` to make `Stopwatch` usage conditional on the class existing and simplified output to show only total generation time.
- Added a Symfony progress bar to `MHTMLTrait::downloadAsMhtml` to track asset download progress.
- `MHTMLTrait` now ensures all relative URLs in the HTML content are made absolute, including those in attributes (href, src, etc.) and CSS `url()` references.
- Refactored `ImportCommand`, `FixturesCommand`, `UpdateCommand`, and `InitializeCommand` to use `SymfonyStyle` for consistent I/O.
- Replaced manual `ConfirmationQuestion` usage with `$this->io()->confirm()` across all commands.
- `ImportCommand` now skips the overwrite prompt when importing files (fixtures, documents, options) if the source and target contents are identical.
- Updated `ImportCommand` output to indicate when a file is skipped due to identical contents.
- Updated `ImportCommand`, `FixturesCommand`, `UpdateCommand`, and `InitializeCommand` with the `AsCommand` attribute to resolve Symfony 6.1 deprecations.
- Refined the final summary message of `ImportCommand` to accurately reflect the number of newly imported versus identical skipped items.

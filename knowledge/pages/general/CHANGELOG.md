<!--
id: changelog
tags: ''
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Refactored `ImportCommand`, `FixturesCommand`, `UpdateCommand`, and `InitializeCommand` to use `SymfonyStyle` for consistent I/O.
- Replaced manual `ConfirmationQuestion` usage with `$this->io()->confirm()` across all commands.
- `ImportCommand` now skips the overwrite prompt when importing files (fixtures, documents, options) if the source and target contents are identical.
- Updated `ImportCommand` output to indicate when a file is skipped due to identical contents.
- Refined the final summary message of `ImportCommand` to accurately reflect the number of newly imported versus identical skipped items.

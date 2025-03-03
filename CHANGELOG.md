# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [3.0.0] - 2025-03-03

### Changed

- Minimum PHP is now 8.2.
- Minimum Laravel is now 11.

## [2.0.0] - 2023-06-20

### Changed

- Upgraded to Laravel 10.
- Minimum PHP version is now 8.1.
- Use `assert()` to ensure the soft delete boolean driver receives the correct type of model.

## [1.1.0] - 2022-09-14

### Added

- Package now supports PHP 8.1 and Laravel 9.

## [1.0.1] - 2021-07-31

### Fixed

- [#2](https://github.com/laravel-json-api/boolean-softdeletes/pull/2) Use `class_uses_recursive()` to detect trait on
  model class.

## [1.0.0] - 2021-07-31

Initial release.

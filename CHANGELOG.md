# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [2.0.0] - 2023-06-28

# !! BREAKING CHANGES !!

- All classes related to EntityManager logic (except the annotations) have been moved to `Awwar\PhpHttpEntityManager`
  namespace. Sorry for that. Current package will wrap around `awwar/php-http-entity-manager` package.
- Rename `RelationMap` Annotation to `RelationField`.
- Rename `FieldMap` Annotation to `DataField`.

### Refactor

- Extends this package from awwar/php-http-entity-manager

## [1.0.4] - 2023-10-28

### Added

* Add Symfony `^6.0` support

## [1.0.3] - 2023-02-01

### Fixed

- Required `callback` parameter in DefaultValue is now optional

## [1.0.2] - 2022-09-11

### Refactor

- Refactoring and cleanup

## [1.0.1] - 2022-09-08

### Fixed

- Bug with deletion
- Bug when Http EntityManager deletes entity on deletion

### Added

- Add DefaultValue annotation

#### Changed

- Extends DataField setting
- HttpEntityManager: find method can use criteria for requests

### Doc
``
- Init documentation

## [1.0.0] - 2022-09-06

### Added

- Initial release

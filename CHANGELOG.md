# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Changed
- Rename the library to QCrud and the Composer package to `eril/qcrud`.
- Move PHP namespaces from `Eril\CRUD` to `Eril\QCrud`; update imports accordingly.
- Keep the main `CRUD` class, global `CRUD` alias, and `CRUD::` API unchanged.


## [1.1.0] - 2025-12-03

### Added
- docs: add project documentation and contribution guides
- feat(cli): add log management tool and CI workflow
- ci(testing): add test suite and github actions workflow
- refactor(query): extract where aliases and add global helpers
- feat(exceptions): add custom crud exception
- feat(query): implement fluent query builder and refactor api
- feat(core): add query builder and integrated logging

### Changed
- refactor(CRUD): simplify transaction return logic

## [1.0.0] - 2024-01-15

### Added
- Initial release with complete CRUD operations
- Fluent Query Builder with subquery support
- PDO connection management
- Transaction support with rollback
- Automatic error logging system
- CLI tool for log management
- Helper functions
- Full PHPUnit test suite

### Features
- Create, Read, Update, Delete operations
- Complex query building with joins, groups, having
- Parameter binding with type support
- Connection pooling with static PDO instance
- 7-day log retention with automatic cleanup
- Configurable logging system

## [0.1.0] - 2025-12-02

### Added
- feat(core): add initial CRUD library implementation


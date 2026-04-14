# CHANGELOG

All notable changes to BeirutTime OSINT Pro will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- PSR-4 autoloading for better code organization
- CI/CD pipeline with GitHub Actions
- PHPUnit test coverage reporting
- PHPStan static analysis configuration
- Mutation testing with Infection
- Object caching support (Redis, Memcached, WordPress Transients)
- Modular architecture with base modules and interfaces

### Changed
- Updated composer.json with improved autoload configuration
- Enhanced cache handler with auto-detection of backends
- Improved directory structure separation (src/, includes/)
- Added comprehensive development scripts

### Fixed
- Early warning engine name collision
- AutoTrain batch size requests capped safely

### Deprecated
- Monolithic plugin structure (migrating to modular)

### Removed
- Direct file includes (replaced with PSR-4 autoloading)

### Security
- Enhanced AJAX request validation
- Improved input sanitization across services

---

## [17.4.2] - 2024-01-XX

### Added
- Advanced early warning engine
- AutoTrain integration for automated model training
- AutoEval for automatic evaluation
- Hybrid warfare analysis module
- WebSocket support for real-time updates
- Entity relations manager
- NewsLog service for content tracking
- Batch reindexer service
- Verification service for content validation

### Changed
- Updated plugin version to 17.4.2
- Restructured includes directory
- Improved classifier service with context memory

### Fixed
- Various bug fixes in classifier patterns
- Performance optimizations in database queries

---

## [17.4.1] - 2023-12-XX

### Added
- Initial modular architecture foundation
- Cache handler with multiple backend support
- Base module interface and implementation
- Dashboard, Map, Chart, Analysis, and Export modules

### Changed
- Migrated from monolithic to modular structure
- Separated concerns into dedicated service classes

---

## [17.4.0] - 2023-11-XX

### Added
- Initial release of BeirutTime OSINT Pro
- Strategic OSINT dashboard for WordPress
- Basic classification and analysis features

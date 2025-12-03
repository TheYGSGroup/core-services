# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2025-01-XX

### Added
- Initial release of YGS Core Services
- WordPress-style Hook Management System (`HookManager`)
  - Action hooks (`doAction`, `addAction`)
  - Filter hooks (`applyFilter`, `addFilter`)
  - Priority-based execution
  - Support for named and positional arguments
  - Comprehensive error handling
- Panel Extension Hooks helper for Lunar Panel extensions
- Laravel Facade support
- Configuration system
- Service Provider with auto-discovery
- Comprehensive README documentation

### Migration
- Migrated `CheckoutEventService` from order-platform to use `HookManager` as a compatibility wrapper
- Migrated `PanelExtensionHooks` from order-platform to use `HookManager` internally

### Technical Details
- Built for Laravel 11+
- PHP 8.2+ required
- MIT License


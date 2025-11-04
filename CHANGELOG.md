# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.0] - 2025-11-04

### Added
- PHP 8.3 compatibility checks using PHPCS and PHPCompatibility tools
- Development tools configuration (PHPCS, PHPCBF, PHPCompatibility)
- Composer scripts for quality tools usage:
  - `composer phpcs` : code verification according to PSR-12 and PHP 8.3 compatibility
  - `composer phpcbf` : automatic code formatting
  - `composer phpcompat` : specific PHP 8.3 compatibility verification

### Changed
- Updated minimum PHP version requirement to PHP 8.3+
- Modified date filtering to use XML record datestamps instead of file modification time
- Improved date parsing for `until` parameter to include full day (23:59:59) when date-only format is used

### Fixed
- Fixed PHP 8.2+ deprecation warnings for dynamic properties in `Response` and `Server` classes
- Fixed PHP 8.1+ deprecation warning when passing `null` to `DOMDocument::createElement()`
- Fixed fatal error in `checkDateFormat()` method when `getLastErrors()` returns `false`
- Fixed date filtering functionality to use datestamps from XML records instead of file system timestamps
- Fixed `formatTimestamp()` method to properly handle `until` parameter with date-only format (end of day)
- Replaced deprecated `gmstrftime()` function with `gmdate()` for PHP 8.1+ compatibility
- Replaced deprecated `strptime()` function with `DateTime` API for better compatibility
- Fixed PHPDoc annotation syntax in `Server` class

### Technical Details
- Added explicit property declarations in `Response` class (`$verb`, `$verbNode`)
- Added explicit property declarations in `Server` class (`$identifyResponse`, `$listMetadataFormatsCallback`, `$listRecordsCallback`, `$listSetsCallback`, `$getRecordCallback`, `$response`)
- Created `extractDatestampFromXml()` method in `Data` class to extract datestamps from XML records
- Improved error handling in `checkDateFormat()` to handle `getLastErrors()` return values correctly
- Updated `formatTimestamp()` to support end-of-day calculation for `until` parameter

## [1.4.0] - Previous Version

### Previous Features
- OAI-PMH 2.0 protocol implementation
- Support for multiple metadata formats
- Set hierarchy support
- Record deletion handling
- Resumption tokens for large result sets


# Changelog

All notable changes to the Author Profile Updater plugin will be documented in this file.

## 1.1.1 - 2025-02-27

### Added
- Added force update option to bulk update feature to allow updating authors already mapped to users

### Fixed
- Fixed a fatal error when using the force update feature due to missing parameter in get_author_email method call
- Enhanced email source tracking in debug information

## 1.1.0 - 2023-07-15

### Added
- Force update option to allow updating authors already mapped to different users
- Enhanced debug information with more detailed matching process data
- Improved author-user mapping logic with better error handling
- Added checkbox in UI for force update option

### Changed
- Refactored code for better maintainability and performance
- Improved UI for specific user update form
- Enhanced error messages and success notifications

## 1.0.0 - 2023-06-30

### Added
- Initial release
- Bulk update functionality for all authors
- Specific user update functionality
- Basic debug information
- Admin UI for managing author updates

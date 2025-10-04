# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2025-10-04

### Fixed
- Dark mode styling for admin interface - improved contrast and readability
- Status boxes now properly adapt to both light and dark themes

## [0.2.0] - 2025-10-04

### Fixed
- Case sensitivity bug causing duplicate user accounts for different username
  capitalizations (#2). New users are now created with lowercase usernames while
  maintaining backwards compatibility for existing users.

### Added
- Admin interface for managing duplicate user accounts caused by case sensitivity bug
- Admin interface for managing plugin configuration (API URL, username, password)
  with support for both config.php and database-based configuration

## [0.1.2] - 2024-06-07

### Changed
- Move to new logger syntax for Nextcloud 31 compatibility.

## [0.1.0] - 2022-09-03

### Fixed
- Fixed invalid info.xml

### Added
- Initial version of the UserVO plugin with username and password support.

[unreleased]: https://github.com/NikolausDemmel/user_vo/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/NikolausDemmel/user_vo/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/NikolausDemmel/user_vo/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/NikolausDemmel/user_vo/compare/v0.1.0...v0.1.2
[0.1.0]: https://github.com/NikolausDemmel/user_vo/releases/tag/v0.1.0

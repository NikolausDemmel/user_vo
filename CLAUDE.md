# User VO - VereinOnline User Authentication for Nextcloud

A Nextcloud authentication plugin that authenticates users against the [VereinOnline](https://vereinonline.org/) API.

## Overview

This plugin enables Nextcloud to authenticate users using their VereinOnline credentials. Passwords are never stored locally - all authentication happens against the remote VereinOnline API server.

**Key Features:**
- External authentication via VereinOnline API
- User display names cached in local database table `user_vo`
- Admin interface for configuration and duplicate user management
- Support for both `config.php` and admin UI configuration
- Compatible with Nextcloud 24-31

## Architecture

### Components

```
lib/
├── AppInfo/Application.php      # App bootstrap, backend registration
├── UserVOAuth.php               # Main authentication logic
├── Base.php                     # Base class for user backend
├── Controller/
│   └── AdminController.php      # Admin settings API endpoints
├── Settings/
│   ├── UserVOAdminSettings.php  # Admin UI template
│   └── UserVOAdminSection.php   # Admin section registration
├── Service/
│   └── ConfigService.php        # Configuration management
└── Migration/
    └── Version1000Date...php    # Database schema
```

### Authentication Flow

1. User enters credentials at Nextcloud login
2. `UserVOAuth::checkCanonicalPassword()` is called
3. API request sent to VereinOnline with credentials
4. On success, user record created/updated in `user_vo` table
5. User logged into Nextcloud

### Configuration Precedence

The plugin supports two configuration methods with clear precedence:

1. **config.php** (highest priority) - Traditional server configuration
2. **Admin UI** (fallback) - Database-stored configuration via admin interface

`ConfigService` handles loading configuration with proper precedence.

## Installation & Configuration

### Installation

1. Place plugin in Nextcloud's `apps` directory
2. Enable via OCC: `php occ app:enable user_vo`
3. Configure via admin interface or config.php

### Configuration via config.php

Add to your Nextcloud `config/config.php`:

```php
'user_backends' => array(
    array(
        'class' => '\\OCA\\UserVO\\UserVOAuth',
        'arguments' => array(
            'https://vereinonline.org/YOUR_ORGANIZATION', // API URL
            'API_USERNAME',                                // API username
            'API_PASSWORD',                                // API password
        ),
    ),
),
```

**Note:** When configured via `config.php`, the backend is NOT auto-registered by the Application class to avoid conflicts. Nextcloud's core loads backends from this array.

### Configuration via Admin Interface

Navigate to **Settings** → **Administration** → **User VO** to:
- Set API URL, username, and password
- Test connection to VereinOnline API
- View active configuration source
- Manage duplicate users (from case-sensitivity bug)

The admin interface shows which configuration is active and allows testing.

## Database Schema

The plugin uses table `oc_user_vo` (prefix may vary):

```sql
CREATE TABLE oc_user_vo (
    uid VARCHAR(64) PRIMARY KEY,
    displayname VARCHAR(64),
    backend VARCHAR(64)
);
```

**Important:** If you modify `user_backends` configuration, update the `backend` field to match, or users will lose their display names.

## Development

### Building Release Packages

```bash
# Build appstore package
make appstore

# Output: build/artifacts/appstore/user_vo.tar.gz
```

The Makefile excludes development files (tests, Makefile, readme-dev.md, etc.) from the appstore package.

### ⚠️ Security: .gitignore and Makefile Exclusions

**IMPORTANT:** Whenever you add a file to `.gitignore`, also ensure it's excluded from the package in the `Makefile`.

This is **critical for PHP files** that might contain credentials (config files, test files, etc.):

1. **Add to `.gitignore`:**
   ```
   test_vo_api.php
   config_*.php
   ```

2. **Add to `Makefile` exclusions:**
   ```makefile
   --exclude="../$(app_name)/test_vo_api.php" \
   --exclude="../$(app_name)/config_*.php" \
   ```

**Why this matters:**
- `.gitignore` prevents accidental commits to version control
- Makefile exclusions prevent credentials from being packaged in releases
- Both layers of protection are necessary

**Files to watch for:**
- `test_*.php` - Test scripts with API credentials
- `config_*.php` - Configuration files with passwords
- `temp_*.php` - Temporary files that might contain sensitive data

### Key Files for Development

- `appinfo/info.xml` - App metadata, version, dependencies
- `CHANGELOG.md` - Version history
- `Makefile` - Build and packaging scripts
- `readme-dev.md` - Release process documentation

### Code Structure Notes

**UserVOAuth.php:**
- Extends `Base.php` which implements Nextcloud's user backend interface
- Constructor accepts optional arguments for backward compatibility
- Falls back to `ConfigService` when no arguments provided
- `checkCanonicalPassword()` performs actual authentication via API
- `makeRequest()` handles HTTP communication with VereinOnline API

**Case Sensitivity Fix (v0.2.0):**
- Usernames now normalized to lowercase on creation
- Existing users with mixed-case usernames remain functional
- Admin UI provides duplicate management tools

## API Integration

### VereinOnline API Authentication

The plugin uses token-based authentication:

```php
$token = 'A/' . $username . '/' . md5($password);
```

### API Endpoint

```
POST {api_url}/?api=VerifyLogin
Authorization: {token}
Content-Type: application/json

{
    "user": "username",
    "password": "password",
    "result": "id"
}
```

Response on success: `["user_id"]`
Response on failure: `{"error": "message"}`

## Troubleshooting

### Check Logs

Nextcloud logs are at `data/nextcloud.log`:

```bash
# Search for user_vo entries
grep 'user_vo' data/nextcloud.log
```

Common log messages:
- `"Using configuration from constructor parameters"` - Using config.php
- `"UserVO configuration is incomplete"` - Missing API credentials
- `"API request failed"` - Network/connectivity issues
- `"User authentication error"` - Invalid credentials

### Common Issues

**Users can't log in:**
- Check configuration is complete (API URL, username, password)
- Test API connection in admin interface
- Verify VereinOnline API is accessible
- Check logs for authentication errors

**Display names lost:**
- Verify `backend` field in `user_vo` table matches config
- Check if `user_backends` configuration changed

**Case sensitivity duplicates:**
- Use admin interface duplicate management tools (v0.2.0+)
- See CHANGELOG.md for migration details

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

**Current version: 0.2.0** (2025-10-04)
- Fixed case sensitivity bug causing duplicate accounts
- Added admin interface for configuration and duplicate management

## License

AGPL-3.0 - See [LICENSE](LICENSE)

## Links

- **GitHub:** https://github.com/NikolausDemmel/user_vo
- **Issues:** https://github.com/NikolausDemmel/user_vo/issues
- **Nextcloud App Store:** https://apps.nextcloud.com/apps/user_vo

## Credits

Based on [Nextcloud External User Authentication](https://github.com/nextcloud/user_external) plugin.

Author: Nikolaus Demmel

VereinOnline user authentication
============================


**Authenticate user login against the [VereinOnline](https://vereinonline.org/) API with automatic user data synchronization.**

Passwords are not stored locally; authentication always happens against the
remote server. User data (display name, email, profile photos) is automatically
synchronized from VereinOnline on login and optionally via nightly background jobs.

User information is stored in the database table `user_vo`. When
modifying the `user_backends` configuration, you need to update the database
table's `backend` field, or your users will lose their configured display name.

If something does not work, check the log file at
`nextcloud/data/nextcloud.log`.

**⚠⚠ Warning:** If you are using more than one backend or especially one backend
more often than once, make sure that you still have resp. get unique `uid`s in
the database. ⚠⚠

> *Note:* The implementation and documentation is derived from the [External
User Authentication plugin](https://github.com/nextcloud/user_external).

## Configuration

### API Configuration

Configure the plugin via the admin interface at **Settings** → **Administration** → **User VO**. Provide your VereinOnline API URL, username, and password.

Alternatively, add this to your `config.php`:

```php
'user_backends' => array(
    array(
        'class' => '\OCA\UserVO\UserVOAuth',
        'arguments' => array(
            'https://vereinonline.org/YOUR_ORGANIZATION', // API URL
            'API_USERNAME',                                // API username
            'API_PASSWORD',                                // API password
        ),
    ),
),
```

**Note:** Settings in `config.php` take precedence over admin interface settings. The admin interface shows which configuration is active and allows testing the connection.

### User Data Synchronization

The admin interface provides control over user data synchronization:

**Sync Options:**
- **Email sync** (enabled by default): Synchronize email addresses from VereinOnline
- **Photo sync** (disabled by default): Synchronize profile pictures from VereinOnline
- Display name sync is always enabled

**Nightly Sync:**
- **Disabled by default** - enable via checkbox in admin interface
- Runs automatically every 24 hours when enabled
- Shows status: last run time, success/failed, sync summary
- Useful for keeping user data up-to-date without requiring login

**Manual Sync:**
- Trigger immediate sync for all users via "Sync from VO" button
- Preview local users and VO data before syncing
- View detailed sync results and status

**Important:** VereinOnline is the source of truth. Manual changes to user data in Nextcloud will be overwritten on next sync.

### Upgrading from v0.2.2

When upgrading to v0.3.0, the first sync automatically populates VO user IDs for existing users. No user action is required - users don't need to log in again after the upgrade.

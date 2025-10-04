VereinOnline user authentication
============================


**Authenticate user login against the [VereinOnline](https://vereinonline.org/) API.**

Passwords are not stored locally; authentication always happens against the
remote server.

It stores users and their display name in its own database table `user_vo`. When
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

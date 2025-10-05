<?php
script('user_vo', 'admin');
style('user_vo', 'admin');
?>

<div id="user_vo_admin" class="section">
    <h2><?php p($l->t('VereinOnline User Authentication')); ?></h2>

    <!-- Configuration Section -->
    <div class="configuration-section">
        <h3><?php p($l->t('Configuration')); ?></h3>

        <?php
            $sources = $_['config_status']['current_config']['sources'] ?? [];
            $hasConfigPhp = in_array('config.php', $sources);
            $hasAdminInterface = in_array('admin_interface', $sources);
            $adminConfig = $_['config_status']['admin_config'];
            $hasAdminConfigSet = !empty($adminConfig['api_url']) || !empty($adminConfig['api_username']) || !empty($adminConfig['api_password']);

            // Check if config.php configuration is complete
            $configPhpComplete = ($sources['api_url'] === 'config.php') &&
                                 ($sources['api_username'] === 'config.php') &&
                                 ($sources['api_password'] === 'config.php');
            $isPartialConfig = $hasConfigPhp && !$configPhpComplete;

            // Check if overall configuration is complete (from any source)
            // A value is set if it has a source (not null)
            $isConfigComplete = ($sources['api_url'] !== null) &&
                               ($sources['api_username'] !== null) &&
                               ($sources['api_password'] !== null);
        ?>

        <?php if ($hasConfigPhp): ?>
            <!-- Config.php is present - show current active config -->
            <div class="status-box <?php echo $isPartialConfig ? 'status-box-red' : 'status-box-green'; ?>">
                <div style="margin-bottom: 15px;">
                    <span class="icon icon-info"></span>
                    <strong>
                        <?php if ($isPartialConfig): ?>
                            <?php p($l->t('Partial Configuration (from config.php)')); ?>
                        <?php else: ?>
                            <?php p($l->t('Active Configuration (from config.php)')); ?>
                        <?php endif; ?>
                    </strong>
                    <p style="margin: 5px 0;">
                        <?php if ($isPartialConfig): ?>
                            <?php p($l->t('This plugin is partially configured via config.php. Some required values are missing. Values in config.php take precedence over admin interface settings. To configure the plugin through this admin interface instead, remove the user_backends entry for UserVO from your config.php file.')); ?>
                        <?php else: ?>
                            <?php p($l->t('This plugin is configured via config.php. These values take precedence and cannot be changed through this interface. To configure the plugin through this admin interface instead, remove the user_backends entry for UserVO from your config.php file.')); ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div style="max-width: 600px;">
                    <div style="margin-bottom: 12px;">
                        <div style="font-weight: bold; margin-bottom: 5px;"><?php p($l->t('API URL')); ?></div>
                        <div class="config-value-display">
                            <?php if ($sources['api_url'] === 'config.php'): ?>
                                <?php p($_['config_status']['current_config']['api_url']); ?>
                            <?php else: ?>
                                <span class="not-set"><?php p($l->t('(not set in config.php)')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <div style="font-weight: bold; margin-bottom: 5px;"><?php p($l->t('API Username')); ?></div>
                        <div class="config-value-display">
                            <?php if ($sources['api_username'] === 'config.php'): ?>
                                <?php p($_['config_status']['current_config']['api_username']); ?>
                            <?php else: ?>
                                <span class="not-set"><?php p($l->t('(not set in config.php)')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <div style="font-weight: bold; margin-bottom: 5px;"><?php p($l->t('API Password')); ?></div>
                        <div class="config-value-display">
                            <?php if ($sources['api_password'] === 'config.php'): ?>
                                <?php p($_['config_status']['current_config']['api_password']); ?>
                            <?php else: ?>
                                <span class="not-set"><?php p($l->t('(not set in config.php)')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="button" id="test-config" class="btn btn-secondary test-config-btn"
                            data-mode="config-php"
                            data-api-url="<?php p($_['config_status']['current_config']['api_url'] ?? ''); ?>"
                            data-api-username="<?php p($_['config_status']['current_config']['api_username'] ?? ''); ?>"
                            <?php if (!$isConfigComplete): ?>disabled="disabled" style="opacity: 0.5; cursor: not-allowed;"<?php endif; ?>>
                        <?php p($l->t('Test Configuration')); ?>
                    </button>
                    <?php if (!$isConfigComplete): ?>
                        <p class="incomplete-warning">
                            <?php p($l->t('Configuration is incomplete. All three values (URL, Username, Password) are required to test the configuration.')); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div id="config-message-configphp" class="config-message" style="display: none; margin-top: 15px;"></div>
            </div>

        <?php endif; ?>

        <!-- Show configuration form always -->
        <?php if ($hasConfigPhp): ?>
            <!-- When config.php is active, show the form in a yellow warning box -->
            <div class="status-box status-box-yellow">
                <div style="margin-bottom: 15px;">
                    <span class="icon icon-info"></span>
                    <strong><?php p($l->t('Database Configuration (from admin interface - currently unused)')); ?></strong>
                    <p style="margin: 5px 0;"><?php p($l->t('This configuration is stored in the database but not currently used because config.php takes precedence. You can edit these values in preparation for removing the config.php settings.')); ?></p>
                </div>
        <?php else: ?>
            <!-- No config.php - show form in green box if configured, red if not -->
            <?php if ($_['config_status']['is_configured']): ?>
                <div class="status-box status-box-green">
                    <div style="margin-bottom: 15px;">
                        <span class="icon icon-checkmark"></span>
                        <strong><?php p($l->t('Active Configuration (from admin interface)')); ?></strong>
                        <p style="margin: 5px 0;"><?php p($l->t('This plugin is configured via the admin interface. You can modify the configuration below.')); ?></p>
                    </div>
            <?php else: ?>
                <div class="status-box status-box-red">
                    <div style="margin-bottom: 15px;">
                        <span class="icon icon-error"></span>
                        <strong><?php p($l->t('Configuration Required')); ?></strong>
                        <p style="margin: 5px 0;"><?php p($l->t('Please configure the plugin using the form below.')); ?></p>
                    </div>
            <?php endif; ?>
        <?php endif; ?>

        <form id="user-vo-config-form">
                <div class="form-group">
                    <label for="api-url"><?php p($l->t('API URL')); ?></label>
                    <input type="url" id="api-url" name="api_url"
                           value="<?php p($_['config_status']['admin_config']['api_url']); ?>"
                           placeholder="https://vereinonline.org/YOUR_ORGANIZATION_NAME"
                           required>
                    <em><?php p($l->t('The base URL of your VereinOnline organization')); ?></em>
                </div>

                <div class="form-group">
                    <label for="api-username"><?php p($l->t('API Username')); ?></label>
                    <input type="text" id="api-username" name="api_username"
                           value="<?php p($_['config_status']['admin_config']['api_username']); ?>"
                           placeholder="API_USER"
                           required>
                    <em><?php p($l->t('Your VereinOnline API username')); ?></em>
                </div>

                <div class="form-group">
                    <label for="api-password"><?php p($l->t('API Password')); ?></label>
                    <input type="password" id="api-password" name="api_password"
                           placeholder="<?php p($_['config_status']['admin_config']['api_password'] ? $l->t('Password set - leave empty to keep current') : $l->t('Enter API password')); ?>"
                           <?php if (!$_['config_status']['admin_config']['api_password']): ?>required<?php endif; ?>>
                    <em><?php p($l->t('Your VereinOnline API password')); ?></em>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php p($l->t('Save Configuration')); ?>
                    </button>
                    <button type="button" class="btn btn-secondary test-config-btn">
                        <?php p($l->t('Test Configuration')); ?>
                    </button>
                    <button type="button" id="clear-config" class="btn btn-secondary">
                        <?php p($l->t('Clear Configuration')); ?>
                    </button>
                </div>
            </form>

            <div id="config-message-admin" class="config-message" style="display: none;"></div>

        </div> <!-- Close colored configuration box (yellow/green/red) -->
    </div>

    <!-- User Data Synchronization Section -->
    <div class="user-sync-section">
        <h3><?php p($l->t('User Data Synchronization')); ?></h3>

        <div class="vo-notice">
            <span class="icon icon-info"></span>
            <?php p($l->t('User data (display name, email) is automatically synchronized from VereinOnline on every login. VO is the source of truth - manual changes in Nextcloud will be overwritten.')); ?>
        </div>

        <h4><?php p($l->t('Sync Options')); ?></h4>
        <p>
            <input type="checkbox" id="sync-email" name="sync_email" class="checkbox"
                   <?php if (($_['sync_settings']['sync_email'] ?? 'true') === 'true'): ?>checked<?php endif; ?> />
            <label for="sync-email"><?php p($l->t('Sync email addresses from VO (enabled by default)')); ?></label>
        </p>
        <p>
            <input type="checkbox" id="sync-photo" name="sync_photo" class="checkbox"
                   <?php if (($_['sync_settings']['sync_photo'] ?? 'false') === 'true'): ?>checked<?php endif; ?> />
            <label for="sync-photo"><?php p($l->t('Sync profile pictures from VO (disabled by default)')); ?></label>
        </p>
        <p>
            <button id="save-user-sync-settings" class="button"><?php p($l->t('Save Sync Settings')); ?></button>
            <span id="user-sync-message" class="config-message"></span>
        </p>

        <h4><?php p($l->t('Manual User Sync')); ?></h4>
        <p><?php p($l->t('Trigger immediate synchronization for all users. This will fetch the latest data from VereinOnline for all user_vo users.')); ?></p>
        <p>
            <button id="view-local-data" class="button"><?php p($l->t('View Users')); ?></button>
            <button id="view-user-metadata" class="button"><?php p($l->t('Preview VO')); ?></button>
            <button id="sync-all-users" class="button"><?php p($l->t('Sync from VO')); ?></button>
            <span id="sync-all-users-status"></span>
        </p>

        <div id="user-sync-results" style="display:none; margin-top: 20px;">
            <h4><?php p($l->t('Sync Results')); ?></h4>
            <div id="user-sync-summary"></div>
            <table class="vo-users-table">
                <thead>
                    <tr>
                        <th><?php p($l->t('Username')); ?></th>
                        <th><?php p($l->t('VO User ID')); ?></th>
                        <th><?php p($l->t('Display Name')); ?></th>
                        <th><?php p($l->t('Email')); ?></th>
                        <th><?php p($l->t('Photo')); ?></th>
                        <th><?php p($l->t('Last Synced')); ?></th>
                        <th><?php p($l->t('Status')); ?></th>
                    </tr>
                </thead>
                <tbody id="user-sync-list"></tbody>
            </table>
        </div>
    </div>

    <!-- User Account Management Section -->
    <div class="duplicate-accounts-section">
        <h3><?php p($l->t('User Account Management')); ?></h3>
        <p><?php p($l->t('This tool helps you identify and manage duplicate user accounts that were created due to a case sensitivity bug in version 0.1.2 and earlier of the user_vo plugin (see ')); ?><a href="https://github.com/NikolausDemmel/user_vo/issues/2" target="_blank" rel="noopener">GitHub issue #2</a><?php p($l->t('). When users logged in with different capitalizations of their username, multiple accounts were created for the same person.')); ?></p>
        <p><?php p($l->t('Use this interface to scan for duplicates and decide which accounts to keep visible. After exposing duplicate accounts, users can log into them to retrieve files or data, and you can then delete unwanted accounts through the user management interface or using the occ user:delete command.')); ?></p>

        <div class="admin-controls">
            <button id="scan-duplicates" class="btn btn-primary">
                <?php p($l->t('Scan for Users')); ?>
            </button>
        </div>

        <div id="scan-results" style="display: none;">
            <div id="summary-info"></div>

            <div id="duplicate-results" style="display: none;">
                <h4><?php p($l->t('Duplicate Users')); ?></h4>
                <p><?php p($l->t('Users with existing duplicates that can be managed:')); ?></p>
                <div id="duplicate-list"></div>
            </div>

            <div id="all-users-results" style="display: none;">
                <h4><?php p($l->t('All Plugin Users')); ?></h4>
                <p><?php p($l->t('Complete overview of all users managed by the user_vo plugin:')); ?></p>
                <div id="all-users-list"></div>
            </div>
        </div>
    </div>
</div>

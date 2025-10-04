<?php
namespace OCA\UserVO\Service;

use OCP\IConfig;
use function OCP\Log\logger;

/**
 * Service for managing UserVO configuration
 * Centralizes configuration logic to avoid duplication between:
 * - UserVOAuth (authentication backend)
 * - AdminController (API endpoints)
 * - UserVOAdminSettings (settings page rendering)
 */
class ConfigService {
    private IConfig $config;

    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    /**
     * Load configuration values with proper precedence
     * Order: config.php > admin interface database settings
     *
     * @param bool $maskPassword Whether to mask the password for display (default true)
     * @return array Configuration values: ['api_url' => string, 'api_username' => string, 'api_password' => string]
     */
    public function loadConfiguration(bool $maskPassword = true): array {
        $apiUrl = '';
        $username = '';
        $password = '';

        // Check for config.php settings first (takes precedence)
        $configBackends = $this->config->getSystemValue('user_backends', []);
        $voBackend = null;

        // Find the UserVO backend in config.php
        foreach ($configBackends as $backend) {
            if (isset($backend['class'])) {
                $normalizedClass = str_replace('\\\\', '\\', $backend['class']);
                if ($normalizedClass === '\OCA\UserVO\UserVOAuth' || $normalizedClass === 'OCA\UserVO\UserVOAuth') {
                    $voBackend = $backend;
                    break;
                }
            }
        }

        if ($voBackend && isset($voBackend['arguments'])) {
            // Use config.php settings (takes precedence) - even if incomplete
            $apiUrl = $voBackend['arguments'][0] ?? '';
            $username = $voBackend['arguments'][1] ?? '';
            $password = $voBackend['arguments'][2] ?? '';

            logger('user_vo')->debug('Using configuration from config.php', [
                'has_url' => !empty($apiUrl),
                'has_username' => !empty($username),
                'has_password' => !empty($password)
            ]);
        }

        // Fill in missing values from admin interface if config.php is incomplete
        if (empty($apiUrl)) {
            $apiUrl = $this->config->getAppValue('user_vo', 'api_url', '');
        }
        if (empty($username)) {
            $username = $this->config->getAppValue('user_vo', 'api_username', '');
        }
        if (empty($password)) {
            $password = $this->config->getAppValue('user_vo', 'api_password', '');
        }

        return [
            'api_url' => $apiUrl,
            'api_username' => $username,
            'api_password' => $maskPassword && $password ? $this->maskPassword($password) : $password,
        ];
    }

    /**
     * Mask a password for display
     *
     * @param string $password The password to mask
     * @return string Masked password (asterisks matching length)
     */
    private function maskPassword(string $password): string {
        return str_repeat('*', strlen($password));
    }

    /**
     * Get current configuration source
     *
     * @return string 'config.php', 'admin_interface', or 'incomplete'
     */
    public function getConfigurationSource(): string {
        $configBackends = $this->config->getSystemValue('user_backends', []);

        foreach ($configBackends as $backend) {
            if (isset($backend['class'])) {
                $normalizedClass = str_replace('\\\\', '\\', $backend['class']);
                if ($normalizedClass === '\OCA\UserVO\UserVOAuth' || $normalizedClass === 'OCA\UserVO\UserVOAuth') {
                    return 'config.php';
                }
            }
        }

        // Check if admin interface has settings
        $adminApiUrl = $this->config->getAppValue('user_vo', 'api_url', '');
        if (!empty($adminApiUrl)) {
            return 'admin_interface';
        }

        return 'incomplete';
    }

    /**
     * Get detailed information about where each config value comes from
     *
     * @return array ['api_url' => 'config.php'|'admin_interface'|null, ...]
     */
    public function getConfigurationSources(): array {
        $configBackends = $this->config->getSystemValue('user_backends', []);
        $voBackend = null;

        // Find the UserVO backend in config.php
        foreach ($configBackends as $backend) {
            if (isset($backend['class'])) {
                $normalizedClass = str_replace('\\\\', '\\', $backend['class']);
                if ($normalizedClass === '\OCA\UserVO\UserVOAuth' || $normalizedClass === 'OCA\UserVO\UserVOAuth') {
                    $voBackend = $backend;
                    break;
                }
            }
        }

        $sources = [
            'api_url' => null,
            'api_username' => null,
            'api_password' => null,
        ];

        $configPhpValues = [
            'api_url' => false,
            'api_username' => false,
            'api_password' => false,
        ];

        // Check what's in config.php
        if ($voBackend && isset($voBackend['arguments'])) {
            if (isset($voBackend['arguments'][0]) && !empty($voBackend['arguments'][0])) {
                $configPhpValues['api_url'] = true;
            }
            if (isset($voBackend['arguments'][1]) && !empty($voBackend['arguments'][1])) {
                $configPhpValues['api_username'] = true;
            }
            if (isset($voBackend['arguments'][2]) && !empty($voBackend['arguments'][2])) {
                $configPhpValues['api_password'] = true;
            }
        }

        // Check what's in admin interface
        $adminValues = [
            'api_url' => !empty($this->config->getAppValue('user_vo', 'api_url', '')),
            'api_username' => !empty($this->config->getAppValue('user_vo', 'api_username', '')),
            'api_password' => !empty($this->config->getAppValue('user_vo', 'api_password', '')),
        ];

        // Determine source for each value
        foreach (['api_url', 'api_username', 'api_password'] as $key) {
            if ($configPhpValues[$key]) {
                $sources[$key] = 'config.php';
            } elseif ($adminValues[$key]) {
                $sources[$key] = 'admin_interface';
            }
        }

        return $sources;
    }

    /**
     * Get complete configuration status for display in admin interface
     *
     * @return array Configuration status including:
     *               - source: where config comes from (config.php/admin_interface/incomplete)
     *               - current_config: currently active configuration values (masked)
     *               - admin_config: values stored in database (for "unused config" display, masked)
     *               - is_config_php: whether config.php is being used
     *               - is_configured: whether plugin is configured
     */
    public function getConfigurationStatus(): array {
        $source = $this->getConfigurationSource();
        $config = $this->loadConfiguration(maskPassword: true);
        $sources = $this->getConfigurationSources();

        // Get admin interface settings (even if not used)
        // These are masked for display purposes
        $adminPassword = $this->config->getAppValue('user_vo', 'api_password', '');
        $adminConfig = [
            'api_url' => $this->config->getAppValue('user_vo', 'api_url', ''),
            'api_username' => $this->config->getAppValue('user_vo', 'api_username', ''),
            'api_password' => $adminPassword ? $this->maskPassword($adminPassword) : '',
        ];

        return [
            'source' => $source,
            'current_config' => [
                'api_url' => $config['api_url'],
                'api_username' => $config['api_username'],
                'api_password' => $config['api_password'],
                'source' => $source,
                'sources' => $sources,
            ],
            'admin_config' => $adminConfig,
            'is_config_php' => $source === 'config.php',
            'is_configured' => $source !== 'incomplete'
        ];
    }

    /**
     * Save configuration to database
     *
     * @param string $apiUrl
     * @param string $apiUsername
     * @param string $apiPassword (optional, won't overwrite if empty)
     * @return bool Success
     */
    public function saveConfiguration(string $apiUrl, string $apiUsername, string $apiPassword = ''): bool {
        $this->config->setAppValue('user_vo', 'api_url', $apiUrl);
        $this->config->setAppValue('user_vo', 'api_username', $apiUsername);

        // Only update password if provided (don't overwrite existing password with empty string)
        if (!empty($apiPassword)) {
            $this->config->setAppValue('user_vo', 'api_password', $apiPassword);
        }

        return true;
    }

    /**
     * Clear all configuration from database
     *
     * @return bool Success
     */
    public function clearConfiguration(): bool {
        $this->config->deleteAppValue('user_vo', 'api_url');
        $this->config->deleteAppValue('user_vo', 'api_username');
        $this->config->deleteAppValue('user_vo', 'api_password');

        return true;
    }
}

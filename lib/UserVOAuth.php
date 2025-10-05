<?php
/**
 * @author Nikolaus Demmel <nikolaus@nikolaus-demmel.de>
 * @copyright (c) 2023 Nikolaus Demmel <nikolaus@nikolaus-demmel.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the LICENSE file.
 */

declare(strict_types=1);

namespace OCA\UserVO;

use function OCP\Log\logger;
use OCA\UserVO\Base;
use OCP\IConfig;
use OCA\UserVO\Service\ConfigService;

class UserVOAuth extends Base {
    private $apiUrl;
    private $username;
    private $password;
    private $config;
    private $configService;

    public function __construct($apiUrl = null, $username = null, $password = null, IConfig $config = null) {
        parent::__construct('user_vo');
        $this->config = $config ?? \OC::$server->getConfig();
        $this->configService = new ConfigService($this->config);

        if ($apiUrl !== null && $username !== null && $password !== null) {
            // Use constructor parameters (for backward compatibility / testing)
            $this->apiUrl = $apiUrl;
            $this->username = $username;
            $this->password = $password;
            logger('user_vo')->debug('Using configuration from constructor parameters');
        } else {
            // Load configuration using ConfigService (handles precedence: config.php > admin interface)
            $configuration = $this->configService->loadConfiguration(maskPassword: false);
            $this->apiUrl = $configuration['api_url'];
            $this->username = $configuration['api_username'];
            $this->password = $configuration['api_password'];
        }

        // Validate that we have all required configuration
        if (empty($this->apiUrl) || empty($this->username) || empty($this->password)) {
            logger('user_vo')->error('UserVO configuration is incomplete. Please configure via config.php or admin interface.');
        }
    }

    /**
     * Get current configuration source
     * @return string 'config.php', 'admin_interface', or 'incomplete'
     */
    public function getConfigurationSource(): string {
        return $this->configService->getConfigurationSource();
    }

    /**
     * Get current configuration values
     * @return array
     */
    public function getCurrentConfig(): array {
        // Get masked configuration from ConfigService
        $maskedConfig = $this->configService->loadConfiguration(maskPassword: true);

        return [
            'api_url' => $this->apiUrl,
            'api_username' => $this->username,
            'api_password' => $maskedConfig['api_password'], // Already masked by ConfigService
            'source' => $this->getConfigurationSource(),
            'sources' => $this->getConfigurationSources()
        ];
    }

    /**
     * Get detailed information about where each config value comes from
     * @return array
     */
    public function getConfigurationSources(): array {
        return $this->configService->getConfigurationSources();
    }

    /**
     * Check if the provided credentials are valid and authenticate the user.
     *
     * @param string $uid      The canonical username
     * @param string $password The password
     *
     * @return bool|string The authenticated user's ID if successful, otherwise false
     */
    protected function checkCanonicalPassword($uid, $password) {
        // Perform the necessary authentication logic using Vereinonline API
        // Make API request to verify the credentials and retrieve user information
        // Return the authenticated user's ID or false

        // Example implementation:
        $token = 'A/' . $this->username . '/' . md5($this->password);

        $url = $this->apiUrl . "/?api=VerifyLogin";
        $data = [
            'user' => $uid,
            'password' => $password,
            'result' => 'id',
        ];

        $response = $this->makeRequest($url, $data, $token);

        if ($response === null) {
            logger('user_vo')->error('API request failed');
            return false;
        } elseif (is_array($response) && isset($response[0]) && $response[0] !== '') {
            // Authentication successful - store user first
            $this->storeUser($uid);

            // Fetch extended user data from VO and sync to NC
            $voUserId = $response[0];
            $voUserData = $this->fetchUserDataFromVO($voUserId);

            if ($voUserData !== null) {
                // Sync user data (display name, email, phone, metadata)
                $this->syncUserData($uid, $voUserData);
            } else {
                logger('user_vo')->warning('Failed to fetch user data from VO after successful login', [
                    'uid' => $uid,
                    'vo_user_id' => $voUserId
                ]);
                // Continue login anyway - authentication was successful
            }

            return $uid;
        } elseif (is_array($response) && isset($response['error'])) {
            $errorMessage = $response['error'];
            logger('user_vo')->error('User authentication error: ' . $errorMessage);
            return false;
        } else {
            logger('user_vo')->error('Invalid API response: ' . json_encode($response), ['app' => 'user_vo']);
            return false;
        }
    }


    /**
     * Make a request to the Vereinonline API.
     *
     * @param string $url    The API URL
     * @param array  $data   The request data
     * @param string $token  The authentication token
     *
     * @return mixed The API response
     */
    private function makeRequest($url, $data, $token) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $token,
        ]);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HEADER, false);



        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);



        if ($response === false) {
            logger('user_vo')->error('API request failed: ' . $error);
            return null;
        }

        if ($httpCode !== 200) {
            logger('user_vo')->error('API request returned non-200 status code: ' . $httpCode);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Fetch extended user data from VO API
     *
     * @param string $voUserId VO user ID (from VerifyLogin result)
     * @return array|null User data or null on error
     */
    protected function fetchUserDataFromVO(string $voUserId): ?array {
        $token = 'A/' . $this->username . '/' . md5($this->password);
        $url = $this->apiUrl . "/?api=GetMember";
        $data = ['id' => $voUserId];

        $response = $this->makeRequest($url, $data, $token);

        if (!$response || isset($response['error'])) {
            logger('user_vo')->error("Failed to fetch user data from VO", [
                'vo_user_id' => $voUserId,
                'error' => $response['error'] ?? 'Unknown error'
            ]);
            return null;
        }

        // CRITICAL: Filter out users without login credentials
        if (empty($response['userlogin'])) {
            logger('user_vo')->debug("Skipping user without VO login credentials", [
                'vo_user_id' => $voUserId
            ]);
            return null;
        }

        // Check if user is deleted in VO
        if (!empty($response['geloescht']) && $response['geloescht'] !== "0") {
            logger('user_vo')->info("User is deleted in VO", ['vo_user_id' => $voUserId]);
            return null;
        }

        // Return normalized structure with actual VO field names
        return [
            'id' => $response['id'],                    // VO user ID
            'username' => $response['userlogin'],       // Username for NC
            'firstname' => $response['vorname'] ?? '',  // First name
            'lastname' => $response['nachname'] ?? '',  // Last name
            'email' => $response['p_email'] ?? '',      // Personal email
            'group_ids' => $response['gruppenids'] ?? '',     // Comma-separated group IDs
        ];
    }

    /**
     * Synchronize user data from VO to Nextcloud
     *
     * @param string $uid NC username (lowercase canonical)
     * @param array $voUserData User data from fetchUserDataFromVO
     * @return bool Success
     */
    protected function syncUserData(string $uid, array $voUserData): bool {
        try {
            // Username mismatch warning - VO username might have different case
            if (strtolower($voUserData['username']) !== $uid) {
                logger('user_vo')->warning("Username mismatch during sync", [
                    'nc_uid' => $uid,
                    'vo_username' => $voUserData['username'],
                    'vo_user_id' => $voUserData['id']
                ]);
                // Continue anyway - we use NC's canonical username
            }

            // Get NC user object
            $userManager = \OC::$server->getUserManager();
            $user = $userManager->get($uid);

            if (!$user) {
                logger('user_vo')->error("Cannot sync - user not found in NC", ['uid' => $uid]);
                return false;
            }

            // Update display name (always)
            $displayName = trim($voUserData['firstname'] . ' ' . $voUserData['lastname']);
            if (!empty($displayName) && $displayName !== ' ') {
                $user->setDisplayName($displayName);
            }

            // Update email (if configured and available)
            $syncEmail = $this->config->getAppValue('user_vo', 'sync_email', 'true') === 'true';
            if ($syncEmail && !empty($voUserData['email'])) {
                $user->setSystemEMailAddress($voUserData['email']);
            }

            // Update metadata in user_vo table
            $this->updateVOMetadata($uid, $voUserData);

            return true;

        } catch (\Exception $e) {
            logger('user_vo')->error("Failed to sync user data", [
                'uid' => $uid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update VO metadata in user_vo table
     *
     * @param string $uid NC username
     * @param array $voUserData User data from fetchUserDataFromVO
     */
    protected function updateVOMetadata(string $uid, array $voUserData): void {
        try {
            $db = \OC::$server->getDatabaseConnection();

            // Check if record exists
            $qb = $db->getQueryBuilder();
            $qb->select('uid')
                ->from('user_vo')
                ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
            $result = $qb->executeQuery();
            $exists = $result->fetchOne() !== false;
            $result->closeCursor();

            if ($exists) {
                // Update existing record
                $qb = $db->getQueryBuilder();
                $qb->update('user_vo')
                    ->set('vo_user_id', $qb->createNamedParameter($voUserData['id']))
                    ->set('vo_group_ids', $qb->createNamedParameter($voUserData['group_ids']))
                    ->set('last_synced', $qb->createNamedParameter(new \DateTime(), 'datetime'))
                    ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
                $qb->executeStatement();
            } else {
                // Insert new record (should not normally happen as storeUser creates the record)
                $displayName = trim($voUserData['firstname'] . ' ' . $voUserData['lastname']);
                $qb = $db->getQueryBuilder();
                $qb->insert('user_vo')
                    ->values([
                        'uid' => $qb->createNamedParameter($uid),
                        'displayname' => $qb->createNamedParameter($displayName),
                        'backend' => $qb->createNamedParameter(self::class),
                        'vo_user_id' => $qb->createNamedParameter($voUserData['id']),
                        'vo_group_ids' => $qb->createNamedParameter($voUserData['group_ids']),
                        'last_synced' => $qb->createNamedParameter(new \DateTime(), 'datetime')
                    ]);
                $qb->executeStatement();
            }

            logger('user_vo')->debug("Updated VO metadata", [
                'uid' => $uid,
                'vo_user_id' => $voUserData['id'],
                'group_count' => empty($voUserData['group_ids']) ? 0 : count(explode(',', $voUserData['group_ids']))
            ]);

        } catch (\Exception $e) {
            logger('user_vo')->error("Failed to update VO metadata", [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
        }
    }
}

<?php

namespace OCA\UserVO\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IConfig;
use OCA\UserVO\UserVOAuth;
use OCA\UserVO\Service\ConfigService;
use Psr\Log\LoggerInterface;

class AdminController extends Controller {

    private $connection;
    private $logger;
    private $groupManager;
    private $config;
    private $configService;

    public function __construct(
        $appName,
        IRequest $request,
        IDBConnection $connection,
        LoggerInterface $logger,
        IGroupManager $groupManager,
        IConfig $config,
        ConfigService $configService
    ) {
        parent::__construct($appName, $request);
        $this->connection = $connection;
        $this->logger = $logger;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->configService = $configService;
    }

    /**
     * Admin settings page
     */
    public function index() {
        // Get current configuration status from service
        $configStatus = $this->configService->getConfigurationStatus();

        return new TemplateResponse('user_vo', 'admin', [
            'config_status' => $configStatus
        ], 'admin');
    }

    /**
     * Get configuration status (API endpoint)
     */
    public function getConfigurationStatus() {
        return $this->configService->getConfigurationStatus();
    }

    /**
     * Save configuration via admin interface
     */
    public function saveConfiguration() {
        $apiUrl = $this->request->getParam('api_url', '');
        $apiUsername = $this->request->getParam('api_username', '');
        $apiPassword = $this->request->getParam('api_password', '');

        // Check if we have partial configuration in database
        $existingUrl = $this->config->getAppValue('user_vo', 'api_url', '');
        $existingUsername = $this->config->getAppValue('user_vo', 'api_username', '');
        $existingPassword = $this->config->getAppValue('user_vo', 'api_password', '');

        $hasPartialConfig = !empty($existingUrl) || !empty($existingUsername) || !empty($existingPassword);
        $hasIncompleteConfig = empty($apiUrl) || empty($apiUsername) || (empty($apiPassword) && empty($existingPassword));

        if ($hasPartialConfig && $hasIncompleteConfig) {
            return new JSONResponse([
                'success' => false,
                'message' => 'Configuration is incomplete. Please provide all required fields (API URL, Username, and Password).'
            ], 400);
        }

        // Validate required fields
        if (empty($apiUrl) || empty($apiUsername) || (empty($apiPassword) && empty($existingPassword))) {
            return new JSONResponse([
                'success' => false,
                'message' => 'API URL and Username are required. Password is required if not already set.'
            ], 400);
        }

        // Validate URL format
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return new JSONResponse([
                'success' => false,
                'message' => 'Invalid API URL format.'
            ], 400);
        }

        // Save to database using service
        $this->configService->saveConfiguration($apiUrl, $apiUsername, $apiPassword);

        $this->logger->info('UserVO configuration updated via admin interface');

        return new JSONResponse([
            'success' => true,
            'message' => 'Configuration saved successfully.'
        ]);
    }

    /**
     * Test configuration by making a test API request
     */
    public function testConfiguration() {
        $apiUrl = $this->request->getParam('api_url', '');
        $apiUsername = $this->request->getParam('api_username', '');
        $apiPassword = $this->request->getParam('api_password', '');

        // If no password provided, get the actual password from configuration
        // For admin interface mode (URL/username provided), get from database only
        // For config.php mode (no URL/username), get from full configuration
        if (empty($apiPassword)) {
            if (!empty($apiUrl) && !empty($apiUsername)) {
                // Admin interface mode - get password from database only
                $apiPassword = $this->config->getAppValue('user_vo', 'api_password', '');
            } else {
                // Config.php mode - get everything from configuration (respects precedence)
                $configuration = $this->configService->loadConfiguration(maskPassword: false);
                $apiPassword = $configuration['api_password'];

                if (empty($apiUrl)) {
                    $apiUrl = $configuration['api_url'];
                }
                if (empty($apiUsername)) {
                    $apiUsername = $configuration['api_username'];
                }
            }
        }

        // Validate required fields
        if (empty($apiUrl) || empty($apiUsername) || empty($apiPassword)) {
            return new JSONResponse([
                'success' => false,
                'message' => 'API URL, Username, and Password are required for testing.'
            ], 400);
        }

        // Validate URL format
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return new JSONResponse([
                'success' => false,
                'message' => 'Invalid API URL format.'
            ], 400);
        }

        try {
            // Test the API connection
            $result = $this->testApiConnection($apiUrl, $apiUsername, $apiPassword);

            if ($result['success']) {
                return new JSONResponse([
                    'success' => true,
                    'message' => 'Configuration test successful: ' . $result['message']
                ]);
            } else {
                return new JSONResponse([
                    'success' => false,
                    'message' => 'Configuration test failed: ' . $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            $this->logger->error('Configuration test error: ' . $e->getMessage());
            return new JSONResponse([
                'success' => false,
                'message' => 'Configuration test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test API connection with provided credentials
     */
    private function testApiConnection($apiUrl, $username, $password) {
        $token = 'A/' . $username . '/' . md5($password);
        $url = rtrim($apiUrl, '/') . '/?api=VerifyLogin';

        // Test with a dummy user to verify credentials without creating real users
        $data = [
            'user' => 'test_user_that_should_not_exist',
            'password' => 'dummy_password',
            'result' => 'id',
        ];

        $response = $this->makeApiRequest($url, $data, $token);

        if ($response === null) {
            return [
                'success' => false,
                'message' => 'Unable to connect to API. Please check the API URL and network connectivity.'
            ];
        }

        // Check for authentication/authorization errors
        // VereinOnline API returns {"error":"Zugriff verweigert..."} for invalid credentials
        if (is_array($response) && isset($response['error'])) {
            $errorMessage = $response['error'];
            // Check for German "Zugriff verweigert" (Access denied) or English auth errors
            if (stripos($errorMessage, 'zugriff verweigert') !== false ||
                stripos($errorMessage, 'access denied') !== false ||
                stripos($errorMessage, 'authentication') !== false ||
                stripos($errorMessage, 'credential') !== false ||
                stripos($errorMessage, 'unauthorized') !== false) {
                return [
                    'success' => false,
                    'message' => 'Invalid API credentials. Please check your username and password.'
                ];
            }
            // Other API errors - include the message but ensure proper encoding
            return [
                'success' => false,
                'message' => 'API error: ' . $errorMessage
            ];
        }

        // If we get here, the API is reachable and credentials are valid
        // (even if the test user doesn't exist, which is expected - API returns [""] in this case)
        return [
            'success' => true,
            'message' => 'API connection successful. Credentials are valid.'
        ];
    }

    /**
     * Make a request to the VereinOnline API
     */
    private function makeApiRequest($url, $data, $token) {
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
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 10 second timeout
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5); // 5 second connection timeout

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($response === false) {
            throw new \Exception('API request failed: ' . $error);
        }

        if ($httpCode === 401 || $httpCode === 403) {
            throw new \Exception('Authentication failed (HTTP ' . $httpCode . ')');
        }

        if ($httpCode !== 200) {
            throw new \Exception('API request returned HTTP ' . $httpCode);
        }

        return json_decode($response, true);
    }

    /**
     * Clear configuration from admin interface
     */
    public function clearConfiguration() {
        // Check if config.php has settings
        $auth = new UserVOAuth(null, null, null, $this->config);
        $configSource = $auth->getConfigurationSource();

        // Clear admin interface settings from database using service
        $this->configService->clearConfiguration();

        $this->logger->info('UserVO admin interface configuration cleared');

        if ($configSource === 'config.php') {
            return new JSONResponse([
                'success' => true,
                'message' => 'Admin interface configuration cleared successfully. Note: Configuration is still active via config.php file - remove the user_backends entry from config.php to fully disable the plugin.'
            ]);
        } else {
            return new JSONResponse([
                'success' => true,
                'message' => 'Configuration cleared successfully. The plugin is now unconfigured.'
            ]);
        }
    }

    /**
     * Save user sync settings
     */
    public function saveUserSyncSettings() {
        $syncEmail = $this->request->getParam('sync_email', 'false');
        $syncPhoto = $this->request->getParam('sync_photo', 'false');

        // Convert to string 'true' or 'false' for consistency
        $emailValue = $syncEmail === 'true' || $syncEmail === true ? 'true' : 'false';
        $photoValue = $syncPhoto === 'true' || $syncPhoto === true ? 'true' : 'false';

        // Store as string 'true' or 'false' for consistency
        $this->configService->set('sync_email', $emailValue);
        $this->configService->set('sync_photo', $photoValue);

        $this->logger->info('User sync settings updated', [
            'sync_email' => $emailValue,
            'sync_photo' => $photoValue
        ]);

        return new JSONResponse([
            'success' => true,
            'message' => 'Sync settings saved successfully.'
        ]);
    }

    /**
     * Manually sync all VO users
     */
    public function syncAllUsers() {
        try {
            $results = [];
            $successCount = 0;
            $failureCount = 0;
            $skippedCount = 0;

            // Get all users from user_vo table
            $qb = $this->connection->getQueryBuilder();
            $qb->select('uid', 'vo_user_id')
                ->from('user_vo')
                ->where($qb->expr()->eq('backend', $qb->createNamedParameter('user_vo')));
            $result = $qb->executeQuery();
            $users = $result->fetchAll();
            $result->closeCursor();

            // Get UserVOAuth instance to access sync methods
            $configuration = $this->configService->loadConfiguration(maskPassword: false);
            $auth = new UserVOAuth(
                $configuration['api_url'],
                $configuration['api_username'],
                $configuration['api_password'],
                $this->config
            );

            foreach ($users as $userRow) {
                $uid = $userRow['uid'];

                // Skip users with !duplicate marker
                if (str_ends_with($uid, '!duplicate')) {
                    $skippedCount++;
                    continue;
                }

                $voUserId = $userRow['vo_user_id'];

                // If no VO user ID stored, skip (user hasn't logged in with new version yet)
                if (empty($voUserId)) {
                    $results[] = [
                        'uid' => $uid,
                        'status' => 'skipped',
                        'message' => 'No VO user ID - will sync on next login'
                    ];
                    $skippedCount++;
                    continue;
                }

                // Fetch user data from VO using reflection to access protected method
                $reflection = new \ReflectionClass($auth);
                $fetchMethod = $reflection->getMethod('fetchUserDataFromVO');
                $fetchMethod->setAccessible(true);
                $voUserData = $fetchMethod->invoke($auth, $voUserId);

                if ($voUserData === null) {
                    $results[] = [
                        'uid' => $uid,
                        'vo_user_id' => $voUserId,
                        'status' => 'failed',
                        'message' => 'Could not fetch data from VO'
                    ];
                    $failureCount++;
                    continue;
                }

                // Sync user data using reflection
                $syncMethod = $reflection->getMethod('syncUserData');
                $syncMethod->setAccessible(true);
                $success = $syncMethod->invoke($auth, $uid, $voUserData);

                if ($success) {
                    // Get last_synced from database
                    $qb = $this->connection->getQueryBuilder();
                    $qb->select('displayname', 'last_synced')
                        ->from('user_vo')
                        ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
                    $userResult = $qb->executeQuery();
                    $userData = $userResult->fetch();
                    $userResult->closeCursor();

                    // Get user email
                    $user = \OC::$server->getUserManager()->get($uid);
                    $email = $user ? $user->getSystemEMailAddress() : '';

                    // Check photo sync status
                    $photoStatus = 'Not configured';
                    $syncPhoto = $this->config->getAppValue('user_vo', 'sync_photo', 'false') === 'true';
                    if ($syncPhoto) {
                        // Get photo URL using reflection
                        $getPhotoMethod = $reflection->getMethod('getPhotoUrl');
                        $getPhotoMethod->setAccessible(true);
                        $photoUrl = $getPhotoMethod->invoke($auth, $voUserId);

                        if ($photoUrl === null) {
                            $photoStatus = 'No photo in VO';
                        } else {
                            // Sync photo and get status
                            $syncPhotoMethod = $reflection->getMethod('syncUserPhoto');
                            $syncPhotoMethod->setAccessible(true);
                            $photoResult = $syncPhotoMethod->invoke($auth, $uid, $photoUrl);
                            $photoStatus = $photoResult['message'] ?? 'Unknown';
                        }
                    }

                    $results[] = [
                        'uid' => $uid,
                        'vo_user_id' => $voUserId,
                        'display_name' => $userData['displayname'] ?? '',
                        'email' => $email,
                        'photo_status' => $photoStatus,
                        'last_synced' => $userData['last_synced'] ?? null,
                        'status' => 'success',
                        'message' => 'Synced successfully'
                    ];
                    $successCount++;
                } else {
                    $results[] = [
                        'uid' => $uid,
                        'vo_user_id' => $voUserId,
                        'status' => 'failed',
                        'message' => 'Sync method returned false'
                    ];
                    $failureCount++;
                }
            }

            return new JSONResponse([
                'success' => true,
                'summary' => [
                    'total' => count($users),
                    'success' => $successCount,
                    'failed' => $failureCount,
                    'skipped' => $skippedCount
                ],
                'results' => $results
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in syncAllUsers: ' . $e->getMessage(), ['app' => 'user_vo']);
            return new JSONResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to strip the !duplicate marker from a uid
     */
    private function stripDuplicateMarker($uid) {
        if (str_ends_with($uid, '!duplicate')) {
            return substr($uid, 0, -10);  // Correct: removes all 10 characters
        }
        return $uid;
    }

    /**
     * Scan for duplicates and return comprehensive user analysis
     */
    public function scanDuplicates() {
        try {
            // Get all users from oc_accounts
            $accountsQuery = $this->connection->getQueryBuilder();
            $accountsQuery->select('uid')
                ->from('accounts')
                ->orderBy('uid');
            $accountsResult = $accountsQuery->execute();
            $allAccountUsers = $accountsResult->fetchAll();
            $accountsResult->closeCursor();

            // Get all user_vo entries (to determine exposure status)
            $userVoQuery = $this->connection->getQueryBuilder();
            $userVoQuery->select('uid', 'displayname')
                ->from('user_vo')
                ->where($userVoQuery->expr()->eq('backend', $userVoQuery->createNamedParameter('user_vo')));
            $userVoResult = $userVoQuery->execute();
            $userVoEntries = $userVoResult->fetchAll();
            $userVoResult->closeCursor();

            // Create map of exposed users (exist in user_vo)
            $exposedUsers = [];
            foreach ($userVoEntries as $row) {
                $exposedUsers[$row['uid']] = $row['displayname'];
            }

            // Filter accounts to only include user_vo-managed users (case-insensitive match)
            $managedUsers = [];
            foreach ($allAccountUsers as $accountUser) {
                $normalizedAccount = strtolower($accountUser['uid']);
                // Check if this account has a corresponding entry in user_vo (canonical or marked)
                foreach ($exposedUsers as $voUid => $voDisplayname) {
                    if (strtolower($this->stripDuplicateMarker($voUid)) === $normalizedAccount) {
                        $managedUsers[] = $accountUser['uid'];
                        break;
                    }
                }
            }

            // Group managed users by normalized username
            $userGroups = [];
            foreach ($managedUsers as $uid) {
                $normalizedUid = strtolower($uid);
                if (!isset($userGroups[$normalizedUid])) {
                    $userGroups[$normalizedUid] = [];
                }
                $userGroups[$normalizedUid][] = $uid;
            }

            // Prepare response arrays
            $duplicateGroups = [];
            $allPluginUsers = [];

            // Process each user group
            foreach ($userGroups as $normalizedUid => $variants) {
                // Find canonical user (exists in user_vo without !duplicate marker)
                $canonical = $this->findCanonicalUser($normalizedUid);

                $variantData = [];
                foreach ($variants as $uid) {
                    $isCanonical = ($uid === $canonical);
                    $markedUid = $uid . '!duplicate';
                    $isExposed = array_key_exists($uid, $exposedUsers) || array_key_exists($markedUid, $exposedUsers);
                    $isDuplicate = array_key_exists($markedUid, $exposedUsers);

                    // Get display name from user_vo if exposed, otherwise use uid
                    $displayname = '';
                    if (array_key_exists($uid, $exposedUsers)) {
                        $displayname = !empty($exposedUsers[$uid]) ? $exposedUsers[$uid] : $uid;
                    } elseif (array_key_exists($markedUid, $exposedUsers)) {
                        $displayname = !empty($exposedUsers[$markedUid]) ? $exposedUsers[$markedUid] : $uid;
                    } else {
                        $displayname = $uid;
                    }

                    $variantData[] = [
                        'uid' => $uid,  // Clean uid for frontend
                        'display_uid' => $uid,
                        'is_exposed' => $isExposed,
                        'is_canonical' => $isCanonical,
                        'is_marked_duplicate' => $isDuplicate,
                        'file_count' => $this->countUserFiles($uid),
                        'displayname' => $displayname,
                        'groups' => $this->getUserGroups($uid),
                        'creation_date' => $this->getUserDirectoryCreationDate($uid),
                        'is_normalized' => ($uid === $normalizedUid),
                    ];
                }

                $groupInfo = [
                    'normalized_uid' => $normalizedUid,
                    'variants' => $variantData,
                ];

                // Add to appropriate categories
                if (count($variants) > 1) {
                    // Multiple variants = duplicate group
                    $duplicateGroups[] = $groupInfo;
                }

                // Add all variants to the comprehensive list
                foreach ($variantData as $variant) {
                    $allPluginUsers[] = $variant;
                }
            }

            // Sort arrays for consistent display
            usort($duplicateGroups, function($a, $b) {
                return strcmp($a['normalized_uid'], $b['normalized_uid']);
            });
            usort($allPluginUsers, function($a, $b) {
                return strcmp($a['uid'], $b['uid']);
            });

            return new JSONResponse([
                'success' => true,
                'duplicateSets' => $duplicateGroups,
                'allPluginUsers' => $allPluginUsers,
                'summary' => [
                    'duplicateSets' => count($duplicateGroups),
                    'totalManagedUsers' => count($allPluginUsers)
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in scanDuplicates: ' . $e->getMessage(), ['app' => 'user_vo']);
            return new JSONResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Expose a user: add to user_vo with !duplicate marker
     */
    public function exposeUser() {
        $data = $this->request->getParams();
        $uid = $data['uid'] ?? null;
        if (!$uid) {
            return new JSONResponse(['success' => false, 'error' => 'No uid provided']);
        }

        // Add !duplicate marker to the uid
        $markedUid = $uid . '!duplicate';

        // Only add if not already present
        $query = $this->connection->getQueryBuilder();
        $query->select('uid')
            ->from('user_vo')
            ->where($query->expr()->eq('uid', $query->createNamedParameter($markedUid)))
            ->andWhere($query->expr()->eq('backend', $query->createNamedParameter('user_vo')));
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();
        if ($row) {
            return new JSONResponse(['success' => true, 'message' => 'Already exposed']);
        }

        $insert = $this->connection->getQueryBuilder();
        $insert->insert('user_vo')
            ->values([
                'uid' => $insert->createNamedParameter($markedUid),
                'backend' => $insert->createNamedParameter('user_vo'),
                'displayname' => $insert->createNamedParameter($uid),
            ]);
        $insert->execute();
        return new JSONResponse(['success' => true]);
    }

    /**
     * Hide a user: remove from user_vo (unless canonical)
     */
    public function hideUser() {
        $data = $this->request->getParams();
        $uid = $data['uid'] ?? null;
        if (!$uid) {
            return new JSONResponse(['success' => false, 'error' => 'No uid provided']);
        }

        $normalizedUid = strtolower($uid);
        $canonical = $this->findCanonicalUser($normalizedUid);

        // Don't allow hiding canonical users
        if ($uid === $canonical) {
            return new JSONResponse(['success' => false, 'error' => 'Cannot hide canonical user']);
        }

        // Remove the marked duplicate entry (uid + !duplicate)
        $markedUid = $uid . '!duplicate';
        $delete = $this->connection->getQueryBuilder();
        $delete->delete('user_vo')
            ->where($delete->expr()->eq('uid', $delete->createNamedParameter($markedUid)))
            ->andWhere($delete->expr()->eq('backend', $delete->createNamedParameter('user_vo')));
        $delete->execute();

        return new JSONResponse(['success' => true]);
    }



    /**
     * Find the canonical user (first one without !duplicate marker) for a normalized username
     */
    private function findCanonicalUser($normalizedUid) {
        $query = $this->connection->getQueryBuilder();
        $query->select('uid')
            ->from('user_vo')
            ->where($query->expr()->eq('backend', $query->createNamedParameter('user_vo')))
            ->andWhere($query->expr()->notLike('uid', $query->createNamedParameter('%!duplicate')))
            ->andWhere($query->expr()->eq(
                $query->func()->lower('uid'),
                $query->createNamedParameter($normalizedUid)
            ))
            ->setMaxResults(1);
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();
        return $row ? $row['uid'] : null;
    }

    /**
     * Count files for a user (in their data directory)
     */
    private function countUserFiles($uid) {
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/html/data');
        $userDir = $dataDir . '/' . $uid . '/files';
        if (!is_dir($userDir)) {
            return 0;
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($userDir, \RecursiveDirectoryIterator::SKIP_DOTS));
        $count = 0;
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get groups for a user
     */
    private function getUserGroups($uid) {
        $user = \OC::$server->getUserManager()->get($uid);
        if (!$user) {
            return [];
        }

        $groups = $this->groupManager->getUserGroups($user);
        $groupNames = [];
        foreach ($groups as $group) {
            $groupNames[] = $group->getGID();
        }
        return $groupNames;
    }

    /**
     * Get user directory creation date (using birth time if available, fallback to oldest file)
     */
    private function getUserDirectoryCreationDate($uid) {
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/html/data');
        $userDir = $dataDir . '/' . $uid;

        if (!is_dir($userDir)) {
            return null;
        }

        // Try to get birth time using stat command (Linux systems)
        $birthTime = $this->getBirthTime($userDir);
        if ($birthTime !== null) {
            return date('Y-m-d H:i:s', $birthTime);
        }

        // Fallback: find the oldest file in the user directory
        $oldestTime = $this->findOldestFileTime($userDir);
        if ($oldestTime !== null) {
            return date('Y-m-d H:i:s', $oldestTime);
        }

        return null;
    }

    /**
     * Get birth time (creation time) using stat command
     */
    private function getBirthTime($path) {
        $escapedPath = escapeshellarg($path);
        $output = shell_exec("stat -c %W '$escapedPath' 2>/dev/null");

        if ($output !== null) {
            $birthTime = (int)trim($output);
            // %W returns 0 if birth time is not available
            if ($birthTime > 0) {
                return $birthTime;
            }
        }

        // Try alternative method for systems that support it
        $output = shell_exec("stat -f %B '$escapedPath' 2>/dev/null");
        if ($output !== null) {
            $birthTime = (int)trim($output);
            if ($birthTime > 0) {
                return $birthTime;
            }
        }

        return null;
    }

    /**
     * Find the oldest file in the user directory as fallback
     */
    private function findOldestFileTime($userDir) {
        $oldestTime = null;

        // Check common files that are created early
        $checkFiles = [
            $userDir . '/files',
            $userDir . '/cache',
            $userDir . '/files_trashbin'
        ];

        foreach ($checkFiles as $file) {
            if (file_exists($file)) {
                $time = filectime($file);
                if ($time !== false && ($oldestTime === null || $time < $oldestTime)) {
                    $oldestTime = $time;
                }
            }
        }

        return $oldestTime;
    }
}

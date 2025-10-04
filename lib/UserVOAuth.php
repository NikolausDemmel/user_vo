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
            $this->storeUser($uid);
            // return $response[0];
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
}

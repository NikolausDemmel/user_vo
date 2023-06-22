<?php

declare(strict_types=1);

namespace OCA\UserVO;

use OCA\UserVO\Base;

class UserVOAuth extends Base {
    private $apiUrl;
    private $username;
    private $password;

    public function __construct($apiUrl, $username, $password) {
        parent::__construct('user_vo');
        $this->apiUrl = $apiUrl;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Check if the provided credentials are valid and authenticate the user.
     *
     * @param string $uid      The username
     * @param string $password The password
     *
     * @return bool|string The authenticated user's ID if successful, otherwise false
     */
    public function checkPassword($uid, $password) {
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
            \OC::$server->getLogger()->error('API request failed', ['app' => 'user_vo']);
            return false;
        } elseif (is_array($response) && isset($response[0]) && $response[0] !== '') {
            $this->storeUser($uid);
            // return $response[0];
            return $uid;
        } elseif (is_array($response) && isset($response['error'])) {
            $errorMessage = $response['error'];
            \OC::$server->getLogger()->error('User authentication error: ' . $errorMessage, ['app' => 'user_vo']);
            return false;
        } else {
            \OC::$server->getLogger()->error('Invalid API response: ' . json_encode($response), ['app' => 'user_vo']);
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
    
        // Enable verbose output for debugging
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        $verboseOutput = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verboseOutput);
    
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
    
        curl_close($curl);

        // rewind($verboseOutput);
        // $verboseInfo = stream_get_contents($verboseOutput);
        // fclose($verboseOutput);
    
        // \OC::$server->getLogger()->warning('API request:', [
        //     'url' => $url,
        //     'data' => $data,
        //     'token' => $token,
        //     'response' => $response,
        //     'httpCode' => $httpCode,
        //     'error' => $error,
        //     'verboseInfo' => $verboseInfo,
        //     'app' => 'user_vo',
        // ]);
    
        if ($response === false) {
            \OC::$server->getLogger()->error('API request failed: ' . $error, ['app' => 'user_vo']);
            return null;
        }
    
        if ($httpCode !== 200) {
            \OC::$server->getLogger()->error('API request returned non-200 status code: ' . $httpCode, ['app' => 'user_vo']);
            return null;
        }
    
        return json_decode($response, true);
    }
}

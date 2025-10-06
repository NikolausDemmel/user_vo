<?php
/**
 * @author Nikolaus Demmel <nikolaus@nikolaus-demmel.de>
 * @copyright (c) 2025 Nikolaus Demmel <nikolaus@nikolaus-demmel.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the LICENSE file.
 */

declare(strict_types=1);

namespace OCA\UserVO\Cron;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use function OCP\Log\logger;
use OCA\UserVO\Service\ConfigService;
use OCA\UserVO\UserVOAuth;

class SyncUsersJob extends TimedJob {
    private IConfig $config;
    private IDBConnection $connection;
    private ConfigService $configService;

    public function __construct(ITimeFactory $time, IConfig $config, IDBConnection $connection, ConfigService $configService) {
        parent::__construct($time);
        $this->config = $config;
        $this->connection = $connection;
        $this->configService = $configService;

        // Run once per day (24 hours)
        $this->setInterval(24 * 60 * 60);
    }

    protected function run($argument): void {
        // Check if nightly sync is enabled
        $enabled = $this->config->getAppValue('user_vo', 'enable_nightly_sync', 'false') === 'true';

        if (!$enabled) {
            logger('user_vo')->debug('Nightly sync is disabled, skipping');
            return;
        }

        logger('user_vo')->info('Starting nightly user sync');

        $startTime = time();
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;
        $error = null;

        try {
            // Get all users from user_vo table
            $qb = $this->connection->getQueryBuilder();
            $qb->select('uid', 'vo_user_id')
                ->from('user_vo')
                ->where($qb->expr()->eq('backend', $qb->createNamedParameter('user_vo')))
                ->orderBy('uid', 'ASC');
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

            // Use reflection to access protected methods
            $reflectionClass = new \ReflectionClass($auth);
            $fetchUserDataMethod = $reflectionClass->getMethod('fetchUserDataFromVO');
            $fetchUserDataMethod->setAccessible(true);
            $syncUserDataMethod = $reflectionClass->getMethod('syncUserData');
            $syncUserDataMethod->setAccessible(true);

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
                    $skippedCount++;
                    continue;
                }

                try {
                    // Fetch user data from VO
                    $voUserData = $fetchUserDataMethod->invoke($auth, $voUserId);

                    if ($voUserData === null) {
                        $failureCount++;
                        continue;
                    }

                    if (isset($voUserData['_error'])) {
                        $failureCount++;
                        continue;
                    }

                    // Sync user data
                    $success = $syncUserDataMethod->invoke($auth, $uid, $voUserData);

                    if ($success !== false) {
                        $successCount++;
                    } else {
                        $failureCount++;
                    }
                } catch (\Exception $e) {
                    logger('user_vo')->error('Error syncing user in nightly job', [
                        'uid' => $uid,
                        'error' => $e->getMessage()
                    ]);
                    $failureCount++;
                }
            }

            // Store success status
            $this->config->setAppValue('user_vo', 'nightly_sync_last_run', (string)$startTime);
            $this->config->setAppValue('user_vo', 'nightly_sync_last_status', 'success');
            $this->config->setAppValue('user_vo', 'nightly_sync_last_error', '');
            $this->config->setAppValue('user_vo', 'nightly_sync_last_summary', json_encode([
                'total' => count($users),
                'synced' => $successCount,
                'failed' => $failureCount,
                'skipped' => $skippedCount
            ]));

            logger('user_vo')->info('Nightly user sync completed', [
                'total' => count($users),
                'synced' => $successCount,
                'failed' => $failureCount,
                'skipped' => $skippedCount
            ]);

        } catch (\Exception $e) {
            // Store failure status
            $error = $e->getMessage();
            $this->config->setAppValue('user_vo', 'nightly_sync_last_run', (string)$startTime);
            $this->config->setAppValue('user_vo', 'nightly_sync_last_status', 'failed');
            $this->config->setAppValue('user_vo', 'nightly_sync_last_error', $error);
            $this->config->setAppValue('user_vo', 'nightly_sync_last_summary', json_encode([
                'total' => count($users ?? []),
                'synced' => $successCount,
                'failed' => $failureCount,
                'skipped' => $skippedCount
            ]));

            logger('user_vo')->error('Nightly user sync failed', [
                'error' => $error,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

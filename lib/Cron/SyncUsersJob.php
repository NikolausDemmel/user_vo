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
use function OCP\Log\logger;
use OCA\UserVO\Controller\AdminController;

class SyncUsersJob extends TimedJob {
    private IConfig $config;
    private AdminController $adminController;

    public function __construct(ITimeFactory $time, IConfig $config, AdminController $adminController) {
        parent::__construct($time);
        $this->config = $config;
        $this->adminController = $adminController;

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

        try {
            // Call syncFromVO and extract data from JSON response
            $response = $this->adminController->syncFromVO();
            $data = $response->getData();

            if (!$data['success']) {
                throw new \Exception($data['error'] ?? 'Sync failed');
            }

            // Convert 'success' key to 'synced' for consistency
            $summary = [
                'total' => $data['summary']['total'],
                'synced' => $data['summary']['success'],
                'failed' => $data['summary']['failed'],
                'skipped' => $data['summary']['skipped']
            ];

            // Store success status
            $this->config->setAppValue('user_vo', 'nightly_sync_last_run', (string)$startTime);
            $this->config->setAppValue('user_vo', 'nightly_sync_last_status', 'success');
            $this->config->setAppValue('user_vo', 'nightly_sync_last_error', '');
            $this->config->setAppValue('user_vo', 'nightly_sync_last_summary', json_encode($summary));

            logger('user_vo')->info('Nightly user sync completed', $summary);

        } catch (\Exception $e) {
            // Store failure status
            $error = $e->getMessage();
            $this->config->setAppValue('user_vo', 'nightly_sync_last_run', (string)$startTime);
            $this->config->setAppValue('user_vo', 'nightly_sync_last_status', 'failed');
            $this->config->setAppValue('user_vo', 'nightly_sync_last_error', $error);
            $this->config->setAppValue('user_vo', 'nightly_sync_last_summary', json_encode([
                'total' => 0,
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0
            ]));

            logger('user_vo')->error('Nightly user sync failed', [
                'error' => $error,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

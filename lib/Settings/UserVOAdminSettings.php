<?php
namespace OCA\UserVO\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\IConfig;
use OCA\UserVO\Service\ConfigService;

class UserVOAdminSettings implements ISettings {
    private IL10N $l;
    private ConfigService $configService;

    public function __construct(IL10N $l, ConfigService $configService) {
        $this->l = $l;
        $this->configService = $configService;
    }

    public function getForm() {
        // Get configuration status from the service
        $configStatus = $this->configService->getConfigurationStatus();

        // Get sync settings
        $syncSettings = [
            'sync_email' => $this->configService->get('sync_email', 'true'),
            'sync_photo' => $this->configService->get('sync_photo', 'false')
        ];

        return new TemplateResponse('user_vo', 'admin', [
            'config_status' => $configStatus,
            'sync_settings' => $syncSettings
        ], 'admin');
    }

    public function getSection() {
        return 'user_vo'; // Must match getID() from your section class
    }

    public function getPriority() {
        return 10;
    }
}

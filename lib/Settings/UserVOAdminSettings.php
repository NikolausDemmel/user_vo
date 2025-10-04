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

        return new TemplateResponse('user_vo', 'admin', [
            'config_status' => $configStatus
        ], 'admin');
    }

    public function getSection() {
        return 'user_vo'; // Must match getID() from your section class
    }

    public function getPriority() {
        return 10;
    }
}

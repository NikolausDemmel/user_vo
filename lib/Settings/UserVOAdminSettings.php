<?php
namespace OCA\UserVO\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\Settings\ISettings;

class UserVOAdminSettings implements ISettings {
    private IL10N $l;

    public function __construct(IL10N $l) {
        $this->l = $l;
    }

    public function getForm() {
        return new TemplateResponse('user_vo', 'admin', [], 'admin');
    }

    public function getSection() {
        return 'user_vo'; // Must match getID() from your section class
    }

    public function getPriority() {
        return 10;
    }
} 

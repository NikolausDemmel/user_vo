<?php
namespace OCA\UserVO\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class UserVOAdminSection implements IIconSection {
    private IL10N $l;
    private IURLGenerator $urlGenerator;

    public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }

    public function getIcon(): string {
        // Use the default Nextcloud settings icon for now
        return $this->urlGenerator->imagePath('core', 'actions/settings-dark.svg');
    }

    public function getID(): string {
        return 'user_vo';
    }

    public function getName(): string {
        return $this->l->t('User VO');
    }

    public function getPriority(): int {
        return 98;
    }
} 

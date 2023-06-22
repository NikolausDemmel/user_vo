<?php

declare(strict_types=1);

namespace OCA\UserVO\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public function __construct() {
        parent::__construct('user_vo');
    }

    public function register(IRegistrationContext $context): void {
        //$context->registerBackendProvider(UserVOAuth::class);
    }

    public function boot(IBootContext $context): void {
    }
}
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Setting;

class AboutController
{
    public function index(): void
    {
        $settings = [];
        $dbConfigFile = __DIR__ . '/../../config/db_config.php';
        if (file_exists($dbConfigFile)) {
            try {
                $settings = (new Setting())->getAll();
            } catch (\Throwable $e) {
                $settings = [];
            }
        }

        $isLoggedIn = Security::isLoggedIn();
        require __DIR__ . '/../../views/about/index.php';
    }
}

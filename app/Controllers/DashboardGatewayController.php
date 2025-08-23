<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;

final class DashboardGatewayController extends BaseController
{
    public function route(): void
    {
        if (!Security::check()) {
            header('Location: /login?redirect=/dashboard');
            exit;
        }

        switch (Security::role()) {
            case 'ADMIN':
                header('Location: /admin/dashboard');
                exit;
            case 'EMPLOYEE':
                header('Location: /employee/dashboard');
                exit;
            case 'USER':
                header('Location: /user/dashboard');
                exit;
            default:
                header('Location: /login?redirect=/dashboard');
                exit;
        }
    }
}

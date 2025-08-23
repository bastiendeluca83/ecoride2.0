<?php
declare(strict_types=1);

namespace App\Security;

final class Security
{
    public static function check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }

    public static function role(): string
    {
        return $_SESSION['user']['role'] ?? 'GUEST';
    }

    public static function ensure(array $roles): void
    {
        if (!self::check()) {
            // Redirige vers login en conservant la destination
            $target = $_SERVER['REQUEST_URI'] ?? '/dashboard';
            header('Location: /login?redirect=' . rawurlencode($target));
            exit;
        }
        if (!in_array(self::role(), $roles, true)) {
            self::redirectByRole();
        }
    }

    public static function redirectByRole(): void
    {
        $r = self::role();
        if ($r === 'ADMIN')    { header('Location: /admin/dashboard');    exit; }
        if ($r === 'EMPLOYEE') { header('Location: /employee/dashboard'); exit; }
        if ($r === 'USER')     { header('Location: /user/dashboard');     exit; }

        header('Location: /login'); // GUEST
        exit;
    }
}

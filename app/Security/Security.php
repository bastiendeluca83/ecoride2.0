<?php
declare(strict_types=1);

namespace App\Security;

final class Security
{
    /* ========= AUTH ========= */

    /** Utilisateur connecté ? */
    public static function check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }

    /** Rôle courant (sinon GUEST) */
    public static function role(): string
    {
        $r = $_SESSION['user']['role'] ?? 'GUEST';
        return is_string($r) ? strtoupper($r) : 'GUEST';
    }

    /**
     * Empêche l'accès si non connecté ou rôle non autorisé.
     * Redirige vers /login si invité, sinon vers l'espace correspondant.
     */
    public static function ensure(array $roles): void
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        if (!self::check()) {
            $target = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login?redirect=' . rawurlencode($target));
            exit;
        }
        $r = self::role();
        if (!in_array($r, $roles, true)) {
            self::redirectByRole();
        }
    }

    /** Redirige l'utilisateur vers son espace selon son rôle. */
    public static function redirectByRole(): void
    {
        $r = self::role();
        if ($r === 'ADMIN')    { header('Location: /admin/dashboard');    exit; }
        if ($r === 'EMPLOYEE') { header('Location: /employee');           exit; }
        if ($r === 'USER')     { header('Location: /user/dashboard');     exit; }
        header('Location: /'); exit;
    }

    /* ========= CSRF ========= */

    /** Retourne le token CSRF (crée si absent) */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** <input type="hidden" ...> prêt à injecter dans les formulaires */
    public static function csrfField(): string
    {
        $t = self::csrfToken();
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Vérifie le token CSRF soumis */
    public static function checkCsrf(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $sessionToken = (string)($_SESSION['csrf'] ?? '');
        $submitted    = (string)($token ?? '');
        return $sessionToken !== '' && $submitted !== '' && hash_equals($sessionToken, $submitted);
    }

    /** Régénère un token CSRF (utile après login/logout) */
    public static function regenCsrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}

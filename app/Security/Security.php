<?php
declare(strict_types=1);

namespace App\Security;

final class Security
{
    /*  AUTH  */

    public static function check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }

    public static function role(): string
    {
        $r = $_SESSION['user']['role'] ?? 'GUEST';
        return is_string($r) ? strtoupper($r) : 'GUEST';
    }

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

    public static function redirectByRole(): void
    {
        $r = self::role();
        if ($r === 'ADMIN')    { header('Location: /admin/dashboard');    exit; }
        if ($r === 'EMPLOYEE') { header('Location: /employee');           exit; }
        if ($r === 'USER')     { header('Location: /user/dashboard');     exit; }
        header('Location: /'); exit;
    }

    /*  CSRF */

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        $t = self::csrfToken();
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function checkCsrf(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $sessionToken = (string)($_SESSION['csrf'] ?? '');
        $submitted    = (string)($token ?? '');
        return $sessionToken !== '' && $submitted !== '' && hash_equals($sessionToken, $submitted);
    }

    public static function regenCsrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    /* Tokens d'avis (signature)  */

    private static function appKey(): string
    {
        $k = (string)(getenv('APP_KEY') ?: '');
        if ($k === '') {
            // Fallback dev (à éviter en prod)
            $k = 'INSECURE-DEV-KEY-change-me';
        }
        return $k;
    }

    /*
     * Crée un token signé pour envoyer un lien d’avis.
     * Format: base64url(json) + '.' + hmac
     */
    public static function signReviewToken(int $rideId, int $passengerId, int $expiresTs): string
    {
        $payload = [
            'rid' => $rideId,
            'pid' => $passengerId,
            'exp' => $expiresTs,
            'tid' => bin2hex(random_bytes(8)), // id du token pour audit
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $b64  = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $mac  = hash_hmac('sha256', $b64, self::appKey(), true);
        $sig  = rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
        return $b64 . '.' . $sig;
    }

    /**
     * Génère un token d'avis à partir d'un TTL lisible (ex: '+7 days', '+2 hours').
     * Je l'utilise côté service d'invitations pour éviter de calculer l'expiration à la main.
     */
    public static function issueReviewToken(int $rideId, int $passengerId, string $ttl = '+7 days'): string
    {
        $exp = (new \DateTimeImmutable($ttl))->getTimestamp();
        return self::signReviewToken($rideId, $passengerId, $exp);
    }

    /* Vérifie le token et retourne le payload (ou null) */
    public static function verifyReviewToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;
        [$b64, $sig] = $parts;

        $calc = rtrim(strtr(base64_encode(hash_hmac('sha256', $b64, self::appKey(), true)), '+/', '-_'), '=');
        if (!hash_equals($calc, $sig)) return null;

        $json = base64_decode(strtr($b64, '-_', '+/'));
        $data = json_decode((string)$json, true);
        if (!$data || !isset($data['rid'],$data['pid'],$data['exp'])) return null;
        if ((int)$data['exp'] < time()) return null;
        return $data;
    }
}

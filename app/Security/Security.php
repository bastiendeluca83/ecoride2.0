<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Classe Security
 * ---------------
 * Cette classe gère :
 * - l'authentification et les rôles des utilisateurs
 * - la sécurité CSRF
 * - la génération et vérification de tokens signés (ex: pour les avis).
 * 
 * Elle est utilisée un peu partout dans l'application pour contrôler l'accès
 * aux pages sensibles et sécuriser les formulaires.
 */
final class Security
{
    /* -------------------- PARTIE AUTHENTIFICATION -------------------- */

    /** 
     * Vérifie si un utilisateur est connecté
     * -> On teste simplement si la session contient un id d'utilisateur
     */
    public static function check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }

    /**
     * Retourne le rôle actuel de l'utilisateur connecté
     * -> Si aucun rôle trouvé, on retourne "GUEST" (visiteur).
     */
    public static function role(): string
    {
        $r = $_SESSION['user']['role'] ?? 'GUEST';
        return is_string($r) ? strtoupper($r) : 'GUEST';
    }

    /**
     * Vérifie si l'utilisateur possède l'un des rôles donnés
     * - Si pas connecté => redirection vers login
     * - Si connecté mais rôle incorrect => redirection selon son rôle
     */
    public static function ensure(array $roles): void
    {
        // Toujours démarrer la session si ce n'est pas déjà fait
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        // Vérifie la connexion
        if (!self::check()) {
            $target = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login?redirect=' . rawurlencode($target));
            exit;
        }

        // Vérifie le rôle
        $r = self::role();
        if (!in_array($r, $roles, true)) {
            self::redirectByRole();
        }
    }

    /**
     * Redirige un utilisateur selon son rôle
     * - ADMIN -> /admin/dashboard
     * - EMPLOYEE -> /employee
     * - USER -> /user/dashboard
     * - Sinon retour vers /
     */
    public static function redirectByRole(): void
    {
        $r = self::role();
        if ($r === 'ADMIN')    { header('Location: /admin/dashboard');    exit; }
        if ($r === 'EMPLOYEE') { header('Location: /employee');           exit; }
        if ($r === 'USER')     { header('Location: /user/dashboard');     exit; }
        header('Location: /'); exit;
    }

    /* -------------------- PARTIE CSRF -------------------- */

    /**
     * Génère ou retourne le token CSRF stocké en session
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
        }
        return $_SESSION['csrf'];
    }

    /**
     * Retourne directement le champ HTML caché contenant le token CSRF
     * -> pratique à insérer dans les <form>
     */
    public static function csrfField(): string
    {
        $t = self::csrfToken();
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Vérifie si le token CSRF soumis correspond à celui en session
     */
    public static function checkCsrf(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $sessionToken = (string)($_SESSION['csrf'] ?? '');
        $submitted    = (string)($token ?? '');
        return $sessionToken !== '' && $submitted !== '' && hash_equals($sessionToken, $submitted);
    }

    /**
     * Force la régénération d’un nouveau token CSRF
     * -> Utile après une action critique (ex: changement de mot de passe)
     */
    public static function regenCsrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    /* -------------------- PARTIE TOKENS POUR AVIS -------------------- */

    /**
     * Retourne la clé secrète utilisée pour signer les tokens
     * -> Cherche d'abord dans APP_KEY (variable d'environnement)
     * -> Sinon utilise une clé de secours (pas sécurisée en prod).
     */
    private static function appKey(): string
    {
        $k = (string)(getenv('APP_KEY') ?: '');
        if ($k === '') {
            // ATTENTION : clé par défaut pour le dev, à changer absolument en prod
            $k = 'INSECURE-DEV-KEY-change-me';
        }
        return $k;
    }

    /**
     * Crée un token signé pour envoyer un lien d’avis
     * Format : base64url(json du payload) + '.' + signature HMAC
     */
    public static function signReviewToken(int $rideId, int $passengerId, int $expiresTs): string
    {
        // Les infos stockées dans le token
        $payload = [
            'rid' => $rideId,       // id du trajet
            'pid' => $passengerId,  // id du passager
            'exp' => $expiresTs,    // expiration en timestamp
            'tid' => bin2hex(random_bytes(8)), // identifiant unique du token
        ];

        // Encodage du payload en JSON + base64url
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $b64  = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        // Calcul signature HMAC avec clé secrète
        $mac  = hash_hmac('sha256', $b64, self::appKey(), true);
        $sig  = rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');

        return $b64 . '.' . $sig;
    }

    /**
     * Génère un token d'avis en précisant un TTL lisible
     * Exemple : '+7 days' ou '+2 hours'
     */
    public static function issueReviewToken(int $rideId, int $passengerId, string $ttl = '+7 days'): string
    {
        $exp = (new \DateTimeImmutable($ttl))->getTimestamp();
        return self::signReviewToken($rideId, $passengerId, $exp);
    }

    /**
     * Vérifie si un token d'avis est valide
     * - Vérifie la signature
     * - Vérifie l'expiration
     * - Retourne le contenu (payload) ou null si invalide
     */
    public static function verifyReviewToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;
        [$b64, $sig] = $parts;

        // Recalcule la signature et compare
        $calc = rtrim(strtr(base64_encode(hash_hmac('sha256', $b64, self::appKey(), true)), '+/', '-_'), '=');
        if (!hash_equals($calc, $sig)) return null;

        // Décodage du payload
        $json = base64_decode(strtr($b64, '-_', '+/'));
        $data = json_decode((string)$json, true);

        // Vérifie la présence des champs et la date d'expiration
        if (!$data || !isset($data['rid'],$data['pid'],$data['exp'])) return null;
        if ((int)$data['exp'] < time()) return null;

        return $data;
    }
}

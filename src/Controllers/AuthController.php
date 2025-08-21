<?php
namespace App\Controllers;

use App\Db\Sql;

class AuthController
{
    /** N’autorise que les chemins relatifs internes */
    private function safeRedirect(?string $url): string {
        if (!$url) return '/';
        if ($url[0] !== '/') return '/';
        if (strpos($url, '//') === 0) return '/';
        return $url;
    }

    /** Retour par défaut : champ hidden > HTTP_REFERER > / */
    private function currentRedirect(): string {
        $post = $_POST['redirect'] ?? null;
        if (is_string($post) && $post !== '') return $this->safeRedirect($post);

        $ref  = $_SERVER['HTTP_REFERER'] ?? '/';
        $p    = parse_url($ref);
        $path = $p['path'] ?? '/';
        if (!empty($p['query'])) $path .= '?' . $p['query'];
        return $this->safeRedirect($path);
    }

    /** Force un retour PUBLIC si la page référente est protégée */
    private function publicRedirect(string $url): string {
        $onlyPath = parse_url($url, PHP_URL_PATH) ?? '/';
        foreach (['/admin','/employee','/dashboard'] as $locked) {
            if ($onlyPath === $locked || str_starts_with($onlyPath, $locked.'/')) {
                return '/'; // bascule vers home si la page n’est plus accessible
            }
        }
        return $url;
    }

    /* ---------- Forms fallback (si tu n’utilises pas le modal) ---------- */
    public function loginForm() { ob_start(); ?>
        <h1>Connexion</h1>
        <form method="post" action="/login">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    <?php return ob_get_clean(); }

    public function signupForm() { ob_start(); ?>
        <h1>Créer un compte</h1>
        <form method="post" action="/signup">
            <input name="pseudo" placeholder="Pseudo" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Créer</button>
        </form>
    <?php return ob_get_clean(); }

    /* ------------------------------- ACTIONS ------------------------------- */

    public function signup() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $pdo = Sql::pdo();
        $initialCredits = 20;

        $stmt = $pdo->prepare("
            INSERT INTO users(pseudo, email, password_hash, role, credits, is_suspended)
            VALUES(?, ?, ?, 'USER', ?, 0)
        ");
        $stmt->execute([
            $_POST['pseudo'],
            $_POST['email'],
            password_hash($_POST['password'], PASSWORD_BCRYPT),
            $initialCredits
        ]);

        $id = (int)$pdo->lastInsertId();
        $_SESSION['user'] = [
            'id'           => $id,
            'role'         => 'USER',
            'pseudo'       => $_POST['pseudo'],
            'avatar_url'   => null,
            'is_suspended' => 0,
            'credits'      => $initialCredits,
        ];

        header('Location: ' . $this->publicRedirect($this->currentRedirect()));
        exit;
    }

    public function login() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $pdo = Sql::pdo();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['password'], $user['password_hash'])) {
            if ((int)$user['is_suspended'] === 1) { http_response_code(403); echo "<p>Compte suspendu.</p>"; return; }

            $_SESSION['user'] = [
                'id'           => (int)$user['id'],
                'role'         => $user['role'],
                'pseudo'       => $user['pseudo'],
                'avatar_url'   => $user['avatar_url'] ?? null,
                'is_suspended' => (int)$user['is_suspended'],
                'credits'      => (int)$user['credits'],
            ];

            header('Location: ' . $this->publicRedirect($this->currentRedirect()));
            exit;
        }

        http_response_code(401);
        echo "<p>Identifiants invalides.</p>";
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Vérif CSRF si on l’envoie (on accepte aussi GET sans token pour compat)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
            if (!$ok) { header('Location: /?error=csrf'); exit; }
        }

        $redirect = $this->publicRedirect($this->currentRedirect());

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        header('Location: ' . $redirect);
        exit;
    }
}

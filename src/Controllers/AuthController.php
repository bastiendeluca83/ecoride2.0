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
            <input type="text" name="email" placeholder="Email ou nom" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    <?php return ob_get_clean(); }

    public function signupForm() { ob_start(); ?>
        <h1>Créer un compte</h1>
        <form method="post" action="/signup">
            <input name="nom" placeholder="Nom" required>
            <input name="prenom" placeholder="Prénom">
            <input name="adresse" placeholder="Adresse">
            <input name="telephone" placeholder="Téléphone">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required>
            <button type="submit">Créer</button>
        </form>
    <?php return ob_get_clean(); }

    /* ------------------------------- ACTIONS ------------------------------- */

    public function signup() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // CSRF soft si fourni
        if (isset($_POST['csrf'], $_SESSION['csrf']) && !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            http_response_code(400);
            echo "<p>Session expirée. Merci de réessayer.</p>";
            return;
        }

        $pdo = Sql::pdo();
        $initialCredits = 20;

        // Récup champs
        $nom       = trim($_POST['nom']      ?? '');
        $prenom    = trim($_POST['prenom']   ?? '');
        $adresse   = trim($_POST['adresse']  ?? '');
        $telephone = trim($_POST['telephone']?? '');
        $email     = trim($_POST['email']    ?? '');
        $pass      = $_POST['password']         ?? '';
        $pass2     = $_POST['password_confirm'] ?? '';

        // Validations minimales
        if ($nom === '' || $email === '' || $pass === '' || $pass2 === '') {
            http_response_code(400); echo "<p>Champs requis manquants.</p>"; return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400); echo "<p>Email invalide.</p>"; return;
        }
        if (strlen($pass) < 8) {
            http_response_code(400); echo "<p>Mot de passe trop court (min 8).</p>"; return;
        }
        if ($pass !== $pass2) {
            http_response_code(400); echo "<p>Les mots de passe ne correspondent pas.</p>"; return;
        }
        if ($telephone !== '' && !preg_match('/^[0-9 ]+$/', $telephone)) {
            http_response_code(400); echo "<p>Téléphone invalide (chiffres et espaces seulement).</p>"; return;
        }
        $tel = ($telephone === '') ? null : $telephone;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (nom, prenom, adresse, telephone, email, password_hash, role, credits, is_suspended)
                VALUES (?, ?, ?, ?, ?, ?, 'USER', ?, 0)
            ");
            $stmt->execute([
                $nom, $prenom, $adresse, $tel, $email,
                password_hash($pass, PASSWORD_BCRYPT),
                $initialCredits
            ]);
        } catch (\PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                if (str_contains($e->getMessage(), 'email')) {
                    http_response_code(409); echo "<p>Un compte existe déjà avec cet email.</p>"; return;
                }
                if (str_contains($e->getMessage(), 'telephone')) {
                    http_response_code(409); echo "<p>Ce téléphone est déjà utilisé.</p>"; return;
                }
            }
            throw $e;
        }

        $id = (int)$pdo->lastInsertId();
        $_SESSION['user'] = [
            'id'           => $id,
            'role'         => 'USER',
            'nom'          => $nom,
            'prenom'       => $prenom,
            'adresse'      => $adresse,
            'telephone'    => $tel,
            'email'        => $email,
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

        $identifier = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';

        if ($identifier === '' || $password === '') {
            http_response_code(400);
            echo "<p>Veuillez remplir tous les champs.</p>";
            return;
        }

        // Email OU nom
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR nom = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ((int)$user['is_suspended'] === 1) { http_response_code(403); echo "<p>Compte suspendu.</p>"; return; }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'           => (int)$user['id'],
                'role'         => $user['role'],
                'nom'          => $user['nom'],
                'prenom'       => $user['prenom'] ?? '',
                'adresse'      => $user['adresse'] ?? '',
                'telephone'    => $user['telephone'] ?? null,
                'email'        => $user['email'] ?? '',
                'avatar_url'   => $user['avatar_url'] ?? null,
                'is_suspended' => (int)$user['is_suspended'],
                'credits'      => (int)$user['credits'],
            ];

            // Redirection "safe" + fallback par rôle si besoin
            $dest = $this->publicRedirect($this->currentRedirect());
            $onlyPath = parse_url($dest, PHP_URL_PATH) ?? '/';
            if ($dest === '/' || $onlyPath === '/login' || $onlyPath === '/signup') {
                if ($user['role'] === 'ADMIN') {
                    $dest = '/admin';
                } elseif ($user['role'] === 'EMPLOYEE' || $user['role'] === 'EMPLOYE') {
                    $dest = '/employee';
                } else {
                    $dest = '/dashboard';
                }
            }

            header('Location: ' . $dest);
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

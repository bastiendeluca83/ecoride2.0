<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Services\Mailer;          // ajout : envoi des mails
use App\Security\Security;        // ajout : signature/lecture du token

class AuthController extends BaseController
{
    /** N’autorise que les chemins relatifs internes */
    private function safeRedirect(?string $url): string {
        if (!is_string($url) || $url === '') return '/dashboard';
        if ($url[0] !== '/') return '/dashboard';
        if (strpos($url, '//') === 0) return '/dashboard';
        return $url;
    }

    /** redirect param (POST/GET) > HTTP_REFERER > /dashboard */
    private function currentRedirect(): string {
        $param = $_POST['redirect'] ?? $_GET['redirect'] ?? null;
        if (is_string($param) && $param !== '') return $this->safeRedirect($param);

        $ref  = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '') {
            $p    = parse_url($ref);
            $path = $p['path'] ?? '/dashboard';
            if (!empty($p['query'])) $path .= '?' . $p['query'];
            return $this->safeRedirect($path);
        }
        return '/dashboard';
    }

    /** Force un retour PUBLIC si la page référente est protégée */
    private function publicRedirect(string $url): string {
        $onlyPath = parse_url($url, PHP_URL_PATH) ?? '/';
        foreach (['/admin','/employee','/user','/dashboard'] as $locked) {
            if ($onlyPath === $locked || str_starts_with($onlyPath, $locked.'/')) {
                return '/';
            }
        }
        return $url;
    }

    /* -------------------------- FORMS -------------------------- */

    public function loginForm(): void {
        $this->render('auth/login', ['title' => 'Connexion – EcoRide']);
    }

    public function signupForm(): void {
        $this->render('auth/signup', ['title' => 'Créer un compte – EcoRide']);
    }

    /* ------------------------------- ACTIONS ------------------------------- */

    public function signup(): void {
        if (session_status() === \PHP_SESSION_NONE) session_start();

        $pdo = Sql::pdo();
        $initialCredits = 20;

        $nom       = trim($_POST['nom']      ?? '');
        $prenom    = trim($_POST['prenom']   ?? '');
        $adresse   = trim($_POST['adresse']  ?? '');
        $telephone = trim($_POST['telephone']?? '');
        $email     = trim($_POST['email']    ?? '');
        $pass      = $_POST['password']         ?? '';
        $pass2     = $_POST['password_confirm'] ?? '';

        if ($nom === '' || $email === '' || $pass === '' || $pass2 === '') {
            $this->render('auth/signup', ['title'=>'Créer un compte – EcoRide', 'error'=>'Champs requis manquants.']); return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/signup', ['title'=>'Créer un compte – EcoRide', 'error'=>'Email invalide.']); return;
        }
        if (strlen($pass) < 8) {
            $this->render('auth/signup', ['title'=>'Créer un compte – EcoRide', 'error'=>'Mot de passe trop court (min 8).']); return;
        }
        if ($pass !== $pass2) {
            $this->render('auth/signup', ['title'=>'Créer un compte – EcoRide', 'error'=>'Les mots de passe ne correspondent pas.']); return;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, adresse, telephone, email, password_hash, role, credits, is_suspended)
                                   VALUES (?, ?, ?, ?, ?, ?, 'USER', ?, 0)");
            $stmt->execute([$nom,$prenom,$adresse,$telephone?:null,$email,password_hash($pass,PASSWORD_BCRYPT),$initialCredits]);
            $uid = (int)$pdo->lastInsertId();
        } catch (\PDOException $e) {
            $msg = "Erreur : ".$e->getMessage();
            $this->render('auth/signup',['title'=>'Créer un compte – EcoRide','error'=>$msg]); return;
        }

        // Session utilisateur
        $_SESSION['user'] = [
            'id'=>$uid,'role'=>'USER','nom'=>$nom,'prenom'=>$prenom,'adresse'=>$adresse,
            'telephone'=>$telephone?:null,'email'=>$email,'credits'=>$initialCredits,'is_suspended'=>0
        ];

        // Envoi des mails (bienvenue + vérification)
        try {
            $user = [
                'id' => $uid,
                'email' => $email,
                'pseudo' => $prenom ?: $nom,
                'nom' => $nom,
            ];
            $base = getenv('BASE_URL') ?: 'http://localhost:8080';

            // Lien de vérif : je réutilise Security::signReviewToken en mettant uid comme rid/pid
            $exp   = time() + 86400*7; // 7 jours
            $token = Security::signReviewToken($uid, $uid, $exp);
            $link  = rtrim($base,'/') . '/verify-email?token=' . $token;

            $mailer = new Mailer();
            $mailer->sendWelcome($user);
            $mailer->sendVerifyEmail($user, $link);
        } catch (\Throwable $e) {
            // Je ne bloque pas l'inscription si l'envoi échoue
            error_log('[signup mail] ' . $e->getMessage());
        }

        // Redirection classique (tu peux switch sur /login si tu veux forcer la vérif avant usage)
        header('Location: /dashboard'); exit;
    }

    public function login(): void {
        if (session_status() === \PHP_SESSION_NONE) session_start();
        $pdo = Sql::pdo();

        $identifier = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        if ($identifier===''||$password==='') {
            $this->render('auth/login',['title'=>'Connexion – EcoRide','error'=>'Veuillez remplir tous les champs.']); return;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR nom = ? LIMIT 1");
        $stmt->execute([$identifier,$identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ((int)$user['is_suspended']===1) {
                $this->render('auth/login',['title'=>'Connexion – EcoRide','error'=>'Compte suspendu.']); return;
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'=>(int)$user['id'],'role'=>$user['role'],'nom'=>$user['nom'],'prenom'=>$user['prenom']??'',
                'adresse'=>$user['adresse']??'','telephone'=>$user['telephone']??null,'email'=>$user['email']??'',
                'credits'=>(int)$user['credits'],'is_suspended'=>(int)$user['is_suspended']
            ];

            header('Location: /dashboard'); exit;
        }
        $this->render('auth/login',['title'=>'Connexion – EcoRide','error'=>'Identifiants invalides.']);
    }

    public function logout(): void {
        if (session_status() === \PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start(); session_regenerate_id(true);

        header('Location: /?logged_out=1'); exit;
    }

    /* ----------------------------- Vérification e-mail ----------------------------- */

    public function verifyEmail(): void
    {
        if (session_status() === \PHP_SESSION_NONE) session_start();
        $pdo = Sql::pdo();

        $token = (string)($_GET['token'] ?? '');
        $claims = $token ? Security::verifyReviewToken($token) : null; // je réutilise le verify existant
        if (!$claims || !isset($claims['rid'], $claims['pid']) || (int)$claims['rid'] !== (int)$claims['pid']) {
            $_SESSION['flash_error'] = 'Lien de vérification invalide ou expiré.';
            header('Location: /login'); exit;
        }

        $uid = (int)$claims['rid'];

        try {
            // OK si la colonne existe. Sinon, voir ALTER TABLE dans la note.
            $st = $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ? AND (email_verified_at IS NULL OR email_verified_at = '0000-00-00 00:00:00')");
            $st->execute([$uid]);
            $_SESSION['flash_success'] = $st->rowCount() > 0
                ? 'Adresse e-mail vérifiée. Vous pouvez utiliser toutes les fonctionnalités.'
                : 'Adresse déjà vérifiée.';
        } catch (\PDOException $e) {
            // Si la colonne manque, je ne casse pas l UX
            error_log('[verify-email] ' . $e->getMessage());
            $_SESSION['flash_error'] = "La vérification n'a pas pu être appliquée (contacte l'admin).";
        }

        header('Location: /login'); exit;
    }
}

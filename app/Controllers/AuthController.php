<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Services\Mailer;          // J'utilise mon service maison pour l'envoi d'emails
use App\Security\Security;        // J'utilise mes helpers de sécurité (signature/lecture token)

/**
 * Contrôleur d'authentification.
 * Rôle : afficher les formulaires (login/signup), gérer l'inscription/connexion,
 *        la déconnexion, et la vérification d'email.
 * NB : aucune vue HTML ici, je reste dans l'orchestration (MVC).
 */
class AuthController extends BaseController
{
    /** 
     * Petite barrière de sécurité : je n'autorise que les redirections internes (chemins relatifs).
     * - Si l'URL est vide, ou commence par autre chose que '/', ou est un protocole caché ('//'), je renvoie /dashboard.
     */
    private function safeRedirect(?string $url): string {
        if (!is_string($url) || $url === '') return '/dashboard';
        if ($url[0] !== '/') return '/dashboard';
        if (strpos($url, '//') === 0) return '/dashboard';
        return $url;
    }

    /**
     * Détermine la redirection courante après action :
     * - Priorité au paramètre 'redirect' (POST/GET)
     * - Sinon, je tente HTTP_REFERER (et je ne garde que le path + query)
     * - Fallback : /dashboard
     */
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

    /**
     * Variante pour forcer un retour public (utile après logout par ex.) :
     * - Si l'URL pointe vers une zone protégée, je redirige vers '/'.
     */
    private function publicRedirect(string $url): string {
        $onlyPath = parse_url($url, PHP_URL_PATH) ?? '/';
        foreach (['/admin','/employee','/user','/dashboard'] as $locked) {
            if ($onlyPath === $locked || str_starts_with($onlyPath, $locked.'/')) {
                return '/';
            }
        }
        return $url;
    }

    /* -------------------------- FORMS (GET) -------------------------- */

    /** Affiche le formulaire de connexion */
    public function loginForm(): void {
        $this->render('auth/login', ['title' => 'Connexion – EcoRide']);
    }

    /** Affiche le formulaire de création de compte */
    public function signupForm(): void {
        $this->render('auth/signup', ['title' => 'Créer un compte – EcoRide']);
    }

    /* ------------------------------- ACTIONS (POST/GET) ------------------------------- */

    /**
     * Inscription utilisateur :
     * - Récupération/sanitation des champs
     * - Validations basiques (email, taille de mdp, confirmation)
     * - Insertion SQL (mot de passe hashé)
     * - Ouverture de session + crédits initiaux
     * - Envoi mails (bienvenue + lien de vérification)
     */
    public function signup(): void {
        if (session_status() === \PHP_SESSION_NONE) session_start();

        $pdo = Sql::pdo();
        $initialCredits = 20; // Bonus de bienvenue

        // Je récupère mes champs propres (trim où pertinent)
        $nom       = trim($_POST['nom']      ?? '');
        $prenom    = trim($_POST['prenom']   ?? '');
        $adresse   = trim($_POST['adresse']  ?? '');
        $telephone = trim($_POST['telephone']?? '');
        $email     = trim($_POST['email']    ?? '');
        $pass      = $_POST['password']         ?? '';
        $pass2     = $_POST['password_confirm'] ?? '';

        // Garde-fous de base (côté front je peux améliorer encore)
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

        // Insertion SQL : rôle USER par défaut, crédits initiaux, non suspendu
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (nom, prenom, adresse, telephone, email, password_hash, role, credits, is_suspended)
                 VALUES (?, ?, ?, ?, ?, ?, 'USER', ?, 0)"
            );
            $stmt->execute([
                $nom,
                $prenom,
                $adresse,
                $telephone ?: null,
                $email,
                password_hash($pass, PASSWORD_BCRYPT),
                $initialCredits
            ]);
            $uid = (int)$pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Ici je renvoie le message PDO : pour prod, je pourrais afficher un message plus générique
            $msg = "Erreur : ".$e->getMessage();
            $this->render('auth/signup',['title'=>'Créer un compte – EcoRide','error'=>$msg]); return;
        }

        // Je crée la session directement après l'inscription
        $_SESSION['user'] = [
            'id'            => $uid,
            'role'          => 'USER',
            'nom'           => $nom,
            'prenom'        => $prenom,
            'adresse'       => $adresse,
            'telephone'     => $telephone ?: null,
            'email'         => $email,
            'credits'       => $initialCredits,
            'is_suspended'  => 0
        ];

        // J'envoie mes e-mails (bienvenue + vérification)
        try {
            $user = [
                'id'     => $uid,
                'email'  => $email,
                'pseudo' => $prenom ?: $nom, // je choisis un affichage sympa
                'nom'    => $nom,
            ];
            $base = getenv('BASE_URL') ?: 'http://localhost:8080';

            // Je réutilise mon mécanisme de token "review" pour signer un lien de vérif
            $exp   = time() + 86400*7; // valide 7 jours
            $token = Security::signReviewToken($uid, $uid, $exp); // rid/pid = uid
            $link  = rtrim($base,'/') . '/verify-email?token=' . $token;

            $mailer = new Mailer();
            $mailer->sendWelcome($user);
            $mailer->sendVerifyEmail($user, $link);
        } catch (\Throwable $e) {
            // L'échec d'envoi ne doit pas bloquer l'inscription
            error_log('[signup mail] ' . $e->getMessage());
        }

        // Je redirige vers le dashboard (on peut forcer /login si on veut imposer la vérif email avant usage)
        header('Location: /dashboard'); exit;
    }

    /**
     * Connexion utilisateur :
     * - Auth par email OU nom (identifier)
     * - Vérification du hash
     * - Blocage si compte suspendu
     * - Regénération d'ID de session (sécurité)
     */
    public function login(): void {
        if (session_status() === \PHP_SESSION_NONE) session_start();
        $pdo = Sql::pdo();

        $identifier = trim($_POST['email'] ?? '');  // email ou nom
        $password   = $_POST['password'] ?? '';
        if ($identifier === '' || $password === '') {
            $this->render('auth/login',['title'=>'Connexion – EcoRide','error'=>'Veuillez remplir tous les champs.']); return;
        }

        // Je cherche par email OU nom (je garde LIMIT 1)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR nom = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        // Vérification du hash + statut de suspension
        if ($user && password_verify($password, $user['password_hash'])) {
            if ((int)$user['is_suspended'] === 1) {
                $this->render('auth/login',['title'=>'Connexion – EcoRide','error'=>'Compte suspendu.']); return;
            }

            // Je sécurise la session
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'           => (int)$user['id'],
                'role'         => $user['role'],
                'nom'          => $user['nom'],
                'prenom'       => $user['prenom']  ?? '',
                'adresse'      => $user['adresse'] ?? '',
                'telephone'    => $user['telephone'] ?? null,
                'email'        => $user['email']    ?? '',
                'credits'      => (int)$user['credits'],
                'is_suspended' => (int)$user['is_suspended']
            ];

            header('Location: /dashboard'); exit;
        }

        // Sinon → message générique (pour éviter l’énumération d’emails)
        $this->render('auth/login',['title'=>'Connexion – EcoRide','error'=>'Identifiants invalides.']);
    }

    /**
     * Déconnexion :
     * - Vidage de la session
     * - Invalidation du cookie de session si présent
     * - Destruction + regénération d’une session vierge (anti fixation)
     */
    public function logout(): void {
        if (session_status() === \PHP_SESSION_NONE) session_start();

        // Je purge toutes les données
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        // Je repars sur une session propre
        session_start();
        session_regenerate_id(true);

        // Retour à l'accueil public
        header('Location: /?logged_out=1'); exit;
    }

    /* ----------------------------- Vérification e-mail ----------------------------- */

    /**
     * Endpoint de vérification d'email : /verify-email?token=...
     * - Token signé via Security::signReviewToken (je réutilise mon système existant)
     * - J'accepte le token si rid == pid == uid
     * - Mise à jour de users.email_verified_at si besoin
     * - Flash messages via session + redirection vers /login
     */
    public function verifyEmail(): void
    {
        if (session_status() === \PHP_SESSION_NONE) session_start();
        $pdo = Sql::pdo();

        $token  = (string)($_GET['token'] ?? '');
        $claims = $token ? Security::verifyReviewToken($token) : null; // Vérif et décodage de mon token
        if (!$claims || !isset($claims['rid'], $claims['pid']) || (int)$claims['rid'] !== (int)$claims['pid']) {
            $_SESSION['flash_error'] = 'Lien de vérification invalide ou expiré.';
            header('Location: /login'); exit;
        }

        $uid = (int)$claims['rid'];

        try {
            // Je coche l'email comme vérifié si la colonne existe (sinon, je log et je n'interromps pas l'UX)
            $st = $pdo->prepare(
                "UPDATE users
                 SET email_verified_at = NOW()
                 WHERE id = ?
                   AND (email_verified_at IS NULL OR email_verified_at = '0000-00-00 00:00:00')"
            );
            $st->execute([$uid]);

            $_SESSION['flash_success'] = $st->rowCount() > 0
                ? 'Adresse e-mail vérifiée. Vous pouvez utiliser toutes les fonctionnalités.'
                : 'Adresse déjà vérifiée.';
        } catch (\PDOException $e) {
            // Si la colonne manque, je reste user-friendly et je notifie l'admin via logs
            error_log('[verify-email] ' . $e->getMessage());
            $_SESSION['flash_error'] = "La vérification n'a pas pu être appliquée (contacte l'admin).";
        }

        header('Location: /login'); exit;
    }
}

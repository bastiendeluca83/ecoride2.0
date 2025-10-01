<?php
namespace App\Controllers;

use App\Security\Security;
use App\Models\User;

/**
 * ProfileController
 * - Ce contrôleur ne gère que le profil de l'utilisateur connecté.
 * - Il permet d'afficher le formulaire d'édition (editForm)
 * - Il permet aussi de traiter la mise à jour (update)
 * - Je sécurise les accès avec Security::ensure et les validations nécessaires.
 */
class ProfileController
{
    /**
     * Affiche le formulaire d'édition du profil.
     * - Je protège l'accès (seulement connecté + rôles autorisés)
     * - Je récupère les infos utilisateur en BDD (via User::findById)
     * - J'inclus directement la vue dashboard/profile_edit.php
     * - J’utilise ob_start/ob_get_clean car ici je ne passe pas par render() comme ailleurs.
     */
    public function editForm(): string
    {
        // si pas connecté → redirection login
        if (!Security::check()) { header('Location: /login'); exit; }
        // je restreins aux rôles "USER", "EMPLOYEE", "ADMIN"
        Security::ensure(['USER','EMPLOYEE','ADMIN']);

        // je récupère l'utilisateur courant
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user   = $userId ? (User::findById($userId) ?? []) : [];

        /* Vue associée: app/Views/dashboard/profile_edit.php */
        ob_start();
        $title = 'Modifier mon profil';
        $user  = $user; // je rends dispo $user dans la vue
        include dirname(__DIR__,1).'/Views/dashboard/profile_edit.php';
        return ob_get_clean();
    }

    /**
     * Traite la mise à jour du profil.
     * - Je protège aussi l’accès avec Security::ensure
     * - Je fais une vérification CSRF
     * - Je nettoie et valide les entrées (pseudo, email, téléphone, bio, mot de passe)
     * - Je mets à jour l’utilisateur en base
     * - Je mets à jour la session pour refléter les changements
     * - Je redirige avec un flash message
     */
    public function update(): string
    {
        // sécurité standard
        if (!Security::check()) { header('Location: /login'); exit; }
        Security::ensure(['USER','EMPLOYEE','ADMIN']);

        // protection CSRF
        if (!Security::checkCsrf($_POST['csrf'] ?? '')) {
            http_response_code(400); return 'CSRF invalide';
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        /* Nettoyage des inputs */
        $pseudo = trim((string)($_POST['pseudo'] ?? ''));
        $email  = trim((string)($_POST['email']  ?? ''));
        $phone  = trim((string)($_POST['phone']  ?? ''));
        $bio    = trim((string)($_POST['bio']    ?? ''));

        /* Validations basiques */
        $errors = [];
        if ($pseudo === '' || mb_strlen($pseudo) < 2) $errors[] = 'Pseudo trop court.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if ($phone !== '' && !preg_match('/^[0-9 +().-]{6,20}$/', $phone)) $errors[] = 'Téléphone invalide.';

        /* Gestion du mot de passe (facultatif mais sécurisé) */
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($newPass !== '' || $confirm !== '') {
            if ($newPass !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';
            if (mb_strlen($newPass) < 8) $errors[] = 'Mot de passe trop court (min. 8).';
        }

        // si erreurs → je retourne à la page avec un flash_error
        if ($errors) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: /user/profile'); exit;
        }

        /* Mise à jour du profil */
        User::updateProfile($userId, [
            'nom'    => $pseudo,  // compat: la BDD stocke nom, mais je garde pseudo côté affichage
            'email'  => $email,
            'telephone' => $phone,
            'bio'    => $bio,
        ]);

        /* Mise à jour du mot de passe si fourni */
        if ($newPass !== '' && $newPass === $confirm) {
            User::updatePassword($userId, $newPass);
        }

        /* Je synchronise la session (pratique pour afficher direct les nouvelles infos dans l'entête) */
        $_SESSION['user']['pseudo'] = $pseudo;
        $_SESSION['user']['email']  = $email;
        $_SESSION['flash_success']  = 'Profil mis à jour.';

        // je redirige pour éviter un double POST
        header('Location: /user/profile'); exit;
    }
}

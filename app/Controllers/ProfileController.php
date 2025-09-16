<?php
namespace App\Controllers;

use App\Security\Security;
use App\Models\User;

class ProfileController
{
    public function editForm(): string
    {
        if (!Security::check()) { header('Location: /login'); exit; }
        Security::ensure(['USER','EMPLOYEE','ADMIN']);

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user   = $userId ? (User::findById($userId) ?? []) : [];

        /* Vue: app/Views/dashboard/profile_edit.php */
        ob_start();
        $title = 'Modifier mon profil';
        $user  = $user;
        include dirname(__DIR__,1).'/Views/dashboard/profile_edit.php';
        return ob_get_clean();
    }

    public function update(): string
    {
        if (!Security::check()) { header('Location: /login'); exit; }
        Security::ensure(['USER','EMPLOYEE','ADMIN']);

        if (!Security::checkCsrf($_POST['csrf'] ?? '')) {
            http_response_code(400); return 'CSRF invalide';
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        /* Sanitize */
        $pseudo = trim((string)($_POST['pseudo'] ?? ''));
        $email  = trim((string)($_POST['email']  ?? ''));
        $phone  = trim((string)($_POST['phone']  ?? ''));
        $bio    = trim((string)($_POST['bio']    ?? ''));

        /* Validations simples */
        $errors = [];
        if ($pseudo === '' || mb_strlen($pseudo) < 2) $errors[] = 'Pseudo trop court.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if ($phone !== '' && !preg_match('/^[0-9 +().-]{6,20}$/', $phone)) $errors[] = 'Téléphone invalide.';

        /* Password */
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($newPass !== '' || $confirm !== '') {
            if ($newPass !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';
            if (mb_strlen($newPass) < 8) $errors[] = 'Mot de passe trop court (min. 8).';
        }

        if ($errors) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: /user/profile'); exit;
        }

        /* Update profil */
        User::updateProfile($userId, [
            'nom'    => $pseudo,  // compat FR/EN via User::updateProfile()
            'email'  => $email,
            'telephone' => $phone,
            'bio'    => $bio,
        ]);

        /* Update password si fourni */
        if ($newPass !== '' && $newPass === $confirm) {
            User::updatePassword($userId, $newPass);
        }

        /* Sync session (utile pour l’entête) */
        $_SESSION['user']['pseudo'] = $pseudo;
        $_SESSION['user']['email']  = $email;
        $_SESSION['flash_success']  = 'Profil mis à jour.';

        header('Location: /user/profile'); exit;
    }
}

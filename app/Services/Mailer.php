<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Classe Mailer
 * -------------
 * Service maison qui encapsule PHPMailer pour centraliser l’envoi d’e-mails.
 * Elle prend en charge :
 *   - La configuration SMTP (via app.php ou config par défaut)
 *   - L’envoi de mails génériques (send)
 *   - Des méthodes dédiées à notre métier (confirmation, publication, etc.)
 */
final class Mailer
{
    /** @var PHPMailer Instance utilisée pour envoyer tous les mails */
    private PHPMailer $m;

    public function __construct()
    {
        // ---------------- Chargement configuration ---------------------
        // On essaye plusieurs emplacements possibles pour trouver app.php
        $rootApp = dirname(__DIR__);           // répertoire app/
        $root    = dirname($rootApp);          // racine du projet
        $try = [
            $rootApp . '/config/app.php',
            $rootApp . '/Config/app.php',
            $root    . '/config/app.php',
            $root    . '/Config/app.php',
        ];

        $all = null;
        foreach ($try as $p) {
            if (is_file($p)) { $all = require $p; break; }
        }

        if (!is_array($all)) {
            // Si aucune config trouvée -> log + fallback valeurs vides
            error_log('[MAILER] Fichier config app.php introuvable');
            $all = [];
        }
        $cfg = $all['mail'] ?? [];

        // ---------------- Initialisation PHPMailer ---------------------
        $this->m = new PHPMailer(true);

        // Toujours désactiver les messages debug dans la réponse HTTP
        $this->m->SMTPDebug   = 0;
        $this->m->Debugoutput = 'error_log';

        $this->m->isSMTP();
        $this->m->CharSet = 'UTF-8';
        $this->m->isHTML(true); // tous les mails en HTML par défaut

        // Timeout court pour éviter de bloquer la requête trop longtemps
        $this->m->Timeout  = (int)($cfg['timeout'] ?? 10);
        $this->m->Host     = (string)($cfg['host'] ?? 'localhost');
        $this->m->Port     = (int)($cfg['port'] ?? 25);

        // ---------------- Expéditeur (From) ---------------------
        $fromEmail = (string)($cfg['from_email'] ?? 'no-reply@ecoride.local');
        $fromName  = (string)($cfg['from_name']  ?? 'EcoRide');
        try {
            $this->m->setFrom($fromEmail, $fromName);
        } catch (\Throwable $e) {
            // Si jamais l’adresse est invalide -> on log et fallback générique
            error_log('[MAILER] setFrom invalide: '.$e->getMessage());
            $this->m->setFrom('no-reply@localhost', 'EcoRide');
        }

        // ---------------- Authentification SMTP ---------------------
        $username = (string)($cfg['username'] ?? '');
        $password = (string)($cfg['password'] ?? '');
        $enc      = strtolower(trim((string)($cfg['encryption'] ?? '')));

        $this->m->SMTPAuth = ($username !== '');
        if ($this->m->SMTPAuth) {
            $this->m->Username = $username;
            $this->m->Password = $password;
        }

        // Choix du chiffrement (ssl, tls ou aucun)
        if ($enc === 'ssl') {
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->m->SMTPSecure = '';
        }

        // ---------------- Options SSL (dev) ---------------------
        if (!empty($cfg['allow_self_signed'])) {
            $this->m->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }
    }

    /**
     * Méthode générique d’envoi d’e-mail
     * - $toEmail : adresse du destinataire
     * - $toName  : nom du destinataire
     * - $subject : sujet
     * - $html    : corps HTML
     * - $textAlt : version texte brut (optionnelle)
     */
    public function send(string $toEmail, string $toName, string $subject, string $html, string $textAlt = ''): bool
    {
        try {
            $this->m->clearAllRecipients();
            $this->m->clearAttachments();
            $this->m->addAddress($toEmail, $toName);
            $this->m->Subject = $subject;
            $this->m->Body    = $html;
            $this->m->AltBody = $textAlt !== '' ? $textAlt : trim(strip_tags($html));
            return $this->m->send();
        } catch (Exception $e) {
            error_log('[MAILER] '.$e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log('[MAILER][THROWABLE] '.$e->getMessage());
            return false;
        }
    }

    /* ----------------- MÉTHODES MÉTIER ----------------- */

    /** Mail envoyé au chauffeur quand son trajet est publié */
    public function sendRidePublished(array $driver, array $ride): bool
    {
        $subject = "Votre trajet a été publié ✅";
        $html    = $this->render('ride_published', compact('driver','ride'));
        return $this->send(
            (string)($driver['email'] ?? ''),
            (string)($driver['pseudo'] ?? $driver['nom'] ?? 'Chauffeur'),
            $subject,
            $html
        );
    }

    /** Mail de confirmation pour le passager */
    public function sendBookingConfirmation(array $passenger, array $ride, array $driver): bool
    {
        $subject = "Confirmation de votre réservation 🚗";
        $html    = $this->render('booking_user', compact('passenger','ride','driver'));
        return $this->send(
            (string)($passenger['email'] ?? ''),
            (string)($passenger['pseudo'] ?? $passenger['nom'] ?? 'Passager'),
            $subject,
            $html
        );
    }

    /** Mail envoyé au chauffeur quand un passager réserve son trajet */
    public function sendDriverNewReservation(array $driver, array $ride, array $passenger): bool
    {
        $subject = "Nouvelle réservation sur votre trajet ✉️";
        $html    = $this->render('booking_driver', compact('driver','ride','passenger'));
        return $this->send(
            (string)($driver['email'] ?? ''),
            (string)($driver['pseudo'] ?? $driver['nom'] ?? 'Chauffeur'),
            $subject,
            $html
        );
    }

    /** Invitation pour le passager à déposer un avis */
    public function sendReviewInvite(array $passenger, array $ride, array $driver, string $link): bool
    {
        $subject = "Votre avis sur le trajet “".($ride['from_city'] ?? '')." → ".($ride['to_city'] ?? '')."”";
        $html    = $this->render('review_invite', [
            'passenger' => $passenger,
            'ride'      => $ride,
            'driver'    => $driver,
            'link'      => $link,
        ]);
        $alt = "Déposer mon avis : {$link}";
        return $this->send(
            (string)($passenger['email'] ?? ''),
            (string)($passenger['pseudo'] ?? 'Passager'),
            $subject,
            $html,
            $alt
        );
    }

    /** Mail de bienvenue lors de l’inscription */
    public function sendWelcome(array $user): bool
    {
        $subject = "Bienvenue sur EcoRide 👋";
        $html    = $this->render('signup_welcome', ['user' => $user]);
        return $this->send(
            (string)($user['email'] ?? ''),
            (string)($user['pseudo'] ?? $user['nom'] ?? 'Utilisateur'),
            $subject,
            $html
        );
    }

    /** Mail de vérification d’adresse e-mail */
    public function sendVerifyEmail(array $user, string $link): bool
    {
        $subject = "Confirmez votre adresse e-mail";
        $html    = $this->render('signup_verify', ['user' => $user, 'link' => $link]);
        return $this->send(
            (string)($user['email'] ?? ''),
            (string)($user['pseudo'] ?? $user['nom'] ?? 'Utilisateur'),
            $subject,
            $html
        );
    }

    /* ----------------- Rendu des templates mails ----------------- */

    /**
     * Rendu d’un template d’e-mail
     * - Cherche dans /Views/email
     * - Injecte les variables fournies
     * - Renvoie le HTML final
     */
    private function render(string $template, array $vars): string
    {
        $base = dirname(__DIR__); // app/
        $paths = [
            $base . "/Views/email/{$template}.php",
            $base . "/views/email/{$template}.php",
            $base . "/View/email/{$template}.php",
        ];

        $file = null;
        foreach ($paths as $p) { if (is_file($p)) { $file = $p; break; } }

        if ($file === null) {
            error_log("[MAILER] Template email manquant: {$template}");
            return "<p style=\"font-family:Arial,sans-serif\">{$template}</p>";
        }

        // Inclusion des helpers communs aux mails (si présents)
        $helpers = $base . "/Views/email/_helpers.php";
        if (is_file($helpers)) { include_once $helpers; }

        // On isole l’exécution du template pour capturer le HTML
        try {
            extract($vars, EXTR_SKIP);
            ob_start();
            include $file;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            error_log('[MAILER][RENDER] '.$e->getMessage());
            return "<p style=\"font-family:Arial,sans-serif\">EcoRide</p>";
        }
    }
}

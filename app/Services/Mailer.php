<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class Mailer
{
    private PHPMailer $m;

    public function __construct()
    {
        /* Localise config/app.php */
        $rootApp = dirname(__DIR__);
        $root    = dirname($rootApp);
        $try = [
            $rootApp . '/config/app.php',
            $rootApp . '/Config/app.php',
            $root    . '/config/app.php',
            $root    . '/Config/app.php',
        ];
        $all = null;
        foreach ($try as $p) { if (is_file($p)) { $all = require $p; break; } }
        if (!is_array($all)) {
            throw new \RuntimeException('Config app.php introuvable: ' . implode(', ', $try));
        }
        $cfg = $all['mail'] ?? [];

        $this->m = new PHPMailer(true);
        $this->m->isSMTP();
        $this->m->Host    = (string)($cfg['host'] ?? 'localhost');
        $this->m->Port    = (int)($cfg['port'] ?? 587);
        $this->m->CharSet = 'UTF-8';
        $this->m->isHTML(true);

        /* From */
        $fromEmail = (string)($cfg['from_email'] ?? 'no-reply@ecoride.fr');
        $fromName  = (string)($cfg['from_name']  ?? 'EcoRide');
        $this->m->setFrom($fromEmail, $fromName);

        /* Auth si username non vide */
        $hasUser = ((string)($cfg['username'] ?? '') !== '');
        $this->m->SMTPAuth = $hasUser;
        if ($hasUser) {
            $this->m->Username = (string)$cfg['username'];
            $this->m->Password = (string)($cfg['password'] ?? '');
        }

        /* Chiffrement */
        $enc = strtolower(trim((string)($cfg['encryption'] ?? '')));
        if ($enc === 'ssl') {
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // 465
        } elseif ($enc === 'tls') {
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 587
        } else {
            $this->m->SMTPSecure = false; // MailHog (pas de TLS)
        }

        /* Debug */
        $debug = (int)($cfg['debug'] ?? 0);
        if ($debug > 0) {
            $this->m->SMTPDebug   = 2;
            $this->m->Debugoutput = 'error_log';
        }
    }

    public function send(string $toEmail, string $toName, string $subject, string $html, string $textAlt = ''): bool
    {
        try {
            $this->m->clearAllRecipients();
            $this->m->addAddress($toEmail, $toName);
            $this->m->Subject = $subject;
            $this->m->Body    = $html;
            $this->m->AltBody = $textAlt ?: strip_tags($html);
            return $this->m->send();
        } catch (Exception $e) {
            error_log('[MAILER] ' . $e->getMessage());
            return false;
        }
    }

    /* Cas EcoRide */

    public function sendRidePublished(array $driver, array $ride): bool
    {
        $subject = "Votre trajet a été publié ✅";
        $html    = $this->render('ride_published', compact('driver','ride'));
        return $this->send(
            (string)$driver['email'],
            (string)($driver['pseudo'] ?? $driver['nom'] ?? 'Chauffeur'),
            $subject,
            $html
        );
    }

    public function sendBookingConfirmation(array $passenger, array $ride, array $driver): bool
    {
        $subject = "Confirmation de votre réservation 🚗";
        $html    = $this->render('booking_user', compact('passenger','ride','driver'));
        return $this->send(
            (string)$passenger['email'],
            (string)($passenger['pseudo'] ?? $passenger['nom'] ?? 'Passager'),
            $subject,
            $html
        );
    }

    public function sendDriverNewReservation(array $driver, array $ride, array $passenger): bool
    {
        $subject = "Nouvelle réservation sur votre trajet ✉️";
        $html    = $this->render('booking_driver', compact('driver','ride','passenger'));
        return $this->send(
            (string)$driver['email'],
            (string)($driver['pseudo'] ?? $driver['nom'] ?? 'Chauffeur'),
            $subject,
            $html
        );
    }

    /* Invitation à laisser un avis (après fin de trajet) */
    public function sendReviewInvite(array $passenger, array $ride, array $driver, string $link): bool
    {
        $subject = "Votre avis sur le trajet “{$ride['from_city']} → {$ride['to_city']}”";
        $html    = $this->render('review_invite', [
            'passenger' => $passenger,
            'ride'      => $ride,
            'driver'    => $driver,
            'link'      => $link,
        ]);
        return $this->send(
            (string)$passenger['email'],
            (string)($passenger['pseudo'] ?? 'Passager'),
            $subject,
            $html
        );
    }

    private function render(string $template, array $vars): string
    {
        $file = dirname(__DIR__) . "/Views/emails/{$template}.php";
        if (!is_file($file)) return "<p>Template manquant: {$template}</p>";
        extract($vars, EXTR_SKIP);
        ob_start(); include $file; return (string)ob_get_clean();
    }
}

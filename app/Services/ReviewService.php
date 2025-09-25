<?php
declare(strict_types=1);

namespace App\Services;

use App\Security\Security;

/**
 * Service d'envoi des invitations d'avis.
 * Je reste côté Mongo (pas de table MySQL d'invitation) et je m'appuie
 * sur le token vérifié par Security::verifyReviewToken().
 */
final class ReviewService
{
    private Mailer $mailer;
    private string $baseUrl;

    public function __construct(?Mailer $mailer = null, ?string $baseUrl = null)
    {
        // Je permets l'injection mais je fournis des valeurs par défaut.
        $this->mailer  = $mailer  ?? new Mailer();
        $this->baseUrl = rtrim($baseUrl ?? (getenv('BASE_URL') ?: 'http://localhost:8080'), '/');
    }

    /**
     * J'envoie une invitation à un passager pour un trajet.
     * $ride/$driver/$passenger sont des arrays (déjà récupérés via tes Models ou PDO).
     */
    public function invitePassenger(array $ride, array $driver, array $passenger, string $ttl = '+7 days'): bool
    {
        // Je génère un token signé qui contient au minimum: rid (ride_id), pid (passenger_id), exp.
        $token = Security::issueReviewToken((int)$ride['id'], (int)$passenger['id'], $ttl);
        $link  = $this->baseUrl . '/reviews/new?token=' . $token;

        return $this->mailer->sendReviewInvite($passenger, $ride, $driver, $link);
    }

    /**
     * J'envoie l'invitation à toute la liste de passagers confirmés.
     */
    public function inviteAll(array $ride, array $driver, array $passengers, string $ttl = '+7 days'): int
    {
        $sent = 0;
        foreach ($passengers as $p) {
            if (!empty($p['email']) && $this->invitePassenger($ride, $driver, $p, $ttl)) {
                $sent++;
            }
        }
        return $sent;
    }
}

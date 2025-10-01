<?php
declare(strict_types=1);

namespace App\Services;

use App\Security\Security;

/**
 * Service d'envoi des invitations d'avis
 * --------------------------------------
 * Mon objectif ici est simple : centraliser toute la logique qui concerne
 * l’envoi des invitations d’avis aux passagers après un trajet.
 *
 * Choix techniques :
 * - Je reste côté MongoDB pour stocker/récupérer les avis (pas de table MySQL d’invitations).
 * - Je m’appuie sur un token signé/horodaté pour sécuriser le lien d’invitation.
 *   -> génération : Security::issueReviewToken()
 *   -> vérification : Security::verifyReviewToken()
 *
 * Avantages :
 * - Pas de table supplémentaire à maintenir pour les invitations.
 * - Un lien autoportant (le token inclut ride_id, passenger_id, expiration).
 * - La vérification est stateless (juste la clé secrète partagée côté serveur).
 */
final class ReviewService
{
    /** Le service mail que j’utilise pour expédier les e-mails. */
    private Mailer $mailer;

    /**
     * Base publique du site, nécessaire pour générer des liens cliquables.
     * Ex : https://ecoride.example.com
     */
    private string $baseUrl;

    /**
     * Je permets l’injection du Mailer et de la baseUrl pour les tests
     * (inversion de dépendances), mais je fournis aussi des valeurs par défaut
     * pour l’environnement local.
     */
    public function __construct(?Mailer $mailer = null, ?string $baseUrl = null)
    {
        // Si on ne m’injecte rien, je crée mon propre Mailer.
        $this->mailer  = $mailer  ?? new Mailer();

        // Je sécurise la base URL : fallback sur BASE_URL sinon localhost.
        // rtrim pour éviter les doubles "slash" quand je construis le lien.
        $this->baseUrl = rtrim($baseUrl ?? (getenv('BASE_URL') ?: 'http://localhost:8080'), '/');
    }

    /**
     * Envoie une invitation d’avis à UN passager concernant UN trajet.
     *
     * Paramètres attendus :
     * - $ride      : array (détails du trajet ; au minimum 'id', 'from_city', 'to_city'…)
     * - $driver    : array (info chauffeur ; email/pseudo/nom…)
     * - $passenger : array (info passager ; email/pseudo/nom…)
     * - $ttl       : durée de validité du lien, ex. '+7 days'
     *
     * Étapes :
     * 1) Générer un token signé (rid, pid, exp) -> Security::issueReviewToken()
     * 2) Construire l’URL publique d’invitation avec ce token en query string.
     * 3) Déléguer l’envoi au Mailer (template email 'review_invite').
     */
    public function invitePassenger(array $ride, array $driver, array $passenger, string $ttl = '+7 days'): bool
    {
        // Génère un token signé contenant :
        // - rid : id du trajet
        // - pid : id du passager
        // - exp : timestamp d’expiration (calculé avec $ttl)
        $token = Security::issueReviewToken((int)$ride['id'], (int)$passenger['id'], $ttl);

        // Je fabrique le lien cliquable que je place dans l’e-mail.
        $link  = $this->baseUrl . '/reviews/new?token=' . $token;

        // J’envoie l’e-mail d’invitation : le Mailer s’occupe du rendu HTML.
        return $this->mailer->sendReviewInvite($passenger, $ride, $driver, $link);
    }

    /**
     * Envoie l’invitation à TOUS les passagers confirmés d’un trajet.
     *
     * Je parcours la liste $passengers, et pour chacun :
     * - si un email est présent, j’essaie d’envoyer l’invitation
     * - je compte combien ont été réellement envoyées (retours true)
     *
     * Retour :
     * - nombre d’e-mails effectivement envoyés.
     */
    public function inviteAll(array $ride, array $driver, array $passengers, string $ttl = '+7 days'): int
    {
        $sent = 0;

        foreach ($passengers as $p) {
            // Je filtre les passagers sans email pour éviter des erreurs d’envoi.
            if (!empty($p['email']) && $this->invitePassenger($ride, $driver, $p, $ttl)) {
                $sent++;
            }
        }

        return $sent;
    }
}

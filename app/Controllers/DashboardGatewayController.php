<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;

/**
 * DashboardGatewayController
 * Mon "portier" du tableau de bord :
 * - Vérifie que l'utilisateur est connecté
 * - Redirige automatiquement vers le bon dashboard selon le rôle
 *   (ADMIN → /admin/dashboard, EMPLOYEE → /employee/dashboard, USER → /user/dashboard)
 * - En cas d'anomalie, renvoie vers /login avec un redirect propre.
 *
 * L'idée : garder une URL unique /dashboard côté front, et router ici côté serveur.
 */
final class DashboardGatewayController extends BaseController
{
    /**
     * Route principale appelée sur /dashboard
     * Je ne rends aucune vue : je fais uniquement des redirections HTTP.
     */
    public function route(): void
    {
        // 1) Accès réservé : si non connecté, je renvoie vers /login
        //    Je passe ?redirect=/dashboard pour revenir ici après login.
        if (!Security::check()) {
            header('Location: /login?redirect=/dashboard');
            exit;
        }

        // 2) Je route en fonction du rôle courant.
        //    NB : j’utilise des exit juste après chaque header pour stopper le flux.
        switch (Security::role()) {
            case 'ADMIN':
                header('Location: /admin/dashboard');
                exit;

            case 'EMPLOYEE':
                header('Location: /employee/dashboard');
                exit;

            case 'USER':
                header('Location: /user/dashboard');
                exit;

            // 3) Fallback de sécurité : si pour une raison quelconque le rôle n'est pas reconnu,
            //    je redirige vers /login (avec redirect pour boucler proprement).
            default:
                header('Location: /login?redirect=/dashboard');
                exit;
        }
    }
}

<?php
namespace App\Controllers;

/**
 * StaticController
 * - Je centralise ici les pages statiques du site (pas de logique métier).
 * - Objectif : rester propre côté MVC et éviter de mélanger le rendu avec le back-end.
 */
class StaticController extends BaseController
{
    /**
     * Page Mentions légales
     * - J’envoie juste un titre + quelques metas pour le SEO/robots.
     * - La vue (app/Views/pages/mentions-legales.php) s’occupe de l’HTML.
     */
    public function mentions(): void
    {
        $this->render('pages/mentions-legales', [
            'title' => 'Mentions légales – EcoRide',
            'meta'  => [
                'description' => "Mentions légales de la plateforme de covoiturage écologique EcoRide.",
                'robots'      => "noindex,follow" // je choisis de ne pas indexer, mais je laisse suivre les liens
            ],
            'bodyClass' => 'page-mentions' // utile si je veux un style spécifique sur cette page
        ]);
    }
}

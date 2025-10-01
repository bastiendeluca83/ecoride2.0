<?php
namespace App\Controllers;

/**
 * StaticController
 * - Je centralise ici les pages statiques du site (pas de logique métier).
 * - Je renvoie vers des vues "pages/*" qui contiennent juste du HTML.
 */
class StaticController extends BaseController
{
    /** Mentions légales (tu l’avais déjà, je la laisse telle quelle) */
    public function mentions(): void
    {
        $this->render('pages/mentions-legales', [
            'title' => 'Mentions légales – EcoRide',
            'meta'  => [
                'description' => "Mentions légales de la plateforme de covoiturage écologique EcoRide.",
                'robots'      => "noindex,follow" // je préfère éviter l’indexation de cette page
            ],
            'bodyClass' => 'page-mentions'
        ]);
    }

    /** Politique de confidentialité */
    public function confidentialite(): void
    {
        $this->render('pages/confidentialite', [
            'title' => 'Politique de confidentialité – EcoRide',
            'meta'  => [
                'description' => "Politique de confidentialité et gestion des données personnelles sur EcoRide."
            ],
            'bodyClass' => 'page-confidentialite'
        ]);
    }

    /** Conditions Générales d’Utilisation (CGU) */
    public function cgu(): void
    {
        $this->render('pages/cgu', [
            'title' => 'Conditions générales d’utilisation – EcoRide',
            'meta'  => [
                'description' => "Conditions générales d’utilisation de la plateforme EcoRide."
            ],
            'bodyClass' => 'page-cgu'
        ]);
    }

    /** Contact (affiché dans le footer uniquement côté UI) */
    public function contact(): void
    {
        $this->render('pages/contact', [
            'title' => 'Contact – EcoRide',
            'meta'  => [
                'description' => "Formulaire et informations de contact pour EcoRide."
            ],
            'bodyClass' => 'page-contact'
        ]);
    }
}

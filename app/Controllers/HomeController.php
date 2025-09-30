<?php
namespace App\Controllers;

/**
 * HomeController
 * - Je centralise ici les pages "publiques" simples du site.
 * - Je reste strict MVC : aucune logique métier, je passe juste un titre et j'appelle la vue.
 * - Les vues correspondantes sont dans app/Views (ex: home/index.php, pages/contact.php, pages/legal.php).
 */
class HomeController extends BaseController
{
    /**
     * Page d'accueil
     * - J’affiche la vue home/index avec un titre propre pour le SEO.
     * - Pas de données complexes ici, juste du rendu statique + composants communs (header/footer).
     */
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'EcoRide – Covoiturage écoresponsable',
        ]);
    }

    /**
     * Page contact
     * - Je pointe vers pages/contact (formulaire ou infos).
     * - Toujours aucun traitement dans le contrôleur, le POST du formulaire sera traité ailleurs (contrôleur dédié).
     */
    public function contact(): void
    {
        $this->render('pages/contact', [
            'title' => 'Contact – EcoRide',
        ]);
    }

    /**
     * Page mentions légales
     * - Je garde la vue séparée (pages/legal) pour rester propre et réutilisable.
     * - Le contenu est purement informatif, géré côté vue.
     */
    public function legal(): void
    {
        $this->render('pages/legal', [
            'title' => 'Mentions légales – EcoRide',
        ]);
    }
}

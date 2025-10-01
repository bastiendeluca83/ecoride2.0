<?php
namespace App\Controllers;

/**
 * Classe de base de tous mes contrôleurs.
 * Elle centralise les méthodes communes (ici : render).
 */
class BaseController {

    /**
     * Méthode utilitaire pour afficher une page.
     *
     * @param string $view   Le nom de la vue à charger (ex: 'auth/login')
     * @param array  $params Les données que je veux exposer à la vue
     */
    protected function render(string $view, array $params = []): void {
        // Je transforme le tableau $params en variables locales accessibles dans la vue
        // Exemple : ['title' => 'Accueil'] devient $title = 'Accueil';
        extract($params, EXTR_SKIP);

        // -----------------------------
        // 1) J'inclus la vue demandée
        // -----------------------------
        // Je capture son contenu dans $content pour l’injecter ensuite dans mon layout
        ob_start();
        require __DIR__ . "/../Views/{$view}.php";
        $content = ob_get_clean();

        // -----------------------------
        // 2) J'inclus le layout global
        // -----------------------------
        // Mon layout (base.php) se charge d’entourer la vue ($content) avec l’HTML commun (header, footer…)
        require __DIR__ . "/../Views/layouts/base.php";
    }
}

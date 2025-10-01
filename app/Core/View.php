<?php
namespace App\Core;

/**
 * Classe View : je centralise le rendu des pages.
 * - Je reçois un chemin de template "domaine/fichier" (ex: home/index)
 * - Je reçois un tableau $data avec les variables à exposer à la vue
 * - Je m'occupe d'inclure header, (flash), la vue principale, puis le footer
 *
 * Avantage : toutes les pages partagent la même structure (layout commun),
 * et le contrôleur reste propre (il appelle juste View::render / BaseController->render).
 */
class View {
    /**
     * Rend une vue avec un layout commun.
     * @param string $tpl  Chemin relatif de la vue sans extension (ex: 'home/index')
     * @param array  $data Données passées à la vue (ex: ['title'=>'Accueil'])
     */
    public static function render(string $tpl, array $data = []): void {
        /* Je rends disponibles les clés de $data comme variables locales dans la vue.
           EXTR_SKIP = si un nom existe déjà, je ne l’écrase pas (sécurité de base). */
        extract($data, EXTR_SKIP);

        // Exemple : "home/index" => /app/Views/home/index.php (VIEW_PATH défini dans Paths.php)
        $file = VIEW_PATH . '/' . $tpl . '.php';

        // Je vérifie que la vue demandée existe pour éviter un include silencieux
        if (!is_file($file)) {
            http_response_code(500);
            echo "❌ Vue introuvable : $file";
            return;
        }

        /* ---------- Layout commun ---------- */

        /* Header commun (doctype, <head>, ouverture du <body>, navbar…) */
        require VIEW_PATH . '/includes/header.php';

        /* Messages flash (optionnel) :
           - Si un fichier includes/flash.php existe, je l’inclus pour afficher les messages
             contenus en session (success, error, info…). */
        $flashFile = VIEW_PATH . '/includes/flash.php';
        if (is_file($flashFile)) {
            require $flashFile;
        }

        /* Vue principale (contenu spécifique à la page) */
        require $file;

        /* Footer commun (fermeture du layout, scripts fin de page, etc.) */
        require VIEW_PATH . '/includes/footer.php';
    }
}

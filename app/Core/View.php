<?php
namespace App\Core;

/**
 * Classe View : centralise le rendu des pages
 * - Injecte les données ($data)
 * - Ajoute automatiquement header, flash et footer
 * - Charge la vue demandée
 */
class View {
    public static function render(string $tpl, array $data = []): void {
        /* Rend disponibles les variables du tableau $data dans la vue */
        extract($data, EXTR_SKIP);

        // Exemple : "home/index" => /app/Views/home/index.php
        $file = VIEW_PATH . '/' . $tpl . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            echo "❌ Vue introuvable : $file";
            return;
        }

        /* Inclusion du header */
        require VIEW_PATH . '/includes/header.php';

        /* Messages flash (optionnel, si tu crées flash.php)*/
        $flashFile = VIEW_PATH . '/includes/flash.php';
        if (is_file($flashFile)) {
            require $flashFile;
        }

        /* Vue principale */
        require $file;

        /* Footer */
        require VIEW_PATH . '/includes/footer.php';
    }
}

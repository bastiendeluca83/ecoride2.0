<?php
namespace App\Controllers;

class BaseController {
    protected function render(string $view, array $params = []): void {
        // Expose les variables à la vue
        extract($params, EXTR_SKIP);

        // 1) Rendre la vue demandée → $content
        ob_start();
        require __DIR__ . "/../Views/{$view}.php";
        $content = ob_get_clean();

        // 2) Injecter dans le layout unique
        require __DIR__ . "/../Views/layouts/base.php";
    }
}

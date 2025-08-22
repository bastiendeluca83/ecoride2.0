<?php
namespace App\Controllers;

class TrajetController
{
    public function show(): string
    {
        // réutilise ton fichier existant
        ob_start();
        include __DIR__ . '/../../public/trajet.php';
        return ob_get_clean();
    }
}

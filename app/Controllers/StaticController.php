<?php
namespace App\Controllers;

class StaticController extends BaseController
{
    public function mentions(): void
    {
        $this->render('pages/legal', [
            'title' => 'Mentions légales – EcoRide',
        ]);
    }
}

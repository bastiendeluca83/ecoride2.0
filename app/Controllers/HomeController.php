<?php
namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'EcoRide – Covoiturage écoresponsable',
        ]);
    }

    public function contact(): void
    {
        $this->render('pages/contact', [
            'title' => 'Contact – EcoRide',
        ]);
    }

    public function legal(): void
    {
        $this->render('pages/legal', [
            'title' => 'Mentions légales – EcoRide',
        ]);
    }
}

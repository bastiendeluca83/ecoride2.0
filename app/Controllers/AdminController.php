<?php
namespace App\Controllers;

use App\Security\Security;
use App\Db\Sql;
use PDO;

class AdminController
{
    private function pdo(): PDO { return Sql::pdo(); }

    public function index()
    {
        Security::ensure(['ADMIN']);
        $title = 'Espace Administrateur';
        ob_start();
        include __DIR__ . '/../Views/dashboard/admin.php';
        return ob_get_clean();
    }

    public function createEmployee()
    {
        Security::ensure(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        $pdo = $this->pdo();
        $nom = trim($_POST['nom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = (string)($_POST['password'] ?? '');

        if (!$nom || !$email || strlen($pass) < 8) { header('Location:/admin'); return; }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO users(nom, email, password_hash, role, credits) VALUES(:p,:e,:h,'EMPLOYEE',0)")
                ->execute(['p'=>$nom,'e'=>$email,'h'=>$hash]);
        } catch (\Throwable $e) {}
        header('Location: /admin');
    }

    public function suspendUser()
    {
        Security::ensure(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        $targetId = (int)($_POST['id'] ?? 0);
        $suspend  = (int)($_POST['suspend'] ?? 1);
        $selfId   = (int)($_SESSION['user']['id'] ?? 0);

        if ($targetId <= 0 || $targetId === $selfId) { header('Location:/admin'); return; }

        $this->pdo()->prepare("UPDATE users SET is_suspended = :s WHERE id = :id")
            ->execute(['s'=>$suspend,'id'=>$targetId]);
        header('Location: /admin');
    }

    public function suspendAccount() { $this->suspendUser(); }
}

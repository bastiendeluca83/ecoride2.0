<?php
namespace App\Controllers;

use App\Security\Security;
use App\Db\Sql;
use PDO;

class AdminController extends BaseController
{
    private function pdo(): PDO { return Sql::pdo(); }

    public function index(): void {
        Security::ensure(['ADMIN']);
        $this->render('dashboard/admin', ['title' => 'Espace Administrateur']);
    }

    // Alias pour compatibilité
    public function addEmployee(): void    { $this->createEmployee(); }
    public function suspendEmployee(): void{ $this->suspendUser(); }
    public function suspendAccount(): void { $this->suspendUser(); }

    /** POST /admin/employees/create */
    public function createEmployee(): void {
        Security::ensure(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        $pdo   = $this->pdo();
        $nom   = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        if ($nom===''||$email===''||strlen($pass)<8){ header('Location:/admin'); return; }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO users (nom,email,password_hash,role,credits,is_suspended)
                           VALUES (:n,:e,:h,'EMPLOYEE',0,0)")
                ->execute(['n'=>$nom,'e'=>$email,'h'=>$hash]);
        } catch (\PDOException $e) {
            header('Location:/admin?error=duplicate'); return;
        }
        header('Location:/admin?created=1');
    }

    /** POST /admin/users/suspend */
    public function suspendUser(): void {
        Security::ensure(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        $targetId = (int)($_POST['id'] ?? 0);
        $suspend  = (int)($_POST['suspend'] ?? 1);
        $selfId   = (int)($_SESSION['user']['id'] ?? 0);

        if ($targetId<=0 || $targetId===$selfId){ header('Location:/admin?error=badtarget'); return; }

        $this->pdo()->prepare("UPDATE users SET is_suspended = :s WHERE id = :id")
            ->execute(['s'=>$suspend,'id'=>$targetId]);
        header('Location:/admin?suspended=1');
    }
}

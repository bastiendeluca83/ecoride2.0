<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Db\Mongo;
use MongoDB\BSON\ObjectId;

final class EmployeeController extends BaseController
{
    public function index(): void
    {
        // Autoriser EMPLOYEE et ADMIN
        Security::ensure(['EMPLOYEE','ADMIN']);

        $mongoError = null;
        $reviews    = [];
        $incidents  = []; // TODO: à alimenter plus tard

        // --- Mongo : charger les avis en attente
        try {
            $db = Mongo::db();
            if (!$db) {
                $mongoError = 'MongoDB non configuré (MONGO_HOST/DB).';
            } else {
                $coll   = $db->selectCollection('reviews');
                $cursor = $coll->find(['status' => 'PENDING'], [
                    'limit' => 50,
                    'sort'  => ['created_at' => -1],
                ]);

                foreach ($cursor as $doc) {
                    $arr = (is_object($doc) && method_exists($doc, 'getArrayCopy'))
                        ? $doc->getArrayCopy()
                        : (array)$doc;

                    $reviews[] = [
                        '_id'        => isset($arr['_id']) ? (string)$arr['_id'] : '',
                        'driver'     => $arr['driver']       ?? 'n/a',
                        'driverMail' => $arr['driver_mail']  ?? 'n/a',
                        'note'       => (int)($arr['note']    ?? 0),
                        'comment'    => (string)($arr['comment'] ?? ''),
                        'ride_id'    => (int)($arr['ride_id'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $mongoError = 'Erreur MongoDB : ' . $e->getMessage();
        }

        // --- Bouton contextuel selon rôle
        $role       = Security::role();
        $crossLabel = ($role === 'ADMIN') ? 'Espace administrateur' : 'Espace utilisateur';
        $crossHref  = ($role === 'ADMIN') ? '/admin/dashboard'     : '/user/dashboard';

        // --- CSRF + current URL (pour le formulaire de logout)
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf       = $_SESSION['csrf'];
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/employee';

        // --- Render (le layout s’occupe du header/footer)
        $this->render('dashboard/employee', [
            'title'      => 'Espace Employé',
            'mongoError' => $mongoError,
            'reviews'    => $reviews,
            'incidents'  => $incidents,
            'crossLabel' => $crossLabel,
            'crossHref'  => $crossHref,
            'csrf'       => $csrf,
            'currentUrl' => $currentUrl,
        ]);
    }

    /** POST /employee/reviews   (id, action=approve|reject, csrf) */
    public function moderate(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        // CSRF (facultatif mais recommandé)
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            http_response_code(400);
            header('Location: /employee?error=csrf'); return;
        }

        $rawId  = $_POST['id'] ?? '';
        if (is_array($rawId)) { $rawId = reset($rawId); }
        $id     = is_string($rawId) ? trim($rawId) : '';
        $action = trim((string)($_POST['action'] ?? ''));

        if ($id === '' || !in_array($action, ['approve','reject'], true)) { header('Location: /employee'); return; }

        $status = ($action === 'approve') ? 'APPROVED' : 'REJECTED';
        $this->updateStatusSafe($id, $status);
        header('Location: /employee'); // PRG
    }

    /** Helpers dédiés ObjectId */
    private function normalizeMongoId(string $id)
    {
        if (strlen($id) === 24 && ctype_xdigit($id)) {
            try { return new ObjectId($id); } catch (\Throwable $e) {}
        }
        return $id;
    }

    /** Convertit l'id si possible et met à jour le statut. */
    private function updateStatusSafe(string $id, string $status): void
    {
        try {
            $db = Mongo::db();
            if (!$db) { return; }
            $coll = $db->selectCollection('reviews');

            $filterId = $this->normalizeMongoId($id);
            $coll->updateOne(['_id' => $filterId], ['$set' => ['status' => $status]]);
        } catch (\Throwable $e) {
            // error_log('updateStatusSafe: '.$e->getMessage());
        }
    }
}

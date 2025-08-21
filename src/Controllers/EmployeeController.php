<?php
namespace App\Controllers;

use App\Security\Security;
use App\Db\Mongo;
use MongoDB\BSON\ObjectId;

class EmployeeController
{
    public function index()
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        $mongoError = null;
        $reviews = [];
        $incidents = [];

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
                    // BSONDocument -> array
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

        $title = 'Espace Employé';
        ob_start();
        include __DIR__ . '/../Views/dashboard/employee.php';
        return ob_get_clean();
    }

    /** POST /employee/reviews   (id, action=approve|reject) */
    public function moderate(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        $rawId  = $_POST['id'] ?? '';
        if (is_array($rawId)) { $rawId = reset($rawId); }
        $id     = is_string($rawId) ? trim($rawId) : '';
        $action = trim((string)($_POST['action'] ?? ''));

        if ($id === '' || !in_array($action, ['approve','reject'], true)) { header('Location: /employee'); exit; }

        $status = ($action === 'approve') ? 'APPROVED' : 'REJECTED';
        $this->updateStatusSafe($id, $status);
        header('Location: /employee'); exit;
    }

    /** Helpers dédiés ObjectId */
    private function normalizeMongoId(string $id)
    {
        if (strlen($id) === 24 && ctype_xdigit($id)) {
            try { return new ObjectId($id); } catch (\Throwable $e) { /* garde string */ }
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
            // Optionnel : error_log('updateStatusSafe: '.$e->getMessage());
        }
    }
}

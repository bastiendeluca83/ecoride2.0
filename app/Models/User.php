<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

/**
 * Classe User
 * -----------
 * Modèle d'accès aux utilisateurs.
 * J'y regroupe tous les helpers SQL (find/update/auth…) + quelques
 * méthodes utiles côté admin (création d'employé, suspension…).
 *
 * Particularité : je gère deux schémas possibles de table `users`
 * (FR ou EN) via de petites détections auto (noms/prénoms, téléphone…).
 */
class User
{
    /* ============================================================
       Helpers DB bas niveau
       ============================================================ */

    /** Récupère la connexion PDO depuis ma couche Sql */
    private static function pdo(): \PDO { return Sql::pdo(); }

    /** Exécute une requête et renvoie une ligne (ou null) */
    private static function one(string $sql, array $p = []): ?array {
        $st = self::pdo()->prepare($sql); $st->execute($p);
        $row = $st->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }

    /** Exécute une requête et renvoie toutes les lignes (ou []) */
    private static function all(string $sql, array $p = []): array {
        $st = self::pdo()->prepare($sql); $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ============================================================
       Détection du schéma (FR vs EN) et colonne de date de naissance
       ============================================================ */

    /** Mémo : le schéma expose-t-il des colonnes en anglais ? */
    private static ?bool $useEnglishCols = null;

    /** Mémo : quelle colonne sert de date de naissance en BDD ? */
    private static ?string $dateCol = null;

    /**
     * Je détecte si j'utilise le schéma EN (présence de last_name).
     * Ça me permet de mapper dynamiquement les colonnes.
     */
    private static function useEnglish(): bool {
        if (self::$useEnglishCols !== null) return self::$useEnglishCols;
        $st = self::pdo()->prepare("SHOW COLUMNS FROM users LIKE 'last_name'");
        $st->execute();
        self::$useEnglishCols = (bool)$st->fetch(PDO::FETCH_ASSOC);
        return self::$useEnglishCols;
    }

    /** Vérifie l'existence d'une colonne dans `users` */
    private static function colExists(string $col): bool {
        $st = self::pdo()->prepare("SHOW COLUMNS FROM users LIKE :c");
        $st->execute([':c'=>$col]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne le nom réel de la colonne "date de naissance" en BDD.
     * Je teste plusieurs variantes possibles.
     * (NB: 'date_of_birth' est en doublon dans la liste, sans incidence.)
     */
    private static function dateColumn(): ?string {
        if (self::$dateCol !== null) return self::$dateCol;
        foreach (['date_naissance','date_of_birth','date_of_birth'] as $c) {
            if (self::colExists($c)) { self::$dateCol = $c; return $c; }
        }
        self::$dateCol = null;
        return null;
    }

    /* ============================================================
       CRUD / Authentification de base
       ============================================================ */

    /**
     * Crée un utilisateur avec rôle (USER par défaut) et crédits initiaux.
     * Le mot de passe est hashé en bcrypt.
     */
    public static function create(string $pseudo, string $email, string $plainPassword, string $role = 'USER', int $credits = 20): int {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users(nom,email,password_hash,role,credits,created_at) VALUES(:p,:e,:h,:r,:c,NOW())";
        $pdo = self::pdo();
        $pdo->prepare($sql)->execute([':p'=>$pseudo, ':e'=>$email, ':h'=>$hash, ':r'=>$role, ':c'=>$credits]);
        return (int)$pdo->lastInsertId();
    }

    /** Récupère un utilisateur par e-mail (ou null) */
    public static function findByEmail(string $email): ?array {
        return self::one("SELECT * FROM users WHERE email=:e LIMIT 1", [':e'=>$email]);
    }

    /**
     * Vérifie un couple email/mot de passe.
     * Renvoie l'utilisateur si ok, sinon null.
     */
    public static function verifyPassword(string $email, string $password): ?array {
        $user = self::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'] ?? '')) return null;
        return $user;
    }

    /** Liste tous les users (ou filtre par rôle) */
    public static function listAll(?string $role = null): array {
        if ($role) return self::all("SELECT * FROM users WHERE role=:r ORDER BY created_at DESC", [':r'=>$role]);
        return self::all("SELECT * FROM users ORDER BY created_at DESC");
    }

    /** Supprime un utilisateur (hard delete) */
    public static function delete(int $id): bool {
        return self::pdo()->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
    }

    /** Change le rôle d’un utilisateur */
    public static function setRole(int $id, string $role): bool {
        return self::pdo()->prepare("UPDATE users SET role=:r WHERE id=:id")->execute([':r'=>$role, ':id'=>$id]);
    }

    /** Ajuste les crédits (delta positif ou négatif) */
    public static function adjustCredits(int $id, int $delta): bool {
        return self::pdo()->prepare("UPDATE users SET credits = credits + :d WHERE id=:id")->execute([':d'=>$delta, ':id'=>$id]);
    }

    /**
     * firstOrCreateByEmail
     * Si l'email existe déjà -> retourne l'id existant.
     * Sinon -> crée le compte avec rôle/credits fournis.
     */
    public static function firstOrCreateByEmail(string $pseudo, string $email, string $plain, string $role='USER', int $credits=20): int {
        $row = self::findByEmail($email);
        if ($row) return (int)$row['id'];
        return self::create($pseudo, $email, $plain, $role, $credits);
    }

    /** Vérifie l’unicité d’email (avec option d’exclure un id) */
    public static function emailExists(string $email, int $excludeId = 0): bool {
        $st = self::pdo()->prepare('SELECT 1 FROM users WHERE email = :e AND id <> :id LIMIT 1');
        $st->execute([':e'=>$email, ':id'=>$excludeId]);
        return (bool)$st->fetchColumn();
    }

    /* ============================================================
       Profil (mapping FR/EN) + compat date de naissance
       ============================================================ */

    /**
     * Récupère un user par id en normalisant la sortie :
     * - j’expose toujours nom/prenom/telephone/adresse même si la BDD est EN
     * - j’expose date_naissance via un alias quel que soit le vrai nom de colonne
     */
    public static function findById(int $id): ?array {
        $dateCol = self::dateColumn();
        $dateSel = $dateCol ? "$dateCol AS date_naissance" : "NULL AS date_naissance";

        if (self::useEnglish()) {
            $sql = "SELECT id,
                           last_name  AS nom,
                           first_name AS prenom,
                           email,
                           phone      AS telephone,
                           address    AS adresse,
                           credits, role, bio, avatar_path,
                           last_credit_topup,
                           $dateSel
                    FROM users WHERE id = :id";
        } else {
            $sql = "SELECT id, nom, prenom, email, telephone, adresse, credits, role, bio, avatar_path,
                           last_credit_topup,
                           $dateSel
                    FROM users WHERE id = :id";
        }
        try {
            return self::one($sql, [':id'=>$id]);
        } catch (\Throwable $e) {
            error_log('[User::findById] '.$e->getMessage());
            return null;
        }
    }

    /**
     * Met à jour le profil avec un tableau de données.
     * Je mappe dynamiquement les clés fournies vers les colonnes BDD réelles.
     * Si une valeur string est vide -> je la passe à NULL (utile pour les champs optionnels).
     */
    public static function updateProfile(int $id, array $data): bool {
        $useEN = self::useEnglish();
        $dateCol = self::dateColumn() ?? 'date_naissance';

        // Carte des clés d'entrée -> colonnes BDD (FR/EN)
        $map = $useEN
            ? [
                'nom'=>'last_name', 'prenom'=>'first_name', 'telephone'=>'phone', 'adresse'=>'address',
                'email'=>'email', 'bio'=>'bio', 'avatar_path'=>'avatar_path',
                // tolérance : si on me donne des clés FR, je les mappe quand même
                'last_name'=>'last_name','first_name'=>'first_name','phone'=>'phone','address'=>'address',
                'date_naissance' => $dateCol,
                'date_of_birth'  => $dateCol,
              ]
            : [
                'nom'=>'nom', 'prenom'=>'prenom', 'telephone'=>'telephone', 'adresse'=>'adresse',
                'email'=>'email', 'bio'=>'bio', 'avatar_path'=>'avatar_path',
                // tolérance : si on me donne des clés EN, je les mappe vers FR
                'last_name'=>'nom','first_name'=>'prenom','phone'=>'telephone','address'=>'adresse',
                'date_naissance' => $dateCol,
                'date_of_birth'  => $dateCol,
              ];

        // Construction dynamique du SET
        $set = [];
        $p = [':id'=>$id];
        foreach ($data as $k=>$v) {
            if (!isset($map[$k])) continue; // clé non supportée -> on ignore
            $col = $map[$k];
            $val = is_string($v) ? trim($v) : $v;
            $set[] = "$col = :$col";
            $p[":$col"] = ($val === '') ? null : $val;
        }
        if (!$set) return false; // rien à mettre à jour

        $sql = 'UPDATE users SET '.implode(', ', $set).', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
        try {
            return self::pdo()->prepare($sql)->execute($p);
        } catch (\Throwable $e) {
            error_log('[User::updateProfile] '.$e->getMessage());
            return false;
        }
    }

    /** Met à jour le mot de passe (hash par défaut de PHP = bcrypt/argon) */
    public static function updatePassword(int $id, string $plain): bool {
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        try {
            return self::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute([':h'=>$hash, ':id'=>$id]);
        } catch (\Throwable $e) {
            error_log('[User::updatePassword] '.$e->getMessage());
            return false;
        }
    }

    /** Met à jour le chemin d’avatar */
    public static function updateAvatar(int $id, string $path): bool {
        try {
            return self::pdo()->prepare('UPDATE users SET avatar_path = :a WHERE id = :id')
                ->execute([':a'=>$path, ':id'=>$id]);
        } catch (\Throwable $e) {
            error_log('[User::updateAvatar] '.$e->getMessage());
            return false;
        }
    }

    /* ============================================================
       Complétude de profil (utile pour onboarding)
       ============================================================ */

    /** Indique si un mot de passe est bien défini */
    public static function passwordIsSet(int $id): bool {
        $st = self::pdo()->prepare('SELECT password_hash FROM users WHERE id=:id');
        $st->execute([':id'=>$id]);
        $hash = (string)$st->fetchColumn();
        return $hash !== '';
    }

    /**
     * Vérifie la complétude du profil et renvoie :
     *  - complete: bool
     *  - missing : liste des champs manquants (nom, prenom, email, telephone, adresse, avatar, preferences)
     */
    public static function isProfileComplete(int $id): array {
        $u = self::findById($id) ?? [];
        $missing = [];

        foreach (['nom','prenom','email','telephone','adresse'] as $f) {
            if (empty(trim((string)($u[$f] ?? '')))) { $missing[] = $f; }
        }
        if (empty($u['avatar_path'])) { $missing[] = 'avatar'; }

        // Les préférences chauffeur peuvent être obligatoires selon ton UX
        try {
            if (!\App\Models\UserPreferences::exists($id)) { $missing[] = 'preferences'; }
        } catch (\Throwable $e) {
            // si la table/feature n'existe pas encore, je signale quand même "preferences"
            $missing[] = 'preferences';
        }
        return ['complete' => count($missing) === 0, 'missing' => $missing];
    }

    /* ============================================================
       AJOUTS orientés admin
       ============================================================ */

    /**
     * Crée un employé (rôle EMPLOYEE) de façon robuste :
     * - vérifie l'unicité email
     * - détecte les colonnes disponibles (password/password_hash, suspended…)
     * - gère FR/EN pour nom/prénom
     */
    public static function createEmployee(string $email, string $password, ?string $nom = null, ?string $prenom = null): int
    {
        $pdo = self::pdo();

        /* Unicité email */
        $st = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        if ($st->fetchColumn()) {
            throw new \RuntimeException('email_exists');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        /* Colonnes/valeurs dynamiques selon le schéma réel */
        $cols = ['email'];
        $vals = [':email' => $email];

        if (self::colExists('password_hash')) { $cols[] = 'password_hash'; $vals[':pwd'] = $hash; }
        elseif (self::colExists('password')) {  $cols[] = 'password';      $vals[':pwd'] = $hash; }

        if (self::colExists('role'))    { $cols[] = 'role';    $vals[':role']    = 'EMPLOYEE'; }
        if (self::colExists('credits')) { $cols[] = 'credits'; $vals[':credits'] = 0; }

        if (self::colExists('is_suspended')) { $cols[] = 'is_suspended'; $vals[':susp'] = 0; }
        elseif (self::colExists('suspended')) { $cols[] = 'suspended';   $vals[':susp'] = 0; }

        /* nom/prenom compatibles FR/EN */
        if ($nom !== null) {
            if (self::colExists('nom'))           { $cols[] = 'nom';        $vals[':nom'] = $nom; }
            elseif (self::colExists('last_name')) { $cols[] = 'last_name';  $vals[':nom'] = $nom; }
            elseif (self::colExists('name'))      { $cols[] = 'name';       $vals[':nom'] = $nom; }
        }
        if ($prenom !== null) {
            if (self::colExists('prenom'))         { $cols[] = 'prenom';     $vals[':pre'] = $prenom; }
            elseif (self::colExists('first_name')) { $cols[] = 'first_name'; $vals[':pre'] = $prenom; }
        }

        if (self::colExists('created_at')) { $cols[] = 'created_at'; $vals[':created'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s'); }

        /* Construction de l'INSERT (colonnes/valeurs alignées) */
        $placeholders = [];
        foreach ($vals as $k => $_) { $placeholders[] = $k; }

        $sql = "INSERT INTO users (".implode(',', $cols).") VALUES (".implode(',', $placeholders).")";
        $st  = $pdo->prepare($sql);
        $st->execute($vals);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Suspends / réactive un compte utilisateur (si la colonne existe).
     * Renvoie true même si la BDD ne possède pas de colonne de suspension,
     * pour garder la logique applicative simple côté contrôleur.
     */
    public static function setSuspended(int $userId, bool $suspend): bool
    {
        $pdo = self::pdo();
        $col = null;
        if (self::colExists('is_suspended')) { $col = 'is_suspended'; }
        elseif (self::colExists('suspended')) { $col = 'suspended'; }

        if ($col === null) {
            return true; // schéma sans suspension -> on considère OK
        }

        $sql = "UPDATE users SET {$col} = :v WHERE id = :id";
        $st  = $pdo->prepare($sql);
        return $st->execute([':v' => ($suspend ? 1 : 0), ':id' => $userId]);
    }

    /* ============================================================
       Crédit auto périodique (top-up)
       ============================================================ */

    /**
     * Ajoute automatiquement des crédits si la dernière recharge
     * remonte au-delà d’un certain intervalle (par défaut 14 jours).
     * - $intervalSpec suit le format DateInterval (ex. 'P14D', 'P1M')
     */
    public static function topUpCreditsIfDue(int $userId, int $amount, string $intervalSpec = 'P14D'): bool {
        try {
            $threshold = (new \DateTimeImmutable())->sub(new \DateInterval($intervalSpec))->format('Y-m-d H:i:s');
            $sql = "UPDATE users
                    SET credits = credits + :a, last_credit_topup = NOW()
                    WHERE id = :id
                      AND (last_credit_topup IS NULL OR last_credit_topup <= :th)";
            $st = self::pdo()->prepare($sql);
            $st->execute([':a'=>$amount, ':id'=>$userId, ':th'=>$threshold]);
            return $st->rowCount() > 0; // true si une ligne a été impactée
        } catch (\Throwable $e) {
            error_log('[User::topUpCreditsIfDue] '.$e->getMessage());
            return false;
        }
    }
}

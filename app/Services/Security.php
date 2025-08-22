<?php
namespace App\Security;

final class Security {
  private static function boot(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
  }

  public static function user(): ?array {
    self::boot(); return $_SESSION['user'] ?? null;
  }

  public static function check(): bool {
    self::boot(); return isset($_SESSION['user']);
  }

  public static function role(): ?string {
    self::boot(); return $_SESSION['user']['role'] ?? null;
  }

  public static function ensure(array $roles): void {
    self::boot();
    if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? null, $roles, true) || (int)($_SESSION['user']['is_suspended'] ?? 0) === 1) {
      http_response_code(403);
      echo '<h1>Accès refusé</h1>';
      exit;
    }
  }

  public static function redirectByRole(): void {
    self::boot();
    $r = $_SESSION['user']['role'] ?? 'USER';
    if ($r === 'ADMIN')    { header('Location: /admin');    exit; }
    if ($r === 'EMPLOYEE') { header('Location: /employee'); exit; }
    header('Location: /dashboard'); exit;
  }
}

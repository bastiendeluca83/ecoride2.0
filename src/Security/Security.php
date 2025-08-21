<?php
namespace App\Security;

final class Security {
  public static function user(): ?array { return $_SESSION['user'] ?? null; }
  public static function check(): bool { return isset($_SESSION['user']); }
  public static function role(): ?string { return $_SESSION['user']['role'] ?? null; }
  public static function ensure(array $roles): void {
    if (!self::check() || !in_array(self::role(), $roles, true) || (int)($_SESSION['user']['is_suspended'] ?? 0) === 1) {
      http_response_code(403);
      echo '<h1>Accès refusé</h1>';
      exit;
    }
  }
  public static function redirectByRole(): void {
    $r = self::role();
    if ($r === 'ADMIN') { header('Location: /admin'); exit; }
    if ($r === 'EMPLOYEE') { header('Location: /employee'); exit; }
    header('Location: /dashboard'); exit;
  }
}

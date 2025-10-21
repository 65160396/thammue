<?php
require_once __DIR__ . '/../_config.php';

function notify(PDO $pdo, int $user_id, string $type, string $title, ?string $body=null, ?string $link=null): void {
  $stmt = $pdo->prepare(
    "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?,?,?,?,?)"
  );
  $stmt->execute([$user_id, $type, $title, $body, $link]);
}

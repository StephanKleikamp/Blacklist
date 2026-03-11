<?php
/**
 * install.php – Einmalig aufrufen, um die Datenbanktabellen anzulegen.
 * DANACH DIESE DATEI LÖSCHEN oder per .htaccess sperren!
 */
require_once __DIR__ . '/config.php';

$sql = "
CREATE TABLE IF NOT EXISTS `clear_users` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`     VARCHAR(50)  NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clear_lists` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `color`      VARCHAR(7)   NOT NULL DEFAULT '#FF6B6B',
    `position`   INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `clear_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clear_items` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `list_id`    INT UNSIGNED NOT NULL,
    `text`       VARCHAR(500) NOT NULL,
    `completed`  TINYINT(1)   NOT NULL DEFAULT 0,
    `position`   INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`list_id`) REFERENCES `clear_lists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo = db();
    // Execute each statement individually
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt) $pdo->exec($stmt);
    }
    echo '<p style="font-family:sans-serif;color:green;">✓ Tabellen erfolgreich angelegt.</p>';
    echo '<p style="font-family:sans-serif;">Jetzt kannst du unter <a href="index.php">index.php</a> einen ersten Benutzer registrieren.</p>';
    echo '<p style="font-family:sans-serif;color:red;"><strong>Bitte diese Datei (install.php) jetzt löschen!</strong></p>';
} catch (Exception $e) {
    echo '<p style="font-family:sans-serif;color:red;">Fehler: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

<?php
// ============================================================
// Datenbankverbindung – bitte hier deine Zugangsdaten eintragen
// ============================================================
define('DB_HOST',     'localhost');
define('DB_NAME',     'DEIN_DATENBANKNAME');
define('DB_USER',     'DEIN_DATENBANKBENUTZER');
define('DB_PASS',     'DEIN_DATENBANKPASSWORT');
define('DB_CHARSET',  'utf8mb4');

// Session-Sicherheitsschlüssel (beliebige lange Zeichenkette)
define('SECRET_KEY', 'aendere-mich-zu-einem-sicheren-zufallsstring');

// ============================================================
// Verbindung herstellen (wird von api.php eingebunden)
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

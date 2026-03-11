<?php
/**
 * api.php – REST-API für die Clear-WebApp
 *
 * Routen (alle via ?action=...):
 *   POST register          – Benutzer anlegen
 *   POST login             – einloggen
 *   POST logout            – ausloggen
 *   GET  me                – Session prüfen
 *
 *   GET  lists             – alle Listen des Benutzers
 *   POST lists/create      – neue Liste anlegen
 *   POST lists/update      – Liste umbenennen / Farbe ändern
 *   POST lists/delete      – Liste löschen
 *   POST lists/reorder     – Reihenfolge speichern
 *
 *   GET  items&list_id=X   – alle Items einer Liste
 *   POST items/create      – neues Item anlegen
 *   POST items/update      – Item bearbeiten / abhaken
 *   POST items/delete      – Item löschen
 *   POST items/reorder     – Reihenfolge speichern
 */

require_once __DIR__ . '/config.php';

// ── CORS / Headers ───────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Session ──────────────────────────────────────────────────
session_name('clear_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30, // 30 Tage
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── Helpers ──────────────────────────────────────────────────
function json_ok(mixed $data = null): never {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function require_auth(): int {
    if (empty($_SESSION['user_id'])) json_err('Nicht eingeloggt', 401);
    return (int) $_SESSION['user_id'];
}

function require_int(array $data, string $key): int {
    if (!isset($data[$key]) || !is_numeric($data[$key])) json_err("Pflichtfeld: $key");
    return (int) $data[$key];
}

function require_str(array $data, string $key, int $max = 500): string {
    if (!isset($data[$key]) || trim($data[$key]) === '') json_err("Pflichtfeld: $key");
    return mb_substr(trim($data[$key]), 0, $max);
}

// ── Routing ──────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match (true) {

    // ── Auth ────────────────────────────────────────────────
    $action === 'register' && $method === 'POST' => (function () {
        $b    = body();
        $user = require_str($b, 'username', 50);
        $pass = require_str($b, 'password', 200);
        if (strlen($pass) < 6) json_err('Passwort mindestens 6 Zeichen');
        $pdo = db();
        if ($pdo->query("SELECT COUNT(*) FROM clear_users WHERE username = " . $pdo->quote($user))->fetchColumn()) {
            json_err('Benutzername bereits vergeben');
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO clear_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$user, $hash]);
        $_SESSION['user_id']  = (int) $pdo->lastInsertId();
        $_SESSION['username'] = $user;
        json_ok(['username' => $user]);
    })(),

    $action === 'login' && $method === 'POST' => (function () {
        $b    = body();
        $user = require_str($b, 'username', 50);
        $pass = require_str($b, 'password', 200);
        $pdo  = db();
        $row  = $pdo->prepare('SELECT id, password_hash FROM clear_users WHERE username = ?');
        $row->execute([$user]);
        $row  = $row->fetch();
        if (!$row || !password_verify($pass, $row['password_hash'])) json_err('Ungültige Zugangsdaten', 401);
        $_SESSION['user_id']  = (int) $row['id'];
        $_SESSION['username'] = $user;
        json_ok(['username' => $user]);
    })(),

    $action === 'logout' => (function () {
        session_destroy();
        json_ok();
    })(),

    $action === 'me' => (function () {
        if (empty($_SESSION['user_id'])) json_err('Nicht eingeloggt', 401);
        json_ok(['username' => $_SESSION['username']]);
    })(),

    // ── Lists ───────────────────────────────────────────────
    $action === 'lists' && $method === 'GET' => (function () {
        $uid  = require_auth();
        $stmt = db()->prepare(
            'SELECT id, title, color, position FROM clear_lists WHERE user_id = ? ORDER BY position, id'
        );
        $stmt->execute([$uid]);
        json_ok($stmt->fetchAll());
    })(),

    $action === 'lists/create' && $method === 'POST' => (function () {
        $uid   = require_auth();
        $b     = body();
        $title = require_str($b, 'title', 255);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $b['color'] ?? '') ? $b['color'] : '#FF6B6B';
        $pdo   = db();
        $pos   = (int) $pdo->prepare('SELECT COALESCE(MAX(position),0)+1 FROM clear_lists WHERE user_id = ?')
                            ->execute([$uid]) ? $pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM clear_lists WHERE user_id=$uid")->fetchColumn() : 1;
        $stmt  = $pdo->prepare('INSERT INTO clear_lists (user_id, title, color, position) VALUES (?,?,?,?)');
        $stmt->execute([$uid, $title, $color, $pos]);
        $id = (int) $pdo->lastInsertId();
        json_ok(['id' => $id, 'title' => $title, 'color' => $color, 'position' => $pos]);
    })(),

    $action === 'lists/update' && $method === 'POST' => (function () {
        $uid = require_auth();
        $b   = body();
        $id  = require_int($b, 'id');
        $pdo = db();
        // Ownership check
        $own = $pdo->prepare('SELECT id FROM clear_lists WHERE id=? AND user_id=?');
        $own->execute([$id, $uid]);
        if (!$own->fetch()) json_err('Nicht gefunden', 404);

        $fields = [];
        $vals   = [];
        if (isset($b['title'])) { $fields[] = 'title=?';  $vals[] = mb_substr(trim($b['title']), 0, 255); }
        if (isset($b['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $b['color'])) {
            $fields[] = 'color=?'; $vals[] = $b['color'];
        }
        if (empty($fields)) json_err('Keine Felder zum Aktualisieren');
        $vals[] = $id;
        $pdo->prepare('UPDATE clear_lists SET ' . implode(',', $fields) . ' WHERE id=?')->execute($vals);
        json_ok();
    })(),

    $action === 'lists/delete' && $method === 'POST' => (function () {
        $uid = require_auth();
        $b   = body();
        $id  = require_int($b, 'id');
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM clear_lists WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        if (!$stmt->rowCount()) json_err('Nicht gefunden', 404);
        json_ok();
    })(),

    $action === 'lists/reorder' && $method === 'POST' => (function () {
        $uid    = require_auth();
        $b      = body();
        $ids    = $b['ids'] ?? [];
        if (!is_array($ids)) json_err('ids muss ein Array sein');
        $pdo    = db();
        $update = $pdo->prepare('UPDATE clear_lists SET position=? WHERE id=? AND user_id=?');
        foreach ($ids as $pos => $id) {
            $update->execute([(int)$pos, (int)$id, $uid]);
        }
        json_ok();
    })(),

    // ── Items ───────────────────────────────────────────────
    $action === 'items' && $method === 'GET' => (function () {
        $uid    = require_auth();
        $listId = isset($_GET['list_id']) ? (int)$_GET['list_id'] : 0;
        if (!$listId) json_err('list_id fehlt');
        $pdo    = db();
        // Ownership check
        $own = $pdo->prepare('SELECT id FROM clear_lists WHERE id=? AND user_id=?');
        $own->execute([$listId, $uid]);
        if (!$own->fetch()) json_err('Nicht gefunden', 404);
        $stmt = $pdo->prepare(
            'SELECT id, text, completed, position FROM clear_items WHERE list_id=? ORDER BY position, id'
        );
        $stmt->execute([$listId]);
        json_ok($stmt->fetchAll());
    })(),

    $action === 'items/create' && $method === 'POST' => (function () {
        $uid    = require_auth();
        $b      = body();
        $listId = require_int($b, 'list_id');
        $text   = require_str($b, 'text', 500);
        $pdo    = db();
        $own    = $pdo->prepare('SELECT id FROM clear_lists WHERE id=? AND user_id=?');
        $own->execute([$listId, $uid]);
        if (!$own->fetch()) json_err('Nicht gefunden', 404);
        $pos  = (int) $pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM clear_items WHERE list_id=$listId")->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO clear_items (list_id, text, position) VALUES (?,?,?)');
        $stmt->execute([$listId, $text, $pos]);
        $id = (int) $pdo->lastInsertId();
        json_ok(['id' => $id, 'text' => $text, 'completed' => 0, 'position' => $pos]);
    })(),

    $action === 'items/update' && $method === 'POST' => (function () {
        $uid = require_auth();
        $b   = body();
        $id  = require_int($b, 'id');
        $pdo = db();
        // Ownership via join
        $own = $pdo->prepare(
            'SELECT i.id FROM clear_items i JOIN clear_lists l ON l.id=i.list_id WHERE i.id=? AND l.user_id=?'
        );
        $own->execute([$id, $uid]);
        if (!$own->fetch()) json_err('Nicht gefunden', 404);

        $fields = [];
        $vals   = [];
        if (isset($b['text']))      { $fields[] = 'text=?';      $vals[] = mb_substr(trim($b['text']), 0, 500); }
        if (isset($b['completed'])) { $fields[] = 'completed=?'; $vals[] = $b['completed'] ? 1 : 0; }
        if (empty($fields)) json_err('Keine Felder');
        $vals[] = $id;
        $pdo->prepare('UPDATE clear_items SET ' . implode(',', $fields) . ' WHERE id=?')->execute($vals);
        json_ok();
    })(),

    $action === 'items/delete' && $method === 'POST' => (function () {
        $uid = require_auth();
        $b   = body();
        $id  = require_int($b, 'id');
        $pdo = db();
        $stmt = $pdo->prepare(
            'DELETE i FROM clear_items i JOIN clear_lists l ON l.id=i.list_id WHERE i.id=? AND l.user_id=?'
        );
        $stmt->execute([$id, $uid]);
        if (!$stmt->rowCount()) json_err('Nicht gefunden', 404);
        json_ok();
    })(),

    $action === 'items/reorder' && $method === 'POST' => (function () {
        $uid    = require_auth();
        $b      = body();
        $ids    = $b['ids'] ?? [];
        $listId = require_int($b, 'list_id');
        if (!is_array($ids)) json_err('ids muss ein Array sein');
        $pdo    = db();
        $update = $pdo->prepare(
            'UPDATE clear_items i JOIN clear_lists l ON l.id=i.list_id SET i.position=? WHERE i.id=? AND l.user_id=?'
        );
        foreach ($ids as $pos => $id) {
            $update->execute([(int)$pos, (int)$id, $uid]);
        }
        json_ok();
    })(),

    default => json_err('Unbekannte Aktion', 404),
};

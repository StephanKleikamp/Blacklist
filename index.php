<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="theme-color" content="#1a1a2e">
  <title>Clear Lists</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     LOGIN SCREEN
════════════════════════════════════════════════════════════ -->
<div id="screen-auth" class="screen active">
  <div class="auth-box">
    <h1 class="auth-logo">Clear</h1>
    <p class="auth-sub">Deine Listen, überall.</p>

    <form id="form-login" autocomplete="on">
      <input type="text"     id="auth-username" name="username" placeholder="Benutzername" autocomplete="username" required>
      <input type="password" id="auth-password" name="password" placeholder="Passwort"     autocomplete="current-password" required>
      <button type="submit" class="btn-primary">Einloggen</button>
      <button type="button" id="btn-show-register" class="btn-ghost">Noch kein Konto? Registrieren</button>
    </form>

    <form id="form-register" class="hidden" autocomplete="on">
      <input type="text"     id="reg-username" name="username" placeholder="Benutzername" autocomplete="username" required>
      <input type="password" id="reg-password" name="password" placeholder="Passwort (min. 6 Zeichen)" autocomplete="new-password" required>
      <button type="submit" class="btn-primary">Konto erstellen</button>
      <button type="button" id="btn-show-login" class="btn-ghost">Zurück zum Login</button>
    </form>

    <p id="auth-error" class="error-msg hidden"></p>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     LISTS OVERVIEW
════════════════════════════════════════════════════════════ -->
<div id="screen-lists" class="screen">
  <header class="app-header">
    <button id="btn-logout" class="icon-btn" title="Ausloggen">&#x2715;</button>
    <h1 class="header-title">Meine Listen</h1>
    <button id="btn-add-list" class="icon-btn icon-btn--add" title="Neue Liste">&#x2B;</button>
  </header>

  <ul id="list-container" class="list-container">
    <!-- rendered by JS -->
  </ul>

  <p id="lists-empty" class="empty-hint hidden">Tippe auf + um eine neue Liste anzulegen.</p>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ITEMS VIEW (single list)
════════════════════════════════════════════════════════════ -->
<div id="screen-items" class="screen">
  <header class="app-header" id="items-header">
    <button id="btn-back" class="icon-btn" title="Zurück">&#x2190;</button>
    <h1 id="items-title" class="header-title editable" title="Tippen zum Umbenennen">Liste</h1>
    <button id="btn-add-item" class="icon-btn icon-btn--add" title="Neues Item">&#x2B;</button>
  </header>

  <ul id="item-container" class="item-container">
    <!-- rendered by JS -->
  </ul>

  <p id="items-empty" class="empty-hint hidden">Tippe auf + um ein neues Element hinzuzufügen.</p>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL – neuer Listentitel / Item-Text
════════════════════════════════════════════════════════════ -->
<div id="modal-overlay" class="modal-overlay hidden">
  <div class="modal">
    <p id="modal-label" class="modal-label">Neue Liste</p>
    <input id="modal-input" type="text" class="modal-input" placeholder="" maxlength="255">
    <div class="modal-row">
      <!-- color picker shown only for lists -->
      <span id="modal-color-wrap" class="color-wrap">
        <label class="color-label" for="modal-color">Farbe</label>
        <input type="color" id="modal-color" value="#FF6B6B">
      </span>
      <button id="modal-cancel" class="btn-ghost">Abbrechen</button>
      <button id="modal-ok"     class="btn-primary">OK</button>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
</body>
</html>

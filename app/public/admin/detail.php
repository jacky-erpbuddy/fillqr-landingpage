<?php
require_once __DIR__ . '/../../src/app.php';

session_start();

$pdo      = db();
$tenantId = resolveTenantIdByHost($pdo);

// Tenant inkl. Logo laden
$stmt = $pdo->prepare('SELECT name, logo_path FROM tbl_tenant WHERE id = ?');
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Ihr Verein', 'logo_path' => null];
$tenantName = $tenant['name'] ?? 'Demo-Verein';
$tenantLogo = !empty($tenant['logo_path']) ? $tenant['logo_path'] : null;



// CSRF-Token für Status-Formular vorbereiten
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ID aus GET holen und validieren
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "Ungültige Antrags-ID.";
    exit;
}

// Antrag für diesen Tenant laden
$stmt = $pdo->prepare("
    SELECT *
    FROM tbl_application
    WHERE tenant_id = :tenant_id
      AND id = :id
    LIMIT 1
");
$stmt->execute([
    ':tenant_id' => $tenantId,
    ':id'        => $id,
]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    http_response_code(404);
    echo "Antrag wurde nicht gefunden.";
    exit;
}

// Vereinsname (für Kopf)
$stmt = $pdo->prepare('SELECT name FROM tbl_tenant WHERE id = ?');
$stmt->execute([$tenantId]);
$tenantName = $stmt->fetchColumn() ?: 'Ihr Verein';

// Warnungen aus Event-Log nachladen (falls has_warnings = 1)
$warnings = [];
if (!empty($app['has_warnings'])) {
    $stmtW = $pdo->prepare("
        SELECT event
        FROM tbl_application_event
        WHERE application_id = :app_id
        ORDER BY ts DESC, id DESC
        LIMIT 5
    ");
    $stmtW->execute([':app_id' => $app['id']]);

    while ($rowW = $stmtW->fetch(PDO::FETCH_ASSOC)) {
        $data = json_decode($rowW['event'] ?? '', true);
        if (!is_array($data)) {
            continue;
        }
        if (!empty($data['warnings']) && is_array($data['warnings'])) {
            $warnings = $data['warnings'];
            break;
        }
    }
}

// Mapping Warn-Code → lesbarer Text
$warningMessages = [
    'birthdate_invalid'      => 'Geburtsdatum konnte nicht eindeutig ausgewertet werden – bitte prüfen.',
    'minor_flag_maybe_wrong' => 'Checkbox „Ich bin minderjährig“ ist gesetzt, das berechnete Alter liegt aber bei mindestens 18 Jahren.',
    'no_sepa_mandate'        => 'Es liegt kein SEPA-Lastschriftmandat vor – Beitragseinzug ggf. separat klären.',
];
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Antrag #<?= (int)$app['id'] ?> – <?= htmlspecialchars($tenantName) ?></title>

  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- gemeinsame Basis-Styles -->
  <link rel="stylesheet" href="/assets/css/base.css?v=2">

  <!-- Detail-spezifische Styles -->
  <style>
    .page--admin {
      max-width: 900px;
      margin: 0 auto;
    }

    .card--admin {
      padding: var(--spacing-md) var(--spacing-md) var(--spacing-lg);
    }

    /* Kopfzeile mit Status / Form */
    .status-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px var(--spacing-md);
      align-items: center;
      justify-content: space-between;
      margin-bottom: var(--spacing-sm);
    }

    .status-form {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 8px;
      align-items: center;
      font-size: 0.8rem;
    }

    .status-form label {
      color: var(--color-text-muted);
    }

    .status-form select {
      padding: 5px 8px;
      border-radius: var(--radius-md);
      border: 1px solid var(--color-border);
      font: inherit;
      font-size: var(--font-size-sm);
      background: rgba(26, 26, 36, 0.6);
      color: var(--color-text);
    }

    .status-form select:focus {
      outline: none;
      border-color: var(--color-cyan);
    }

    .status-form button {
      padding: 5px 14px;
      border-radius: var(--radius-md);
      border: none;
      background: linear-gradient(135deg, var(--color-cyan) 0%, var(--color-green) 100%);
      color: var(--color-bg);
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition-normal);
    }

    .status-form button:hover {
      box-shadow: 0 0 20px rgba(91, 203, 222, 0.4);
      transform: translateY(-1px);
    }

    /* Mobile Detail */
    @media (max-width: 640px) {
      .status-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }

      .status-form {
        width: 100%;
        justify-content: flex-start;
      }

      dl {
        grid-template-columns: 1fr;
      }

      dl dt {
        margin-top: var(--spacing-xs);
      }

      .member-photo {
        max-width: 260px;
        width: 100%;
      }
    }
  </style>
</head>

<body>
    <div class="page page--admin">

    <div class="header-row">
      <div>
        <div class="back-link">
          &larr; <a href="index.php">Zurück zur Übersicht</a>
        </div>

        <h1>
          Antrag #<?= (int)$app['id'] ?>
          – <?= htmlspecialchars($app['full_name'] ?? '') ?>
        </h1>
        <p class="subtitle">
          Verein: <strong><?= htmlspecialchars($tenantName) ?></strong><br>
          Eingegangen am <?= htmlspecialchars($app['created_at'] ?? '') ?>
          <?php if (!empty($app['updated_at'])): ?>
            · letzte Änderung <?= htmlspecialchars($app['updated_at']) ?>
          <?php endif; ?>
        </p>
      </div>

      <div class="header-right">
        <?php if (!empty($tenantLogo)): ?>
          <img src="<?= htmlspecialchars($tenantLogo) ?>"
               alt="Vereinslogo"
               style="display:block;max-width:130px;max-height:48px;border-radius:8px;">
        <?php else: ?>
          <div class="logo-placeholder">
            Hier könnte<br>Ihr Logo<br>stehen
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card card--admin">

      <!-- Status-Block usw. wie gehabt -->

      <?php
        $pillClass = 'status-new';
        if (!empty($app['has_warnings'])) {
            $pillClass = 'status-warn';
        }
      ?>
      <div class="status-row">
        <p style="margin:0;">
          <span class="status-pill <?= $pillClass ?>">
            Status: <?= htmlspecialchars($app['status'] ?? '') ?>
            <?php if (!empty($app['has_warnings'])): ?>
              · ⚠ Hinweise
            <?php endif; ?>
          </span>
          <?php if (!empty($app['is_minor'])): ?>
            <span class="tag">Minderjährig</span>
          <?php endif; ?>
          <?php if (!empty($app['style'])): ?>
            <span class="tag">Sparte: <?= htmlspecialchars($app['style']) ?></span>
          <?php endif; ?>
          <?php if (!empty($app['membership_type_code'])): ?>
            <span class="tag">Tarif: <?= htmlspecialchars($app['membership_type_code']) ?></span>
          <?php endif; ?>
        </p>

        <form method="post" action="update_status.php" class="status-form">
          <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <label for="status-select">Status ändern:</label>
          <select id="status-select" name="status">
            <option value="new"      <?= $app['status'] === 'new'      ? 'selected' : '' ?>>Neu</option>
            <option value="reviewed" <?= $app['status'] === 'reviewed' ? 'selected' : '' ?>>Geprüft</option>
            <option value="exported" <?= $app['status'] === 'exported' ? 'selected' : '' ?>>Exportiert</option>
            <option value="archived" <?= $app['status'] === 'archived' ? 'selected' : '' ?>>Archiviert</option>
          </select>
          <button type="submit">Speichern</button>
        </form>
      </div>

      <h2>Angaben zur Person</h2>
      <dl>
        <dt>Name</dt>
        <dd><?= htmlspecialchars($app['full_name'] ?? '') ?></dd>

        <dt>Geburtsdatum</dt>
        <dd><?= htmlspecialchars($app['birthdate'] ?? '–') ?></dd>

        <dt>Adresse</dt>
        <dd>
          <?php if (!empty($app['street'])): ?>
            <?= htmlspecialchars($app['street']) ?><br>
          <?php endif; ?>
          <?php if (!empty($app['zip']) || !empty($app['city'])): ?>
            <?= htmlspecialchars(trim(($app['zip'] ?? '') . ' ' . ($app['city'] ?? ''))) ?>
          <?php else: ?>
            <span class="muted">–</span>
          <?php endif; ?>
        </dd>

        <dt>E-Mail</dt>
        <dd>
          <?php if (!empty($app['email'])): ?>
            <a href="mailto:<?= htmlspecialchars($app['email']) ?>">
              <?= htmlspecialchars($app['email']) ?>
            </a>
          <?php else: ?>
            <span class="muted">–</span>
          <?php endif; ?>
        </dd>

        <dt>Telefon</dt>
        <dd><?= htmlspecialchars($app['phone'] ?? '') ?: '<span class="muted">–</span>' ?></dd>
      </dl>

      <h2>Gesetzlicher Vertreter</h2>
      <dl>
        <dt>Name</dt>
        <dd><?= htmlspecialchars($app['guardian_name'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>Beziehung</dt>
        <dd><?= htmlspecialchars($app['guardian_relation'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>E-Mail</dt>
        <dd>
          <?php if (!empty($app['guardian_email'])): ?>
            <a href="mailto:<?= htmlspecialchars($app['guardian_email']) ?>">
              <?= htmlspecialchars($app['guardian_email']) ?>
            </a>
          <?php else: ?>
            <span class="muted">–</span>
          <?php endif; ?>
        </dd>

        <dt>Telefon</dt>
        <dd><?= htmlspecialchars($app['guardian_phone'] ?? '') ?: '<span class="muted">–</span>' ?></dd>
      </dl>


      
      <h2>Mitgliedsfoto</h2>
      <dl>
        <dt>Foto</dt>
        <dd>
          <?php if (!empty($app['photo_path'])): ?>
            <div class="member-photo">
              <img
                src="<?= htmlspecialchars('/' . ltrim($app['photo_path'], '/')) ?>"
                alt="Mitgliedsfoto von <?= htmlspecialchars($app['full_name'] ?? '') ?>">
            </div>
          <?php else: ?>
            <span class="muted">Kein Foto hinterlegt.</span>
          <?php endif; ?>
        </dd>
      </dl>




      <h2>Mitgliedschaft & Eintritt</h2>
      <dl>
        <dt>Disziplin / Sparte</dt>
        <dd><?= htmlspecialchars($app['style'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>Mitgliedschaft</dt>
        <dd><?= htmlspecialchars($app['membership_type_code'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>Eintrittstermin</dt>
        <dd><?= htmlspecialchars($app['entry_date'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>Bemerkungen</dt>
        <dd><?= nl2br(htmlspecialchars($app['remarks'] ?? '')) ?: '<span class="muted">–</span>' ?></dd>
      </dl>

      <h2>SEPA-Lastschrift</h2>
      <dl>
        <dt>Kontoinhaber</dt>
        <dd><?= htmlspecialchars($app['sepa_account_holder'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>IBAN</dt>
        <dd><?= htmlspecialchars($app['sepa_iban'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>BIC</dt>
        <dd><?= htmlspecialchars($app['sepa_bic'] ?? '') ?: '<span class="muted">–</span>' ?></dd>

        <dt>Mandat erteilt</dt>
        <dd>
          <?php if (!empty($app['sepa_consent'])): ?>
            <?= htmlspecialchars($app['sepa_consent']) ?>
          <?php else: ?>
            <span class="muted">kein Mandat gespeichert</span>
          <?php endif; ?>
        </dd>
      </dl>

      <h2>System-Infos</h2>
      <dl>
        <dt>Interne ID</dt>
        <dd><?= (int)$app['id'] ?></dd>

        <dt>Tenant-ID</dt>
        <dd><?= (int)$app['tenant_id'] ?></dd>

        <dt>Warnungen</dt>
        <dd>
          <?php if (!empty($warnings)): ?>
            <ul class="warn-list">
              <?php foreach ($warnings as $code): ?>
                <?php $text = $warningMessages[$code] ?? ('Unbekannte Warnung: ' . $code); ?>
                <li><?= htmlspecialchars($text) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php elseif (!empty($app['has_warnings'])): ?>
            <span class="muted">
              Es liegen Warnungen vor, konnten aber nicht aus dem Event-Log gelesen werden.
            </span>
          <?php else: ?>
            <span class="muted">Keine Warnungen markiert.</span>
          <?php endif; ?>
        </dd>
      </dl>
    </div>
  </div>
</body>
</html>

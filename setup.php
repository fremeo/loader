<?php
// setup.php

declare(strict_types=1);

// Simple autoloader for our classes
spl_autoload_register(function ($class) {
    // Nur die beiden gewünschten Klassen berücksichtigen
    $allowed = ['Packagist', 'ComposerManager'];
    if (!in_array($class, $allowed, true)) {
        return;
    }

    $base = __DIR__ . DIRECTORY_SEPARATOR . 'setup' . DIRECTORY_SEPARATOR;
    $file = $base . $class . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});


// Constants
define('SETUP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'setup');
define('SYSTEM_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'system');
define('COMPOSER_PHAR', SETUP_DIR . DIRECTORY_SEPARATOR . 'composer.phar');
define('LOG_FILE', SETUP_DIR . DIRECTORY_SEPARATOR . 'log.txt');

// Ensure folders exist and composer.phar is present
function ensureEnvironment(): void {
    if (!is_dir(SETUP_DIR)) {
        mkdir(SETUP_DIR, 0775, true);
    }
    if (!is_dir(SYSTEM_DIR)) {
        mkdir(SYSTEM_DIR, 0775, true);
    }
    if (!is_file(COMPOSER_PHAR)) {
        // Download latest stable composer.phar
        // Official installer JSON points to stable; using static URL simplifies.
        $url = 'https://getcomposer.org/download/latest-stable/composer.phar';
        $ctx = stream_context_create(['http' => ['timeout' => 30]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            // Fallback: try main composer.phar URL
            $url2 = 'https://getcomposer.org/composer.phar';
            $data = @file_get_contents($url2, false, $ctx);
        }
        if ($data === false) {
            // Write a minimal error log
            file_put_contents(LOG_FILE, "[Error] Unable to download composer.phar.\n");
        } else {
            file_put_contents(COMPOSER_PHAR, $data);
        }
    }
    if (!is_file(LOG_FILE)) {
        file_put_contents(LOG_FILE, "");
    }
}



// Initialize
ensureEnvironment();

// Route AJAX actions
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
    header('Content-Type: application/json; charset=utf-8');
    $packagist = new Packagist();
    $composer = new ComposerManager(COMPOSER_PHAR, LOG_FILE);

    try {
        switch ($action) {
            case 'search':
                $tags = trim($_GET['tags'] ?? '');
                $query = trim($_GET['q'] ?? '');
                $list = $packagist->getProjectList($tags === 'none' ? '' : $tags, $query);
                // Merge install status/version info
                $installed = $composer->getInstalledPackages();
                foreach ($list as &$pkg) {
                    $name = $pkg['name'] ?? '';
                    if (isset($installed[$name])) {
                        $pkg['installed'] = true;
                        $pkg['installed_version'] = $installed[$name]['version'] ?? '';
                        $pkg['update_available'] = $installed[$name]['update_available'] ?? false;
                    } else {
                        $pkg['installed'] = false;
                        $pkg['installed_version'] = '';
                        $pkg['update_available'] = false;
                    }
                }
                echo json_encode(['ok' => true, 'data' => $list]);
                break;

            case 'versions':
                $package = trim($_GET['package'] ?? '');
                if ($package === '') {
                    echo json_encode(['ok' => false, 'error' => 'Missing package']);
                    break;
                }
                $info = $packagist->getProject($package);
                $versions = $info['versions'] ?? [];
                echo json_encode(['ok' => true, 'versions' => $versions, 'wiki' => $info['wiki'] ?? null, 'url' => $info['url'] ?? null, 'author' => $info['author'] ?? null, 'description' => $info['description'] ?? null]);
                break;

            case 'install':
                $packages = $_POST['packages'] ?? [];
                // packages: [{name, version}] array
                if (!is_array($packages) || empty($packages)) {
                    echo json_encode(['ok' => false, 'error' => 'No packages provided']);
                    break;
                }
                $result = $composer->installPackages($packages);
                echo json_encode($result);
                break;

            case 'update':
                $packages = $_POST['packages'] ?? [];
                if (!is_array($packages) || empty($packages)) {
                    echo json_encode(['ok' => false, 'error' => 'No packages provided']);
                    break;
                }
                $result = $composer->updatePackages($packages);
                echo json_encode($result);
                break;

            case 'remove':
                $packages = $_POST['packages'] ?? [];
                if (!is_array($packages) || empty($packages)) {
                    echo json_encode(['ok' => false, 'error' => 'No packages provided']);
                    break;
                }
                $result = $composer->removePackages($packages);
                echo json_encode($result);
                break;

            case 'log':
                $content = @file_get_contents(LOG_FILE);
                echo json_encode(['ok' => true, 'log' => $content ?: '']);
                break;

            case 'installed':
                $installed = $composer->getInstalledPackages();
                echo json_encode(['ok' => true, 'data' => $installed]);
                break;

            default:
                echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        }
    } catch (\Throwable $e) {
        file_put_contents(LOG_FILE, "[Exception] " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// From here: HTML UI
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>PHP App Setup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5.x CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background: #f8f9fa; }
        .status-installed { color: #198754; font-weight: 600; }
        .status-update { color: #ffc107; font-weight: 600; }
        .status-error { color: #dc3545; font-weight: 600; }
        .table-fixed { height: 50vh; overflow-y: auto; }
        .log-box { height: 25vh; overflow-y: auto; background:#111; color:#0f0; font-family: monospace; padding: 1rem; border-radius: 0.25rem; white-space: pre-wrap;}
        .pkg-link { text-decoration: none; }
        .wiki-btn { margin-left: .5rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">Setup</h1>

    <div class="card mb-3">
        <div class="card-body">
            <form id="searchForm" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Suche</label>
                    <input type="text" class="form-control" id="searchQuery" placeholder="Suchbegriffe...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tags</label>
                    <select class="form-select" id="searchTags">
                        <option value="none">Nichts</option>
                        <option value="phpapp-modul">phpapp-modul</option>
                        <option value="phpapp-template">phpapp-template</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Suchen</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card mb-3">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs" id="resultsTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" id="search-tab" data-bs-toggle="tab" data-bs-target="#searchPane" type="button" role="tab">Suche</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="installed-tab" data-bs-toggle="tab" data-bs-target="#installedPane" type="button" role="tab">Installierte</button>
      </li>
    </ul>
  </div>
  <div class="card-body tab-content">
    <!-- Suche -->
    <div class="tab-pane fade show active" id="searchPane" role="tabpanel">
      <div class="d-flex justify-content-end mb-2">
        <button class="btn btn-success btn-sm" id="btnInstall">Installieren</button>
      </div>
      <div class="table-fixed">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Auswahl</th><th>Projekt</th><th>Autor</th><th>Beschreibung</th><th>Status</th><th>Version</th>
            </tr>
          </thead>
          <tbody id="resultsBody"></tbody>
        </table>
      </div>
    </div>
    <!-- Installierte -->
    <div class="tab-pane fade" id="installedPane" role="tabpanel">
      <div class="d-flex justify-content-end mb-2">
        <button class="btn btn-warning btn-sm" id="btnUpdate">Aktualisieren</button>
        <button class="btn btn-danger btn-sm" id="btnRemove">Deinstallieren</button>
      </div>
      <div class="table-fixed">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Auswahl</th><th>Projekt</th><th>Autor</th><th>Beschreibung</th><th>Status</th><th>Version</th>
            </tr>
          </thead>
          <tbody id="installedBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Log anzeigen</span>
            <button class="btn btn-outline-secondary btn-sm" id="btnRefreshLog">Aktualisieren</button>
        </div>
        <div class="card-body">
            <div id="logBox" class="log-box"></div>
        </div>
    </div>
</div>

<script>
function statusBadge(pkg) {
    if (pkg.installed) {
        if (pkg.update_available) return '<span class="status-update">Update verfügbar</span>';
        return '<span class="status-installed">Installiert (' + (pkg.installed_version || '') + ')</span>';
    }
    return '<span class="text-muted">Nicht installiert</span>';
}

function loadInstalled() {
  $.getJSON('setup.php', {action: 'installed'}, function(res){
    if (res.ok) {
      const data = [];
      // res.data ist ein Objekt {name: {version, latest, update_available}}
      for (const [name, info] of Object.entries(res.data)) {
        data.push({
          name: name,
          description: '', // optional: aus Packagist nachladen
          url: 'https://packagist.org/packages/' + name,
          author: '',
          installed: true,
          installed_version: info.version,
          update_available: info.update_available
        });
      }
      renderRows(data, '#installedBody');
    }
  });
}

function loadVersions(packageName, selectEl) {
    $.getJSON('setup.php', {action: 'versions', package: packageName}, function(res) {
        if (res.ok) {
            const versions = res.versions || [];
            $(selectEl).empty();
            versions.forEach(function(v) {
                const label = v.version || v;
                $(selectEl).append($('<option>').val(label).text(label));
            });
            // Attach wiki button if available
            const wikiBtn = $(selectEl).closest('tr').find('.wiki-btn');
            if (res.wiki) {
                wikiBtn.removeClass('d-none').attr('href', res.wiki);
            } else {
                wikiBtn.addClass('d-none').attr('href', '#');
            }
        }
    });
}

function renderRows(items, targetSelector) {
  const tbody = $(targetSelector);
  tbody.empty();
  items.forEach(function(pkg) {
    const tr = $('<tr>');
    const checkbox = $('<input type="checkbox" class="form-check-input pkg-check">').data('name', pkg.name);
    const versionSelect = $('<select class="form-select form-select-sm version-select" style="min-width:140px;"></select>');
    tr.append($('<td>').append(checkbox));
    const projectLink = $('<a class="pkg-link" target="_blank">').attr('href', pkg.url || '#').text(pkg.name || '');
    const wikiBtn = $('<a class="btn btn-outline-info btn-sm wiki-btn d-none" target="_blank">Wiki</a>');
    tr.append($('<td>').append(projectLink).append(wikiBtn));
    tr.append($('<td>').text(pkg.author || ''));
    tr.append($('<td>').text(pkg.description || ''));
    tr.append($('<td>').html(statusBadge(pkg)));
    tr.append($('<td>').append(versionSelect));
    tbody.append(tr);
    loadVersions(pkg.name, versionSelect);
  });
}

function search() {
    const q = $('#searchQuery').val() || '';
    const tags = $('#searchTags').val() || 'none';
    $.getJSON('setup.php', {action: 'search', q: q, tags: tags}, function(res) {
        if (res.ok) {
            // Put installed first
            const data = res.data || [];
            data.sort(function(a,b){
                return (b.installed ? 1 : 0) - (a.installed ? 1 : 0);
            });
            renderRows(data, '#resultsBody');
        }
    });
}

function selectedPackages(targetSelector) {
  const pkgs = [];
  $(targetSelector + ' .pkg-check:checked').each(function(){
    const name = $(this).data('name');
    const version = $(this).closest('tr').find('.version-select').val() || '';
    pkgs.push({name: name, version: version});
  });
  return pkgs;
}

function refreshLog() {
    $.getJSON('setup.php', {action: 'log'}, function(res){
        if (res.ok) {
            $('#logBox').text(res.log);
            const box = document.getElementById('logBox');
            box.scrollTop = box.scrollHeight;
        }
    });
}
//setInterval(refreshLog, 2000);

$(function(){
    search();
    $('#searchForm').on('submit', function(e){ e.preventDefault(); search(); });
    $('#btnRefreshLog').on('click', function(){ refreshLog(); });

    $('#btnInstall').on('click', function(){
        const pkgs = selectedPackages('#resultsBody');
        if (pkgs.length === 0) return;
        $.ajax({
            url: 'setup.php?action=install',
            method: 'POST',
            data: {packages: pkgs},
            success: function(res){
                refreshLog();
                search();
				loadInstalled();
            }
        });
    });

    $('#btnUpdate').on('click', function(){
	  const pkgs = selectedPackages('#installedBody');
	  if (pkgs.length === 0) return;
	  $.post('setup.php?action=update', {packages: pkgs}, function(){
		refreshLog();
		loadInstalled();
	  }, 'json');
	});

    $('#btnRemove').on('click', function(){
	  const pkgs = selectedPackages('#installedBody');
	  if (pkgs.length === 0) return;
	  $.post('setup.php?action=remove', {packages: pkgs}, function(){
		refreshLog();
		loadInstalled();
	  }, 'json');
	});
});

$(function(){
  search();
  loadInstalled();
  // Tabs wechseln: optional beim Aktivieren neu laden
  $('#installed-tab').on('shown.bs.tab', function(){ loadInstalled(); });
});
</script>
</body>
</html>

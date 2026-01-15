<?php
// setup.php

declare(strict_types=1);
/*
// Simple autoloader for our classes
spl_autoload_register(function ($class) {
    // Nur die beiden gewünschten Klassen berücksichtigen
    $allowed = ['Packagist', 'ComposerManager'];
    if (!in_array($class, $allowed, true)) {
        return;
    }

    $base = __DIR__ . DIRECTORY_SEPARATOR . 'system'. DIRECTORY_SEPARATOR .'papp'. DIRECTORY_SEPARATOR .'phpapp'. DIRECTORY_SEPARATOR .'system'. DIRECTORY_SEPARATOR .'core' . DIRECTORY_SEPARATOR;
    $file = $base . $class . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
*/

// Constants
define('PROJECT_ROOT', __DIR__);
define('SYSTEM_TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'data_c'. DIRECTORY_SEPARATOR . 'papp_phpapp');
define('SETUP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'system'. DIRECTORY_SEPARATOR .'papp'. DIRECTORY_SEPARATOR .'phpapp'. DIRECTORY_SEPARATOR .'system'. DIRECTORY_SEPARATOR .'core');
define('SYSTEM_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'system');
define('COMPOSER_PHAR', SETUP_DIR . DIRECTORY_SEPARATOR . 'composer.phar');
define('LOG_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'data_c'. DIRECTORY_SEPARATOR .'papp_phpapp' . DIRECTORY_SEPARATOR . 'log.txt');

// Ensure folders exist and composer.phar is present
function ensureEnvironment(): void {
    if (!is_dir(SETUP_DIR)) {
        mkdir(SETUP_DIR, 0775, true);
    }
    if (!is_dir(SYSTEM_DIR)) {
        mkdir(SYSTEM_DIR, 0775, true);
    }
	if (!is_dir(SYSTEM_TEMP_DIR)) {
        mkdir(SYSTEM_TEMP_DIR, 0775, true);
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
				$type  = trim($_GET['type'] ?? '');
                $list = $packagist->getProjectList($tags === 'none' ? '' : $tags, $query, $type);
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
			case 'reinstall':
				$result = $composer->reInstall();
				echo json_encode(['ok' => true, 'data' => $result]);
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
                <div class="col-md-4">
                    <label class="form-label">Suche</label>
                    <input type="text" class="form-control" id="searchQuery" placeholder="Suchbegriffe...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tags</label>
                    <select class="form-select" id="searchTags">
                        <option value="none">Nichts</option>
						<option value="phpapp">phpapp</option>
                        <option value="phpapp-module">phpapp-module</option>
                        <option value="phpapp-template">phpapp-template</option>
                    </select>
                </div>
				<div class="col-md-3">
				  <label for="searchType" class="form-label">Type</label>
				  <select id="searchType" class="form-select">
					<option value="">keine</option>
					<option value="phpapp-module">phpapp-module</option>
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
              <th>Auswahl</th><th>Projekt</th><th>Autor</th><th>Beschreibung</th><th>Type</th><th>Status</th><th>Version</th>
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
              <th>Auswahl</th><th>Projekt</th><th>Autor</th><th>Beschreibung</th><th>Type</th><th>Status</th><th>Version</th>
            </tr>
          </thead>
          <tbody id="installedBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
    

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Log anzeigen</span>
			<button class="btn btn-warning btn-sm" id="btnReinstall">ReInstall</button>
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
          description: info.description, // optional: aus Packagist nachladen
          url: 'https://packagist.org/packages/' + name,
          author: info.author,
          installed: true,
          installed_version: info.version,
          update_available: info.update_available,
		  type : info.type,
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
	tr.append($('<td>').text(pkg.type || ''));
    tr.append($('<td>').html(statusBadge(pkg)));
    tr.append($('<td>').append(versionSelect));
    tbody.append(tr);
    loadVersions(pkg.name, versionSelect);
  });
}

function search() {
    const q = $('#searchQuery').val() || '';
    const tags = $('#searchTags').val() || 'none';
	const type = $('#searchType').val() || ''; 
    $.getJSON('setup.php', {action: 'search', q: q, tags: tags, type: type}, function(res) {
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
	
	$('#btnReinstall').on('click', function() {
		
		if (!confirm('Möchten Sie wirklich eine Neuinstallation durchführen? Alle Pakete werden erneut installiert und der Vorgang kann einige Zeit dauern.')) {
			return; // Abbrechen
		}
		$.getJSON('setup.php', {action: 'reinstall'}, function(res){
		});
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

<?php 
class ComposerManager
{
    private string $composerPhar;
    private string $phpBin;
    private string $logFile;

    public function __construct(string $composerPhar, string $logFile)
    {
        $this->composerPhar = $composerPhar;
        $this->logFile = $logFile;
		$this->phpBin = $this->detectPhpBinary();   // hier intern setzen
    }

private function detectPhpBinary(): string
{
    // Unter Windows direkt den absoluten Pfad zurückgeben
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $win = 'C:\\xampp\\php\\php.exe';
        if (is_file($win)) {
            return $win;
        }
    }

    // Auf Unix-Systemen zuerst "php" im PATH testen
    $test = @shell_exec("php -v 2>&1");
    if (is_string($test) && stripos($test, 'PHP') !== false) {
        return 'php';
    }

    // Fallbacks für Unix
    $candidates = ['/usr/bin/php', '/usr/local/bin/php'];
    foreach ($candidates as $cand) {
        if (is_file($cand)) {
            return $cand;
        }
    }

    // Notfall: trotzdem "php" zurückgeben
    return 'php';
}
	
    private function runComposer(array $args): array
    {
		// HOME/COMPOSER_HOME setzen, damit Composer unter Windows läuft 
		if (!getenv('COMPOSER_HOME')) { 
			putenv('COMPOSER_HOME=' . sys_get_temp_dir()); 
		}
		#chdir(dirname(__DIR__)); // Projektwurzel
		chdir(PROJECT_ROOT);
        // Composer runs in project root (composer.json must exist there)
        $cmd = escapeshellcmd($this->phpBin) . ' ' . escapeshellarg($this->composerPhar) . ' -d ' . escapeshellarg(PROJECT_ROOT);
        foreach ($args as $a) {
            $cmd .= ' ' . $a; // args expected already escaped or safe
        }

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($cmd, $descriptor, $pipes, dirname(__DIR__)); // project root = one level above classes
        if (!is_resource($process)) {
            $this->appendLog("[Error] Failed to start Composer process.\n");
            return ['ok' => false, 'error' => 'Process start failed'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->appendLog("---- Composer command ----\n$cmd\n");
        if ($stdout) $this->appendLog($stdout . "\n");
        if ($stderr) $this->appendLog($stderr . "\n");
        $this->appendLog("Exit code: $exitCode\n--------------------------\n");

        $ok = ($exitCode === 0);
        return ['ok' => $ok, 'code' => $exitCode, 'out' => $stdout, 'err' => $stderr];
    }

    private function appendLog(string $text): void
    {
        @file_put_contents($this->logFile, $text, FILE_APPEND);
    }

	private function getInstalledTypes(): array
	{
		$lockFile = __DIR__ . '/../composer.lock'; // Pfad anpassen
		if (!is_file($lockFile)) return [];

		$json = json_decode(file_get_contents($lockFile), true);
		$types = [];
		foreach (($json['packages'] ?? []) as $pkg) {
			$types[$pkg['name']]['type'] = $pkg['type'] ?? '';
			$types[$pkg['name']]['description'] = $pkg['description'] ?? '';
			$types[$pkg['name']]['author'] = $pkg['authors'][0]['name'] ?? '';
		}
		foreach (($json['packages-dev'] ?? []) as $pkg) {
			$types[$pkg['name']]['type'] = $pkg['type'] ?? '';
			$types[$pkg['name']]['description'] = $pkg['description'] ?? '';
		}
		return $types;
	}

	public function reInstall(): array
	{
		$vendorDir = $this->getVendorDir();
		$composerDir = $vendorDir . '/composer';

		// 1. composer/ Ordner löschen
		if (is_dir($composerDir)) {
			$this->deleteDirectory($composerDir);
		}

		// 2. autoload.php löschen
		$autoloadFile = $vendorDir . '/autoload.php';
		if (is_file($autoloadFile)) {
			unlink($autoloadFile);
		}

		// 3. composer.lock löschen (optional, aber empfohlen)
		$lockFile = dirname(__DIR__) . '/composer.lock';
		if (is_file($lockFile)) {
			unlink($lockFile);
		}

		// 4. Pakete neu installieren (erzeugt installed.php)
		$installResult = $this->runComposer(['install']);

		// 5. Autoloader optimiert neu erzeugen
		$autoloadResult = $this->runComposer(['dump-autoload', '-o']);

		return [
			'install' => $installResult,
			'autoload' => $autoloadResult
		];
	}

	
	
	private function getVendorDir(): string
	{
		$composerJson = dirname(__DIR__) . '/composer.json';

		if (is_file($composerJson)) {
			$data = json_decode(file_get_contents($composerJson), true);

			if (isset($data['config']['vendor-dir'])) {
				return dirname(__DIR__) . '/' . $data['config']['vendor-dir'];
			}
		}

		// Standard-Fallback
		return dirname(__DIR__) . '/vendor';
	}
	
	// Hilfsfunktion zum rekursiven Löschen
	
	private function deleteDirectory(string $dir): void
	{
		if (!is_dir($dir)) return;

		$items = array_diff(scandir($dir), ['.', '..']);
		foreach ($items as $item) {
			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->deleteDirectory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
	
    public function getInstalledPackages(): array
    {
        // Use composer show --installed --format=json
        $res = $this->runComposer(['show', '--format=json']);
        if (!$res['ok']) return [];
        $json = json_decode($res['out'], true);
        $installed = [];
		$types = $this->getInstalledTypes();
        foreach (($json['installed'] ?? []) as $pkg) {
            $name = $pkg['name'] ?? '';
            $version = $pkg['version'] ?? '';
            $latest = $pkg['latest'] ?? $version;
            $installed[$name] = [
                'version' => $version,
                'latest' => $latest,
                'update_available' => $latest !== $version,
				'type' => $types[$name]['type'] ?? '',
				'description' => $types[$name]['description'] ?? '',
				'author' => $types[$name]['author'] ?? '',
            ];
        }
        return $installed;
    }

    public function installPackages(array $packages): array
    {
        // Packages: [{name, version}]
        $args = ['require'];
        foreach ($packages as $p) {
            $name = $p['name'] ?? '';
            $version = trim($p['version'] ?? '');
            if ($name === '') continue;
            $pkgArg = escapeshellarg($name . ($version !== '' ? ':' . $version : ''));
            $args[] = $pkgArg;
        }
		
        $result = $this->runComposer($args);
        return $result;
    }

    public function updatePackages(array $packages): array
{
    $args = ['require']; // statt 'update'
    foreach ($packages as $p) {
        $name = trim($p['name'] ?? '');
        $version = trim($p['version'] ?? '');
        if ($name === '') continue;

        // Wenn Version gewählt, Constraint anhängen
        $arg = $name . ($version !== '' ? ' ' . $version : '');
        $args[] = $arg;
    }
    return $this->runComposer($args);
}

    public function removePackages(array $packages): array
    {
        $args = ['remove'];
        foreach ($packages as $p) {
            $name = $p['name'] ?? '';
            if ($name === '') continue;
            $args[] = escapeshellarg($name);
        }
        $result = $this->runComposer($args);
        return $result;
    }
}


class Packagist
{
    private function fetchJson(string $url): array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: PHP-Setup\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return [];
        }
        $json = json_decode($data, true);
        return is_array($json) ? $json : [];
    }

    public function getProjectList(string $tags, string $search, string $type = ''): array
    {
        $query = [];
        if ($search !== '') $query[] = 'q=' . urlencode($search);
        if ($tags !== '') $query[] = 'tags=' . urlencode($tags);
		if ($type !== '')   $query[] = 'type=' . urlencode($type);
        $url = 'https://packagist.org/search.json' . (empty($query) ? '' : ('?' . implode('&', $query)));
        $res = $this->fetchJson($url);

        $items = [];
        foreach (($res['results'] ?? []) as $r) {
            $items[] = [
                'name' => $r['name'] ?? '',
                'description' => $r['description'] ?? '',
                'url' => $r['url'] ?? '',
                'repository' => $r['repository'] ?? '',
                'downloads' => $r['downloads'] ?? 0,
                'favers' => $r['favers'] ?? 0,
                'author' => $r['vendor'] ?? '', // vendor maps roughly to author/org
				'type' => $this->getPackageType($r['name']),
                'installed' => false,
                'installed_version' => '',
                'update_available' => false,
            ];
        }
        return $items;
    }

    public function getProject(string $package): array
    {
        // Primary package info: includes versions via /packages/<vendor>/<name>.json
        $pkgUrl = 'https://repo.packagist.org/p2/' . $package . '.json';
        $data = $this->fetchJson($pkgUrl);

        $versions = [];
        if (isset($data['packages'][$package]) && is_array($data['packages'][$package])) {
            foreach ($data['packages'][$package] as $ver) {
                $versions[] = [
                    'version' => $ver['version'] ?? '',
                    'require' => $ver['require'] ?? [],
                    'time' => $ver['time'] ?? null
                ];
            }
        }

        // Attempt to infer metadata for UI continuity
        $metaUrl = 'https://packagist.org/packages/' . $package . '.json';
        $meta = $this->fetchJson($metaUrl);
        $desc = $meta['package']['description'] ?? '';
        $url = $meta['package']['repository'] ?? ('https://packagist.org/packages/' . $package);
        $authors = $meta['package']['authors'][0]['name'] ?? '';
		$type = $meta['package']['type'] ?? '';
        $wiki = null; // Not standardized; could be from extra, if present
        if (isset($meta['package']['extra']['wiki'])) {
            $wiki = $meta['package']['extra']['wiki'];
        }

        // Security advisories endpoint example (for existence)
        // https://packagist.org/api/security-advisories/?packages[]=vendor/name
        // Not parsed here, but could be used to flag advisories.

        return [
            'versions' => $versions,
            'description' => $desc,
            'url' => $url,
            'author' => $authors,
            'wiki' => $wiki,
			'type' => $meta['package']['type'] ?? ''
        ];
    }
	
	private function getPackageType(string $package): string
{
    // Detail-Endpoint liefert den Typ
    $metaUrl = 'https://packagist.org/packages/' . $package . '.json';
    $meta = $this->fetchJson($metaUrl);

    if (isset($meta['package']['type'])) {
        return $meta['package']['type'];
    }
    return '';
}
}

?>
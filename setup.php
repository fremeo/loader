<?php
declare(strict_types=1);

/**
 * setup.php — Grafisches Setup für PHP-Projekte (Topic: phpapp2)
 * - Composer latest-stable Download in ./setup/composer.phar
 * - Öffentliche Projekte aus GitHub/GitLab mit Topic "phpapp2" + Suche
 * - Anzeige: Avatar (Projektbild), Autor, Titel, Kurzbeschreibung, Plattform, Status, Versionen (aktuell vs. verfügbar)
 * - Aktionen: Installieren, Aktualisieren (Version wählen), Reparieren (Neuinstallation), Deinstallieren
 * - Mehrfachauswahl per Checkbox
 * - Installationsziel: ./system
 */


//////////////////////
// Konfiguration
//////////////////////
$CONFIG = [
    'setup_dir'           => __DIR__ . '/setup',
    'composer_phar'       => __DIR__ . '/setup/composer.phar',
    'system_dir'          => __DIR__ . '/system',
    'composer_url'        => 'https://getcomposer.org/download/latest-stable/composer.phar',
    'github_token'        => '', // optional für höhere API-Limits
    'gitlab_token'        => '', // optional für höhere API-Limits
    'http_user_agent'     => 'phpapp2-setup/2.5 (+local)',
    'github_search_url'   => 'https://api.github.com/search/repositories',
    'gitlab_projects_url' => 'https://gitlab.com/api/v4/projects',
];


if (($_GET['action'] ?? '') === 'tags') {
    $platform = $_GET['platform'] ?? '';
    $fullname = $_GET['fullname'] ?? '';
    $tags = [];

    if ($platform === 'github') {
        // GitHub Tags abrufen
        $tags = fetchGitHubTags($fullname, $CONFIG);
    } elseif ($platform === 'gitlab') {
        // GitLab Tags abrufen
        $url = "https://gitlab.com/api/v4/projects/".urlencode($fullname)."/repository/tags";
        $tagsJson = httpGetJson($url,['Accept: application/json']);
        foreach ($tagsJson as $t) {
            if (!empty($t['name'])) $tags[] = $t['name'];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($tags);
    exit;
}

//////////////////////
// Utilities
//////////////////////
function ensureDir(string $path): void {
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException("Konnte Ordner nicht erstellen: $path");
        }
    }
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function httpGetJson(string $url, array $headers = [], int $timeout = 25): array {
    $opts = [
        'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $timeout],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return [];
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}
function downloadFile(string $url, string $dest, array $headers = [], int $timeout = 60): bool {
    $opts = [
        'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $timeout],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    $ctx = stream_context_create($opts);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return false;
    return file_put_contents($dest, $data) !== false;
}
function runComposer(array $args, string $composerPhar, string $cwd): array {
	
	if (stripos(PHP_OS, 'WIN') === 0) {
		// Windows → festen Pfad zur php.exe nutzen
		$phpBin = 'C:\\xampp\\php\\php.exe';
	} else {
		// Unix/Linux → automatisch den aktuellen Interpreter
		$phpBin = PHP_BINARY;
	}

    $cmd = escapeshellcmd($phpBin) . ' ' . escapeshellarg($composerPhar) . ' ' . implode(' ', array_map('escapeshellarg', $args));
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes, $cwd);

    $out = ''; $err = ''; $code = null;
    if (is_resource($proc)) {
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code = proc_close($proc);
    }
    return ['code' => $code, 'out' => $out, 'err' => $err, 'cmd' => $cmd];
}

function normalizeId(string $platform, string $fullName): string {
    return strtolower($platform . '/' . $fullName);
}
function projectInstallPath(string $systemDir, string $platform, string $fullName): string {
    $safe = str_replace(['/', '\\'], '_', strtolower($platform . '_' . $fullName));
    return $systemDir . DIRECTORY_SEPARATOR . $safe;
}

//////////////////////
// Composer bereitstellen
//////////////////////
ensureDir($CONFIG['setup_dir']);
ensureDir($CONFIG['system_dir']);
$composerReady = file_exists($CONFIG['composer_phar']);
$composerMsg   = '';
if (!$composerReady) {
    $ok = downloadFile($CONFIG['composer_url'], $CONFIG['composer_phar']);
    if ($ok) { $composerReady = true; $composerMsg = 'Composer erfolgreich heruntergeladen.'; }
    else     { $composerMsg = 'Composer Download fehlgeschlagen. Prüfe Netzwerk/SSL.'; }
}

//////////////////////
// Installed Index
//////////////////////
function installedIndexPath(array $CONFIG): string {
    return $CONFIG['system_dir'] . DIRECTORY_SEPARATOR . '.installed.json';
}
function loadInstalledIndex(array $CONFIG): array {
    $file = installedIndexPath($CONFIG);
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
function saveInstalledIndex(array $CONFIG, array $idx): void {
    file_put_contents(installedIndexPath($CONFIG), json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
$installedIdx = loadInstalledIndex($CONFIG);

//////////////////////
// Discovery Helper (Tags/Info)
//////////////////////
function fetchGitHubTags(string $ownerRepo, array $CONFIG): array {
    $url = "https://api.github.com/repos/$ownerRepo/tags?per_page=50";
    $headers = ['User-Agent: ' . $CONFIG['http_user_agent'], 'Accept: application/vnd.github+json'];
    if ($CONFIG['github_token']) $headers[] = 'Authorization: Bearer ' . $CONFIG['github_token'];
    $json = httpGetJson($url, $headers);
    $tags = [];
    foreach ($json as $t) { if (!empty($t['name'])) $tags[] = $t['name']; }
    return $tags;
}
function fetchGitLabTags(string|int $projectId, array $CONFIG): array {
    $url = "https://gitlab.com/api/v4/projects/" . urlencode((string)$projectId) . "/repository/tags?per_page=50";
    $headers = ['Accept: application/json'];
    if ($CONFIG['gitlab_token']) $headers[] = 'Authorization: Bearer ' . $CONFIG['gitlab_token'];
    $json = httpGetJson($url, $headers);
    $tags = [];
    foreach ($json as $t) { if (!empty($t['name'])) $tags[] = $t['name']; }
    return $tags;
}

function parseGitHubResults(array $json, array $CONFIG): array {
    $items = $json['items'] ?? [];
    $result = [];
    foreach ($items as $it) {
        $full = $it['full_name'] ?? '';
        if (!$full) continue;
        $result[] = [
            'platform'    => 'github',
            'full_name'   => $full,
            'author'      => $it['owner']['login'] ?? '',
            'description' => $it['description'] ?? '',
            'url'         => $it['html_url'] ?? '',
            'clone_url'   => $it['clone_url'] ?? '',
            'avatar_url'  => $it['owner']['avatar_url'] ?? '',
            'wiki_url'    => !empty($it['has_wiki']) ? (($it['html_url'] ?? '') . '/wiki') : null,
            'tags'        => fetchGitHubTags($full, $CONFIG),
            'visibility'  => 'public',
        ];
    }
    return $result;
}
function parseGitLabResults(array $json, array $CONFIG): array {
    $result = [];
    foreach ($json as $it) {
        $vis = $it['visibility'] ?? 'private';
        if ($vis !== 'public') continue;
        $full = $it['path_with_namespace'] ?? '';
        if (!$full) continue;
        // Autor-Name (public Projekte haben kein owner-Feld wie GitHub; fallback: namespace)
        $author = $it['namespace']['name'] ?? ($it['namespace']['path'] ?? '');
        $result[] = [
            'platform'    => 'gitlab',
            'full_name'   => $full,
            'author'      => $author,
            'description' => $it['description'] ?? '',
            'url'         => $it['web_url'] ?? '',
            'clone_url'   => $it['http_url_to_repo'] ?? '',
            'avatar_url'  => $it['avatar_url'] ?? '',
            'wiki_url'    => !empty($it['wiki_enabled']) ? (($it['web_url'] ?? '') . '/-/wikis/home') : null,
            'tags'        => fetchGitLabTags($it['id'] ?? '', $CONFIG),
            'visibility'  => $vis,
        ];
    }
    return $result;
}

//////////////////////
// Discovery: GitHub + GitLab
//////////////////////
$search = trim($_GET['q'] ?? '');
$topic  = '';//'phpapp2';
$projects = [];

// GitHub
{
    ###$q = 'topic:' . $topic . ($search !== '' ? ' ' . $search : '');
	$q =   ($search !== '' ? ' ' . $search : '');
    $params = ['q' => $q, 'per_page' => '25', 'sort' => 'updated', 'order' => 'desc'];
    $url = $CONFIG['github_search_url'] . '?' . http_build_query($params);
    $headers = ['User-Agent: ' . $CONFIG['http_user_agent'], 'Accept: application/vnd.github+json'];
    if ($CONFIG['github_token']) $headers[] = 'Authorization: Bearer ' . $CONFIG['github_token'];
    $ghJson = httpGetJson($url, $headers);
    $projects = array_merge($projects, parseGitHubResults($ghJson, $CONFIG));
}
// GitLab (nur public)
{
    $params = [
        'search'     => ($search !== '' ? $search . ' ' : '') . $topic,
        'per_page'   => '25',
        'order_by'   => 'updated_at',
        'sort'       => 'desc',
        'visibility' => 'public',
    ];
    $url = $CONFIG['gitlab_projects_url'] . '?' . http_build_query($params);
    $headers = ['Accept: application/json'];
    if ($CONFIG['gitlab_token']) $headers[] = 'Authorization: Bearer ' . $CONFIG['gitlab_token'];
    $glJson = httpGetJson($url, $headers);
    $projects = array_merge($projects, parseGitLabResults($glJson, $CONFIG));
}

//////////////////////
// Status/Versionen für installierte Projekte
//////////////////////
function readInstalledVersion(string $installPath, string $packageFullName): ?string {
    $lock = $installPath . DIRECTORY_SEPARATOR . 'composer.lock';
    if (!is_file($lock)) return null;
    $data = json_decode((string)file_get_contents($lock), true);
    if (!is_array($data)) return null;
    $pkgs = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);
    foreach ($pkgs as $p) {
        if (!empty($p['name']) && strtolower($p['name']) === strtolower($packageFullName)) {
            return $p['version'] ?? null;
        }
    }
    return null;
}
function composerOutdatedDirect(string $composerPhar, string $installPath): array {
    if (!is_file($installPath . '/composer.json')) return [];
    $res = runComposer(['outdated', '--direct', '--format=json'], $composerPhar, $installPath);
    if ($res['code'] !== 0) return [];
    $json = json_decode($res['out'], true);
    return is_array($json) ? ($json['installed'] ?? []) : [];
}
function isInstalled(array $installedIdx, string $platform, string $fullName): bool {
    $id = normalizeId($platform, $fullName);
    return isset($installedIdx[$id]) && is_dir($installedIdx[$id]['path']);
}


//////////////////////
// Aktionen (Install/Update/Repair/Remove)
//////////////////////
// wird die zentrale composer.json im Root ergänzt.
function addProjectToRootComposer(array $CONFIG, array $meta, string $version): void {
    $composerJson = __DIR__ . '/composer.json'; // Root composer.json
    $data = [];
    if (file_exists($composerJson)) {
        $data = json_decode(file_get_contents($composerJson), true) ?: [];
    }
    if (!isset($data['name'])) $data['name'] = 'system/root';
    if (!isset($data['repositories'])) $data['repositories'] = [];
    // Repository nur hinzufügen, wenn noch nicht vorhanden
    $exists = false;
    foreach ($data['repositories'] as $repo) {
        if (($repo['url'] ?? '') === $meta['clone_url']) { $exists = true; break; }
    }
    if (!$exists) {
        $data['repositories'][] = ['type'=>'vcs','url'=>$meta['clone_url']];
    }
    if (!isset($data['require'])) $data['require'] = [];
    $packageName = preg_replace('/[^a-z0-9_.-]/i','-', $meta['full_name']); // oder eigene Logik
if (strpos($packageName,'/')===false) {
    // sicherstellen, dass vendor/package vorhanden ist
    $packageName = 'customvendor/'.$packageName;
}
$data['require'][$packageName] = $version ?: '*';
    file_put_contents($composerJson, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
function composerInstallAction(array $CONFIG, array &$installedIdx, array $meta, string $version): array {
    // Projekt in Root-composer.json eintragen
    addProjectToRootComposer($CONFIG, $meta, $version);

    // Prüfen, ob composer.lock existiert
    $lockFile = __DIR__ . '/composer.lock';
    if (!file_exists($lockFile)) {
        // Erster Lauf: komplettes Update, damit lock-Datei erzeugt wird
        $res = runComposer(['update','--no-interaction'], $CONFIG['composer_phar'], __DIR__);
    } else {
        // Danach reicht ein partielles Update für das neue Paket
        $res = runComposer(['update',$meta['package_name'],'--no-interaction'], $CONFIG['composer_phar'], __DIR__);
    }

    $id = normalizeId($meta['platform'], $meta['full_name']);
    if ($res['code'] === 0) {
        $installedIdx[$id] = [
            'platform' => $meta['platform'],
            'full_name'=> $meta['full_name'],
            'path'     => __DIR__, // Root ist jetzt Installationsort
            'url'      => $meta['clone_url'],
            'installed_at' => date('c'),
        ];
        saveInstalledIndex($CONFIG, $installedIdx);
        return ['ok' => true, 'msg' => "Installiert: {$meta['full_name']} ($version)", 'log' => $res['out']];
    }
    return ['ok' => false, 'msg' => "Fehler bei Installation: {$meta['full_name']}", 'log' => $res['err']];
}
function composerUpdateAction(array $CONFIG, array $installedIdx, array $meta, string $version): array {
    addProjectToRootComposer($CONFIG, $meta, $version);
    $res = runComposer(['update', $meta['full_name'], '--no-interaction'], $CONFIG['composer_phar'], __DIR__);
    if ($res['code'] === 0) {
        return ['ok' => true, 'msg' => "Aktualisiert: {$meta['full_name']} ($version)", 'log' => $res['out']];
    }
    return ['ok' => false, 'msg' => "Fehler bei Update: {$meta['full_name']}", 'log' => $res['err']];
}
function composerRepairAction(array $CONFIG, array &$installedIdx, array $meta, string $version): array {
    // Reparieren = Ordner säubern + Neuinstallation
    $id = normalizeId($meta['platform'], $meta['full_name']);
    $installPath = projectInstallPath($CONFIG['system_dir'], $meta['platform'], $meta['full_name']);
    if (is_dir($installPath)) {
        $it = new RecursiveDirectoryIterator($installPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
        }
        @rmdir($installPath);
    }
    unset($installedIdx[$id]);
    saveInstalledIndex($CONFIG, $installedIdx);
    return composerInstallAction($CONFIG, $installedIdx, $meta, $version);
}
function composerRemoveAction(array $CONFIG, array &$installedIdx, array $meta): array {
    $composerJson = __DIR__ . '/composer.json';
    if (!file_exists($composerJson)) {
        return ['ok' => false, 'msg' => 'composer.json fehlt im Root.', 'log' => ''];
    }
    $data = json_decode(file_get_contents($composerJson), true) ?: [];
    unset($data['require'][$meta['full_name']]);
    file_put_contents($composerJson, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    $res = runComposer(['update','--no-interaction'], $CONFIG['composer_phar'], __DIR__);

    $id = normalizeId($meta['platform'], $meta['full_name']);
    unset($installedIdx[$id]);
    saveInstalledIndex($CONFIG, $installedIdx);

    return ['ok' => $res['code']===0, 'msg' => "Deinstalliert: {$meta['full_name']}", 'log' => $res['out'].$res['err']];
}

//////////////////////
// POST Handling
//////////////////////
$feedback = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pick'])) {
    $action = $_POST['action'] ?? '';
    foreach ((array)$_POST['pick'] as $json) {
        $meta = json_decode($json, true);
        if (!is_array($meta)) continue;
        $version = $_POST['version'][$meta['full_name']] ?? '*';
        if ($action === 'install') {
            $feedback[] = composerInstallAction($CONFIG, $installedIdx, $meta, $version);
        } elseif ($action === 'update') {
            $feedback[] = composerUpdateAction($CONFIG, $installedIdx, $meta, $version);
        } elseif ($action === 'repair') {
            $feedback[] = composerRepairAction($CONFIG, $installedIdx, $meta, $version);
        } elseif ($action === 'remove') {
            $feedback[] = composerRemoveAction($CONFIG, $installedIdx, $meta);
        }
    }
}

//////////////////////
// Statuskarte pro Projekt
//////////////////////
$statusMap = [];
if ($composerReady) {
    foreach ($installedIdx as $id => $info) {
        $installedVer = readInstalledVersion($info['path'], $info['full_name']);
        $outdated = composerOutdatedDirect($CONFIG['composer_phar'], $info['path']);
        $hasUpdate = !empty($outdated);
        $statusMap[$id] = [
            'installed_version' => $installedVer,
            'has_update'        => $hasUpdate,
        ];
    }
}

//////////////////////
// HTML Ausgabe (Bootstrap)
//////////////////////
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Setup — PHP Projekte (phpapp2)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-bottom: 60px; }
        .installed-badge { font-size: 0.85rem; }
        .avatar { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
        .log-block { white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="#">Setup</a>
        <span class="navbar-text">Composer & GitHub/GitLab</span>
    </div>
</nav>

<div class="container">

    <div class="alert <?= $composerReady ? 'alert-success' : 'alert-warning' ?>">
        <?= h($composerMsg ?: ($composerReady ? 'Composer ist bereit.' : 'Composer fehlt.')) ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Suche (Topic: phpapp2 + optionaler Begriff)</label>
                    <input type="text" name="q" class="form-control" placeholder="z. B. cms, api, auth" value="<?= h($search) ?>">
                </div>
                <div class="col-md-4 align-self-end">
                    <button class="btn btn-primary w-100" type="submit">Suchen</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($feedback)): ?>
        <?php foreach ($feedback as $f): ?>
            <div class="alert <?= $f['ok'] ? 'alert-success' : 'alert-danger' ?>">
                <?= h($f['msg']) ?>
            </div>
            <?php if (!empty($f['log'])): ?>
                <details class="mb-3">
                    <summary>Log anzeigen</summary>
                    <div class="log-block border rounded p-2 mt-2"><?= h($f['log']) ?></div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Verfügbare öffentliche Projekte (<?= count($projects) ?>) — Topic: phpapp2</span>
            <div class="d-flex gap-2">
                <button form="projectsForm" class="btn btn-sm btn-primary" name="action" value="install">Ausgewählte installieren</button>
                <button form="projectsForm" class="btn btn-sm btn-warning" name="action" value="update">Ausgewählte aktualisieren</button>
                <button form="projectsForm" class="btn btn-sm btn-secondary" name="action" value="repair">Ausgewählte reparieren</button>
                <button form="projectsForm" class="btn btn-sm btn-outline-danger" name="action" value="remove">Ausgewählte deinstallieren</button>
            </div>
        </div>
        <div class="card-body">
            <form method="post" id="projectsForm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Auswahl</th>
                                <th>Bild</th>
                                <th>Projekt</th>
                                <th>Autor</th>
                                <th>Plattform</th>
                                <th>Beschreibung</th>
                                <th>Status</th>
                                <th>Versionen</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projects)): ?>
                                <tr><td colspan="9" class="text-muted">Keine Projekte gefunden. Ändere den Suchbegriff.</td></tr>
                            <?php else: ?>
                                <?php foreach ($projects as $p):
                                    $id = normalizeId($p['platform'], $p['full_name']);
                                    $isInst = isInstalled($installedIdx, $p['platform'], $p['full_name']);
                                    $st = $statusMap[$id] ?? ['installed_version' => null, 'has_update' => false];
                                    $currentVersion = $st['installed_version'];
                                    $hasUpdate = $st['has_update'];
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox"
       class="form-check-input project-check"
       data-platform="<?= h($p['platform']) ?>"
       data-fullname="<?= h($p['full_name']) ?>"
       name="pick[]"
       value="<?= h(json_encode($p)) ?>">
                                        </td>
                                        <td>
                                            <?php if (!empty($p['avatar_url'])): ?>
                                                <img src="<?= h($p['avatar_url']) ?>" alt="avatar" class="avatar">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['full_name']) ?></a>
                                            <?php if (!empty($p['wiki_url'])): ?>
                                                <a href="<?= h($p['wiki_url']) ?>" target="_blank" class="btn btn-sm btn-outline-info ms-2">Wiki</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($p['author'] ?? '') ?></td>
                                        <td><span class="badge bg-secondary"><?= h($p['platform']) ?></span></td>
                                        <td><?= h($p['description'] ?? '') ?></td>
                                        <td>
                                            <?php if ($isInst): ?>
                                                <span class="badge bg-success installed-badge">Installiert</span>
                                                <?php if ($currentVersion): ?>
                                                    <span class="badge bg-light text-dark installed-badge">Aktuell: <?= h($currentVersion) ?></span>
                                                <?php endif; ?>
                                                <?php if ($hasUpdate): ?>
                                                    <span class="badge bg-warning text-dark installed-badge">Update verfügbar</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark installed-badge">Auf neuestem Stand</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark installed-badge">Nicht installiert</span>
                                            <?php endif; ?>
                                        </td>
                                       <td id="version-cell-<?= h($p['platform'].'_'.str_replace(['/','\\'],'_',$p['full_name'])) ?>">
    <span class="text-muted">Bitte Projekt auswählen…</span>
</td>
                                        <td class="d-flex flex-wrap gap-2">
                                            <?php if (!$isInst): ?>
                                                <button class="btn btn-sm btn-primary" name="action" value="install" type="submit">Installieren</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-warning" name="action" value="update" type="submit">Aktualisieren</button>
                                                <button class="btn btn-sm btn-secondary" name="action" value="repair" type="submit">Reparieren</button>
                                                <button class="btn btn-sm btn-outline-danger" name="action" value="remove" type="submit">Deinstallieren</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <h6>Installationsziel</h6>
        <p><code><?= h($CONFIG['system_dir']) ?></code></p>
    </div>

</div>

<footer class="footer mt-5">
    <div class="container">
        <hr>
        <p class="text-muted small">Setup für PHP-Projekte (Topic: phpapp2). Nutzt Composer lokal (composer.phar) und Bootstrap für UI.</p>
    </div>
</footer>

<!-- Bootstrap JS Bundle via CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.project-check').forEach(cb => {
  cb.addEventListener('change', function() {
    if (this.checked) {
      const platform = this.dataset.platform;
      const fullname = this.dataset.fullname;
      const cellId = 'version-cell-' + platform + '_' + fullname.replace(/[\/\\]/g,'_');
      fetch('setup.php?action=tags&platform='+encodeURIComponent(platform)+'&fullname='+encodeURIComponent(fullname))
        .then(r => r.json())
        .then(tags => {
          const cell = document.getElementById(cellId);
          if (!tags.length) {
            cell.innerHTML = '<span class="text-muted">Keine Tags gefunden</span>';
          } else {
            let html = '<select name="version['+fullname+']" class="form-select form-select-sm">';
            tags.forEach(t => { html += '<option value="'+t+'">'+t+'</option>'; });
            html += '</select>';
            cell.innerHTML = html;
          }
        });
    }
  });
});
</script>
</body>
</html>

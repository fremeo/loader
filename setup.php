<?php
/**
 * setup.php
 * Bootstrap + jQuery Oberfläche + AJAX-Endpunkte für:
 * - Projektliste (GitHub/GitLab, Topic=phpapp2 mit optionalem Suchfilter)
 * - Tag/Version-Nachladen pro Projekt
 * - Installieren, Aktualisieren, Reparieren, Deinstallieren via ComposerManager
 * - Log-Anzeige aus setup/log.txt
 *
 * Voraussetzung:
 * - classes/Platform.php
 * - classes/ComposerManager.php
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('SETUP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'setup');
define('SYSTEM_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'system');
define('CLASSES_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'setup');
define('COMPOSER_PHAR', SETUP_DIR . DIRECTORY_SEPARATOR . 'composer.phar');
define('COMPOSER_LOG', SETUP_DIR . DIRECTORY_SEPARATOR . 'log.txt');
define('COMPOSER_JSON', __DIR__ . DIRECTORY_SEPARATOR . 'composer.json');

require_once CLASSES_DIR . DIRECTORY_SEPARATOR . 'Platform.php';
require_once CLASSES_DIR . DIRECTORY_SEPARATOR . 'ComposerManager.php';

// Initialisierung (Composer/Ordner)
$pm = new Platform();
$pm->ensureDirectoriesAndComposer();

/**
 * Hilfsfunktion: sichere Eingaben (Basic)
 */
function in($key, $default = null) {
    return isset($_REQUEST[$key]) ? trim((string)$_REQUEST[$key]) : $default;
}

/**
 * AJAX Router
 * WICHTIG: wir senden per POST, daher auf $_REQUEST prüfen, nicht $_GET
 */
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $platformFilter = in('platform', 'all'); // github/gitlab/all
        $topic = in('topic', 'phpapp2');
        $search = in('search', '');
        $visibility = in('visibility', 'public'); // optional

        $action = in('action', '');
        $pm = new Platform();
        $cm = new ComposerManager(COMPOSER_JSON, COMPOSER_PHAR, COMPOSER_LOG, SYSTEM_DIR);

        switch ($action) {

            case 'listProjects':
                // Liste abrufen
                $projects = $pm->getProjectList($platformFilter, $topic, $visibility, $search);

                // Statusinformationen ergänzen
                foreach ($projects as &$p) {
                    $p['status'] = $cm->getProjectStatus($p);
                    $p['installed_version'] = $cm->getInstalledVersion($p);
                    $p['has_update'] = $cm->hasUpdate($p);
                }
                unset($p);

                // Installierte zuerst
                usort($projects, function ($a, $b) {
                    $sa = $a['status'] === 'installed' ? 0 : 1;
                    $sb = $b['status'] === 'installed' ? 0 : 1;
                    if ($sa === $sb) return strcmp($a['title'], $b['title']);
                    return $sa <=> $sb;
                });

                echo json_encode(['ok' => true, 'projects' => $projects]);
                break;

            case 'getProjectTags':
                $platform = in('platform', 'github');
                $id = in('id', '');
                if (!$id) throw new Exception('id missing');
                $tags = $pm->getTags($platform, $id);
                echo json_encode(['ok' => true, 'tags' => $tags]);
                break;

            case 'install':
                $platform = in('platform', 'github');
                $id = in('id', '');
                $version = in('version', ''); // tag optional
                if (!$id) throw new Exception('id missing');
                $proj = $pm->getProject($platform, $id);
                $res = $cm->install($proj, $version);
                echo json_encode($res);
                break;

            case 'update':
                $platform = in('platform', 'github');
                $id = in('id', '');
                $version = in('version', '');
                if (!$id) throw new Exception('id missing');
                $proj = $pm->getProject($platform, $id);
                $res = $cm->update($proj, $version);
                echo json_encode($res);
                break;

            case 'repair':
                $platform = in('platform', 'github');
                $id = in('id', '');
                if (!$id) throw new Exception('id missing');
                $proj = $pm->getProject($platform, $id);
                $res = $cm->repair($proj);
                echo json_encode($res);
                break;

            case 'uninstall':
                $platform = in('platform', 'github');
                $id = in('id', '');
                if (!$id) throw new Exception('id missing');
                $proj = $pm->getProject($platform, $id);
                $res = $cm->uninstall($proj);
                echo json_encode($res);
                break;

            case 'getWikiList':
                $platform = in('platform', 'github');
                $id = in('id', '');
                $res = $pm->getWikiList($platform, $id);
                echo json_encode(['ok' => true, 'pages' => $res]);
                break;

            case 'getWikiPage':
                $platform = in('platform', 'github');
                $id = in('id', '');
                $pageId = in('pageId', '');
                $res = $pm->getWikiPage($platform, $id, $pageId);
                echo json_encode(['ok' => true, 'content' => $res]);
                break;

            case 'getLog':
                $log = file_exists(COMPOSER_LOG) ? file_get_contents(COMPOSER_LOG) : '';
                echo json_encode(['ok' => true, 'log' => $log]);
                break;

            default:
                echo json_encode(['ok' => false, 'error' => 'unknown action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
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
        body { background: #f6f8fa; }
        .status-installed { color: #198754; font-weight: 600; }   /* Grün */
        .status-update    { color: #ffc107; font-weight: 600; }   /* Gelb */
        .status-error     { color: #dc3545; font-weight: 600; }   /* Rot */
        .table-fixed { height: 60vh; overflow-y: auto; }
        .avatar { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; }
        .logbox { background: #111; color: #0f0; font-family: monospace; padding: 10px; border-radius: 6px; height: 20vh; overflow-y: auto; }
        .btn-wiki { margin-left: 8px; }
        .sticky-top-custom { position: sticky; top: 0; background: #fff; z-index: 9; padding: 12px 0; }
    </style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="sticky-top-custom">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Suche</label>
                <input type="text" class="form-control" id="search" placeholder="Freie Begriffe...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Plattform</label>
                <select class="form-select" id="platform">
                    <option value="all">Alle</option>
                    <option value="github">GitHub</option>
                    <option value="gitlab">GitLab</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Topic</label>
                <input type="text" class="form-control" id="topic" value="phpapp2" placeholder="phpapp2 (leer = ohne Einschränkung)">
            </div>
            <div class="col-md-3">
                <!-- Fix: type="button" -->
                <button type="button" class="btn btn-primary w-100" id="btnRefresh">Projekte laden</button>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <div class="table-responsive table-fixed">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                <tr>
                    <th>Auswahl</th>
                    <th>Bild</th>
                    <th>Projekt</th>
                    <th>Autor</th>
                    <th>Plattform</th>
                    <th>Beschreibung</th>
                    <th>Status</th>
                    <th>Version</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody id="projectRows">
                <tr><td colspan="9">Keine Daten geladen.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <h5>Log anzeigen</h5>
        <div class="logbox" id="logbox"></div>
    </div>
</div>

<script>
function api(params) {
    return $.ajax({
        url: 'setup.php',
        method: 'POST',
        data: Object.assign({ ajax: '1' }, params),
        dataType: 'json'
    });
}

function refreshProjects() {
    const platform = $('#platform').val();
    const topic = $('#topic').val();
    const search = $('#search').val();

    api({ action: 'listProjects', platform, topic, search }).done(res => {
        if (!res.ok) {
            $('#projectRows').html(`<tr><td colspan="9" class="text-danger">${res.error || 'Fehler'}</td></tr>`);
            return;
        }
        const rows = [];
        if (res.projects.length === 0) {
            rows.push('<tr><td colspan="9">Keine Projekte gefunden.</td></tr>');
        } else {
            res.projects.forEach(p => {
                const statusClass =
                    p.status === 'installed' ? 'status-installed' :
                    (p.has_update ? 'status-update' :
                    (p.status === 'error' ? 'status-error' : ''));
                const wikiBtn = p.has_wiki ? `<button class="btn btn-sm btn-outline-secondary btn-wiki" data-platform="${p.platform}" data-id="${p.id}">Wiki</button>` : '';
                const dropdown = `<select class="form-select form-select-sm version-select" data-platform="${p.platform}" data-id="${p.id}">
                                    <option value="">Version laden...</option>
                                  </select>`;
                rows.push(`
                    <tr data-id="${p.id}" data-platform="${p.platform}">
                        <td><input type="checkbox" class="form-check-input project-check"></td>
                        <td><img src="${p.avatar_url || ''}" alt="avatar" class="avatar"></td>
                        <td>
                            <a href="${p.web_url}" target="_blank" class="fw-semibold">${p.title}</a>
                            ${wikiBtn}
                        </td>
                        <td>${p.author || '-'}</td>
                        <td>${p.platform}</td>
                        <td>${p.description || ''}</td>
                        <td class="${statusClass}">
                            ${p.status}
                            ${p.has_update ? '<span title="Update verfügbar">⬆️</span>' : ''}
                            ${p.installed_version ? ' (installiert: ' + p.installed_version + ')' : ''}
                        </td>
                        <td>${dropdown}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-success btn-install">Installieren</button>
                                <button class="btn btn-warning btn-update">Aktualisieren</button>
                                <button class="btn btn-secondary btn-repair">Reparieren</button>
                                <button class="btn btn-danger btn-uninstall">Deinstallieren</button>
                            </div>
                        </td>
                    </tr>
                `);
            });
        }
        $('#projectRows').html(rows.join(''));
    }).fail((xhr, status, err) => {
        $('#projectRows').html(`<tr><td colspan="9" class="text-danger">AJAX Fehler: ${status} ${err}</td></tr>`);
    });
}

// Event‑Handler nur einmal global binden
$(function(){
    // … andere Aktionen …

    // Checkbox → Tags nachladen
    $('#projectRows').on('change', '.project-check', function(){
        const $tr = $(this).closest('tr');
        if (this.checked) {
            const $sel = $tr.find('.version-select');
            // Nur laden, wenn noch nicht befüllt
            if ($sel.children().length <= 1) {
                const platform = $sel.data('platform');
                const id = $sel.data('id');
                api({ action: 'getProjectTags', platform, id }).done(r => {
                    if (r.ok) {
                        $sel.html(r.tags.length ? r.tags.map(t => `<option value="${t}">${t}</option>`).join('') : '<option value="">Keine Tags</option>');
                    } else {
                        $sel.html('<option value="">Fehler beim Laden</option>');
                    }
                });
            }
        }
    });
});

function appendLog() {
    api({ action: 'getLog' }).done(res => {
		if (res.ok) {
			const formatted = (res.log || '').replace(/\n/g, '<br>');
			$('#logbox').html(formatted);
			const logBox = document.getElementById('logbox');
			logBox.scrollTop = logBox.scrollHeight;
		}
	});
}

$(function(){
    // Button klick
    $('#btnRefresh').on('click', refreshProjects);

	$('#search').on('keyup', function(e){
		if (e.keyCode === 13) {
			refreshProjects();
		}
	});

    // Aktionen
    $('#projectRows').on('click', '.btn-install', function(){
        const $tr = $(this).closest('tr');
        const platform = $tr.data('platform');
        const id = $tr.data('id');
        const version = $tr.find('.version-select').val() || '';
        api({ action: 'install', platform, id, version }).done(res => {
            appendLog();
            refreshProjects();
        });
    });

    $('#projectRows').on('click', '.btn-update', function(){
        const $tr = $(this).closest('tr');
        const platform = $tr.data('platform');
        const id = $tr.data('id');
        const version = $tr.find('.version-select').val() || '';
        api({ action: 'update', platform, id, version }).done(res => {
            appendLog();
            refreshProjects();
        });
    });

    $('#projectRows').on('click', '.btn-repair', function(){
        const $tr = $(this).closest('tr');
        const platform = $tr.data('platform');
        const id = $tr.data('id');
        api({ action: 'repair', platform, id }).done(res => {
            appendLog();
            refreshProjects();
        });
    });

    $('#projectRows').on('click', '.btn-uninstall', function(){
        const $tr = $(this).closest('tr');
        const platform = $tr.data('platform');
        const id = $tr.data('id');
        api({ action: 'uninstall', platform, id }).done(res => {
            appendLog();
            refreshProjects();
        });
    });

    // Wiki Button
    $('#projectRows').on('click', '.btn-wiki', function(){
        const platform = $(this).data('platform');
        const id = $(this).data('id');
        api({ action: 'getWikiList', platform, id }).done(res => {
            if (res.ok) {
                const items = res.pages.map(p => `<li><a href="#" class="wiki-link" data-platform="${platform}" data-id="${id}" data-pageid="${p.id}">${p.title}</a></li>`).join('');
                const html = `<div class="alert alert-info"><strong>Wiki Seiten:</strong><ul>${items}</ul></div>`;
                $(this).closest('td').append(html);
            }
        });
    });

    // Wiki Page
    $(document).on('click', '.wiki-link', function(e){
        e.preventDefault();
        const platform = $(this).data('platform');
        const id = $(this).data('id');
        const pageId = $(this).data('pageid');
        api({ action: 'getWikiPage', platform, id, pageId }).done(res => {
            if (res.ok) {
                const modalHtml = `
                <div class="modal fade" id="wikiModal" tabindex="-1">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Wiki</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        ${res.content}
                      </div>
                    </div>
                  </div>
                </div>`;
                $('body').append(modalHtml);
                const modal = new bootstrap.Modal(document.getElementById('wikiModal'));
                modal.show();
                $('#wikiModal').on('hidden.bs.modal', function(){ $(this).remove(); });
            }
        });
    });

    // Initialer Load
    refreshProjects();

    // Log Intervall (alle 3s). Zum Deaktivieren: Zeile auskommentieren.
    setInterval(appendLog, 3000);
});
</script>
</body>
</html>

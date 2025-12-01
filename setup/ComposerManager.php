<?php
/**
 * Klassen-Datei: ComposerManager.php
 * Aufgaben:
 * - composer.json selektiv anpassen (require, repositories) ohne Rest zu überschreiben
 * - Installation, Update, Reparatur, Deinstallation
 * - Composer-Ausführung mit Log-Datei im setup-Ordner (log.txt)
 * - Status/Version-Erkennung installierter Pakete
 *
 * Wichtige Annahmen:
 * - composer.json im Root ist vorhanden
 * - Projekte sollen unter system/ installiert sein (Composer "vendor-dir" optional)
 */

declare(strict_types=1);

class ComposerManager
{
    private string $composerJson;
    private string $composerPhar;
    private string $logFile;
    private string $systemDir;

    public function __construct(string $composerJson, string $composerPhar, string $logFile, string $systemDir)
    {
        $this->composerJson = $composerJson;
        $this->composerPhar = $composerPhar;
        $this->logFile = $logFile;
        $this->systemDir = $systemDir;

        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
        if (!is_dir($this->systemDir)) {
            mkdir($this->systemDir, 0775, true);
        }
    }

    public function install(array $project, ?string $version = null): array
{
    $this->appendLog("== INSTALL: {$project['title']} ==");
	
	// vor require sicherstellen, dass Repo eingetragen ist
    $ok = $this->ensureRepository($project);
    if (!$ok || !empty($project['install_blocked'])) {
        $this->appendLog("Installation blockiert: Paketname stimmt nicht mit Repository überein");
        return ['ok' => false, 'message' => 'Installation blockiert: Paketname stimmt nicht mit Repository überein'];
    }

    // Wichtig: nur den von ensureRepository gesetzten Namen verwenden
    if (empty($project['package_name'])) {
        $this->appendLog("Installation blockiert: kein gültiger Paketname gefunden");
        return ['ok' => false, 'message' => 'Installation blockiert: kein gültiger Paketname gefunden'];
    }

    // Hier korrekt den gesetzten Namen verwenden
    $pkg = $project['package_name'];
	
    ##$pkg = $this->determinePackageName($project);

    $args = ['require', $pkg];
    if ($version) {
        $args[] = $version;
    }

    $res = $this->runComposer($args);
    return ['ok' => $res, 'message' => $res ? 'Install erfolgreich' : 'Install fehlgeschlagen'];
}

    public function update(array $project, ?string $version = null): array
{
    $this->appendLog("== UPDATE: {$project['title']} ==");
	
	// vor require sicherstellen, dass Repo eingetragen ist
    $ok = $this->ensureRepository($project);
    if (!$ok || !empty($project['install_blocked'])) {
        return ['ok' => false, 'message' => 'Installation blockiert: Paketname stimmt nicht mit Repository überein'];
    }
	
	if (empty($project['package_name'])) {
        return ['ok' => false, 'message' => 'Update blockiert: kein gültiger Paketname gefunden'];
    }
    ##$pkg = $this->determinePackageName($project);

    $args = ['update', $pkg];
    if ($version) {
        $args[] = $version;
    }

    $res = $this->runComposer($args);
    return ['ok' => $res, 'message' => $res ? 'Update erfolgreich' : 'Update fehlgeschlagen'];
}

    public function repair(array $project): array
{
    $this->appendLog("== REPAIR: {$project['title']} ==");
    $res = $this->runComposer(['install']);
    return ['ok' => $res, 'message' => $res ? 'Reparatur erfolgreich' : 'Reparatur fehlgeschlagen'];
}

    public function uninstall(array $project): array
{
    $this->appendLog("== UNINSTALL: {$project['title']} ==");
    $pkg = $this->determinePackageName($project);

    $res = $this->runComposer(['remove', $pkg]);
    return ['ok' => $res, 'message' => $res ? 'Deinstallation erfolgreich' : 'Deinstallation fehlgeschlagen'];
}

    public function getProjectStatus(array $project): string
    {
        $pkg = $this->determinePackageName($project);
        $lock = $this->readComposerLock();
        if (!$lock) return 'not installed';
        foreach (['packages', 'packages-dev'] as $key) {
            foreach ($lock[$key] ?? [] as $p) {
                if (($p['name'] ?? '') === $pkg) {
                    return 'installed';
                }
            }
        }
        // Optional: Fehlerstatus erkennen
        return 'not installed';
    }

    public function getInstalledVersion(array $project): ?string
    {
        $pkg = $this->determinePackageName($project);
        $lock = $this->readComposerLock();
        foreach (['packages', 'packages-dev'] as $key) {
            foreach ($lock[$key] ?? [] as $p) {
                if (($p['name'] ?? '') === $pkg) {
                    return $p['version'] ?? null;
                }
            }
        }
        return null;
    }

    public function hasUpdate(array $project): bool
    {
        // Heuristik: Wenn installiert und Tags vorhanden, prüfen ob installierte Version nicht der höchste Tag ist
        // Genauigkeit hängt von Versioning ab.
        return false; // Für Einfachheit hier Stub; kann durch "composer outdated" ergänzt werden.
    }


private function ensureRepository(array &$project): bool
{
    if (empty($project['repo_url'])) {
        return false;
    }

    $version = $project['version'] ?? 'master';
    $repoUrl = rtrim($project['repo_url'], '/');

    // Raw-URL je nach Host bestimmen
    if (strpos($repoUrl, 'github.com') !== false) {
        $parts = parse_url($repoUrl);
        $path  = trim($parts['path'] ?? '', '/'); // z.B. owner/repo
        $repoComposerUrl = "https://raw.githubusercontent.com/{$path}/{$version}/composer.json";
        $repoPath = strtolower($path);
    } else {
        // GitLab oder andere: wir nehmen den Pfad nach Host
        $parts = parse_url($repoUrl);
        $path  = trim($parts['path'] ?? '', '/'); // z.B. owner/repo or group/subgroup/repo
        // Für Vergleich nur owner/repo (letzte zwei Segmente) verwenden
        $segments = array_values(array_filter(explode('/', $path)));
        $len = count($segments);
        if ($len >= 2) {
            $repoPath = strtolower($segments[$len - 2] . '/' . $segments[$len - 1]);
        } else {
            $repoPath = strtolower($path);
        }
        $repoComposerUrl = "{$repoUrl}/-/raw/{$version}/composer.json";
    }

    $this->appendLog("Versuche composer.json aus Repo zu laden: $repoComposerUrl");

    $remoteJson = @file_get_contents($repoComposerUrl);
    if (!$remoteJson) {
        $this->appendLog("Fehler: composer.json im Repo konnte nicht geladen werden.");
        return false;
    }

    $remote = json_decode($remoteJson, true);
    if (!isset($remote['name'])) {
        $this->appendLog("Fehler: composer.json im Repo enthält keinen 'name'-Eintrag.");
        return false;
    }

    $actualName = strtolower($remote['name']); // z.B. smarty/smarty
    $expectedName = isset($project['expected_name']) ? strtolower($project['expected_name']) : null;

    $this->appendLog("Paketname aus Repo erkannt: {$remote['name']}");
    $this->appendLog("Repo-Pfad (für Vergleich): {$repoPath}");
    if ($expectedName) {
        $this->appendLog("Erwarteter Paketname (explicit): {$expectedName}");
    }

    // Zulassungsregel:
    // - wenn expected_name gesetzt ist: nur zulassen, wenn expected_name === actualName
    // - sonst: nur zulassen, wenn repoPath === actualName
    $allow = false;
    if ($expectedName) {
        if ($expectedName === $actualName) {
            $allow = true;
        } else {
            $allow = false;
        }
    } else {
        // kein expected_name: prüfen ob repoPath exakt dem Paketnamen entspricht
        if ($repoPath === $actualName) {
            $allow = true;
        } else {
            $allow = false;
        }
    }

    if (!$allow) {
        $this->appendLog("Installation blockiert: Repo-Pfad '{$repoPath}' stimmt nicht mit Paketname '{$actualName}' überein.");
        $project['install_blocked'] = true;

        // Sicherheit: entferne vorhandenen Repo-Eintrag falls vorhanden
        $json = file_exists($this->composerJson) ? json_decode(file_get_contents($this->composerJson), true) : [];
        if (!empty($json['repositories'])) {
            $before = count($json['repositories']);
            $json['repositories'] = array_values(array_filter($json['repositories'], function($r) use ($project){
                return !(($r['type'] ?? '') === 'vcs' && ($r['url'] ?? '') === $project['repo_url']);
            }));
            if (count($json['repositories']) !== $before) {
                file_put_contents($this->composerJson, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
                $this->appendLog("Repository-Eintrag entfernt (Mismatch): {$project['repo_url']}");
            }
        }

        return false;
    }

    // Erlaubt: Paketname setzen und composer.json anpassen (repositories + require)
    $project['package_name'] = $remote['name']; // original case

    $json = file_exists($this->composerJson) ? json_decode(file_get_contents($this->composerJson), true) : [];
    if (!is_array($json)) $json = [];

    // repositories pflegen
    $json['repositories'] = $json['repositories'] ?? [];
    $repoExists = false;
    foreach ($json['repositories'] as $repo) {
        if (($repo['url'] ?? '') === $project['repo_url']) {
            $repoExists = true;
            break;
        }
    }
    if (!$repoExists) {
        $json['repositories'][] = ['type' => 'vcs', 'url' => $project['repo_url']];
        $this->appendLog("Repository hinzugefügt: {$project['repo_url']}");
    } else {
        $this->appendLog("Repository bereits vorhanden: {$project['repo_url']}");
    }

    // require pflegen: nur hinzufügen, wenn noch nicht vorhanden
    $json['require'] = $json['require'] ?? [];
    $requireVersion = $project['require_version'] ?? ($project['version'] ?? null);
    if ($requireVersion === null) {
        $requireVersion = '*';
    }
    if (!isset($json['require'][$project['package_name']])) {
        $json['require'][$project['package_name']] = $requireVersion;
        $this->appendLog("Require hinzugefügt: {$project['package_name']} => {$requireVersion}");
    } else {
        $this->appendLog("Require bereits vorhanden für: {$project['package_name']}");
    }

    // composer.json schreiben
    file_put_contents($this->composerJson, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    return true;
}




    // ------------------ Interne Helfer ------------------

    private function determinePackageName(array $project): string
    {
        // Bevorzugt packagist Name, sonst fallback
        if (!empty($project['package_name'])) return $project['package_name'];
        // Notfalls "vendor/package" aus author/title
        $author = preg_replace('/\s+/', '-', strtolower($project['author'] ?? 'unknown'));
        $name = preg_replace('/\s+/', '-', strtolower($project['title'] ?? 'pkg'));
        return $author . '/' . $name;
    }

    private function ensureComposerEntries(array $project, ?string $versionConstraint = null): void
    {
        $json = $this->readComposerJson();
        if (!$json) throw new \RuntimeException('composer.json nicht gefunden oder ungültig');

        // require pflegen
        $pkg = $this->determinePackageName($project);
        $json['require'] = $json['require'] ?? [];
        $json['require'][$pkg] = $versionConstraint ?: '*';

        // repositories pflegen (VCS), falls nicht über Packagist gefunden
        $repoUrl = $project['repo_url'] ?? null;
        if ($repoUrl) {
            $json['repositories'] = $json['repositories'] ?? [];
            // Falls bereits vorhanden, nichts tun
            $already = false;
            foreach ($json['repositories'] as $r) {
                if (($r['type'] ?? '') === 'vcs' && ($r['url'] ?? '') === $repoUrl) {
                    $already = true; break;
                }
            }
            if (!$already) {
                $json['repositories'][] = ['type' => 'vcs', 'url' => $repoUrl];
            }
        }

        $this->writeComposerJsonSelective($json);
    }

    private function removeRepositoryFor(array $project): void
    {
        $json = $this->readComposerJson();
        if (!$json) return;

        $repoUrl = $project['repo_url'] ?? null;
        if ($repoUrl && !empty($json['repositories'])) {
            $json['repositories'] = array_values(array_filter($json['repositories'], function($r) use ($repoUrl){
                return !(($r['type'] ?? '') === 'vcs' && ($r['url'] ?? '') === $repoUrl);
            }));
        }
        // require entfernen
        $pkg = $this->determinePackageName($project);
        if (!empty($json['require'][$pkg])) {
            unset($json['require'][$pkg]);
        }
        $this->writeComposerJsonSelective($json);
    }

    private function readComposerJson(): ?array
    {
        if (!file_exists($this->composerJson)) return null;
        $data = json_decode(file_get_contents($this->composerJson), true);
        return is_array($data) ? $data : null;
    }

    private function readComposerLock(): ?array
    {
        $lockFile = dirname($this->composerJson) . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!file_exists($lockFile)) return null;
        $data = json_decode(file_get_contents($lockFile), true);
        return is_array($data) ? $data : null;
    }

    private function writeComposerJsonSelective(array $json): void
    {
        // Nur require/repositories wurden angepasst, Rest bleibt erhalten
        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->composerJson, $encoded . PHP_EOL);
    }

    private function runComposer(array $args): bool
    {
        $phpBin = $this->detectPhpBinary();
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($this->composerPhar) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $cwd = dirname($this->composerJson);

        $this->appendLog("CMD: " . $cmd);
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptorspec, $pipes, $cwd);
        if (!is_resource($proc)) {
            $this->appendLog("Composer Start fehlgeschlagen");
            return false;
        }
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        $this->appendLog($out);
        $this->appendLog($err);
        $this->appendLog("Exit Code: " . $code);

        return $code === 0;
    }

    private function detectPhpBinary(): string
    {
        // Cross-OS Erkennung: erst relative "php", sonst Windows XAMPP Pfad fallback
        // Unix: "php" im PATH
        $php = 'php';
        // Test ob lauffähig
        $which = $this->which($php);
        if ($which) return $which;

        // Windows Fallback
        $winFallback = 'C:\\xampp\\php\\php.exe';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && file_exists($winFallback)) {
            return $winFallback;
        }
        // Letzter Versuch: direkt "php"
        return $php;
    }

    private function which(string $binary): ?string
    {
        // einfacher which/where
        $cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'where ' . escapeshellarg($binary) : 'which ' . escapeshellarg($binary);
        $out = [];
        @exec($cmd, $out);
        return isset($out[0]) && $out[0] !== '' ? $out[0] : null;
    }

    private function appendLog(string $text): void
    {
        file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $text . PHP_EOL, FILE_APPEND);
    }
}

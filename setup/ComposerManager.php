<?php
// classes/ComposerManager.php

declare(strict_types=1);

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
        // Composer runs in project root (composer.json must exist there)
        $cmd = escapeshellcmd($this->phpBin) . ' ' . escapeshellarg($this->composerPhar);
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

    public function getInstalledPackages(): array
    {
        // Use composer show --installed --format=json
        $res = $this->runComposer(['show', '--format=json']);
        if (!$res['ok']) return [];
        $json = json_decode($res['out'], true);
        $installed = [];
        foreach (($json['installed'] ?? []) as $pkg) {
            $name = $pkg['name'] ?? '';
            $version = $pkg['version'] ?? '';
            $latest = $pkg['latest'] ?? $version;
            $installed[$name] = [
                'version' => $version,
                'latest' => $latest,
                'update_available' => $latest !== $version
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

<?php

class Composer
{
    private string $composerPath;
    private string $phpBinary;
    private string $projectRoot;
    private int $timeoutSeconds = 300; // Timeout für lange Operationen

    public function __construct(string $composerPath)
    {
        $this->composerPath = $composerPath;
        $this->projectRoot = dirname($_SERVER['SCRIPT_FILENAME']).'/../';
        $this->phpBinary = $this->detectPhpBinary();

        if (!getenv('COMPOSER_HOME')) {
            putenv('COMPOSER_HOME=' . sys_get_temp_dir());
        }
    }
	
	private function detectPhpBinary(): string
{
    // 1. Dynamische Liste aller potenziellen CLI-Pfade generieren
    $majorMinor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; // z.B. 8.2
    $short = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;           // z.B. 82

    $candidates = [
        // Windows / XAMPP Standard
        'C:\\xampp\\php\\php.exe',
        
        // Linux Standard & versionsspezifische Pfade
        '/usr/local/bin/php',
        "/usr/local/bin/php{$short}",
        '/usr/local/bin/php-cli',
        "/usr/local/bin/php{$majorMinor}-cli",
        '/usr/bin/php',
        "/usr/bin/php{$majorMinor}",
        "/usr/bin/php{$short}",
        '/usr/bin/php-cli',
        "/usr/bin/php{$majorMinor}-cli",
        
        // Hoster-Strukturen (z.B. Plesk)
        "/opt/plesk/php/{$majorMinor}/bin/php"
    ];

    // 2. Kandidaten durchlaufen und aktiv auf echte CLI-SAPI prüfen
    foreach ($candidates as $path) {
        if (@is_file($path) && @is_executable($path)) {
            // Führt einen kurzen Testbefehl mit der gefundenen Binary aus
            $sapi = @shell_exec(escapeshellarg($path) . ' -r "echo PHP_SAPI;" 2>&1');
            
            // Wenn die Rückgabe exakt 'cli' lautet, haben wir den perfekten Pfad
            if (trim($sapi) === 'cli') {
                return $path;
            }
        }
    }
	
	// 2.5. Linux Systembefehle nutzen, um nach 'php-cli' und 'php' zu suchen
    // 'which' sucht im gesamten System-PATH nach der ausführbaren Datei
    foreach (['php-cli', 'php'] as $cmd) {
        $path = @shell_exec("which $cmd 2>&1");
        if ($path) {
            $path = trim($path);
            // Validierung: Ist der gefundene System-Pfad wirklich eine CLI-SAPI?
            $sapi = @shell_exec(escapeshellarg($path) . ' -r "echo PHP_SAPI;" 2>&1');
            if (trim($sapi) === 'cli') {
                return $path;
            }
        }
    }

    // 3. Fallback 1: Versuche 'php-cli' direkt über das System aufzurufen
    $systemCliTest = @shell_exec('php-cli -r "echo PHP_SAPI;" 2>&1');
    if (trim($systemCliTest) === 'cli') {
        return 'php-cli';
    }

    // 4. Fallback 2: Falls PHP_BINARY (die aktuelle Web-SAPI) wider Erwarten doch CLI ist
    if (defined('PHP_BINARY') && is_executable(PHP_BINARY)) {
        $webSapiTest = @shell_exec(escapeshellarg(PHP_BINARY) . ' -r "echo PHP_SAPI;" 2>&1');
        if (trim($webSapiTest) === 'cli') {
            return PHP_BINARY;
        }
    }


    return false;
}


    public function stream(string $command): Generator
    {
        // Arbeitsverzeichnis setzen
        chdir($this->projectRoot);

        // Verbose
        $command = '-vv ' . $command;

        // Prüfen, ob php binary und composer.phar existieren
        $debugFile = $this->projectRoot . '/composer_debug.txt';
        file_put_contents($debugFile, "=== START " . date('c') . " ===\n", FILE_APPEND);

        if (!is_executable($this->phpBinary) && !@is_file($this->phpBinary)) {
            file_put_contents($debugFile, "ERROR: PHP binary not executable or not found: {$this->phpBinary}\n", FILE_APPEND);
            yield "Fehler: PHP binary nicht gefunden: {$this->phpBinary}";
            return;
        }

        if (!is_file($this->composerPath)) {
            file_put_contents($debugFile, "ERROR: composer.phar not found: {$this->composerPath}\n", FILE_APPEND);
            yield "Fehler: composer.phar nicht gefunden: {$this->composerPath}";
            return;
        }

        // Befehl bauen - use escapeshellarg for paths
        $fullCommand =
            escapeshellarg($this->phpBinary) . ' ' .
            escapeshellarg($this->composerPath) . ' ' .
            '--working-dir=' . escapeshellarg($this->projectRoot) . ' ' .
            $command;

        file_put_contents($debugFile, "CMD: $fullCommand\n", FILE_APPEND);
        file_put_contents($debugFile, "PWD: " . getcwd() . "\n", FILE_APPEND);

        // Basis-Environment: merge von getenv + $_ENV + $_SERVER, sicherstellen PATH vorhanden
        $baseEnv = [];
        // getenv list
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $p) {
            // noop, we will set PATH below
        }
        if (is_array($_ENV)) $baseEnv = array_merge($baseEnv, $_ENV);
        if (is_array($_SERVER)) $baseEnv = array_merge($baseEnv, $_SERVER);

        // Ensure PATH is present
        if (!isset($baseEnv['PATH']) && getenv('PATH') !== false) {
            $baseEnv['PATH'] = getenv('PATH');
        }

        // Env overrides: leere COMPOSER_CAFILE zwingt Composer, internes CA zu verwenden
        $envOverrides = [
            'COMPOSER_HOME' => sys_get_temp_dir(),
            'COMPOSER_CAFILE' => '',
            'SSL_CERT_FILE' => '',
            'CURL_CA_BUNDLE' => '',
        ];

        $env = array_merge($baseEnv, $envOverrides);

        // Wähle Modus abhängig von SAPI
		$isCli = (PHP_SAPI === 'cli');

		if ($isCli) {
			// echtes Passthrough in CLI: Composer schreibt direkt in die Konsole
			$descriptorSpec = [
				0 => ['pipe', 'r'],
				1 => ['file', 'php://stdout', 'w'],
				2 => ['file', 'php://stderr', 'w'],
			];
		} else {
			// Nicht-CLI: wir wollen die Ausgabe lesen und selbst echoen (damit sie im Browser erscheint)
			$descriptorSpec = [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			];
		}

		$start = microtime(true);
		$process = proc_open($fullCommand, $descriptorSpec, $pipes, $this->projectRoot, $env);

		if (!is_resource($process)) {
			file_put_contents($debugFile, "ERROR: proc_open failed\n", FILE_APPEND);
			yield "Fehler: Composer-Prozess konnte nicht gestartet werden.";
			return;
		}

		if (isset($pipes[0]) && is_resource($pipes[0])) {
			fclose($pipes[0]);
		}

		$startTime = time();

		if ($isCli) {
			// CLI passthrough: Composer schreibt direkt in die Konsole; wir warten nur auf Ende
			while (true) {
				$status = proc_get_status($process);
				if (!$status['running']) break;
				if ((time() - $startTime) > $this->timeoutSeconds) {
					proc_terminate($process);
					file_put_contents($debugFile, "ERROR: Composer process timed out after {$this->timeoutSeconds}s\n", FILE_APPEND);
					yield "Fehler: Composer-Prozess hat Timeout erreicht.";
					break;
				}
				usleep(20000);
			}
		} else {
			// Nicht-CLI: Capture-Modus, zeilenweise lesen und sofort ausgeben (oder yield)
			stream_set_blocking($pipes[1], false);
			stream_set_blocking($pipes[2], false);

			$suppressTrace = false;

			while (true) {
				$read = [$pipes[1], $pipes[2]];
				$write = null;
				$except = null;
				$num = @stream_select($read, $write, $except, 0, 200000);

				if ($num === false) break;

				if ($num > 0) {
					foreach ($read as $r) {
						while (($line = fgets($r)) !== false) {
							$line = rtrim($line, "\r\n");

							// Falls Composer die kurze Meldung direkt liefert, gib sie durch
							if (preg_match('/^Command "([^"]+)" is not defined\./', $line)) {
								echo $line . PHP_EOL;
								$suppressTrace = true;
								continue;
							}

							// Wenn wir Symfony Exception-Header sehen, ersetzen wir durch kurze Meldung
							if (strpos($line, 'Symfony\\Component\\Console\\Exception\\CommandNotFoundException') !== false
								|| strpos($line, 'Exception trace:') !== false
								|| strpos($line, 'Stack trace:') !== false) {
								// kurze, konsistente Meldung
								echo 'Command is not defined.' . PHP_EOL;
								$suppressTrace = true;
								continue;
							}

							if ($suppressTrace) {
								// unterdrücke Stacktrace-Zeilen
								continue;
							}

							// Normale Ausgabe: sofort echoen (erscheint im Browser/Response)
							echo $line . PHP_EOL;
							// optional: flush, damit Browser die Ausgabe schrittweise erhält
							if (function_exists('flush')) { @flush(); @ob_flush(); }
						}
					}
				}

				$status = proc_get_status($process);
				if (!$status['running']) {
					// Restpuffer lesen
					while (($line = fgets($pipes[1])) !== false) {
						echo rtrim($line, "\r\n") . PHP_EOL;
					}
					while (($line = fgets($pipes[2])) !== false) {
						echo rtrim($line, "\r\n") . PHP_EOL;
					}
					break;
				}

				if ((time() - $startTime) > $this->timeoutSeconds) {
					proc_terminate($process);
					file_put_contents($debugFile, "ERROR: Composer process timed out after {$this->timeoutSeconds}s\n", FILE_APPEND);
					echo "Fehler: Composer-Prozess hat Timeout erreicht." . PHP_EOL;
					break;
				}

				usleep(20000);
			}
		}

		// Prozess beenden und Exitcode holen
		$exit = proc_close($process);
		$duration = microtime(true) - $start;

		// Debug-Log
		$log = "EXIT: $exit\nDURATION: {$duration}s\n";
		file_put_contents($debugFile, $log, FILE_APPEND);

		// Wenn du weiterhin Generator-yields brauchst, yield hier den Exitcode
		#yield "EXIT: $exit";


    }
}

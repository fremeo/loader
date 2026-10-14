<?php

class Composer
{
    private string $composerPath;
    private string $phpBinary;
    private string $projectRoot;
    private int $timeoutSeconds = 300; // Timeout für lange Operationen
    private ?string $capture = null; // gesetzt während run(): Ausgabe wird gesammelt statt ausgegeben

    /**
     * @param string      $composerPath Pfad zur composer.phar
     * @param string|null $projectRoot  Projektordner (mit composer.json). Ohne Angabe: Ordner über dem aufrufenden Skript (setup/console.php).
     */
    public function __construct(string $composerPath, ?string $projectRoot = null)
    {
        $this->composerPath = $composerPath;
        $this->projectRoot = $projectRoot ?? dirname($_SERVER['SCRIPT_FILENAME']).'/../';
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
            $sapi = (string)$this->shell(escapeshellarg($path) . ' -r "echo PHP_SAPI;"');
            
            // Wenn die Rückgabe exakt 'cli' lautet, haben wir den perfekten Pfad
            if (trim($sapi) === 'cli') {
                return $path;
            }
        }
    }
	
	// 2.5. Linux Systembefehle nutzen, um nach 'php-cli' und 'php' zu suchen
    // 'which' sucht im gesamten System-PATH nach der ausführbaren Datei
    foreach (['php-cli', 'php'] as $cmd) {
        $path = (string)$this->shell("which $cmd");
        if ($path) {
            $path = trim($path);
            // Validierung: Ist der gefundene System-Pfad wirklich eine CLI-SAPI?
            $sapi = (string)$this->shell(escapeshellarg($path) . ' -r "echo PHP_SAPI;"');
            if (trim($sapi) === 'cli') {
                return $path;
            }
        }
    }

    // 3. Fallback 1: Versuche 'php-cli' direkt über das System aufzurufen
    $systemCliTest = (string)$this->shell('php-cli -r "echo PHP_SAPI;"');
    if (trim($systemCliTest) === 'cli') {
        return 'php-cli';
    }

    // 4. Fallback 2: Falls PHP_BINARY (die aktuelle Web-SAPI) wider Erwarten doch CLI ist
    if (defined('PHP_BINARY') && is_executable(PHP_BINARY)) {
        $webSapiTest = (string)$this->shell(escapeshellarg(PHP_BINARY) . ' -r "echo PHP_SAPI;"');
        if (trim($webSapiTest) === 'cli') {
            return PHP_BINARY;
        }
    }


    return false;
}


    /**
     * Führt einen Shell-Befehl aus und gibt die Ausgabe zurück. Auf vielen Servern ist proc_open gesperrt, deshalb der Reihe nach
     * proc_open, popen, shell_exec und exec. Null = keine dieser Funktionen ist verfügbar (alle über disable_functions gesperrt).
     */
    protected function shell(string $command): ?string
    {
        $command .= ' 2>&1';

        if (function_exists('proc_open')) {
            $process = @proc_open($command, [1 => ['pipe', 'w']], $pipes);
            if (is_resource($process)) {
                $out = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                proc_close($process);
                return (string)$out;
            }
        }
        if (function_exists('popen')) {
            $handle = @popen($command, 'r');
            if (is_resource($handle)) {
                $out = stream_get_contents($handle);
                pclose($handle);
                return (string)$out;
            }
        }
        if (function_exists('shell_exec')) {
            return (string)@shell_exec($command);
        }
        if (function_exists('exec')) {
            @exec($command, $lines);
            return implode(PHP_EOL, $lines);
        }

        return null;
    }

    /**
     * Führt die Composer-Skripte eines Events (z.B. post-install-cmd) aus der composer.json aus.
     * Ohne proc_open kann Composer selbst keine Skripte starten, deshalb führt das Setup sie anhand der composer.json aus.
     * "@php ..." läuft mit der gefundenen PHP-CLI, andere "@..."-Verweise werden übersprungen.
     */
    protected function runScripts(string $event): void
    {
        $json = json_decode((string)@file_get_contents($this->projectRoot . '/composer.json'), true);
        $commands = (array)($json['scripts'][$event] ?? []);
        if (!$commands) {
            return;
        }

        $this->emit(PHP_EOL . "> Composer-Skript {$event}" . PHP_EOL);
        chdir($this->projectRoot);

        foreach ($commands as $original) {
            $this->emit("> {$original}" . PHP_EOL);
            $command = $original;

            if (str_starts_with($command, '@php ')) {
                if ($this->phpBinary === '') {
                    $this->emit('Fehler: Es wurde keine PHP-CLI gefunden, das Skript kann nicht ausgeführt werden.' . PHP_EOL);
                    return;
                }
                $command = escapeshellarg($this->phpBinary) . ' ' . substr($command, 5);
            } elseif (str_starts_with($command, '@')) {
                $this->emit('Übersprungen (vom Setup nicht unterstützt).' . PHP_EOL);
                continue;
            }

            $output = $this->shell($command);
            if ($output === null) {
                $this->emit('Fehler: Auf diesem Server kann kein Prozess gestartet werden (proc_open, popen, shell_exec und exec sind gesperrt).' . PHP_EOL
                    . 'Führe das Skript per SSH oder als Cron-Job im Projektordner aus:' . PHP_EOL . "  {$original}" . PHP_EOL);
                return;
            }
            $this->emit($output . PHP_EOL);
        }
    }

    /** Composer-Events, die zu einem Befehl gehören (so wie Composer sie selbst auslösen würde). */
    protected function eventsFor(string $command): array
    {
        return match (true) {
            (bool)preg_match('/\b(re)?install\b/', $command)     => ['post-install-cmd'],
            (bool)preg_match('/\b(update|require)\b/', $command)  => ['post-update-cmd'],
            (bool)preg_match('/\bremove\b/', $command)            => ['post-package-uninstall', 'post-update-cmd'],
            default                                               => [],
        };
    }

    /**
     * Kann ein eigener Prozess gestartet werden? Auf vielen Servern ist proc_open über disable_functions gesperrt,
     * oder es gibt keine nutzbare PHP-CLI-Binary. Dann läuft Composer im selben PHP-Prozess (siehe streamInProcess).
     */
    protected function canSpawnProcess(): bool
    {
        return function_exists('proc_open') && $this->phpBinary !== '';
    }

    /** Ausgabe an den Browser bzw. die Konsole (in Tests überschreibbar). Während run() wird sie stattdessen gesammelt. */
    protected function emit(string $text): void
    {
        if ($this->capture !== null) {
            $this->capture .= $text;
            return;
        }
        echo $text;
        if (function_exists('flush')) { @ob_flush(); @flush(); }
    }

    /**
     * Führt einen Composer-Befehl aus und gibt die komplette Ausgabe zurück: ['success' => bool, 'output' => string].
     * Fehler des Setups (z.B. composer.phar fehlt) werden von stream() gemeldet und gelten als nicht erfolgreich.
     */
    public function run(string $command): array
    {
        $this->capture = '';
        $errors = [];
        try {
            foreach ($this->stream($command) as $line) {
                $errors[] = (string)$line;
            }
        } finally {
            $output = $this->capture;
            $this->capture = null;
        }

        return [
            'success' => !$errors,
            'output'  => trim($output . PHP_EOL . implode(PHP_EOL, $errors)),
        ];
    }

    /**
     * Installierte Pakete aus vendor/composer/installed.json (kein Prozessstart nötig):
     * [Name => ['version', 'type', 'description', 'author']]. Den neuesten Stand liefert Packagist.
     */
    public function getInstalledPackages(): array
    {
        $root      = rtrim($this->projectRoot, '/\\');
        $json      = json_decode((string)@file_get_contents($root . '/composer.json'), true);
        $vendorDir = $root . '/' . trim((string)($json['config']['vendor-dir'] ?? 'vendor'), '/\\');
        $installed = json_decode((string)@file_get_contents($vendorDir . '/composer/installed.json'), true);

        $packages = [];
        foreach ((array)($installed['packages'] ?? $installed ?? []) as $pkg) {
            if (empty($pkg['name'])) {
                continue;
            }
            $packages[$pkg['name']] = [
                'version'     => $pkg['version'] ?? '',
                'type'        => $pkg['type'] ?? '',
                'description' => $pkg['description'] ?? '',
                'author'      => $pkg['authors'][0]['name'] ?? '',
            ];
        }
        ksort($packages);

        return $packages;
    }

    /**
     * Führt composer.phar ohne neuen Prozess aus: die Phar wird eingebunden und die Composer-Anwendung direkt gestartet.
     * Voraussetzungen: PHP-Erweiterung phar, allow_url_fopen oder curl, für Pakete zip (ZipArchive); genug Speicher und Laufzeit.
     */
    protected function streamInProcess(string $command): Generator
    {
        $debugFile = $this->projectRoot . '/composer_debug.txt';
        file_put_contents($debugFile, "MODE: im selben Prozess (proc_open nicht verfügbar oder keine PHP-CLI gefunden)\n", FILE_APPEND);

        // "run-script <event>": Composer kann ohne proc_open keine Skripte starten, das Setup führt sie aus der composer.json aus
        if (preg_match('/\brun-script\s+(\S+)/', $command, $m)) {
            $this->runScripts($m[1]);
            return;
        }

        $phar = realpath($this->composerPath);
        if ($phar === false) {
            yield "Fehler: composer.phar nicht gefunden: {$this->composerPath}";
            return;
        }
        if (!in_array('phar', stream_get_wrappers(), true)) {
            yield "Fehler: Für Composer ohne Prozessstart wird die PHP-Erweiterung 'phar' benötigt.";
            return;
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '2G');

        // Composer-Skripte wie "@php index.php migration" brauchen einen Prozess und laufen hier nicht: weglassen,
        // die Migration wird danach in der Konsole mit dem Befehl "migration" ausgeführt.
        $skipScripts = preg_match('/\b(install|reinstall|update|require|remove)\b/', $command) === 1 && !str_contains($command, '--no-scripts');
        if ($skipScripts) {
            $command .= ' --no-scripts';
        }
        foreach (['COMPOSER_NO_INTERACTION' => '1', 'COMPOSER_HOME' => sys_get_temp_dir(), 'COMPOSER_CAFILE' => '', 'SSL_CERT_FILE' => '', 'CURL_CA_BUNDLE' => ''] as $k => $v) {
            putenv("$k=$v");
        }
        chdir($this->projectRoot);

        try {
            require_once 'phar://' . $phar . '/vendor/autoload.php';

            $emit = fn(string $text) => $this->emit($text);
            $output = new class($emit) extends \Symfony\Component\Console\Output\Output {
                public function __construct(private \Closure $emit)
                {
                    parent::__construct();
                }

                protected function doWrite(string $message, bool $newline): void
                {
                    ($this->emit)($message . ($newline ? PHP_EOL : ''));
                }
            };

            $workingDir = str_replace('\\', '/', realpath($this->projectRoot));
            $application = new \Composer\Console\Application();
            $application->setAutoExit(false);
            $exit = $application->run(new \Symfony\Component\Console\Input\StringInput($command . ' --no-interaction --no-ansi --working-dir="' . $workingDir . '"'), $output);

            file_put_contents($debugFile, "EXIT: $exit\n", FILE_APPEND);

            if ($skipScripts && $exit === 0) {
                foreach ($this->eventsFor($command) as $event) {
                    $this->runScripts($event);
                }
            }
        } catch (\Throwable $e) {
            file_put_contents($debugFile, 'ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
            yield 'Fehler: ' . $e->getMessage();
        }
    }

    public function stream(string $command): Generator
    {
        // Arbeitsverzeichnis setzen
        chdir($this->projectRoot);

        // Verbose
        $command = '-vv ' . $command;

        // Ohne proc_open (oder ohne PHP-CLI) läuft Composer im selben Prozess
        if (!$this->canSpawnProcess()) {
            yield from $this->streamInProcess($command);
            return;
        }

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

        $env = array_filter(array_merge($baseEnv, $envOverrides), 'is_scalar'); // Arrays (z.B. argv aus $_SERVER) sind keine gültigen Umgebungsvariablen

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
								$this->emit($line . PHP_EOL);
								$suppressTrace = true;
								continue;
							}

							// Wenn wir Symfony Exception-Header sehen, ersetzen wir durch kurze Meldung
							if (strpos($line, 'Symfony\\Component\\Console\\Exception\\CommandNotFoundException') !== false
								|| strpos($line, 'Exception trace:') !== false
								|| strpos($line, 'Stack trace:') !== false) {
								// kurze, konsistente Meldung
								$this->emit('Command is not defined.' . PHP_EOL);
								$suppressTrace = true;
								continue;
							}

							if ($suppressTrace) {
								// unterdrücke Stacktrace-Zeilen
								continue;
							}

							// Normale Ausgabe: sofort ausgeben (erscheint im Browser/Response)
							$this->emit($line . PHP_EOL);
						}
					}
				}

				$status = proc_get_status($process);
				if (!$status['running']) {
					// Restpuffer lesen
					while (($line = fgets($pipes[1])) !== false) {
						$this->emit(rtrim($line, "\r\n") . PHP_EOL);
					}
					while (($line = fgets($pipes[2])) !== false) {
						$this->emit(rtrim($line, "\r\n") . PHP_EOL);
					}
					break;
				}

				if ((time() - $startTime) > $this->timeoutSeconds) {
					proc_terminate($process);
					file_put_contents($debugFile, "ERROR: Composer process timed out after {$this->timeoutSeconds}s\n", FILE_APPEND);
					$this->emit("Fehler: Composer-Prozess hat Timeout erreicht." . PHP_EOL);
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

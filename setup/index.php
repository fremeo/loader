<?php
// Name der geschützten Zieldatei (im selben Ordner)
$targetFile = 'console.php';

// Pfade zu den Schutzdateien im aktuellen Verzeichnis
$htaccessPath = __DIR__ . '/.htaccess';
$htpasswdPath = __DIR__ . '/.htpasswd';

// 1. SCHRITT: Prüfen, ob der Schutz bereits existiert
if (file_exists($htaccessPath) && file_exists($htpasswdPath)) {
    header("Location: $targetFile");
    exit;
}

// 2. SCHRITT: Umgebung erkennen (Windows vs. Linux)
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// 3. SCHRITT: Zugangsdaten generieren
$username = 'admin';
$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$password = '';
for ($i = 0; $i < 12; $i++) {
    $password .= $chars[random_int(0, strlen($chars) - 1)];
}

// 4. SCHRITT: Passwort-Verschlüsselung je nach System wählen
if ($isWindows) {
    // Lokal unter XAMPP: Klartext (da Windows-Apache oft Probleme mit crypt() hat)
    $hashedPassword = $password;
} else {
    // Live unter Linux: Sicheres SHA-512 (Standard für moderne Linux-Server)
    $salt = '$6$' . bin2hex(random_bytes(8)) . '$';
    $hashedPassword = crypt($password, $salt);
}

// Inhalt für .htpasswd erzeugen
$htpasswdContent = $username . ":" . $hashedPassword . "\n";


// 5. SCHRITT: Pfad für die .htaccess an das Betriebssystem anpassen
$htpasswdApachePath = $htpasswdPath;

if ($isWindows) {
    // Windows-Korrekturen: Backslashes zu Slashes, Laufwerksbuchstabe GROSS, keine Anführungszeichen
    $htpasswdApachePath = str_replace('\\', '/', $htpasswdApachePath);
    if (preg_match('/^[a-z]:/i', $htpasswdApachePath)) {
        $htpasswdApachePath = ucfirst($htpasswdApachePath);
    }
    $authFileDirective = "AuthUserFile " . $htpasswdApachePath;
} else {
    // Linux-Standard: Pfad in Anführungszeichen (erlaubt Leerzeichen im Pfad falls vorhanden)
    $authFileDirective = "AuthUserFile \"" . $htpasswdApachePath . "\"";
}

// Inhalt für .htaccess erzeugen
$htaccessContent = "AuthType Basic\n";
$htaccessContent .= "AuthName \"Restricted Area\"\n";
$htaccessContent .= $authFileDirective . "\n"; 
$htaccessContent .= "Require valid-user\n";


// 6. SCHRITT: Dateien schreiben
if (file_put_contents($htpasswdPath, $htpasswdContent) && file_put_contents($htaccessPath, $htaccessContent)) {
    $success = true;
} else {
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Verzeichnisschutz eingerichtet</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; color: #333; padding: 40px; }
        .card { background: #fff; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; font-size: 24px; margin-top: 0; }
        .credentials { background: #eef2f7; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 0 4px 4px 0; }
        .credentials p { margin: 5px 0; font-family: monospace; font-size: 16px; }
        .btn { display: inline-block; background: #2ecc71; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 15px; }
        .btn:hover { background: #27ae60; }
        .badge { display: inline-block; padding: 4px 8px; background: #e67e22; color: white; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge.linux { background: #2980b9; }
    </style>
</head>
<body>

<div class="card">
    <?php if ($success): ?>
        <h1>🔒 Verzeichnisschutz aktiv</h1>
        <p>Umgebung erkannt: 
            <span class="badge <?php echo !$isWindows ? 'linux' : ''; ?>">
                <?php echo $isWindows ? 'Windows (XAMPP Lokal)' : 'Linux (Live-Server)'; ?>
            </span>
        </p>
        <p>Das Passwort wurde passend für dieses Betriebssystem hinterlegt (<?php echo $isWindows ? 'Klartext' : 'SHA-512 Verschlüsselt'; ?>).</p>
        
        <div class="credentials">
            <p><strong>Benutzername:</strong> <?php echo htmlspecialchars($username); ?></p>
            <p><strong>Passwort:</strong> <?php echo htmlspecialchars($password); ?></p>
        </div>
        
        <p>Bitte testen Sie den Zugang (am besten im <strong>Inkognito-Modus</strong> Ihres Browsers):</p>
        <a href="<?php echo htmlspecialchars($targetFile); ?>" class="btn">Zur console.php weiterleiten</a>
    <?php else: ?>
        <h1 style="color: #e74c3c;">❌ Fehler beim Schreiben der Dateien.</h1>
    <?php endif; ?>
</div>

</body>
</html>

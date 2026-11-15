<?php
/**
 * Composer Web-Konsole
 * Benötigt: composer.phar + Composer.php im gleichen Ordner
 */

require_once __DIR__ . '/Composer.php';

/* ===========================
   AJAX-Streaming Handler
   =========================== */
if (isset($_POST['cmd'])) {
    $cmd = trim($_POST['cmd']);

    // Composer-Instanz aus externer Datei
    $composer = new Composer(__DIR__ . '/composer.phar');

    header("Content-Type: text/plain");
    header("Cache-Control: no-cache");
    header("X-Accel-Buffering: no");

    foreach ($composer->stream($cmd) as $line) {
        echo $line . "\n";
        flush();
        ob_flush();
    }
    exit;
}

/* ===========================
   HTML / JS Oberfläche
   =========================== */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Composer Web-Konsole</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        body {
            background: #222;
            color: #eee;
            font-family: Consolas, monospace;
        }
        #console {
            background: #111;
            height: 500px;
            overflow-y: auto;
            border: 1px solid #444;
            white-space: pre-wrap;
        }
        #input {
            background: #000;
            color: #0f0;
            border: none;
            width: 100%;
            padding: 10px;
            font-family: Consolas, monospace;
        }
        #input:focus {
            outline: none;
        }
    </style>
</head>
<body class="p-3">

<h3 class="text-light mb-3">Composer Web-Konsole</h3>

<div id="console"></div>

<input id="input" type="text" placeholder="composer command eingeben und Enter drücken...">

<script>
$(function () {

    function appendToConsole(text) {
        $("#console").append(text + "\n");
        $("#console").scrollTop($("#console")[0].scrollHeight);
    }

    $("#input").keypress(function (e) {
        if (e.which === 13) {
            let cmd = $("#input").val().trim();
            if (cmd.length === 0) return;

            appendToConsole("<div class='sticky-top text-warning bg-dark'> > composer " + cmd + "</div>");
            $("#input").val("");

            let xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 3) {
                    appendToConsole("<div class='text-white-50 p-2'>"+xhr.responseText+"</div>");
                }
                if (xhr.readyState === 4) {
                    appendToConsole("\n<div class='text-success p-2'>--- Prozess beendet ---</div>\n");
                }
            };

            xhr.send("cmd=" + encodeURIComponent(cmd));
        }
    });

});
</script>

</body>
</html>

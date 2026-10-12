<?php
// Router pour le serveur de developpement integre de PHP
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Si l'URI commence par /gtm-tracker
if (strpos($uri, '/gtm-tracker') === 0) {
    $relative = substr($uri, strlen('/gtm-tracker'));
    if ($relative === '' || $relative === '/') {
        $relative = '/index.php';
    }
    $target = __DIR__ . $relative;
    if (is_dir($target)) {
        $target = rtrim($target, '/\\') . '/index.php';
    }

    if (file_exists($target) && !is_dir($target)) {
        $ext = pathinfo($target, PATHINFO_EXTENSION);
        $mimes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'ico'  => 'image/x-icon',
            'json' => 'application/json',
            'pdf'  => 'application/pdf',
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
            readfile($target);
            exit;
        }
        if ($ext === 'php') {
            require $target;
            exit;
        }
    }
}

// Requete normale a la racine
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

$direct = __DIR__ . $uri;
if (is_dir($direct)) {
    $indexFile = rtrim($direct, '/\\') . '/index.php';
    if (file_exists($indexFile)) {
        require $indexFile;
        exit;
    }
}
if (file_exists($direct) && !is_dir($direct)) {
    return false; // PHP sert le fichier directement
}

return false;


<?php
// router.php for PHP built-in web server

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Normalize path: strip leading /salonora if present
if (strpos($uri, '/salonora') === 0) {
    $uri = substr($uri, strlen('/salonora'));
}

if ($uri === '' || $uri === '/') {
    $uri = '/public/index.php';
}

// If accessing /public directly without file
if ($uri === '/public' || $uri === '/public/') {
    $uri = '/public/index.php';
}

$file = __DIR__ . $uri;

// If file exists and is not a directory, handle it
if (is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        require $file;
        return true;
    }
    
    // Serve static files with proper MIME type
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    if (isset($mimes[$ext])) {
        header("Content-Type: " . $mimes[$ext]);
        readfile($file);
        return true;
    }
    return false; // let built-in server handle other files
}

// Fallback: check inside public directory
$publicFile = __DIR__ . '/public' . $uri;
if (is_file($publicFile)) {
    $ext = pathinfo($publicFile, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        require $publicFile;
        return true;
    }
    return false;
}

http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri);

<?php

/**
 * Router for `php artisan serve` (PHP's built-in web server).
 *
 * Same as Laravel's default router, except that uploaded images are sent
 * with "Access-Control-Allow-Origin: *", like the original Express static
 * route (Apache gets the same header from public/uploads/.htaccess).
 */
$publicPath = getcwd();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$file = $publicPath.$uri;

if (str_starts_with($uri, '/uploads/') && is_file($file) && ! str_contains($uri, '..')) {
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpe' => 'image/jpeg', 'jfif' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    header('Content-Type: '.($types[$extension] ?? 'application/octet-stream'));
    header('Content-Length: '.filesize($file));
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');
    header('Cache-Control: public, max-age=0');
    header('Access-Control-Allow-Origin: *');
    header('X-Content-Type-Options: nosniff');

    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($file);
    }

    return true;
}

if ($uri !== '/' && file_exists($file)) {
    return false;
}

require_once $publicPath.'/index.php';

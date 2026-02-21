<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user'])) {
    setFlash('error', 'Please log in to continue.');
    redirect('login.php');
}
// Regenerate session periodically
if (!isset($_SESSION['last_regenerated']) || time() - $_SESSION['last_regenerated'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}

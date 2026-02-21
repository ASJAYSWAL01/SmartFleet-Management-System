<?php
require_once 'config.php';
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: login.php');
exit;

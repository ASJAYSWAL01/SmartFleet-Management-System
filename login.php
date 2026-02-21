<?php
require_once 'config.php';

// Already logged in
if (isset($_SESSION['user'])) {
    redirect('index.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            $_SESSION['last_regenerated'] = time();
            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('index.php');
        } else {
            $errors[] = 'Invalid credentials or account is inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — FleetFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-600 rounded-2xl mb-4 shadow-lg shadow-blue-500/30">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-white">FleetFlow</h1>
        <p class="text-slate-400 text-sm mt-1">Fleet & Logistics Management</p>
    </div>

    <!-- Card -->
    <div class="bg-white/10 backdrop-blur-md rounded-2xl border border-white/10 shadow-2xl p-8">
        <h2 class="text-xl font-semibold text-white mb-6">Sign in to your account</h2>

        <?php if (!empty($errors)): ?>
        <div class="mb-5 p-4 bg-red-500/20 border border-red-500/40 rounded-xl">
            <?php foreach ($errors as $err): ?>
            <p class="text-sm text-red-300"><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="<?= e($email) ?>"
                           class="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                           placeholder="you@company.com" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                    <input type="password" name="password"
                           class="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                           placeholder="••••••••" required>
                </div>
                <button type="submit"
                        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition shadow-lg shadow-blue-500/30 mt-2">
                    Sign In
                </button>
            </div>
        </form>

        <!-- Demo Credentials -->
        <div class="mt-6 p-4 bg-blue-600/10 border border-blue-500/20 rounded-xl">
            <p class="text-xs font-semibold text-blue-300 mb-2">Demo Credentials (password: password)</p>
            <div class="grid grid-cols-2 gap-1 text-xs text-slate-400">
                <span>Manager:</span><span class="text-slate-300">admin@fleetflow.com</span>
                <span>Dispatcher:</span><span class="text-slate-300">dispatcher@fleetflow.com</span>
                <span>Safety:</span><span class="text-slate-300">safety@fleetflow.com</span>
                <span>Finance:</span><span class="text-slate-300">finance@fleetflow.com</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>

<?php
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = $_SESSION['user'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — FleetFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link.active { background: rgba(59,130,246,0.15); color: #3b82f6; border-right: 3px solid #3b82f6; }
        .sidebar-link { transition: all .15s; }
        .sidebar-link:hover { background: rgba(255,255,255,0.06); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

<!-- Top Navbar -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white border-b border-slate-200 shadow-sm">
    <div class="flex items-center justify-between px-4 h-14">
        <!-- Logo + Mobile Menu Toggle -->
        <div class="flex items-center gap-3">
            <button id="sidebarToggle" class="p-2 rounded-md text-slate-500 hover:bg-slate-100 lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
                <span class="font-bold text-slate-800 text-lg">FleetFlow</span>
            </a>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-3">
            <span class="hidden sm:inline-flex text-xs font-medium px-2 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                <?= e($user['role'] ?? '') ?>
            </span>
            <!-- Profile Dropdown -->
            <div class="relative" id="profileDropdown">
                <button onclick="toggleDropdown()" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 transition">
                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="hidden md:block text-sm font-medium text-slate-700"><?= e($user['name'] ?? '') ?></span>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="profileMenu" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-sm font-medium text-slate-800"><?= e($user['name'] ?? '') ?></p>
                        <p class="text-xs text-slate-500"><?= e($user['email'] ?? '') ?></p>
                    </div>
                    <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed top-14 left-0 bottom-0 w-60 bg-slate-900 z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-200 overflow-y-auto">
    <nav class="p-3 space-y-0.5">
        <?php
        $navItems = [
            ['href'=>'index.php','icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6','label'=>'Dashboard','page'=>'index'],
            ['href'=>'vehicles.php','icon'=>'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0','label'=>'Vehicles','page'=>'vehicles'],
            ['href'=>'drivers.php','icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z','label'=>'Drivers','page'=>'drivers'],
            ['href'=>'trips.php','icon'=>'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7','label'=>'Trips','page'=>'trips'],
            ['href'=>'maintenance.php','icon'=>'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z','label'=>'Maintenance','page'=>'maintenance'],
            ['href'=>'fuel_logs.php','icon'=>'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3','label'=>'Fuel Logs','page'=>'fuel_logs'],
            ['href'=>'vehicle_costs.php','icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z','label'=>'Vehicle Costs','page'=>'vehicle_costs'],
            ['href'=>'reports.php','icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z','label'=>'Reports','page'=>'reports'],
        ];
        foreach ($navItems as $item):
            $active = $currentPage === $item['page'] ? 'active' : '';
        ?>
        <a href="<?= $item['href'] ?>" class="sidebar-link <?= $active ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $item['icon'] ?>"/>
            </svg>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
</aside>

<!-- Flash Messages -->
<?php if ($flash): ?>
<div id="flashMsg" class="fixed top-16 right-4 z-50 max-w-sm w-full">
    <div class="flex items-start gap-3 p-4 rounded-xl shadow-lg border <?= $flash['type']==='success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800' ?>">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <?php if($flash['type']==='success'): ?>
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            <?php else: ?>
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            <?php endif; ?>
        </svg>
        <p class="text-sm font-medium"><?= e($flash['message']) ?></p>
        <button onclick="document.getElementById('flashMsg').remove()" class="ml-auto text-current opacity-50 hover:opacity-100">✕</button>
    </div>
</div>
<script>setTimeout(()=>{ const el=document.getElementById('flashMsg'); if(el) el.remove(); }, 5000);</script>
<?php endif; ?>

<!-- Main Content Area -->
<main class="lg:ml-60 mt-14 min-h-screen">
    <div class="p-4 md:p-6">

<script>
function toggleDropdown() {
    document.getElementById('profileMenu').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    if (!document.getElementById('profileDropdown').contains(e.target)) {
        document.getElementById('profileMenu').classList.add('hidden');
    }
});
function closeSidebar() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.add('hidden');
}
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
});
</script>

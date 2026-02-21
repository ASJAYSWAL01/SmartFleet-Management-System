<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Dashboard';
$db = getDB();

// KPI Counts
$totalVehicles   = $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$availVehicles   = $db->query("SELECT COUNT(*) FROM vehicles WHERE status='Available'")->fetchColumn();
$onTripVehicles  = $db->query("SELECT COUNT(*) FROM vehicles WHERE status='On Trip'")->fetchColumn();
$inShopVehicles  = $db->query("SELECT COUNT(*) FROM vehicles WHERE status='In Shop'")->fetchColumn();

$totalDrivers    = $db->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
$onDutyDrivers   = $db->query("SELECT COUNT(*) FROM drivers WHERE status='On Duty'")->fetchColumn();

$activeTrips     = $db->query("SELECT COUNT(*) FROM trips WHERE status='Dispatched'")->fetchColumn();
$completedTrips  = $db->query("SELECT COUNT(*) FROM trips WHERE status='Completed'")->fetchColumn();

$monthRevenue    = $db->query("SELECT COALESCE(SUM(revenue),0) FROM trips WHERE status='Completed' AND MONTH(actual_arrival)=MONTH(NOW()) AND YEAR(actual_arrival)=YEAR(NOW())")->fetchColumn();
$monthCosts      = $db->query("SELECT COALESCE(SUM(amount),0) FROM vehicle_costs WHERE MONTH(cost_date)=MONTH(NOW()) AND YEAR(cost_date)=YEAR(NOW())")->fetchColumn();

$pendingMaint    = $db->query("SELECT COUNT(*) FROM maintenance WHERE status != 'Completed'")->fetchColumn();
$expiredLicenses = $db->query("SELECT COUNT(*) FROM drivers WHERE license_expiry < CURDATE() AND status != 'Suspended'")->fetchColumn();

// Recent Trips
$recentTrips = $db->query("
    SELECT t.*, v.license_plate, d.name as driver_name
    FROM trips t
    JOIN vehicles v ON v.id=t.vehicle_id
    JOIN drivers d ON d.id=t.driver_id
    ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();

// Vehicle Status Distribution
$vStatusRows = $db->query("SELECT status, COUNT(*) as cnt FROM vehicles GROUP BY status")->fetchAll();
$vStatusMap  = array_column($vStatusRows, 'cnt', 'status');

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-slate-500 text-sm mt-0.5"><?= date('l, F j, Y') ?></p>
    </div>
    <?php if(hasRole(['Manager','Dispatcher'])): ?>
    <a href="trip_create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Trip
    </a>
    <?php endif; ?>
</div>

<!-- Alerts -->
<?php if ($expiredLicenses > 0): ?>
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3">
    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
    <p class="text-sm text-red-700"><strong><?= $expiredLicenses ?> driver(s)</strong> have expired licenses and cannot be assigned to trips. <a href="drivers.php" class="underline font-medium">Review now →</a></p>
</div>
<?php endif; ?>

<?php if ($pendingMaint > 0): ?>
<div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
    <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
    <p class="text-sm text-amber-700"><strong><?= $pendingMaint ?></strong> pending/in-progress maintenance records. <a href="maintenance.php" class="underline font-medium">View →</a></p>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $kpis = [
        ['label'=>'Total Vehicles','value'=>$totalVehicles,'sub'=>"$availVehicles available",'icon'=>'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0','color'=>'blue'],
        ['label'=>'Active Trips','value'=>$activeTrips,'sub'=>"$completedTrips completed",'icon'=>'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7','color'=>'indigo'],
        ['label'=>'On Duty Drivers','value'=>$onDutyDrivers,'sub'=>"of $totalDrivers total",'icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z','color'=>'emerald'],
        ['label'=>'Month Revenue','value'=>formatCurrency((float)$monthRevenue),'sub'=>'Cost: '.formatCurrency((float)$monthCosts),'icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z','color'=>'violet'],
    ];
    foreach ($kpis as $kpi):
        $colors = [
            'blue'=>['bg'=>'bg-blue-50','icon'=>'text-blue-600','border'=>'border-blue-100'],
            'indigo'=>['bg'=>'bg-indigo-50','icon'=>'text-indigo-600','border'=>'border-indigo-100'],
            'emerald'=>['bg'=>'bg-emerald-50','icon'=>'text-emerald-600','border'=>'border-emerald-100'],
            'violet'=>['bg'=>'bg-violet-50','icon'=>'text-violet-600','border'=>'border-violet-100'],
        ];
        $c = $colors[$kpi['color']];
    ?>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= $kpi['label'] ?></p>
                <p class="text-2xl font-bold text-slate-800 mt-1"><?= $kpi['value'] ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= $kpi['sub'] ?></p>
            </div>
            <div class="w-10 h-10 <?= $c['bg'] ?> rounded-lg flex items-center justify-center border <?= $c['border'] ?>">
                <svg class="w-5 h-5 <?= $c['icon'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $kpi['icon'] ?>"/>
                </svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Fleet Status + Recent Trips -->
<div class="grid lg:grid-cols-3 gap-6">

    <!-- Fleet Status Breakdown -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-semibold text-slate-700 mb-4">Fleet Status</h2>
        <div class="space-y-3">
            <?php
            $statuses = [
                'Available' => ['bg'=>'bg-emerald-500','text'=>'text-emerald-700','light'=>'bg-emerald-50'],
                'On Trip'   => ['bg'=>'bg-blue-500','text'=>'text-blue-700','light'=>'bg-blue-50'],
                'In Shop'   => ['bg'=>'bg-amber-500','text'=>'text-amber-700','light'=>'bg-amber-50'],
                'Suspended' => ['bg'=>'bg-red-500','text'=>'text-red-700','light'=>'bg-red-50'],
            ];
            foreach ($statuses as $st => $cls):
                $cnt  = $vStatusMap[$st] ?? 0;
                $pct  = $totalVehicles > 0 ? round($cnt / $totalVehicles * 100) : 0;
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-medium text-slate-600"><?= $st ?></span>
                    <span class="<?= $cls['text'] ?> font-semibold"><?= $cnt ?> <span class="text-slate-400 font-normal">(<?= $pct ?>%)</span></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full <?= $cls['bg'] ?> rounded-full transition-all" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <a href="vehicle_create.php" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-medium transition">
                + Add Vehicle
            </a>
            <a href="vehicles.php" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg text-xs font-medium transition">
                View All
            </a>
        </div>
    </div>

    <!-- Recent Trips -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <h2 class="font-semibold text-slate-700">Recent Trips</h2>
            <a href="trips.php" class="text-xs text-blue-600 hover:text-blue-800 font-medium">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase text-slate-400 border-b border-slate-100">
                        <th class="text-left px-5 py-3 font-medium">Trip #</th>
                        <th class="text-left px-4 py-3 font-medium">Route</th>
                        <th class="text-left px-4 py-3 font-medium">Driver</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($recentTrips as $trip): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-5 py-3">
                            <a href="trips.php" class="font-mono text-xs font-medium text-blue-600 hover:text-blue-800"><?= e($trip['trip_number']) ?></a>
                            <div class="text-xs text-slate-400"><?= e($trip['license_plate']) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-700 truncate max-w-[120px]"><?= e($trip['origin']) ?></div>
                            <div class="text-slate-400 text-xs truncate max-w-[120px]">→ <?= e($trip['destination']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e($trip['driver_name']) ?></td>
                        <td class="px-4 py-3"><?= statusBadge($trip['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentTrips)): ?>
                    <tr><td colspan="4" class="text-center py-8 text-slate-400 text-sm">No trips yet. <a href="trip_create.php" class="text-blue-600 hover:underline">Create one →</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

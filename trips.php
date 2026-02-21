<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Trips';
$db = getDB();

// Cancel trip
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_trip']) && hasRole(['Manager','Dispatcher'])) {
    $tid    = (int)$_POST['trip_id'];
    $reason = trim($_POST['cancel_reason'] ?? 'Cancelled by ' . $_SESSION['user']['name']);
    $stmt   = $db->prepare("SELECT * FROM trips WHERE id=:id");
    $stmt->execute(['id'=>$tid]);
    $trip   = $stmt->fetch();

    if ($trip && in_array($trip['status'],['Draft','Dispatched'])) {
        $db->beginTransaction();
        $db->prepare("UPDATE trips SET status='Cancelled', cancelled_reason=:r WHERE id=:id")->execute(['r'=>$reason,'id'=>$tid]);
        // Free vehicle and driver if dispatched
        if ($trip['status']==='Dispatched') {
            $db->prepare("UPDATE vehicles SET status='Available' WHERE id=:id")->execute(['id'=>$trip['vehicle_id']]);
            $db->prepare("UPDATE drivers SET status='Off Duty' WHERE id=:id")->execute(['id'=>$trip['driver_id']]);
        }
        $db->commit();
        setFlash('success','Trip cancelled.');
    } else {
        setFlash('error','Cannot cancel this trip in its current state.');
    }
    redirect('trips.php');
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$where  = [];
$params = [];
if ($search) { $where[] = "(t.trip_number LIKE :s OR t.origin LIKE :s OR t.destination LIKE :s OR d.name LIKE :s OR v.license_plate LIKE :s)"; $params['s']="%$search%"; }
if ($status)  { $where[] = "t.status=:st"; $params['st']=$status; }
$whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$trips = $db->prepare("
    SELECT t.*, v.license_plate, v.make, v.model, d.name as driver_name
    FROM trips t
    JOIN vehicles v ON v.id=t.vehicle_id
    JOIN drivers d ON d.id=t.driver_id
    $whereSql
    ORDER BY t.created_at DESC
");
$trips->execute($params);
$trips = $trips->fetchAll();

include 'includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Trips</h1>
        <p class="text-slate-500 text-sm"><?= count($trips) ?> trip(s)</p>
    </div>
    <?php if(hasRole(['Manager','Dispatcher'])): ?>
    <a href="trip_create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create Trip
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search trip, route, driver, plate..."
               class="flex-1 min-w-[200px] px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Statuses</option>
            <?php foreach(['Draft','Dispatched','Completed','Cancelled'] as $s): ?>
            <option value="<?=$s?>" <?= $status===$s?'selected':'' ?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">Filter</button>
        <a href="trips.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-200 transition">Reset</a>
    </form>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-3">Cancel Trip</h3>
        <form method="POST">
            <input type="hidden" name="trip_id" id="cancelTripId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Reason for cancellation</label>
                <textarea name="cancel_reason" rows="3" placeholder="Optional reason..."
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" name="cancel_trip" class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition">Confirm Cancel</button>
                <button type="button" onclick="closeCancelModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Close</button>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs uppercase text-slate-500">
                    <th class="text-left px-5 py-3 font-medium">Trip #</th>
                    <th class="text-left px-4 py-3 font-medium">Route</th>
                    <th class="text-left px-4 py-3 font-medium">Vehicle</th>
                    <th class="text-left px-4 py-3 font-medium">Driver</th>
                    <th class="text-left px-4 py-3 font-medium">Distance</th>
                    <th class="text-left px-4 py-3 font-medium">Revenue</th>
                    <th class="text-left px-4 py-3 font-medium">Status</th>
                    <th class="text-left px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($trips as $t): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs font-semibold text-blue-600"><?= e($t['trip_number']) ?></span>
                        <div class="text-xs text-slate-400 mt-0.5"><?= $t['scheduled_departure'] ? date('d M, H:i', strtotime($t['scheduled_departure'])) : 'No schedule' ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-700 truncate max-w-[120px]"><?= e($t['origin']) ?></div>
                        <div class="text-slate-400 text-xs truncate max-w-[120px]">→ <?= e($t['destination']) ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-mono text-xs font-medium text-slate-700"><?= e($t['license_plate']) ?></div>
                        <div class="text-xs text-slate-400"><?= e($t['make'].' '.$t['model']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= e($t['driver_name']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= $t['distance_km'] > 0 ? number_format($t['distance_km'],1).' km' : '—' ?></td>
                    <td class="px-4 py-3 font-medium text-slate-700"><?= formatCurrency((float)$t['revenue']) ?></td>
                    <td class="px-4 py-3"><?= statusBadge($t['status']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <?php if($t['status']==='Draft' && hasRole(['Manager','Dispatcher'])): ?>
                            <a href="trip_create.php?dispatch=<?= $t['id'] ?>" class="text-xs px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg font-medium transition">Dispatch</a>
                            <?php endif; ?>
                            <?php if($t['status']==='Dispatched' && hasRole(['Manager','Dispatcher'])): ?>
                            <a href="trip_complete.php?id=<?= $t['id'] ?>" class="text-xs px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-medium transition">Complete</a>
                            <?php endif; ?>
                            <?php if(in_array($t['status'],['Draft','Dispatched']) && hasRole(['Manager','Dispatcher'])): ?>
                            <button onclick="openCancelModal(<?= $t['id'] ?>)" class="text-xs px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition">Cancel</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($trips)): ?>
                <tr><td colspan="8" class="text-center py-10 text-slate-400">No trips found. <a href="trip_create.php" class="text-blue-600 hover:underline">Create one →</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openCancelModal(id) {
    document.getElementById('cancelTripId').value = id;
    document.getElementById('cancelModal').classList.remove('hidden');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>

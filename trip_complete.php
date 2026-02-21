<?php
require_once 'includes/auth_check.php';
requireRole(['Manager','Dispatcher']);
$pageTitle = 'Complete Trip';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT t.*, v.license_plate, v.make, v.model, d.name as driver_name FROM trips t JOIN vehicles v ON v.id=t.vehicle_id JOIN drivers d ON d.id=t.driver_id WHERE t.id=:id");
$stmt->execute(['id'=>$id]);
$trip = $stmt->fetch();

if (!$trip) { setFlash('error','Trip not found.'); redirect('trips.php'); }
if ($trip['status'] !== 'Dispatched') { setFlash('error','Only dispatched trips can be completed.'); redirect('trips.php'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $distance  = (float)($_POST['distance_km'] ?? $trip['distance_km']);
    $revenue   = (float)($_POST['revenue'] ?? $trip['revenue']);
    $notes     = trim($_POST['notes'] ?? '');
    $odometer  = (float)($_POST['final_odometer'] ?? 0);

    if ($distance < 0) $errors[] = 'Distance cannot be negative.';
    if ($revenue < 0)  $errors[] = 'Revenue cannot be negative.';

    if (empty($errors)) {
        $db->beginTransaction();

        // Update trip
        $db->prepare("UPDATE trips SET status='Completed', actual_arrival=NOW(), distance_km=:dk, revenue=:rv, notes=CONCAT(COALESCE(notes,''),:nt) WHERE id=:id")
           ->execute(['dk'=>$distance, 'rv'=>$revenue, 'nt'=>$notes ? "\n[Completion note] $notes" : '', 'id'=>$id]);

        // Free vehicle + driver
        $db->prepare("UPDATE vehicles SET status='Available'" . ($odometer > 0 ? ", odometer=:od" : "") . " WHERE id=:id")
           ->execute(array_filter(['od'=>$odometer ?: null, 'id'=>$trip['vehicle_id']]));
        $db->prepare("UPDATE drivers SET status='Off Duty' WHERE id=:id")->execute(['id'=>$trip['driver_id']]);

        // Auto-log cost per km if distance > 0
        $db->commit();
        setFlash('success', 'Trip '.$trip['trip_number'].' completed! Vehicle and driver are now available.');
        redirect('trips.php');
    }
}

include 'includes/header.php';
?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="trips.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div><h1 class="text-2xl font-bold text-slate-800">Complete Trip</h1></div>
    </div>

    <!-- Trip Summary -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 mb-5">
        <p class="font-mono font-semibold text-indigo-700 mb-1"><?= e($trip['trip_number']) ?></p>
        <p class="text-slate-700"><?= e($trip['origin']) ?> → <?= e($trip['destination']) ?></p>
        <p class="text-sm text-slate-500 mt-1">Vehicle: <?= e($trip['license_plate'].' — '.$trip['make'].' '.$trip['model']) ?></p>
        <p class="text-sm text-slate-500">Driver: <?= e($trip['driver_name']) ?></p>
    </div>

    <?php if(!empty($errors)): ?>
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
        <?php foreach($errors as $err): ?><p class="text-sm text-red-700">• <?= e($err) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" class="space-y-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Actual Distance (km)</label>
                    <input type="number" name="distance_km" value="<?= e($trip['distance_km']) ?>" min="0" step="0.1"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Final Revenue (₹)</label>
                    <input type="number" name="revenue" value="<?= e($trip['revenue']) ?>" min="0" step="0.01"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Final Odometer Reading (km)</label>
                    <input type="number" name="final_odometer" min="0" step="0.1" placeholder="Optional"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Completion Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Any delivery notes, issues, etc."></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition shadow">
                    Mark as Completed
                </button>
                <a href="trips.php" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

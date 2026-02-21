<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Vehicles';
$db = getDB();

// Toggle Out of Service
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_service']) && hasRole(['Manager','Safety Officer'])) {
    $vid    = (int)$_POST['vehicle_id'];
    $stmt   = $db->prepare("SELECT status FROM vehicles WHERE id=:id");
    $stmt->execute(['id'=>$vid]);
    $v = $stmt->fetch();
    if ($v) {
        $newStatus = $v['status']==='Suspended' ? 'Available' : 'Suspended';
        $db->prepare("UPDATE vehicles SET status=:s WHERE id=:id")->execute(['s'=>$newStatus,'id'=>$vid]);
        setFlash('success', 'Vehicle status updated to '.$newStatus.'.');
    }
    redirect('vehicles.php');
}

// Delete vehicle
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_vehicle']) && hasRole('Manager')) {
    $vid = (int)$_POST['vehicle_id'];
    try {
        $db->prepare("DELETE FROM vehicles WHERE id=:id")->execute(['id'=>$vid]);
        setFlash('success', 'Vehicle deleted.');
    } catch (PDOException $e) {
        setFlash('error', 'Cannot delete vehicle — it has associated records.');
    }
    redirect('vehicles.php');
}

// Filters
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$where   = [];
$params  = [];
if ($search) { $where[] = "(v.license_plate LIKE :s OR v.make LIKE :s OR v.model LIKE :s)"; $params['s']="%$search%"; }
if ($status)  { $where[] = "v.status=:st"; $params['st']=$status; }
$whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$vehicles = $db->prepare("
    SELECT v.*,
        (SELECT COUNT(*) FROM trips WHERE vehicle_id=v.id AND status='Completed') as total_trips,
        (SELECT COALESCE(SUM(amount),0) FROM vehicle_costs WHERE vehicle_id=v.id) as total_costs
    FROM vehicles v $whereSql ORDER BY v.created_at DESC
");
$vehicles->execute($params);
$vehicles = $vehicles->fetchAll();

include 'includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Vehicles</h1>
        <p class="text-slate-500 text-sm"><?= count($vehicles) ?> vehicle(s) found</p>
    </div>
    <?php if(hasRole(['Manager','Dispatcher'])): ?>
    <a href="vehicle_create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Vehicle
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search plate, make, model..."
               class="flex-1 min-w-[200px] px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Statuses</option>
            <?php foreach (['Available','On Trip','In Shop','Suspended'] as $s): ?>
            <option value="<?=$s?>" <?= $status===$s?'selected':'' ?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">Filter</button>
        <a href="vehicles.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-200 transition">Reset</a>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs uppercase text-slate-500">
                    <th class="text-left px-5 py-3 font-medium">Plate / Info</th>
                    <th class="text-left px-4 py-3 font-medium">Type</th>
                    <th class="text-left px-4 py-3 font-medium">Capacity</th>
                    <th class="text-left px-4 py-3 font-medium">Odometer</th>
                    <th class="text-left px-4 py-3 font-medium">Trips</th>
                    <th class="text-left px-4 py-3 font-medium">Total Costs</th>
                    <th class="text-left px-4 py-3 font-medium">Status</th>
                    <th class="text-left px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($vehicles as $v): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-5 py-3">
                        <div class="font-semibold text-slate-800 font-mono"><?= e($v['license_plate']) ?></div>
                        <div class="text-xs text-slate-500"><?= e($v['make'].' '.$v['model'].' ('.$v['year'].')') ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= e($v['type']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= number_format($v['max_capacity']) ?> kg</td>
                    <td class="px-4 py-3 text-slate-600"><?= number_format($v['odometer'],1) ?> km</td>
                    <td class="px-4 py-3 font-medium text-slate-700"><?= $v['total_trips'] ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= formatCurrency((float)$v['total_costs']) ?></td>
                    <td class="px-4 py-3"><?= statusBadge($v['status']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="vehicle_edit.php?id=<?= $v['id'] ?>" class="text-xs px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">Edit</a>
                            <?php if(hasRole(['Manager','Safety Officer']) && !in_array($v['status'],['On Trip'])): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                                <button type="submit" name="toggle_service"
                                    class="text-xs px-2.5 py-1.5 rounded-lg font-medium transition <?= $v['status']==='Suspended' ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' : 'bg-amber-50 hover:bg-amber-100 text-amber-700' ?>">
                                    <?= $v['status']==='Suspended' ? 'Reinstate' : 'Suspend' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if(hasRole('Manager')): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this vehicle? This cannot be undone.')">
                                <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                                <button type="submit" name="delete_vehicle" class="text-xs px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vehicles)): ?>
                <tr><td colspan="8" class="text-center py-10 text-slate-400">No vehicles found. <a href="vehicle_create.php" class="text-blue-600 hover:underline">Add one →</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

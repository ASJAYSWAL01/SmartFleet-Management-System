<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Maintenance';
$db = getDB();

// Complete maintenance
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['complete_maintenance']) && hasRole(['Manager','Safety Officer'])) {
    $mid  = (int)$_POST['maint_id'];
    $cost = (float)($_POST['final_cost'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM maintenance WHERE id=:id");
    $stmt->execute(['id'=>$mid]);
    $maint = $stmt->fetch();

    if ($maint && $maint['status']!=='Completed') {
        $db->beginTransaction();
        $db->prepare("UPDATE maintenance SET status='Completed', completed_date=CURDATE(), cost=:cost WHERE id=:id")->execute(['cost'=>$cost,'id'=>$mid]);
        // Only mark vehicle available if no other pending maintenance
        $check = $db->prepare("SELECT COUNT(*) FROM maintenance WHERE vehicle_id=:vid AND status!='Completed' AND id!=:id");
        $check->execute(['vid'=>$maint['vehicle_id'],'id'=>$mid]);
        if ($check->fetchColumn() == 0) {
            $db->prepare("UPDATE vehicles SET status='Available' WHERE id=:id AND status='In Shop'")->execute(['id'=>$maint['vehicle_id']]);
        }
        // Log cost
        if ($cost > 0) {
            $db->prepare("INSERT INTO vehicle_costs (vehicle_id,cost_type,amount,cost_date,description,created_by) VALUES (:vid,'Maintenance',:amt,CURDATE(),:desc,:uid)")
               ->execute(['vid'=>$maint['vehicle_id'],'amt'=>$cost,'desc'=>'Maintenance #'.$mid,'uid'=>$_SESSION['user']['id']]);
        }
        $db->commit();
        setFlash('success','Maintenance marked as completed.');
    }
    redirect('maintenance.php');
}

$status = $_GET['status'] ?? '';
$where  = $status ? "WHERE m.status=:st" : '';
$params = $status ? ['st'=>$status] : [];

$records = $db->prepare("
    SELECT m.*, v.license_plate, v.make, v.model
    FROM maintenance m
    JOIN vehicles v ON v.id=m.vehicle_id
    $where ORDER BY m.scheduled_date DESC
");
$records->execute($params);
$records = $records->fetchAll();

include 'includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Maintenance</h1>
        <p class="text-slate-500 text-sm"><?= count($records) ?> record(s)</p>
    </div>
    <?php if(hasRole(['Manager','Safety Officer'])): ?>
    <a href="maintenance_create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Schedule Maintenance
    </a>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" class="flex gap-3 flex-wrap">
        <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Statuses</option>
            <?php foreach(['Scheduled','In Progress','Completed'] as $s): ?>
            <option value="<?=$s?>" <?= $status===$s?'selected':'' ?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">Filter</button>
        <a href="maintenance.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-200 transition">Reset</a>
    </form>
</div>

<!-- Complete Modal -->
<div id="completeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Complete Maintenance</h3>
        <form method="POST">
            <input type="hidden" name="maint_id" id="maintId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Final Cost (₹)</label>
                <input type="number" name="final_cost" min="0" step="0.01" placeholder="0.00"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-3">
                <button type="submit" name="complete_maintenance" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold transition">Complete</button>
                <button type="button" onclick="document.getElementById('completeModal').classList.add('hidden')" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Close</button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs uppercase text-slate-500">
                    <th class="text-left px-5 py-3 font-medium">Vehicle</th>
                    <th class="text-left px-4 py-3 font-medium">Type</th>
                    <th class="text-left px-4 py-3 font-medium">Description</th>
                    <th class="text-left px-4 py-3 font-medium">Scheduled</th>
                    <th class="text-left px-4 py-3 font-medium">Cost</th>
                    <th class="text-left px-4 py-3 font-medium">Status</th>
                    <th class="text-left px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($records as $r): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-5 py-3">
                        <div class="font-mono text-xs font-semibold text-slate-800"><?= e($r['license_plate']) ?></div>
                        <div class="text-xs text-slate-400"><?= e($r['make'].' '.$r['model']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= e($r['maintenance_type']) ?></td>
                    <td class="px-4 py-3 text-slate-600 max-w-[180px] truncate"><?= e($r['description'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= date('d M Y', strtotime($r['scheduled_date'])) ?></td>
                    <td class="px-4 py-3 font-medium text-slate-700"><?= formatCurrency((float)$r['cost']) ?></td>
                    <td class="px-4 py-3"><?= statusBadge($r['status']) ?></td>
                    <td class="px-4 py-3">
                        <?php if($r['status']!=='Completed' && hasRole(['Manager','Safety Officer'])): ?>
                        <button onclick="document.getElementById('maintId').value=<?= $r['id'] ?>; document.getElementById('completeModal').classList.remove('hidden')"
                                class="text-xs px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-medium transition">
                            Complete
                        </button>
                        <?php else: ?>
                        <span class="text-xs text-slate-400"><?= $r['completed_date'] ? date('d M', strtotime($r['completed_date'])) : '—' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($records)): ?>
                <tr><td colspan="7" class="text-center py-10 text-slate-400">No maintenance records. <a href="maintenance_create.php" class="text-blue-600 hover:underline">Schedule one →</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

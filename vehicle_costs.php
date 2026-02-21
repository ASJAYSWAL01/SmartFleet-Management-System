<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Vehicle Costs';
$db = getDB();

$errors = [];
$vehicles = $db->query("SELECT * FROM vehicles ORDER BY license_plate")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $vId      = (int)($_POST['vehicle_id'] ?? 0);
    $costType = $_POST['cost_type'] ?? '';
    $amount   = (float)($_POST['amount'] ?? 0);
    $costDate = $_POST['cost_date'] ?? date('Y-m-d');
    $desc     = trim($_POST['description'] ?? '');
    $tripId   = (int)($_POST['trip_id'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    if (!$vId) $errors[] = 'Select a vehicle.';
    if (!in_array($costType,['Fuel','Maintenance','Insurance','Registration','Toll','Driver Pay','Misc'])) $errors[] = 'Invalid cost type.';
    if ($amount <= 0) $errors[] = 'Amount must be > 0.';

    if (empty($errors)) {
        $db->prepare("INSERT INTO vehicle_costs (vehicle_id,trip_id,cost_type,amount,cost_date,description,notes,created_by) VALUES (:vi,:ti,:ct,:am,:cd,:desc,:nt,:uid)")
           ->execute(['vi'=>$vId,'ti'=>$tripId?:null,'ct'=>$costType,'am'=>$amount,'cd'=>$costDate,'desc'=>$desc?:null,'nt'=>$notes?:null,'uid'=>$_SESSION['user']['id']]);
        setFlash('success','Cost entry added.');
        redirect('vehicle_costs.php');
    }
}

$filter_vid = (int)($_GET['vehicle_id'] ?? 0);
$where  = $filter_vid ? "WHERE c.vehicle_id=:vid" : '';
$params = $filter_vid ? ['vid'=>$filter_vid] : [];
$costs  = $db->prepare("SELECT c.*, v.license_plate FROM vehicle_costs c JOIN vehicles v ON v.id=c.vehicle_id $where ORDER BY c.cost_date DESC, c.created_at DESC LIMIT 100");
$costs->execute($params);
$costs = $costs->fetchAll();

// Per-vehicle summary
$summary = $db->query("
    SELECT v.id, v.license_plate, v.make, v.model,
        COALESCE(SUM(c.amount),0) as total_cost,
        COALESCE((SELECT SUM(revenue) FROM trips WHERE vehicle_id=v.id AND status='Completed'),0) as total_revenue,
        COALESCE((SELECT SUM(distance_km) FROM trips WHERE vehicle_id=v.id AND status='Completed'),0) as total_km
    FROM vehicles v
    LEFT JOIN vehicle_costs c ON c.vehicle_id=v.id
    GROUP BY v.id ORDER BY total_cost DESC
")->fetchAll();

$activeTrips = $db->query("SELECT id, trip_number FROM trips WHERE status='Dispatched' ORDER BY trip_number")->fetchAll();

include 'includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Vehicle Costs</h1>
    <p class="text-slate-500 text-sm">Track all operational expenses per vehicle</p>
</div>

<!-- Vehicle Cost Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <?php foreach(array_slice($summary,0,6) as $s):
        $roi = $s['total_cost'] > 0 ? round((($s['total_revenue'] - $s['total_cost']) / $s['total_cost'])*100,1) : 0;
        $cpm = $s['total_km'] > 0 ? round($s['total_cost']/$s['total_km'],2) : 0;
    ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
            <span class="font-mono text-sm font-bold text-slate-800"><?= e($s['license_plate']) ?></span>
            <span class="text-xs text-slate-400"><?= e($s['make'].' '.$s['model']) ?></span>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div>
                <p class="text-xs text-slate-400">Cost</p>
                <p class="font-semibold text-slate-800 text-sm"><?= formatCurrency((float)$s['total_cost']) ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Revenue</p>
                <p class="font-semibold text-slate-800 text-sm"><?= formatCurrency((float)$s['total_revenue']) ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-400">ROI</p>
                <p class="font-semibold text-sm <?= $roi >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= $roi ?>%</p>
            </div>
        </div>
        <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between text-xs text-slate-400">
            <span>Cost/km: <strong class="text-slate-600">₹<?= $cpm ?></strong></span>
            <span>Dist: <strong class="text-slate-600"><?= number_format($s['total_km'],0) ?> km</strong></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Add Cost Form -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-semibold text-slate-700 mb-4">Add Expense</h2>
        <?php if(!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl">
            <?php foreach($errors as $err): ?><p class="text-xs text-red-700">• <?= e($err) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Vehicle <span class="text-red-500">*</span></label>
                <select name="vehicle_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">— Select —</option>
                    <?php foreach($vehicles as $v): ?>
                    <option value="<?=$v['id']?>"><?= e($v['license_plate'].' — '.$v['make']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Cost Type <span class="text-red-500">*</span></label>
                <select name="cost_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach(['Fuel','Maintenance','Insurance','Registration','Toll','Driver Pay','Misc'] as $ct): ?>
                    <option><?=$ct?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Amount (₹) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" min="0.01" step="0.01" required
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date <span class="text-red-500">*</span></label>
                <input type="date" name="cost_date" value="<?= date('Y-m-d') ?>"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Link to Trip</label>
                <select name="trip_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">None</option>
                    <?php foreach($activeTrips as $t): ?>
                    <option value="<?=$t['id']?>"><?= e($t['trip_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <input type="text" name="description"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Brief description">
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">Add Expense</button>
        </form>
    </div>

    <!-- Costs Table -->
    <div class="lg:col-span-2">
        <div class="flex items-center gap-3 mb-3">
            <form method="GET" class="flex gap-2">
                <select name="vehicle_id" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Vehicles</option>
                    <?php foreach($vehicles as $v): ?>
                    <option value="<?=$v['id']?>" <?= $filter_vid==$v['id']?'selected':'' ?>><?= e($v['license_plate']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200 transition">Filter</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-xs uppercase text-slate-500">
                            <th class="text-left px-5 py-3 font-medium">Vehicle</th>
                            <th class="text-left px-4 py-3 font-medium">Date</th>
                            <th class="text-left px-4 py-3 font-medium">Type</th>
                            <th class="text-left px-4 py-3 font-medium">Description</th>
                            <th class="text-right px-4 py-3 font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($costs as $c): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-800"><?= e($c['license_plate']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= date('d M Y', strtotime($c['cost_date'])) ?></td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium"><?= e($c['cost_type']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 max-w-[150px] truncate"><?= e($c['description'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-800"><?= formatCurrency((float)$c['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($costs)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-slate-400 text-sm">No cost records yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

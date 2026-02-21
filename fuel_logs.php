<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Fuel Logs';
$db = getDB();

$vehicles = $db->query("SELECT * FROM vehicles ORDER BY license_plate")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $vId      = (int)($_POST['vehicle_id'] ?? 0);
    $fuelDate = $_POST['fuel_date'] ?? date('Y-m-d');
    $liters   = (float)($_POST['liters'] ?? 0);
    $ppl      = (float)($_POST['price_per_liter'] ?? 0);
    $odometer = (float)($_POST['odometer_reading'] ?? 0);
    $fuelType = $_POST['fuel_type'] ?? 'Diesel';
    $station  = trim($_POST['station_name'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');
    $tripId   = (int)($_POST['trip_id'] ?? 0);

    if (!$vId) $errors[] = 'Select a vehicle.';
    if ($liters <= 0) $errors[] = 'Liters must be > 0.';
    if ($ppl <= 0)    $errors[] = 'Price per liter must be > 0.';

    if (empty($errors)) {
        $db->prepare("INSERT INTO fuel_logs (vehicle_id,trip_id,fuel_date,liters,price_per_liter,odometer_reading,fuel_type,station_name,notes,created_by)
                      VALUES (:vi,:ti,:fd,:lt,:ppl,:od,:ft,:st,:nt,:uid)")
           ->execute([
               'vi'=>$vId, 'ti'=>$tripId?:null, 'fd'=>$fuelDate, 'lt'=>$liters,
               'ppl'=>$ppl, 'od'=>$odometer?:null, 'ft'=>$fuelType, 'st'=>$station?:null,
               'nt'=>$notes?:null, 'uid'=>$_SESSION['user']['id'],
           ]);
        // Also add to vehicle_costs
        $total = round($liters * $ppl, 2);
        $db->prepare("INSERT INTO vehicle_costs (vehicle_id,trip_id,cost_type,amount,cost_date,description,created_by) VALUES (:vi,:ti,'Fuel',:amt,:fd,'Fuel log',:uid)")
           ->execute(['vi'=>$vId,'ti'=>$tripId?:null,'amt'=>$total,'fd'=>$fuelDate,'uid'=>$_SESSION['user']['id']]);
        setFlash('success','Fuel log added successfully.');
        redirect('fuel_logs.php');
    }
}

$filter_vid = (int)($_GET['vehicle_id'] ?? 0);
$where  = $filter_vid ? "WHERE f.vehicle_id=:vid" : '';
$params = $filter_vid ? ['vid'=>$filter_vid] : [];
$logs   = $db->prepare("SELECT f.*, v.license_plate, v.make, v.model FROM fuel_logs f JOIN vehicles v ON v.id=f.vehicle_id $where ORDER BY f.fuel_date DESC, f.created_at DESC LIMIT 100");
$logs->execute($params);
$logs = $logs->fetchAll();

// Monthly totals
$monthlyTotal = $db->query("SELECT COALESCE(SUM(total_cost),0) FROM fuel_logs WHERE MONTH(fuel_date)=MONTH(NOW()) AND YEAR(fuel_date)=YEAR(NOW())")->fetchColumn();
$monthlyLiters = $db->query("SELECT COALESCE(SUM(liters),0) FROM fuel_logs WHERE MONTH(fuel_date)=MONTH(NOW()) AND YEAR(fuel_date)=YEAR(NOW())")->fetchColumn();

// Active trips for linking
$activeTrips = $db->query("SELECT id, trip_number, origin, destination FROM trips WHERE status='Dispatched' ORDER BY trip_number")->fetchAll();

include 'includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Fuel Logs</h1>
    <p class="text-slate-500 text-sm">This month: <?= number_format($monthlyLiters,1) ?> L — <?= formatCurrency((float)$monthlyTotal) ?></p>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Add Fuel Log -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-semibold text-slate-700 mb-4">Add Fuel Entry</h2>

        <?php if(!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl">
            <?php foreach($errors as $err): ?><p class="text-xs text-red-700">• <?= e($err) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" id="fuelForm">
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Link to Trip (optional)</label>
                <select name="trip_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">None</option>
                    <?php foreach($activeTrips as $t): ?>
                    <option value="<?=$t['id']?>"><?= e($t['trip_number'].' — '.$t['origin'].' → '.$t['destination']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date <span class="text-red-500">*</span></label>
                <input type="date" name="fuel_date" value="<?= date('Y-m-d') ?>"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Liters <span class="text-red-500">*</span></label>
                    <input type="number" name="liters" min="0.01" step="0.01" id="liters"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Price/L (₹) <span class="text-red-500">*</span></label>
                    <input type="number" name="price_per_liter" min="0.01" step="0.0001" id="ppl"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg text-xs text-slate-500">
                Total: <strong class="text-slate-800 text-sm" id="totalCost">₹0.00</strong>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Fuel Type</label>
                <select name="fuel_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach(['Diesel','Petrol','CNG','Electric','Other'] as $ft): ?>
                    <option><?=$ft?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Odometer Reading</label>
                <input type="number" name="odometer_reading" min="0" step="0.1"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Station Name</label>
                <input type="text" name="station_name"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">Add Fuel Log</button>
        </form>
    </div>

    <!-- Logs Table -->
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
                            <th class="text-left px-4 py-3 font-medium">Liters</th>
                            <th class="text-left px-4 py-3 font-medium">Price/L</th>
                            <th class="text-left px-4 py-3 font-medium">Total</th>
                            <th class="text-left px-4 py-3 font-medium">Type</th>
                            <th class="text-left px-4 py-3 font-medium">Odometer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($logs as $l): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-800"><?= e($l['license_plate']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= date('d M Y', strtotime($l['fuel_date'])) ?></td>
                            <td class="px-4 py-3 text-slate-700 font-medium"><?= number_format($l['liters'],2) ?> L</td>
                            <td class="px-4 py-3 text-slate-600">₹<?= number_format($l['price_per_liter'],2) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-800"><?= formatCurrency((float)$l['total_cost']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($l['fuel_type']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $l['odometer_reading'] ? number_format($l['odometer_reading'],1).' km' : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($logs)): ?>
                        <tr><td colspan="7" class="text-center py-8 text-slate-400 text-sm">No fuel logs yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const litersInput = document.getElementById('liters');
const pplInput    = document.getElementById('ppl');
const totalEl     = document.getElementById('totalCost');
function updateTotal() {
    const t = (parseFloat(litersInput.value)||0) * (parseFloat(pplInput.value)||0);
    totalEl.textContent = '₹' + t.toFixed(2);
}
litersInput.addEventListener('input', updateTotal);
pplInput.addEventListener('input', updateTotal);
</script>

<?php include 'includes/footer.php'; ?>

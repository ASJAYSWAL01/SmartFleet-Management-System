<?php
require_once 'includes/auth_check.php';
requireRole(['Manager','Dispatcher']);
$pageTitle = 'Create Trip';
$db = getDB();

// Handle dispatch of existing draft
$dispatchId = (int)($_GET['dispatch'] ?? 0);
if ($dispatchId) {
    $stmt = $db->prepare("SELECT * FROM trips WHERE id=:id AND status='Draft'");
    $stmt->execute(['id'=>$dispatchId]);
    $draftTrip = $stmt->fetch();

    if ($draftTrip && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['dispatch_trip'])) {
        $db->beginTransaction();
        $db->prepare("UPDATE trips SET status='Dispatched', actual_departure=NOW() WHERE id=:id")->execute(['id'=>$dispatchId]);
        $db->prepare("UPDATE vehicles SET status='On Trip' WHERE id=:id")->execute(['id'=>$draftTrip['vehicle_id']]);
        $db->prepare("UPDATE drivers SET status='On Duty' WHERE id=:id")->execute(['id'=>$draftTrip['driver_id']]);
        $db->commit();
        setFlash('success', 'Trip '.$draftTrip['trip_number'].' dispatched!');
        redirect('trips.php');
    }
}

$errors = [];
$data   = ['vehicle_id'=>'','driver_id'=>'','origin'=>'','destination'=>'','cargo_description'=>'','cargo_weight'=>'0','distance_km'=>'0','scheduled_departure'=>'','revenue'=>'0','notes'=>'','action'=>'draft'];

// Load available vehicles and drivers
$availableVehicles = $db->query("SELECT * FROM vehicles WHERE status='Available' ORDER BY license_plate")->fetchAll();
$availableDrivers  = $db->query("SELECT * FROM drivers WHERE status IN ('On Duty','Off Duty') ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['dispatch_trip'])) {
    $data = [
        'vehicle_id'         => (int)($_POST['vehicle_id'] ?? 0),
        'driver_id'          => (int)($_POST['driver_id'] ?? 0),
        'origin'             => trim($_POST['origin'] ?? ''),
        'destination'        => trim($_POST['destination'] ?? ''),
        'cargo_description'  => trim($_POST['cargo_description'] ?? ''),
        'cargo_weight'       => (float)($_POST['cargo_weight'] ?? 0),
        'distance_km'        => (float)($_POST['distance_km'] ?? 0),
        'scheduled_departure'=> $_POST['scheduled_departure'] ?? '',
        'revenue'            => (float)($_POST['revenue'] ?? 0),
        'notes'              => trim($_POST['notes'] ?? ''),
        'action'             => $_POST['action'] ?? 'draft',
    ];

    // Validations
    if (!$data['vehicle_id']) $errors[] = 'Select a vehicle.';
    if (!$data['driver_id'])  $errors[] = 'Select a driver.';
    if (empty($data['origin'])) $errors[] = 'Origin is required.';
    if (empty($data['destination'])) $errors[] = 'Destination is required.';

    if ($data['vehicle_id'] && empty($errors)) {
        $vStmt = $db->prepare("SELECT * FROM vehicles WHERE id=:id");
        $vStmt->execute(['id'=>$data['vehicle_id']]);
        $vehicle = $vStmt->fetch();

        if (!$vehicle) { $errors[] = 'Vehicle not found.'; }
        elseif ($vehicle['status'] !== 'Available') { $errors[] = 'Vehicle is not available (status: '.$vehicle['status'].').'; }
        elseif ($data['cargo_weight'] > $vehicle['max_capacity']) { $errors[] = 'Cargo weight ('.$data['cargo_weight'].' kg) exceeds vehicle capacity ('.$vehicle['max_capacity'].' kg).'; }
    }

    if ($data['driver_id'] && empty($errors)) {
        $dStmt = $db->prepare("SELECT * FROM drivers WHERE id=:id");
        $dStmt->execute(['id'=>$data['driver_id']]);
        $driver = $dStmt->fetch();

        if (!$driver) { $errors[] = 'Driver not found.'; }
        elseif ($driver['status'] !== 'On Duty') { $errors[] = 'Driver is not On Duty (status: '.$driver['status'].').'; }
        elseif (strtotime($driver['license_expiry']) < time()) { $errors[] = 'Driver\'s license is expired ('.$driver['license_expiry'].'). Cannot assign.'; }
    }

    if (empty($errors)) {
        $tripNumber = generateTripNumber();
        $status = ($data['action'] === 'dispatch') ? 'Dispatched' : 'Draft';

        $db->beginTransaction();
        $db->prepare("
            INSERT INTO trips (trip_number,vehicle_id,driver_id,origin,destination,cargo_description,cargo_weight,distance_km,scheduled_departure,revenue,notes,status,actual_departure,created_by)
            VALUES (:tn,:vi,:di,:or,:de,:cd,:cw,:dk,:sd,:rv,:no,:st,:ad,:cb)
        ")->execute([
            'tn'=>$tripNumber, 'vi'=>$data['vehicle_id'], 'di'=>$data['driver_id'],
            'or'=>$data['origin'], 'de'=>$data['destination'], 'cd'=>$data['cargo_description']?:null,
            'cw'=>$data['cargo_weight'], 'dk'=>$data['distance_km'],
            'sd'=>$data['scheduled_departure']?:null, 'rv'=>$data['revenue'], 'no'=>$data['notes']?:null,
            'st'=>$status, 'ad'=>($status==='Dispatched'?date('Y-m-d H:i:s'):null),
            'cb'=>$_SESSION['user']['id'],
        ]);

        if ($status === 'Dispatched') {
            $db->prepare("UPDATE vehicles SET status='On Trip' WHERE id=:id")->execute(['id'=>$data['vehicle_id']]);
            $db->prepare("UPDATE drivers SET status='On Duty' WHERE id=:id")->execute(['id'=>$data['driver_id']]);
        }
        $db->commit();

        setFlash('success', "Trip $tripNumber created" . ($status==='Dispatched'?' and dispatched':'') . '!');
        redirect('trips.php');
    }
}

include 'includes/header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="trips.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div><h1 class="text-2xl font-bold text-slate-800">Create Trip</h1><p class="text-slate-500 text-sm">Schedule or dispatch a new trip</p></div>
    </div>

    <?php if(!empty($availableVehicles) === false): ?>
    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700">
        ⚠️ No vehicles are currently available. <a href="vehicles.php" class="underline font-medium">Check vehicle statuses →</a>
    </div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
        <?php foreach($errors as $err): ?><p class="text-sm text-red-700">• <?= e($err) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Dispatch Existing Draft -->
    <?php if($dispatchId && isset($draftTrip)): ?>
    <div class="bg-white rounded-xl border border-indigo-200 shadow-sm p-6 mb-6">
        <h3 class="font-semibold text-slate-700 mb-2">Dispatch Draft Trip: <span class="font-mono text-blue-600"><?= e($draftTrip['trip_number']) ?></span></h3>
        <p class="text-sm text-slate-600 mb-4"><?= e($draftTrip['origin']) ?> → <?= e($draftTrip['destination']) ?></p>
        <form method="POST">
            <div class="flex gap-3">
                <button type="submit" name="dispatch_trip" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition shadow">
                    Dispatch Now
                </button>
                <a href="trips.php" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" class="space-y-5">
            <!-- Vehicle + Driver Selection -->
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Vehicle <span class="text-red-500">*</span></label>
                    <select name="vehicle_id" id="vehicleSelect" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">— Select Vehicle —</option>
                        <?php foreach($availableVehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" data-capacity="<?= $v['max_capacity'] ?>" <?= $data['vehicle_id']==$v['id']?'selected':'' ?>>
                            <?= e($v['license_plate'].' — '.$v['make'].' '.$v['model'].' (Cap: '.number_format($v['max_capacity']).' kg)') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="capacityHint" class="text-xs text-slate-400 mt-1"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Driver <span class="text-red-500">*</span></label>
                    <select name="driver_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">— Select Driver —</option>
                        <?php foreach($availableDrivers as $d):
                            $expired = strtotime($d['license_expiry']) < time();
                        ?>
                        <option value="<?= $d['id'] ?>" <?= $data['driver_id']==$d['id']?'selected':'' ?> <?= ($d['status']!=='On Duty'||$expired)?'style="color:#94a3b8"':'' ?>>
                            <?= e($d['name'].' ('.$d['status'].')'.($expired?' ⚠ Expired':'')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Only "On Duty" drivers with valid licenses can be dispatched.</p>
                </div>
            </div>

            <!-- Route -->
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Origin <span class="text-red-500">*</span></label>
                    <input type="text" name="origin" value="<?= e($data['origin']) ?>" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. Mumbai Warehouse">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Destination <span class="text-red-500">*</span></label>
                    <input type="text" name="destination" value="<?= e($data['destination']) ?>" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. Pune Distribution Centre">
                </div>
            </div>

            <!-- Cargo -->
            <div class="grid sm:grid-cols-3 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Cargo Description</label>
                    <input type="text" name="cargo_description" value="<?= e($data['cargo_description']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. Electronic goods, 50 cartons">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Cargo Weight (kg)</label>
                    <input type="number" name="cargo_weight" value="<?= e($data['cargo_weight']) ?>" min="0" step="0.01" id="cargoWeight"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Trip Details -->
            <div class="grid sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Distance (km)</label>
                    <input type="number" name="distance_km" value="<?= e($data['distance_km']) ?>" min="0" step="0.1"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Scheduled Departure</label>
                    <input type="datetime-local" name="scheduled_departure" value="<?= e($data['scheduled_departure']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Revenue (₹)</label>
                    <input type="number" name="revenue" value="<?= e($data['revenue']) ?>" min="0" step="0.01"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Any special instructions..."><?= e($data['notes']) ?></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-100">
                <button type="submit" name="action" value="draft" class="px-5 py-2.5 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm font-semibold transition">
                    Save as Draft
                </button>
                <button type="submit" name="action" value="dispatch" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition shadow">
                    Create &amp; Dispatch Now
                </button>
                <a href="trips.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const vehicleSelect = document.getElementById('vehicleSelect');
const cargoWeight   = document.getElementById('cargoWeight');
const hint          = document.getElementById('capacityHint');

function updateCapacityHint() {
    const opt = vehicleSelect.options[vehicleSelect.selectedIndex];
    const cap = parseFloat(opt.dataset.capacity || 0);
    const wt  = parseFloat(cargoWeight.value || 0);
    if (cap > 0) {
        const ok = wt <= cap;
        hint.textContent = `Max capacity: ${cap.toLocaleString()} kg — Cargo: ${wt.toLocaleString()} kg (${ok ? '✓ OK' : '✗ Exceeds limit!'})`;
        hint.className = `text-xs mt-1 ${ok ? 'text-emerald-600' : 'text-red-600 font-medium'}`;
    } else { hint.textContent = ''; }
}
vehicleSelect.addEventListener('change', updateCapacityHint);
cargoWeight.addEventListener('input', updateCapacityHint);
updateCapacityHint();
</script>

<?php include 'includes/footer.php'; ?>

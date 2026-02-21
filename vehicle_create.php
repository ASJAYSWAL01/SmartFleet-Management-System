<?php
require_once 'includes/auth_check.php';
requireRole(['Manager','Dispatcher']);
$pageTitle = 'Add Vehicle';
$db = getDB();

$errors = [];
$data   = ['license_plate'=>'','make'=>'','model'=>'','year'=>date('Y'),'type'=>'Truck','max_capacity'=>'','odometer'=>'0','notes'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $data = [
        'license_plate' => strtoupper(trim($_POST['license_plate'] ?? '')),
        'make'          => trim($_POST['make'] ?? ''),
        'model'         => trim($_POST['model'] ?? ''),
        'year'          => (int)($_POST['year'] ?? date('Y')),
        'type'          => $_POST['type'] ?? 'Truck',
        'max_capacity'  => $_POST['max_capacity'] ?? '',
        'odometer'      => $_POST['odometer'] ?? '0',
        'notes'         => trim($_POST['notes'] ?? ''),
    ];

    if (empty($data['license_plate'])) $errors[] = 'License plate is required.';
    if (strlen($data['license_plate']) > 20) $errors[] = 'License plate too long (max 20 chars).';
    if (empty($data['make'])) $errors[] = 'Make is required.';
    if (empty($data['model'])) $errors[] = 'Model is required.';
    if ($data['year'] < 1990 || $data['year'] > (int)date('Y')+1) $errors[] = 'Invalid year.';
    if (!is_numeric($data['max_capacity']) || $data['max_capacity'] <= 0) $errors[] = 'Max capacity must be a positive number (kg).';
    if (!is_numeric($data['odometer']) || $data['odometer'] < 0) $errors[] = 'Odometer must be a non-negative number.';
    if (!in_array($data['type'],['Truck','Van','Car','Motorcycle','Bus','Other'])) $errors[] = 'Invalid vehicle type.';

    if (empty($errors)) {
        // Check unique plate
        $chk = $db->prepare("SELECT id FROM vehicles WHERE license_plate=:p");
        $chk->execute(['p'=>$data['license_plate']]);
        if ($chk->fetch()) $errors[] = 'License plate already exists.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO vehicles (license_plate,make,model,year,type,max_capacity,odometer,notes) VALUES (:lp,:ma,:mo,:yr,:tp,:mc,:od,:nt)");
        $stmt->execute([
            'lp'=>$data['license_plate'], 'ma'=>$data['make'], 'mo'=>$data['model'],
            'yr'=>$data['year'], 'tp'=>$data['type'], 'mc'=>$data['max_capacity'],
            'od'=>$data['odometer'], 'nt'=>$data['notes'],
        ]);
        setFlash('success', 'Vehicle '.$data['license_plate'].' added successfully.');
        redirect('vehicles.php');
    }
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="vehicles.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Add Vehicle</h1>
            <p class="text-slate-500 text-sm">Register a new vehicle to the fleet</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
        <?php foreach($errors as $e): ?><p class="text-sm text-red-700">• <?= e($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">License Plate <span class="text-red-500">*</span></label>
                    <input type="text" name="license_plate" value="<?= e($data['license_plate']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
                           placeholder="e.g. FF-001-A" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                    <select name="type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach(['Truck','Van','Car','Motorcycle','Bus','Other'] as $t): ?>
                        <option value="<?=$t?>" <?= $data['type']===$t?'selected':'' ?>><?=$t?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Make <span class="text-red-500">*</span></label>
                    <input type="text" name="make" value="<?= e($data['make']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. Tata" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Model <span class="text-red-500">*</span></label>
                    <input type="text" name="model" value="<?= e($data['model']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. Prima 4038.S" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Year <span class="text-red-500">*</span></label>
                    <input type="number" name="year" value="<?= e($data['year']) ?>" min="1990" max="<?= date('Y')+1 ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Max Capacity (kg) <span class="text-red-500">*</span></label>
                    <input type="number" name="max_capacity" value="<?= e($data['max_capacity']) ?>" min="1" step="0.01"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="25000" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Current Odometer (km)</label>
                    <input type="number" name="odometer" value="<?= e($data['odometer']) ?>" min="0" step="0.1"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Any additional notes..."><?= e($data['notes']) ?></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition shadow">
                    Add Vehicle
                </button>
                <a href="vehicles.php" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

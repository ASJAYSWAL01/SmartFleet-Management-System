<?php
require_once 'includes/auth_check.php';
requireRole(['Manager','Safety Officer']);
$pageTitle = 'Schedule Maintenance';
$db = getDB();

$errors = [];
$data   = ['vehicle_id'=>'','maintenance_type'=>'Routine','description'=>'','scheduled_date'=>date('Y-m-d'),'vendor'=>'','notes'=>''];

$vehicles = $db->query("SELECT * FROM vehicles ORDER BY license_plate")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $data = [
        'vehicle_id'       => (int)($_POST['vehicle_id'] ?? 0),
        'maintenance_type' => $_POST['maintenance_type'] ?? 'Routine',
        'description'      => trim($_POST['description'] ?? ''),
        'scheduled_date'   => $_POST['scheduled_date'] ?? '',
        'vendor'           => trim($_POST['vendor'] ?? ''),
        'notes'            => trim($_POST['notes'] ?? ''),
    ];

    if (!$data['vehicle_id']) $errors[] = 'Select a vehicle.';
    if (empty($data['scheduled_date'])) $errors[] = 'Scheduled date is required.';
    if (!in_array($data['maintenance_type'],['Routine','Repair','Inspection','Tire','Oil Change','Brake','Engine','Other'])) $errors[] = 'Invalid type.';

    if (empty($errors)) {
        $db->beginTransaction();
        $db->prepare("INSERT INTO maintenance (vehicle_id,maintenance_type,description,scheduled_date,vendor,notes,status,created_by) VALUES (:vi,:mt,:desc,:sd,:ve,:nt,'Scheduled',:uid)")
           ->execute([
               'vi'=>$data['vehicle_id'], 'mt'=>$data['maintenance_type'], 'desc'=>$data['description']?:null,
               'sd'=>$data['scheduled_date'], 've'=>$data['vendor']?:null, 'nt'=>$data['notes']?:null,
               'uid'=>$_SESSION['user']['id'],
           ]);
        // Set vehicle to In Shop
        $db->prepare("UPDATE vehicles SET status='In Shop' WHERE id=:id AND status='Available'")->execute(['id'=>$data['vehicle_id']]);
        $db->commit();
        setFlash('success','Maintenance scheduled. Vehicle status set to In Shop if it was Available.');
        redirect('maintenance.php');
    }
}

include 'includes/header.php';
?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="maintenance.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div><h1 class="text-2xl font-bold text-slate-800">Schedule Maintenance</h1></div>
    </div>

    <?php if(!empty($errors)): ?>
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
        <?php foreach($errors as $err): ?><p class="text-sm text-red-700">• <?= e($err) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-700">
        ⚠️ Scheduling maintenance on an <strong>Available</strong> vehicle will set its status to <strong>In Shop</strong>.
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" class="space-y-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Vehicle <span class="text-red-500">*</span></label>
                    <select name="vehicle_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">— Select —</option>
                        <?php foreach($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $data['vehicle_id']==$v['id']?'selected':'' ?>>
                            <?= e($v['license_plate'].' — '.$v['make'].' '.$v['model'].' ['.$v['status'].']') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                    <select name="maintenance_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach(['Routine','Repair','Inspection','Tire','Oil Change','Brake','Engine','Other'] as $t): ?>
                        <option value="<?=$t?>" <?= $data['maintenance_type']===$t?'selected':'' ?>><?=$t?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Scheduled Date <span class="text-red-500">*</span></label>
                    <input type="date" name="scheduled_date" value="<?= e($data['scheduled_date']) ?>" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Vendor / Workshop</label>
                    <input type="text" name="vendor" value="<?= e($data['vendor']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. Quick Fix Garage">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                <textarea name="description" rows="2"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="What needs to be done..."><?= e($data['description']) ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e($data['notes']) ?></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition shadow">Schedule Maintenance</button>
                <a href="maintenance.php" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

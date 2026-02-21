<?php
require_once 'includes/auth_check.php';
requireRole(['Manager','Dispatcher']);
$pageTitle = 'Add Driver';
$db = getDB();

$errors = [];
$data   = ['name'=>'','email'=>'','phone'=>'','license_number'=>'','license_expiry'=>'','license_class'=>'HTV','status'=>'Off Duty','date_of_birth'=>'','address'=>'','emergency_contact'=>'','emergency_phone'=>'','notes'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $data = [
        'name'              => trim($_POST['name'] ?? ''),
        'email'             => trim($_POST['email'] ?? ''),
        'phone'             => trim($_POST['phone'] ?? ''),
        'license_number'    => strtoupper(trim($_POST['license_number'] ?? '')),
        'license_expiry'    => $_POST['license_expiry'] ?? '',
        'license_class'     => trim($_POST['license_class'] ?? ''),
        'status'            => $_POST['status'] ?? 'Off Duty',
        'date_of_birth'     => $_POST['date_of_birth'] ?? '',
        'address'           => trim($_POST['address'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'emergency_phone'   => trim($_POST['emergency_phone'] ?? ''),
        'notes'             => trim($_POST['notes'] ?? ''),
    ];

    if (empty($data['name'])) $errors[] = 'Name is required.';
    if (!empty($data['email']) && !filter_var($data['email'],FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (empty($data['license_number'])) $errors[] = 'License number is required.';
    if (empty($data['license_expiry'])) $errors[] = 'License expiry is required.';
    if (!in_array($data['status'],['On Duty','Off Duty','Suspended'])) $errors[] = 'Invalid status.';

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM drivers WHERE license_number=:ln");
        $chk->execute(['ln'=>$data['license_number']]);
        if ($chk->fetch()) $errors[] = 'License number already registered.';
    }

    if (empty($errors)) {
        $db->prepare("INSERT INTO drivers (name,email,phone,license_number,license_expiry,license_class,status,date_of_birth,address,emergency_contact,emergency_phone,notes)
                      VALUES (:na,:em,:ph,:ln,:le,:lc,:st,:dob,:ad,:ec,:ep,:nt)")
           ->execute([
               'na'=>$data['name'], 'em'=>$data['email']?:null, 'ph'=>$data['phone']?:null,
               'ln'=>$data['license_number'], 'le'=>$data['license_expiry'], 'lc'=>$data['license_class']?:null,
               'st'=>$data['status'], 'dob'=>$data['date_of_birth']?:null, 'ad'=>$data['address']?:null,
               'ec'=>$data['emergency_contact']?:null, 'ep'=>$data['emergency_phone']?:null, 'nt'=>$data['notes']?:null,
           ]);
        setFlash('success', 'Driver '.$data['name'].' added successfully.');
        redirect('drivers.php');
    }
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="drivers.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div><h1 class="text-2xl font-bold text-slate-800">Add Driver</h1><p class="text-slate-500 text-sm">Register a new driver</p></div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
        <?php foreach($errors as $err): ?><p class="text-sm text-red-700">• <?= e($err) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?= e($data['name']) ?>" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                    <input type="email" name="email" value="<?= e($data['email']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Phone</label>
                    <input type="text" name="phone" value="<?= e($data['phone']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">License Number <span class="text-red-500">*</span></label>
                    <input type="text" name="license_number" value="<?= e($data['license_number']) ?>" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
                           placeholder="DL-2021-001234">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">License Expiry <span class="text-red-500">*</span></label>
                    <input type="date" name="license_expiry" value="<?= e($data['license_expiry']) ?>" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">License Class</label>
                    <input type="text" name="license_class" value="<?= e($data['license_class']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="HTV, LTV, etc.">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Initial Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach(['On Duty','Off Duty','Suspended'] as $s): ?>
                        <option value="<?=$s?>" <?= $data['status']===$s?'selected':'' ?>><?=$s?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= e($data['date_of_birth']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Emergency Contact</label>
                    <input type="text" name="emergency_contact" value="<?= e($data['emergency_contact']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Emergency Phone</label>
                    <input type="text" name="emergency_phone" value="<?= e($data['emergency_phone']) ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
                <textarea name="address" rows="2"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e($data['address']) ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e($data['notes']) ?></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition shadow">Add Driver</button>
                <a href="drivers.php" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

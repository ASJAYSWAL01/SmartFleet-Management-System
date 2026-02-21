<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Driver Profile';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM drivers WHERE id=:id");
$stmt->execute(['id'=>$id]);
$driver = $stmt->fetch();
if (!$driver) { setFlash('error','Driver not found.'); redirect('drivers.php'); }

$trips = $db->prepare("SELECT t.*, v.license_plate FROM trips t JOIN vehicles v ON v.id=t.vehicle_id WHERE t.driver_id=:id ORDER BY t.created_at DESC LIMIT 20");
$trips->execute(['id'=>$id]);
$trips = $trips->fetchAll();

$expired = strtotime($driver['license_expiry']) < time();

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="drivers.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div><h1 class="text-2xl font-bold text-slate-800">Driver Profile</h1></div>
    </div>

    <?php if($expired): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
        ⚠️ This driver's license expired on <?= date('d M Y',strtotime($driver['license_expiry'])) ?>. They cannot be assigned to trips.
    </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col items-center text-center mb-5">
                <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold mb-3">
                    <?= strtoupper(substr($driver['name'],0,1)) ?>
                </div>
                <h2 class="font-bold text-slate-800 text-lg"><?= e($driver['name']) ?></h2>
                <?= statusBadge($driver['status']) ?>
            </div>

            <div class="space-y-3 text-sm">
                <?php
                $fields = [
                    'Email'              => $driver['email'],
                    'Phone'              => $driver['phone'],
                    'License No.'        => $driver['license_number'],
                    'License Class'      => $driver['license_class'],
                    'License Expiry'     => date('d M Y', strtotime($driver['license_expiry'])) . ($expired ? ' ⚠️' : ''),
                    'Date of Birth'      => $driver['date_of_birth'] ? date('d M Y', strtotime($driver['date_of_birth'])) : null,
                    'Emergency Contact'  => $driver['emergency_contact'],
                    'Emergency Phone'    => $driver['emergency_phone'],
                ];
                foreach ($fields as $label => $val):
                    if (!$val) continue;
                ?>
                <div class="flex flex-col">
                    <span class="text-xs text-slate-400 uppercase tracking-wide"><?= $label ?></span>
                    <span class="text-slate-700 font-medium"><?= e($val) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if($driver['address']): ?>
                <div class="flex flex-col">
                    <span class="text-xs text-slate-400 uppercase tracking-wide">Address</span>
                    <span class="text-slate-700"><?= nl2br(e($driver['address'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if($driver['notes']): ?>
                <div class="pt-2 border-t border-slate-100">
                    <span class="text-xs text-slate-400 uppercase tracking-wide block mb-1">Notes</span>
                    <p class="text-slate-600 text-xs"><?= nl2br(e($driver['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Trip History -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-5 border-b border-slate-100">
                <h3 class="font-semibold text-slate-700">Trip History (last 20)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-xs uppercase text-slate-500 border-b border-slate-100">
                            <th class="text-left px-4 py-3 font-medium">Trip #</th>
                            <th class="text-left px-4 py-3 font-medium">Route</th>
                            <th class="text-left px-4 py-3 font-medium">Vehicle</th>
                            <th class="text-left px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($trips as $t): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs text-blue-600"><?= e($t['trip_number']) ?></td>
                            <td class="px-4 py-3">
                                <div class="text-slate-700 truncate max-w-[140px]"><?= e($t['origin']) ?></div>
                                <div class="text-slate-400 text-xs">→ <?= e($t['destination']) ?></div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= e($t['license_plate']) ?></td>
                            <td class="px-4 py-3"><?= statusBadge($t['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($trips)): ?>
                        <tr><td colspan="4" class="text-center py-8 text-slate-400">No trips yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

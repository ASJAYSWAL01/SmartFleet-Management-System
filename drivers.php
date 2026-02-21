<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Drivers';
$db = getDB();

// Delete driver
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_driver']) && hasRole('Manager')) {
    $did = (int)$_POST['driver_id'];
    try {
        $db->prepare("DELETE FROM drivers WHERE id=:id")->execute(['id'=>$did]);
        setFlash('success','Driver deleted.');
    } catch(PDOException $e) {
        setFlash('error','Cannot delete driver — has associated trips.');
    }
    redirect('drivers.php');
}

// Change status
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_status']) && hasRole(['Manager','Safety Officer'])) {
    $did    = (int)$_POST['driver_id'];
    $newSt  = $_POST['new_status'] ?? '';
    if (in_array($newSt,['On Duty','Off Duty','Suspended'])) {
        $db->prepare("UPDATE drivers SET status=:s WHERE id=:id")->execute(['s'=>$newSt,'id'=>$did]);
        setFlash('success','Driver status updated.');
    }
    redirect('drivers.php');
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$where  = [];
$params = [];
if ($search) { $where[] = "(name LIKE :s OR license_number LIKE :s OR phone LIKE :s)"; $params['s']="%$search%"; }
if ($status)  { $where[] = "status=:st"; $params['st']=$status; }
$whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$drivers = $db->prepare("SELECT d.*, (SELECT COUNT(*) FROM trips WHERE driver_id=d.id AND status='Completed') as total_trips FROM drivers d $whereSql ORDER BY d.created_at DESC");
$drivers->execute($params);
$drivers = $drivers->fetchAll();

include 'includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Drivers</h1>
        <p class="text-slate-500 text-sm"><?= count($drivers) ?> driver(s) found</p>
    </div>
    <?php if(hasRole(['Manager','Dispatcher'])): ?>
    <a href="driver_create.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Driver
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, license, phone..."
               class="flex-1 min-w-[200px] px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Statuses</option>
            <?php foreach(['On Duty','Off Duty','Suspended'] as $s): ?>
            <option value="<?=$s?>" <?= $status===$s?'selected':'' ?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">Filter</button>
        <a href="drivers.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-200 transition">Reset</a>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs uppercase text-slate-500">
                    <th class="text-left px-5 py-3 font-medium">Driver</th>
                    <th class="text-left px-4 py-3 font-medium">License</th>
                    <th class="text-left px-4 py-3 font-medium">Expiry</th>
                    <th class="text-left px-4 py-3 font-medium">Phone</th>
                    <th class="text-left px-4 py-3 font-medium">Trips</th>
                    <th class="text-left px-4 py-3 font-medium">Status</th>
                    <th class="text-left px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($drivers as $d):
                    $expired = strtotime($d['license_expiry']) < time();
                ?>
                <tr class="hover:bg-slate-50 transition <?= $expired ? 'bg-red-50/50' : '' ?>">
                    <td class="px-5 py-3">
                        <a href="driver_profile.php?id=<?= $d['id'] ?>" class="font-semibold text-slate-800 hover:text-blue-600"><?= e($d['name']) ?></a>
                        <?php if($expired): ?><span class="ml-1 text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded">License Expired!</span><?php endif; ?>
                        <div class="text-xs text-slate-400"><?= e($d['license_class'] ?? '') ?></div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= e($d['license_number']) ?></td>
                    <td class="px-4 py-3 text-sm <?= $expired ? 'text-red-600 font-medium' : 'text-slate-600' ?>"><?= date('d M Y',strtotime($d['license_expiry'])) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= e($d['phone'] ?? '—') ?></td>
                    <td class="px-4 py-3 font-medium text-slate-700"><?= $d['total_trips'] ?></td>
                    <td class="px-4 py-3"><?= statusBadge($d['status']) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="driver_profile.php?id=<?= $d['id'] ?>" class="text-xs px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium transition">View</a>
                            <?php if(hasRole(['Manager','Safety Officer'])): ?>
                            <form method="POST" class="inline flex items-center gap-1">
                                <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                                <select name="new_status" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400">
                                    <?php foreach(['On Duty','Off Duty','Suspended'] as $s): ?>
                                    <option value="<?=$s?>" <?= $d['status']===$s?'selected':'' ?>><?=$s?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="change_status" class="text-xs px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-medium transition">Set</button>
                            </form>
                            <?php endif; ?>
                            <?php if(hasRole('Manager')): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this driver?')">
                                <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                                <button type="submit" name="delete_driver" class="text-xs px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition">Del</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($drivers)): ?>
                <tr><td colspan="7" class="text-center py-10 text-slate-400">No drivers found. <a href="driver_create.php" class="text-blue-600 hover:underline">Add one →</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'includes/auth_check.php';
$pageTitle = 'Reports';
$db = getDB();

// Date range filter
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-t');

// Overall Stats
$totalRevenue = $db->prepare("SELECT COALESCE(SUM(revenue),0) FROM trips WHERE status='Completed' AND actual_arrival BETWEEN :s AND :e");
$totalRevenue->execute(['s'=>$start.' 00:00:00','e'=>$end.' 23:59:59']);
$totalRevenue = (float)$totalRevenue->fetchColumn();

$totalCosts = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM vehicle_costs WHERE cost_date BETWEEN :s AND :e");
$totalCosts->execute(['s'=>$start,'e'=>$end]);
$totalCosts = (float)$totalCosts->fetchColumn();

$profit   = $totalRevenue - $totalCosts;
$roi      = $totalCosts > 0 ? round(($profit / $totalCosts)*100, 1) : 0;
$totalKm  = $db->prepare("SELECT COALESCE(SUM(distance_km),0) FROM trips WHERE status='Completed' AND actual_arrival BETWEEN :s AND :e");
$totalKm->execute(['s'=>$start.' 00:00:00','e'=>$end.' 23:59:59']);
$totalKm = (float)$totalKm->fetchColumn();
$costPerKm = $totalKm > 0 ? round($totalCosts / $totalKm, 2) : 0;

$completedTrips = $db->prepare("SELECT COUNT(*) FROM trips WHERE status='Completed' AND actual_arrival BETWEEN :s AND :e");
$completedTrips->execute(['s'=>$start.' 00:00:00','e'=>$end.' 23:59:59']);
$completedTrips = (int)$completedTrips->fetchColumn();

$cancelledTrips = $db->prepare("SELECT COUNT(*) FROM trips WHERE status='Cancelled' AND updated_at BETWEEN :s AND :e");
$cancelledTrips->execute(['s'=>$start.' 00:00:00','e'=>$end.' 23:59:59']);
$cancelledTrips = (int)$cancelledTrips->fetchColumn();

// Per Vehicle Report
$vehicleReport = $db->prepare("
    SELECT v.id, v.license_plate, v.make, v.model,
        COUNT(CASE WHEN t.status='Completed' THEN 1 END) as trips_done,
        COALESCE(SUM(CASE WHEN t.status='Completed' THEN t.revenue ELSE 0 END),0) as revenue,
        COALESCE(SUM(CASE WHEN t.status='Completed' THEN t.distance_km ELSE 0 END),0) as distance,
        COALESCE((SELECT SUM(amount) FROM vehicle_costs WHERE vehicle_id=v.id AND cost_date BETWEEN :s AND :e),0) as costs
    FROM vehicles v
    LEFT JOIN trips t ON t.vehicle_id=v.id AND t.actual_arrival BETWEEN :sa AND :ea
    GROUP BY v.id
    HAVING trips_done > 0 OR costs > 0
    ORDER BY revenue DESC
");
$vehicleReport->execute(['s'=>$start,'e'=>$end,'sa'=>$start.' 00:00:00','ea'=>$end.' 23:59:59']);
$vehicleReport = $vehicleReport->fetchAll();

// Cost Breakdown by Type
$costBreakdown = $db->prepare("SELECT cost_type, COALESCE(SUM(amount),0) as total FROM vehicle_costs WHERE cost_date BETWEEN :s AND :e GROUP BY cost_type ORDER BY total DESC");
$costBreakdown->execute(['s'=>$start,'e'=>$end]);
$costBreakdown = $costBreakdown->fetchAll();

// Top Drivers
$driverReport = $db->prepare("
    SELECT d.name, COUNT(*) as trips, COALESCE(SUM(t.revenue),0) as revenue, COALESCE(SUM(t.distance_km),0) as distance
    FROM trips t JOIN drivers d ON d.id=t.driver_id
    WHERE t.status='Completed' AND t.actual_arrival BETWEEN :s AND :e
    GROUP BY d.id ORDER BY revenue DESC LIMIT 10
");
$driverReport->execute(['s'=>$start.' 00:00:00','e'=>$end.' 23:59:59']);
$driverReport = $driverReport->fetchAll();

include 'includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Reports</h1>
        <p class="text-slate-500 text-sm">Fleet performance analytics</p>
    </div>
    <!-- Date Range -->
    <form method="GET" class="flex items-center gap-2">
        <input type="date" name="start" value="<?= e($start) ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <span class="text-slate-400">to</span>
        <input type="date" name="end" value="<?= e($end) ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">Apply</button>
    </form>
</div>

<!-- KPI Summary -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $kpis = [
        ['label'=>'Total Revenue','value'=>formatCurrency($totalRevenue),'sub'=>"$completedTrips trips",'color'=>'emerald'],
        ['label'=>'Total Costs','value'=>formatCurrency($totalCosts),'sub'=>"$cancelledTrips cancelled",'color'=>'red'],
        ['label'=>'Net Profit','value'=>formatCurrency($profit),'sub'=>"ROI: $roi%",'color'=>$profit>=0?'blue':'red'],
        ['label'=>'Cost per KM','value'=>'₹'.$costPerKm,'sub'=>number_format($totalKm,0).' km total','color'=>'violet'],
    ];
    foreach($kpis as $kpi):
        $colors=['emerald'=>'text-emerald-600 bg-emerald-50','red'=>'text-red-600 bg-red-50','blue'=>'text-blue-600 bg-blue-50','violet'=>'text-violet-600 bg-violet-50'];
        $c=$colors[$kpi['color']];
    ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= $kpi['label'] ?></p>
        <p class="text-2xl font-bold mt-1 <?= explode(' ',$c)[0] ?>"><?= $kpi['value'] ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $kpi['sub'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6 mb-6">
    <!-- Cost Breakdown -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-semibold text-slate-700 mb-4">Cost Breakdown</h2>
        <?php if (empty($costBreakdown)): ?>
        <p class="text-slate-400 text-sm">No costs in this period.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach($costBreakdown as $cb):
                $pct = $totalCosts > 0 ? round($cb['total']/$totalCosts*100) : 0;
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-slate-600 font-medium"><?= e($cb['cost_type']) ?></span>
                    <span class="text-slate-800 font-semibold"><?= formatCurrency((float)$cb['total']) ?> <span class="text-slate-400 font-normal text-xs">(<?= $pct ?>%)</span></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Drivers -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="p-5 border-b border-slate-100">
            <h2 class="font-semibold text-slate-700">Driver Performance</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-xs uppercase text-slate-500 border-b border-slate-100">
                        <th class="text-left px-5 py-3 font-medium">Driver</th>
                        <th class="text-right px-4 py-3 font-medium">Trips</th>
                        <th class="text-right px-4 py-3 font-medium">Distance</th>
                        <th class="text-right px-4 py-3 font-medium">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($driverReport as $dr): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-800"><?= e($dr['name']) ?></td>
                        <td class="px-4 py-3 text-right text-slate-600"><?= $dr['trips'] ?></td>
                        <td class="px-4 py-3 text-right text-slate-600"><?= number_format($dr['distance'],0) ?> km</td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-700"><?= formatCurrency((float)$dr['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($driverReport)): ?>
                    <tr><td colspan="4" class="text-center py-8 text-slate-400 text-sm">No completed trips in this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Vehicle Report -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h2 class="font-semibold text-slate-700">Vehicle Performance Report</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs uppercase text-slate-500">
                    <th class="text-left px-5 py-3 font-medium">Vehicle</th>
                    <th class="text-right px-4 py-3 font-medium">Trips</th>
                    <th class="text-right px-4 py-3 font-medium">Distance</th>
                    <th class="text-right px-4 py-3 font-medium">Revenue</th>
                    <th class="text-right px-4 py-3 font-medium">Costs</th>
                    <th class="text-right px-4 py-3 font-medium">Profit</th>
                    <th class="text-right px-4 py-3 font-medium">Cost/km</th>
                    <th class="text-right px-4 py-3 font-medium">ROI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($vehicleReport as $vr):
                    $vProfit = $vr['revenue'] - $vr['costs'];
                    $vRoi    = $vr['costs'] > 0 ? round(($vProfit/$vr['costs'])*100,1) : 0;
                    $vCpm    = $vr['distance'] > 0 ? round($vr['costs']/$vr['distance'],2) : 0;
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs font-bold text-slate-800"><?= e($vr['license_plate']) ?></span>
                        <span class="text-slate-400 text-xs ml-1"><?= e($vr['make'].' '.$vr['model']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-600"><?= $vr['trips_done'] ?></td>
                    <td class="px-4 py-3 text-right text-slate-600"><?= number_format($vr['distance'],0) ?> km</td>
                    <td class="px-4 py-3 text-right font-medium text-emerald-700"><?= formatCurrency((float)$vr['revenue']) ?></td>
                    <td class="px-4 py-3 text-right font-medium text-red-600"><?= formatCurrency((float)$vr['costs']) ?></td>
                    <td class="px-4 py-3 text-right font-semibold <?= $vProfit>=0?'text-emerald-700':'text-red-600' ?>"><?= formatCurrency($vProfit) ?></td>
                    <td class="px-4 py-3 text-right text-slate-600">₹<?= $vCpm ?></td>
                    <td class="px-4 py-3 text-right font-semibold <?= $vRoi>=0?'text-emerald-700':'text-red-600' ?>"><?= $vRoi ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($vehicleReport)): ?>
                <tr><td colspan="8" class="text-center py-8 text-slate-400">No data for selected period.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if(!empty($vehicleReport)): ?>
            <tfoot class="bg-slate-50 border-t-2 border-slate-200 font-semibold">
                <tr class="text-sm">
                    <td class="px-5 py-3 text-slate-700">Totals</td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $completedTrips ?></td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= number_format($totalKm,0) ?> km</td>
                    <td class="px-4 py-3 text-right text-emerald-700"><?= formatCurrency($totalRevenue) ?></td>
                    <td class="px-4 py-3 text-right text-red-600"><?= formatCurrency($totalCosts) ?></td>
                    <td class="px-4 py-3 text-right <?= $profit>=0?'text-emerald-700':'text-red-600' ?>"><?= formatCurrency($profit) ?></td>
                    <td class="px-4 py-3 text-right text-slate-700">₹<?= $costPerKm ?></td>
                    <td class="px-4 py-3 text-right <?= $roi>=0?'text-emerald-700':'text-red-600' ?>"><?= $roi ?>%</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'db.php';
require_once 'helpers.php';

// Calculate standard date boundaries
$prev_month_boundary = date('Y-m-01', strtotime('first day of last month'));

// Count statistics for the interactive dashboard indicators based on period tracking
$total_active = $db->query("SELECT COUNT(*) FROM members WHERE status = 'Alive'")->fetchColumn();
$total_deceased = $db->query("SELECT COUNT(*) FROM members WHERE status = 'Deceased'")->fetchColumn();

// Fetch active members who have paid at least up to the previous month dynamically
$paid_active = $db->prepare("SELECT COUNT(*) FROM members WHERE status = 'Alive' AND chanda_paid_to >= ?");
$paid_active->execute([$prev_month_boundary]);
$paid_active_count = $paid_active->fetchColumn();

$chanda_percent = ($total_active > 0) ? round(($paid_active_count / $total_active) * 100) : 0;

$pending_welfare = $db->query("SELECT COUNT(*) FROM welfare WHERE status = 'Pending'")->fetchColumn();

// Fetch Editable baseline reserve amount from system settings table
$base_reserve_stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'baitulmal_base_reserve'");
$base_reserve = (int) ($base_reserve_stmt->fetchColumn() ?: 250000);

// Fetch dynamic inflows metrics
$total_inflows = $db->query("SELECT SUM(amount) FROM baitulmal_inflows")->fetchColumn() ?: 0;

// Fetch dynamic outflows metrics (Paid / Disbursed only)
$total_outflows_disbursed = $db->query("SELECT SUM(amount) FROM welfare WHERE status = 'Paid'")->fetchColumn() ?: 0;

// Calculate net Baitul-Mal balance dynamically
$total_reserves_available = $base_reserve + $total_inflows - $total_outflows_disbursed;

// Fetch demographics data for Nagercoil wards
$wards_list = ["Ward 1", "Ward 2", "Ward 3", "Ward 4", "Ward 5", "Ward 6"];
$ward_demographics = [];
foreach ($wards_list as $ward) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE mahallah = ? AND status = 'Alive'");
    $stmt->execute([$ward]);
    $ward_demographics[$ward] = $stmt->fetchColumn();
}
$max_demographics_count = max(array_values($ward_demographics)) ?: 1;

// Fetch recent lists
$deceased_recent = $db->query("SELECT * FROM members WHERE status = 'Deceased' ORDER BY deceased_date DESC LIMIT 4")->fetchAll();

// Fetch 4 active members whose subscriptions are pending for the previous month compared to today
$unpaid_chanda_stmt = $db->prepare("
    SELECT * FROM members 
    WHERE status = 'Alive' 
    AND (chanda_paid_to IS NULL OR chanda_paid_to < ?)
    ORDER BY first_name ASC LIMIT 4
");
$unpaid_chanda_stmt->execute([$prev_month_boundary]);
$unpaid_chanda_list = $unpaid_chanda_stmt->fetchAll();

require_once 'header.php';
?>

<!-- Quick Statistics Dashboard -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <!-- Stat: Active Members -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Jamaath Members</p>
                <h3 class="text-3xl font-extrabold text-emerald-800 mt-2"><?php echo $total_active; ?></h3>
                <p class="text-xs text-slate-500 mt-1">Alive registered families</p>
            </div>
            <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl">
                <i class="fa-solid fa-user-check text-xl"></i>
            </div>
        </div>
    </div>
    <!-- Stat: Deceased -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Deceased (Marhoom)</p>
                <h3 class="text-3xl font-extrabold text-slate-700 mt-2"><?php echo $total_deceased; ?></h3>
                <p class="text-xs text-slate-500 mt-1">Burial register mappings</p>
            </div>
            <div class="bg-slate-100 text-slate-600 p-3 rounded-xl">
                <i class="fa-solid fa-monument text-xl"></i>
            </div>
        </div>
    </div>
    <!-- Stat: Chanda Ratio -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Chanda Status (Active)</p>
                <h3 class="text-3xl font-extrabold text-teal-700 mt-2"><?php echo $chanda_percent; ?>%</h3>
                <p class="text-xs text-slate-500 mt-1">Subscribed up to date</p>
            </div>
            <div class="bg-teal-50 text-teal-600 p-3 rounded-xl">
                <i class="fa-solid fa-receipt text-xl"></i>
            </div>
        </div>
    </div>
    <!-- Stat: Baitul-Mal Available -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Bait-Ul-Mal Balance</p>
                <h3 class="text-3xl font-extrabold text-amber-700 mt-2">
                    ₹<?php echo formatIndianCurrency($total_reserves_available); ?></h3>
                <p class="text-xs text-slate-500 mt-1">Zakat & charity reserves</p>
            </div>
            <div class="bg-amber-50 text-amber-600 p-3 rounded-xl">
                <i class="fa-solid fa-vault text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Demographics and mini trackers grid layouts -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Ward Distribution Progress Metrics -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Mahallah / Ward Demographics</h3>
                    <p class="text-xs text-slate-500">Active families distributed throughout regional Nagercoil wards
                    </p>
                </div>
                <span class="text-xs bg-emerald-50 px-2.5 py-1 rounded-lg text-emerald-700 font-bold">Dynamic
                    Census</span>
            </div>
            <div class="space-y-4">
                <?php foreach ($ward_demographics as $ward_name => $ward_count):
                    $percentage = ($ward_count / $max_demographics_count) * 100;
                    ?>
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span class="text-slate-700"><?php echo htmlspecialchars($ward_name); ?></span>
                            <span class="text-emerald-700 font-bold"><?php echo $ward_count; ?> Active Families</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2.5">
                            <div class="bg-gradient-to-r from-emerald-600 to-teal-700 h-2.5 rounded-full"
                                style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Deceased Member Records -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Deceased Member Records (Marhoom)</h3>
                    <p class="text-xs text-slate-500">Demise entries mapped directly to official graveyard and burial
                        plot archives</p>
                </div>
                <i class="fa-solid fa-dove text-slate-400"></i>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-150 text-slate-400 text-xs uppercase tracking-wider">
                            <th class="py-3 px-4 font-semibold">Member Details</th>
                            <th class="py-3 px-4 font-semibold">Card No</th>
                            <th class="py-3 px-4 font-semibold">Mahallah</th>
                            <th class="py-3 px-4 font-semibold">Demise Date</th>
                            <th class="py-3 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($deceased_recent)): ?>
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 text-xs">No deceased records filed
                                    yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($deceased_recent as $m_dec): ?>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-3.5 px-4">
                                        <p class="font-bold text-slate-800 text-xs">
                                            <?php echo htmlspecialchars($m_dec['first_name'] . ' ' . $m_dec['last_name']); ?>
                                        </p>
                                        <span class="text-[10px] text-slate-400">S/O:
                                            <?php echo htmlspecialchars($m_dec['father_husband_name']); ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 font-bold text-xs">
                                        <?php echo htmlspecialchars($m_dec['card_no']); ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-500">
                                        <?php echo htmlspecialchars($m_dec['mahallah']); ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-rose-600 font-semibold text-xs">
                                        <?php echo htmlspecialchars($m_dec['deceased_date']); ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <form method="POST" action="actions.php">
                                            <input type="hidden" name="action" value="revert_active">
                                            <input type="hidden" name="id" value="<?php echo $m_dec['id']; ?>">
                                            <button type="submit"
                                                class="text-emerald-700 text-xs font-bold hover:underline">Revert
                                                Status</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Modules Quick Navigation Desk -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">System Modules Directory</h3>
                    <p class="text-xs text-slate-500">Quick administrative gateway to all active system modules</p>
                </div>
            </div>

            <!-- Grid Layout representing all Navbar links with creative tracking cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">

                <!-- Core Modules -->
                <a href="dashboard.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-chart-line text-sm"></i></div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Dashboard</p>
                        <p class="text-[10px] text-slate-400 font-medium">Core Metrics</p>
                    </div>
                </a>

                <a href="members.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-users text-sm"></i></div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Members Directory</p>
                        <p class="text-[10px] text-slate-400 font-medium">Family Archives</p>
                    </div>
                </a>

                <a href="baitul_mal.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-handshake-angle text-sm"></i></div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Bait-Ul-Mal</p>
                        <p class="text-[10px] text-slate-400 font-medium">Welfare Ledger</p>
                    </div>
                </a>

                <a href="nikah.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-ring text-sm"></i></div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Nikah</p>
                        <p class="text-[10px] text-slate-400 font-medium">Marriage Register</p>
                    </div>
                </a>

                <a href="burial.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-monument text-sm"></i></div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Burial</p>
                        <p class="text-[10px] text-slate-400 font-medium">Burial Records</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Regional Information Desk and Subscription list -->
    <div class="space-y-6">
        <!-- NVK Muslim Jamaath Registry Banner -->
        <div
            class="bg-gradient-to-br from-teal-900 to-emerald-950 text-white p-6 rounded-2xl shadow-md relative overflow-hidden">
            <div class="absolute -right-16 -bottom-16 w-44 h-44 bg-emerald-800/10 rounded-full"></div>
            <h3 class="text-xl font-bold serif-title mb-2">NVK Muslim Jamaath Registry</h3>
            <p class="text-xs text-emerald-200 leading-relaxed mb-4">Official administrative portal governing
                demographic registrations, Bait-Ul-Mal support initiatives, Nikah certifications, and burial records in
                Vadasery.</p>
            <div class="border-t border-teal-800/60 pt-4 space-y-3.5">
                <div class="flex items-center space-x-3 text-sm">
                    <span
                        class="bg-emerald-800/50 w-8 h-8 rounded-xl flex items-center justify-center text-xs text-emerald-300">🕌</span>
                    <div>
                        <p class="text-[10px] text-emerald-300">Central Registry Hub</p>
                        <p class="font-semibold text-emerald-50 text-xs">Kuthba Pallivasal, Vadasery, Nagercoil</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 text-sm">
                    <span
                        class="bg-emerald-800/50 w-8 h-8 rounded-xl flex items-center justify-center text-xs text-emerald-300">📞</span>
                    <div>
                        <p class="text-[10px] text-emerald-300">Administrative Hotline</p>
                        <p class="font-semibold text-emerald-50 text-xs">+91 00000 00000</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unpaid subscription alert desk -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-bold text-slate-800">Pending Chanda Tracker</h4>
                <span
                    class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Unpaid</span>
            </div>
            <p class="text-xs text-slate-500 mb-4">Displays members who haven't paid their subscription up to the
                previous month (<b><?php echo date('F Y', strtotime('first day of last month')); ?></b>).</p>
            <div class="space-y-3">
                <?php if (empty($unpaid_chanda_list)): ?>
                    <div class="text-center py-4 text-xs text-slate-400 font-medium">All active members are fully paid!
                    </div>
                <?php else: ?>
                    <?php foreach ($unpaid_chanda_list as $unpaid): ?>
                        <div class="flex justify-between items-center bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <div>
                                <p class="text-xs font-bold text-slate-800">
                                    <?php echo htmlspecialchars($unpaid['first_name'] . ' ' . $unpaid['last_name']); ?>
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    <?php if (!empty($unpaid['chanda_paid_to'])): ?>
                                        Paid up to: <span
                                            class="font-semibold text-emerald-700"><?php echo date('M Y', strtotime($unpaid['chanda_paid_to'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-rose-600">No payment history logged</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <!-- Mark Paid updates the chanda_paid_to dynamically up to the previous month boundary -->
                            <form method="POST" action="actions.php">
                                <input type="hidden" name="action" value="collect_chanda">
                                <input type="hidden" name="id" value="<?php echo $unpaid['id']; ?>">
                                <button type="submit"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-semibold px-2.5 py-1.5 rounded-lg transition-colors shadow">
                                    Quick Collect
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center pt-2">
                        <a href="members.php?chanda=Unpaid"
                            class="text-xs font-semibold text-emerald-700 hover:underline">View all pending members
                            &raquo;</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
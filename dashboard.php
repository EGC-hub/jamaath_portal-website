<?php
require_once 'db.php';
require_once 'helpers.php';

// Calculate standard date boundaries
$prev_month_boundary = date('Y-m-01', strtotime('first day of last month'));

// Count statistics for the interactive dashboard indicators based on period tracking
$total_active = $db->query("SELECT COUNT(*) FROM members WHERE status = 'Alive'")->fetchColumn();
$total_deceased = $db->query("SELECT COUNT(*) FROM members WHERE status = 'Deceased'")->fetchColumn();

// MODIFICATION: Fetch active members whose LATEST ledger payment covers at least up to the previous month boundary
$paid_active = $db->prepare("
    SELECT COUNT(*) 
    FROM members m 
    WHERE m.status = 'Alive' 
      AND (
        SELECT cp.paid_to 
        FROM chanda_payments cp 
        WHERE cp.member_id = m.id 
        ORDER BY cp.paid_to DESC LIMIT 1
      ) >= ?
");
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
$deceased_recent = $db->query("SELECT b.*, m.card_no AS deceased_member_card_no, m.mahallah AS deceased_mahallah
FROM burial_registry b
LEFT JOIN members m ON b.deceased_member_id = m.id
ORDER BY b.death_datetime DESC 
LIMIT 4;")->fetchAll();

// MODIFICATION: Fetch 4 active members whose latest subscription record is missing or pending compared to the previous month boundary
$unpaid_chanda_stmt = $db->prepare("
    SELECT m.* FROM members m
    WHERE m.status = 'Alive' 
      AND (
        SELECT cp.paid_to 
        FROM chanda_payments cp 
        WHERE cp.member_id = m.id 
        ORDER BY cp.paid_to DESC LIMIT 1
      ) IS NULL 
      OR (
        SELECT cp.paid_to 
        FROM chanda_payments cp 
        WHERE cp.member_id = m.id 
        ORDER BY cp.paid_to DESC LIMIT 1
      ) < ?
    ORDER BY m.first_name ASC 
    LIMIT 4
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
                <p class="text-xs text-slate-500 mt-1">Subscribed up to date
                    (<b><?php echo date('F Y', strtotime('first day of last month')); ?></b>)</p>
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
                    <p class="text-xs text-slate-500">Demise entries mapped directly to official burial details
                    </p>
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
                            <th class="py-3 px-4 font-semibold">Age</th>
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
                                            <?php echo htmlspecialchars($m_dec['deceased_name']); ?>
                                        </p>
                                        <span class="text-[10px] text-slate-400">S/O:
                                            <?php echo htmlspecialchars($m_dec['deceased_father_husband']); ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 font-bold text-xs">
                                        <?php echo htmlspecialchars($m_dec['deceased_member_card_no']); ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-500">
                                        <?php echo date('d M Y - h:i A', strtotime($m_dec['death_datetime'])); ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-rose-600 font-semibold text-xs">
                                        <span class="bg-teal-50 px-2.5 py-1 rounded-md text-[10px]">
                                            <i class="fa-solid fa-clock mr-1"></i>
                                            <?php
                                            if (!empty($m_dec['death_datetime'])) {
                                                // Standardizes parsing from database format
                                                $dateObj = date_create($m_dec['death_datetime']);
                                                if ($dateObj) {
                                                    echo date_format($dateObj, 'd M Y - h:i A');
                                                } else {
                                                    echo htmlspecialchars($m_dec['death_datetime']); // Fallback if string is purely text
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php echo htmlspecialchars($m_dec['deceased_age']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chanda report generator -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all mt-6">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="bg-amber-50 text-amber-600 p-2 rounded-lg">
                        <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Chanda Report Builder</h4>
                        <p class="text-[11px] text-slate-400">Export real-time filtered subscription profiles & ledger
                            balances</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" id="report_reset_btn"
                        class="text-[10px] font-bold text-slate-400 hover:text-rose-600 border border-slate-200 hover:border-rose-200 px-2.5 py-1 rounded-lg transition-colors flex items-center gap-1 bg-white">
                        <i class="fa-solid fa-arrow-rotate-left text-[9px]"></i> Clear
                    </button>
                    <div id="report-match-badge"
                        class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-1 rounded-full transition-all">
                        Scanning...
                    </div>
                </div>
            </div>

            <form id="chanda_report_form" method="GET" action="export_chanda_report.php" target="_blank"
                class="mt-4 space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Search
                            Identifier</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" name="search" id="report_search_input"
                                placeholder="Name, Card No or Phone..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:border-teal-500 focus:bg-white transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Mahallah
                            Neighborhood</label>
                        <select name="mahallah" id="report_mahallah_select"
                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:border-teal-500 focus:bg-white transition-all">
                            <option value="All">All Mahallahs</option>
                            <option value="Ward 1">Ward 1</option>
                            <option value="Ward 2">Ward 2</option>
                            <option value="Ward 3">Ward 3</option>
                            <option value="Ward 4">Ward 4</option>
                            <option value="Ward 5">Ward 5</option>
                            <option value="Ward 6">Ward 6</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Chanda
                            Compliance Status</label>
                        <select name="filter_chanda" id="report_chanda_select"
                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:border-teal-500 focus:bg-white transition-all">
                            <option value="All">Show All Records</option>
                            <option value="Paid">Paid (Up to Date)</option>
                            <option value="Unpaid">Unpaid / Arrears Pending</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-2 pt-2">
                    <button type="button" onclick="fetchChandaReportPreview()" id="report_preview_btn"
                        class="flex-1 bg-teal-700 hover:bg-teal-800 text-white font-bold py-2 px-4 rounded-xl text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        Compile Preview
                    </button>

                    <button type="submit" id="report_print_btn" name="format" value="print"
                        class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-4 rounded-xl text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-print"></i>
                        Print
                    </button>

                    <button type="submit" id="report_excel_btn" name="format" value="excel"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-file-excel"></i>
                        Spreadsheet (.xls)
                    </button>
                </div>
            </form>
            <div class="mt-6 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden hidden flex flex-col"
                id="chanda-preview-wrapper">
                <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3 flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Chanda Live Report
                        Preview Sandbox</span>

                    <button type="button" onclick="clearChandaReportPreview()"
                        class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-[10px] px-3 py-1.5 rounded-lg transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-trash-can"></i> Clear Preview
                    </button>
                </div>

                <iframe id="chanda-preview-frame" class="w-full h-[650px] border-0 m-0 p-0" src="about:blank"></iframe>
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
                        <i class="fa-solid fa-chart-line text-sm"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Dashboard</p>
                        <p class="text-[10px] text-slate-400 font-medium">Core Metrics</p>
                    </div>
                </a>

                <a href="members.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-users text-sm"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Members Directory</p>
                        <p class="text-[10px] text-slate-400 font-medium">Family Archives</p>
                    </div>
                </a>

                <a href="baitul_mal.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-handshake-angle text-sm"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Bait-Ul-Mal</p>
                        <p class="text-[10px] text-slate-400 font-medium">Welfare Ledger</p>
                    </div>
                </a>

                <a href="nikah.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-ring text-sm"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 text-xs group-hover:text-emerald-950">Nikah</p>
                        <p class="text-[10px] text-slate-400 font-medium">Marriage Register</p>
                    </div>
                </a>

                <a href="burial.php"
                    class="p-3.5 bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-xl transition-all duration-200 group flex items-center space-x-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-white text-slate-500 group-hover:text-emerald-700 shadow-sm border border-slate-100 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-monument text-sm"></i>
                    </div>
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

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Compile active dataset lookup map of all living members from database
        const activeMembersDataset = <?php
        $feedback_stmt = $db->query("SELECT id, first_name, last_name, card_no, phone, mahallah, chanda_status FROM members WHERE status = 'Alive'");
        echo json_encode($feedback_stmt->fetchAll(PDO::FETCH_ASSOC));
        ?>;

        const reportForm = document.getElementById('chanda_report_form');
        const searchInput = document.getElementById('report_search_input');
        const mahallahSelect = document.getElementById('report_mahallah_select');
        const chandaSelect = document.getElementById('report_chanda_select');
        const matchBadge = document.getElementById('report-match-badge');
        const resetBtn = document.getElementById('report_reset_btn');
        const printBtn = document.getElementById('report_print_btn');
        const excelBtn = document.getElementById('report_excel_btn');

        function calculateLiveReportMatches() {
            const queryText = searchInput.value.toLowerCase().trim();
            const selectedMahallah = mahallahSelect.value;
            const selectedChanda = chandaSelect.value;

            let matchCount = 0;

            activeMembersDataset.forEach(member => {
                const matchesText = !queryText ||
                    (member.first_name + ' ' + member.last_name).toLowerCase().includes(queryText) ||
                    member.card_no.toLowerCase().includes(queryText) ||
                    member.phone.includes(queryText);

                const matchesMahallah = (selectedMahallah === 'All') || (member.mahallah === selectedMahallah);
                const matchesChanda = (selectedChanda === 'All') || (member.chanda_status === selectedChanda);

                if (matchesText && matchesMahallah && matchesChanda) {
                    matchCount++;
                }
            });

            if (matchCount === 0) {
                matchBadge.className = "bg-rose-100 text-rose-700 text-[10px] font-bold px-2.5 py-1 rounded-full animate-pulse";
                matchBadge.textContent = "0 Records Found";
                printBtn.disabled = true;
                excelBtn.disabled = true;
            } else {
                matchBadge.className = "bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-1 rounded-full";
                matchBadge.textContent = matchCount + " Matching Records";
                printBtn.disabled = false;
                excelBtn.disabled = false;
            }
        }

        // MODIFICATION: Form event listener logic for explicit interactive click reset
        resetBtn.addEventListener('click', function () {
            reportForm.reset();
            calculateLiveReportMatches(); // Instantly sweeps metrics back to baseline counts
        });

        // MODIFICATION: Reset parameters after download submission to prevent form-state freezing
        reportForm.addEventListener('submit', function () {
            // Delays execution for a split millisecond so the processing headers capture initial variables successfully before clearing fields
            setTimeout(() => {
                reportForm.reset();
                calculateLiveReportMatches();
            }, 150);
        });

        // Attach real-time evaluation observers
        searchInput.addEventListener('input', calculateLiveReportMatches);
        mahallahSelect.addEventListener('change', calculateLiveReportMatches);
        chandaSelect.addEventListener('change', calculateLiveReportMatches);

        // Initial load scan
        calculateLiveReportMatches();
    });

    // ATTACH THESE INSIDE YOUR MAIN SCRIPT TAG WINDOW BINDINGS AREA
    function fetchChandaReportPreview() {
        var search = document.getElementById('report_search_input').value;
        var mahallah = document.getElementById('report_mahallah_select').value;
        var chanda = document.getElementById('report_chanda_select').value;

        var wrapper = document.getElementById('chanda-preview-wrapper');
        var iframe = document.getElementById('chanda-preview-frame');

        // Build the query sequence pointing directly to your existing export router mapping
        var url = 'export_chanda_report.php?search=' + encodeURIComponent(search) +
            '&mahallah=' + encodeURIComponent(mahallah) +
            '&filter_chanda=' + encodeURIComponent(chanda) +
            '&format=preview';

        // Reveal the sandbox dashboard frame module wrapper view container layout box
        wrapper.classList.remove('hidden');

        // Set targeted iframe resource source path trigger parameter array safely
        iframe.src = url;
    }

    function clearChandaReportPreview() {
        var reportForm = document.getElementById('chanda_report_form');
        var wrapper = document.getElementById('chanda-preview-wrapper');
        var iframe = document.getElementById('chanda-preview-frame');

        // 1. Reset selection options layout inside form parameters back to baseline indices
        reportForm.reset();

        // 2. Clear values map internally by executing your live counter recalculator update rule
        // Note: Assuming calculateLiveReportMatches is exposed globally or inside the same scope tree container
        if (typeof calculateLiveReportMatches === 'function') {
            calculateLiveReportMatches();
        }

        // 3. Reset preview window target array and tuck layout element back safely
        iframe.src = 'about:blank';
        wrapper.classList.add('hidden');
    }
</script>

<?php require_once 'footer.php'; ?>
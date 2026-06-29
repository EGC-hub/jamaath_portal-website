<?php
date_default_timezone_set('Asia/Kolkata');

// Start authenticated session tracker to secure the report endpoint
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

$type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$format = $_GET['format'] ?? '';

$whereClauses = [];
$params = [];

if (!empty($start_date)) {
    $whereClauses[] = "DATE(date_added) >= :start_date";
    $params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $whereClauses[] = "DATE(date_added) <= :end_date";
    $params[':end_date'] = $end_date;
}

$whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";
$rows = [];
$reportTitle = "";

try {
    if ($type === 'inflows') {
        $reportTitle = "Contribution Inflows (Collections) Sheet";
        $stmt = $db->prepare("SELECT donor_name AS label_name, type, amount, date_added FROM baitulmal_inflows" . $whereSql . " ORDER BY date_added DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($type === 'outflows') {
        $reportTitle = "Welfare Outflows (Disbursements) Sheet";
        $stmt = $db->prepare("SELECT name AS label_name, type, amount, date_added FROM welfare" . $whereSql . " ORDER BY date_added DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $reportTitle = "Aid Applications Registry Sheet";
        $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS label_name, type, amount, date_added FROM baitulmal_applications" . $whereSql . " ORDER BY date_added DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die("<div style='padding:20px; font-family:sans-serif; color:#b91c1c;'>Database Error Encountered: " . htmlspecialchars($e->getMessage()) . "</div>");
}

$totalAmount = 0;
foreach ($rows as $row) {
    $totalAmount += $row['amount'];
}

// ==================== EXECUTIVE NATIVE EXCEL GENERATION ENGINE ====================
if ($format === 'excel') {
    $filename = "BaitUlMal_" . ucfirst($type) . "_Report_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    // Force Excel to parse the Rupee symbol (₹) cleanly using UTF-8 BOM
    echo "\xEF\xBB\xBF";

    // Build the executive styled table layout matching red burgundy criteria
    echo "<table border='1'>";
    echo "<tr><th colspan='4' style='background-color: #1e293b; color: #ffffff; font-size: 14px; font-weight: bold; text-align: center;'>NVK MUSLIM JAMAATH REGISTRY PORTAL</th></tr>";
    echo "<tr><th colspan='4' style='background-color: #f8fafc; color: #881337; font-size: 11px; font-weight: bold; text-align: center;'>$reportTitle (" . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date)) . ")</th></tr>";

    echo "<tr style='background-color: #881337; color: #ffffff; font-weight: bold;'>";
    echo "<th>Donar Name</th>";
    echo "<th>Donation Type</th>";
    echo "<th>Date & Time</th>";
    echo "<th>Amount (₹)</th>";
    echo "</tr>";

    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['label_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['type']) . "</td>";
        echo "<td>" . date('d M Y - h:i A', strtotime($row['date_added'])) . "</td>";
        echo "<td style='text-align: right;'>₹" . number_format($row['amount'], 2) . "</td>";
        echo "</tr>";
    }

    echo "<tr style='font-weight: bold; background-color: #f1f5f9;'>";
    echo "<td colspan='3' style='text-align: right;'>Final Statement Total:</td>";
    echo "<td style='text-align: right; color: #881337;'>₹" . number_format($totalAmount, 2) . "</td>";
    echo "</tr>";
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Bait-ul-Mal Financial  Statement</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        if (window.self !==window.top) {
            document.querySelector('.iframe-hide').style.display='none';
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased p-8 print:bg-white print:p-0">

    <div
        class="max-w-5xl mx-auto mb-6 p-4 bg-white border border-slate-200 rounded-xl flex items-center justify-between shadow-xs print:hidden iframe-hide">
        <div class="text-xs text-slate-500 font-medium">
            <i class="fa-solid fa-circle-info text-blue-600 mr-1"></i> Review document lines below before filing or
            saving as PDF.
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.close();"
                class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-4 py-2 rounded-lg transition-all cursor-pointer">
                Close Window
            </button>
            <button onclick="window.print();"
                class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs px-5 py-2 rounded-lg transition-all shadow-sm cursor-pointer">
                Execute Print / Export PDF
            </button>
        </div>
    </div>

    <div
        class="max-w-5xl mx-auto bg-white border border-slate-200 rounded-2xl p-8 shadow-sm print:border-none print:shadow-none print:p-0">

        <div class="border-b-2 border-slate-900 pb-5 mb-6 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">NVK MUSLIM JAMAATH PORTAL</h1>
                <p class="text-xs font-bold text-rose-900 mt-0.5 uppercase tracking-wider">Bait-ul-Mal Accounts
                    Registry</p>
            </div>
            <div class="text-right text-xs text-slate-400 font-medium">
                <div>Generated:
                    <?php echo date('d M Y - h:i A'); ?>
                </div>
                <div class="font-mono text-[10px] mt-0.5">Report generated by:
                    <?php echo htmlspecialchars(isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'System'); ?>
                </div>
            </div>
        </div>

        <div
            class="grid grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4 mb-6 border border-slate-100 text-xs font-semibold text-slate-700 print:bg-transparent print:border-none">
            <div>
                <div class="text-[10px] uppercase text-slate-400 tracking-wider font-bold mb-0.5">Statement Target Scope
                </div>
                <div class="text-slate-900 text-sm font-extrabold">
                    <?php echo $reportTitle; ?>
                </div>
            </div>
            <div>
                <div class="text-[10px] uppercase text-slate-400 tracking-wider font-bold mb-0.5">Designated Accounting
                    Timeline</div>
                <div class="text-slate-600 font-mono">
                    <?php echo date('d M Y', strtotime($start_date)); ?> to
                    <?php echo date('d M Y', strtotime($end_date)); ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-[10px] uppercase text-slate-400 tracking-wider font-bold mb-0.5">Total Compiled Volume
                </div>
                <div class="text-rose-950 text-base font-black">₹
                    <?php echo number_format($totalAmount); ?> <span class="text-xs font-normal text-slate-400">(
                        <?php echo count($rows); ?> records)
                    </span>
                </div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div
                class="text-center py-12 text-slate-400 text-xs font-medium border border-dashed border-slate-200 rounded-xl">
                No verified records were logged into system registries within the selected date limits.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-100/60 text-[10px] uppercase font-bold text-slate-500 tracking-wider print:bg-transparent">
                            <th class="py-3 px-4">Donar Name</th>
                            <th class="py-3 px-4">Donation Type</th>
                            <th class="py-3 px-4 font-mono">Date & Time</th>
                            <th class="py-3 px-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    <?php echo htmlspecialchars($row['label_name']); ?>
                                </td>
                                <td class="py-3 px-4 text-slate-500">
                                    <?php echo htmlspecialchars($row['type']); ?>
                                </td>
                                <td class="py-3 px-4 text-slate-400 font-mono">
                                    <?php echo date('d M Y - h:i A', strtotime($row['date_added'])); ?>
                                </td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 text-sm">₹
                                    <?php echo number_format($row['amount']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr class="bg-slate-50 font-bold border-t-2 border-slate-200 print:bg-transparent">
                            <td colspan="3"
                                class="py-4 px-4 text-right uppercase text-[10px] tracking-wider text-slate-400 font-extrabold">
                                Final Statement Total:</td>
                            <td
                                class="py-4 px-4 text-right text-base font-black text-rose-950 border-b-4 double border-rose-950">
                                ₹
                                <?php echo number_format($totalAmount); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- <div class="mt-16 grid grid-cols-2 gap-8 text-xs font-bold text-slate-400 print:mt-24">
            <div>
                <div class="w-48 border-b border-slate-300 mb-1"></div>
                <div>Compiled By: Secretary Registry Terminal</div>
            </div>
            <div class="text-right flex flex-col items-end">
                <div class="w-48 border-b border-slate-300 mb-1"></div>
                <div>Authorized Signature: Jamaath Bait-ul-Mal Auditor</div>
            </div>
        </div> -->
    </div>
</body>

</html>
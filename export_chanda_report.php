<?php
date_default_timezone_set('Asia/Kolkata');

// Start authenticated session tracker to secure the report endpoint
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Enforce user authentication bounds
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Access Denied: Please log in to generate reports.");
}

// Include your secure PDO database booster connection 
require_once 'db.php';

// 1. Capture dynamic filter parameters safely
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$mahallah = isset($_GET['mahallah']) ? trim($_GET['mahallah']) : 'All';
$filter_chanda = isset($_GET['filter_chanda']) ? trim($_GET['filter_chanda']) : 'All';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'print';

// Calculate the previous month boundary baseline identically to dashboard states
$prev_month_boundary = date('Y-m-01', strtotime('first day of last month'));

// 2. Build the query dynamically matching accounting logic parameters
$where_clauses = ["m.status = 'Alive'"]; // Always scan living members only
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR m.card_no LIKE ? OR m.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($mahallah !== 'All') {
    $where_clauses[] = "m.mahallah = ?";
    $params[] = $mahallah;
}

if ($filter_chanda !== 'All') {
    if ($filter_chanda === 'Paid') {
        $where_clauses[] = "(
            SELECT cp.paid_to 
            FROM chanda_payments cp 
            WHERE cp.member_id = m.id 
            ORDER BY cp.paid_to DESC LIMIT 1
        ) >= ?";
        $params[] = $prev_month_boundary;
    } else {
        $where_clauses[] = "(
            (SELECT cp.paid_to FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) IS NULL 
            OR 
            (SELECT cp.paid_to FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) < ?
        )";
        $params[] = $prev_month_boundary;
    }
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch matching dataset along with their precise latest range parameters and transaction amounts
$query_string = "
    SELECT m.*,
           (SELECT cp.paid_from FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) AS latest_paid_from,
           (SELECT cp.paid_to FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) AS latest_paid_to,
           (SELECT cp.total_amount FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) AS latest_total_amount
    FROM members m
    WHERE $where_sql
    ORDER BY m.first_name ASC
";

$stmt = $db->prepare($query_string);
$stmt->execute($params);
$report_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format SQL Date values cleanly for view templates
function formatReportMonthYear($date_str)
{
    if (empty($date_str))
        return 'No Record';
    return date('M Y', strtotime($date_str));
}

// 3. Branch output layout execution streams based on selected button format
if ($format === 'excel') {
    // Branch Out: Set streaming binary content attachment headers to export clean Excel sheets
    $filename = "Chanda_Subscription_Report_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    // MODIFICATION: Send the UTF-8 BOM to force Excel to read the Rupee symbol (₹) correctly
    echo "\xEF\xBB\xBF";

    // Construct structural table template directly for Excel interpreter parsing
    echo "<table border='1'>";
    echo "<tr style='background-color: #047857; color: #ffffff; font-weight: bold;'>";
    echo "<th>Card No</th>";
    echo "<th>Full Name</th>";
    echo "<th>Mahallah</th>";
    echo "<th>Phone Number</th>";
    echo "<th>Status Tag</th>";
    echo "<th>Paid From</th>";
    echo "<th>Paid To</th>";
    echo "<th>Amount Paid (₹)</th>"; // Added Header
    echo "</tr>";

    $grand_total_excel = 0;
    foreach ($report_records as $row) {
        $full_name = $row['first_name'] . ' ' . $row['last_name'];
        $amount = isset($row['latest_total_amount']) ? (float) $row['latest_total_amount'] : 0.00;
        $grand_total_excel += $amount;

        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['card_no']) . "</td>";
        echo "<td>" . htmlspecialchars($full_name) . "</td>";
        echo "<td>" . htmlspecialchars($row['mahallah']) . "</td>";
        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($row['chanda_status']) . "</td>";
        echo "<td>" . formatReportMonthYear($row['latest_paid_from']) . "</td>";
        echo "<td>" . formatReportMonthYear($row['latest_paid_to']) . "</td>";
        echo "<td>₹" . number_format($amount, 2) . "</td>"; // Added Cell
        echo "</tr>";
    }
    // Summary Row for Excel Spreadsheet
    echo "<tr style='font-weight: bold; background-color: #f1f5f9;'>";
    echo "<td colspan='7' style='text-align: right;'>Grand Total:</td>";
    echo "<td>₹" . number_format($grand_total_excel, 2) . "</td>";
    echo "</tr>";
    echo "</table>";
    exit;
} else {
    // Default Stream: Render fully formatted, highly printable minimal layout window view
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Chanda Subscription Ledger Audit Report</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <style>
            @media print {
                .no-print {
                    display: none;
                }

                body {
                    background-color: #ffffff;
                    padding: 0;
                }
            }
        </style>
    </head>

    <body class="bg-slate-50 p-8 font-sans text-slate-800">

        <div class="max-w-5xl mx-auto bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
            <div
                class="no-print flex justify-between items-center bg-slate-100 p-4 rounded-xl mb-6 border border-slate-200">
                <p class="text-xs font-semibold text-slate-500">📄 System generated document layout. Ready for signature or
                    printing.</p>
                <button onclick="window.print();"
                    class="bg-teal-700 hover:bg-teal-800 text-white font-bold py-1.5 px-4 rounded-lg text-xs uppercase tracking-wide cursor-pointer transition-colors">
                    🖨️ Execute Print Window
                </button>
            </div>

            <div class="flex justify-between items-start border-b-2 border-slate-900 pb-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase">NVK Muslim Jamaath
                    </h1>
                    <p class="text-xs text-slate-500 font-medium">Nagercoil Registry Office | Chanda
                        Statement</p>
                    <div
                        class="mt-2 text-[11px] text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg inline-block border border-slate-200">
                        <b>Active Filters:</b> Mahallah: <span class="underline font-bold">
                            <?php echo htmlspecialchars($mahallah); ?>
                        </span> |
                        Chanda Status: <span class="underline font-bold">
                            <?php echo htmlspecialchars($filter_chanda); ?>
                        </span>
                        <?php if (!empty($search))
                            echo " | Search Keyword: '<i>" . htmlspecialchars($search) . "</i>'"; ?>
                    </div>
                </div>
                <div class="text-right">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Statement Generated</h3>
                    <p class="text-sm font-bold text-slate-800 mt-1">
                        <?php echo date('d F Y - h:i A'); ?>
                    </p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Report Generated By:
                        <?php echo htmlspecialchars(isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'System'); ?>
                    </p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b-2 border-slate-400 bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[10px]">
                            <th class="p-3">Card No</th>
                            <th class="p-3">Member Name</th>
                            <th class="p-3">Mahallah Sector</th>
                            <th class="p-3">Phone Number</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3">Paid From</th>
                            <th class="p-3">Paid Up To</th>
                            <th class="p-3 text-right">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (count($report_records) === 0): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-400 italic">No member profiles matched the
                                    given parameters.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $grand_total_print = 0;
                            foreach ($report_records as $row):
                                $row_amount = isset($row['latest_total_amount']) ? (float) $row['latest_total_amount'] : 0.00;
                                $grand_total_print += $row_amount;
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-3 font-mono font-bold text-slate-900">
                                        <?php echo htmlspecialchars($row['card_no']); ?>
                                    </td>
                                    <td class="p-3 font-bold text-slate-800">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </td>
                                    <td class="p-3 text-slate-600"><?php echo htmlspecialchars($row['mahallah']); ?></td>
                                    <td class="p-3 font-mono text-slate-500"><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td class="p-3 text-center">
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $row['chanda_status'] === 'Paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                            <?php echo htmlspecialchars($row['chanda_status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-slate-700 font-medium">
                                        <?php echo formatReportMonthYear($row['latest_paid_from']); ?>
                                    </td>
                                    <td
                                        class="p-3 font-bold <?php echo ($row['latest_paid_to'] >= $prev_month_boundary) ? 'text-emerald-700' : 'text-rose-700'; ?>">
                                        <?php echo formatReportMonthYear($row['latest_paid_to']); ?>
                                    </td>
                                    <td class="p-3 text-right font-mono font-bold text-slate-900">
                                        <?php echo number_format($row_amount, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="bg-slate-50/80 font-bold text-sm border-t-2 border-slate-400">
                                <td colspan="7" class="p-4 text-right uppercase tracking-wider text-xs text-slate-500">Grand
                                    Total:</td>
                                <td
                                    class="p-4 text-right text-teal-800 font-mono font-black border-b-4 border-double border-teal-600">
                                    ₹<?php echo number_format($grand_total_print, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 pt-16 border-t border-dashed border-slate-300 flex justify-between items-center text-center">
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Prepared By</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">
                        Treasurer
                    </div>
                </div>
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Verified Authority</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Secretary/President</div>
                </div>
            </div>
        </div>

    </body>

    </html>
    <?php
    exit;
}
?>
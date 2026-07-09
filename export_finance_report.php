<?php
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Boundary Guardrail Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Access Denied: Please authenticate session access to generate master financial reports.");
}

require_once 'db.php';

// 1. Setup Parameters
$date_from = !empty($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-01-01');
$date_to = !empty($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$payment_mode = isset($_GET['payment_mode']) ? trim($_GET['payment_mode']) : 'All';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'print';

// 2. Fetch Income Stream Records (Fee Payments Linked to Student/Course profiles)
$income_where = ["DATE(p.created_at) >= ?", "DATE(p.created_at) <= ?"];
$income_params = [$date_from, $date_to];

if ($payment_mode !== 'All') {
    $income_where[] = "p.payment_mode = ?";
    $income_params[] = $payment_mode;
}

$income_sql = "
    SELECT 
        'INCOME' AS transaction_type,
        p.id AS structural_id,
        p.receipt_no AS tracking_reference,
        p.amount_paid AS numeric_amount,
        p.payment_mode,
        DATE(p.created_at) AS processing_date,
        CONCAT(s.first_name, ' ', s.last_name) AS entity_name,
        c.course_name AS tracking_narrative
    FROM academic_fee_payments p
    INNER JOIN academic_enrollments e ON p.enrollment_id = e.id
    INNER JOIN academic_students s ON e.student_id = s.id
    INNER JOIN academic_courses c ON e.course_id = c.id
    WHERE " . implode(' AND ', $income_where) . "
";

// 3. Fetch Expenditure Outflow Stream Records (Operational Expenses Layer)
$expense_where = ["e.payment_date >= ?", "e.payment_date <= ?"];
$expense_params = [$date_from, $date_to];

if ($payment_mode !== 'All') {
    $expense_where[] = "e.payment_mode = ?";
    $expense_params[] = $payment_mode;
}

$expense_sql = "
    SELECT 
        'EXPENSE' AS transaction_type,
        e.id AS structural_id,
        e.voucher_no AS tracking_reference,
        e.amount_paid AS numeric_amount,
        e.payment_mode,
        e.payment_date AS processing_date,
        e.recipient_name AS entity_name,
        CONCAT(e.expense_category, IF(e.expense_narrative IS NOT NULL, CONCAT(' (', e.expense_narrative, ')'), '')) AS tracking_narrative
    FROM academic_expenses e
    WHERE " . implode(' AND ', $expense_where) . "
";

// Execute Queries
$stmt_inc = $db->prepare($income_sql);
$stmt_inc->execute($income_params);
$income_records = $stmt_inc->fetchAll(PDO::FETCH_ASSOC);

$stmt_exp = $db->prepare($expense_sql);
$stmt_exp->execute($expense_params);
$expense_records = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

// Combine and sort chronologically by date
$master_ledger = array_merge($income_records, $expense_records);
usort($master_ledger, function ($a, $b) {
    return strcmp($a['processing_date'], $b['processing_date']);
});

// Calculate Unified Balance Matrices
$total_income = 0.00;
$total_expense = 0.00;
foreach ($master_ledger as $row) {
    if ($row['transaction_type'] === 'INCOME') {
        $total_income += (float) $row['numeric_amount'];
    } else {
        $total_expense += (float) $row['numeric_amount'];
    }
}
$net_liquidity = $total_income - $total_expense;

// 4. Execution Stream Branching: Excel Output Handler
if ($format === 'excel') {
    $filename = "Academic_Financial_Ledger_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM targeting the Indian Rupee Character (₹)

    echo "<table border='1'>";
    echo "<tr><th colspan='7' style='font-size:16px; font-weight:bold; background-color:#0f172a; color:#ffffff;'>Academic Ledger Balance Statement ({$date_from} to {$date_to})</th></tr>";
    echo "<tr style='background-color: #0284c7; color: #ffffff; font-weight: bold;'>";
    echo "<th>Date</th>";
    echo "<th>Classification</th>";
    echo "<th>Voucher/Receipt No</th>";
    echo "<th>Associated Entity</th>";
    echo "<th>Context Description Ledger Narrative</th>";
    echo "<th>Payment Mode</th>";
    echo "<th>Amount (₹)</th>";
    echo "</tr>";

    foreach ($master_ledger as $row) {
        $is_inc = ($row['transaction_type'] === 'INCOME');
        $style = $is_inc ? "style='color:#15803d;'" : "style='color:#b91c1c;'";
        $prefix = $is_inc ? "+" : "-";

        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['processing_date']) . "</td>";
        echo "<td font-weight:bold;>" . $row['transaction_type'] . "</td>";
        echo "<td>" . htmlspecialchars($row['tracking_reference']) . "</td>";
        echo "<td>" . htmlspecialchars($row['entity_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tracking_narrative']) . "</td>";
        echo "<td>" . htmlspecialchars($row['payment_mode']) . "</td>";
        echo "<td {$style}>{$prefix}₹" . number_format($row['numeric_amount'], 2) . "</td>";
        echo "</tr>";
    }

    echo "<tr style='font-weight: bold; background-color: #f8fafc;'>";
    echo "<td colspan='6' style='text-align: right;'>Total Fee Collections Realized (Inflow):</td>";
    echo "<td style='color:#15803d;'>₹" . number_format($total_income, 2) . "</td>";
    echo "</tr>";
    echo "<tr style='font-weight: bold; background-color: #f8fafc;'>";
    echo "<td colspan='6' style='text-align: right;'>Total Operational Expenditure (Outflow):</td>";
    echo "<td style='color:#b91c1c;'>₹" . number_format($total_expense, 2) . "</td>";
    echo "</tr>";

    $net_bg = ($net_liquidity >= 0) ? "background-color:#dcfce7; color:#15803d;" : "background-color:#fee2e2; color:#b91c1c;";
    echo "<tr style='font-weight: bold; {$net_bg}'>";
    echo "<td colspan='6' style='text-align: right;'>Net Operating Balance Ledger Capital yield:</td>";
    echo "<td>₹" . number_format($net_liquidity, 2) . "</td>";
    echo "</tr>";
    echo "</table>";
    exit;
} else {
    // 5. Execution Stream Branching: Default Highly-Printable Framework Layout View
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Academic Income vs Expense Financial Statement Statement</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }

                body {
                    background-color: #ffffff;
                    padding: 0;
                }

                .print-card {
                    border: none !important;
                    shadow: none !important;
                }
            }
        </style>
    </head>

    <body class="bg-slate-50 p-6 font-sans text-slate-800">

        <div class="max-w-5xl mx-auto bg-white p-8 rounded-2xl shadow-sm border border-slate-200 print-card">

            <div
                class="no-print flex justify-between items-center bg-slate-100 p-4 rounded-xl mb-6 border border-slate-200">
                <p class="text-xs font-semibold text-slate-500">
                    <i class="fa-solid fa-circle-info text-teal-600 mr-1"></i> Unified System Ledger Trace View. Ready for
                    execution print arrays.
                </p>
                <button onclick="window.print();"
                    class="bg-slate-900 hover:bg-black text-white font-bold py-1.5 px-4 rounded-lg text-xs uppercase tracking-wide cursor-pointer transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Execute Local Print Engine
                </button>
            </div>

            <div class="flex justify-between items-start border-b-2 border-slate-900 pb-5">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase">Euro Global Consultancy</h1>
                    <p class="text-xs text-slate-500 font-medium">Academic Management System | Audit Accounting Module</p>
                    <div
                        class="mt-2.5 text-[11px] text-slate-600 bg-slate-50 px-3 py-2 rounded-lg inline-block border border-slate-200 space-y-0.5">
                        <div><b>Temporal Boundary:</b> <span
                                class="underline font-bold"><?php echo htmlspecialchars($date_from); ?></span> to <span
                                class="underline font-bold"><?php echo htmlspecialchars($date_to); ?></span></div>
                        <div><b>Mode Constraining Flag:</b> <span
                                class="badge bg-slate-200 text-slate-800 px-1.5 py-0.5 rounded text-[10px] uppercase font-bold"><?php echo htmlspecialchars($payment_mode); ?></span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Statement Compiled On</h3>
                    <p class="text-sm font-bold text-slate-800 mt-1"><?php echo date('d M Y - h:i A'); ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Operator Execution Handle:
                        <?php echo htmlspecialchars(isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'System Administrator'); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                <div class="bg-emerald-50/60 border border-emerald-200/80 p-4 rounded-xl">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 block">Total Realized Cash
                        Inflows</span>
                    <span
                        class="text-xl font-mono font-black text-emerald-800 block mt-1">₹<?php echo number_format($total_income, 2); ?></span>
                    <span class="text-[9px] text-emerald-500 mt-0.5 block">Student fee payments executed</span>
                </div>
                <div class="bg-rose-50/60 border border-rose-200/80 p-4 rounded-xl">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 block">Total Operational
                        Outflows</span>
                    <span
                        class="text-xl font-mono font-black text-rose-800 block mt-1">₹<?php echo number_format($total_expense, 2); ?></span>
                    <span class="text-[9px] text-rose-500 mt-0.5 block">Voucher provisions liquidated</span>
                </div>
                <div
                    class="<?php echo ($net_liquidity >= 0) ? 'bg-sky-50 border border-sky-200' : 'bg-amber-50 border border-amber-200'; ?> p-4 rounded-xl">
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider <?php echo ($net_liquidity >= 0) ? 'text-sky-700' : 'text-amber-700'; ?> block">Net
                        Operating Balance</span>
                    <span
                        class="text-xl font-mono font-black <?php echo ($net_liquidity >= 0) ? 'text-sky-900' : 'text-amber-900'; ?> block mt-1">₹<?php echo number_format($net_liquidity, 2); ?></span>
                    <span
                        class="text-[9px] <?php echo ($net_liquidity >= 0) ? 'text-sky-600' : 'text-amber-600'; ?> mt-0.5 block">Net
                        yield matrix liquidity</span>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b-2 border-slate-400 bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[10px]">
                            <th class="p-3">Processing Date</th>
                            <th class="p-3">Classification</th>
                            <th class="p-3">Reference Token</th>
                            <th class="p-3">Target Entity Profile Name</th>
                            <th class="p-3">Context Ledger Narrative / Course</th>
                            <th class="p-3">Payment Framework</th>
                            <th class="p-3 text-right">Delta Yield Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (count($master_ledger) === 0): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">Zero ledger matching records exist
                                    inside specified operational bounds parameters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($master_ledger as $row):
                                $is_income = ($row['transaction_type'] === 'INCOME');
                                ?>
                                <tr class="hover:bg-slate-50/40 transition-colors">
                                    <td class="p-3 font-mono text-slate-600">
                                        <?php echo htmlspecialchars($row['processing_date']); ?></td>
                                    <td class="p-3 font-bold">
                                        <span
                                            class="px-2 py-0.5 rounded text-[9px] font-extrabold tracking-wide uppercase <?php echo $is_income ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'; ?>">
                                            <?php echo $row['transaction_type']; ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-slate-900">
                                        <?php echo htmlspecialchars($row['tracking_reference']); ?></td>
                                    <td class="p-3 font-semibold text-slate-800">
                                        <?php echo htmlspecialchars($row['entity_name']); ?></td>
                                    <td class="p-3 text-slate-600 max-w-xs truncate"
                                        title="<?php echo htmlspecialchars($row['tracking_narrative']); ?>">
                                        <?php echo htmlspecialchars($row['tracking_narrative']); ?>
                                    </td>
                                    <td class="p-3 text-slate-600 font-medium"><?php echo htmlspecialchars($row['payment_mode']); ?>
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono font-bold <?php echo $is_income ? 'text-emerald-700' : 'text-rose-700'; ?>">
                                        <?php echo ($is_income ? '+' : '-') . number_format($row['numeric_amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 pt-12 border-t border-dashed border-slate-300 flex justify-between items-center text-center">
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Prepared Accountancy Handle</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Program Operations Controller</div>
                </div>
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Verified System Auditor</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Managing Consultant Signature</div>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}
?>
<?php
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Access Denied: Please authenticate session access to generate system profile matrices.");
}

require_once 'db.php';

// Capture Parameters
$is_active = isset($_GET['is_active']) ? trim($_GET['is_active']) : 'All';
$duration_unit = isset($_GET['duration_unit']) ? trim($_GET['duration_unit']) : 'All';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'print';

$where_clauses = ["1=1"];
$params = [];

if ($is_active !== 'All') {
    $where_clauses[] = "c.is_active = ?";
    $params[] = (int) $is_active;
}

if ($duration_unit !== 'All') {
    $where_clauses[] = "c.duration_unit = ?";
    $params[] = $duration_unit;
}

$query_string = "
    SELECT 
        c.*,
        COUNT(e.id) AS total_allocations,
        SUM(CASE WHEN e.status = 'ongoing' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN e.status IN ('dropped', 'cancelled') THEN 1 ELSE 0 END) AS attrition_count,
        COALESCE((
            SELECT SUM(p.amount_paid)
            FROM academic_fee_payments p
            INNER JOIN academic_enrollments ae ON p.enrollment_id = ae.id
            WHERE ae.course_id = c.id
        ), 0.00) AS total_revenue_realized
    FROM academic_courses c
    LEFT JOIN academic_enrollments e ON e.course_id = c.id
    WHERE " . implode(' AND ', $where_clauses) . "
    GROUP BY c.id
    ORDER BY c.course_code ASC
";

$stmt = $db->prepare($query_string);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'excel') {
    $filename = "Course_Yield_Report_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");

    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr><th colspan='10' style='font-size:16px; font-weight:bold; background-color:#78350f; color:#ffffff;'>Academic Curriculum & Yield Metrics</th></tr>";
    echo "<tr style='background-color: #b45309; color: #ffffff; font-weight: bold;'>";
    echo "<th>Course Code</th>";
    echo "<th>Course Name</th>";
    echo "<th>Standard Base Fee</th>";
    echo "<th>Duration Value</th>";
    echo "<th>Duration Unit</th>";
    echo "<th>Total Allocations</th>";
    echo "<th>Active Tracks</th>";
    echo "<th>Completed (Graduated)</th>";
    echo "<th>Attrition Tracks (Drop/Cancel)</th>";
    echo "<th>Total Collected Revenue (₹)</th>";
    echo "</tr>";

    foreach ($courses as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['course_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>₹" . number_format($row['standard_fee'], 2) . "</td>";
        echo "<td>" . $row['duration_value'] . "</td>";
        echo "<td>" . $row['duration_unit'] . "</td>";
        echo "<td>" . $row['total_allocations'] . "</td>";
        echo "<td>" . $row['active_count'] . "</td>";
        echo "<td>" . $row['completed_count'] . "</td>";
        echo "<td>" . $row['attrition_count'] . "</td>";
        echo "<td>₹" . number_format($row['total_revenue_realized'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Academic Program Yield Ledger</title>
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
            }
        </style>
    </head>

    <body class="bg-slate-50 p-6 font-sans text-slate-800">
        <div class="max-w-5xl mx-auto bg-white p-8 rounded-2xl shadow-sm border border-slate-200">

            <div
                class="no-print flex justify-between items-center bg-slate-100 p-4 rounded-xl mb-6 border border-slate-200">
                <p class="text-xs font-semibold text-slate-500">
                    <i class="fa-solid fa-book-bookmark text-amber-600 mr-1"></i> Curriculum Distribution Audit Log. Ready
                    for administrative evaluations.
                </p>
                <button onclick="window.print();"
                    class="bg-slate-900 hover:bg-black text-white font-bold py-1.5 px-4 rounded-lg text-xs uppercase tracking-wide cursor-pointer transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Execute Local Print Engine
                </button>
            </div>

            <div class="flex justify-between items-start border-b-2 border-slate-900 pb-5">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase">Euro Global Consultancy</h1>
                    <p class="text-xs text-slate-500 font-medium">Academic Management System | Program Yield Engine</p>
                    <div
                        class="mt-2.5 text-[11px] text-slate-600 bg-slate-50 px-3 py-2 rounded-lg inline-block border border-slate-200 space-y-0.5">
                        <div><b>Filters:</b> Activation Scope: <span class="font-bold underline">
                                <?php echo $is_active === 'All' ? 'All Layouts' : ($is_active === '1' ? 'Active Only' : 'Inactive Only'); ?>
                            </span></div>
                        <div>Duration Scale: <span
                                class="badge bg-slate-200 text-slate-800 px-1.5 py-0.5 rounded text-[10px] font-bold">
                                <?php echo htmlspecialchars($duration_unit); ?>
                            </span></div>
                    </div>
                </div>
                <div class="text-right">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Statement Compiled On</h3>
                    <p class="text-sm font-bold text-slate-800 mt-1">
                        <?php echo date('d M Y - h:i A'); ?>
                    </p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Active Tracks Accounted: <span
                            class="font-bold font-mono text-slate-900">
                            <?php echo count($courses); ?>
                        </span></p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b-2 border-slate-400 bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[10px]">
                            <th class="p-3">Course Code</th>
                            <th class="p-3">Program Title Description</th>
                            <th class="p-3 text-right">Standard Base Fee</th>
                            <th class="p-3 text-center">Duration Metric</th>
                            <th class="p-3 text-center">Allocations</th>
                            <th class="p-3 text-center">Active / Graduated</th>
                            <th class="p-3 text-center">Attrition Rate</th>
                            <th class="p-3 text-right">Realized Cash yield</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (count($courses) === 0): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-400 italic">No course footprints matched
                                    chosen configuration profiles.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $grand_revenue = 0.00;
                            foreach ($courses as $row):
                                $grand_revenue += (float) $row['total_revenue_realized'];
                                $attrition_rate = $row['total_allocations'] > 0 ? ($row['attrition_count'] / $row['total_allocations']) * 100 : 0;
                                ?>
                                <tr class="hover:bg-slate-50/40 transition-colors">
                                    <td class="p-3 font-mono font-bold text-slate-900">
                                        <?php echo htmlspecialchars($row['course_code']); ?>
                                    </td>
                                    <td class="p-3 font-bold text-slate-800">
                                        <?php echo htmlspecialchars($row['course_name']); ?>
                                        <?php if ((int) $row['is_active'] === 0): ?>
                                            <span
                                                class="ml-1 bg-slate-100 text-slate-500 text-[9px] px-1.5 py-0.5 rounded font-bold uppercase">Legacy</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-right font-mono font-medium text-slate-700">₹
                                        <?php echo number_format($row['standard_fee'], 2); ?>
                                    </td>
                                    <td class="p-3 text-center text-slate-600 font-medium">
                                        <?php echo $row['duration_value'] . ' ' . $row['duration_unit']; ?>
                                    </td>
                                    <td class="p-3 text-center font-bold font-mono text-slate-800">
                                        <?php echo $row['total_allocations']; ?>
                                    </td>
                                    <td class="p-3 text-center font-mono text-slate-600">
                                        <span class="text-emerald-700 font-bold">
                                            <?php echo $row['active_count']; ?>
                                        </span> / <span class="text-blue-700 font-bold">
                                            <?php echo $row['completed_count']; ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-center font-mono">
                                        <div
                                            class="font-bold <?php echo $attrition_rate > 30 ? 'text-rose-700' : 'text-slate-500'; ?>">
                                            <?php echo number_format($attrition_rate, 1); ?>%
                                        </div>
                                        <div class="text-[9px] text-slate-400">
                                            <?php echo $row['attrition_count']; ?> dropouts
                                        </div>
                                    </td>
                                    <td class="p-3 text-right font-mono font-black text-slate-900">₹
                                        <?php echo number_format($row['total_revenue_realized'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="bg-slate-50/80 font-bold text-sm border-t-2 border-slate-400">
                                <td colspan="7" class="p-4 text-right uppercase tracking-wider text-xs text-slate-500">Gross
                                    Module Capital Inflow Realized:</td>
                                <td
                                    class="p-4 text-right text-slate-900 font-mono font-black border-b-4 border-double border-slate-900">
                                    ₹
                                    <?php echo number_format($grand_revenue, 2); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 pt-12 border-t border-dashed border-slate-300 flex justify-between items-center text-center">
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Curriculum Review Officer</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Academic Dean Registry</div>
                </div>
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Authorized Comptroller</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Chief Executive Sign-Off</div>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}
?>
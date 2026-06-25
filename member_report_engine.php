<?php
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

function calculateAge($dob)
{
    if (empty($dob) || $dob === '0000-00-00')
        return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('now');
    return $birthDate->diff($today)->y;
}

// Extract parameters
$search = $_GET['search'] ?? '';
$mahallah = $_GET['mahallah'] ?? 'All';
$status = $_GET['status'] ?? 'All';
$chanda = $_GET['chanda'] ?? 'All';
$designation = $_GET['designation'] ?? 'All';
$occupation = $_GET['occupation'] ?? 'All';
$format = $_GET['format'] ?? '';

$whereClauses = [];
$params = [];

// Text Search wildcards across all specified columns
if (trim($search) !== '') {
    $whereClauses[] = "(first_name LIKE :search OR last_name LIKE :search OR family_name LIKE :search OR father_husband_name LIKE :search OR card_no LIKE :search)";
    $params[':search'] = '%' . trim($search) . '%';
}

if ($mahallah !== 'All') {
    $whereClauses[] = "mahallah = :mahallah";
    $params[':mahallah'] = $mahallah;
}
if ($status !== 'All') {
    $whereClauses[] = "status = :status";
    $params[':status'] = $status;
}
if ($chanda !== 'All') {
    $whereClauses[] = "chanda_status = :chanda";
    $params[':chanda'] = $chanda;
}
if ($designation !== 'All') {
    $whereClauses[] = "designation = :designation";
    $params[':designation'] = $designation;
}
if ($occupation !== 'All') {
    $whereClauses[] = "occupation = :occupation";
    $params[':occupation'] = $occupation;
}

$whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";
$rows = [];
$reportTitle = "Customized Members Registry";

try {
    $queryStr = "SELECT card_no, first_name, last_name, family_name, father_husband_name, dob, phone, mahallah, occupation, designation, status, chanda_status FROM members" . $whereSql . " ORDER BY card_no ASC";
    $stmt = $db->prepare($queryStr);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("<div style='padding:20px; font-family:sans-serif; color:#b91c1c;'>Database Error Encountered: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// ==================== EXECUTIVE NATIVE EXCEL GENERATION ENGINE ====================
if ($format === 'excel') {
    $filename = "Jamaath_Members_Registry_Report_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM representation

    echo "<table border='1'>";
    echo "<tr><th colspan='9' style='background-color: #0f766e; color: #ffffff; font-size: 14px; font-weight: bold; text-align: center;'>NVK MUSLIM JAMAATH REGISTRY</th></tr>";
    echo "<tr><th colspan='9' style='background-color: #f8fafc; color: #1e293b; font-size: 11px; font-weight: bold; text-align: center;'>$reportTitle</th></tr>";

    echo "<tr style='background-color: #0f766e; color: #ffffff; font-weight: bold;'>";
    echo "<th>Card No</th>";
    echo "<th>Full Name (House/Family Name)</th>";
    echo "<th>Father / Husband Name</th>";
    echo "<th>Age</th>";
    echo "<th>Mahallah Location</th>";
    echo "<th>Phone Contact</th>";
    echo "<th>Occupation Profile</th>";
    echo "<th>Role Designation</th>";
    echo "<th>Chanda Status</th>";
    echo "</tr>";

    foreach ($rows as $row) {
        $fullName = $row['first_name'] . ' ' . $row['last_name'];
        if (!empty($row['family_name'])) {
            $fullName .= ' (' . $row['family_name'] . ')';
        }
        echo "<tr>";
        echo "<td style='font-family: monospace; font-weight: bold; text-align: center;'>" . htmlspecialchars($row['card_no']) . "</td>";
        echo "<td>" . htmlspecialchars($fullName) . "</td>";
        echo "<td>" . htmlspecialchars($row['father_husband_name']) . "</td>";
        echo "<td style='text-align: center;'>" . calculateAge($row['dob']) . "</td>";
        echo "<td>" . htmlspecialchars($row['mahallah']) . "</td>";

        // FIX: Use mso-number-format text flag to keep the clean value without formula code leaking
        echo "<td style='mso-number-format:\"@\"; text-align: left;'>" . htmlspecialchars($row['phone']) . "</td>";

        echo "<td>" . htmlspecialchars($row['occupation'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
        echo "<td style='text-align: center; font-weight: bold;'>" . htmlspecialchars($row['chanda_status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Jamaath Registry Analytical Statement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .print\:hidden {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased p-8 print:bg-white print:p-0">

    <div id="action-bar"
        class="max-w-6xl mx-auto mb-6 p-4 bg-white border border-slate-200 rounded-xl flex items-center justify-between shadow-xs print:hidden">
        <div class="text-xs text-slate-500 font-medium">
            <i class="fa-solid fa-circle-info text-teal-600 mr-1"></i> Review registered dataset information lines below
            before export processing routines.
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print();"
                class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-sm cursor-pointer flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i> Print Layout View
            </button>
            <a href="?search=<?php echo urlencode($search); ?>&mahallah=<?php echo urlencode($mahallah); ?>&status=<?php echo urlencode($status); ?>&chanda=<?php echo urlencode($chanda); ?>&designation=<?php echo urlencode($designation); ?>&occupation=<?php echo urlencode($occupation); ?>&format=excel"
                class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-sm cursor-pointer flex items-center gap-1.5">
                <i class="fa-solid fa-file-excel"></i> Export Native Excel Sheet
            </a>
        </div>
    </div>

    <div
        class="max-w-6xl mx-auto bg-white border border-slate-200 rounded-2xl p-8 shadow-sm print:border-none print:shadow-none print:p-0">

        <div class="border-b-2 border-slate-900 pb-5 mb-6 flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">NVK MUSLIM JAMAATH REGISTRY</h1>
                <p class="text-xs font-bold text-teal-800 mt-0.5 uppercase tracking-wider">Analytical Community
                    Documentation Terminal</p>
            </div>
            <div class="text-right text-xs text-slate-400 font-medium">
                <div>Generated: <?php echo date('d M Y - h:i A'); ?></div>
                <div class="font-mono text-[10px] mt-0.5">Report Generated by:
                    <?php echo htmlspecialchars(isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'System Administrator'); ?>
                </div>
            </div>
        </div>

        <div
            class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4 mb-6 border border-slate-100 text-xs font-semibold text-slate-700 print:bg-transparent print:border-none">
            <div>
                <div class="text-[10px] uppercase text-slate-400 tracking-wider font-bold mb-0.5">Report Compilation
                    Category</div>
                <div class="text-slate-900 text-sm font-extrabold"><?php echo $reportTitle; ?></div>
            </div>
            <div>
                <div class="text-[10px] uppercase text-slate-400 tracking-wider font-bold mb-0.5">Applied Selection
                    Profile Bounds</div>
                <div class="text-slate-600 font-mono text-[11px] leading-relaxed">
                    Mahallah: <span class="text-slate-900 font-bold"><?php echo htmlspecialchars($mahallah); ?></span> |
                    Status: <span class="text-slate-900 font-bold"><?php echo htmlspecialchars($status); ?></span><br>
                    Role: <span class="text-slate-900 font-bold"><?php echo htmlspecialchars($designation); ?></span> |
                    Work: <span class="text-slate-900 font-bold"><?php echo htmlspecialchars($occupation); ?></span>
                    <?php if ($search !== ''): ?>
                        <br>Keyword: <span class="text-rose-700 font-bold">"<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-[10px] uppercase text-slate-400 tracking-wider font-bold mb-0.5">Total Matching Core
                    Records</div>
                <div class="text-teal-950 text-base font-black">
                    <?php echo count($rows); ?> <span class="text-xs font-normal text-slate-400">Members Filtered</span>
                </div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div
                class="text-center py-12 text-slate-400 text-xs font-medium border border-dashed border-slate-200 rounded-xl">
                No registered members matching current customized parameters were logged inside system tables.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-100/60 text-[10px] uppercase font-bold text-slate-500 tracking-wider print:bg-transparent">
                            <th class="py-3 px-4">Card No</th>
                            <th class="py-3 px-4">Full Identity Name</th>
                            <th class="py-3 px-4">Father / Husband Name</th>
                            <th class="py-3 px-4 text-center">Age</th>
                            <th class="py-3 px-4">Mahallah Location</th>
                            <th class="py-3 px-4">Phone Number</th>
                            <th class="py-3 px-4">Occupation Profile</th>
                            <th class="py-3 px-4">Designation Status Role</th>
                            <th class="py-3 px-4 text-center">Chanda Account</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                    <?php echo htmlspecialchars($row['card_no']); ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    <?php
                                    echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                                    if (!empty($row['family_name'])) {
                                        echo ' <span class="text-slate-400 font-normal">(' . htmlspecialchars($row['family_name']) . ')</span>';
                                    }
                                    ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?php echo htmlspecialchars($row['father_husband_name']); ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-slate-900">
                                    <?php echo calculateAge($row['dob']); ?>
                                </td>
                                <td class="py-3 px-4 text-slate-500"><?php echo htmlspecialchars($row['mahallah']); ?></td>
                                <td class="py-3 px-4 text-slate-500 font-mono"><?php echo htmlspecialchars($row['phone']); ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?php echo htmlspecialchars($row['occupation'] ?: 'N/A'); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo ($row['designation'] === 'Ordinary Member') ? 'bg-slate-50 text-slate-600 border-slate-200' : 'bg-teal-50 text-teal-800 border-teal-200 uppercase'; ?>">
                                        <?php echo htmlspecialchars($row['designation']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo ($row['chanda_status'] === 'Paid') ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'; ?>">
                                        <?php echo htmlspecialchars($row['chanda_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        if (window.self !== window.top) {
            const bar = document.getElementById('action-bar');
            if (bar) bar.style.display = 'none';
        }
    </script>
</body>

</html>
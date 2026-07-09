<?php
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Access Denied: Please authenticate session access to generate master profile reports.");
}

require_once 'db.php';

// Capture Parameters
$status = isset($_GET['status']) ? trim($_GET['status']) : 'All';
$study_level = isset($_GET['study_level']) ? trim($_GET['study_level']) : '';
$jamaath_status = isset($_GET['jamaath_status']) ? trim($_GET['jamaath_status']) : 'All';
$gender = isset($_GET['gender']) ? trim($_GET['gender']) : 'All';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'print';

// Construct Dynamic Processing Chains
$where_clauses = ["1=1"];
$params = [];

if ($status !== 'All') {
    if ($status === 'CRITICAL_PAUSE') {
        $where_clauses[] = "e.resume_count = 2 AND e.status IN ('ongoing', 'hold')";
    } else {
        $where_clauses[] = "e.status = ?";
        $params[] = $status;
    }
}

if (!empty($study_level)) {
    $where_clauses[] = "s.study_level LIKE ?";
    $params[] = "%" . $study_level . "%";
}

if ($jamaath_status !== 'All') {
    $where_clauses[] = "s.jamaath_status = ?";
    $params[] = $jamaath_status;
}

if ($gender !== 'All') {
    $where_clauses[] = "s.gender = ?";
    $params[] = $gender;
}

$query_string = "
    SELECT 
        s.student_reg_no,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
        s.email,
        s.student_phone,
        s.gender,
        s.study_level,
        s.jamaath_status,
        c.course_code,
        c.course_name,
        e.status AS enrollment_status,
        e.resume_count,
        e.accumulated_pause_days
    FROM academic_students s
    INNER JOIN academic_enrollments e ON e.student_id = s.id
    INNER JOIN academic_courses c ON e.course_id = c.id
    WHERE " . implode(' AND ', $where_clauses) . "
    ORDER BY s.student_reg_no ASC
";

$stmt = $db->prepare($query_string);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatter helper for context labels
function getStatusBadgeStyle($status, $resume_count)
{
    if ($resume_count >= 2 && ($status === 'ongoing' || $status === 'hold')) {
        return "background-color: #fef2f2; color: #991b1b; font-weight: bold; border: 1px solid #fee2e2;";
    }
    switch ($status) {
        case 'assigned':
            return "background-color: #f1f5f9; color: #475569;";
        case 'ongoing':
            return "background-color: #ecfdf5; color: #065f46;";
        case 'hold':
            return "background-color: #fffbeb; color: #92400e;";
        case 'completed':
            return "background-color: #eff6ff; color: #1e40af;";
        case 'dropped':
            return "background-color: #f8fafc; color: #64748b; text-decoration: line-through;";
        case 'cancelled':
            return "background-color: #fff5f5; color: #c53030;";
        default:
            return "background-color: #f1f5f9; color: #334155;";
    }
}

// Binary Excel Sheet Layout Execution Generator
if ($format === 'excel') {
    $filename = "Student_Cohort_Report_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");

    echo "\xEF\xBB\xBF"; // Unicode UTF-8 BOM
    echo "<table border='1'>";
    echo "<tr><th colspan='11' style='font-size:16px; font-weight:bold; background-color:#1e293b; color:#ffffff;'>Student Lifecycle & Cohort Statement</th></tr>";
    echo "<tr style='background-color: #0369a1; color: #ffffff; font-weight: bold;'>";
    echo "<th>Reg No</th>";
    echo "<th>Student Full Name</th>";
    echo "<th>Email Address</th>";
    echo "<th>Phone Number</th>";
    echo "<th>Gender</th>";
    echo "<th>Study Level</th>";
    echo "<th>Jamaath Relation</th>";
    echo "<th>Course Target Code</th>";
    echo "<th>Allocated Course Title</th>";
    echo "<th>Track Status</th>";
    echo "<th>Resumption Modifications Counter</th>";
    echo "</tr>";

    foreach ($records as $row) {
        $flag = ($row['resume_count'] >= 2 && ($row['enrollment_status'] === 'ongoing' || $row['enrollment_status'] === 'hold')) ? " (CRITICAL LOCKOUT RISK)" : "";
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['student_reg_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_phone']) . "</td>";
        echo "<td>" . htmlspecialchars($row['gender']) . "</td>";
        echo "<td>" . htmlspecialchars($row['study_level']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jamaath_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>" . strtoupper($row['enrollment_status']) . $flag . "</td>";
        echo "<td>" . $row['resume_count'] . " / 2</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
} else {
    // Default Clean Printable Framework View
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Student Cohort Statement Registry</title>
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
                    <i class="fa-solid fa-graduation-cap text-blue-600 mr-1"></i> Cohort Data Allocation Sheet. Prepared for
                    document signing structures.
                </p>
                <button onclick="window.print();"
                    class="bg-slate-900 hover:bg-black text-white font-bold py-1.5 px-4 rounded-lg text-xs uppercase tracking-wide cursor-pointer transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Execute Local Print Engine
                </button>
            </div>

            <div class="flex justify-between items-start border-b-2 border-slate-900 pb-5">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase">Euro Global Consultancy</h1>
                    <p class="text-xs text-slate-500 font-medium">Academic Management System | Student Cohort Registries</p>
                    <div
                        class="mt-2.5 text-[11px] text-slate-600 bg-slate-50 px-3 py-2 rounded-lg inline-block border border-slate-200 space-y-0.5">
                        <div><b>Active Query Constraints:</b> Status: <span class="font-bold underline">
                                <?php echo htmlspecialchars($status); ?>
                            </span> | Level: <span class="font-bold underline">
                                <?php echo htmlspecialchars(empty($study_level) ? 'All' : $study_level); ?>
                            </span></div>
                        <div>Jamaath Sector: <span
                                class="badge bg-slate-200 text-slate-800 px-1.5 py-0.5 rounded text-[10px] font-bold">
                                <?php echo htmlspecialchars($jamaath_status); ?>
                            </span></div>
                    </div>
                </div>
                <div class="text-right">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Statement Compiled On</h3>
                    <p class="text-sm font-bold text-slate-800 mt-1">
                        <?php echo date('d M Y - h:i A'); ?>
                    </p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Records Returned: <span
                            class="font-bold font-mono text-slate-900">
                            <?php echo count($records); ?>
                        </span></p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b-2 border-slate-400 bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[10px]">
                            <th class="p-3">Reg Identifier</th>
                            <th class="p-3">Student Name</th>
                            <th class="p-3">Contact Metrics</th>
                            <th class="p-3">Study Level / Gender</th>
                            <th class="p-3">Course Allocation</th>
                            <th class="p-3 text-center">Ceiling Modifications</th>
                            <th class="p-3 text-right">Lifecycle Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (count($records) === 0): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">No historical student profiles
                                    match specified workspace parameters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $row):
                                $is_at_ceiling = ($row['resume_count'] >= 2 && ($row['enrollment_status'] === 'ongoing' || $row['enrollment_status'] === 'hold'));
                                ?>
                                <tr
                                    class="hover:bg-slate-50/40 transition-colors <?php echo $is_at_ceiling ? 'bg-rose-50/40' : ''; ?>">
                                    <td class="p-3 font-mono font-bold text-slate-900">
                                        <?php echo htmlspecialchars($row['student_reg_no']); ?>
                                    </td>
                                    <td class="p-3 font-bold text-slate-800">
                                        <?php echo htmlspecialchars($row['student_name']); ?>
                                        <?php if ($is_at_ceiling): ?>
                                            <span class="block text-[9px] font-extrabold text-rose-700 tracking-wide"><i
                                                    class="fa-solid fa-triangle-exclamation animate-bounce"></i> LOCKOUT RISK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 font-mono text-slate-600 space-y-0.5">
                                        <div class="text-[11px]">
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            <?php echo htmlspecialchars($row['student_phone'] ?? 'No Phone'); ?>
                                        </div>
                                    </td>
                                    <td class="p-3 font-medium text-slate-700">
                                        <div>
                                            <?php echo htmlspecialchars($row['study_level']); ?>
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            <?php echo htmlspecialchars($row['gender']); ?> •
                                            <?php echo htmlspecialchars($row['jamaath_status']); ?>
                                        </div>
                                    </td>
                                    <td class="p-3 text-slate-600 max-w-xs">
                                        <div class="font-bold text-slate-700">
                                            <?php echo htmlspecialchars($row['course_code']); ?>
                                        </div>
                                        <div class="truncate text-[11px]">
                                            <?php echo htmlspecialchars($row['course_name']); ?>
                                        </div>
                                    </td>
                                    <td
                                        class="p-3 text-center font-mono font-bold <?php echo ($row['resume_count'] >= 2) ? 'text-rose-700' : 'text-slate-600'; ?>">
                                        <?php echo htmlspecialchars($row['resume_count']); ?> / 2
                                        <div class="text-[9px] font-medium text-slate-400">
                                            <?php echo $row['accumulated_pause_days']; ?> pause days
                                        </div>
                                    </td>
                                    <td class="p-3 text-right">
                                        <span
                                            class="px-2 py-0.5 rounded text-[9px] font-extrabold tracking-wide uppercase inline-block"
                                            style="<?php echo getStatusBadgeStyle($row['enrollment_status'], $row['resume_count']); ?>">
                                            <?php echo htmlspecialchars($row['enrollment_status']); ?>
                                        </span>
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
                        Compiled Registrar Profile</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Student Admissions Clerk</div>
                </div>
                <div class="w-48">
                    <div
                        class="border-t border-slate-400 pt-2 text-[10px] uppercase font-bold tracking-wider text-slate-500">
                        Verified Board Authority</div>
                    <div class="text-xs font-semibold text-slate-400 mt-1">Managing Director Verification</div>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}
?>
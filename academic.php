<?php
require_once 'db.php';
require_once 'helpers.php';

// Handle pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'courses';

// Fetch all active tracks using PDO pattern
$courses_query = $db->query("SELECT * FROM academic_courses ORDER BY course_code ASC");
$courses = $courses_query->fetchAll(PDO::FETCH_ASSOC);

// Include the standard system navigation layouts
include_once 'header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 min-h-screen text-slate-800">

    <div
        class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2 tracking-tight">
                Academic Management Module
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Centralized repository for courses configuration, student profiles,
                active enrollments, and tuition fee collection tracking.</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 p-1.5 rounded-lg select-none">
        <button onclick="switchAcademicTab('courses')" id="tab-btn-courses"
            class="px-4 py-2 rounded-md font-medium text-xs uppercase tracking-wider transition-all cursor-pointer">
            Course Catalog
        </button>
        <button onclick="switchAcademicTab('students')" id="tab-btn-students"
            class="px-4 py-2 rounded-md font-medium text-xs uppercase tracking-wider transition-all cursor-pointer">
            Student Directory
        </button>
        <button onclick="switchAcademicTab('registrations')" id="tab-btn-registrations"
            class="px-4 py-2 rounded-md font-medium text-xs uppercase tracking-wider transition-all cursor-pointer">
            Course Enrollments
        </button>
        <button onclick="switchAcademicTab('fees')" id="tab-btn-fees"
            class="px-4 py-2 rounded-md font-medium text-xs uppercase tracking-wider transition-all cursor-pointer">
            Fee Collection
        </button>
        <button onclick="switchAcademicTab('reports')" id="tab-btn-reports"
            class="px-4 py-2 rounded-md font-medium text-xs uppercase tracking-wider transition-all cursor-pointer">
            Reports & Analytics
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm transition-all duration-200">
        <div id="academic-panel-courses" class="academic-tab-content hidden space-y-4">
            <div
                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">
                        Configured Academic Course Registry
                    </h2>
                    <p class="text-xs text-slate-500">Add or manage institutional course specifications and standard
                        tuition fee models.</p>
                </div>
                <button onclick="openCourseFormModal()"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg transition-all shadow-sm flex items-center gap-1.5 cursor-pointer select-none">
                    Add New Course
                </button>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-700 tracking-wider uppercase select-none sticky top-0">
                                <th class="px-6 py-4 w-36">Course Code</th>
                                <th class="px-6 py-4">Course/Specification Name</th>
                                <th class="px-6 py-4 text-right w-48">Standard Fee (₹)</th>
                                <th class="px-6 py-4 w-44 text-center">Status</th>
                                <th class="px-6 py-4 w-32 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic bg-white">
                                        No course tracks configured inside the registry catalog yet.
                                    </td>
                                </tr>
                                <?php
                            else:
                                foreach ($courses as $index => $c):
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4 font-mono font-bold text-slate-900">
                                            <?php echo htmlspecialchars($c['course_code']); ?>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-slate-800">
                                            <?php echo htmlspecialchars($c['course_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-semibold font-mono text-slate-900">
                                            <?php echo number_format($c['standard_fee'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ((int) $c['is_active'] === 1): ?>
                                                <span
                                                    class="inline-flex items-center bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase select-none">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center bg-slate-100 text-slate-500 border border-slate-200 text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase select-none">
                                                    Suspended
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4.5 text-right pr-8">
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <button type="button" title="Edit Course"
                                                    onclick='populateCourseEdit(<?php echo json_encode($c); ?>)'
                                                    class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200 text-xs transition-colors">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <form method="POST" action="actions.php"
                                                    onsubmit="return confirm('Are you sure you want to permanently delete course track [<?php echo htmlspecialchars($c['course_code'], ENT_QUOTES); ?>]? This cannot be undone.');"
                                                    class="inline-flex">
                                                    <input type="hidden" name="action" value="delete_course">
                                                    <input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>">
                                                    <button type="submit"
                                                        class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors"
                                                        title="Delete Course">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="academic-panel-students" class="academic-tab-content hidden space-y-4">

            <div
                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">
                        Student Registry
                    </h2>
                    <p class="text-xs text-slate-500">
                        Manage base demographics data profiles, program levels, and vital emergency contact parameters
                        for students.
                    </p>
                </div>
                <button onclick="openStudentFormModal()"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg transition-all shadow-sm flex items-center gap-1.5 cursor-pointer select-none">
                    Register New Student
                </button>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-700 tracking-wider uppercase select-none sticky top-0">
                                <th class="px-6 py-4 w-44">Registration No</th>
                                <th class="px-6 py-4">Student Name</th>
                                <th class="px-6 py-4">Study Level / Specification</th>
                                <th class="px-6 py-4 w-48">Primary Contact</th>
                                <th class="px-6 py-4 w-32 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                            <?php
                            // Fetch dynamic student directory matrix records
                            $students_query = $db->query("SELECT * FROM academic_students ORDER BY student_reg_no DESC");
                            $students = $students_query->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($students)):
                                ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic bg-white">
                                        No active student files cataloged inside the directory database registry yet.
                                    </td>
                                </tr>
                                <?php
                            else:
                                foreach ($students as $s):
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <!-- Registration & Photo Grid -->
                                        <td class="px-6 py-4 font-mono text-xs font-bold text-slate-700 tracking-wide">
                                            <?php echo htmlspecialchars($s['student_reg_no']); ?>
                                        </td>

                                        <!-- Student Name Only Cell -->
                                        <td class="px-6 py-4 font-medium text-slate-800">
                                            <span onclick='triggerStudentProfileView(<?php echo json_encode($s); ?>)'
                                                class="font-bold text-slate-900 block hover:text-emerald-700 cursor-pointer transition-colors">
                                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                            </span>
                                        </td>

                                        <!-- Program Levels Placement Cell -->
                                        <td class="px-6 py-4 text-xs text-slate-600">
                                            <span
                                                class="inline-block bg-slate-100 text-slate-700 font-bold px-2 py-0.5 rounded-md uppercase text-[10px] tracking-wider mr-1.5"><?php echo htmlspecialchars($s['study_level']); ?></span>
                                            <?php echo htmlspecialchars($s['study_specification'] ?? 'N/A'); ?>
                                        </td>

                                        <!-- Primary Contact: Father Full Profile Target -->
                                        <td class="px-6 py-4 text-xs text-slate-700">
                                            <div class="font-bold text-slate-900">
                                                <?php echo htmlspecialchars($s['father_name']); ?>
                                            </div>
                                            <div class="text-slate-500 font-mono mt-0.5 flex items-center gap-1">
                                                <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                                <?php echo htmlspecialchars($s['father_phone']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4.5 text-center">
                                            <div class="inline-flex items-center justify-center gap-1.5">
                                                <button type="button" title="View Full Profile"
                                                    onclick='triggerStudentProfileView(<?php echo json_encode($s); ?>)'
                                                    class="bg-white text-emerald-600 w-8 h-8 rounded-lg border border-slate-200 hover:border-emerald-200 text-xs transition-colors cursor-pointer select-none flex items-center justify-center shadow-2xs">
                                                    <i class="fa-solid fa-id-card-clip"></i>
                                                </button>

                                                <button type="button" title="Edit Student Profile"
                                                    onclick='populateStudentEdit(<?php echo json_encode($s); ?>)'
                                                    class="bg-emerald-50 hover:bg-teal-50 text-teal-600 w-8 h-8 rounded-lg border border-slate-200 hover:border-emerald-200 text-xs transition-colors cursor-pointer select-none flex items-center justify-center shadow-2xs">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <form method="POST" action="actions.php"
                                                    onsubmit="return confirm('Are you sure you want to permanently delete profile records for [<?php echo htmlspecialchars($s['student_reg_no'], ENT_QUOTES); ?>]? This cannot be undone.');"
                                                    class="inline-flex m-0 p-0">
                                                    <input type="hidden" name="action" value="delete_student">
                                                    <input type="hidden" name="student_id"
                                                        value="<?php echo (int) $s['id']; ?>">
                                                    <button type="submit" title="Delete Student Profile"
                                                        class="bg-rose-50 hover:bg-rose-50 text-rose-500 hover:text-rose-600 w-8 h-8 rounded-lg border border-slate-200 hover:border-rose-200 text-xs transition-colors cursor-pointer select-none flex items-center justify-center shadow-2xs">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab Panel 3: Course Enrollments -->
        <div id="academic-panel-registrations" class="academic-tab-content hidden space-y-4">

            <!-- Header Block -->
            <div
                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Course Enrollment Registry</h2>
                    <p class="text-xs text-slate-500">Connect students to multi-course tracks, monitor ongoing
                        educational statuses, manage key timeline dates, and track dropped metrics.</p>
                </div>
                <button onclick="openEnrollmentModal()"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg transition-all shadow-sm flex items-center gap-1.5 cursor-pointer select-none">
                    Enroll Student
                </button>
            </div>

            <!-- Main Data Grid Matrix -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-700 tracking-wider uppercase select-none sticky top-0">
                                <th class="px-6 py-4">Student Details</th>
                                <th class="px-6 py-4">Enrolled Course</th>
                                <th class="px-6 py-4">Instructor & Timeline</th>
                                <th class="px-6 py-4 w-40">Status Track</th>
                                <th class="px-6 py-4 w-56 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                            <?php
                            $enroll_query = $db->query("
                        SELECT ae.*, 
                               stu.student_reg_no, stu.first_name, stu.last_name,
                               ac.course_code, ac.course_name
                        FROM academic_enrollments ae
                        JOIN academic_students stu ON ae.student_id = stu.id
                        JOIN academic_courses ac ON ae.course_id = ac.id
                        ORDER BY ae.id DESC
                    ");
                            $enrollments = $enroll_query->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($enrollments)):
                                ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic bg-white">
                                        No active course enrollment files cataloged inside the database registry yet.
                                    </td>
                                </tr>
                                <?php
                            else:
                                foreach ($enrollments as $e):
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4">
                                            <span
                                                class="font-bold text-slate-900 block"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></span>
                                            <div class="text-slate-400 font-mono text-[11px] mt-0.5 tracking-wide">
                                                <?php echo htmlspecialchars($e['student_reg_no']); ?>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4">
                                            <span
                                                class="inline-block bg-slate-100 text-slate-700 font-bold px-2 py-0.5 rounded-md uppercase text-[10px] tracking-wider mr-1.5"><?php echo htmlspecialchars($e['course_code']); ?></span>
                                            <span
                                                class="text-slate-800 font-medium"><?php echo htmlspecialchars($e['course_name']); ?></span>
                                        </td>

                                        <td class="px-6 py-4 text-xs text-slate-700">
                                            <div class="font-bold text-slate-900 flex items-center gap-1">
                                                <i class="fa-solid fa-chalkboard-teacher text-[10px] text-slate-400"></i>
                                                <?php echo htmlspecialchars($e['instructor_id']); ?>
                                            </div>
                                            <div class="text-slate-500 font-mono mt-0.5 flex flex-col gap-0.5 text-[11px]">
                                                <span>Start:
                                                    <?php echo (!empty($e['start_date']) && $e['start_date'] !== '0000-00-00') ? htmlspecialchars($e['start_date']) : '<span class="italic text-slate-400">Course Not Started</span>'; ?></span>
                                                <?php if ($e['status'] === 'completed' && !empty($e['end_date'])): ?>
                                                    <span class="text-emerald-600 font-semibold">End:
                                                        <?php echo htmlspecialchars($e['end_date']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4">
                                            <?php
                                            $statusColors = [
                                                'assigned' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'ongoing' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'dropped' => 'bg-rose-50 text-rose-700 border-rose-200'
                                            ];
                                            $colorClass = $statusColors[$e['status']] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                                            ?>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border <?php echo $colorClass; ?>">
                                                <?php echo ucfirst($e['status']); ?>
                                            </span>
                                            <?php if ($e['status'] === 'dropped' && !empty($e['drop_reason'])): ?>
                                                <div class="text-[11px] text-rose-600 font-medium max-w-xs mt-1 italic"
                                                    title="<?php echo htmlspecialchars($e['drop_reason']); ?>">
                                                    Reason: <?php echo htmlspecialchars($e['drop_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4.5 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <!-- Clean Step Status Corridor Dropdown Trigger Selector -->
                                                <div class="relative inline-block text-left">
                                                    <?php if (in_array($e['status'], ['completed', 'dropped'])): ?>
                                                        <select disabled
                                                            class="bg-slate-50 text-slate-400 cursor-not-allowed text-[11px] rounded-md border border-slate-200 px-2 py-1 focus:outline-hidden opacity-70">
                                                            <option>Closed Track</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <select
                                                            onchange="routeStatusModalTransition(<?php echo (int) $e['id']; ?>, this.value, '<?php echo $e['status']; ?>')"
                                                            class="bg-white hover:bg-slate-50 text-slate-700 text-[11px] rounded-md border border-slate-200 px-2 py-1 focus:border-emerald-500 focus:outline-hidden cursor-pointer shadow-2xs font-medium">
                                                            <option value="" selected hidden>Update Status</option>
                                                            <?php if ($e['status'] === 'assigned'): ?>
                                                                <option value="ongoing">Ongoing</option>
                                                                <option value="dropped">Dropped</option>
                                                            <?php elseif ($e['status'] === 'ongoing'): ?>
                                                                <option value="completed">Completed</option>
                                                                <option value="dropped">Dropped</option>
                                                            <?php endif; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="inline-flex items-center gap-1">
                                                    <button type="button" title="Edit Metadata"
                                                        onclick='populateEnrollmentEdit(<?php echo json_encode($e); ?>)'
                                                        class="bg-emerald-50 hover:bg-teal-50 text-teal-600 w-8 h-8 rounded-lg border border-slate-200 text-xs flex items-center justify-center shadow-2xs"><i
                                                            class="fa-solid fa-pen-to-square"></i></button>
                                                    <button type="button" title="Delete Track"
                                                        onclick="triggerEnrollmentDelete(<?php echo (int) $e['id']; ?>)"
                                                        class="bg-rose-50 text-rose-500 w-8 h-8 rounded-lg border border-slate-200 text-xs flex items-center justify-center shadow-2xs"><i
                                                            class="fa-solid fa-trash-can"></i></button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="academic-panel-fees"
            class="academic-tab-content hidden text-center text-slate-400 p-12 bg-white rounded-xl border border-slate-200 shadow-sm italic text-xs">
            Financial payment ledger collection records overlay placeholder.
        </div>

        <div id="academic-panel-reports"
            class="academic-tab-content hidden text-center text-slate-400 p-12 bg-white rounded-xl border border-slate-200 shadow-sm italic text-xs">
            Combined filters panel and statements generator placeholder.
        </div>

    </div>
</div>

<div id="course-form-modal"
    class="fixed inset-0 z-50 invisible opacity-0 transition-all duration-300 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs" onclick="closeCourseFormModal()"></div>

    <div class="bg-white border border-slate-200 w-full max-w-md rounded-xl shadow-xl overflow-hidden relative z-10 transform scale-95 transition-transform duration-300"
        id="course-modal-chassis">
        <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 flex justify-between items-center select-none">
            <h3 id="course-modal-title" class="text-sm font-bold text-slate-900">
                Configure New Course Track
            </h3>
            <button onclick="closeCourseFormModal()"
                class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer text-xl font-semibold">&times;</button>
        </div>

        <form id="course-config-form" method="POST" action="actions.php" class="p-5 space-y-4">
            <input type="hidden" name="action" id="course-form-action" value="add_course">
            <input type="hidden" name="course_id" id="course-form-id" value="">

            <div>
                <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Course
                    Identification Key Code <span class="text-rose-500">*</span></label>
                <input type="text" name="course_code" id="field_course_code" required
                    placeholder="e.g., COMP-UG, ARB-101"
                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono uppercase">
            </div>

            <div>
                <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Course
                    Descriptive Title <span class="text-rose-500">*</span></label>
                <input type="text" name="course_name" id="field_course_name" required
                    placeholder="e.g., Bachelor of Islamic Commerce"
                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
            </div>

            <div>
                <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Standard Fee
                    Profile (₹) <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3.5 top-2.5 text-slate-400 font-bold text-xs">₹</span>
                    <input type="number" name="standard_fee" id="field_standard_fee" step="0.01" min="0.00" required
                        placeholder="0.00"
                        class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg pl-7 pr-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                </div>
            </div>

            <div>
                <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Operational
                    Offering Status</label>
                <select name="is_active" id="field_course_is_active"
                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                    <option value="1">Active / Accepting Registrations</option>
                    <option value="0">Suspended / Catalog Hold</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 select-none">
                <button type="button" onclick="closeCourseFormModal()"
                    class="px-4 py-2 text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="course-form-submit-btn"
                    class="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-all shadow-sm cursor-pointer">
                    Save Course
                </button>
            </div>
        </form>
    </div>
</div>

<div id="student-form-modal"
    class="fixed inset-0 z-50 invisible opacity-0 transition-all duration-300 flex items-center justify-center p-4 overflow-y-auto">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs" onclick="closeStudentFormModal()"></div>

    <div class="bg-white border border-slate-200 w-full max-w-2xl rounded-xl shadow-xl overflow-hidden relative z-10 transform scale-95 transition-transform duration-300 my-8"
        id="student-modal-chassis">
        <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 flex justify-between items-center select-none">
            <h3 id="student-modal-title" class="text-sm font-bold text-slate-900">
                Register New Institutional Student Profile
            </h3>
            <button onclick="closeStudentFormModal()"
                class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer text-xl font-semibold">&times;</button>
        </div>

        <form id="student-config-form" method="POST" action="actions.php" enctype="multipart/form-data"
            class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
            <input type="hidden" name="action" id="student-form-action" value="add_student">
            <input type="hidden" name="student_id" id="student-form-id" value="">

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">1. Core Identification &
                    Demographics</h4>

                <div
                    class="flex flex-col sm:flex-row items-center gap-4 bg-white p-3.5 rounded-lg border border-slate-200 shadow-2xs mb-2">
                    <div
                        class="w-16 h-16 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 overflow-hidden shrink-0 shadow-inner">
                        <img id="field_avatar_preview" src="" alt="Live Preview"
                            class="w-full h-full object-cover hidden">
                        <i id="field_avatar_icon" class="fa-solid fa-user-gradient text-xl text-slate-300"></i>
                    </div>
                    <div class="space-y-1.5 w-full">
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600">Upload
                            Student Profile Picture <span class="text-rose-500">*</span></label>
                        <input type="file" name="student_avatar" id="field_student_avatar"
                            accept="image/jpeg,image/png,image/jpg"
                            class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition-all cursor-pointer">
                        <p class="text-[10px] text-slate-400">Dimensions mapping: Square aspect ratio preferred.
                            JPG/PNG, Max size: 2MB.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">First
                            Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="first_name" id="field_first_name" required placeholder="e.g., Mohammad"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Last
                            Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="last_name" id="field_last_name" required placeholder="e.g., Sulthan"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Gender
                            <span class="text-rose-500">*</span></label>
                        <select name="gender" id="field_gender" required
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Date
                            of Birth <span class="text-rose-500">*</span></label>
                        <input type="date" name="dob" id="field_dob" required
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                    </div>
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Marital
                            Status <span class="text-rose-500">*</span></label>
                        <select name="marital_status" id="field_marital_status" required
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Blood
                            Group <span class="text-rose-500">*</span></label>
                        <select name="blood_group" id="field_blood_group" required
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">2. Academic Track
                    Classification</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Study
                            Level <span class="text-rose-500">*</span></label>
                        <select name="study_level" id="field_study_level" required
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                            <option value="No Formal Education">No Formal Education</option>
                            <option value="Primary School">Primary School (Class 1-5)</option>
                            <option value="Middle School">Middle School (Class 6-8)</option>
                            <option value="High School">High School / SSLC (Class 10)</option>
                            <option value="Higher Secondary">Higher Secondary / HSC (Class 12)</option>
                            <option value="Diploma / ITI">Diploma / ITI</option>
                            <option value="Undergraduate">Undergraduate (UG)</option>
                            <option value="Postgraduate">Postgraduate (PG)</option>
                            <option value="Doctorate">Doctorate / Ph.D.</option>
                            <option value="Post Doctorate">Post Doctorate</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Study
                            Specification Description</label>
                        <input type="text" name="study_specification" id="field_study_specification"
                            placeholder="e.g., B.E. CSE, M.Sc Physics"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                        <span class="text-xs text-red-500">If not applicable enter "N/A"</span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">3. Jamaath Affiliation
                    Parameters
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Jamaath
                            Status <span class="text-rose-500">*</span></label>
                        <select name="jamaath_status" id="field_jamaath_status"
                            onchange="toggleJamaathAffiliationFields()" required
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                            <option value="Within">Within Jamaath</option>
                            <option value="Outside">Outside Jamaath</option>
                        </select>
                    </div>

                    <!-- Dynamic Field: Inside Jamaath Input Box -->
                    <div id="wrapper_jamaath_membership" class="block">
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Membership
                            Card ID (Student / Father) <span class="text-rose-500">*</span></label>
                        <input type="text" name="jamaath_membership_id" id="field_jamaath_membership_id"
                            placeholder="e.g., M-1041 or F-302"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all uppercase font-mono">
                    </div>

                    <!-- Dynamic Field: Outside Jamaath Dropdown List -->
                    <div id="wrapper_jamaath_outside_dropdown" class="hidden">
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Select
                            External Jamaath <span class="text-rose-500">*</span></label>
                        <select name="external_jamaath_name" id="field_external_jamaath_name"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all cursor-pointer">
                            <option value="">Select External Jamaath Region</option>
                            <option value="Kottar Jamaath">Kottar Jamaath</option>
                            <option value="Elankadai Jamaath">Elankadai Jamaath</option>
                            <option value="Thuckalay Jamaath">Thuckalay Jamaath</option>
                            <option value="Colachel Jamaath">Colachel Jamaath</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">4. Primary Family &
                    Communication Contacts</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Father's
                            Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="father_name" id="field_father_name" required
                            placeholder="Father's Full Name"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Father's
                            Contact Phone <span class="text-rose-500">*</span></label>
                        <div class="iti-parent w-full">
                            <input type="tel" name="father_phone" id="field_father_phone" required
                                placeholder="Mandatory Contact"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg pl-14 pr-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Student
                            Personal Phone</label>
                        <div class="iti-parent w-full">
                            <input type="tel" name="student_phone" id="field_student_phone"
                                placeholder="Optional (Self Contact)"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg pl-14 pr-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Guardian
                            Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="guardian_name" id="field_guardian_name" required
                            placeholder="Guardian's Name"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Guardian
                            Phone Number <span class="text-rose-500">*</span></label>
                        <div class="iti-parent w-full">
                            <input type="tel" name="guardian_phone" id="field_guardian_phone" required
                                placeholder="Emergency No"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg pl-14 pr-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">5. Residential & Communication
                    Address</h4>
                <div class="space-y-4">

                    <!-- Sub-Card A: Residential Address Profile -->
                    <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-150 space-y-3 shadow-2xs">
                        <div class="flex items-center gap-1.5 font-bold text-slate-700 text-xs pb-1 select-none">
                            <i class="fa-solid fa-house-chimney text-emerald-600"></i> Residential Address
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Address
                                    Line 1 <span class="text-rose-500">*</span></label>
                                <input type="text" name="res_address_line1" id="field_res_address_line1" required
                                    placeholder="Street / Door No"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Address
                                    Line 2</label>
                                <input type="text" name="res_address_line2" id="field_res_address_line2"
                                    placeholder="Locality / Landmark"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">City
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="res_city" id="field_res_city" required
                                    placeholder="e.g. Nagercoil"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Pincode
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="res_pincode" id="field_res_pincode" required
                                    placeholder="e.g. 629002"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">State
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="res_state" id="field_res_state" required
                                    placeholder="e.g. Tamil Nadu"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Country
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="res_country" id="field_res_country" required value="India"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Card B: Communication Address Profile -->
                    <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-150 space-y-3 shadow-2xs">
                        <div class="flex items-center justify-between pb-1 select-none">
                            <div class="flex items-center gap-1.5 font-bold text-slate-700 text-xs">
                                <i class="fa-solid fa-briefcase text-teal-600"></i> Communication Address
                            </div>
                            <label
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="checkbox" id="field_same_address_checkbox"
                                    onchange="syncResidentialToCommunicationAddress()"
                                    class="w-3.5 h-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                                Same as Residential Address
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Address
                                    Line 1 <span class="text-rose-500">*</span></label>
                                <input type="text" name="comm_address_line1" id="field_comm_address_line1" required
                                    placeholder="Street / Door No"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Address
                                    Line 2</label>
                                <input type="text" name="comm_address_line2" id="field_comm_address_line2"
                                    placeholder="Locality / Landmark"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">City
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="comm_city" id="field_comm_city" required
                                    placeholder="e.g. Nagercoil"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Pincode
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="comm_pincode" id="field_comm_pincode" required
                                    placeholder="e.g. 629002"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">State
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="comm_state" id="field_comm_state" required
                                    placeholder="e.g. Tamil Nadu"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Country
                                    <span class="text-rose-500">*</span></label>
                                <input type="text" name="comm_country" id="field_comm_country" required value="India"
                                    class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">6. Verification Credentials
                    Mapping</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Aadhaar
                            Profile Key Number<span class="text-rose-500">*</span></label>
                        <input type="text" name="aadhar_no" id="field_aadhar_no" required maxlength="12"
                            placeholder="e.g., 12-digit Verification Identifier"
                            class="w-full h-10 bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Upload
                            Aadhaar Doc<span class="text-rose-500">*</span></label>
                        <input type="file" name="aadhar_doc" id="field_aadhar_doc" accept="image/*,.pdf"
                            class="w-full h-10 bg-white border border-slate-300 text-slate-700 text-xs rounded-lg file:mr-3 file:h-full file:py-0 file:px-3 file:rounded-l-lg file:rounded-r-none file:border-0 file:border-r file:border-slate-300 file:text-xs file:font-bold file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100 transition-all cursor-pointer flex items-center p-0 overflow-hidden">
                        <div id="field_aadhar_preview_container" class="mt-2 hidden">
                            <div class="border border-slate-200 rounded-lg p-2 bg-slate-50 inline-block max-w-full">
                                <img id="field_aadhar_img_preview" src="" alt="Aadhaar Scan Preview"
                                    class="max-h-24 rounded hidden">
                                <div id="field_aadhar_pdf_preview"
                                    class="hidden text-xs text-slate-600 flex items-center gap-1.5 font-medium px-1">
                                    <i class="fa-solid fa-file-pdf text-rose-500 text-sm"></i>
                                    <span id="field_aadhar_pdf_name" class="truncate max-w-xs">document.pdf</span>
                                </div>
                            </div>
                        </div>
                        <div id="field_aadhar_doc_link" class="text-[11px] font-medium text-emerald-600 mt-1.5 hidden">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-150 select-none">
                <button type="button" onclick="closeStudentFormModal()"
                    class="px-4 py-2 text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="student-form-submit-btn"
                    class="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-all shadow-sm cursor-pointer">
                    Register File Profile
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Student Profile Modal Chassis -->
<div id="student-view-modal"
    class="fixed inset-0 z-50 invisible opacity-0 transition-all duration-300 flex items-center justify-center p-4 overflow-y-auto">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs" onclick="closeStudentViewModal()"></div>

    <div class="bg-white border border-slate-200 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden relative z-10 transform scale-95 transition-transform duration-300 my-8"
        id="student-view-chassis">

        <!-- Header Profile Banner Panel -->
        <div class="bg-emerald-950 text-white p-6 relative flex justify-between items-start select-none">
            <div class="flex items-center gap-4">
                <div class="w-24 h-24 rounded-full bg-emerald-800/50 border-2 border-emerald-500/30 flex items-center justify-center text-white text-3xl font-bold tracking-wider font-mono shadow-inner overflow-hidden shrink-0"
                    id="view_avatar_placeholder">
                    ST
                </div>
                <div class="space-y-1.5">
                    <h3 id="view_full_name" class="text-2xl font-bold tracking-tight">Student Name</h3>
                    <div class="flex flex-wrap gap-1.5 items-center">
                        <span id="view_tag_reg_no"
                            class="bg-emerald-900/80 border border-emerald-700/50 text-[10px] px-2.5 py-0.5 rounded-md font-bold font-mono uppercase text-emerald-300 tracking-wider">Card:
                            N/A</span>
                        <span id="view_tag_gender"
                            class="bg-emerald-900/80 border border-emerald-700/50 text-[10px] px-2.5 py-0.5 rounded-md font-bold uppercase text-emerald-300 tracking-wider">Gender</span>
                    </div>
                </div>
            </div>
            <button onclick="closeStudentViewModal()"
                class="text-white/60 hover:text-white transition-colors cursor-pointer text-xl font-semibold bg-white/10 hover:bg-white/20 w-7 h-7 rounded-full flex items-center justify-center">&times;</button>
        </div>

        <!-- Main Dossier Content Body Grid -->
        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto bg-slate-50/40">

            <!-- Matrix 1: Core Institutional & Demographics Data Profile -->
            <div
                class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Date of
                        Birth</span>
                    <div id="view_dob" class="font-bold text-slate-800 font-mono">--/--/----</div>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Study
                        Level</span>
                    <div id="view_study_level" class="font-bold text-slate-800">N/A</div>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Blood
                        Group</span>
                    <div id="view_blood_group" class="font-bold text-rose-600 font-mono">N/A</div>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Marital
                        Status</span>
                    <div id="view_marital_status" class="font-bold text-slate-800">N/A</div>
                </div>
                <div class="col-span-2">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Track
                        Specification</span>
                    <div id="view_study_specification" class="font-bold text-slate-800 truncate">N/A</div>
                </div>
                <div class="col-span-2">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Jamaath
                        Connection Registry</span>
                    <div id="view_jamaath_identity"
                        class="font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded inline-block">
                        N/A</div>
                </div>
            </div>

            <!-- Matrix 2: Communication Vectors Grid Layout -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Phone Card -->
                <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs flex items-start gap-3 text-xs">
                    <div
                        class="text-emerald-600 bg-emerald-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Contact
                            Phone</span>
                        <div id="view_student_phone" class="font-bold text-slate-800 font-mono">N/A</div>
                    </div>
                </div>

                <!-- Father Identity Details -->
                <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs flex items-start gap-3 text-xs">
                    <div
                        class="text-slate-600 bg-slate-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div class="truncate">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Father
                            / Primary</span>
                        <div id="view_father_name" class="font-bold text-slate-800 truncate">N/A</div>
                        <div id="view_father_phone" class="font-mono text-[11px] text-slate-500 mt-0.5">N/A</div>
                    </div>
                </div>

                <!-- Emergency Guardian Card -->
                <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs flex items-start gap-3 text-xs">
                    <div class="text-teal-600 bg-teal-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="truncate">
                        <span
                            class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Guardian</span>
                        <div id="view_guardian_name" class="font-bold text-slate-800 truncate">N/A</div>
                        <div id="view_guardian_phone" class="font-mono text-[11px] text-slate-500 mt-0.5">N/A</div>
                    </div>
                </div>
            </div>

            <!-- Identity Verification Document Container -->
            <div
                class="bg-white p-4 rounded-xl border border-dashed border-slate-200 shadow-xs flex items-center justify-between text-xs">
                <div class="flex items-center gap-3">
                    <div
                        class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Aadhaar
                            Identity</span>
                        <div id="view_aadhar_no" class="font-bold text-slate-800 tracking-wider">---- ---- ----</div>
                    </div>
                </div>
                <div id="view_aadhar_link_wrapper"
                    class="text-xs font-bold text-emerald-600 flex items-center gap-1 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100 hover:bg-emerald-100 transition-colors">
                    <i class="fa-solid fa-file-pdf"></i>
                    <a id="view_aadhar_download" href="#" target="_blank"
                        class="hover:text-emerald-700 transition-colors">View Document Scan</a>
                </div>
            </div>

            <!-- Matrix 3: Address Profiles Layout (Dual Column Stacked Cards) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Residential Address View Block -->
                <!-- Residential Address View Block -->
                <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs text-xs space-y-2">
                    <div class="flex items-center gap-1.5 font-bold text-slate-700 border-b border-slate-100 pb-1.5">
                        <i class="fa-solid fa-house-chimney text-emerald-600"></i> Residential Location Address
                    </div>
                    <div class="space-y-1 text-slate-700 font-semibold leading-relaxed">
                        <div id="view_res_address" class="text-slate-800 font-bold">N/A</div>
                        <div><span id="view_res_city">N/A</span>, <span id="view_res_state">N/A</span>, <span
                                id="view_res_country" class="text-slate-500 font-bold">India</span></div>
                        <div class="font-mono text-[11px] text-slate-400" id="view_res_postal">------</div>
                    </div>
                </div>

                <!-- Communication Address View Block -->
                <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs text-xs space-y-2">
                    <div class="flex items-center gap-1.5 font-bold text-slate-700 border-b border-slate-100 pb-1.5">
                        <i class="fa-solid fa-briefcase text-teal-600"></i> Communication Contact Address
                    </div>
                    <div class="space-y-1 text-slate-700 font-semibold leading-relaxed">
                        <div id="view_comm_address" class="text-slate-800 font-bold">N/A</div>
                        <div><span id="view_comm_city">N/A</span>, <span id="view_comm_state">N/A</span>, <span
                                id="view_comm_country" class="text-slate-500 font-bold">India</span></div>
                        <div class="font-mono text-[11px] text-slate-400" id="view_comm_postal">------</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Dismiss Window Action Belt -->
        <div class="bg-slate-50 px-5 py-3.5 border-t border-t-slate-150 flex items-center justify-end select-none">
            <button onclick="closeStudentViewModal()"
                class="px-5 py-2 text-xs font-bold bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition-all shadow-xs cursor-pointer tracking-wide">
                Close Profile
            </button>
        </div>
    </div>
</div>

<!-- Enrollment Operational Modal Structure -->
<div id="enrollment-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-xs" onclick="closeEnrollmentModal()">
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div
            class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-900" id="enrollment-modal-title">New Course Enrollment
                    Configuration</h3>
                <button type="button" onclick="closeEnrollmentModal()"
                    class="text-slate-400 hover:text-slate-600 transition-colors"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="enrollment-form" method="POST" action="actions.php" class="p-6 space-y-4">
                <input type="hidden" name="action" id="enrollment-action-type" value="add_enrollment">
                <input type="hidden" name="enrollment_id" id="form-enrollment-id" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Select
                        Student</label>
                    <select name="student_id" id="form-student-id" required
                        class="w-full text-xs rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:outline-hidden">
                        <option value="">-- Choose Profile Track --</option>
                        <?php
                        // Fetch active student records directly within selection scope
                        $modal_students = $db->query("SELECT id, student_reg_no, first_name, last_name FROM academic_students ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($modal_students as $st) {
                            echo '<option value="' . (int) $st['id'] . '">[' . htmlspecialchars($st['student_reg_no']) . '] ' . htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Select
                        Course</label>
                    <select name="course_id" id="form-course-id" required
                        class="w-full text-xs rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:outline-hidden">
                        <option value="">-- Choose Catalog Track --</option>
                        <?php
                        // Fetch active courses directly within selection scope
                        $modal_courses = $db->query("SELECT id, course_code, course_name FROM academic_courses WHERE is_active = 1 ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($modal_courses as $cr) {
                            echo '<option value="' . (int) $cr['id'] . '">[' . htmlspecialchars($cr['course_code']) . '] ' . htmlspecialchars($cr['course_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Assigned
                        Instructor</label>
                    <select name="instructor_id" id="form-instructor-id" required
                        class="w-full text-xs rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:outline-hidden">
                        <option value="">-- Choose Academic Leader --</option>
                        <option value="Staff 1">Staff 1</option>
                        <option value="Staff 2">Staff 2</option>
                    </select>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end space-x-3">
                    <button type="button" onclick="closeEnrollmentModal()"
                        class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 shadow-sm transition-all">Save
                        Configurations</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL SUB-A: ONGOING TRANSITION DIALOG BLOCK
     ========================================== -->
<div id="modal-status-ongoing" class="fixed inset-0 z-50 overflow-y-auto hidden" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-xs"
            onclick="closeStatusModal('ongoing')"></div>
        <div
            class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all my-8 max-w-sm w-full border border-slate-200 z-10">
            <div class="bg-amber-50 px-6 py-4 border-b border-amber-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-amber-900">Commence Course Track: Ongoing</h3>
                <button type="button" onclick="closeStatusModal('ongoing')"
                    class="text-amber-500 hover:text-amber-700"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="actions.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_enrollment_status">
                <input type="hidden" name="enrollment_id" class="workflow-id-field" value="">
                <input type="hidden" name="target_status" value="ongoing">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Actual Start
                        Date</label>
                    <input type="date" name="start_date" required
                        class="w-full text-xs rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-amber-500 focus:outline-hidden">
                </div>
                <div class="pt-4 border-t border-slate-100 flex justify-end space-x-2">
                    <button type="button" onclick="closeStatusModal('ongoing')"
                        class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-md hover:bg-slate-200">Cancel</button>
                    <button type="submit"
                        class="px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-md hover:bg-amber-700 shadow-sm">Set
                        Ongoing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL SUB-B: COMPLETED TRANSITION DIALOG BLOCK
     ========================================== -->
<div id="modal-status-completed" class="fixed inset-0 z-50 overflow-y-auto hidden" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-xs"
            onclick="closeStatusModal('completed')"></div>
        <div
            class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all my-8 max-w-sm w-full border border-slate-200 z-10">
            <div class="bg-emerald-50 px-6 py-4 border-b border-emerald-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-emerald-900">Graduate Course Track: Completed</h3>
                <button type="button" onclick="closeStatusModal('completed')"
                    class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="actions.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_enrollment_status">
                <input type="hidden" name="enrollment_id" class="workflow-id-field" value="">
                <input type="hidden" name="target_status" value="completed">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Actual Completion
                        Date</label>
                    <input type="date" name="end_date" required
                        class="w-full text-xs rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:outline-hidden">
                </div>
                <div class="pt-4 border-t border-slate-100 flex justify-end space-x-2">
                    <button type="button" onclick="closeStatusModal('completed')"
                        class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-md hover:bg-slate-200">Cancel</button>
                    <button type="submit"
                        class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-md hover:bg-emerald-700 shadow-sm">Set
                        Completed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL SUB-C: DROPPED TRANSITION DIALOG BLOCK
     ========================================== -->
<div id="modal-status-dropped" class="fixed inset-0 z-50 overflow-y-auto hidden" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-xs"
            onclick="closeStatusModal('dropped')"></div>
        <div
            class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all my-8 max-w-sm w-full border border-slate-200 z-10">
            <div class="bg-rose-50 px-6 py-4 border-b border-rose-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-rose-900">Terminate Course Track: Dropped</h3>
                <button type="button" onclick="closeStatusModal('dropped')" class="text-rose-500 hover:text-rose-700"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="actions.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_enrollment_status">
                <input type="hidden" name="enrollment_id" class="workflow-id-field" value="">
                <input type="hidden" name="target_status" value="dropped">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Reason for
                        Dropping Track</label>
                    <textarea name="drop_reason" required rows="3"
                        placeholder="Provide precise administrative context explaining student departure..."
                        class="w-full text-xs rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-rose-500 focus:outline-hidden resize-none"></textarea>
                </div>
                <div class="pt-4 border-t border-slate-100 flex justify-end space-x-2">
                    <button type="button" onclick="closeStatusModal('dropped')"
                        class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-md hover:bg-slate-200">Cancel</button>
                    <button type="submit"
                        class="px-3 py-1.5 bg-rose-600 text-white text-xs font-semibold rounded-md hover:bg-rose-700 shadow-sm">Set
                        Dropped</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Tab Switching Controller (Matches the capsule navigation style cleanly)
    function switchAcademicTab(tabId) {
        document.querySelectorAll('.academic-tab-content').forEach(el => el.classList.add('hidden'));

        document.querySelectorAll('[id^="tab-btn-"]').forEach(btn => {
            btn.classList.remove('bg-emerald-50', 'text-slate-900', 'shadow-xs', 'font-bold');
            btn.classList.add('text-slate-500', 'hover:text-slate-900');
        });

        const targetPanel = document.getElementById('academic-panel-' + tabId);
        if (targetPanel) {
            targetPanel.classList.remove('hidden');
        }

        const activeBtn = document.getElementById('tab-btn-' + tabId);
        if (activeBtn) {
            activeBtn.classList.remove('text-slate-500', 'hover:text-slate-900');
            activeBtn.classList.add('bg-emerald-50', 'text-slate-900', 'shadow-xs', 'font-bold');
        }
    }

    // Modal Toggle Handlers
    function openCourseFormModal() {
        const modal = document.getElementById('course-form-modal');
        const chassis = document.getElementById('course-modal-chassis');

        document.getElementById('course-config-form').reset();
        document.getElementById('course-form-action').value = 'add_course';
        document.getElementById('course-form-id').value = '';
        document.getElementById('course-modal-title').innerHTML = 'Configure New Course Track';
        document.getElementById('course-form-submit-btn').innerHTML = 'Save Course';
        document.getElementById('field_course_code').disabled = false;

        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);
    }

    function closeCourseFormModal() {
        const modal = document.getElementById('course-form-modal');
        const chassis = document.getElementById('course-modal-chassis');

        chassis.classList.add('scale-95');
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('invisible'), 300);
    }

    function populateCourseEdit(course) {
        // Check global authentication rules intercept if existing inside environment
        if (typeof checkGlobalAuthorization === 'function') {
            if (!checkGlobalAuthorization('edit')) return;
        }

        const modal = document.getElementById('course-form-modal');
        const chassis = document.getElementById('course-modal-chassis');

        document.getElementById('course-form-action').value = 'edit_course';
        document.getElementById('course-form-id').value = course.id;
        document.getElementById('course-modal-title').innerHTML = 'Edit Course Settings Parameter';
        document.getElementById('course-form-submit-btn').innerHTML = 'Apply Changes';

        // Auto populate values
        document.getElementById('field_course_code').value = course.course_code;
        document.getElementById('field_course_code').disabled = true; // Safe lock primary key value matches
        document.getElementById('field_course_name').value = course.course_name;
        document.getElementById('field_standard_fee').value = course.standard_fee;
        document.getElementById('field_course_is_active').value = course.is_active;

        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);
    }

    // Modal Toggle Control Logic for Student Profiles Directory
    function openStudentFormModal() {
        const modal = document.getElementById('student-form-modal');
        const chassis = document.getElementById('student-modal-chassis');

        document.getElementById('student-config-form').reset();
        document.getElementById('student-form-action').value = 'add_student';
        document.getElementById('student-form-id').value = '';
        document.getElementById('student-modal-title').innerHTML = 'Register New Institutional Student Profile';
        document.getElementById('student-form-submit-btn').innerHTML = 'Register File Profile';
        document.getElementById('field_aadhar_doc_link').classList.add('hidden');
        document.getElementById('field_aadhar_no').disabled = false;

        // Force validation requirements for new additions
        document.getElementById('field_student_avatar').required = true;
        document.getElementById('field_aadhar_doc').required = true;

        // Reset visual preview imagery back to initial states
        document.getElementById('field_avatar_preview').classList.add('hidden');
        document.getElementById('field_avatar_icon').classList.remove('hidden');

        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);

        // Default form configuration parameters for fresh inputs
        document.getElementById('field_jamaath_status').value = 'Within';
        toggleJamaathAffiliationFields();

        const sameAddrCheck = document.getElementById('field_same_address_checkbox');
        if (sameAddrCheck) {
            sameAddrCheck.checked = false;
            syncResidentialToCommunicationAddress();
        }
    }

    function closeStudentFormModal() {
        const modal = document.getElementById('student-form-modal');
        const chassis = document.getElementById('student-modal-chassis');

        chassis.classList.add('scale-95');
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('invisible'), 300);
    }

    function populateStudentEdit(student) {
        // Trigger capture validation trace hooks to comply with security blockers
        if (typeof checkGlobalAuthorization === 'function') {
            if (!checkGlobalAuthorization('edit')) return;
        }

        const modal = document.getElementById('student-form-modal');
        const chassis = document.getElementById('student-modal-chassis');

        document.getElementById('student-form-action').value = 'edit_student';
        document.getElementById('student-form-id').value = student.id;
        document.getElementById('student-modal-title').innerHTML = `Modify Student Profile Workspace Parameters: [${student.student_reg_no}]`;
        document.getElementById('student-form-submit-btn').innerHTML = 'Apply Changes';

        // --- Block 1: Demographics & Core Fields ---
        document.getElementById('field_first_name').value = student.first_name;
        document.getElementById('field_last_name').value = student.last_name;
        document.getElementById('field_gender').value = student.gender;
        document.getElementById('field_dob').value = student.dob;
        document.getElementById('field_marital_status').value = student.marital_status || 'Single';
        document.getElementById('field_blood_group').value = student.blood_group || '';

        // --- Block 2: Academic Track ---
        document.getElementById('field_study_level').value = student.study_level;
        document.getElementById('field_study_specification').value = student.study_specification || '';

        // --- New Section: Jamaath Affiliation Mappings ---
        if (student.jamaath_status) {
            document.getElementById('field_jamaath_status').value = student.jamaath_status;
            if (student.jamaath_status === 'Within') {
                document.getElementById('field_jamaath_membership_id').value = student.jamaath_membership_id || '';
            } else if (student.jamaath_status === 'Outside') {
                document.getElementById('field_external_jamaath_name').value = student.external_jamaath_name || '';
            }
        }
        toggleJamaathAffiliationFields(); // Fire layout visibility filter

        // --- Block 3: Family Contacts ---
        document.getElementById('field_father_name').value = student.father_name || '';
        document.getElementById('field_father_phone').value = student.father_phone || '';
        document.getElementById('field_student_phone').value = student.student_phone || '';
        document.getElementById('field_guardian_name').value = student.guardian_name;
        document.getElementById('field_guardian_phone').value = student.guardian_phone;

        // --- Block 4: Dual Address Infrastructure Mappings ---
        document.getElementById('field_res_address_line1').value = student.res_address_line1 || '';
        document.getElementById('field_res_address_line2').value = student.res_address_line2 || '';
        document.getElementById('field_res_city').value = student.res_city || '';
        document.getElementById('field_res_pincode').value = student.res_pincode || '';
        document.getElementById('field_res_state').value = student.res_state || '';
        document.getElementById('field_res_country').value = student.res_country || 'India';

        document.getElementById('field_comm_address_line1').value = student.comm_address_line1 || '';
        document.getElementById('field_comm_address_line2').value = student.comm_address_line2 || '';
        document.getElementById('field_comm_city').value = student.comm_city || '';
        document.getElementById('field_comm_pincode').value = student.comm_pincode || '';
        document.getElementById('field_comm_state').value = student.comm_state || '';
        document.getElementById('field_comm_country').value = student.comm_country || 'India';

        // Evaluate if Residential matches Communication to set synchronization state toggle
        const isSameAddress =
            (student.res_address_line1 === student.comm_address_line1) &&
            (student.res_address_line2 === student.comm_address_line2) &&
            (student.res_city === student.comm_city) &&
            (student.res_pincode === student.comm_pincode) &&
            (student.res_state === student.comm_state) &&
            (student.res_country === student.comm_country);

        const sameAddrCheckbox = document.getElementById('field_same_address_checkbox');
        if (sameAddrCheckbox) {
            sameAddrCheckbox.checked = isSameAddress;
            syncResidentialToCommunicationAddress(); // Activates lock styles if true
        }

        // --- Block 5: File Handling and Identity Management ---
        document.getElementById('field_aadhar_no').value = student.aadhar_no || '';
        document.getElementById('field_aadhar_no').disabled = true; // Lock identity field adjustments

        document.getElementById('field_student_avatar').required = false;
        document.getElementById('field_aadhar_doc').required = false;

        // Handle Avatar Preview
        const avatarImg = document.getElementById('field_avatar_preview');
        const avatarIcon = document.getElementById('field_avatar_icon');
        if (student.avatar_path) {
            avatarImg.src = student.avatar_path;
            avatarImg.classList.remove('hidden');
            avatarIcon.classList.add('hidden');
        } else {
            avatarImg.classList.add('hidden');
            avatarIcon.classList.remove('hidden');
        }

        // Handle Verification Scan Document Preview
        const docLink = document.getElementById('field_aadhar_doc_link');
        const previewContainer = document.getElementById('field_aadhar_preview_container');
        const imgPreview = document.getElementById('field_aadhar_img_preview');
        const pdfPreview = document.getElementById('field_aadhar_pdf_preview');
        const pdfName = document.getElementById('field_aadhar_pdf_name');

        if (previewContainer) previewContainer.classList.add('hidden');
        if (imgPreview) imgPreview.classList.add('hidden');
        if (pdfPreview) pdfPreview.classList.add('hidden');

        if (student.aadhar_doc_path) {
            docLink.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> <a href="${student.aadhar_doc_path}" target="_blank" class="underline font-bold hover:text-emerald-700">View Active Document Scan Profile</a>`;
            docLink.classList.remove('hidden');

            if (previewContainer) {
                const extension = student.aadhar_doc_path.split('.').pop().toLowerCase();
                previewContainer.classList.remove('hidden');

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                    if (imgPreview) {
                        imgPreview.src = student.aadhar_doc_path;
                        imgPreview.classList.remove('hidden');
                    }
                } else if (extension === 'pdf') {
                    if (pdfPreview && pdfName) {
                        pdfName.innerHTML = `<a href="${student.aadhar_doc_path}" target="_blank" class="underline hover:text-emerald-600">Active_Aadhaar_Document.pdf</a>`;
                        pdfPreview.classList.remove('hidden');
                    }
                }
            }
        } else {
            docLink.classList.add('hidden');
        }

        // Set phone flags formatting parameters dynamically
        if (itiStudent && student.student_phone) itiStudent.setNumber(student.student_phone);
        if (itiGuardian && student.guardian_phone) itiGuardian.setNumber(student.guardian_phone);
        if (itiFather && student.father_phone) itiFather.setNumber(student.father_phone);

        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);
    }

    // System dynamic tab router bootstrapper
    document.addEventListener("DOMContentLoaded", function () {
        switchAcademicTab('<?php echo $active_tab; ?>');
    });

    // Global references for international phone validation tracking
    let itiStudent, itiGuardian, itiFather;

    document.addEventListener("DOMContentLoaded", function () {
        const studentInput = document.getElementById("field_student_phone");
        const guardianInput = document.getElementById("field_guardian_phone");

        // Initialize Student Phone Field
        if (studentInput && typeof window.intlTelInput !== "undefined") {
            itiStudent = window.intlTelInput(studentInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js" // ensures formatting support
            });
        }

        // Initialize Guardian Phone Field
        if (guardianInput && typeof window.intlTelInput !== "undefined") {
            itiGuardian = window.intlTelInput(guardianInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
            });
        }

        // Initialize Father Phone Field
        const fatherInput = document.getElementById("field_father_phone");
        if (fatherInput && typeof window.intlTelInput !== "undefined") {
            itiFather = window.intlTelInput(fatherInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
            });
        }

        // Intercept form submit to append full international numbers seamlessly
        const studentForm = document.getElementById("student-config-form");
        if (studentForm) {
            studentForm.addEventListener("submit", function () {
                if (itiStudent && studentInput.value.trim()) studentInput.value = itiStudent.getNumber();
                if (itiGuardian && guardianInput.value.trim()) guardianInput.value = itiGuardian.getNumber();
                if (itiFather && fatherInput.value.trim()) fatherInput.value = itiFather.getNumber(); // Appends +91 format securely
            });
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        const fileInput = document.getElementById('field_aadhar_doc');
        const previewContainer = document.getElementById('field_aadhar_preview_container');
        const imgPreview = document.getElementById('field_aadhar_img_preview');
        const pdfPreview = document.getElementById('field_aadhar_pdf_preview');
        const pdfName = document.getElementById('field_aadhar_pdf_name');

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = this.files[0];

                // Hide everything initially on state change
                previewContainer.classList.add('hidden');
                imgPreview.classList.add('hidden');
                pdfPreview.classList.add('hidden');

                if (!file) return;

                // 1. Strict Max-Size Validation (2MB = 2 * 1024 * 1024 Bytes)
                const maxSizeBytes = 2 * 1024 * 1024;
                if (file.size > maxSizeBytes) {
                    alert("Upload validation failure: The selected document exceeds the maximum limit of 2MB.");
                    this.value = ''; // Flush selection
                    return;
                }

                // 2. Generate and Render Component Previews
                previewContainer.classList.remove('hidden');
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        imgPreview.src = e.target.result;
                        imgPreview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    pdfName.textContent = file.name;
                    pdfPreview.classList.remove('hidden');
                }
            });
        }

        const avatarInput = document.getElementById('field_student_avatar');
        const avatarImg = document.getElementById('field_avatar_preview');
        const avatarIcon = document.getElementById('field_avatar_icon');

        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) {
                    avatarImg.classList.add('hidden');
                    avatarIcon.classList.remove('hidden');
                    return;
                }

                // Enforce Strict 2MB Limit Constraint Validation Check
                if (file.size > (2 * 1024 * 1024)) {
                    alert("Upload constraint failure: The selected profile photo exceeds the 2MB size limit.");
                    this.value = '';
                    avatarImg.classList.add('hidden');
                    avatarIcon.classList.remove('hidden');
                    return;
                }

                // Format type verification gate
                if (!file.type.startsWith('image/')) {
                    alert("Format structure error: Please upload a valid image asset file (JPG, JPEG, or PNG).");
                    this.value = '';
                    return;
                }

                // Render live file thumbnail snapshot structure
                const reader = new FileReader();
                reader.onload = function (e) {
                    avatarImg.src = e.target.result;
                    avatarImg.classList.remove('hidden');
                    avatarIcon.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            });
        }
    });

    // Update the existing closeStudentFormModal routine to clear previews on dismiss
    const baseCloseModal = closeStudentFormModal;
    closeStudentFormModal = function () {
        baseCloseModal();
        document.getElementById('field_aadhar_preview_container').classList.add('hidden');
    };

    // View Modal Display Interface Controller Matrix
    function triggerStudentProfileView(student) {
        console.log("Invoking view pipeline layout parameters context tracker:", student);

        const modal = document.getElementById('student-view-modal');
        const chassis = document.getElementById('student-view-chassis');

        if (!modal || !chassis) return;

        // Force Modal Display Visibility States
        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);

        // Core Profile Name Identifiers
        const fName = student.first_name || '';
        const lName = student.last_name || '';
        document.getElementById('view_full_name').textContent = `${fName} ${lName}`.trim();
        document.getElementById('view_tag_reg_no').textContent = `Card: ${student.student_reg_no || 'N/A'}`;
        document.getElementById('view_tag_gender').textContent = student.gender || 'N/A';

        // Set Dynamic Photo Avatar Box
        const elAvatar = document.getElementById('view_avatar_placeholder');
        if (elAvatar) {
            if (student.avatar_path) {
                elAvatar.innerHTML = `<img src="${student.avatar_path}" class="w-full h-full object-cover" alt="Student profile picture asset">`;
            } else {
                elAvatar.textContent = ((fName.charAt(0) || '') + (lName.charAt(0) || '')).toUpperCase();
            }
        }

        // Expanded Informational Profiles Matrix
        document.getElementById('view_dob').textContent = student.dob ? student.dob.split('-').reverse().join('-') : '--------';
        document.getElementById('view_study_level').textContent = student.study_level || 'N/A';
        document.getElementById('view_study_specification').textContent = student.study_specification || 'N/A';
        document.getElementById('view_blood_group').textContent = student.blood_group || 'N/A';
        document.getElementById('view_marital_status').textContent = student.marital_status || 'Single';

        // Process Jamaath Text Conditions Label
        const elJamaath = document.getElementById('view_jamaath_identity');
        if (elJamaath) {
            if (student.jamaath_status === 'Within') {
                elJamaath.textContent = `Within Jamaath (ID: ${student.jamaath_membership_id || 'N/A'})`;
                elJamaath.className = "font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded inline-block text-[11px]";
            } else {
                elJamaath.textContent = `External: ${student.external_jamaath_name || 'N/A'}`;
                elJamaath.className = "font-bold text-amber-700 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded inline-block text-[11px]";
            }
        }

        // Contact Nodes Assignments
        document.getElementById('view_student_phone').textContent = student.student_phone || 'N/A';
        document.getElementById('view_father_name').textContent = student.father_name || 'N/A';
        document.getElementById('view_father_phone').textContent = student.father_phone || 'N/A';
        document.getElementById('view_guardian_name').textContent = student.guardian_name || 'N/A';
        document.getElementById('view_guardian_phone').textContent = student.guardian_phone || 'N/A';

        // Dynamic Identification Formatting
        const elAadhar = document.getElementById('view_aadhar_no');
        if (elAadhar) {
            elAadhar.textContent = student.aadhar_no ? student.aadhar_no.replace(/(\d{4})/g, '$1 ').trim() : '---- ---- ----';
        }

        // Mapping Card A: Residential Geographic Locations with Country
        const secondaryRes = student.res_address_line2 ? `, ${student.res_address_line2}` : '';
        document.getElementById('view_res_address').textContent = `${student.res_address_line1 || 'N/A'}${secondaryRes}`;
        document.getElementById('view_res_city').textContent = student.res_city || 'N/A';
        document.getElementById('view_res_state').textContent = student.res_state || 'N/A';
        document.getElementById('view_res_country').textContent = student.res_country || 'India';
        document.getElementById('view_res_postal').textContent = student.res_pincode ? `PIN: ${student.res_pincode}` : '------';

        // Mapping Card B: Communication Geographic Locations with Country
        const secondaryComm = student.comm_address_line2 ? `, ${student.comm_address_line2}` : '';
        document.getElementById('view_comm_address').textContent = `${student.comm_address_line1 || 'N/A'}${secondaryComm}`;
        document.getElementById('view_comm_city').textContent = student.comm_city || 'N/A';
        document.getElementById('view_comm_state').textContent = student.comm_state || 'N/A';
        document.getElementById('view_comm_country').textContent = student.comm_country || 'India';
        document.getElementById('view_comm_postal').textContent = student.comm_pincode ? `PIN: ${student.comm_pincode}` : '------';

        // Control Document Link Mapping Structures
        const downloadAction = document.getElementById('view_aadhar_download');
        const linkWrapper = document.getElementById('view_aadhar_link_wrapper');
        if (downloadAction && linkWrapper) {
            if (student.aadhar_doc_path) {
                downloadAction.href = student.aadhar_doc_path;
                linkWrapper.style.display = 'flex';
            } else {
                linkWrapper.style.display = 'none';
            }
        }
    }

    function closeStudentViewModal() {
        const modal = document.getElementById('student-view-modal');
        const chassis = document.getElementById('student-view-chassis');

        chassis.classList.add('scale-95');
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('invisible'), 300);
    }

    function syncResidentialToCommunicationAddress() {
        const isSynced = document.getElementById('field_same_address_checkbox').checked;

        // Define key field mapping pairs
        const addressMap = [
            { source: 'field_res_address_line1', target: 'field_comm_address_line1' },
            { source: 'field_res_address_line2', target: 'field_comm_address_line2' },
            { source: 'field_res_city', target: 'field_comm_city' },
            { source: 'field_res_pincode', target: 'field_comm_pincode' },
            { source: 'field_res_state', target: 'field_comm_state' },
            { source: 'field_res_country', target: 'field_comm_country' }
        ];

        addressMap.forEach(pair => {
            const srcEl = document.getElementById(pair.source);
            const tgtEl = document.getElementById(pair.target);

            if (srcEl && tgtEl) {
                if (isSynced) {
                    tgtEl.value = srcEl.value;
                    tgtEl.readOnly = true;
                    tgtEl.classList.add('bg-slate-50', 'text-slate-500'); // Add locked styling hint
                } else {
                    tgtEl.readOnly = false;
                    tgtEl.classList.remove('bg-slate-50', 'text-slate-500');
                }
            }
        });
    }

    // Attach real-time input listeners to residential fields so changes reflect instantly while checked
    document.addEventListener("DOMContentLoaded", function () {
        const fieldsToWatch = ['field_res_address_line1', 'field_res_address_line2', 'field_res_city', 'field_res_pincode', 'field_res_state', 'field_res_country'];
        fieldsToWatch.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function () {
                    if (document.getElementById('field_same_address_checkbox').checked) {
                        syncResidentialToCommunicationAddress();
                    }
                });
            }
        });
    });

    function toggleJamaathAffiliationFields() {
        const status = document.getElementById('field_jamaath_status').value;
        const wrapMembership = document.getElementById('wrapper_jamaath_membership');
        const wrapOutside = document.getElementById('wrapper_jamaath_outside_dropdown');

        const inputMembership = document.getElementById('field_jamaath_membership_id');
        const selectOutside = document.getElementById('field_external_jamaath_name');

        if (status === 'Within') {
            // Show membership layout path
            wrapMembership.classList.remove('hidden');
            wrapOutside.classList.add('hidden');

            if (inputMembership) inputMembership.required = true;
            if (selectOutside) {
                selectOutside.required = false;
                selectOutside.value = ''; // Flush stale choice
            }
        } else if (status === 'Outside') {
            // Show external region mapping dropdown path
            wrapMembership.classList.add('hidden');
            wrapOutside.classList.remove('hidden');

            if (inputMembership) {
                inputMembership.required = false;
                inputMembership.value = '';
            }
            if (selectOutside) selectOutside.required = true;
        }
    }


    /**
  * Phase 2 Handshake: Course Enrollments Lifecycles Script Engine
  */

    function routeStatusModalTransition(enrollmentId, targetStatus, currentStatus) {
        if (!enrollmentId || !targetStatus) return;

        if (currentStatus === 'completed' || currentStatus === 'dropped') {
            alert("Operation blocked: Closed lifecycle tracks cannot be altered.");
            window.location.reload();
            return;
        }

        const targetModal = document.getElementById(`modal-status-${targetStatus}`);
        if (!targetModal) return;

        // Isolate this specific sub-modal's enrollment ID field
        const idFields = targetModal.querySelectorAll('.workflow-id-field');
        idFields.forEach(field => { field.value = enrollmentId; });

        // --- DYNAMIC CALENDAR DATE VALIDATION BOUNDARIES ---
        const todayStr = new Date().toISOString().split('T')[0];

        if (targetStatus === 'ongoing') {
            const startDateInput = targetModal.querySelector('input[name="start_date"]');
            if (startDateInput) {
                // Rule: Start date cannot be more than 30 days in the future
                const maxFutureDate = new Date();
                maxFutureDate.setDate(maxFutureDate.getDate() + 30);

                startDateInput.setAttribute('max', maxFutureDate.toISOString().split('T')[0]);
                // Catch typos by blocking dates older than 1 year
                const minPastDate = new Date();
                minPastDate.setFullYear(minPastDate.getFullYear() - 1);
                startDateInput.setAttribute('min', minPastDate.toISOString().split('T')[0]);
            }
        } else if (targetStatus === 'completed') {
            const endDateInput = targetModal.querySelector('input[name="end_date"]');
            if (endDateInput) {
                // Rule: End date cannot be in the future relative to today
                endDateInput.setAttribute('max', todayStr);

                // Look up the active table row to fetch its associated start date string
                const row = document.querySelector(`select[onchange*="${enrollmentId}"]`).closest('tr');
                const timelineText = row.querySelector('.text-slate-500.font-mono').textContent;
                const startMatch = timelineText.match(/Start:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/);

                if (startMatch && startMatch[1]) {
                    // Rule: End date must be on or after the actual start date
                    endDateInput.setAttribute('min', startMatch[1]);
                }
            }
        }

        targetModal.classList.remove('hidden');
    }

    function closeStatusModal(statusType) {
        const targetModal = document.getElementById(`modal-status-${statusType}`);
        if (targetModal) targetModal.classList.add('hidden');
        window.location.reload(); // Instantly sweeps selection states back to baseline indexes cleanly
    }

    function openEnrollmentModal(title = "New Course Enrollment Configuration", action = "add_enrollment") {
        const modal = document.getElementById('enrollment-modal');
        const form = document.getElementById('enrollment-form');
        if (form) form.reset();

        document.getElementById('enrollment-modal-title').textContent = title;
        document.getElementById('enrollment-action-type').value = action;
        document.getElementById('form-enrollment-id').value = "";

        document.getElementById('form-student-id').removeAttribute('disabled');
        document.getElementById('form-course-id').removeAttribute('disabled');

        if (modal) modal.classList.remove('hidden');
    }

    function closeEnrollmentModal() {
        const modal = document.getElementById('enrollment-modal');
        if (modal) modal.classList.add('hidden');
    }

    function populateEnrollmentEdit(enrollmentData) {
        openEnrollmentModal("Modify Course Enrollment Track", "edit_enrollment");
        document.getElementById('form-enrollment-id').value = enrollmentData.id;
        document.getElementById('form-student-id').value = enrollmentData.student_id;
        document.getElementById('form-course-id').value = enrollmentData.course_id;
        document.getElementById('form-instructor-id').value = enrollmentData.instructor_id;

        document.getElementById('form-student-id').setAttribute('disabled', 'disabled');
        document.getElementById('form-course-id').setAttribute('disabled', 'disabled');
    }

    function triggerEnrollmentDelete(enrollmentId) {
        if (confirm("Are you sure you want to permanently delete this course enrollment record track?")) {
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'actions.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_enrollment';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'enrollment_id';
            idInput.value = enrollmentId;

            tempForm.appendChild(actionInput);
            tempForm.appendChild(idInput);
            document.body.appendChild(tempForm);
            tempForm.submit();
        }
    }
</script>

<?php
include_once 'footer.php';
?>
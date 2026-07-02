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
                        Manage base demographics data profiles, program levels, and vital emergency contact parameters for students.
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
                                        <td class="px-6 py-4 font-mono font-bold text-slate-900">
                                            <?php echo htmlspecialchars($s['student_reg_no']); ?>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-slate-800">
                                            <span onclick='triggerStudentProfileView(<?php echo json_encode($s); ?>)'
                                                class="font-bold text-slate-900 block hover:text-emerald-700 cursor-pointer transition-colors">
                                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                            </span>
                                            <div class="text-[10px] text-slate-500 mt-0.5 font-sans select-none">
                                                <?php echo htmlspecialchars($s['gender']); ?> &bull; DOB:
                                                <?php echo date('d-m-Y', strtotime($s['dob'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-slate-800">
                                            <span
                                                class="inline-flex items-center bg-slate-100 text-slate-600 border border-slate-200 text-[10px] px-2 py-0.5 rounded-sm font-bold uppercase select-none tracking-wide mr-1">
                                                <?php echo htmlspecialchars($s['study_level']); ?>
                                            </span>
                                            <span class="text-slate-500 text-xs font-normal">
                                                <?php echo htmlspecialchars($s['study_specification'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-mono text-slate-700 text-xs">
                                            <div class="font-bold text-slate-900 font-sans text-xs mb-0.5">
                                                <?php echo htmlspecialchars($s['guardian_name']); ?>
                                            </div>
                                            <i
                                                class="fa-solid fa-phone text-[10px] text-slate-400 mr-1"></i><?php echo htmlspecialchars($s['guardian_phone']); ?>
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

        <div id="academic-panel-registrations"
            class="academic-tab-content hidden text-center text-slate-400 p-12 bg-white rounded-xl border border-slate-200 shadow-sm italic text-xs">
            Course allocation enrollment workflows console placeholder.
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
                        <input type="text" name="last_name" id="field_last_name" required placeholder="e.g., Abdullah"
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
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">3. Primary Communication
                    Vectors</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Student
                            Phone Phone</label>
                        <div class="iti-parent w-full">
                            <input type="tel" name="student_phone" id="field_student_phone" placeholder="Optional"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg pl-14 pr-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Guardian
                            Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="guardian_name" id="field_guardian_name" required
                            placeholder="Full Name"
                            class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label
                            class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Guardian
                            Phone <span class="text-rose-500">*</span></label>
                        <div class="iti-parent w-full">
                            <input type="tel" name="guardian_phone" id="field_guardian_phone" required
                                placeholder="Emergency No"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg pl-14 pr-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">4. Residential Location
                    Metrics</h4>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Address
                                Line 1 <span class="text-rose-500">*</span></label>
                            <input type="text" name="address_line1" id="field_address_line1" required
                                placeholder="Door No, Building Name, Street"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                        </div>
                        <div>
                            <label
                                class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Address
                                Line 2</label>
                            <input type="text" name="address_line2" id="field_address_line2"
                                placeholder="Locality, Area Name"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">City
                                <span class="text-rose-500">*</span></label>
                            <input type="text" name="city" id="field_city" required placeholder="e.g., Nagercoil"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all">
                        </div>
                        <div>
                            <label
                                class="block text-[11px] uppercase tracking-wider font-bold text-slate-600 mb-1.5">Pincode
                                / Postal Zip Code <span class="text-rose-500">*</span></label>
                            <input type="text" name="pincode" id="field_pincode" required placeholder="e.g., 629001"
                                class="w-full bg-white border border-slate-300 text-slate-800 text-xs rounded-lg px-3 py-2.5 focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">5. Verification Credentials
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

<div id="student-view-modal"
    class="fixed inset-0 z-50 invisible opacity-0 transition-all duration-300 flex items-center justify-center p-4 overflow-y-auto">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs" onclick="closeStudentViewModal()"></div>

    <div class="bg-white border border-slate-200 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden relative z-10 transform scale-95 transition-transform duration-300 my-8"
        id="student-view-chassis">

        <div class="bg-emerald-950 text-white p-6 relative flex justify-between items-start select-none">
            <div class="flex items-center gap-4">
                <div class="w-32 h-32 rounded-full bg-emerald-800/50 border border-emerald-500/30 flex items-center justify-center text-white text-2xl font-bold tracking-wider font-mono shadow-inner"
                    id="view_avatar_placeholder">
                    ST
                </div>
                <div class="space-y-1">
                    <h3 id="view_full_name" class="text-xl font-bold tracking-tight">Student Name</h3>
                    <div class="flex flex-wrap gap-1.5 items-center">
                        <span id="view_tag_reg_no"
                            class="bg-emerald-900/80 border border-emerald-700/50 text-[10px] px-2 py-0.5 rounded-md font-bold font-mono uppercase text-emerald-300 tracking-wider">Card:
                            N/A</span>
                        <span id="view_tag_gender"
                            class="bg-emerald-900/80 border border-emerald-700/50 text-[10px] px-2 py-0.5 rounded-md font-bold uppercase text-emerald-300 tracking-wider">Gender</span>
                    </div>
                </div>
            </div>
            <button onclick="closeStudentViewModal()"
                class="text-white/60 hover:text-white transition-colors cursor-pointer text-xl font-semibold bg-white/10 hover:bg-white/20 w-7 h-7 rounded-full flex items-center justify-center">&times;</button>
        </div>

        <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto bg-slate-50/30">

            <div
                class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs grid grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-6 text-xs">
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
                <div class="col-span-2 md:col-span-1">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Study
                        Specification</span>
                    <div id="view_study_specification" class="font-bold text-slate-800 truncate">N/A</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

                <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs flex items-start gap-3 text-xs">
                    <div class="text-teal-600 bg-teal-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="truncate">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Primary
                            Guardian</span>
                        <div id="view_guardian_name" class="font-bold text-slate-800 truncate">N/A</div>
                        <div id="view_guardian_phone" class="font-mono text-[11px] text-slate-500 mt-0.5">N/A</div>
                    </div>
                </div>

                <div
                    class="bg-white p-4 rounded-xl border border-dashed border-slate-200 shadow-xs flex items-start gap-3 text-xs">
                    <div
                        class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div class="truncate w-full">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Aadhaar
                            Identity</span>
                        <div id="view_aadhar_no" class="font-bold text-slate-800 font-mono tracking-wide">---- ----
                            ----</div>
                        <div id="view_aadhar_link_wrapper"
                            class="mt-1 text-[11px] font-medium text-emerald-600 flex items-center gap-1">
                            <i class="fa-solid fa-file-arrow-down text-[10px]"></i>
                            <a id="view_aadhar_download" href="#" target="_blank"
                                class="underline hover:text-emerald-700 transition-colors">View Attachment</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-150 shadow-xs text-xs space-y-3">
                <div class="flex items-center gap-1.5 border-b border-slate-100 pb-2 text-slate-400 select-none">
                    <i class="fa-solid fa-map-location-dot text-slate-300"></i>
                    <h4 class="text-[10px] font-bold tracking-wider uppercase">Residential Location Metrics</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Street
                            Address Coordinates</span>
                        <div id="view_address_full" class="font-semibold text-slate-700 leading-relaxed">Line 1,
                            Line 2</div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">City
                                / Town</span>
                            <div id="view_city" class="font-bold text-slate-800">N/A</div>
                        </div>
                        <div>
                            <span
                                class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Postal
                                Pincode</span>
                            <div id="view_pincode" class="font-bold text-slate-800 font-mono">------</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="bg-slate-50 px-5 py-3.5 border-t border-slate-150 flex items-center justify-end select-none">
            <button onclick="closeStudentViewModal()"
                class="px-5 py-2 text-xs font-bold bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition-all shadow-xs cursor-pointer tracking-wide">
                Close Profile
            </button>
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

        // Bind corresponding values safely to layout fields
        document.getElementById('field_first_name').value = student.first_name;
        document.getElementById('field_last_name').value = student.last_name;
        document.getElementById('field_gender').value = student.gender;
        document.getElementById('field_dob').value = student.dob;
        document.getElementById('field_study_level').value = student.study_level;
        document.getElementById('field_study_specification').value = student.study_specification || '';
        document.getElementById('field_student_phone').value = student.student_phone || '';
        document.getElementById('field_guardian_name').value = student.guardian_name;
        document.getElementById('field_guardian_phone').value = student.guardian_phone;
        document.getElementById('field_address_line1').value = student.address_line1;
        document.getElementById('field_address_line2').value = student.address_line2 || '';
        document.getElementById('field_city').value = student.city;
        document.getElementById('field_pincode').value = student.pincode;

        // Handle identification records mapping
        document.getElementById('field_aadhar_no').value = student.aadhar_no;
        document.getElementById('field_aadhar_no').disabled = true;

        // --- FILE HANDLING MANAGEMENT RULES FOR EDITS ---

        // Since files already exist on the server, they are not mandatory for edits
        document.getElementById('field_student_avatar').required = false;
        document.getElementById('field_aadhar_doc').required = false;

        // 1. Populate Existing Student Avatar Preview Image
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

        // 2. Populate Existing Document Preview Framework Mappings
        const docLink = document.getElementById('field_aadhar_doc_link');
        const previewContainer = document.getElementById('field_aadhar_preview_container');
        const imgPreview = document.getElementById('field_aadhar_img_preview');
        const pdfPreview = document.getElementById('field_aadhar_pdf_preview');
        const pdfName = document.getElementById('field_aadhar_pdf_name');

        // Clear previous state selections
        if (previewContainer) previewContainer.classList.add('hidden');
        if (imgPreview) imgPreview.classList.add('hidden');
        if (pdfPreview) pdfPreview.classList.add('hidden');

        if (student.aadhar_doc_path) {
            // Render text link indicator beneath file container channel
            docLink.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> <a href="${student.aadhar_doc_path}" target="_blank" class="underline font-bold hover:text-emerald-700">View Active Document Scan Profile</a>`;
            docLink.classList.remove('hidden');

            // Render live preview element inside preview blocks if it's an image or a PDF template
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

        // Auto-detect and set international flag configurations on edit population
        if (itiStudent && student.student_phone) itiStudent.setNumber(student.student_phone);
        if (itiGuardian && student.guardian_phone) itiGuardian.setNumber(student.guardian_phone);

        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);
    }

    // System dynamic tab router bootstrapper
    document.addEventListener("DOMContentLoaded", function () {
        switchAcademicTab('<?php echo $active_tab; ?>');
    });

    // Global references for international phone validation tracking
    let itiStudent, itiGuardian;

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

        // Intercept form submit to append full international numbers seamlessly
        const studentForm = document.getElementById("student-config-form");
        if (studentForm) {
            studentForm.addEventListener("submit", function () {
                if (itiStudent && studentInput.value.trim()) {
                    studentInput.value = itiStudent.getNumber(); // Replaces local digits with full international string (+91...)
                }
                if (itiGuardian && guardianInput.value.trim()) {
                    guardianInput.value = itiGuardian.getNumber();
                }
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
        console.log("Profile view handler sequence invoked for:", student);

        const modal = document.getElementById('student-view-modal');
        const chassis = document.getElementById('student-view-chassis');

        if (!modal || !chassis) {
            console.error("Layout target wrapper matching chassis containers could not be found in active DOM frameworks.");
            return;
        }

        // Safe Assignments using optional checks and value verification cascades
        const fName = student.first_name || '';
        const lName = student.last_name || '';
        const fullName = (fName + ' ' + lName).trim() || 'Unmapped Profile';

        const elFullName = document.getElementById('view_full_name');
        if (elFullName) elFullName.textContent = fullName;

        const elRegNo = document.getElementById('view_tag_reg_no');
        if (elRegNo) elRegNo.textContent = `Card: ${student.student_reg_no || 'N/A'}`;

        const elGender = document.getElementById('view_tag_gender');
        if (elGender) elGender.textContent = student.gender || 'N/A';

        const elAvatar = document.getElementById('view_avatar_placeholder');
        if (elAvatar) {
            if (student.avatar_path) {
                elAvatar.innerHTML = `<img src="${student.avatar_path}" class="w-full h-full object-cover rounded-full" alt="Student Profile Picture">`;
            } else {
                elAvatar.textContent = ((student.first_name?.charAt(0) || '') + (student.last_name?.charAt(0) || '')).toUpperCase();
            }
        }

        const elDob = document.getElementById('view_dob');
        if (elDob) {
            elDob.textContent = student.dob ? student.dob.split('-').reverse().join('-') : '--/--/----';
        }

        const elLevel = document.getElementById('view_study_level');
        if (elLevel) elLevel.textContent = student.study_level || 'N/A';

        const elSpec = document.getElementById('view_study_specification');
        if (elSpec) elSpec.textContent = student.study_specification || 'N/A';

        const elPhone = document.getElementById('view_student_phone');
        if (elPhone) elPhone.textContent = student.student_phone || 'N/A';

        const elGName = document.getElementById('view_guardian_name');
        if (elGName) elGName.textContent = student.guardian_name || 'N/A';

        const elGPhone = document.getElementById('view_guardian_phone');
        if (elGPhone) elGPhone.textContent = student.guardian_phone || 'N/A';

        const elAadhar = document.getElementById('view_aadhar_no');
        if (elAadhar) {
            elAadhar.textContent = student.aadhar_no ? student.aadhar_no.replace(/(\d{4})/g, '$1 ').trim() : '---- ---- ----';
        }

        // Geographic address configuration mapping check
        const elAddress = document.getElementById('view_address_full');
        if (elAddress) {
            const secondaryAddress = student.address_line2 ? `, ${student.address_line2}` : '';
            elAddress.textContent = `${student.address_line1 || ''}${secondaryAddress}`;
        }

        const elCity = document.getElementById('view_city');
        if (elCity) elCity.textContent = student.city || 'N/A';

        const elPincode = document.getElementById('view_pincode');
        if (elPincode) elPincode.textContent = student.pincode || '------';

        // Secure download link attachment asset pipeline mapping validation check
        const downloadAction = document.getElementById('view_aadhar_download');
        const linkWrapper = document.getElementById('view_aadhar_link_wrapper');

        if (downloadAction && linkWrapper) {
            if (student.aadhar_doc_path) {
                downloadAction.href = student.aadhar_doc_path;
                linkWrapper.classList.remove('hidden');
            } else {
                linkWrapper.classList.add('hidden');
            }
        }

        // Fire display configuration animation transitions
        modal.classList.remove('invisible', 'opacity-0');
        setTimeout(() => chassis.classList.remove('scale-95'), 20);
    }

    function closeStudentViewModal() {
        const modal = document.getElementById('student-view-modal');
        const chassis = document.getElementById('student-view-chassis');

        chassis.classList.add('scale-95');
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('invisible'), 300);
    }
</script>

<?php
include_once 'footer.php';
?>
<?php
require_once 'db.php';
require_once 'helpers.php';

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

// Fetch active filter variables from GET parameters
$filter_mahallah = isset($_GET['mahallah']) ? $_GET['mahallah'] : 'All';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'All';
$filter_chanda = isset($_GET['chanda']) ? $_GET['chanda'] : 'All';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Calculate the previous month string boundary (e.g., '2026-05-01')
$prev_month_boundary = date('Y-m-01', strtotime('first day of last month'));

// Build dynamic SQL queries to sync filter parameters
$where_clauses = [];
$params = [];

if ($filter_mahallah !== 'All') {
    $where_clauses[] = "mahallah = ?";
    $params[] = $filter_mahallah;
}
if ($filter_status !== 'All') {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}
// MODIFICATION: Check for Paid vs Unpaid status by evaluating the latest entry inside the chanda_payments ledger table
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
            (SELECT cp.paid_to 
             FROM chanda_payments cp 
             WHERE cp.member_id = m.id 
             ORDER BY cp.paid_to DESC LIMIT 1) IS NULL 
            OR 
            (SELECT cp.paid_to 
             FROM chanda_payments cp 
             WHERE cp.member_id = m.id 
             ORDER BY cp.paid_to DESC LIMIT 1) < ?
        )";
        $params[] = $prev_month_boundary;
    }
}
if (!empty($search)) {
    $where_clauses[] = "(first_name LIKE ? OR last_name LIKE ? OR family_name LIKE ? OR card_no LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// MODIFICATION: Appended table alias 'm' to ensure subquery 'm.id' validation paths evaluate correctly
$count_stmt = $db->prepare("SELECT COUNT(*) FROM members m $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1)
    $total_pages = 1;
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch paginated member subset with bundled relational Chanda history lists
$fetch_stmt = $db->prepare("
    SELECT m.*, 
           (SELECT cp.paid_from FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) AS chanda_paid_from,
           (SELECT cp.paid_to FROM chanda_payments cp WHERE cp.member_id = m.id ORDER BY cp.paid_to DESC LIMIT 1) AS chanda_paid_to,
           (SELECT GROUP_CONCAT(CONCAT(
                cp.id, '|', 
                cp.paid_from, '|', 
                cp.paid_to, '|', 
                cp.total_amount, '|', 
                cp.recorded_by, '|', 
                DATE_FORMAT(cp.date_recorded, '%Y-%m-%d %H:%i:%s'), '|', 
                cp.payment_mode, '|', 
                IFNULL(cp.payment_narrative, ''), '|', 
                cp.paid_by_self, '|',
                IFNULL(cp.third_party_name, ''), '|',
                IFNULL(cp.third_party_phone, '')
            ) ORDER BY cp.paid_to DESC SEPARATOR '||') 
            FROM chanda_payments cp 
            WHERE cp.member_id = m.id) AS chanda_history_raw
    FROM members m 
    $where_sql 
    ORDER BY m.date_added DESC 
    LIMIT $limit OFFSET $offset
");
$fetch_stmt->execute($params);
$members = $fetch_stmt->fetchAll();

// Fetch dependents for the retrieved members to avoid nested queries inside loops
$member_ids = array_column($members, 'id');
$dependents_by_member = [];
if (!empty($member_ids)) {
    $in_clause = implode(',', array_fill(0, count($member_ids), '?'));
    $dep_stmt = $db->prepare("SELECT * FROM member_dependents WHERE member_id IN ($in_clause) ORDER BY id ASC");
    $dep_stmt->execute($member_ids);
    $all_deps = $dep_stmt->fetchAll();
    foreach ($all_deps as $dep) {
        $dependents_by_member[$dep['member_id']][] = $dep;
    }
}

// Map dependents array back into each parent member object
foreach ($members as &$m_ref) {
    $m_ref['dependents'] = isset($dependents_by_member[$m_ref['id']]) ? $dependents_by_member[$m_ref['id']] : [];
}
unset($m_ref); // Free reference

$wards_list = ["Ward 1", "Ward 2", "Ward 3", "Ward 4", "Ward 5", "Ward 6"];

// Fetch already assigned leadership roles
$assigned_roles = $db->query("
    SELECT DISTINCT designation 
    FROM members 
    WHERE status != 'Deceased' 
    AND designation IN ('President', 'Vice President', 'Secretary', 'Joint-Secretary', 'Treasurer')
")->fetchAll(PDO::FETCH_COLUMN);

// Badge styling
$designation_colors = [
    'President' => 'bg-rose-50 text-rose-700 border-rose-200/60',
    'Vice President' => 'bg-amber-50 text-amber-700 border-amber-200/60',
    'Secretary' => 'bg-sky-50 text-sky-700 border-sky-200/60',
    'Joint-Secretary' => 'bg-indigo-50 text-indigo-700 border-indigo-200/60',
    'Treasurer' => 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
    'Executive Member' => 'bg-purple-50 text-purple-700 border-purple-200/60',
    'Ordinary Member' => 'bg-slate-50 text-slate-600 border-slate-200/60'
];

require_once 'header.php';
?>

<?php if (isset($_GET['error'])): ?>
    <div
        class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-900 rounded-xl text-xs font-bold flex items-center gap-2.5 shadow-xs animate-pulse">
        <div class="bg-rose-600 text-white w-5 h-5 rounded-full flex items-center justify-center font-black">!</div>
        <div>
            <span class="block font-black text-[13px] text-rose-950">Error</span>
            <p class="text-xs font-medium text-rose-800/90 mt-0.5"><?php echo htmlspecialchars($_GET['error']); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
    <div
        class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-xl text-xs font-bold flex items-center gap-2.5 shadow-xs">
        <div class="bg-emerald-600 text-white w-5 h-5 rounded-full flex items-center justify-center font-black">✓</div>
        <div>
            <span class="block font-black text-[13px] text-emerald-950">Operation Successful</span>
            <p class="text-xs font-medium text-emerald-800/90 mt-0.5"><?php echo htmlspecialchars($_GET['msg']); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="flex items-center justify-between border-b border-slate-200 mb-6 pb-1">
    <div class="flex gap-2 text-xs font-bold uppercase tracking-wider">
        <button onclick="switchMembersTab('directory')" id="tab-btn-directory"
            class="px-4 py-2.5 rounded-t-xl transition-all border-b-2 border-emerald-700 text-emerald-800 bg-emerald-50/50 flex items-center gap-2">
            <i class="fa-solid fa-address-book"></i> Member Directory
        </button>
        <button onclick="switchMembersTab('reports')" id="tab-btn-reports"
            class="px-4 py-2.5 rounded-t-xl transition-all border-b-2 border-transparent text-slate-500 hover:text-slate-700 flex items-center gap-2">
            <i class="fa-solid fa-chart-pie"></i> Report Generation
        </button>
    </div>
</div>

<!-- Directory container -->
<div id="members-tab-directory" class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800">Jamaath Register Directory</h3>
                <p class="text-xs text-slate-500">Complete listing of families and individuals with automatically
                    calculated ages. Click any row to view complete profile card details.</p>
            </div>

            <!-- Filters and Registration Trigger Form -->
            <form method="GET" action="" id="filter-form" class="flex flex-wrap gap-2.5">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                <select name="mahallah" onchange="document.getElementById('filter-form').submit()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Mahallahs</option>
                    <?php foreach ($wards_list as $w_opt): ?>
                        <option value="<?php echo $w_opt; ?>" <?php echo $filter_mahallah == $w_opt ? 'selected' : ''; ?>>
                            <?php echo $w_opt; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="document.getElementById('filter-form').submit()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All" <?php echo $filter_status == 'All' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="Alive" <?php echo $filter_status == 'Alive' ? 'selected' : ''; ?>>Alive</option>
                    <option value="Deceased" <?php echo $filter_status == 'Deceased' ? 'selected' : ''; ?>>Deceased
                        (Marhoom)</option>
                </select>
                <select name="chanda" onchange="document.getElementById('filter-form').submit()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All" <?php echo $filter_chanda == 'All' ? 'selected' : ''; ?>>All Chanda</option>
                    <option value="Paid" <?php echo $filter_chanda == 'Paid' ? 'selected' : ''; ?>>Paid Only (Up to Date)
                    </option>
                    <option value="Unpaid" <?php echo $filter_chanda == 'Unpaid' ? 'selected' : ''; ?>>Unpaid / Pending
                    </option>
                </select>

                <button type="button" onclick="toggleAddMemberForm()"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-sm transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus"></i> <span id="toggle-form-btn-text">Register New Member</span>
                </button>
            </form>
        </div>

        <!-- Add/Edit Member slide-down segment -->
        <div id="add-member-form-section" class="hidden mb-8 border-t border-slate-100 pt-6">
            <div class="max-w-3xl mx-auto bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden">
                <div
                    class="bg-gradient-to-r from-emerald-800 to-teal-900 p-5 text-white flex justify-between items-center">
                    <div>
                        <h4 id="form-console-title" class="font-bold text-sm">Register New Jamaath Member Console</h4>
                        <p class="text-[11px] text-emerald-200">Log demographic and localization parameters.</p>
                    </div>
                    <button type="button" id="form-reset-btn" onclick="resetFormToCreateState()"
                        class="hidden bg-white/20 hover:bg-white/35 text-white font-bold text-[10px] px-2.5 py-1 rounded">
                        <i class="fa-solid fa-rotate mr-1"></i> Switch to Register Form
                    </button>
                </div>
                <form id="member-master-form" method="POST" action="actions.php" enctype="multipart/form-data"
                    class="p-5 space-y-4 text-xs">
                    <!-- Form actions router -->
                    <input type="hidden" name="action" id="form-action-field" value="add_member">
                    <input type="hidden" name="id" id="form-member-id-field" value="">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div
                            class="flex flex-col items-center justify-center p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
                            <label
                                class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-2">Member
                                Photo</label>

                            <!-- Professional Dynamic Placeholder Container -->
                            <div class="w-32 h-32 rounded-2xl border border-slate-200 overflow-hidden bg-slate-50 flex flex-col items-center justify-center text-center relative group shadow-inner mb-3"
                                id="member-photo-container">
                                <!-- Hidden by default until an image is loaded or edited -->
                                <img id="photo-preview" src=""
                                    class="hidden w-full h-full object-cover absolute inset-0">

                                <!-- Visible Placeholder Block -->
                                <div id="photo-placeholder"
                                    class="flex flex-col items-center justify-center text-slate-400 space-y-1 p-2">
                                    <i class="fa-solid fa-user-tie text-3xl text-slate-300"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider leading-tight">No
                                        Image<br>Uploaded</span>
                                </div>
                            </div>

                            <!-- The actual file input tag -->
                            <label
                                class="bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 font-bold text-xs px-4 py-2 rounded-xl cursor-pointer flex items-center gap-1.5 transition-all shadow-sm">
                                <i class="fa-solid fa-camera text-slate-400"></i> Upload Photo
                                <input type="file" name="photo" accept="image/*" class="hidden"
                                    onchange="previewMemberImageOnSelect(event)">
                            </label>
                            <p id="photo_requirement_note"
                                class="text-[9px] text-red-700 text-center mt-2 max-w-[140px] leading-tight">Max size:
                                5MB.</p>
                        </div>

                        <div class="md:col-span-2 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">First Name *</label>
                                    <input type="text" name="first_name" id="field_first_name" required
                                        placeholder="First Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Last Name *</label>
                                    <input type="text" name="last_name" id="field_last_name" required
                                        placeholder="Last Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Family Name</label>
                                    <input type="text" name="family_name" id="field_family_name"
                                        placeholder="e.g. Kottar House"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Card No (Attai No) *</label>
                                    <input type="text" name="card_no" id="field_card_no" required
                                        placeholder="e.g. K-104"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Dependents (Count) *</label>
                                    <input type="number" id="dependents_count" name="dependents_count"
                                        oninput="generateDependentFields(this.value)" required min="0" max="15"
                                        value="0"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic relational dependents segment -->
                    <div id="dependents-dynamic-container"
                        class="hidden bg-slate-100/60 p-4 rounded-xl border border-slate-200 space-y-3">
                        <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-people-roof text-indigo-700"></i> Dependent Demographic Details
                        </h5>
                        <p class="text-[10px] text-slate-500">Provide biographical attributes for all dependents
                            configured above.</p>

                        <div id="dependents-grid-fields" class="space-y-3">
                            <!-- Injected dynamically via JS -->
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Date of Birth *</label>
                            <input type="date" name="dob" id="field_dob" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            <p id="dob-error-msg" class="text-xs text-red-500 mt-1 hidden font-medium"></p>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Gender *</label>
                            <select name="gender" id="field_gender" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Marital Status *</label>
                            <select name="marital_status" id="field_marital_status" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Father Name *</label>
                            <input type="text" name="father_husband_name" id="field_father_name" required
                                placeholder="Name"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Mahallah / Ward *</label>
                            <select name="mahallah" id="field_mahallah" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="">Select Mahallah</option>
                                <?php foreach ($wards_list as $w_nm): ?>
                                    <option value="<?php echo $w_nm; ?>"><?php echo $w_nm; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Phone Number *</label>
                            <input type="tel" id="field_phone" required placeholder="Phone"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            <input type="hidden" name="phone" id="field_phone_full">
                        </div>
                    </div>

                    <div class="bg-slate-100/60 p-4 rounded-xl border border-slate-200/80 space-y-3">
                        <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-id-card text-emerald-700 text-sm"></i> Identity Verification
                        </h5>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">

                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 tracking-wide">Aadhaar Card Number
                                    *</label>
                                <input type="text" name="aadhar_number" id="field_aadhar_number" required
                                    placeholder="12-Digit Aadhaar Number" max-length="12"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm h-[40px] focus:ring-1 focus:ring-emerald-500 focus:outline-none transition-all">
                                <p id="aadhar-error-msg" class="text-[11px] text-red-500 mt-1 hidden font-medium"></p>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 tracking-wide">Upload Aadhaar Copy
                                    *</label>
                                <div class="flex flex-col gap-1.5">
                                    <label
                                        class="w-full text-center bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold text-xs px-4 rounded-xl cursor-pointer flex items-center justify-center gap-1.5 transition-all shadow-sm h-[40px]">
                                        <i class="fa-solid fa-file-arrow-up text-slate-400 text-sm"></i> Choose Document
                                        <input type="file" name="aadhar_doc" id="field_aadhar_doc"
                                            accept="image/*,application/pdf" class="hidden"
                                            onchange="previewAadharDocument(this)">
                                    </label>
                                    <div class="flex flex-col text-center px-1">
                                        <span id="aadhar-file-label"
                                            class="text-[11px] text-slate-500 font-medium truncate max-w-xs block">No
                                            file selected</span>
                                        <span class="text-[9px] text-slate-400 font-semibold tracking-wide">Max size
                                            limit: 2MB (PDF or Image)</span>
                                    </div>
                                </div>
                                <p id="aadhar-file-error-msg" class="text-[11px] text-red-500 mt-1 hidden font-medium">
                                </p>
                            </div>

                            <div class="space-y-1">
                                <span class="block text-[11px] font-bold text-slate-500 tracking-wide">Document
                                    Preview</span>
                                <div class="flex flex-col items-center">
                                    <div id="aadhar-preview-box"
                                        class="w-full h-[65px] rounded-xl border border-slate-200 bg-white shadow-inner flex flex-col items-center justify-center text-center p-1.5 overflow-hidden relative">
                                        <div id="aadhar-preview-placeholder"
                                            class="text-slate-300 flex items-center gap-1.5">
                                            <i class="fa-solid fa-file-invoice text-lg"></i>
                                            <span
                                                class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">No
                                                Document</span>
                                        </div>
                                        <img id="aadhar-img-preview" src=""
                                            class="hidden w-full h-full object-cover rounded-lg">
                                        <div id="aadhar-pdf-preview"
                                            class="hidden text-red-600 flex items-center gap-1.5">
                                            <i class="fa-solid fa-file-pdf text-xl"></i>
                                            <span
                                                class="text-[10px] font-extrabold uppercase text-slate-600 tracking-wide">PDF
                                                Loaded</span>
                                        </div>
                                    </div>
                                    <div id="existing-aadhar-container" class="hidden mt-1.5 w-full text-center">
                                        <a id="existing-aadhar-link" href="#" target="_blank"
                                            class="text-[11px] text-emerald-700 font-bold hover:underline inline-flex items-center gap-1 transition-all">
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Open
                                            Saved File
                                        </a>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Blood Group</label>
                            <select name="blood_group" id="field_blood_group"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="">Select Group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Primary
                                Occupation *</label>
                            <select name="occupation" id="field_occupation" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="" disabled selected>-- Select Occupation --</option>
                                <option value="Business / Merchant">Business / Merchant</option>
                                <option value="Private Sector Employee">Private Sector Employee</option>
                                <option value="Government Employee">Government Employee</option>
                                <option value="Daily Wage / Laborer">Daily Wage / Laborer</option>
                                <option value="Professional (Doctor/Engineer/Lawyer)">Professional
                                    (Doctor/Engineer/Lawyer)</option>
                                <option value="Driver / Transport Worker">Driver / Transport Worker</option>
                                <option value="Retired / Pensioner">Retired / Pensioner</option>
                                <option value="Student">Student</option>
                                <option value="Unemployed">Unemployed</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Designation within Jamaath *</label>
                            <select name="designation" id="field_designation" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Ordinary Member">Ordinary Member</option>
                                <?php
                                $possible_roles = ["President", "Vice President", "Secretary", "Joint-Secretary", "Treasurer", "Executive Member"];
                                foreach ($possible_roles as $role):
                                    $is_assigned = in_array($role, $assigned_roles);
                                    $disabled_attr = $is_assigned ? 'disabled data-occupied="true" class="text-slate-400 bg-slate-100"' : '';
                                    $display_name = $role . ($is_assigned ? ' (Already Assigned)' : '');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $disabled_attr; ?>>
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="bg-slate-100/50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-house text-emerald-700"></i> Residential Address
                        </h5>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Address
                                    Line 1 *</label>
                                <input type="text" id="res_address_line1" name="res_address_line1" required
                                    placeholder="Street / Door No"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Address
                                    Line 2</label>
                                <input type="text" id="res_address_line2" name="res_address_line2"
                                    placeholder="Locality / Landmark"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">City
                                    *</label>
                                <input type="text" id="res_city" name="res_city" required placeholder="e.g. Nagercoil"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pincode
                                    *</label>
                                <input type="text" id="res_pincode" name="res_pincode" required
                                    placeholder="e.g. 629002"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Country
                                    *</label>
                                <input type="text" id="res_country" name="res_country" required placeholder="e.g. India"
                                    value="India"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-100/50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-envelope-open-text text-teal-700"></i> Communication Address
                            </h5>
                            <label
                                class="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 cursor-pointer select-none">
                                <input type="checkbox" id="same-address-check" onchange="syncAddresses()"
                                    class="h-3.5 w-3.5 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300">
                                Same as Residential Address
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Address
                                    Line 1 *</label>
                                <input type="text" id="comm_address_line1" name="comm_address_line1" required
                                    placeholder="Street / Door No"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Address
                                    Line 2</label>
                                <input type="text" id="comm_address_line2" name="comm_address_line2"
                                    placeholder="Locality / Landmark"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">City
                                    *</label>
                                <input type="text" id="comm_city" name="comm_city" required placeholder="e.g. Nagercoil"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pincode
                                    *</label>
                                <input type="text" id="comm_pincode" name="comm_pincode" required
                                    placeholder="e.g. 629002"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Country
                                    *</label>
                                <input type="text" id="comm_country" name="comm_country" required
                                    placeholder="e.g. India" value="India"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Life Status *</label>
                            <select name="status" id="field_status" onchange="toggleFormDeceasedDate(this.value)"
                                required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Alive">Alive</option>
                                <option value="Deceased">Deceased (Marhoom)</option>
                            </select>
                        </div>
                        <div id="form-deceased-date-container" class="hidden">
                            <label class="block font-semibold text-slate-600 mb-1">Demise Date</label>
                            <input type="date" name="deceased_date" id="form-deceased-date-field"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" onclick="toggleAddMemberForm()"
                            class="bg-slate-200 text-slate-700 px-4 py-2 rounded-lg font-bold">Close Panel</button>
                        <button type="submit" id="form-submit-btn"
                            class="bg-emerald-700 text-white px-5 py-2 rounded-lg font-bold shadow-sm">Register
                            Member</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Integrated Server-Side Filter Search Bar with Explicit Search & Clear Controls -->
        <form method="GET" action="" class="flex gap-2 mb-6">
            <input type="hidden" name="mahallah" value="<?php echo htmlspecialchars($filter_mahallah); ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
            <input type="hidden" name="chanda" value="<?php echo htmlspecialchars($filter_chanda); ?>">

            <div class="relative flex-grow">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search members by Name, Family Card, Family Name, Phone..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none transition-all">
            </div>

            <button type="submit"
                class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-5 py-3 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-magnifying-glass"></i> <span>Search</span>
            </button>

            <?php if (!empty($search)): ?>
                <a href="?mahallah=<?php echo urlencode($filter_mahallah); ?>&status=<?php echo urlencode($filter_status); ?>&chanda=<?php echo urlencode($filter_chanda); ?>"
                    class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-4 py-3 rounded-xl transition-all flex items-center justify-center"
                    title="Clear Search">
                    Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Members Directory Table Grid -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr
                        class="bg-slate-50/50 border-b border-slate-200 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-4 px-4 rounded-l-xl">Member Name & Age</th>
                        <th class="py-4 px-4">Card / Attai No</th>
                        <th class="py-4 px-4 text-right rounded-r-xl">Actions</th>
                    </tr>
                </thead>
                <tbody id="members-table-rows" class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($members)): ?>
                        <tr id="empty-members-row">
                            <td colspan="3" class="py-12 text-center text-slate-400 text-xs">No registered members matching
                                current search criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member):
                            $age = calculateAge($member['dob']);
                            $role_clean = !empty($member['designation']) ? $member['designation'] : 'Ordinary Member';
                            $badge_class = isset($designation_colors[$role_clean]) ? $designation_colors[$role_clean] : $designation_colors['Ordinary Member'];
                            ?>
                            <!-- Row Click targets Interactive Profile Card popup -->
                            <tr onclick='openProfileCard(<?php echo json_encode($member); ?>)'
                                class="member-record-row hover:bg-slate-50/75 transition-colors cursor-pointer">
                                <td class="py-4 px-4 flex items-center space-x-3">
                                    <img src="<?php echo htmlspecialchars($member['photo']); ?>"
                                        class="w-16 h-16 rounded-full border border-slate-200 object-cover shadow-sm"
                                        onerror="this.src='[https://placehold.co/150x150/0f766e/ffffff?text=](https://placehold.co/150x150/0f766e/ffffff?text=)<?php echo urlencode($member['first_name']); ?>'">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-1.5 leading-tight">
                                            <p class="font-bold text-slate-800 leading-tight">
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                <?php if (!empty($member['family_name'])): ?>
                                                    <span
                                                        class="text-xs text-slate-400 font-normal tracking-wide">(<?php echo htmlspecialchars($member['family_name']); ?>)</span>
                                                <?php endif; ?>
                                            </p>

                                            <?php if ($role_clean !== 'Ordinary Member'): ?>
                                                <span
                                                    class="inline-block text-[9px] font-bold px-2 py-0.5 rounded-full border tracking-wide uppercase <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($role_clean); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[11px] text-slate-500 font-semibold flex items-center gap-1 mt-1">
                                            <span class="bg-teal-50 text-teal-800 px-1.5 py-0.2 rounded font-mono">Age:
                                                <?php echo $age; ?></span>
                                            <span class="text-slate-400">S/O:
                                                <?php echo htmlspecialchars($member['father_husband_name']); ?></span>
                                        </p>
                                    </div>
                                </td>
                                <td class="py-4 px-4 font-mono font-bold text-slate-600 text-xs">
                                    <?php echo htmlspecialchars($member['card_no']); ?>
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <div onclick="event.stopPropagation()" class="flex items-center justify-end gap-1.5">
                                        <!-- View Card Shortcut -->
                                        <button onclick='openProfileCard(<?php echo json_encode($member); ?>)'
                                            class="bg-slate-50 hover:bg-slate-100 text-slate-600 p-1.5 rounded-lg border border-slate-200 text-xs transition-colors"
                                            title="View Profile Card">
                                            <i class="fa-solid fa-address-card text-emerald-700"></i>
                                        </button>

                                        <!-- Edit Action -->
                                        <button onclick='populateEditForm(<?php echo json_encode($member); ?>)'
                                            class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200 text-xs transition-colors"
                                            title="Edit Profile">
                                            <i class="fa-solid fa-user-gear"></i>
                                        </button>

                                        <!-- Delete Action -->
                                        <form method="POST" action="actions.php"
                                            onsubmit="return confirm('Are you sure you want to delete this member permanently? Dependents will also be removed. This cannot be undone.');"
                                            class="inline">
                                            <input type="hidden" name="action" value="delete_member">
                                            <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                            <button type="submit"
                                                class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors"
                                                title="Delete Permanent">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination UI -->
        <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between border-t border-slate-100 pt-5 mt-5">
                <p class="text-xs text-slate-500">Showing page <span
                        class="font-bold text-slate-800"><?php echo $page; ?></span> of <span
                        class="font-bold text-slate-800"><?php echo $total_pages; ?></span> pages</p>
                <div class="flex gap-1 text-xs">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&mahallah=<?php echo urlencode($filter_mahallah); ?>&status=<?php echo urlencode($filter_status); ?>&chanda=<?php echo urlencode($filter_chanda); ?>&search=<?php echo urlencode($search); ?>"
                            class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold text-slate-700 hover:bg-slate-100 transition-colors">&laquo;
                            Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&mahallah=<?php echo urlencode($filter_mahallah); ?>&status=<?php echo urlencode($filter_status); ?>&chanda=<?php echo urlencode($filter_chanda); ?>&search=<?php echo urlencode($search); ?>"
                            class="px-3 py-1.5 rounded-lg font-semibold border transition-all <?php echo $i == $page ? 'bg-emerald-700 border-emerald-700 text-white' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&mahallah=<?php echo urlencode($filter_mahallah); ?>&status=<?php echo urlencode($filter_status); ?>&chanda=<?php echo urlencode($filter_chanda); ?>&search=<?php echo urlencode($search); ?>"
                            class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold text-slate-700 hover:bg-slate-100 transition-colors">Next
                            &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="members-tab-reports" class="hidden space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-800">Registry Report Generator</h3>
                <p class="text-xs text-slate-500">Generate, review, and print customized analytical documentation
                    datasets across your registry criteria parameters.</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="triggerIframePrint()"
                    class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-print"></i> Print Report Layout
                </button>

                <button onclick="triggerIframeExcelExport()"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-file-excel"></i> Export Excel Sheet
                </button>
            </div>
        </div>

        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Search
                    Keyword</label>
                <input type="text" id="rep-filter-search" oninput="reloadReportEngine()"
                    placeholder="Name, Card No, House Name..."
                    class="w-full bg-white border border-slate-200 rounded-lg p-1.5 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Mahallah</label>
                <select id="rep-filter-mahallah" onchange="reloadReportEngine()"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Mahallahs</option>
                    <?php foreach ($wards_list as $w_opt): ?>
                        <option value="<?php echo htmlspecialchars($w_opt); ?>"><?php echo htmlspecialchars($w_opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Membership
                    Status</label>
                <select id="rep-filter-status" onchange="reloadReportEngine()"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Statuses</option>
                    <option value="Alive">Alive</option>
                    <option value="Deceased">Deceased</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Chanda
                    Status</label>
                <select id="rep-filter-chanda" onchange="reloadReportEngine()"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Records</option>
                    <option value="Paid">Paid Only (Up to Date)</option>
                    <option value="Unpaid">Unpaid / Delinquent</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Designation
                    Role</label>
                <select id="rep-filter-designation" onchange="reloadReportEngine()"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Designations</option>
                    <option value="Ordinary Member">Ordinary Member</option>
                    <?php
                    if (isset($designation_colors)) {
                        foreach (array_keys($designation_colors) as $designation) {
                            if ($designation !== 'Ordinary Member') {
                                echo '<option value="' . htmlspecialchars($designation) . '">' . htmlspecialchars($designation) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Occupation
                    Filter</label>
                <select id="rep-filter-occupation" onchange="reloadReportEngine()"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Occupations</option>
                    <option value="Business / Merchant">Business / Merchant</option>
                    <option value="Private Sector Employee">Private Sector Employee</option>
                    <option value="Government Employee">Government Employee</option>
                    <option value="Daily Wage / Laborer">Daily Wage / Laborer</option>
                    <option value="Professional (Doctor/Engineer/Lawyer)">Professional (Doctor/Engineer/Lawyer)</option>
                    <option value="Driver / Transport Worker">Driver / Transport Worker</option>
                    <option value="Retired / Pensioner">Retired / Pensioner</option>
                    <option value="Student">Student</option>
                    <option value="Unemployed">Unemployed</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="w-full border border-slate-200 rounded-xl overflow-hidden bg-slate-100 shadow-inner"
            style="height: 680px;">
            <iframe id="member-report-frame" src="about:blank" class="w-full h-full bg-white border-none"></iframe>
        </div>
    </div>
</div>

<!-- Modal Dialog: Interactive Profile Details Card Pop-up -->
<div id="profile-card-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div
        class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">

        <div class="bg-gradient-to-r from-emerald-800 to-teal-950 p-6 text-white relative">
            <button onclick="closeProfileCard()"
                class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors text-lg">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>

            <div class="flex items-center space-x-4">
                <img id="card-photo" src=""
                    class="w-28 h-28 rounded-full border-2 border-white/80 object-cover shadow-md" alt="Avatar">
                <div>
                    <div class="flex items-center gap-2">
                        <h4 id="card-fullname" class="text-lg font-bold serif-title leading-none">---</h4>
                        <span id="card-family-name" class="text-xs text-emerald-200 font-medium">---</span>
                    </div>
                    <p class="text-xs text-emerald-200 mt-1.5 flex items-center gap-1">
                        <span id="card-designation-badge" class="hidden"></span>
                        <span class="bg-white/10 px-2 py-0.5 rounded text-[10px] font-mono">Card: <span
                                id="card-card-no"></span></span>
                        <span class="bg-white/10 px-2 py-0.5 rounded text-[10px] font-mono">Age: <span
                                id="card-age"></span></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5 overflow-y-auto text-xs text-slate-700">

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Father Name</p>
                    <p id="card-father" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Date of Birth</p>
                    <p id="card-dob" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Gender</p>
                    <p id="card-gender" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Marital Status</p>
                    <p id="card-marital" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-phone text-emerald-700"></i> Contact Phone
                    </p>
                    <p id="card-phone" class="font-bold text-slate-800 mt-1">---</p>
                </div>
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-map-pin text-teal-700"></i> Mahallah Ward
                    </p>
                    <p id="card-mahallah" class="font-bold text-slate-800 mt-1">---</p>
                </div>
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-briefcase text-slate-600"></i> Occupation
                    </p>
                    <p id="card-occupation" class="font-bold text-slate-800 mt-1">---</p>
                </div>
                <div class="p-3 border border-slate-150 rounded-xl bg-slate-50/50 border-dashed">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-id-card text-emerald-800"></i> Aadhaar Identity
                    </p>
                    <div class="mt-1 flex flex-col gap-1">
                        <p id="card-aadhar-num" class="font-mono font-bold text-slate-800">---</p>
                        <div id="card-aadhar-doc-container">
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-droplet text-rose-600"></i> Blood Group
                    </p>
                    <p id="card-blood" class="font-bold text-slate-800 mt-1">---</p>
                </div>
                <div class="p-3 border border-slate-150 rounded-xl flex items-center justify-around col-span-2">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Life Status</p>
                        <span id="card-status-badge" class="">---</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Chanda Status
                            (Overall)</p>
                        <span id="card-chanda-badge" class="">---</span>
                    </div>
                </div>
            </div>

            <div class="bg-teal-50/50 p-4 rounded-xl border border-teal-150 space-y-3">
                <p class="text-[10px] font-bold text-teal-900 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-calendar-check text-teal-700"></i> Monthly Subscription (Chanda) History
                    Mappings
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-white p-3 rounded-lg border border-slate-100">
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Completed Paid
                            Period</span>
                        <p id="card-chanda-paid-period" class="font-semibold text-xs text-emerald-800 mt-0.5">N/A</p>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Outstanding Due
                            Months</span>
                        <p id="card-chanda-pending-period" class="font-semibold text-xs text-rose-700 mt-0.5">N/A</p>
                    </div>
                </div>

                <form id="card-chanda-form" method="POST" action="actions.php"
                    class="p-3 bg-teal-800 text-white rounded-lg space-y-2">
                    <input type="hidden" name="action" value="update_chanda_period">
                    <input type="hidden" name="id" id="chanda-member-id-field">

                    <p class="text-[9px] font-bold uppercase tracking-wider text-teal-200">Record Subscription Payments
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-slate-800">
                        <div>
                            <label class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Paid
                                From *</label>
                            <input type="month" name="chanda_paid_from" id="chanda_paid_from_input" required
                                class="w-full bg-white rounded p-1 text-[11px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Paid
                                To *</label>
                            <input type="month" name="chanda_paid_to" id="chanda_paid_to_input" required
                                class="w-full bg-white rounded p-1 text-[11px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Total
                                Paid (₹) *</label>
                            <input type="number" name="total_amount" id="chanda_total_amount_input" min="150"
                                step="0.01" placeholder="150.00" required
                                class="w-full bg-white rounded p-1 text-[11px] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-800 pt-1">
                        <div>
                            <label
                                class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Payment
                                Mode *</label>
                            <select name="payment_mode" id="chanda_payment_mode" required
                                onchange="toggleChandaNarrativeField();"
                                class="w-full bg-white rounded p-1 text-[11px] focus:outline-none appearance-none">
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Depositor
                                / Paid By *</label>
                            <div
                                class="flex items-center gap-4 bg-teal-900/40 p-1 rounded border border-teal-700/60 h-[26px] px-2 text-teal-100">
                                <label
                                    class="inline-flex items-center gap-1.5 text-[10px] font-bold cursor-pointer select-none">
                                    <input type="radio" name="paid_by_self" value="1" checked
                                        onclick="toggleChandaPayerFields(true);" class="accent-amber-500 scale-90">
                                    Member Self
                                </label>
                                <label
                                    class="inline-flex items-center gap-1.5 text-[10px] font-bold cursor-pointer select-none">
                                    <input type="radio" name="paid_by_self" value="0"
                                        onclick="toggleChandaPayerFields(false);" class="accent-amber-500 scale-90">
                                    Someone Else
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="chanda_third_party_wrapper"
                        class="hidden grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-800 pt-1 transition-all duration-200">
                        <div>
                            <label class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Payer
                                Full Name *</label>
                            <input type="text" name="third_party_name" id="chanda_third_party_name"
                                placeholder="Enter payer's name"
                                class="w-full bg-white text-slate-800 rounded p-1.5 text-[11px] focus:outline-none placeholder:text-slate-400">
                        </div>
                        <div>
                            <label class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Payer
                                Contact Number *</label>
                            <input type="tel" id="chanda_third_party_phone" name="third_party_phone"
                                class="w-full bg-white text-slate-800 rounded p-1.5 text-[11px] focus:outline-none">
                        </div>
                    </div>

                    <div id="chanda_narrative_wrapper" class="hidden transition-all duration-200">
                        <label id="chanda_narrative_label"
                            class="block text-[8px] font-bold text-teal-100 uppercase tracking-wider mb-1">Transaction
                            Reference / Narrative *</label>
                        <input type="text" name="payment_narrative" id="chanda_payment_narrative"
                            placeholder="Enter UPI Transaction ID or Cheque Number"
                            class="w-full bg-white text-slate-800 rounded p-1.5 text-[11px] focus:outline-none placeholder:text-slate-400">
                    </div>

                    <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-1.5 rounded text-[10px] uppercase tracking-wide transition-colors">
                        Save Subscription Mapping
                    </button>
                </form>

                <div class="mt-4 pt-3 border-t border-teal-100 space-y-1.5">
                    <p class="text-[9px] font-bold uppercase tracking-wider text-teal-900">Ledger Audit History Logs</p>
                    <div class="overflow-x-auto rounded-lg border border-slate-100 max-h-40 overflow-y-auto">
                        <table class="w-full text-left text-[11px] bg-white">
                            <thead
                                class="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-bold sticky top-0">
                                <tr>
                                    <th class="p-2">Period Range</th>
                                    <th class="p-2">Amount</th>
                                    <th class="p-2">Recorded By</th>
                                    <th class="p-2">Date & Time Saved</th>
                                </tr>
                            </thead>
                            <tbody id="card-chanda-history-rows" class="divide-y divide-slate-100 text-slate-700">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="card-dependents-container" class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 hidden">
                <p
                    class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5 mb-2">
                    <i class="fa-solid fa-people-roof text-indigo-700"></i> Family Dependents Detailed List
                </p>
                <div id="card-dependents-list" class="divide-y divide-indigo-100 text-xs"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-200">
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 mb-2">
                        <i class="fa-solid fa-house text-emerald-700"></i> Residential Address
                    </p>
                    <p id="card-res-address" class="text-slate-600 leading-relaxed font-semibold">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-200">
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 mb-2">
                        <i class="fa-solid fa-envelope-open-text text-teal-700"></i> Communication Address
                    </p>
                    <p id="card-comm-address" class="text-slate-600 leading-relaxed font-semibold">---</p>
                </div>
            </div>

        </div>

        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between">
            <button id="card-edit-btn"
                class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-sm transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-user-gear"></i> Update Record
            </button>
            <button onclick="closeProfileCard()"
                class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-5 py-2 rounded-xl transition-all">
                Close Profile
            </button>
        </div>

    </div>
</div>

<script>
    let iti; // Global reference for international telephone module

    // --- 1. GLOBAL WINDOW FUNCTIONS (Accessible by inline HTML triggers) ---

    window.toggleAddMemberForm = function () {
        const formSec = document.getElementById('add-member-form-section');
        const btnText = document.getElementById('toggle-form-btn-text');
        if (formSec) {
            if (formSec.classList.contains('hidden')) {
                formSec.classList.remove('hidden');
                if (btnText) btnText.textContent = "Close Panel Form";
            } else {
                formSec.classList.add('hidden');
                if (btnText) btnText.textContent = "Register New Member";
                resetFormToCreateState();
            }
        }
    };

    window.previewAadharDocument = function (input) {
        const label = document.getElementById("aadhar-file-label");
        const fileError = document.getElementById("aadhar-file-error-msg");
        const placeholder = document.getElementById("aadhar-preview-placeholder");
        const imgPreview = document.getElementById("aadhar-img-preview");
        const pdfPreview = document.getElementById("aadhar-pdf-preview");

        // Clear dynamic operational previews back to clean structural defaults
        if (imgPreview) { imgPreview.classList.add("hidden"); imgPreview.src = ""; }
        if (pdfPreview) pdfPreview.classList.add("hidden");
        if (placeholder) placeholder.classList.remove("hidden");
        if (fileError) fileError.classList.add("hidden");

        if (input.files && input.files[0]) {
            const file = input.files[0];

            // STRICTOR 2MB FILE SIZE GUARD (2 * 1024 * 1024 = 2,097,152 Bytes)
            const maxLimitBytes = 2 * 1024 * 1024;
            if (file.size > maxLimitBytes) {
                if (fileError) {
                    fileError.textContent = "Error: File size is too large. Maximum size allowed is 2MB.";
                    fileError.classList.remove("hidden");
                }
                if (label) label.textContent = "Selection rejected (Oversized)";
                input.value = ""; // Clear file stream element reference
                return;
            }

            if (label) label.textContent = file.name;
            if (placeholder) placeholder.classList.add("hidden");

            if (file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    if (imgPreview) {
                        imgPreview.src = e.target.result;
                        imgPreview.classList.remove("hidden");
                    }
                };
                reader.readAsDataURL(file);
            } else if (file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf")) {
                if (pdfPreview) pdfPreview.classList.remove("hidden");
            } else {
                if (placeholder) placeholder.classList.remove("hidden");
            }
        } else {
            if (label) label.textContent = "No file selected";
        }
    };

    window.resetFormToCreateState = function () {
        const formElement = document.getElementById('member-master-form');
        if (formElement) formElement.reset();

        // Clear and toggle preview elements back to placeholder states safely
        const preview = document.getElementById('photo-preview');
        const placeholder = document.getElementById('photo-placeholder');
        if (preview && placeholder) {
            preview.src = "";
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
        }

        const reqNote = document.getElementById('photo_requirement_note');
        if (reqNote) reqNote.textContent = "Max size: 5MB.";

        document.getElementById('form-action-field').value = 'add_member';
        document.getElementById('form-member-id-field').value = '';
        document.getElementById('form-console-title').textContent = "Register New Jamaath Member Console";
        document.getElementById('form-submit-btn').textContent = "Register Member";

        const resetBtn = document.getElementById('form-reset-btn');
        if (resetBtn) resetBtn.classList.add('hidden');

        if (iti) {
            iti.setCountry("in");
        }
        const fullPhoneHidden = document.getElementById('field_phone_full');
        if (fullPhoneHidden) {
            fullPhoneHidden.value = '';
        }

        const aadharError = document.getElementById('aadhar-error-msg');
        const aadharFileError = document.getElementById('aadhar-file-error-msg');
        const aadharInput = document.getElementById('field_aadhar_number');

        if (aadharError) aadharError.classList.add('hidden');
        if (aadharFileError) aadharFileError.classList.add('hidden');
        if (aadharInput) aadharInput.classList.remove('border-red-500');

        const fileLabel = document.getElementById('aadhar-file-label');
        if (fileLabel) fileLabel.textContent = "No file selected";

        const freshPlaceholder = document.getElementById("aadhar-preview-placeholder");
        const freshImg = document.getElementById("aadhar-img-preview");
        const freshPdf = document.getElementById("aadhar-pdf-preview");

        if (freshPlaceholder) freshPlaceholder.classList.remove("hidden");
        if (freshImg) { freshImg.classList.add("hidden"); freshImg.src = ""; }
        if (freshPdf) freshPdf.classList.add("hidden");

        const existingAadharContainer = document.getElementById('existing-aadhar-container');
        if (existingAadharContainer) {
            existingAadharContainer.classList.add('hidden');
            const linkElement = document.getElementById('existing-aadhar-link');
            if (linkElement) linkElement.href = '#';
        }

        if (typeof generateDependentFields === "function") generateDependentFields(0);
        if (typeof syncAddresses === "function") syncAddresses();
        if (typeof toggleFormDeceasedDate === "function") toggleFormDeceasedDate('Alive');
    };

    // --- 2. REGULAR MODULE CORE LOGIC FUNCTIONS ---

    function generateDependentFields(count, initialData = []) {
        const dependentsCount = parseInt(count) || 0;
        const container = document.getElementById('dependents-dynamic-container');
        const grid = document.getElementById('dependents-grid-fields');

        if (!grid || !container) return;
        grid.innerHTML = '';

        if (dependentsCount > 0) {
            container.classList.remove('hidden');

            for (let i = 0; i < dependentsCount; i++) {
                const data = initialData[i] || { name: '', relationship: 'Son', dob: '', gender: 'Male', status: 'Alive' };

                const card = document.createElement('div');
                card.className = "grid grid-cols-1 sm:grid-cols-5 gap-2 bg-white p-3 rounded-xl border border-slate-200 items-end";

                card.innerHTML = `
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Dependent #${i + 1} Name *</label>
                        <input type="text" name="dep_name[]" value="${data.name}" required placeholder="Name" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Relationship *</label>
                        <select name="dep_relationship[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            <option value="Son" ${data.relationship === 'Son' ? 'selected' : ''}>Son</option>
                            <option value="Daughter" ${data.relationship === 'Daughter' ? 'selected' : ''}>Daughter</option>
                            <option value="Spouse" ${data.relationship === 'Spouse' ? 'selected' : ''}>Spouse</option>
                            <option value="Mother" ${data.relationship === 'Mother' ? 'selected' : ''}>Mother</option>
                            <option value="Father" ${data.relationship === 'Father' ? 'selected' : ''}>Father</option>
                            <option value="Sibling" ${data.relationship === 'Sibling' ? 'selected' : ''}>Sibling</option>
                            <option value="Other" ${data.relationship === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Date of Birth *</label>
                        <input type="date" name="dep_dob[]" value="${data.dob}" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Gender *</label>
                        <select name="dep_gender[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            <option value="Male" ${data.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${data.gender === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Life Status *</label>
                        <select name="dep_status[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            <option value="Alive" ${data.status === 'Alive' ? 'selected' : ''}>Alive</option>
                            <option value="Deceased" ${data.status === 'Deceased' ? 'selected' : ''}>Deceased</option>
                        </select>
                    </div>
                `;
                grid.appendChild(card);
            }
        } else {
            container.classList.add('hidden');
        }
    }

    function syncAddresses() {
        const checkElem = document.getElementById('same-address-check');
        if (!checkElem) return;

        const isChecked = checkElem.checked;
        const fields = ['address_line1', 'address_line2', 'city', 'pincode', 'country']; // Added 'country'

        fields.forEach(field => {
            const resInput = document.getElementById('res_' + field);
            const commInput = document.getElementById('comm_' + field);

            if (resInput && commInput) {
                if (isChecked) {
                    commInput.value = resInput.value;
                    commInput.readOnly = true;
                    commInput.classList.add('bg-slate-100', 'cursor-not-allowed');
                } else {
                    commInput.readOnly = false;
                    commInput.classList.remove('bg-slate-100', 'cursor-not-allowed');
                }
            }
        });
    }

    function populateEditForm(member) {
        const formSec = document.getElementById('add-member-form-section');
        formSec.classList.remove('hidden');
        document.getElementById('toggle-form-btn-text').textContent = "Close Panel Form";
        document.getElementById('form-reset-btn').classList.remove('hidden');

        document.getElementById('form-action-field').value = 'edit_member';
        document.getElementById('form-member-id-field').value = member.id;
        document.getElementById('form-console-title').textContent = "Update Member Records Console";
        document.getElementById('form-submit-btn').textContent = "Save Changes";

        document.getElementById('field_first_name').value = member.first_name;
        document.getElementById('field_last_name').value = member.last_name;
        document.getElementById('field_family_name').value = member.family_name || '';
        document.getElementById('field_card_no').value = member.card_no;

        document.getElementById('dependents_count').value = member.dependents_count;
        generateDependentFields(member.dependents_count, member.dependents || []);

        document.getElementById('field_dob').value = member.dob;
        document.getElementById('field_gender').value = member.gender;
        document.getElementById('field_marital_status').value = member.marital_status || 'Single';
        document.getElementById('field_father_name').value = member.father_husband_name;
        document.getElementById('field_mahallah').value = member.mahallah;

        if (member.phone && iti) {
            iti.setNumber(member.phone);
        } else {
            document.getElementById('field_phone').value = member.phone || '';
        }

        document.getElementById('field_aadhar_number').value = member.aadhar_number || '';

        const aadharError = document.getElementById('aadhar-error-msg');
        const aadharFileError = document.getElementById('aadhar-file-error-msg');
        if (aadharError) aadharError.classList.add('hidden');
        if (aadharFileError) aadharFileError.classList.add('hidden');

        document.getElementById('field_aadhar_doc').value = "";
        document.getElementById('aadhar-file-label').textContent = "No file selected";

        const placeholder = document.getElementById("aadhar-preview-placeholder");
        const imgPreview = document.getElementById("aadhar-img-preview");
        const pdfPreview = document.getElementById("aadhar-pdf-preview");
        const aadharLinkWrap = document.getElementById('existing-aadhar-container');

        if (placeholder) placeholder.classList.add("hidden");
        if (imgPreview) { imgPreview.classList.add("hidden"); imgPreview.src = ""; }
        if (pdfPreview) pdfPreview.classList.add("hidden");

        if (member.aadhar_doc) {
            if (document.getElementById('existing-aadhar-link')) {
                document.getElementById('existing-aadhar-link').href = member.aadhar_doc;
            }
            if (aadharLinkWrap) aadharLinkWrap.classList.remove('hidden');

            const lowerPath = member.aadhar_doc.toLowerCase();
            if (lowerPath.endsWith('.pdf')) {
                if (pdfPreview) pdfPreview.classList.remove("hidden");
            } else {
                if (imgPreview) {
                    imgPreview.src = member.aadhar_doc;
                    imgPreview.classList.remove("hidden");
                }
            }
        } else {
            if (aadharLinkWrap) aadharLinkWrap.classList.add('hidden');
            if (placeholder) placeholder.classList.remove("hidden");
        }

        document.getElementById('field_blood_group').value = member.blood_group || '';
        document.getElementById('field_occupation').value = member.occupation || '';

        if (member.photo) {
            document.getElementById('photo-preview').src = member.photo;
        }

        const selectDesignation = document.getElementById('field_designation');
        Array.from(selectDesignation.options).forEach(opt => {
            if (opt.getAttribute('data-occupied') === "true") {
                opt.disabled = true;
            } else {
                opt.disabled = false;
            }
        });

        const currentOption = Array.from(selectDesignation.options).find(opt => opt.value === member.designation);
        if (currentOption) {
            currentOption.disabled = false;
        }
        selectDesignation.value = member.designation || 'Ordinary Member';

        document.getElementById('res_address_line1').value = member.res_address_line1 || '';
        document.getElementById('res_address_line2').value = member.res_address_line2 || '';
        document.getElementById('res_city').value = member.res_city || '';
        document.getElementById('res_pincode').value = member.res_pincode || '';
        document.getElementById('res_country').value = member.res_country || 'India';

        document.getElementById('comm_address_line1').value = member.comm_address_line1 || '';
        document.getElementById('comm_address_line2').value = member.comm_address_line2 || '';
        document.getElementById('comm_city').value = member.comm_city || '';
        document.getElementById('comm_pincode').value = member.comm_pincode || '';
        document.getElementById('comm_country').value = member.comm_country || 'India';

        const isSame = (member.res_address_line1 === member.comm_address_line1) &&
            (member.res_city === member.comm_city) &&
            (member.res_pincode === member.comm_pincode) &&
            (member.res_country === member.comm_country);

        document.getElementById('same-address-check').checked = isSame;
        syncAddresses();

        document.getElementById('field_status').value = member.status;
        if (typeof toggleFormDeceasedDate === "function") toggleFormDeceasedDate(member.status);
        if (member.status === 'Deceased') {
            document.getElementById('form-deceased-date-field').value = member.deceased_date || '';
        }

        formSec.scrollIntoView({ behavior: 'smooth' });
    }

    function previewMemberImageOnSelect(event) {
        const input = event.target;
        const preview = document.getElementById('photo-preview');
        const placeholder = document.getElementById('photo-placeholder');

        if (input.files && input.files[0] && preview && placeholder) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function openProfileCard(member) {
        document.getElementById('card-photo').src = (member.photo && member.photo.startsWith('uploads/')) ? member.photo : 'https://placehold.co/150x150/0f766e/ffffff?text=' + encodeURIComponent(member.first_name);
        document.getElementById('card-fullname').textContent = member.first_name + ' ' + member.last_name;
        document.getElementById('card-family-name').textContent = member.family_name ? '(' + member.family_name + ')' : '';
        document.getElementById('card-card-no').textContent = member.card_no;
        document.getElementById('card-age').textContent = calculateAgeJS(member.dob);

        document.getElementById('card-father').textContent = member.father_husband_name;
        document.getElementById('card-dob').textContent = formatDateJS(member.dob);
        document.getElementById('card-gender').textContent = member.gender;
        document.getElementById('card-marital').textContent = member.marital_status || 'Single';

        document.getElementById('card-phone').textContent = member.phone;
        document.getElementById('card-mahallah').textContent = member.mahallah;
        document.getElementById('card-occupation').textContent = member.occupation || 'N/A';
        document.getElementById('card-blood').textContent = member.blood_group || 'N/A';

        // Populate New Identity Column Modifications safely
        document.getElementById('card-aadhar-num').textContent = member.aadhar_number ? member.aadhar_number : 'Not Provided';
        const aadharDocWrap = document.getElementById('card-aadhar-doc-container');
        if (aadharDocWrap) {
            if (member.aadhar_doc) {
                aadharDocWrap.innerHTML = `
                <a href="${member.aadhar_doc}" target="_blank" class="mt-1 text-[10px] text-emerald-700 font-bold hover:underline inline-flex items-center gap-1">
                    <i class="fa-solid fa-file-pdf"></i> View Attachment
                </a>`;
            } else {
                aadharDocWrap.innerHTML = `<span class="text-[10px] text-slate-400 italic font-medium">No attached file</span>`;
            }
        }

        document.getElementById('chanda-member-id-field').value = member.id;

        const today = new Date();
        const prevMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);

        const maxMonthStrFrom = formatMonthInputJS(today);
        const endOfYear = new Date(today.getFullYear(), 11, 1);
        const maxMonthStrTo = formatMonthInputJS(endOfYear);
        const minDate = new Date(today.getFullYear() - 2, today.getMonth(), 1);
        const minMonthStr = formatMonthInputJS(minDate);

        const fromInput = document.getElementById('chanda_paid_from_input');
        const toInput = document.getElementById('chanda_paid_to_input');

        if (fromInput && toInput) {
            fromInput.min = minMonthStr;
            fromInput.max = maxMonthStrTo;
            toInput.min = minMonthStr;
            toInput.max = maxMonthStrTo;

            let chandaPaidToDate = null;
            if (member.chanda_paid_to) {
                chandaPaidToDate = new Date(member.chanda_paid_to);
                fromInput.value = member.chanda_paid_from.substring(0, 7);
                toInput.value = member.chanda_paid_to.substring(0, 7);
            } else {
                fromInput.value = maxMonthStrFrom;
                toInput.value = maxMonthStrFrom;
            }

            const cardChandaPaid = document.getElementById('card-chanda-paid-period');
            const cardChandaPending = document.getElementById('card-chanda-pending-period');
            const overallChandaBadge = document.getElementById('card-chanda-badge');
            const isPaidUpToDate = chandaPaidToDate && (chandaPaidToDate >= prevMonth);

            if (isPaidUpToDate) {
                overallChandaBadge.className = "bg-sky-100 text-sky-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider";
                overallChandaBadge.textContent = "Paid (Up to Date)";
                cardChandaPaid.innerHTML = `Paid from <span class="font-bold underline">${formatDateMonthYearJS(member.chanda_paid_from)}</span> to <span class="font-bold underline">${formatDateMonthYearJS(member.chanda_paid_to)}</span>`;
                cardChandaPending.className = "font-bold text-xs text-emerald-700 mt-0.5";
                cardChandaPending.textContent = "No Outstanding Balances";
            } else {
                overallChandaBadge.className = "bg-amber-100 text-amber-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider";
                overallChandaBadge.textContent = "Unpaid / Pending";

                if (member.chanda_paid_from && member.chanda_paid_to) {
                    cardChandaPaid.innerHTML = `Paid from <span class="font-bold underline">${formatDateMonthYearJS(member.chanda_paid_from)}</span> to <span class="font-bold underline">${formatDateMonthYearJS(member.chanda_paid_to)}</span>`;
                    const pendingStart = new Date(chandaPaidToDate.getFullYear(), chandaPaidToDate.getMonth() + 1, 1);
                    cardChandaPending.className = "font-bold text-xs text-rose-700 mt-0.5";

                    if (pendingStart <= prevMonth) {
                        cardChandaPending.innerHTML = `Pending from <span class="underline">${formatDateMonthYearJS(pendingStart)}</span> to <span class="underline">${formatDateMonthYearJS(prevMonth)}</span>`;
                    } else {
                        cardChandaPending.textContent = "No Outstanding Balances";
                    }
                } else {
                    cardChandaPaid.textContent = "No payments recorded yet.";
                    cardChandaPending.className = "font-bold text-xs text-rose-700 mt-0.5";
                    const creationDate = member.date_added ? new Date(member.date_added) : new Date(today.getFullYear(), today.getMonth() - 6, 1);
                    const effectiveStartDate = (creationDate < minDate) ? minDate : creationDate;
                    cardChandaPending.innerHTML = `Outstanding from <span class="underline">${formatDateMonthYearJS(effectiveStartDate)}</span> to <span class="underline">${formatDateMonthYearJS(prevMonth)}</span>`;
                }
            }
        }

        const chandaForm = document.getElementById('card-chanda-form');
        if (chandaForm) {
            chandaForm.onsubmit = function (e) {
                const valFrom = fromInput.value;
                const valTo = toInput.value;
                const totalAmount = parseFloat(document.getElementById('chanda_total_amount_input').value) || 0;
                const isSelf = document.querySelector('input[name="paid_by_self"]:checked').value === "1";

                if (totalAmount < 150) {
                    alert("Error: The minimum accepted subscription payment amount is ₹150.");
                    e.preventDefault();
                    return false;
                }

                if (!isSelf) {
                    const phoneInput = document.getElementById('chanda_third_party_phone');
                    if (phoneInput.value.trim() !== "" && !chandaPayerIti.isValidNumber()) {
                        alert("Error: Please enter a valid phone number for the third-party payer.");
                        e.preventDefault();
                        return false;
                    }
                    phoneInput.value = chandaPayerIti.getNumber();
                }

                // MODIFIED: 'Paid From' now checks against the end of the current year (maxMonthStrTo) instead of maxMonthStrFrom
                if (valFrom < minMonthStr || valFrom > maxMonthStrTo) {
                    alert(`Error: 'Paid From' must be within the past 2 years and cannot exceed December of ${today.getFullYear()}.`);
                    e.preventDefault();
                    return false;
                }

                if (valTo < minMonthStr || valTo > maxMonthStrTo) {
                    alert(`Error: 'Paid To' must be within the past 2 years and cannot exceed December of ${today.getFullYear()}.`);
                    e.preventDefault();
                    return false;
                }

                if (valTo < valFrom) {
                    alert("Error: The 'Paid To' month cannot be earlier than the 'Paid From' month.");
                    e.preventDefault();
                    return false;
                }
            };
        }

        const depContainer = document.getElementById('card-dependents-container');
        const depList = document.getElementById('card-dependents-list');
        if (depList && depContainer) {
            depList.innerHTML = '';
            if (member.dependents && member.dependents.length > 0) {
                depContainer.classList.remove('hidden');
                member.dependents.forEach((dep, idx) => {
                    const depRow = document.createElement('div');
                    depRow.className = "flex items-center justify-between py-2.5 text-sm text-slate-700 " + (idx > 0 ? "border-t border-indigo-100/55" : "");

                    const isDeceased = dep.status === 'Deceased';
                    const statusBadge = isDeceased
                        ? `<span class="bg-rose-100 text-rose-800 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide flex items-center gap-1">🕊️ Deceased (Marhoom)</span>`
                        : `<span class="bg-indigo-100 text-indigo-800 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide">Alive</span>`;

                    depRow.innerHTML = `
                    <div>
                        <p class="font-bold ${isDeceased ? 'text-slate-400 line-through' : 'text-slate-800'}">👤 ${escapeHtml(dep.name)}</p>
                        <p class="text-[11px] text-slate-400 font-mono">DOB: ${formatDateJS(dep.dob)} | Gender: ${dep.gender}</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide">
                            ${escapeHtml(dep.relationship)}
                        </span>
                        ${statusBadge}
                    </div>
                `;
                    depList.appendChild(depRow);
                });
            } else {
                depContainer.classList.add('hidden');
            }
        }

        const desigBadge = document.getElementById('card-designation-badge');
        if (desigBadge) {
            if (member.designation && member.designation !== 'Ordinary Member') {
                desigBadge.textContent = member.designation;
                desigBadge.className = "inline-block bg-emerald-500/25 text-emerald-100 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider";
                desigBadge.style.display = "inline-block";
            } else {
                desigBadge.style.display = "none";
            }
        }

        const statusBadge = document.getElementById('card-status-badge');
        if (statusBadge) {
            if (member.status === 'Alive') {
                statusBadge.className = "bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider";
                statusBadge.textContent = "Alive";
            } else {
                statusBadge.className = "bg-rose-100 text-rose-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider";
                statusBadge.textContent = "Deceased";
            }
        }

        // Appended country location labels directly here
        document.getElementById('card-res-address').innerHTML = `
        ${member.res_address_line1 || ''}<br>
        ${member.res_address_line2 || ''}<br>
        ${member.res_city || ''} - ${member.res_pincode || ''}<br>
        <span class="text-[11px] text-slate-400 uppercase font-bold tracking-wider">${member.res_country || 'India'}</span>
    `;
        document.getElementById('card-comm-address').innerHTML = `
        ${member.comm_address_line1 || ''}<br>
        ${member.comm_address_line2 || ''}<br>
        ${member.comm_city || ''} - ${member.comm_pincode || ''}<br>
        <span class="text-[11px] text-slate-400 uppercase font-bold tracking-wider">${member.comm_country || 'India'}</span>
    `;

        document.getElementById('card-edit-btn').onclick = function () {
            closeProfileCard();
            populateEditForm(member);
        };

        const historyRowsContainer = document.getElementById('card-chanda-history-rows');
        if (historyRowsContainer) {
            historyRowsContainer.innerHTML = '';
            if (member.chanda_history_raw && member.chanda_history_raw.trim() !== '') {
                const records = member.chanda_history_raw.split('||');
                records.forEach(recordStr => {
                    const parts = recordStr.split('|');
                    if (parts.length >= 6) {
                        const recId = parts[0];
                        const pFrom = parts[1];
                        const pTo = parts[2];
                        const pAmount = parseFloat(parts[3]).toFixed(2);
                        const pUser = parts[4];
                        const pDateRaw = parts[5];

                        const pMode = (parts.length >= 7 && parts[6]) ? parts[6] : 'Cash';
                        const pNarrative = (parts.length >= 8 && parts[7]) ? parts[7] : '';
                        const pIsSelf = (parts.length >= 9 && parts[8] !== undefined) ? (parseInt(parts[8]) === 1) : true;

                        // NEW: Dynamic retrieval of third-party parameters from row collection
                        const tpName = (parts.length >= 10 && parts[9]) ? parts[9].trim() : '';
                        const tpPhone = (parts.length >= 11 && parts[10]) ? parts[10].trim() : '';

                        let formattedDateTime = pDateRaw;
                        if (pDateRaw) {
                            const dateObj = new Date(pDateRaw.replace(/-/g, "/"));
                            if (!isNaN(dateObj.getTime())) {
                                formattedDateTime = dateObj.toLocaleDateString('en-IN', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric'
                                }) + ' ' + dateObj.toLocaleTimeString('en-IN', {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hour12: true
                                });
                            }
                        }

                        let modeBadgeClass = "bg-slate-100 text-slate-700 border border-slate-200/60";
                        if (pMode === 'UPI') modeBadgeClass = "bg-indigo-50 text-indigo-700 border border-indigo-100";
                        if (pMode === 'Cheque') modeBadgeClass = "bg-amber-50 text-amber-700 border border-amber-100";

                        let subtitleMetaHtml = "";
                        if ((pMode === 'UPI' || pMode === 'Cheque') && pNarrative) {
                            subtitleMetaHtml = `<span class="block text-[10px] text-slate-400 mt-0.5 italic truncate font-mono">Ref: ${escapeHtml(pNarrative)}</span>`;
                        }

                        // MODIFIED: If not paid by self, display Name and Phone dynamically
                        let payerLabel = "Member Self";
                        if (!pIsSelf) {
                            payerLabel = "Third Party";
                            if (tpName !== '') {
                                payerLabel = escapeHtml(tpName) + (tpPhone !== '' ? ` (${escapeHtml(tpPhone)})` : '');
                            }
                        }
                        const periodMetaSubtitle = `<span class="block text-[10px] text-slate-400 mt-0.5 tracking-wide">By: ${payerLabel}</span>`;

                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-slate-50/75 transition-colors border-b border-slate-100";
                        tr.innerHTML = `
                    <td class="p-2 py-2.5 text-slate-900 align-top">
                        <div class="font-medium">${formatDateMonthYearJS(pFrom)} - ${formatDateMonthYearJS(pTo)}</div>
                        ${periodMetaSubtitle}
                    </td>
                    <td class="p-2 py-2.5 align-top">
                        <div class="font-bold text-emerald-700">₹${pAmount}</div>
                        <span class="inline-block px-1.5 py-0.5 rounded text-[8px] font-extrabold tracking-wider uppercase mt-1 ${modeBadgeClass}">
                            ${pMode}
                        </span>
                        ${subtitleMetaHtml}
                    </td>
                    <td class="p-2 py-2.5 font-mono text-slate-500 align-top">${escapeHtml(pUser)}</td>
                    <td class="p-2 py-2.5 text-slate-400 font-mono text-[11px] align-top whitespace-nowrap">${formattedDateTime}</td>
                `;
                        historyRowsContainer.appendChild(tr);
                    }
                });
            } else {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td colspan="4" class="p-4 text-center text-slate-400 italic">No historical subscription payments logged yet.</td>`;
                historyRowsContainer.appendChild(tr);
            }
        }

        document.getElementById('profile-card-modal').classList.remove('hidden');
    }

    function closeProfileCard() {
        document.getElementById('profile-card-modal').classList.add('hidden');
    }

    function calculateAgeJS(dobString) {
        if (!dobString) return 'N/A';
        const birth = new Date(dobString);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }

    function formatDateJS(dateString) {
        if (!dateString) return '---';
        const dateObj = new Date(dateString);
        return dateObj.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatDateMonthYearJS(dateString) {
        if (!dateString) return '---';
        const dateObj = new Date(dateString);
        return dateObj.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    }

    function formatMonthInputJS(dateObj) {
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}`;
    }

    // This matches the update label feature if your layout specifically binds it
    function updateAadharFileLabel(input) {
        if (typeof window.previewAadharDocument === "function") {
            window.previewAadharDocument(input);
        }
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function toggleChandaNarrativeField() {
        const mode = document.getElementById('chanda_payment_mode').value;
        const wrapper = document.getElementById('chanda_narrative_wrapper');
        const input = document.getElementById('chanda_payment_narrative');

        if (mode === 'UPI' || mode === 'Cheque') {
            wrapper.classList.remove('hidden');
            input.required = true;
            input.placeholder = mode === 'UPI' ? "Enter UPI Reference ID (UTR Number)" : "Enter 6-Digit Cheque Number & Bank Name";
        } else {
            wrapper.classList.add('hidden');
            input.required = false;
            input.value = '';
        }
    }

    function togglePaidByNarrativeHint(isSelf) {
        if (!isSelf) {
            console.log("Third party payer flagged.");
        }
    }

    /**
     * Handles tab view swapping for the Members module
     */
    window.switchMembersTab = function (targetTab) {
        const tabBtnDirectory = document.getElementById('tab-btn-directory');
        const tabBtnReports = document.getElementById('tab-btn-reports');
        const panelDirectory = document.getElementById('members-tab-directory');
        const panelReports = document.getElementById('members-tab-reports');

        if (!tabBtnDirectory || !tabBtnReports || !panelDirectory || !panelReports) {
            return;
        }

        if (targetTab === 'reports') {
            panelDirectory.classList.add('hidden');
            panelReports.classList.remove('hidden');

            tabBtnReports.className = "px-4 py-2.5 rounded-t-xl transition-all border-b-2 border-emerald-700 text-emerald-800 bg-emerald-50/50 flex items-center gap-2 cursor-pointer";
            tabBtnDirectory.className = "px-4 py-2.5 rounded-t-xl transition-all border-b-2 border-transparent text-slate-500 hover:text-slate-700 flex items-center gap-2 cursor-pointer";

            if (document.getElementById('member-report-frame').src === 'about:blank' || document.getElementById('member-report-frame').src === '') {
                window.reloadReportEngine();
            }
        } else {
            panelReports.classList.add('hidden');
            panelDirectory.classList.remove('hidden');

            tabBtnDirectory.className = "px-4 py-2.5 rounded-t-xl transition-all border-b-2 border-emerald-700 text-emerald-800 bg-emerald-50/50 flex items-center gap-2 cursor-pointer";
            tabBtnReports.className = "px-4 py-2.5 rounded-t-xl transition-all border-b-2 border-transparent text-slate-500 hover:text-slate-700 flex items-center gap-2 cursor-pointer";
        }
    };

    /**
     * Re-reads active selector states and compiles parameters to safely feed the iframe layout view
     */
    window.reloadReportEngine = function () {
        const search = encodeURIComponent(document.getElementById('rep-filter-search')?.value || '');
        const mahallah = encodeURIComponent(document.getElementById('rep-filter-mahallah')?.value || 'All');
        const status = encodeURIComponent(document.getElementById('rep-filter-status')?.value || 'All');
        const chanda = encodeURIComponent(document.getElementById('rep-filter-chanda')?.value || 'All');
        const designation = encodeURIComponent(document.getElementById('rep-filter-designation')?.value || 'All');
        const occupation = encodeURIComponent(document.getElementById('rep-filter-occupation')?.value || 'All');

        const frame = document.getElementById('member-report-frame');
        if (frame) {
            frame.src = `member_report_engine.php?search=${search}&mahallah=${mahallah}&status=${status}&chanda=${chanda}&designation=${designation}&occupation=${occupation}`;
        }
    };

    /**
     * Calls target print handler directly through the embedded iframe window contextual print routine
     */
    window.triggerIframePrint = function () {
        const frame = document.getElementById('member-report-frame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    };

    window.triggerIframeExcelExport = function () {
        const search = encodeURIComponent(document.getElementById('rep-filter-search')?.value || '');
        const mahallah = encodeURIComponent(document.getElementById('rep-filter-mahallah')?.value || 'All');
        const status = encodeURIComponent(document.getElementById('rep-filter-status')?.value || 'All');
        const chanda = encodeURIComponent(document.getElementById('rep-filter-chanda')?.value || 'All');
        const designation = encodeURIComponent(document.getElementById('rep-filter-designation')?.value || 'All');
        const occupation = encodeURIComponent(document.getElementById('rep-filter-occupation')?.value || 'All');

        // Forces the browser to trigger the file stream download from the engine directly
        window.location.href = `member_report_engine.php?search=${search}&mahallah=${mahallah}&status=${status}&chanda=${chanda}&designation=${designation}&occupation=${occupation}&format=excel`;
    };

    // Initialize the intl-tel-input library on the new field
    let chandaPayerIti;
    document.addEventListener("DOMContentLoaded", function () {
        const phoneInput = document.getElementById("chanda_third_party_phone");
        if (phoneInput) {
            chandaPayerIti = window.intlTelInput(phoneInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js" // Adjust paths as used in your app
            });
        }
    });

    // Dynamic Field Toggle Handler
    function toggleChandaPayerFields(isSelf) {
        const wrapper = document.getElementById('chanda_third_party_wrapper');
        const nameInput = document.getElementById('chanda_third_party_name');
        const phoneInput = document.getElementById('chanda_third_party_phone');

        if (isSelf) {
            wrapper.classList.add('hidden');
            nameInput.required = false;
            phoneInput.required = false;
            nameInput.value = '';
            phoneInput.value = '';
        } else {
            wrapper.classList.remove('hidden');
            nameInput.required = true;
            phoneInput.required = true;
        }

        // Call legacy helper function hook if needed elsewhere
        if (typeof togglePaidByNarrativeHint === "function") {
            togglePaidByNarrativeHint(isSelf);
        }
    }

    // --- 3. DOM CONTENT INITIALIZATIONS AND SUBMIT INTERCEPTORS ---

    document.addEventListener("DOMContentLoaded", function () {
        const phoneInput = document.getElementById("field_phone");
        const fullPhoneHidden = document.getElementById("field_phone_full");

        if (phoneInput) {
            iti = window.intlTelInput(phoneInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
            });
        }

        // Real-time Sync listeners block
        ['address_line1', 'address_line2', 'city', 'pincode', 'country'].forEach(field => { // Added 'country'
            const el = document.getElementById('res_' + field);
            if (el) {
                el.addEventListener('input', () => {
                    if (document.getElementById('same-address-check').checked) {
                        syncAddresses();
                    }
                });
            }
        });

        // Real-time Aadhaar Formatting
        const aadharInput = document.getElementById("field_aadhar_number");
        const aadharError = document.getElementById("aadhar-error-msg");
        if (aadharInput) {
            aadharInput.addEventListener("input", function () {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length > 12) this.value = this.value.slice(0, 12);

                if (this.value.length > 0 && this.value.length !== 12) {
                    if (aadharError) {
                        aadharError.textContent = "Aadhaar must be an exact 12-digit number.";
                        aadharError.classList.remove("hidden");
                    }
                    this.classList.add("border-red-500");
                } else {
                    if (aadharError) aadharError.classList.add("hidden");
                    this.classList.remove("border-red-500");
                }
            });
        }

        // Form Submission interceptor
        const masterForm = document.getElementById("member-master-form");
        if (masterForm) {
            masterForm.addEventListener("submit", function (e) {
                let formIsValid = true;

                // 1. Phone Number Library Validation
                if (phoneInput && iti) {
                    if (phoneInput.value.trim() === "" || !iti.isValidNumber()) {
                        alert("Please enter a valid Phone Number configuration.");
                        phoneInput.classList.add("border-red-500");
                        formIsValid = false;
                    } else {
                        phoneInput.classList.remove("border-red-500");
                        fullPhoneHidden.value = iti.getNumber();
                    }
                }

                // 2. Identity Number Validation
                if (aadharInput && aadharInput.value.length !== 12) {
                    if (aadharError) {
                        aadharError.textContent = "ID number must be an exact 12-digit number.";
                        aadharError.classList.remove("hidden");
                    }
                    aadharInput.classList.add("border-red-500");
                    formIsValid = false;
                }

                // 3. Document Requirements & Strict 2MB Size Validation
                const actionType = document.getElementById('form-action-field').value;
                const docInput = document.getElementById("field_aadhar_doc");
                const fileError = document.getElementById("aadhar-file-error-msg");

                if (docInput) {
                    if (actionType === 'add_member' && docInput.files.length === 0) {
                        // File is completely missing during initial registration
                        if (fileError) {
                            fileError.textContent = "Please upload an image or PDF copy of the document.";
                            fileError.classList.remove("hidden");
                        }
                        formIsValid = false;
                    } else if (docInput.files.length > 0) {
                        // File is present, verify it does not exceed the 2MB threshold
                        const maxLimitBytes = 2 * 1024 * 1024; // 2,097,152 Bytes
                        if (docInput.files[0].size > maxLimitBytes) {
                            if (fileError) {
                                fileError.textContent = "Error: File size exceeds the 2MB limit. Please choose a smaller file.";
                                fileError.classList.remove("hidden");
                            }
                            formIsValid = false;
                        }
                    }
                }

                // Block transmission if any layer flag drops to false
                if (!formIsValid) {
                    e.preventDefault();
                }
            });
        }
    });
</script>

<?php require_once 'footer.php'; ?>
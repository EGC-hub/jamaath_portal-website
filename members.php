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
if ($filter_chanda !== 'All') {
    if ($filter_chanda === 'Paid') {
        $where_clauses[] = "chanda_paid_to >= ?";
        $params[] = $prev_month_boundary;
    } else {
        $where_clauses[] = "(chanda_paid_to IS NULL OR chanda_paid_to < ?)";
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

// Get total count
$count_stmt = $db->prepare("SELECT COUNT(*) FROM members $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1)
    $total_pages = 1;
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch paginated member subset
$fetch_stmt = $db->prepare("SELECT * FROM members $where_sql ORDER BY date_added DESC LIMIT $limit OFFSET $offset");
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

<!-- Directory container -->
<div class="space-y-6">
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
                            <?php echo $w_opt; ?></option>
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
                            class="flex flex-col items-center justify-center p-3 border border-dashed border-slate-200 rounded-xl bg-white">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Member
                                Photo</label>
                            <div
                                class="relative w-28 h-28 bg-slate-100 rounded-2xl overflow-hidden mb-2 border border-slate-200 flex items-center justify-center">
                                <img id="photo-preview"
                                    src="[https://placehold.co/150x150/0f766e/ffffff?text=No+Photo](https://placehold.co/150x150/0f766e/ffffff?text=No+Photo)"
                                    class="object-cover w-full h-full" alt="Preview">
                            </div>
                            <input type="file" name="photo" id="member-photo-input" accept="image/*"
                                onchange="handlePhotoChange(event)" class="hidden">
                            <button type="button" onclick="document.getElementById('member-photo-input').click()"
                                class="bg-slate-50 border border-slate-200 text-slate-700 px-2.5 py-1.5 rounded-lg font-semibold hover:bg-slate-100">
                                <i class="fa-solid fa-camera mr-1"></i> Upload
                            </button>
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
                            <input type="tel" name="phone" id="field_phone" required placeholder="Phone"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
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
                            <label class="block font-semibold text-slate-600 mb-1">Occupation</label>
                            <input type="text" name="occupation" id="field_occupation"
                                placeholder="Merchant, Software Engineer etc."
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
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

                    <!-- Split Residential Address Fields -->
                    <div class="bg-slate-100/50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5"><i
                                class="fa-solid fa-house text-emerald-700"></i> Residential Address</h5>
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                        </div>
                    </div>

                    <!-- Split Communication Address Fields -->
                    <div class="bg-slate-100/50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5"><i
                                    class="fa-solid fa-envelope-open-text text-teal-700"></i> Communication Address</h5>
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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

<!-- Modal Dialog: Interactive Profile Details Card Pop-up -->
<div id="profile-card-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div
        class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">

        <!-- Premium Modal Header Banner -->
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

        <!-- Detailed Grid Data Body -->
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

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-phone text-emerald-700"></i> Contact Phone</p>
                    <p id="card-phone" class="font-bold text-slate-800 mt-1">---</p>
                </div>
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-map-pin text-teal-700"></i> Mahallah Ward</p>
                    <p id="card-mahallah" class="font-bold text-slate-800 mt-1">---</p>
                </div>
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-briefcase text-slate-600"></i> Occupation</p>
                    <p id="card-occupation" class="font-bold text-slate-800 mt-1">---</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-3 border border-slate-150 rounded-xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-droplet text-rose-600"></i> Blood Group</p>
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

            <!-- New Month-Wise Chanda Tracker Interface inside Profile Pop-up -->
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

                <!-- Collection form embedded inside profile card -->
                <form id="card-chanda-form" method="POST" action="actions.php"
                    class="p-3 bg-teal-800 text-white rounded-lg space-y-2">
                    <input type="hidden" name="action" value="update_chanda_period">
                    <input type="hidden" name="id" id="chanda-member-id-field">
                    <p class="text-[9px] font-bold uppercase tracking-wider text-teal-200">Record Subscription Payments
                    </p>
                    <div class="grid grid-cols-2 gap-2 text-slate-800">
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
                    </div>
                    <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-1.5 rounded text-[10px] uppercase tracking-wide transition-colors">
                        Save Subscription Mapping
                    </button>
                </form>
            </div>

            <!-- Dynamic Dependents List Section -->
            <div id="card-dependents-container" class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 hidden">
                <p
                    class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5 mb-2">
                    <i class="fa-solid fa-people-roof text-indigo-700"></i> Family Dependents Detailed List
                </p>
                <div id="card-dependents-list" class="divide-y divide-indigo-100 text-xs">
                    <!-- Injected dynamically via JS -->
                </div>
            </div>

            <!-- Double Columns: Residential Address vs. Communication Address -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-200">
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 mb-2">
                        <i class="fa-solid fa-house text-emerald-700"></i> Residential Address</p>
                    <p id="card-res-address" class="text-slate-600 leading-relaxed font-semibold">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-200">
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 mb-2">
                        <i class="fa-solid fa-envelope-open-text text-teal-700"></i> Communication Address</p>
                    <p id="card-comm-address" class="text-slate-600 leading-relaxed font-semibold">---</p>
                </div>
            </div>

        </div>

        <!-- Pop-up footer control panel -->
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
    function toggleAddMemberForm() {
        const formSec = document.getElementById('add-member-form-section');
        const btnText = document.getElementById('toggle-form-btn-text');
        if (formSec.classList.contains('hidden')) {
            formSec.classList.remove('hidden');
            btnText.textContent = "Close Panel Form";
        } else {
            formSec.classList.add('hidden');
            btnText.textContent = "Register New Member";
            resetFormToCreateState();
        }
    }

    // Generate nested dependent inputs dynamically based on Dependents Count
    function generateDependentFields(count, initialData = []) {
        const dependentsCount = parseInt(count) || 0;
        const container = document.getElementById('dependents-dynamic-container');
        const grid = document.getElementById('dependents-grid-fields');

        grid.innerHTML = '';

        if (dependentsCount > 0) {
            container.classList.remove('hidden');

            for (let i = 0; i < dependentsCount; i++) {
                const data = initialData[i] || { name: '', relationship: 'Son', dob: '', gender: 'Male' };

                const card = document.createElement('div');
                card.className = "grid grid-cols-1 sm:grid-cols-4 gap-2 bg-white p-3 rounded-xl border border-slate-200 items-end";

                card.innerHTML = `
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Dependent #${i + 1} Name *</label>
                        <input type="text" name="dep_name[]" value="${data.name}" required placeholder="Name" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Relationship *</label>
                        <select name="dep_relationship[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none">
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
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Date of Birth *</label>
                        <input type="date" name="dep_dob[]" value="${data.dob}" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Gender *</label>
                        <select name="dep_gender[]" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                            <option value="Male" ${data.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${data.gender === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                `;
                grid.appendChild(card);
            }
        } else {
            container.classList.add('hidden');
        }
    }

    // Address synchronization
    function syncAddresses() {
        const isChecked = document.getElementById('same-address-check').checked;
        const fields = ['address_line1', 'address_line2', 'city', 'pincode'];

        fields.forEach(field => {
            const resInput = document.getElementById('res_' + field);
            const commInput = document.getElementById('comm_' + field);

            if (isChecked) {
                commInput.value = resInput.value;
                commInput.readOnly = true;
                commInput.classList.add('bg-slate-100', 'cursor-not-allowed');
            } else {
                commInput.readOnly = false;
                commInput.classList.remove('bg-slate-100', 'cursor-not-allowed');
            }
        });
    }

    // Real-time synchronization listeners
    ['address_line1', 'address_line2', 'city', 'pincode'].forEach(field => {
        document.getElementById('res_' + field).addEventListener('input', () => {
            if (document.getElementById('same-address-check').checked) {
                syncAddresses();
            }
        });
    });

    // Populate registration form for updates
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

        // Dependents count and dynamic generation
        document.getElementById('dependents_count').value = member.dependents_count;
        generateDependentFields(member.dependents_count, member.dependents || []);

        document.getElementById('field_dob').value = member.dob;
        document.getElementById('field_gender').value = member.gender;
        document.getElementById('field_marital_status').value = member.marital_status || 'Single';
        document.getElementById('field_father_name').value = member.father_husband_name;
        document.getElementById('field_mahallah').value = member.mahallah;
        document.getElementById('field_phone').value = member.phone;
        document.getElementById('field_blood_group').value = member.blood_group || '';
        document.getElementById('field_occupation').value = member.occupation || '';

        if (member.photo) {
            document.getElementById('photo-preview').src = member.photo;
        }

        // Occupied designation options handling
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

        // Addresses mapping
        document.getElementById('res_address_line1').value = member.res_address_line1 || '';
        document.getElementById('res_address_line2').value = member.res_address_line2 || '';
        document.getElementById('res_city').value = member.res_city || '';
        document.getElementById('res_pincode').value = member.res_pincode || '';

        document.getElementById('comm_address_line1').value = member.comm_address_line1 || '';
        document.getElementById('comm_address_line2').value = member.comm_address_line2 || '';
        document.getElementById('comm_city').value = member.comm_city || '';
        document.getElementById('comm_pincode').value = member.comm_pincode || '';

        const isSame = (member.res_address_line1 === member.comm_address_line1) &&
            (member.res_city === member.comm_city) &&
            (member.res_pincode === member.comm_pincode);

        document.getElementById('same-address-check').checked = isSame;
        syncAddresses();

        document.getElementById('field_status').value = member.status;
        toggleFormDeceasedDate(member.status);
        if (member.status === 'Deceased') {
            document.getElementById('form-deceased-date-field').value = member.deceased_date || '';
        }

        formSec.scrollIntoView({ behavior: 'smooth' });
    }

    // Reset Console
    function resetFormToCreateState() {
        document.getElementById('member-master-form').reset();
        document.getElementById('photo-preview').src = "[https://placehold.co/150x150/0f766e/ffffff?text=No+Photo](https://placehold.co/150x150/0f766e/ffffff?text=No+Photo)";

        document.getElementById('form-action-field').value = 'add_member';
        document.getElementById('form-member-id-field').value = '';
        document.getElementById('form-console-title').textContent = "Register New Jamaath Member Console";
        document.getElementById('form-submit-btn').textContent = "Register Member";
        document.getElementById('form-reset-btn').classList.add('hidden');

        generateDependentFields(0);
        syncAddresses();
        toggleFormDeceasedDate('Alive');
    }

    // Open Interactive Profile Card Pop-up with dependents listing and Period Chanda tracking
    function openProfileCard(member) {
        document.getElementById('card-photo').src = member.photo || '[https://placehold.co/150x150/0f766e/ffffff?text=](https://placehold.co/150x150/0f766e/ffffff?text=)' + encodeURIComponent(member.first_name);
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

        // Set Chanda Input Values
        document.getElementById('chanda-member-id-field').value = member.id;

        // Grab current date to calculate previous month values dynamically in JS
        const today = new Date();
        const prevMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const maxMonthStr = formatMonthInputJS(prevMonth); // e.g. "2026-05"

        // Two years limit boundary calculation
        const minDate = new Date(today.getFullYear() - 2, today.getMonth(), 1);
        const minMonthStr = formatMonthInputJS(minDate); // e.g. "2024-06"

        // Set input constraint attributes dynamically on the modal inputs
        const fromInput = document.getElementById('chanda_paid_from_input');
        const toInput = document.getElementById('chanda_paid_to_input');

        fromInput.min = minMonthStr;
        fromInput.max = maxMonthStr;
        toInput.min = minMonthStr;
        toInput.max = maxMonthStr;

        // Render detailed Chanda payment status on the popup
        const cardChandaPaid = document.getElementById('card-chanda-paid-period');
        const cardChandaPending = document.getElementById('card-chanda-pending-period');
        const overallChandaBadge = document.getElementById('card-chanda-badge');

        let chandaPaidToDate = null;
        if (member.chanda_paid_to) {
            chandaPaidToDate = new Date(member.chanda_paid_to);
            // Format input month fields
            fromInput.value = member.chanda_paid_from.substring(0, 7);
            toInput.value = member.chanda_paid_to.substring(0, 7);
        } else {
            // Default initial selections inside month fields if empty (default to previous month)
            fromInput.value = maxMonthStr;
            toInput.value = maxMonthStr;
        }

        // Determine if paid up to previous month
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

                // Calculate pending starting month (next month after paid_to)
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

                // If never paid, use creation date as start of outstanding or default to 6 months ago (capped within 2 years boundary)
                const creationDate = member.date_added ? new Date(member.date_added) : new Date(today.getFullYear(), today.getMonth() - 6, 1);
                const effectiveStartDate = (creationDate < minDate) ? minDate : creationDate;

                cardChandaPending.innerHTML = `Outstanding from <span class="underline">${formatDateMonthYearJS(effectiveStartDate)}</span> to <span class="underline">${formatDateMonthYearJS(prevMonth)}</span>`;
            }
        }

        // Clean event listener attachments on form to prevent memory leaks and validate locally
        const chandaForm = document.getElementById('card-chanda-form');
        chandaForm.onsubmit = function (e) {
            const valFrom = fromInput.value;
            const valTo = toInput.value;

            if (valFrom < minMonthStr || valFrom > maxMonthStr || valTo < minMonthStr || valTo > maxMonthStr) {
                alert(`Error: Subscriptions can only be mapped within the past 2 years (from ${formatDateMonthYearJS(minDate)} up to ${formatDateMonthYearJS(prevMonth)} only).`);
                e.preventDefault();
                return false;
            }

            if (valTo < valFrom) {
                alert("Error: The 'Paid To' month cannot be earlier than the 'Paid From' month.");
                e.preventDefault();
                return false;
            }
        };

        // Render relational dependents inside Profile Card
        const depContainer = document.getElementById('card-dependents-container');
        const depList = document.getElementById('card-dependents-list');
        depList.innerHTML = '';

        if (member.dependents && member.dependents.length > 0) {
            depContainer.classList.remove('hidden');
            member.dependents.forEach((dep, idx) => {
                const depRow = document.createElement('div');
                depRow.className = "flex items-center justify-between py-2 text-[11px] text-slate-700 " + (idx > 0 ? "border-t border-indigo-100/55" : "");
                depRow.innerHTML = `
                    <div>
                        <p class="font-bold text-slate-800">👤 ${escapeHtml(dep.name)}</p>
                        <p class="text-[9px] text-slate-400 font-mono">DOB: ${formatDateJS(dep.dob)} | Gender: ${dep.gender}</p>
                    </div>
                    <span class="bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide">
                        ${escapeHtml(dep.relationship)}
                    </span>
                `;
                depList.appendChild(depRow);
            });
        } else {
            depContainer.classList.add('hidden');
        }

        const desigBadge = document.getElementById('card-designation-badge');
        if (member.designation && member.designation !== 'Ordinary Member') {
            desigBadge.textContent = member.designation;
            desigBadge.className = "inline-block bg-emerald-500/25 text-emerald-100 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider";
            desigBadge.style.display = "inline-block";
        } else {
            desigBadge.style.display = "none";
        }

        const statusBadge = document.getElementById('card-status-badge');
        if (member.status === 'Alive') {
            statusBadge.className = "bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider";
            statusBadge.textContent = "Alive";
        } else {
            statusBadge.className = "bg-rose-100 text-rose-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider";
            statusBadge.textContent = "Deceased";
        }

        document.getElementById('card-res-address').innerHTML = `
            ${member.res_address_line1 || ''}<br>
            ${member.res_address_line2 || ''}<br>
            ${member.res_city || ''} - ${member.res_pincode || ''}
        `;
        document.getElementById('card-comm-address').innerHTML = `
            ${member.comm_address_line1 || ''}<br>
            ${member.comm_address_line2 || ''}<br>
            ${member.comm_city || ''} - ${member.comm_pincode || ''}
        `;

        document.getElementById('card-edit-btn').onclick = function () {
            closeProfileCard();
            populateEditForm(member);
        };

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
        const options = { day: '2-digit', month: 'short', year: 'numeric' };
        return dateObj.toLocaleDateString('en-US', options);
    }

    function formatDateMonthYearJS(dateString) {
        if (!dateString) return '---';
        const dateObj = new Date(dateString);
        const options = { month: 'short', year: 'numeric' };
        return dateObj.toLocaleDateString('en-US', options);
    }

    function formatMonthInputJS(dateObj) {
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}`;
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php require_once 'footer.php'; ?>
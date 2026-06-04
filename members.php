<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch options & complete members listing
$wards_list = ["Ward 1", "Ward 2", "Ward 3", "Ward 4", "Ward 5", "Ward 6"];
$members = $db->query("SELECT * FROM members ORDER BY date_added DESC")->fetchAll();

// Fetch already assigned primary leadership roles to enforce uniqueness (ignoring deceased records)
$assigned_roles = $db->query("
    SELECT DISTINCT designation 
    FROM members 
    WHERE status != 'Deceased' 
    AND designation IN ('President', 'Vice President', 'Secretary', 'Joint-Secretary', 'Treasurer')
")->fetchAll(PDO::FETCH_COLUMN);

// Color dictionary mapping each designation to an elegant premium Tailwind badge color
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
                    calculated ages</p>
            </div>

            <!-- Filter Actions and Add Member Direct toggle -->
            <div class="flex flex-wrap gap-2.5">
                <select id="filter-mahallah" onchange="filterMembers()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Mahallahs</option>
                    <?php foreach ($wards_list as $w_opt): ?>
                        <option value="<?php echo $w_opt; ?>"><?php echo $w_opt; ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter-status" onchange="filterMembers()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Statuses</option>
                    <option value="Alive">Alive</option>
                    <option value="Deceased">Deceased (Marhoom)</option>
                </select>
                <select id="filter-chanda" onchange="filterMembers()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="All">All Chanda</option>
                    <option value="Paid">Paid Only</option>
                    <option value="Unpaid">Unpaid Only</option>
                </select>
                <button onclick="toggleAddMemberForm()"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-sm transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus"></i> <span id="toggle-form-btn-text">Register New Member</span>
                </button>
            </div>
        </div>

        <!-- Add Member dynamic slide down section -->
        <div id="add-member-form-section" class="hidden mb-8 border-t border-slate-100 pt-6">
            <div class="max-w-3xl mx-auto bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-800 to-teal-900 p-5 text-white">
                    <h4 class="font-bold text-sm">Register New Jamaath Member Console</h4>
                    <p class="text-[11px] text-emerald-200">Log demographic details.</p>
                </div>
                <form method="POST" action="actions.php" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                    <input type="hidden" name="action" value="add_member">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div
                            class="flex flex-col items-center justify-center p-3 border border-dashed border-slate-200 rounded-xl bg-white">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Member
                                Photo</label>
                            <div
                                class="relative w-28 h-28 bg-slate-100 rounded-2xl overflow-hidden mb-2 border border-slate-200 flex items-center justify-center">
                                <img id="photo-preview" src="https://placehold.co/150x150/0f766e/ffffff?text=No+Photo"
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">First Name *</label>
                                    <input type="text" name="first_name" required placeholder="First Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Last Name *</label>
                                    <input type="text" name="last_name" required placeholder="Last Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Card No (Attai No) *</label>
                                    <input type="text" name="card_no" required placeholder="e.g. K-104"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Dependents (Count) *</label>
                                    <div class="flex gap-2">
                                        <input type="number" id="dependents_count" name="dependents_count"
                                            oninput="checkDependents()" required min="0" max="15" value="0"
                                            class="w-20 bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                        <button type="button" id="add-dependent-btn" onclick="openDependentModal()"
                                            class="hidden bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-[10px] font-bold px-3 py-2 rounded-lg flex items-center gap-1.5 transition-all">
                                            <i class="fa-solid fa-people-roof"></i> Add Dependent
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Date of Birth *</label>
                            <input type="date" name="dob" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Gender *</label>
                            <select name="gender" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Father Name *</label>
                            <input type="text" name="father_husband_name" required placeholder="Name"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Mahallah / Ward *</label>
                            <select name="mahallah" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="">Select Mahallah</option>
                                <?php foreach ($wards_list as $w_nm): ?>
                                    <option value="<?php echo $w_nm; ?>"><?php echo $w_nm; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Phone Number *</label>
                            <input type="tel" name="phone" required placeholder="Phone"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Blood Group</label>
                            <select name="blood_group"
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
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Occupation</label>
                            <input type="text" name="occupation" placeholder="Merchant, Software Engineer etc."
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Designation within Jamaath *</label>
                            <select name="designation" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Ordinary Member">Ordinary Member</option>
                                <?php
                                $possible_roles = ["President", "Vice President", "Secretary", "Joint-Secretary", "Treasurer", "Executive Member"];
                                foreach ($possible_roles as $role):
                                    $is_assigned = in_array($role, $assigned_roles);
                                    $disabled_attr = $is_assigned ? 'disabled class="text-slate-400 bg-slate-100"' : '';
                                    $display_name = $role . ($is_assigned ? ' (Already Assigned)' : '');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $disabled_attr; ?>>
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div></div>
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

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Life Status *</label>
                            <select name="status" onchange="toggleFormDeceasedDate(this.value)" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Alive">Alive</option>
                                <option value="Deceased">Deceased (Marhoom)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 mb-1">Subscription (Chanda) *</label>
                            <select name="chanda_status" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                                <option value="Paid">Paid (Current Month)</option>
                                <option value="Unpaid">Unpaid</option>
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
                        <button type="submit"
                            class="bg-emerald-700 text-white px-5 py-2 rounded-lg font-bold shadow-sm">
                            Register</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Instant Search Workspace bar -->
        <div class="relative mb-6">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="search-member" onkeyup="filterMembers()"
                placeholder="Search members by First Name, Last Name, Family Card No, Phone..."
                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none transition-all">
        </div>

        <!-- Members Dynamic Data Table Grid -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr
                        class="bg-slate-50/50 border-b border-slate-200 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-4 px-4 rounded-l-xl">Member Name & Age</th>
                        <th class="py-4 px-4">Card / Attai No</th>
                        <th class="py-4 px-4">Dependents</th>
                        <th class="py-4 px-4">Contact & Ward</th>
                        <th class="py-4 px-4">Life Status</th>
                        <th class="py-4 px-4">Chanda Status</th>
                    </tr>
                </thead>
                <tbody id="members-table-rows" class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($members)): ?>
                        <tr id="empty-members-row">
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">No registered members found in
                                the database.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member):
                            $age = calculateAge($member['dob']);
                            // Grab color config for designation safely (default to Ordinary class if not found)
                            $role_clean = !empty($member['designation']) ? $member['designation'] : 'Ordinary Member';
                            $badge_class = isset($designation_colors[$role_clean]) ? $designation_colors[$role_clean] : $designation_colors['Ordinary Member'];
                            ?>
                            <tr class="member-record-row hover:bg-slate-50/70 transition-colors"
                                data-fname="<?php echo htmlspecialchars(strtolower($member['first_name'])); ?>"
                                data-lname="<?php echo htmlspecialchars(strtolower($member['last_name'])); ?>"
                                data-card="<?php echo htmlspecialchars(strtolower($member['card_no'])); ?>"
                                data-father="<?php echo htmlspecialchars(strtolower($member['father_husband_name'])); ?>"
                                data-phone="<?php echo htmlspecialchars($member['phone']); ?>"
                                data-mahallah="<?php echo htmlspecialchars($member['mahallah']); ?>"
                                data-status="<?php echo htmlspecialchars($member['status']); ?>"
                                data-chanda="<?php echo htmlspecialchars($member['chanda_status']); ?>">
                                <td class="py-4 px-4 flex items-center space-x-3">
                                    <img src="<?php echo htmlspecialchars($member['photo']); ?>"
                                        class="w-10 h-10 rounded-full border border-slate-200 object-cover shadow-sm"
                                        onerror="this.src='https://placehold.co/150x150/0f766e/ffffff?text=<?php echo urlencode($member['first_name']); ?>'">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-1.5 leading-tight">
                                            <p class="font-bold text-slate-800 leading-tight">
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            </p>

                                            <!-- Colored Badges placed directly next to names -->
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
                                    <?php echo htmlspecialchars($member['card_no']); ?></td>
                                <td class="py-4 px-4 font-semibold text-slate-500 text-xs">
                                    <?php echo htmlspecialchars($member['dependents_count']); ?> Dependents</td>
                                <td class="py-4 px-4">
                                    <p class="text-xs font-semibold text-slate-700">
                                        <?php echo htmlspecialchars($member['phone']); ?></p>
                                    <p class="text-[10px] text-slate-400 truncate max-w-[150px]">
                                        <?php echo htmlspecialchars($member['mahallah']); ?></p>
                                </td>
                                <td class="py-4 px-4">
                                    <?php if ($member['status'] === 'Alive'): ?>
                                        <span
                                            class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Alive</span>
                                    <?php else: ?>
                                        <span
                                            class="bg-rose-100 text-rose-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Deceased</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4">
                                    <?php if ($member['chanda_status'] === 'Paid'): ?>
                                        <span
                                            class="bg-sky-100 text-sky-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Paid</span>
                                    <?php else: ?>
                                        <span
                                            class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="no-members-view" class="hidden text-center py-12">
            <span class="text-3xl">🔍</span>
            <h4 class="text-slate-700 font-bold mt-3">No matching Jamaath members</h4>
            <p class="text-xs text-slate-400 mt-1">Please refine your directory filters or clear the search field.</p>
        </div>
    </div>
</div>

<!-- Modal Dialog: Trigger Add Dependents Placeholder -->
<div id="dependent-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-sm w-full p-6 text-center">
        <div
            class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
            <i class="fa-solid fa-people-roof"></i>
        </div>
        <h4 class="text-base font-bold text-slate-800 mb-2">Add Family Dependents</h4>
        <p class="text-xs text-slate-500 mb-6">Here's where you will add your dependent.</p>
        <button type="button" onclick="closeDependentModal()"
            class="w-full bg-slate-950 text-white py-2 rounded-xl text-xs font-semibold hover:bg-slate-800 transition-colors">
            Understood
        </button>
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
        }
    }

    function checkDependents() {
        const dependentsCount = parseInt(document.getElementById('dependents_count').value) || 0;
        const addBtn = document.getElementById('add-dependent-btn');
        if (dependentsCount >= 1) {
            addBtn.classList.remove('hidden');
        } else {
            addBtn.classList.add('hidden');
        }
    }

    function openDependentModal() {
        document.getElementById('dependent-modal').classList.remove('hidden');
    }

    function closeDependentModal() {
        document.getElementById('dependent-modal').classList.add('hidden');
    }

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

    // Mirror updates in real time if "same-address-check" is active
    ['address_line1', 'address_line2', 'city', 'pincode'].forEach(field => {
        document.getElementById('res_' + field).addEventListener('input', () => {
            if (document.getElementById('same-address-check').checked) {
                syncAddresses();
            }
        });
    });

    function filterMembers() {
        const filterMahallah = document.getElementById('filter-mahallah').value;
        const filterStatus = document.getElementById('filter-status').value;
        const filterChanda = document.getElementById('filter-chanda').value;
        const searchVal = document.getElementById('search-member').value.trim().toLowerCase();

        const rows = document.querySelectorAll('.member-record-row');
        let matchingCount = 0;

        rows.forEach(row => {
            const rowFname = row.getAttribute('data-fname');
            const rowLname = row.getAttribute('data-lname');
            const rowCard = row.getAttribute('data-card');
            const rowFather = row.getAttribute('data-father');
            const rowPhone = row.getAttribute('data-phone');
            const rowMahallah = row.getAttribute('data-mahallah');
            const rowStatus = row.getAttribute('data-status');
            const rowChanda = row.getAttribute('data-chanda');

            const matchesMahallah = filterMahallah === 'All' || rowMahallah === filterMahallah;
            const matchesStatus = filterStatus === 'All' || rowStatus === filterStatus;
            const matchesChanda = filterChanda === 'All' || rowChanda === filterChanda;

            const matchesSearch = searchVal === '' ||
                rowFname.includes(searchVal) ||
                rowLname.includes(searchVal) ||
                rowCard.includes(searchVal) ||
                rowFather.includes(searchVal) ||
                rowPhone.includes(searchVal);

            if (matchesMahallah && matchesStatus && matchesChanda && matchesSearch) {
                row.classList.remove('hidden');
                matchingCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        const noMembersView = document.getElementById('no-members-view');
        const emptyRow = document.getElementById('empty-members-row');

        if (matchingCount === 0) {
            noMembersView.classList.remove('hidden');
            if (emptyRow) emptyRow.classList.add('hidden');
        } else {
            noMembersView.classList.add('hidden');
        }
    }
</script>

<?php require_once 'footer.php'; ?>
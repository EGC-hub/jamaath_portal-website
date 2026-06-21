<?php
require_once 'db.php';
require_once 'helpers.php';

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build dynamic search queries
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(deceased_name LIKE ? OR plot_details LIKE ? OR deceased_jamath LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM burial_registry $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1)
    $total_pages = 1;
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch paginated Burial list with optional Joined Member details
$fetch_stmt = $db->prepare("
    SELECT b.*, m.first_name AS rep_first, m.last_name AS rep_last, m.card_no AS rep_card, m.phone AS rep_phone
    FROM burial_registry b
    LEFT JOIN members m ON b.reporter_member_id = m.id
    $where_sql 
    ORDER BY b.burial_datetime DESC, b.id DESC 
    LIMIT $limit OFFSET $offset
");
$fetch_stmt->execute($params);
$burial_list = $fetch_stmt->fetchAll();

// Fetch active members who are alive (to populate selectors for deceased selection & informant selection)
$alive_members = $db->query("SELECT id, first_name, last_name, card_no, father_husband_name, dob, gender FROM members WHERE status = 'Alive' ORDER BY first_name ASC")->fetchAll();

// Fetch active dependents of alive members (for deceased selection)
$alive_dependents = $db->query("
    SELECT d.id, d.name, d.relationship, d.dob, d.gender,
           m.first_name AS prim_first, m.last_name AS prim_last, m.card_no AS prim_card
    FROM member_dependents d
    JOIN members m ON d.member_id = m.id
    WHERE m.status = 'Alive'
    ORDER BY d.name ASC
")->fetchAll();

require_once 'header.php';
?>

<!-- HTML2PDF CDN Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Cemetery Burial Registry Archive</h3>
            <p class="text-xs text-slate-500 font-medium">Official administrative directory tracking final resting
                locations, NOC verifications, and family or informant links</p>
        </div>
        <button onclick="openBurialModal()"
            class="bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-monument"></i> Log New Burial Entry
        </button>
    </div>

    <!-- Dynamic Filter Search Workplace -->
    <form method="GET" action="" class="flex gap-2 mb-6">
        <div class="relative flex-grow">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search by Deceased Name, resting location details, or origin Jamaath..."
                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-rose-500 focus:bg-white focus:outline-none transition-all">
        </div>

        <button type="submit"
            class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-5 py-3 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm">
            <i class="fa-solid fa-magnifying-glass"></i> <span>Search</span>
        </button>

        <?php if (!empty($search)): ?>
            <a href="burial.php"
                class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-4 py-3 rounded-xl transition-all flex items-center justify-center">
                Clear
            </a>
        <?php endif; ?>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr
                    class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider font-semibold bg-slate-50/50">
                    <th class="py-4 px-4 rounded-l-xl">Deceased Name</th>
                    <th class="py-4 px-4">Origin Profile</th>
                    <th class="py-4 px-4">Burial Date & Time</th>
                    <th class="py-4 px-4">Resting location Details</th>
                    <th class="py-4 px-4">NOC / Jamaath Authority</th>
                    <th class="py-4 px-4 text-right rounded-r-xl">Actions</th>
                </tr>
            </thead>
            <tbody id="burial-table-rows" class="divide-y divide-slate-100 text-xs">
                <?php if (empty($burial_list)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400 text-xs">No burial entries found
                            inside jamaath registries.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($burial_list as $burial): ?>
                        <tr onclick='openBurialCard(<?php echo json_encode($burial); ?>)'
                            class="hover:bg-slate-50/75 transition-colors cursor-pointer">
                            <td class="py-4 px-4 font-bold text-slate-800 text-sm">
                                🕊️ <?php echo htmlspecialchars($burial['deceased_name']); ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($burial['is_jamaath_member'] == 1): ?>
                                    <span
                                        class="bg-emerald-50 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded border border-emerald-150 uppercase tracking-wider">
                                        Jamaath Member
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-0.5 rounded border border-slate-200 uppercase tracking-wider">
                                        <?php echo htmlspecialchars($burial['deceased_jamath']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 font-semibold text-rose-800">
                                <span class="bg-rose-50 px-2.5 py-1 rounded-md text-[10px]">
                                    <i class="fa-solid fa-clock mr-1"></i>
                                    <?php echo date('d M Y - h:i A', strtotime($burial['burial_datetime'])); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 font-medium text-slate-700">
                                <i class="fa-solid fa-map-pin mr-1 text-slate-400"></i>
                                <?php echo htmlspecialchars($burial['plot_details']); ?>
                            </td>
                            <td class="py-4 px-4 font-medium">
                                <?php if ($burial['is_jamaath_member'] == 1): ?>
                                    <span class="text-emerald-700 font-bold"><i class="fa-solid fa-circle-check mr-1"></i> Exempt
                                        (Member)</span>
                                <?php elseif ($burial['noc_provided'] == 1): ?>
                                    <span class="text-teal-700 font-bold"><i class="fa-solid fa-file-shield mr-1"></i> NOC
                                        Approved</span>
                                <?php else: ?>
                                    <span class="text-rose-600 font-bold"><i class="fa-solid fa-circle-xmark mr-1"></i> Missing
                                        NOC</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div onclick="event.stopPropagation()" class="flex items-center justify-end gap-1.5">
                                    <button onclick='openBurialCard(<?php echo json_encode($burial); ?>)'
                                        class="bg-slate-50 hover:bg-slate-100 text-slate-600 p-1.5 rounded-lg border border-slate-200 text-xs transition-colors"
                                        title="View Details Popup Card">
                                        <i class="fa-solid fa-address-card text-rose-700"></i>
                                    </button>

                                    <button onclick='populateEditBurial(<?php echo json_encode($burial); ?>)'
                                        class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors"
                                        title="Edit Burial Details">
                                        <i class="fa-solid fa-user-gear"></i>
                                    </button>

                                    <form method="POST" action="actions.php"
                                        onsubmit="return confirm('Are you sure you want to delete this burial registry contract permanently? Parent member statuses will auto-sync back to Alive.');"
                                        class="inline">
                                        <input type="hidden" name="action" value="delete_burial">
                                        <input type="hidden" name="id" value="<?php echo $burial['id']; ?>">
                                        <button type="submit"
                                            class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors"
                                            title="Delete record permanently">
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

    <!-- Pagination controls -->
    <?php if ($total_pages > 1): ?>
        <div class="flex items-center justify-between border-t border-slate-100 pt-5 mt-5">
            <p class="text-xs text-slate-500">Showing page <span
                    class="font-bold text-slate-800"><?php echo $page; ?></span> of <span
                    class="font-bold text-slate-800"><?php echo $total_pages; ?></span> pages</p>
            <div class="flex gap-1 text-xs">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"
                        class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold text-slate-700 hover:bg-slate-100 transition-colors">&laquo;
                        Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"
                        class="px-3 py-1.5 rounded-lg font-semibold border transition-all <?php echo $i == $page ? 'bg-rose-700 border-rose-700 text-white' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"
                        class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold text-slate-700 hover:bg-slate-100 transition-colors">Next
                        &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Interactive detailed Pop-up card for Burial -->
<div id="burial-card-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div
        class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">

        <div class="bg-gradient-to-r from-rose-800 to-slate-950 p-6 text-white relative">
            <button onclick="closeBurialCard()"
                class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors text-lg">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>
            <div class="text-center space-y-1">
                <span class="text-3xl">🕊️</span>
                <h4 class="text-lg font-bold serif-title"> Burial Archive Details</h4>
                <p id="card-date-header" class="text-xs text-rose-200 font-mono">---</p>
            </div>
        </div>

        <div class="p-6 space-y-5 overflow-y-auto text-xs text-slate-700">
            <!-- Double Columns: Deceased Profile vs Informant Profile -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Column A: Deceased Profile -->
                <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-2.5">
                    <p
                        class="text-[10px] font-bold text-rose-800 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                        <i class="fa-solid fa-monument"></i> Deceased Profile
                    </p>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Deceased Name</p>
                        <p id="pop-dec-name" class="font-bold text-slate-800 text-sm">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Father's / Husband's Name</p>
                        <p id="pop-dec-father" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Age</p>
                            <p id="pop-dec-age" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Gender</p>
                            <p id="pop-dec-gender" class="font-semibold text-slate-700">---</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Jamaath Origin Affiliation</p>
                        <p id="pop-dec-jamath" class="font-semibold text-slate-700">---</p>
                    </div>
                </div>

                <!-- Column B: Informant Profile -->
                <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-2.5">
                    <p
                        class="text-[10px] font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                        <i class="fa-solid fa-user-pen"></i> Informant / Reporter
                    </p>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Informant Name</p>
                        <p id="pop-rep-name" class="font-bold text-slate-800 text-sm">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Contact Phone</p>
                        <p id="pop-rep-phone" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Relationship to Deceased</p>
                        <p id="pop-rep-relationship" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Authority Check</p>
                        <p id="pop-rep-authority" class="font-semibold text-slate-700">---</p>
                    </div>
                </div>
            </div>

            <!-- Ceremony timestamps and plot mappings -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-100/55 p-4 rounded-xl border border-slate-200">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Demise Date & Time</p>
                    <p id="pop-death-time" class="font-semibold text-slate-800 mt-0.5">---</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Burial Date & Time</p>
                    <p id="pop-burial-time" class="font-semibold text-slate-800 mt-0.5">---</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-3.5 border border-slate-150 rounded-xl bg-rose-50/20">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cemetery resting plot
                        details</p>
                    <p id="pop-plot" class="font-bold text-rose-900 mt-1 text-sm">---</p>
                </div>
                <div class="p-3.5 border border-slate-150 rounded-xl bg-teal-50/25 flex flex-col justify-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">No Objection
                        Certificate (NOC)</p>
                    <p id="pop-noc-state" class="font-extrabold text-teal-800 text-sm">---</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <button id="pop-edit-btn"
                    class="bg-rose-700 hover:bg-rose-800 text-white font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-user-gear"></i> Update Burial Info
                </button>

                <!-- Dynamic High-Fidelity Certificate Generation Target -->
                <button id="pop-cert-btn"
                    class="font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-file-pdf"></i> Issue Burial Certificate
                </button>
            </div>
            <button onclick="closeBurialCard()"
                class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-5 py-2 rounded-xl">
                Close Card
            </button>
        </div>
    </div>
</div>

<!-- Modal: Log & Edit Burial Registry Entry -->
<div id="burial-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div
        class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-2">
            <h4 id="burial-form-title" class="text-lg font-bold text-slate-800">Log Cemetery Burial location</h4>
            <button onclick="closeBurialModal()" class="text-slate-400 hover:text-slate-600 transition-colors"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <p class="text-xs text-slate-500 mb-4 font-medium">Configure resting locations, timelines, and NOC authorization
            records for deceased individuals.</p>

        <form id="burial-form" method="POST" action="actions.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" id="burial-form-action" value="add_burial">
            <input type="hidden" name="id" id="burial-form-id" value="">

            <!-- Category Check: Jamaath Member or External Profile -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <h5 class="font-bold text-rose-900 text-xs flex items-center gap-1.5"><i class="fa-solid fa-dove"></i>
                    Deceased Origin Profile</h5>

                <div id="deceased-origin-options-container">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Select Deceased
                        Classification *</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-700 select-none">
                            <input type="radio" id="dec_origin_member" name="is_jamaath_member" value="1" checked
                                onchange="toggleDeceasedFields()" class="text-rose-600 focus:ring-rose-500 h-4 w-4">
                            Jamaath Primary Member
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-700 select-none">
                            <input type="radio" id="dec_origin_dependent" name="is_jamaath_member" value="2"
                                onchange="toggleDeceasedFields()" class="text-rose-600 focus:ring-rose-500 h-4 w-4">
                            Jamaath Dependent
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-700 select-none">
                            <input type="radio" id="dec_origin_external" name="is_jamaath_member" value="0"
                                onchange="toggleDeceasedFields()" class="text-rose-600 focus:ring-rose-500 h-4 w-4">
                            Non-Jamaath Member
                        </label>
                    </div>
                </div>

                <!-- Selector: Primary Alive Members -->
                <div id="deceased_member_select_container" class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Deceased Primary Member
                        *</label>
                    <select id="deceased_member_select" name="deceased_member_id"
                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        <option value="">-- Choose Member --</option>
                        <?php foreach ($alive_members as $m):
                            $age = calculateAge($m['dob']); ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' (Age: ' . $age . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Selector: Alive Dependents -->
                <div id="deceased_dependent_select_container" class="space-y-1 hidden">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Deceased Family Dependent
                        *</label>
                    <select id="deceased_dependent_select" name="deceased_dependent_id"
                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        <option value="">-- Choose Dependent --</option>
                        <?php foreach ($alive_dependents as $d): ?>
                            <option value="<?php echo $d['id']; ?>">
                                <?php echo htmlspecialchars($d['name'] . ' (' . $d['relationship'] . ' of ' . $d['prim_first'] . ' ' . $d['prim_last'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Manual Fields (for Non-Jamaath Profiles) -->
                <div id="deceased_manual_fields_container" class="space-y-3 hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Deceased Full Name
                                *</label>
                            <input type="text" id="manual_deceased_name" name="manual_deceased_name"
                                placeholder="Full Name"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Father's /
                                Husband's Name *</label>
                            <input type="text" id="manual_deceased_father" name="manual_deceased_father"
                                placeholder="Father's / Husband's Name"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Age *</label>
                            <input type="number" id="manual_deceased_age" name="manual_deceased_age" min="0"
                                placeholder="Age"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Gender *</label>
                            <select id="manual_deceased_gender" name="manual_deceased_gender"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Origin Jamaath
                                Address *</label>
                            <input type="text" id="manual_deceased_jamath" name="manual_deceased_jamath"
                                placeholder="e.g. Kottar Jamaath"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="bg-rose-50/40 p-3 rounded-lg border border-rose-100 flex items-center h-full">
                        <label
                            class="flex items-center gap-2 cursor-pointer text-xs text-slate-700 font-bold select-none">
                            <input type="checkbox" id="noc_provided_check" name="noc_provided" value="1"
                                class="h-4 w-4 text-rose-600 focus:ring-rose-500 rounded border-slate-300"> No Objection
                            Certificate (NOC) fully provided & verified
                        </label>
                    </div>
                </div>
            </div>

            <!-- Informant / Reporter details -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <h5 class="font-bold text-slate-700 text-xs flex items-center gap-1.5"><i
                        class="fa-solid fa-user-pen"></i> Informant / Death Reporter Profile</h5>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Informant Origin *</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-600">
                            <input type="radio" id="rep_origin_member" name="reported_by_member" value="1" checked
                                onchange="toggleReporterFields()" class="text-rose-600 focus:ring-rose-500 h-4 w-4">
                            Active Jamaath Member
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-600">
                            <input type="radio" id="rep_origin_external" name="reported_by_member" value="0"
                                onchange="toggleReporterFields()" class="text-rose-600 focus:ring-rose-500 h-4 w-4">
                            Non-Member / Custom Contact
                        </label>
                    </div>
                </div>

                <!-- Choice A: Selector for Alive Primary members -->
                <div id="reporter_member_container" class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Informant Member
                            *</label>
                        <select id="reporter_member_select" name="reporter_member_id"
                            class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                            <option value="">-- Choose Member --</option>
                            <?php foreach ($alive_members as $m_rep): ?>
                                <option value="<?php echo $m_rep['id']; ?>">
                                    <?php echo htmlspecialchars($m_rep['first_name'] . ' ' . $m_rep['last_name'] . ' (Card: ' . $m_rep['card_no'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Added Relationship Field for Jamaath Members -->
                    <div id="member_relationship_box">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Relationship to
                            Deceased *</label>
                        <input type="text" id="member_reporter_relationship" name="reporter_relationship"
                            placeholder="e.g. Son, Daughter, Spouse, Neighbor"
                            class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                    </div>
                </div>

                <!-- Choice B: Custom Text inputs for Non-member Informant -->
                <div id="reporter_custom_container" class="space-y-3 hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Informant Name
                                *</label>
                            <input type="text" id="reporter_name" name="reporter_name" placeholder="Name"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Informant Phone
                                *</label>
                            <input type="tel" id="reporter_phone" name="reporter_phone" placeholder="Phone"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Relationship to
                                Deceased *</label>
                            <input type="text" id="reporter_relationship" name="reporter_relationship"
                                placeholder="e.g. Son, Wife, Brother"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plot Metadata and Datetime Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Date & Time of Demise
                        *</label>
                    <input type="datetime-local" id="death-datetime-field" name="death_datetime" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Date & Time of Burial
                        *</label>
                    <input type="datetime-local" id="burial-datetime-field" name="burial_datetime" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Burial Resting location details
                    (Location Row/No) *</label>
                <input type="text" id="plot-details-field" name="plot_details" required
                    placeholder="e.g. Graveyard Ground-B, Row #4, Grave #12"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
            </div>

            <div class="flex items-center space-x-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeBurialModal()"
                    class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="burial-form-submit"
                    class="w-1/2 bg-rose-700 hover:bg-rose-800 text-white py-3 rounded-xl font-bold shadow-md transition-colors">
                    Archive Burial Registry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // For date and time of burial validation
    document.addEventListener("DOMContentLoaded", function () {
        const deathInput = document.getElementById('death-datetime-field');
        const burialInput = document.getElementById('burial-datetime-field');
        const burialForm = document.getElementById('chanda_report_form') || deathInput.closest('form');

        // 1. Lock/Unlock burial picker constraints dynamically based on demise selection
        deathInput.addEventListener('change', function () {
            if (this.value) {
                // Forces the burial input to only allow timestamps AFTER the chosen demise time
                burialInput.min = this.value;
            } else {
                burialInput.removeAttribute('min');
            }
        });

        // 2. Fail-safe verification interceptor when clicking submit
        if (burialForm) {
            burialForm.addEventListener('submit', function (e) {
                if (deathInput.value && burialInput.value) {
                    const deathTime = new Date(deathInput.value).getTime();
                    const burialTime = new Date(burialInput.value).getTime();

                    if (burialTime <= deathTime) {
                        e.preventDefault(); // Stop form submission entirely
                        alert("⚠️ Validation Error: The Date & Time of Burial must be strictly after the Date & Time of Demise.");
                        burialInput.focus();
                        return false;
                    }
                }
            });
        }
    });

    // Open Pop-up details card modal
    function openBurialCard(burial) {
        document.getElementById('card-date-header').textContent = "Buried Date: " + formatDateJS(burial.burial_datetime);

        // Deceased Details Populating
        document.getElementById('pop-dec-name').textContent = burial.deceased_name;
        document.getElementById('pop-dec-father').textContent = burial.deceased_father_husband ? burial.deceased_father_husband : "---";
        document.getElementById('pop-dec-age').textContent = burial.deceased_age ? burial.deceased_age + " Years" : "Unknown Age";
        document.getElementById('pop-dec-gender').textContent = burial.deceased_gender || "---";
        document.getElementById('pop-dec-jamath').textContent = (burial.is_jamaath_member == 1) ? "NVK Jamath (Vadasery)" : burial.deceased_jamath;

        // Informant details Populating
        if (burial.reported_by_member == 1) {
            document.getElementById('pop-rep-name').textContent = burial.rep_first + ' ' + burial.rep_last;
            document.getElementById('pop-rep-phone').textContent = burial.rep_phone || "---";
            document.getElementById('pop-rep-relationship').textContent = burial.reporter_relationship || "---";
            document.getElementById('pop-rep-authority').textContent = "Active Member (Card: " + burial.rep_card + ")";
        } else {
            document.getElementById('pop-rep-name').textContent = burial.reporter_name || "---";
            document.getElementById('pop-rep-phone').textContent = burial.reporter_phone || "---";
            document.getElementById('pop-rep-relationship').textContent = burial.reporter_relationship || "---";
            document.getElementById('pop-rep-authority').textContent = "Private Contact Person";
        }

        // Timestamps and Plots
        document.getElementById('pop-death-time').textContent = burial.death_datetime ? formatDateJS(burial.death_datetime) : "---";
        document.getElementById('pop-burial-time').textContent = formatDateJS(burial.burial_datetime);
        document.getElementById('pop-plot').textContent = burial.plot_details;

        const nocState = document.getElementById('pop-noc-state');
        if (burial.is_jamaath_member == 1) {
            nocState.textContent = "Exempt (Jamaath Member)";
            nocState.className = "font-extrabold text-emerald-800 text-sm";
        } else if (burial.noc_provided == 1) {
            nocState.textContent = "Approved & NOC Attached";
            nocState.className = "font-extrabold text-teal-800 text-sm";
        } else {
            nocState.textContent = "NOC Missing / Hold State";
            nocState.className = "font-extrabold text-rose-700 text-sm";
        }

        // Configure Issue Certificate Button with strict validation conditions
        const certBtn = document.getElementById('pop-cert-btn');
        const canGenerate = (burial.is_jamaath_member == 1) || (burial.is_jamaath_member == 0 && burial.noc_provided == 1);

        if (canGenerate) {
            certBtn.disabled = false;
            certBtn.title = "Download high-fidelity death and cemetery burial certificate PDF";
            certBtn.className = "bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors cursor-pointer";
            certBtn.onclick = function () {
                issueBurialCertificate(burial);
            };
        } else {
            certBtn.disabled = true;
            certBtn.title = "Issuance is restricted. Non-Jamaath records require an approved NOC (No Objection Certificate) before generating burial certificates.";
            certBtn.className = "bg-slate-100 text-slate-400 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 cursor-not-allowed";
            certBtn.onclick = null;
        }

        document.getElementById('pop-edit-btn').onclick = function () {
            closeBurialCard();
            populateEditBurial(burial);
        };

        document.getElementById('burial-card-modal').classList.remove('hidden');
    }

    function closeBurialCard() {
        document.getElementById('burial-card-modal').classList.add('hidden');
    }

    // Toggle fields based on deceased category selections
    function toggleDeceasedFields() {
        const isJamathSelection = document.querySelector('input[name="is_jamaath_member"]:checked').value;
        const memberContainer = document.getElementById('deceased_member_select_container');
        const dependentContainer = document.getElementById('deceased_dependent_select_container');
        const manualContainer = document.getElementById('deceased_manual_fields_container');

        if (isJamathSelection == "1") {
            memberContainer.classList.remove('hidden');
            dependentContainer.classList.add('hidden');
            manualContainer.classList.add('hidden');
            document.getElementById('deceased_member_select').required = true;
            document.getElementById('deceased_dependent_select').required = false;
            document.getElementById('manual_deceased_name').required = false;
            document.getElementById('manual_deceased_father').required = false;
        } else if (isJamathSelection == "2") {
            memberContainer.classList.add('hidden');
            dependentContainer.classList.remove('hidden');
            manualContainer.classList.add('hidden');
            document.getElementById('deceased_member_select').required = false;
            document.getElementById('deceased_dependent_select').required = true;
            document.getElementById('manual_deceased_name').required = false;
            document.getElementById('manual_deceased_father').required = false;
        } else {
            memberContainer.classList.add('hidden');
            dependentContainer.classList.add('hidden');
            manualContainer.classList.remove('hidden');
            document.getElementById('deceased_member_select').required = false;
            document.getElementById('deceased_dependent_select').required = false;
            document.getElementById('manual_deceased_name').required = true;
            document.getElementById('manual_deceased_father').required = true;
        }
    }

    // Toggle Informant fields
    function toggleReporterFields() {
        const reportedByMember = document.querySelector('input[name="reported_by_member"]:checked').value;
        const memberReporterBox = document.getElementById('reporter_member_container');
        const customReporterBox = document.getElementById('reporter_custom_container');

        if (reportedByMember == "1") {
            memberReporterBox.classList.remove('hidden');
            customReporterBox.classList.add('hidden');
            document.getElementById('reporter_member_select').required = true;
            document.getElementById('member_reporter_relationship').required = true;
            document.getElementById('reporter_name').required = false;
            document.getElementById('reporter_phone').required = false;
            document.getElementById('reporter_relationship').required = false;
        } else {
            memberReporterBox.classList.add('hidden');
            customReporterBox.classList.remove('hidden');
            document.getElementById('reporter_member_select').required = false;
            document.getElementById('member_reporter_relationship').required = false;
            document.getElementById('reporter_name').required = true;
            document.getElementById('reporter_phone').required = true;
            document.getElementById('reporter_relationship').required = true;
        }
    }

    // Modal forms toggles
    function openBurialModal() {
        resetBurialForm();
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');

        document.getElementById('death-datetime-field').value = `${year}-${month}-${day}T${hours}:${minutes}`;
        document.getElementById('burial-datetime-field').value = `${year}-${month}-${day}T${hours}:${minutes}`;

        document.getElementById('burial-modal').classList.remove('hidden');
    }

    function populateEditBurial(burial) {
        openBurialModal();
        document.getElementById('burial-form-title').textContent = "Update Certified Cemetery Burial Entry";
        document.getElementById('burial-form-action').value = "edit_burial";
        document.getElementById('burial-form-id').value = burial.id;
        document.getElementById('burial-form-submit').textContent = "Save Changes";

        // Deactivate origin choices on edit to preserve database relations
        document.getElementById('deceased-origin-options-container').classList.add('hidden');

        // Set inputs
        if ((burial.is_jamaath_member == 1 || burial.is_jamaath_member == true) && burial.deceased_member_id) {
            document.getElementById('dec_origin_member').checked = true;

            // MODIFICATION: Target the select dropdown element
            const memberSelect = document.getElementById('deceased_member_select');

            // Check if an option with this member's ID already exists in the dropdown list
            let optionExists = Array.from(memberSelect.options).some(opt => opt.value == burial.deceased_member_id);

            // If it doesn't exist (because they are marked Deceased), dynamically inject them so they show perfectly!
            if (!optionExists) {
                const opt = document.createElement('option');
                opt.value = burial.deceased_member_id;

                // Strip out '(Marhoom)' from the name if it's already there to keep it looking clean in the dropdown
                let baseName = burial.deceased_name ? burial.deceased_name.replace(' (Marhoom)', '') : "Assigned Member";
                opt.textContent = baseName + " (Deceased Member)";

                memberSelect.appendChild(opt);
            }

            // Securely select the newly appended or existing ID value
            memberSelect.value = burial.deceased_member_id;

        } else if ((burial.is_jamaath_member == 1 || burial.is_jamaath_member == true) && burial.deceased_dependent_id) {
            document.getElementById('dec_origin_dependent').checked = true;

            // MODIFICATION: Apply the same dynamic fallback rule for Deceased Dependents dropdown
            const depSelect = document.getElementById('deceased_dependent_select');
            let optionExists = Array.from(depSelect.options).some(opt => opt.value == burial.deceased_dependent_id);

            if (!optionExists) {
                const opt = document.createElement('option');
                opt.value = burial.deceased_dependent_id;
                let baseName = burial.deceased_name ? burial.deceased_name.replace(' (Marhoom)', '') : "Assigned Dependent";
                opt.textContent = baseName + " (Deceased Dependent)";
                depSelect.appendChild(opt);
            }

            depSelect.value = burial.deceased_dependent_id;
        } else {
            document.getElementById('dec_origin_external').checked = true;
            document.getElementById('manual_deceased_name').value = burial.deceased_name;
            document.getElementById('manual_deceased_father').value = burial.deceased_father_husband || '';
            document.getElementById('manual_deceased_age').value = burial.deceased_age || '';
            document.getElementById('manual_deceased_gender').value = burial.deceased_gender || 'Male';
            document.getElementById('manual_deceased_jamath').value = burial.deceased_jamath || '';
            document.getElementById('noc_provided_check').checked = (burial.noc_provided == 1);
        }
        toggleDeceasedFields();

        // Informant/Reporter
        if (burial.reported_by_member == 1) {
            document.getElementById('rep_origin_member').checked = true;
            document.getElementById('reporter_member_select').value = burial.reporter_member_id;
            document.getElementById('member_reporter_relationship').value = burial.reporter_relationship || '';
        } else {
            document.getElementById('rep_origin_external').checked = true;
            document.getElementById('reporter_name').value = burial.reporter_name || '';
            document.getElementById('reporter_phone').value = burial.reporter_phone || '';
            document.getElementById('reporter_relationship').value = burial.reporter_relationship || '';
        }
        toggleReporterFields();

        // Datetimes & Plot details
        document.getElementById('death-datetime-field').value = burial.death_datetime ? burial.death_datetime.replace(" ", "T").substring(0, 16) : '';
        document.getElementById('burial-datetime-field').value = burial.burial_datetime.replace(" ", "T").substring(0, 16);
        document.getElementById('plot-details-field').value = burial.plot_details;
    }

    function resetBurialForm() {
        document.getElementById('burial-form').reset();
        document.getElementById('burial-form-title').textContent = "Log Certified Cemetery Burial Plot";
        document.getElementById('burial-form-action').value = "add_burial";
        document.getElementById('burial-form-id').value = "";
        document.getElementById('burial-form-submit').textContent = "Archive Burial Registry";

        document.getElementById('deceased-origin-options-container').classList.remove('hidden');
        document.getElementById('dec_origin_member').checked = true;
        document.getElementById('rep_origin_member').checked = true;

        toggleDeceasedFields();
        toggleReporterFields();
    }

    function closeBurialModal() {
        document.getElementById('burial-modal').classList.add('hidden');
    }

    function formatDateJS(dateString) {
        if (!dateString) return '---';
        const dateObj = new Date(dateString);
        const options = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        return dateObj.toLocaleDateString('en-US', options);
    }

    // High-Fidelity Landscape PDF Burial Certificate Engine
    function issueBurialCertificate(burial) {
        const formattedBurialDate = formatDateJS(burial.burial_datetime);
        const formattedDeathDate = burial.death_datetime ? formatDateJS(burial.death_datetime) : "N/A";
        const relationLabel = burial.deceased_father_husband ? burial.deceased_father_husband : "Biographical Details Verified";

        let originStatus = '';
        if (burial.is_jamaath_member == 1) {
            originStatus = "NVK Jamaath Certified Resident (Exempt from NOC)";
        } else {
            originStatus = "Outside Resident - NOC Verified Reference: " + burial.deceased_jamath;
        }

        const opt = {
            margin: 0.3,
            filename: `Burial_Certificate_${burial.deceased_name.replace(/\s+/g, '_')}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
        };

        const certTemplate = document.createElement('div');
        certTemplate.style.width = '10.4in';
        certTemplate.style.height = '7.5in';
        certTemplate.style.padding = '0.4in';
        certTemplate.style.boxSizing = 'border-box';
        certTemplate.style.background = '#ffffff';
        certTemplate.style.fontFamily = 'Georgia, serif';

        certTemplate.innerHTML = `
            <div style="border: 15px double #047857; padding: 25px; height: 100%; box-sizing: border-box; position: relative; background-image: radial-gradient(circle, #e6f4ea 1px, transparent 1px); background-size: 20px 20px; background-color: #fafbfc;">
                
                <!-- Corner Crest Badges -->
                <div style="position: absolute; top: 12px; left: 12px; color: #047857; font-size: 20px;">🕌</div>
                <div style="position: absolute; top: 12px; right: 12px; color: #047857; font-size: 20px;">🕌</div>
                <div style="position: absolute; bottom: 12px; left: 12px; color: #047857; font-size: 20px;">🕌</div>
                <div style="position: absolute; bottom: 12px; right: 12px; color: #047857; font-size: 20px;">🕌</div>
                
                <!-- Headers -->
                <div style="text-align: center; margin-bottom: 15px;">
                    <h1 style="margin: 0; color: #064e3b; font-size: 30px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;">NVK Muslim Jamaath</h1>
                    <p style="margin: 5px 0 0 0; font-size: 11px; text-transform: uppercase; letter-spacing: 4px; font-weight: bold; color: #047857;">Vadasery, Nagercoil, Kanyakumari District, Tamil Nadu</p>
                    <div style="width: 250px; height: 3px; background: linear-gradient(to right, transparent, #059669, transparent); margin: 12px auto 4px auto;"></div>
                    <div style="width: 150px; height: 1px; background: #e2e8f0; margin: 0 auto;"></div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="font-family: Georgia, serif; font-style: italic; color: #047857; font-size: 24px; margin: 5px 0;">Official Burial Certificate</h2>
                    <p style="font-size: 12px; color: #64748b; margin: 0; font-family: sans-serif;">This is to certify the official registration of demise and cemetery resting ground allocation under Jamaath authority.</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 35px; font-size: 15px;">
                    <tr>
                        <td style="width: 50%; padding: 12px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #047857; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Deceased Name</strong>
                                <span style="font-size: 17px; color: #1e293b; font-weight: bold;">🕊️ ${burial.deceased_name}</span>
                            </div>
                        </td>
                        <td style="width: 50%; padding: 12px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #047857; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Father / Husband Name</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: bold;">${relationLabel}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #047857; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Date & Time of Demise</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: 600;">${formattedDeathDate}</span>
                            </div>
                        </td>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #047857; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Date & Time of Burial</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: 600;">${formattedBurialDate}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #047857; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Cemetery details</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: bold; color: #064e3b;">📍 ${burial.plot_details}</span>
                            </div>
                        </td>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #047857; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Authorization Registry & NOC</strong>
                                <span style="font-size: 14px; color: #334155; font-style: italic;">${originStatus}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 45px; display: flex; justify-content: space-between; align-items: flex-end; padding: 0 30px;">
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 12px; color: #475569; font-weight: 600; font-family: sans-serif;">Secretary</div>
                    </div>
                    <div style="text-align: center; width: 140px;">
                        <div style="border: 2px solid #047857; border-radius: 50%; width: 75px; height: 75px; padding-top: 22px; box-sizing: border-box; margin: 0 auto; color: #047857; font-size: 9px; font-weight: bold; text-transform: uppercase; transform: rotate(-8deg); font-family: sans-serif; text-align: center; line-height: 12px;">
                            Registry<br>Seal
                        </div>
                    </div>
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 12px; color: #475569; font-weight: 600; font-family: sans-serif;">President</div>
                    </div>
                </div>
            </div>
        `;

        html2pdf().set(opt).from(certTemplate).save();
    }

    // Global toggle fields initializer
    window.addEventListener('DOMContentLoaded', () => {
        toggleDeceasedFields();
        toggleReporterFields();
    });
</script>

<?php require_once 'footer.php'; ?>
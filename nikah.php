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
    $where_clauses[] = "(groom_name LIKE ? OR bride_name LIKE ? OR venue LIKE ? OR book_reference LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM nikah_registry $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1)
    $total_pages = 1;
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch paginated Nikah list
$fetch_stmt = $db->prepare("SELECT * FROM nikah_registry $where_sql ORDER BY date_added DESC LIMIT $limit OFFSET $offset");
$fetch_stmt->execute($params);
$nikah_list = $fetch_stmt->fetchAll();

// Fetch active members who are alive to filter for potential grooms/brides
$members = $db->query("SELECT id, card_no, first_name, last_name, dob, gender, father_husband_name FROM members WHERE status = 'Alive'")->fetchAll();

// Fetch all dependents of alive members
$dependents = $db->query("
    SELECT d.id, d.member_id, d.name, d.dob, d.gender, d.relationship,
           m.first_name AS primary_first, m.last_name AS primary_last, m.father_husband_name AS primary_father
    FROM member_dependents d
    JOIN members m ON d.member_id = m.id
    WHERE m.status = 'Alive'
")->fetchAll();

$eligible_grooms = [];
$eligible_brides = [];

// Process primary members
foreach ($members as $m) {
    $age = calculateAge($m['dob']);
    if ($age !== 'N/A') {
        if ($m['gender'] === 'Male' && $age >= 21) {
            $eligible_grooms[] = [
                'name' => $m['first_name'] . ' ' . $m['last_name'],
                'father' => $m['father_husband_name'],
                'age' => $age,
                'source' => 'Member (Card: ' . $m['card_no'] . ')'
            ];
        } elseif ($m['gender'] === 'Female' && $age >= 18) {
            $eligible_brides[] = [
                'name' => $m['first_name'] . ' ' . $m['last_name'],
                'father' => $m['father_husband_name'],
                'age' => $age,
                'source' => 'Member (Card: ' . $m['card_no'] . ')'
            ];
        }
    }
}

// Process dependents
foreach ($dependents as $d) {
    $age = calculateAge($d['dob']);
    if ($age !== 'N/A') {
        $father = $d['primary_first'] . ' ' . $d['primary_last'];
        if ($d['relationship'] === 'Sibling') {
            $father = $d['primary_father'];
        }

        if ($d['gender'] === 'Male' && $age >= 21) {
            $eligible_grooms[] = [
                'name' => $d['name'],
                'father' => $father,
                'age' => $age,
                'source' => $d['relationship'] . ' of ' . $d['primary_first'] . ' ' . $d['primary_last']
            ];
        } elseif ($d['gender'] === 'Female' && $age >= 18) {
            $eligible_brides[] = [
                'name' => $d['name'],
                'father' => $father,
                'age' => $age,
                'source' => $d['relationship'] . ' of ' . $d['primary_first'] . ' ' . $d['primary_last']
            ];
        }
    }
}

require_once 'header.php';
?>

<!-- HTML2PDF CDN Library -->
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Marriage Certificate Registry (Nikah)</h3>
            <p class="text-xs text-slate-500">Official certified wedding registry archives</p>
        </div>
        <button onclick="openNikahModal()" class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-ring"></i> Register New Nikah
        </button>
    </div>

    <!-- Integrated Search Workdesk with Clear Filters -->
    <form method="GET" action="" class="flex gap-2 mb-6">
        <div class="relative flex-grow">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Groom, Bride, Venue or Book Reference..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-teal-500 focus:bg-white focus:outline-none transition-all">
        </div>
        
        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-5 py-3 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm">
            <i class="fa-solid fa-magnifying-glass"></i> <span>Search</span>
        </button>

        <?php if (!empty($search)): ?>
                <a href="nikah.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-4 py-3 rounded-xl transition-all flex items-center justify-center">
                    Clear
                </a>
        <?php endif; ?>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider font-semibold bg-slate-50/50">
                    <th class="py-4 px-4 rounded-l-xl">Groom Name</th>
                    <th class="py-4 px-4">Bride Name</th>
                    <th class="py-4 px-4">Venue</th>
                    <th class="py-4 px-4">Officiated By</th>
                    <th class="py-4 px-4">Nikah Date & Time</th>
                    <th class="py-4 px-4 text-right rounded-r-xl">Actions</th>
                </tr>
            </thead>
            <tbody id="nikah-table-rows" class="divide-y divide-slate-100 text-xs">
                <?php if (empty($nikah_list)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 text-xs">No entries archived inside Nikah certified logs.</td>
                        </tr>
                <?php else: ?>
                        <?php foreach ($nikah_list as $nikah): ?>
                                <tr onclick='openNikahCard(<?php echo json_encode($nikah); ?>)' class="hover:bg-slate-50/75 transition-colors cursor-pointer">
                                    <td class="py-4 px-4 font-bold text-slate-800">
                                        🤵 <?php echo htmlspecialchars($nikah['groom_name']); ?>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-800">
                                        👰 <?php echo htmlspecialchars($nikah['bride_name']); ?>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-600">
                                        <?php echo htmlspecialchars($nikah['venue']); ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (!empty($nikah['conducted_by_jamath']) && $nikah['conducted_by_jamath'] == 1): ?>
                                                <span class="bg-emerald-50 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-150 uppercase tracking-wider">
                                                    NVK Jamaath
                                                </span>
                                        <?php else: ?>
                                                <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-slate-200 uppercase tracking-wider">
                                                    Private
                                                </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 font-semibold text-teal-800">
                                        <span class="bg-teal-50 px-2.5 py-1 rounded-md text-[10px]">
                                            <i class="fa-solid fa-clock mr-1"></i> <?php echo date('d M Y - h:i A', strtotime($nikah['nikah_datetime'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <div onclick="event.stopPropagation()" class="flex items-center justify-end gap-1.5">
                                            <button onclick='openNikahCard(<?php echo json_encode($nikah); ?>)' class="bg-slate-50 hover:bg-slate-100 text-slate-600 p-1.5 rounded-lg border border-slate-200 text-xs transition-colors" title="View Details Popup">
                                                <i class="fa-solid fa-address-card text-teal-700"></i>
                                            </button>

                                            <button onclick='populateEditNikah(<?php echo json_encode($nikah); ?>)' class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200 text-xs transition-colors" title="Update Marriage Record">
                                                <i class="fa-solid fa-user-gear"></i>
                                            </button>

                                            <form method="POST" action="actions.php" onsubmit="return confirm('Are you sure you want to delete this certified Nikah record permanently?');" class="inline">
                                                <input type="hidden" name="action" value="delete_nikah">
                                                <input type="hidden" name="id" value="<?php echo $nikah['id']; ?>">
                                                <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors" title="Delete certified contract">
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
                <p class="text-xs text-slate-500">Showing page <span class="font-bold text-slate-800"><?php echo $page; ?></span> of <span class="font-bold text-slate-800"><?php echo $total_pages; ?></span> pages</p>
                <div class="flex gap-1 text-xs">
                    <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold text-slate-700 hover:bg-slate-100 transition-colors">&laquo; Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1.5 rounded-lg font-semibold border transition-all <?php echo $i == $page ? 'bg-teal-700 border-teal-700 text-white' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold text-slate-700 hover:bg-slate-100 transition-colors">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
    <?php endif; ?>
</div>

<!-- Modal: Interactive detailed Pop-up card for Nikah -->
<div id="nikah-card-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">
        
        <div class="bg-gradient-to-r from-teal-800 to-emerald-950 p-6 text-white relative">
            <button onclick="closeNikahCard()" class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors text-lg">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>
            <div class="text-center space-y-1">
                <span class="text-3xl">🕊️</span>
                <h4 class="text-lg font-bold serif-title">Certified Islamic Marriage Profile</h4>
                <p id="card-date-header" class="text-xs text-teal-200 font-mono">---</p>
            </div>
        </div>

        <div class="p-6 space-y-6 overflow-y-auto text-xs text-slate-700">
            <!-- Double Columns: Groom vs Bride details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Groom Card info -->
                <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-2.5">
                    <p class="text-[10px] font-bold text-teal-800 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                        <i class="fa-solid fa-user-tie"></i> Groom (Bridegroom) Profile
                    </p>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Full Name</p>
                        <p id="pop-groom-name" class="font-bold text-slate-800 text-sm">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Father's Name</p>
                        <p id="pop-groom-father" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Age</p>
                            <p id="pop-groom-age" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Sequence Status</p>
                            <p id="pop-groom-status" class="font-semibold text-slate-700">---</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Jamaath Affiliation</p>
                        <p id="pop-groom-jamath" class="font-semibold text-slate-700">---</p>
                    </div>
                </div>

                <!-- Bride Card info -->
                <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-2.5">
                    <p class="text-[10px] font-bold text-pink-800 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                        <i class="fa-solid fa-person-dress"></i> Bride Profile
                    </p>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Full Name</p>
                        <p id="pop-bride-name" class="font-bold text-slate-800 text-sm">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Father's Name</p>
                        <p id="pop-bride-father" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Age</p>
                            <p id="pop-bride-age" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Sequence Status</p>
                            <p id="pop-bride-status" class="font-semibold text-slate-700">---</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Jamaath Affiliation</p>
                        <p id="pop-bride-jamath" class="font-semibold text-slate-700">---</p>
                    </div>
                </div>
            </div>

            <!-- Core Ceremony metadata fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-100/55 p-4 rounded-xl border border-slate-200">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Venue</p>
                    <p id="pop-venue" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Officiator / Jamaath Seal</p>
                    <p id="pop-officiator" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
            </div>

            <div class="bg-teal-50/40 p-4 rounded-xl border border-teal-150">
                <p class="text-[10px] font-bold text-teal-900 uppercase tracking-wider mb-1.5"><i class="fa-solid fa-book mr-1"></i> Registry Archive References</p>
                <div class="bg-white p-3 rounded border border-teal-100 font-semibold text-slate-700 font-mono">
                    <span id="pop-book">No book reference linked.</span>
                </div>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <button id="pop-edit-btn" class="bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-user-gear"></i> Update Record
                </button>
                
                <!-- Dynamic High-Fidelity Certificate Generation Target -->
                <button id="pop-cert-btn" class="font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-file-pdf"></i> Issue Certificate
                </button>
            </div>
            <button onclick="closeNikahCard()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-5 py-2 rounded-xl">
                Close Card
            </button>
        </div>
    </div>
</div>

<!-- Modal: Register & Edit Nikah Ceremony Registry -->
<div id="nikah-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-2">
            <h4 id="nikah-form-title" class="text-lg font-bold text-slate-800">Register Certified Nikah Contract</h4>
            <button onclick="closeNikahModal()" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <p class="text-xs text-slate-500 mb-4">Validate demographics, age boundaries, and marital status of both partners.</p>

        <form id="nikah-form" method="POST" action="actions.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" id="nikah-form-action" value="add_nikah">
            <input type="hidden" name="id" id="nikah-form-id" value="">

            <!-- Partners Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Groom Section -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <h5 class="font-bold text-teal-900 text-xs flex items-center gap-1.5"><i class="fa-solid fa-user-tie"></i> Groom Details</h5>
                    
                    <div id="groom-origin-box">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Groom Origin *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="groom_origin_jamath" name="groom_origin" value="jamath" checked onchange="toggleGroomFields()" class="text-teal-600 focus:ring-teal-500"> Within Jamaath
                            </label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="groom_origin_external" name="groom_origin" value="external" onchange="toggleGroomFields()" class="text-teal-600 focus:ring-teal-500"> Outside Jamaath
                            </label>
                        </div>
                    </div>

                    <!-- Option A: Groom is inside Jamaath -->
                    <div id="groom_jamath_container" class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Groom (Eligible Males 21+) *</label>
                        <select id="groom_select" name="groom_member_id" onchange="autoPopulateGroom()" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            <option value="">-- Choose Groom --</option>
                            <?php foreach ($eligible_grooms as $idx => $g): ?>
                                    <option value="<?php echo $idx; ?>" data-name="<?php echo htmlspecialchars($g['name']); ?>" data-father="<?php echo htmlspecialchars($g['father']); ?>" data-age="<?php echo $g['age']; ?>" data-source="<?php echo htmlspecialchars($g['source']); ?>">
                                        <?php echo htmlspecialchars($g['name']); ?> (Age: <?php echo $g['age']; ?> | <?php echo htmlspecialchars($g['source']); ?>)
                                    </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Option B: Groom is outside Jamaath OR Auto-populated static parameters -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Groom Name *</label>
                            <input type="text" id="groom_name_field" name="groom_name" required placeholder="Groom Name" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Father's Name *</label>
                            <input type="text" id="groom_father_field" name="groom_father" required placeholder="Groom's Father" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Groom Age *</label>
                                <input type="number" id="groom_age_field" name="groom_age" required min="21" placeholder="Age" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                <p class="text-[9px] text-slate-400 mt-0.5">Min legal age is 21</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Marriage Status *</label>
                                <select id="groom_marriage_field" name="groom_marriage_status" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <option value="First Marriage">First Marriage</option>
                                    <option value="Second Marriage">Second Marriage</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Groom Jamaath</label>
                            <input type="text" id="groom_jamath_field" name="groom_jamath" placeholder="e.g. NVK Jamaath (Vadasery)" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Bride Section -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <h5 class="font-bold text-pink-900 text-xs flex items-center gap-1.5"><i class="fa-solid fa-person-dress"></i> Bride Details</h5>
                    
                    <div id="bride-origin-box">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Bride Origin *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="bride_origin_jamath" name="bride_origin" value="jamath" checked onchange="toggleBrideFields()" class="text-teal-600 focus:ring-teal-500"> Within Jamaath
                            </label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="bride_origin_external" name="bride_origin" value="external" onchange="toggleBrideFields()" class="text-teal-600 focus:ring-teal-500"> Outside Jamaath
                            </label>
                        </div>
                    </div>

                    <!-- Option A: Bride is inside Jamaath -->
                    <div id="bride_jamath_container" class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Bride (Eligible Females 18+) *</label>
                        <select id="bride_select" name="bride_member_id" onchange="autoPopulateBride()" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            <option value="">-- Choose Bride --</option>
                            <?php foreach ($eligible_brides as $idx => $b): ?>
                                    <option value="<?php echo $idx; ?>" data-name="<?php echo htmlspecialchars($b['name']); ?>" data-father="<?php echo htmlspecialchars($b['father']); ?>" data-age="<?php echo $b['age']; ?>" data-source="<?php echo htmlspecialchars($b['source']); ?>">
                                        <?php echo htmlspecialchars($b['name']); ?> (Age: <?php echo $b['age']; ?> | <?php echo htmlspecialchars($b['source']); ?>)
                                    </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Option B: Bride is outside Jamaath OR Auto-populated static parameters -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bride Name *</label>
                            <input type="text" id="bride_name_field" name="bride_name" required placeholder="Bride Name" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Father's Name *</label>
                            <input type="text" id="bride_father_field" name="bride_father" required placeholder="Bride's Father" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bride Age *</label>
                                <input type="number" id="bride_age_field" name="bride_age" required min="18" placeholder="Age" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                <p class="text-[9px] text-slate-400 mt-0.5">Min legal age is 18</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Marriage Status *</label>
                                <select id="bride_marriage_field" name="bride_marriage_status" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <option value="First Marriage">First Marriage</option>
                                    <option value="Second Marriage">Second Marriage</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bride Jamaath</label>
                            <input type="text" id="bride_jamath_field" name="bride_jamath" placeholder="e.g. NVK Jamaath (Vadasery)" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Core Nikah Metadata -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Date & Time of Nikah *</label>
                    <input type="datetime-local" name="nikah_datetime" required id="nikah-datetime-field" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nikah Venue *</label>
                    <input type="text" id="nikah-venue-field" name="venue" required placeholder="e.g. Kottar Central Mosque, Main Hall" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Book & Registration References *</label>
                    <input type="text" id="nikah-book-field" name="book_reference" required placeholder="e.g. Book #14, Page 104" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="flex items-center h-full pt-4">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-700 font-bold select-none">
                        <input type="checkbox" id="nikah-conducted-check" name="conducted_by_jamath" value="1" checked class="h-4 w-4 text-teal-600 focus:ring-teal-500 rounded border-slate-300"> Fully Conducted by NVK Jamaath
                    </label>
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeNikahModal()" class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="nikah-form-submit" class="w-1/2 bg-teal-700 hover:bg-teal-800 text-white py-3 rounded-xl font-bold shadow-md transition-colors">
                    Record Nikah Contract
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open detailed pop-up modal
    function openNikahCard(nikah) {
        document.getElementById('card-date-header').textContent = "Date: " + formatDateJS(nikah.nikah_datetime);
        
        // Populate Groom details
        document.getElementById('pop-groom-name').textContent = nikah.groom_name;
        document.getElementById('pop-groom-father').textContent = nikah.groom_father ? "S/O " + nikah.groom_father : "---";
        document.getElementById('pop-groom-age').textContent = nikah.groom_age ? nikah.groom_age + " Years" : "---";
        document.getElementById('pop-groom-status').textContent = nikah.groom_marriage_status || "First Marriage";
        document.getElementById('pop-groom-jamath').textContent = nikah.groom_jamath || "NVK Jamaath";

        // Populate Bride details
        document.getElementById('pop-bride-name').textContent = nikah.bride_name;
        document.getElementById('pop-bride-father').textContent = nikah.bride_father ? "D/O " + nikah.bride_father : "---";
        document.getElementById('pop-bride-age').textContent = nikah.bride_age ? nikah.bride_age + " Years" : "---";
        document.getElementById('pop-bride-status').textContent = nikah.bride_marriage_status || "First Marriage";
        document.getElementById('pop-bride-jamath').textContent = nikah.bride_jamath || "NVK Jamaath";

        // Metadata details
        document.getElementById('pop-venue').textContent = nikah.venue;
        document.getElementById('pop-officiator').textContent = (nikah.conducted_by_jamath == 1) ? "NVK Jamaath Chief Imam" : "Private Event Officiator";
        document.getElementById('pop-book').textContent = nikah.book_reference || "Legacy Archive (No reference mapped)";

        // Configure Issue Certificate Button
        const certBtn = document.getElementById('pop-cert-btn');
        if (nikah.conducted_by_jamath == 1) {
            certBtn.disabled = false;
            certBtn.title = "Download high-fidelity marriage contract certificate PDF";
            certBtn.className = "bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors cursor-pointer";
            certBtn.onclick = function() {
                issueNikahCertificate(nikah);
            };
        } else {
            certBtn.disabled = true;
            certBtn.title = "Official Certificate issuance is restricted to weddings fully officiated by our Jamaath.";
            certBtn.className = "bg-slate-100 text-slate-400 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 cursor-not-allowed";
            certBtn.onclick = null;
        }

        document.getElementById('pop-edit-btn').onclick = function() {
            closeNikahCard();
            populateEditNikah(nikah);
        };

        document.getElementById('nikah-card-modal').classList.remove('hidden');
    }

    function closeNikahCard() {
        document.getElementById('nikah-card-modal').classList.add('hidden');
    }

    // Toggle forms logic depending on origin choices
    function toggleGroomFields() {
        const groomOrigin = document.querySelector('input[name="groom_origin"]:checked').value;
        const selectContainer = document.getElementById('groom_jamath_container');
        const groomName = document.getElementById('groom_name_field');
        const groomFather = document.getElementById('groom_father_field');
        const groomAge = document.getElementById('groom_age_field');
        const groomJamath = document.getElementById('groom_jamath_field');

        if (groomOrigin === 'jamath') {
            selectContainer.classList.remove('hidden');
            groomName.readOnly = true;
            groomFather.readOnly = true;
            groomAge.readOnly = true;
            groomJamath.readOnly = true;
            
            groomName.classList.add('bg-slate-100', 'cursor-not-allowed');
            groomFather.classList.add('bg-slate-100', 'cursor-not-allowed');
            groomAge.classList.add('bg-slate-100', 'cursor-not-allowed');
            groomJamath.classList.add('bg-slate-100', 'cursor-not-allowed');
            
            autoPopulateGroom();
        } else {
            selectContainer.classList.add('hidden');
            groomName.readOnly = false;
            groomFather.readOnly = false;
            groomAge.readOnly = false;
            groomJamath.readOnly = false;

            groomName.classList.remove('bg-slate-100', 'cursor-not-allowed');
            groomFather.classList.remove('bg-slate-100', 'cursor-not-allowed');
            groomAge.classList.remove('bg-slate-100', 'cursor-not-allowed');
            groomJamath.classList.remove('bg-slate-100', 'cursor-not-allowed');

            document.getElementById('groom_select').value = "";
            groomName.value = "";
            groomFather.value = "";
            groomAge.value = "";
            groomJamath.value = "";
        }
    }

    function toggleBrideFields() {
        const brideOrigin = document.querySelector('input[name="bride_origin"]:checked').value;
        const selectContainer = document.getElementById('bride_jamath_container');
        const brideName = document.getElementById('bride_name_field');
        const brideFather = document.getElementById('bride_father_field');
        const brideAge = document.getElementById('bride_age_field');
        const brideJamath = document.getElementById('bride_jamath_field');

        if (brideOrigin === 'jamath') {
            selectContainer.classList.remove('hidden');
            brideName.readOnly = true;
            brideFather.readOnly = true;
            brideAge.readOnly = true;
            brideJamath.readOnly = true;

            brideName.classList.add('bg-slate-100', 'cursor-not-allowed');
            brideFather.classList.add('bg-slate-100', 'cursor-not-allowed');
            brideAge.classList.add('bg-slate-100', 'cursor-not-allowed');
            brideJamath.classList.add('bg-slate-100', 'cursor-not-allowed');

            autoPopulateBride();
        } else {
            selectContainer.classList.add('hidden');
            brideName.readOnly = false;
            brideFather.readOnly = false;
            brideAge.readOnly = false;
            brideJamath.readOnly = false;

            brideName.classList.remove('bg-slate-100', 'cursor-not-allowed');
            brideFather.classList.remove('bg-slate-100', 'cursor-not-allowed');
            brideAge.classList.remove('bg-slate-100', 'cursor-not-allowed');
            brideJamath.classList.remove('bg-slate-100', 'cursor-not-allowed');

            document.getElementById('bride_select').value = "";
            brideName.value = "";
            brideFather.value = "";
            brideAge.value = "";
            brideJamath.value = "";
        }
    }

    function autoPopulateGroom() {
        const select = document.getElementById('groom_select');
        const selectedOption = select.options[select.selectedIndex];
        
        const groomName = document.getElementById('groom_name_field');
        const groomFather = document.getElementById('groom_father_field');
        const groomAge = document.getElementById('groom_age_field');
        const groomJamath = document.getElementById('groom_jamath_field');

        if (selectedOption && selectedOption.value !== "") {
            groomName.value = selectedOption.getAttribute('data-name');
            groomFather.value = selectedOption.getAttribute('data-father');
            groomAge.value = selectedOption.getAttribute('data-age');
            groomJamath.value = "NVK Jamaath (Vadasery)";
        } else {
            groomName.value = "";
            groomFather.value = "";
            groomAge.value = "";
            groomJamath.value = "";
        }
    }

    function autoPopulateBride() {
        const select = document.getElementById('bride_select');
        const selectedOption = select.options[select.selectedIndex];

        const brideName = document.getElementById('bride_name_field');
        const brideFather = document.getElementById('bride_father_field');
        const brideAge = document.getElementById('bride_age_field');
        const brideJamath = document.getElementById('bride_jamath_field');

        if (selectedOption && selectedOption.value !== "") {
            brideName.value = selectedOption.getAttribute('data-name');
            brideFather.value = selectedOption.getAttribute('data-father');
            brideAge.value = selectedOption.getAttribute('data-age');
            brideJamath.value = "NVK Jamaath (Vadasery)";
        } else {
            brideName.value = "";
            brideFather.value = "";
            brideAge.value = "";
            brideJamath.value = "";
        }
    }

    // Modal windows toggle managers
    function openNikahModal() {
        resetNikahForm();
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('nikah-datetime-field').value = `${year}-${month}-${day}T${hours}:${minutes}`;

        document.getElementById('nikah-modal').classList.remove('hidden');
    }

    function populateEditNikah(nikah) {
        openNikahModal();
        document.getElementById('nikah-form-title').textContent = "Update Certified Marriage Registry";
        document.getElementById('nikah-form-action').value = "edit_nikah";
        document.getElementById('nikah-form-id').value = nikah.id;
        document.getElementById('nikah-form-submit').textContent = "Save Changes";

        // Deactivate origin choices on edit to avoid mismatches
        document.getElementById('groom-origin-box').classList.add('hidden');
        document.getElementById('bride-origin-box').classList.add('hidden');
        document.getElementById('groom_jamath_container').classList.add('hidden');
        document.getElementById('bride_jamath_container').classList.add('hidden');

        // Force raw text input edit privileges on updates
        const inputsToEnable = [
            'groom_name_field', 'groom_father_field', 'groom_age_field', 'groom_jamath_field',
            'bride_name_field', 'bride_father_field', 'bride_age_field', 'bride_jamath_field'
        ];
        inputsToEnable.forEach(id => {
            const el = document.getElementById(id);
            el.readOnly = false;
            el.classList.remove('bg-slate-100', 'cursor-not-allowed');
        });

        // Set inputs
        document.getElementById('groom_name_field').value = nikah.groom_name;
        document.getElementById('groom_father_field').value = nikah.groom_father || '';
        document.getElementById('groom_age_field').value = nikah.groom_age || '';
        document.getElementById('groom_marriage_field').value = nikah.groom_marriage_status || 'First Marriage';
        document.getElementById('groom_jamath_field').value = nikah.groom_jamath || 'NVK Jamaath (Vadasery)';

        document.getElementById('bride_name_field').value = nikah.bride_name;
        document.getElementById('bride_father_field').value = nikah.bride_father || '';
        document.getElementById('bride_age_field').value = nikah.bride_age || '';
        document.getElementById('bride_marriage_field').value = nikah.bride_marriage_status || 'First Marriage';
        document.getElementById('bride_jamath_field').value = nikah.bride_jamath || 'NVK Jamaath (Vadasery)';

        document.getElementById('nikah-datetime-field').value = nikah.nikah_datetime.replace(" ", "T").substring(0, 16);
        document.getElementById('nikah-venue-field').value = nikah.venue;
        document.getElementById('nikah-book-field').value = nikah.book_reference || '';
        document.getElementById('nikah-conducted-check').checked = (nikah.conducted_by_jamath == 1);
    }

    function resetNikahForm() {
        document.getElementById('nikah-form').reset();
        document.getElementById('nikah-form-title').textContent = "Register Certified Nikah Contract";
        document.getElementById('nikah-form-action').value = "add_nikah";
        document.getElementById('nikah-form-id').value = "";
        document.getElementById('nikah-form-submit').textContent = "Archive Nikah Contract";

        document.getElementById('groom-origin-box').classList.remove('hidden');
        document.getElementById('bride-origin-box').classList.remove('hidden');
        
        document.getElementById('groom_origin_jamath').checked = true;
        document.getElementById('bride_origin_jamath').checked = true;

        toggleGroomFields();
        toggleBrideFields();
    }

    function closeNikahModal() {
        document.getElementById('nikah-modal').classList.add('hidden');
    }

    function formatDateJS(dateString) {
        if (!dateString) return '---';
        const dateObj = new Date(dateString);
        const options = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        return dateObj.toLocaleDateString('en-US', options);
    }

    // High-Fidelity Landscape PDF Wedding Certificate Engine
    function issueNikahCertificate(nikah) {
        const groomText = nikah.groom_name + (nikah.groom_father ? " (S/O: " + nikah.groom_father + ")" : "");
        const brideText = nikah.bride_name + (nikah.bride_father ? " (D/O: " + nikah.bride_father + ")" : "");
        const formattedDate = formatDateJS(nikah.nikah_datetime);
        const venueText = nikah.venue;
        const detailsText = nikah.book_reference || "Legacy Registry Archive";

        const opt = {
            margin: 0.3,
            filename: `Nikah_Certificate_${nikah.groom_name.replace(/\s+/g, '_')}.pdf`,
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
            <div style="border: 15px double #0d9488; padding: 25px; height: 100%; box-sizing: border-box; position: relative; background-image: radial-gradient(circle, #f0fdfa 1px, transparent 1px); background-size: 20px 20px; background-color: #fafcfc;">
                
                <!-- Corner Crest Badges -->
                <div style="position: absolute; top: 12px; left: 12px; color: #0f766e; font-size: 20px;">🕌</div>
                <div style="position: absolute; top: 12px; right: 12px; color: #0f766e; font-size: 20px;">🕌</div>
                <div style="position: absolute; bottom: 12px; left: 12px; color: #0f766e; font-size: 20px;">🕌</div>
                <div style="position: absolute; bottom: 12px; right: 12px; color: #0f766e; font-size: 20px;">🕌</div>
                
                <!-- Gold Emblem Ribbons -->
                <div style="text-align: center; margin-bottom: 15px;">
                    <h1 style="margin: 0; color: #115e59; font-size: 30px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;">NVK Jamaath</h1>
                    <p style="margin: 5px 0 0 0; font-size: 11px; text-transform: uppercase; letter-spacing: 4px; font-weight: bold; color: #0f766e;">Vadasery, Nagercoil, Kanyakumari District, Tamil Nadu</p>
                    <div style="width: 250px; height: 3px; background: linear-gradient(to right, transparent, #b45309, transparent); margin: 12px auto 4px auto;"></div>
                    <div style="width: 150px; height: 1px; background: #e2e8f0; margin: 0 auto;"></div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="font-family: Georgia, serif; font-style: italic; color: #b45309; font-size: 24px; margin: 5px 0;">Certificate of Islamic Marriage</h2>
                    <p style="font-size: 12px; color: #64748b; margin: 0; font-family: sans-serif;">This is to certify that the marriage contract (Nikah) has been completed and registered under our Jamaath.</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 35px; font-size: 15px;">
                    <tr>
                        <td style="width: 50%; padding: 12px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">The Groom (Bridegroom)</strong>
                                <span style="font-size: 17px; color: #1e293b; font-weight: bold;">${groomText}</span>
                            </div>
                        </td>
                        <td style="width: 50%; padding: 12px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">The Bride</strong>
                                <span style="font-size: 17px; color: #1e293b; font-weight: bold;">${brideText}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Date & Time</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: 600;">${formattedDate}</span>
                            </div>
                        </td>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Nikah Venue</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: 600;">${venueText}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Registry Books & References</strong>
                                <span style="font-size: 14px; color: #334155; font-style: italic;">${detailsText}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 45px; display: flex; justify-content: space-between; align-items: flex-end; padding: 0 30px;">
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 12px; color: #475569; font-weight: 600; font-family: sans-serif;">Secretary</div>
                    </div>
                    <div style="text-align: center; width: 140px;">
                        <div style="border: 2px solid #0d9488; border-radius: 50%; width: 75px; height: 75px; line-height: 75px; margin: 0 auto; color: #0d9488; font-size: 10px; font-weight: bold; text-transform: uppercase; transform: rotate(-8deg); font-family: sans-serif;">Registry Seal</div>
                    </div>
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 12px; color: #475569; font-weight: 600; font-family: sans-serif;">Chief Imam</div>
                    </div>
                </div>
            </div>
        `;

        html2pdf().set(opt).from(certTemplate).save();
    }

    // Global toggle fields initializer
    window.addEventListener('DOMContentLoaded', () => {
        toggleGroomFields();
        toggleBrideFields();
    });
</script>

<?php require_once 'footer.php'; ?>
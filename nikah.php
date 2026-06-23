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

// 1. Fetch active members (Added 'phone' field extraction)
$members = $db->query("SELECT id, card_no, first_name, last_name, dob, gender, father_husband_name, phone FROM members WHERE status = 'Alive'")->fetchAll();

// 2. Fetch dependents of active members
$dependents = $db->query("
    SELECT d.id, d.member_id, d.name, d.dob, d.gender, d.relationship,
           m.first_name AS primary_first, m.last_name AS primary_last, m.father_husband_name AS primary_father, m.phone AS primary_phone, m.card_no AS primary_card
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
        $item = [
            'first_name' => $m['first_name'],
            'last_name' => $m['last_name'],
            'name' => $m['first_name'] . ' ' . $m['last_name'],
            'phone' => $m['phone'],
            'father' => $m['father_husband_name'],
            'age' => $age,
            'dob' => $m['dob'],
            'card_no' => $m['card_no'],
            'dependent_of' => '', // Empty for primary members
            'source' => 'Member (Card: ' . $m['card_no'] . ')'
        ];

        if ($m['gender'] === 'Male' && $age >= 21) {
            $eligible_grooms[] = $item;
        } elseif ($m['gender'] === 'Female' && $age >= 18) {
            $eligible_brides[] = $item;
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

        // Split dependent name string safely into first/last buckets
        $name_parts = explode(' ', trim($d['name']), 2);
        $first_name = $name_parts[0];
        $last_name = $name_parts[1] ?? '';

        $item = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'name' => $d['name'],
            'phone' => $d['primary_phone'],
            'father' => $father,
            'age' => $age,
            'dob' => $d['dob'],
            'card_no' => '', // Zeroed tracking out for dependents
            'dependent_of' => $d['primary_first'] . ' ' . $d['primary_last'], // Context string injected here
            'source' => $d['relationship'] . ' of ' . $d['primary_first'] . ' ' . $d['primary_last']
        ];

        if ($d['gender'] === 'Male' && $age >= 21) {
            $eligible_grooms[] = $item;
        } elseif ($d['gender'] === 'Female' && $age >= 18) {
            $eligible_brides[] = $item;
        }
    }
}
require_once 'header.php';
?>

<!-- HTML2PDF CDN Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Marriage Certificate Registry (Nikah)</h3>
            <p class="text-xs text-slate-500">Official certified wedding registry archives</p>
        </div>
        <button onclick="resetNikahForm(); openNikahModal();"
            class="bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-colors shadow flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Register New Nikah
        </button>
    </div>

    <!-- Integrated Search Workdesk with Clear Filters -->
    <form method="GET" action="" class="flex gap-2 mb-6">
        <div class="relative flex-grow">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search by Groom, Bride, Venue or Book Reference..."
                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-teal-500 focus:bg-white focus:outline-none transition-all">
        </div>

        <button type="submit"
            class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-5 py-3 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm">
            <i class="fa-solid fa-magnifying-glass"></i> <span>Search</span>
        </button>

        <?php if (!empty($search)): ?>
            <a href="nikah.php"
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
                        <td colspan="6" class="py-12 text-center text-slate-400 text-xs">No entries archived inside Nikah
                            certified logs.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($nikah_list as $nikah): ?>
                        <tr onclick="openNikahCard(<?php echo htmlspecialchars(json_encode($nikah), ENT_QUOTES, 'UTF-8'); ?>)"
                            class="cursor-pointer hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-4 font-bold text-slate-800">
                                🤵 <?php echo htmlspecialchars($nikah['groom_first_name'] . ' ' . $nikah['groom_last_name']); ?>
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-800">
                                👰 <?php echo htmlspecialchars($nikah['bride_first_name'] . ' ' . $nikah['bride_last_name']); ?>
                            </td>
                            <td class="py-4 px-4 font-medium text-slate-600">
                                <?php echo htmlspecialchars($nikah['venue']); ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if (!empty($nikah['conducted_by_jamath']) && $nikah['conducted_by_jamath'] == 1): ?>
                                    <span
                                        class="bg-emerald-50 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-150 uppercase tracking-wider">
                                        NVK Jamaath
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-slate-200 uppercase tracking-wider">
                                        Private
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 font-semibold text-teal-800">
                                <span class="bg-teal-50 px-2.5 py-1 rounded-md text-[10px]">
                                    <i class="fa-solid fa-clock mr-1"></i>
                                    <?php echo date('d M Y - h:i A', strtotime($nikah['nikah_datetime'])); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div onclick="event.stopPropagation()" class="flex items-center justify-end gap-1.5">
                                    <button onclick='openNikahCard(<?php echo json_encode($nikah); ?>)'
                                        class="bg-slate-50 hover:bg-slate-100 text-slate-600 p-1.5 rounded-lg border border-slate-200 text-xs transition-colors"
                                        title="View Details Popup">
                                        <i class="fa-solid fa-address-card text-teal-700"></i>
                                    </button>

                                    <button onclick='populateEditNikah(<?php echo json_encode($nikah); ?>)'
                                        class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200 text-xs transition-colors"
                                        title="Update Marriage Record">
                                        <i class="fa-solid fa-user-gear"></i>
                                    </button>

                                    <form method="POST" action="actions.php"
                                        onsubmit="return confirm('Are you sure you want to delete this certified Nikah record permanently?');"
                                        class="inline">
                                        <input type="hidden" name="action" value="delete_nikah">
                                        <input type="hidden" name="id" value="<?php echo $nikah['id']; ?>">
                                        <button type="submit"
                                            class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors"
                                            title="Delete certified contract">
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
                        class="px-3 py-1.5 rounded-lg font-semibold border transition-all <?php echo $i == $page ? 'bg-teal-700 border-teal-700 text-white' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'; ?>"><?php echo $i; ?></a>
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

<div id="nikah-card-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div
        class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">

        <div class="bg-gradient-to-r from-teal-800 to-emerald-950 p-6 text-white relative">
            <button onclick="closeNikahCard()"
                class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors text-lg">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>
            <div class="text-center space-y-1">
                <span class="text-3xl">🕊️</span>
                <h4 class="text-lg font-bold serif-title">Certified Islamic Marriage Profile</h4>
                <p id="card-date-header" class="text-xs text-teal-200 font-mono">---</p>
            </div>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto text-xs text-slate-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-2.5 relative">
                    <p
                        class="text-[10px] font-bold text-teal-800 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                        <i class="fa-solid fa-user-tie"></i> Groom (Bridegroom) Profile
                    </p>

                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm space-y-2">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"><i
                                class="fa-solid fa-camera mr-1"></i>Uploaded Photograph</p>
                        <div class="flex items-center gap-4">
                            <a id="pop-groom-photo-link" href="#" target="_blank"
                                class="block h-16 w-16 rounded-full overflow-hidden border-2 border-teal-700 shadow shadow-teal-900/20 transition-transform hover:scale-105 duration-200 cursor-pointer flex-shrink-0">
                                <div id="pop-groom-photo-wrap"
                                    class="h-full w-full bg-slate-100 flex items-center justify-center"></div>
                            </a>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-bold text-slate-700">Groom Photo</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Click to view image asset.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1.5"><i
                                class="fa-solid fa-id-card mr-1"></i> File Attachment Verification</p>
                        <div id="pop-groom-doc-wrap" class="text-[11px] font-semibold"></div>
                    </div>

                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">First Name</p>
                        <p id="pop-groom-first-name" class="font-bold text-slate-800 text-sm">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Last Name / Surname</p>
                        <p id="pop-groom-last-name" class="font-bold text-slate-700 text-xs">---</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Primary Contact</p>
                            <p id="pop-groom-phone1" class="font-bold text-slate-700 text-xs font-mono">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Alt Contact</p>
                            <p id="pop-groom-phone2" class="font-semibold text-slate-500 text-xs font-mono">---</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Father Name</p>
                        <p id="pop-groom-father" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Mother Name</p>
                        <p id="pop-groom-mother" class="font-semibold text-slate-700">---</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Date of Birth</p>
                            <p id="pop-groom-dob" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Age</p>
                            <p id="pop-groom-age" class="font-semibold text-slate-700">---</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Sequence Status</p>
                            <p id="pop-groom-status" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Jamaath</p>
                            <p id="pop-groom-jamath" class="font-semibold text-slate-700 truncate">---</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-2.5 relative">
                    <p
                        class="text-[10px] font-bold text-pink-800 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                        <i class="fa-solid fa-person-dress"></i> Bride Profile
                    </p>

                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm space-y-2">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"><i
                                class="fa-solid fa-camera mr-1"></i>Uploaded Photograph</p>
                        <div class="flex items-center gap-4">
                            <a id="pop-bride-photo-link" href="#" target="_blank"
                                class="block h-16 w-16 rounded-full overflow-hidden border-2 border-teal-700 shadow shadow-teal-900/20 transition-transform hover:scale-105 duration-200 cursor-pointer flex-shrink-0">
                                <div id="pop-bride-photo-wrap"
                                    class="h-full w-full bg-slate-100 flex items-center justify-center"></div>
                            </a>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-bold text-slate-700">Bride Photo</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Click to view image asset.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1.5"><i
                                class="fa-solid fa-id-card mr-1"></i> File Attachment Verification</p>
                        <div id="pop-bride-doc-wrap" class="text-[11px] font-semibold"></div>
                    </div>

                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">First Name</p>
                        <p id="pop-bride-name" class="font-bold text-slate-800 text-sm">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Last Name / Surname</p>
                        <p id="pop-bride-last-name" class="font-bold text-slate-700 text-xs">---</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Primary Contact</p>
                            <p id="pop-bride-phone1" class="font-bold text-slate-700 text-xs font-mono">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Alt Contact</p>
                            <p id="pop-bride-phone2" class="font-semibold text-slate-500 text-xs font-mono">---</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Father Name</p>
                        <p id="pop-bride-father" class="font-semibold text-slate-700">---</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Mother Name</p>
                        <p id="pop-bride-mother" class="font-semibold text-slate-700">---</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Date of Birth</p>
                            <p id="pop-bride-dob" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Age</p>
                            <p id="pop-bride-age" class="font-semibold text-slate-700">---</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Sequence Status</p>
                            <p id="pop-bride-status" class="font-semibold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold uppercase">Jamaath</p>
                            <p id="pop-bride-jamath" class="font-semibold text-slate-700 truncate">---</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50/60 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <p
                        class="text-[9px] font-bold text-teal-900 uppercase tracking-wide border-b border-slate-200 pb-1 mb-1">
                        Groom's Legal Witness</p>
                    <div><span class="text-slate-400 font-medium">Name:</span> <strong id="pop-witness-groom-name"
                            class="text-slate-800">---</strong></div>
                    <div><span class="text-slate-400 font-medium">Relation:</span> <span id="pop-witness-groom-relation"
                            class="text-slate-700 font-medium">---</span></div>
                    <div><span class="text-slate-400 font-medium">Phone:</span> <span id="pop-witness-groom-phone"
                            class="font-mono text-slate-700">---</span></div>
                    <div>
                        <span class="text-slate-400 font-medium block">Address:</span>
                        <div id="pop-witness-groom-address"
                            class="text-slate-600 italic text-[11px] leading-relaxed bg-white/60 p-1.5 rounded border border-slate-100 mt-0.5">
                            ---</div>
                    </div>
                    <div id="pop-witness-groom-doc" class="pt-1 mt-1 border-t border-dashed border-slate-200"></div>
                </div>

                <div class="bg-slate-50/60 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <p
                        class="text-[9px] font-bold text-pink-900 uppercase tracking-wide border-b border-slate-200 pb-1 mb-1">
                        Bride's Legal Witness</p>
                    <div><span class="text-slate-400 font-medium">Name:</span> <strong id="pop-witness-bride-name"
                            class="text-slate-800">---</strong></div>
                    <div><span class="text-slate-400 font-medium">Relation:</span> <span id="pop-witness-bride-relation"
                            class="text-slate-700 font-medium">---</span></div>
                    <div><span class="text-slate-400 font-medium">Phone:</span> <span id="pop-witness-bride-phone"
                            class="font-mono text-slate-700">---</span></div>
                    <div>
                        <span class="text-slate-400 font-medium block">Address:</span>
                        <div id="pop-witness-bride-address"
                            class="text-slate-600 italic text-[11px] leading-relaxed bg-white/60 p-1.5 rounded border border-slate-100 mt-0.5">
                            ---</div>
                    </div>
                    <div id="pop-witness-bride-doc" class="pt-1 mt-1 border-t border-dashed border-slate-200"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-100/55 p-4 rounded-xl border border-slate-200">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Venue</p>
                    <p id="pop-venue" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Officiator / Jamaath Seal
                    </p>
                    <p id="pop-officiator" class="font-bold text-slate-800 mt-0.5">---</p>
                </div>
            </div>

            <div class="bg-teal-50/40 p-4 rounded-xl border border-teal-150">
                <p class="text-[10px] font-bold text-teal-900 uppercase tracking-wider mb-1.5"><i
                        class="fa-solid fa-book mr-1"></i> Registry Archive References</p>
                <div class="bg-white p-3 rounded border border-teal-100 font-semibold text-slate-700 font-mono">
                    <span id="pop-book">No book reference linked.</span>
                </div>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <button id="pop-edit-btn"
                    class="bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-user-gear"></i> Update Record
                </button>
                <button id="pop-cert-btn"
                    class="font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-file-pdf"></i> Issue Certificate
                </button>
            </div>
            <button onclick="closeNikahCard()"
                class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-5 py-2 rounded-xl">Close
                Card</button>
        </div>
    </div>
</div>

<div id="nikah-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div
        class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-2">
            <h4 id="nikah-form-title" class="text-lg font-bold text-slate-800">Register Certified Nikah Contract</h4>
            <button type="button" onclick="closeNikahModal()"
                class="text-slate-400 hover:text-slate-600 transition-colors"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <p class="text-xs text-slate-500 mb-4">Validate demographics, age boundaries, parental status, and verification
            metrics for both partners.</p>

        <form id="nikah-form" method="POST" action="actions.php" enctype="multipart/form-data"
            class="space-y-4 text-xs">
            <input type="hidden" name="action" id="nikah-form-action" value="add_nikah">
            <input type="hidden" name="id" id="nikah-form-id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-4">
                    <h5 class="font-bold text-teal-900 text-xs flex items-center gap-1.5 mb-1"><i
                            class="fa-solid fa-user-tie"></i> Groom Details</h5>

                    <div
                        class="bg-white p-3 rounded-lg border border-slate-150 flex items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Groom Photo
                                *</label>
                            <input type="file" name="groom_photo" accept="image/*"
                                onchange="previewFile(this, 'groom_photo_preview_box')"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1 text-[10px] focus:outline-none">
                        </div>
                        <div id="groom_photo_preview_box"
                            class="flex-shrink-0 flex justify-center bg-slate-100 border border-dashed border-slate-300 rounded-lg p-1 h-14 w-14 overflow-hidden items-center transition-all">
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded-lg border border-slate-150 space-y-2.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Select
                                Identification ID *</label>
                            <select id="groom_id_type" name="groom_id_type" onchange="handleIdTypeChange('groom')"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1.5 text-[10px] focus:outline-none">
                                <option value="">-- Select ID --</option>
                                <option value="Aadhaar">Aadhaar Card</option>
                                <option value="Passport">Passport</option>
                                <option value="Driving License">Driving License</option>
                                <option value="Voter ID">Voter ID</option>
                            </select>
                        </div>

                        <div id="groom_id_upload_wrapper"
                            class="hidden flex items-center justify-between gap-4 pt-1 border-t border-dashed border-slate-100">
                            <div class="flex-1">
                                <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">Upload Selected
                                    ID *</label>
                                <input type="file" id="groom_id_doc" name="groom_id_doc" accept=".pdf,image/*"
                                    onchange="previewFile(this, 'groom_id_doc_preview_box')"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1 text-[10px] focus:outline-none">
                            </div>
                            <div id="groom_id_doc_preview_box"
                                class="flex-shrink-0 flex justify-center bg-slate-100 border border-dashed border-slate-300 rounded-lg p-1 h-12 w-12 overflow-hidden items-center transition-all">
                            </div>
                        </div>
                    </div>

                    <div id="groom-origin-box">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Groom Origin
                            *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="groom_origin_jamath" name="groom_origin" value="jamath" checked
                                    onchange="toggleGroomFields()" class="text-teal-600 focus:ring-teal-500"> Within
                                Jamaath
                            </label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="groom_origin_external" name="groom_origin" value="external"
                                    onchange="toggleGroomFields()" class="text-teal-600 focus:ring-teal-500">
                                Outside
                                Jamaath
                            </label>
                        </div>
                    </div>

                    <div id="groom_jamath_container" class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Groom (Eligible Males
                            21+) *</label>
                        <select id="groom_select" name="groom_member_id" onchange="autoPopulateGroom()"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-teal-500 focus:outline-none transition-colors appearance-none">
                            <option value="">-- Choose Groom --</option>
                            <?php foreach ($eligible_grooms as $idx => $g): ?>
                                <option value="<?php echo $idx; ?>"
                                    data-first-name="<?php echo htmlspecialchars($g['first_name'] ?? ''); ?>"
                                    data-last-name="<?php echo htmlspecialchars($g['last_name'] ?? ''); ?>"
                                    data-phone1="<?php echo htmlspecialchars($g['phone'] ?? ''); ?>"
                                    data-father="<?php echo htmlspecialchars($g['father'] ?? ''); ?>"
                                    data-dob="<?php echo htmlspecialchars($g['dob'] ?? ''); ?>">
                                    <?php
                                    echo htmlspecialchars($g['name']);
                                    if (!empty($g['dependent_of'])) {
                                        echo " (Dependent of " . htmlspecialchars($g['dependent_of']) . ")";
                                    } else {
                                        echo " (Card: " . htmlspecialchars($g['card_no'] ?? '') . ")";
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">First Name
                                    *</label>
                                <input type="text" id="groom_first_name_field" name="groom_first_name" required
                                    placeholder="First Name"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Last Name
                                    *</label>
                                <input type="text" id="groom_last_name_field" name="groom_last_name" required
                                    placeholder="Last Name"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Father's
                                    Name &
                                    Status *</label>
                                <div class="flex gap-2">
                                    <input type="text" id="groom_father_field" name="groom_father" required
                                        placeholder="Father's Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <select id="groom_father_status_field" name="groom_father_status"
                                        class="bg-white border border-slate-200 rounded-lg px-2 text-xs focus:outline-none">
                                        <option value="1">Alive</option>
                                        <option value="0">Deceased</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mother's
                                    Name &
                                    Status *</label>
                                <div class="flex gap-2">
                                    <input type="text" id="groom_mother_field" name="groom_mother" required
                                        placeholder="Mother's Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <select id="groom_mother_status_field" name="groom_mother_status"
                                        class="bg-white border border-slate-200 rounded-lg px-2 text-xs focus:outline-none">
                                        <option value="1">Alive</option>
                                        <option value="0">Deceased</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Date of
                                    Birth
                                    *</label>
                                <input type="date" id="groom_dob_field" name="groom_dob" required
                                    onchange="calculateLiveAge(this, 'groom_live_age_preview', 'groom_age_field');"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-[11px] focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                <input type="hidden" id="groom_age_field" name="groom_age" value="">
                                <p class="text-[9px] text-slate-400 mt-0.5">Age: <span id="groom_live_age_preview"
                                        class="font-bold text-teal-700">--</span> (Min 21)</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Marriage
                                    Status
                                    *</label>
                                <select id="groom_marriage_field" name="groom_marriage_status"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <option value="First Marriage">First Marriage</option>
                                    <option value="Second Marriage">Second Marriage</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Aadhar Number
                                *</label>
                            <input type="text" id="groom_aadhar_field" name="groom_aadhar" required maxlength="12"
                                placeholder="12-Digit Aadhaar"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none font-mono">
                        </div>

                        <div id="groom_aadhar_upload_container"
                            class="hidden bg-amber-50 border border-amber-200 p-2 rounded-lg space-y-1">
                            <label class="block text-[10px] font-bold text-amber-900 uppercase">Mandhaar Copy Upload
                                (Aadhaar Required) *</label>
                            <input type="file" id="groom_aadhar_file" name="groom_aadhar_file" accept=".pdf,image/*"
                                onchange="previewFile(this, 'groom_aadhar_preview_box')"
                                class="w-full bg-white border border-amber-200 rounded p-1 text-[10px]">
                            <div id="groom_aadhar_preview_box"
                                class="mt-1 flex justify-center bg-white border border-dashed border-slate-300 rounded h-10 w-10 items-center overflow-hidden">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact No
                                    *</label>
                                <input type="tel" id="groom_phone1_field" name="groom_phone1" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-[11px] focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Alt Contact
                                    No</label>
                                <input type="tel" id="groom_phone2_field" name="groom_phone2"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-[11px] focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Groom
                                Jamaath</label>
                            <input type="text" id="groom_jamath_field" name="groom_jamath"
                                placeholder="e.g. NVK Jamaath (Vadasery)"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-4">
                    <h5 class="font-bold text-pink-900 text-xs flex items-center gap-1.5 mb-1"><i
                            class="fa-solid fa-person-dress"></i> Bride Details</h5>

                    <div
                        class="bg-white p-3 rounded-lg border border-slate-150 flex items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Bride Photo
                                *</label>
                            <input type="file" name="bride_photo" accept="image/*"
                                onchange="previewFile(this, 'bride_photo_preview_box')"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1 text-[10px] focus:outline-none">
                        </div>
                        <div id="bride_photo_preview_box"
                            class="flex-shrink-0 flex justify-center bg-slate-100 border border-dashed border-slate-300 rounded-lg p-1 h-14 w-14 overflow-hidden items-center transition-all">
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded-lg border border-slate-150 space-y-2.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Select
                                Identification ID *</label>
                            <select id="bride_id_type" name="bride_id_type" onchange="handleIdTypeChange('bride')"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1.5 text-[10px] focus:outline-none">
                                <option value="">-- Select ID --</option>
                                <option value="Aadhaar">Aadhaar Card</option>
                                <option value="Passport">Passport</option>
                                <option value="Driving License">Driving License</option>
                                <option value="Voter ID">Voter ID</option>
                            </select>
                        </div>

                        <div id="bride_id_upload_wrapper"
                            class="hidden flex items-center justify-between gap-4 pt-1 border-t border-dashed border-slate-100">
                            <div class="flex-1">
                                <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">Upload Selected
                                    ID *</label>
                                <input type="file" id="bride_id_doc" name="bride_id_doc" accept=".pdf,image/*"
                                    onchange="previewFile(this, 'bride_id_doc_preview_box')"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1 text-[10px] focus:outline-none">
                            </div>
                            <div id="bride_id_doc_preview_box"
                                class="flex-shrink-0 flex justify-center bg-slate-100 border border-dashed border-slate-300 rounded-lg p-1 h-12 w-12 overflow-hidden items-center transition-all">
                            </div>
                        </div>
                    </div>

                    <div id="bride-origin-box">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Bride Origin
                            *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="bride_origin_jamath" name="bride_origin" value="jamath" checked
                                    onchange="toggleBrideFields()" class="text-teal-600 focus:ring-teal-500"> Within
                                Jamaath
                            </label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" id="bride_origin_external" name="bride_origin" value="external"
                                    onchange="toggleBrideFields()" class="text-teal-600 focus:ring-teal-500">
                                Outside
                                Jamaath
                            </label>
                        </div>
                    </div>

                    <div id="bride_jamath_container" class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Select Bride (Eligible
                            Females 18+) *</label>
                        <select id="bride_select" name="bride_member_id" onchange="autoPopulateBride()"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-teal-500 focus:outline-none transition-colors appearance-none">
                            <option value="">-- Choose Bride --</option>
                            <?php foreach ($eligible_brides as $idx => $b): ?>
                                <option value="<?php echo $idx; ?>"
                                    data-first-name="<?php echo htmlspecialchars($b['first_name'] ?? ''); ?>"
                                    data-last-name="<?php echo htmlspecialchars($b['last_name'] ?? ''); ?>"
                                    data-phone1="<?php echo htmlspecialchars($b['phone'] ?? ''); ?>"
                                    data-father="<?php echo htmlspecialchars($b['father'] ?? ''); ?>"
                                    data-dob="<?php echo htmlspecialchars($b['dob'] ?? ''); ?>">
                                    <?php
                                    echo htmlspecialchars($b['name']);
                                    if (!empty($b['dependent_of'])) {
                                        echo " (Dependent of " . htmlspecialchars($b['dependent_of']) . ")";
                                    } else {
                                        echo " (Card: " . htmlspecialchars($b['card_no'] ?? '') . ")";
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">First Name
                                    *</label>
                                <input type="text" id="bride_first_name_field" name="bride_first_name" required
                                    placeholder="First Name"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Last Name
                                    *</label>
                                <input type="text" id="bride_last_name_field" name="bride_last_name" required
                                    placeholder="Last Name"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Father's
                                    Name &
                                    Status *</label>
                                <div class="flex gap-2">
                                    <input type="text" id="bride_father_field" name="bride_father" required
                                        placeholder="Father's Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <select id="bride_father_status_field" name="bride_father_status"
                                        class="bg-white border border-slate-200 rounded-lg px-2 text-xs focus:outline-none">
                                        <option value="1">Alive</option>
                                        <option value="0">Deceased</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mother's
                                    Name &
                                    Status *</label>
                                <div class="flex gap-2">
                                    <input type="text" id="bride_mother_field" name="bride_mother" required
                                        placeholder="Mother's Name"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <select id="bride_mother_status_field" name="bride_mother_status"
                                        class="bg-white border border-slate-200 rounded-lg px-2 text-xs focus:outline-none">
                                        <option value="1">Alive</option>
                                        <option value="0">Deceased</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Date of
                                    Birth
                                    *</label>
                                <input type="date" id="bride_dob_field" name="bride_dob" required
                                    onchange="calculateLiveAge(this, 'bride_live_age_preview', 'bride_age_field');"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-[11px] focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                <input type="hidden" id="bride_age_field" name="bride_age" value="">
                                <p class="text-[9px] text-slate-400 mt-0.5">Age: <span id="bride_live_age_preview"
                                        class="font-bold text-rose-700">--</span> (Min 18)</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Marriage
                                    Status
                                    *</label>
                                <select id="bride_marriage_field" name="bride_marriage_status"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                                    <option value="First Marriage">First Marriage</option>
                                    <option value="Second Marriage">Second Marriage</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Aadhar Number
                                *</label>
                            <input type="text" id="bride_aadhar_field" name="bride_aadhar" required maxlength="12"
                                placeholder="12-Digit Aadhaar"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none font-mono">
                        </div>

                        <div id="bride_aadhar_upload_container"
                            class="hidden bg-amber-50 border border-amber-200 p-2 rounded-lg space-y-1">
                            <label class="block text-[10px] font-bold text-amber-900 uppercase">Mandatory Aadhaar
                                Copy
                                Upload *</label>
                            <input type="file" id="bride_aadhar_file" name="bride_aadhar_file" accept=".pdf,image/*"
                                onchange="previewFile(this, 'bride_aadhar_preview_box')"
                                class="w-full bg-white border border-amber-200 rounded p-1 text-[10px]">
                            <div id="bride_aadhar_preview_box"
                                class="mt-1 flex justify-center bg-white border border-dashed border-slate-300 rounded h-10 w-10 items-center overflow-hidden">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact No
                                    *</label>
                                <input type="tel" id="bride_phone1_field" name="bride_phone1" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-[11px] focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Alt Contact
                                    No</label>
                                <input type="tel" id="bride_phone2_field" name="bride_phone2"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-[11px] focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bride
                                Jamaath</label>
                            <input type="text" id="bride_jamath_field" name="bride_jamath"
                                placeholder="e.g. NVK Jamaath (Vadasery)"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-teal-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-2">
                    <h6
                        class="font-bold text-teal-900 text-[11px] uppercase tracking-wider border-b border-slate-150 pb-1">
                        Groom's Side Witness
                    </h6>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Full Name
                                *</label>
                            <input type="text" name="witness_groom_name" required placeholder="Witness Name"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Relationship
                                *</label>
                            <input type="text" name="witness_groom_relationship" required
                                placeholder="e.g. Uncle, Brother"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Contact Number
                            *</label>
                        <input type="tel" id="witness_groom_phone" name="witness_groom_phone" required
                            placeholder="Mobile Number"
                            class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                    </div>

                    <div class="space-y-1.5 pt-1 border-t border-slate-100">
                        <span class="block text-[9px] font-bold text-teal-900 uppercase tracking-wide">Witness Address
                            Details</span>
                        <div>
                            <input type="text" name="witness_groom_addr_l1" required
                                placeholder="Door No, Building / House Name *"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                        <div>
                            <input type="text" name="witness_groom_addr_l2" placeholder="Street Name, Locality Info"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <div>
                                <input type="text" name="witness_groom_city" required placeholder="City / Town *"
                                    class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                            </div>
                            <div>
                                <input type="text" name="witness_groom_pincode" required placeholder="Pincode *"
                                    class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                            </div>
                            <div>
                                <input type="text" name="witness_groom_country" required placeholder="Country *"
                                    class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-1 items-center pt-1 border-t border-slate-100">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase">Witness ID *</label>
                            <input type="file" name="witness_groom_id_doc" accept=".pdf,image/*"
                                onchange="previewFile(this, 'witness_groom_preview_box')" class="w-full text-[9px]">
                        </div>
                        <div id="witness_groom_preview_box"
                            class="h-10 w-10 border border-dashed border-slate-300 rounded bg-white flex items-center justify-center overflow-hidden">
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-2">
                    <h6
                        class="font-bold text-pink-900 text-[11px] uppercase tracking-wider border-b border-slate-150 pb-1">
                        Bride's Side Witness
                    </h6>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Full Name
                                *</label>
                            <input type="text" name="witness_bride_name" required placeholder="Witness Name"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Relationship
                                *</label>
                            <input type="text" name="witness_bride_relationship" required
                                placeholder="e.g. Father, Friend"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold text-slate-500 uppercase mb-0.5">Contact Number
                            *</label>
                        <input type="tel" id="witness_bride_phone" name="witness_bride_phone" required
                            placeholder="Mobile Number"
                            class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                    </div>

                    <div class="space-y-1.5 pt-1 border-t border-slate-100">
                        <span class="block text-[9px] font-bold text-pink-900 uppercase tracking-wide">Witness Address
                            Details</span>
                        <div>
                            <input type="text" name="witness_bride_addr_l1" required
                                placeholder="Door No, Building / House Name *"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                        <div>
                            <input type="text" name="witness_bride_addr_l2" placeholder="Street Name, Locality Info"
                                class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <div>
                                <input type="text" name="witness_bride_city" required placeholder="City / Town *"
                                    class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                            </div>
                            <div>
                                <input type="text" name="witness_bride_pincode" required placeholder="Pincode *"
                                    class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                            </div>
                            <div>
                                <input type="text" name="witness_bride_country" required placeholder="Country *"
                                    class="w-full bg-white border border-slate-200 rounded-md p-1.5 text-xs focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-1 items-center pt-1 border-t border-slate-100">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-500 uppercase">Witness ID *</label>
                            <input type="file" name="witness_bride_id_doc" accept=".pdf,image/*"
                                onchange="previewFile(this, 'witness_bride_preview_box')" class="w-full text-[9px]">
                        </div>
                        <div id="witness_bride_preview_box"
                            class="h-10 w-10 border border-dashed border-slate-300 rounded bg-white flex items-center justify-center overflow-hidden">
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Date & Time of Nikah
                        *</label>
                    <input type="datetime-local" name="nikah_datetime" required id="nikah-datetime-field"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nikah Venue *</label>
                    <input type="text" id="nikah-venue-field" name="venue" required
                        placeholder="e.g. Kottar Central Mosque, Main Hall"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Book & Registration
                        References *</label>
                    <input type="text" id="nikah-book-field" name="book_reference" required
                        placeholder="e.g. Book #14, Page 104"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="flex items-center h-full pt-4">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-700 font-bold select-none">
                        <input type="checkbox" id="nikah-conducted-check" name="conducted_by_jamath" value="1" checked
                            class="h-4 w-4 text-teal-600 focus:ring-teal-500 rounded border-slate-300">
                        Fully Conducted
                        by NVK Jamaath
                    </label>
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeNikahModal()"
                    class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" id="nikah-form-submit"
                    class="w-1/2 bg-teal-700 hover:bg-teal-800 text-white py-3 rounded-xl font-bold shadow-md transition-colors">Record
                    Nikah Contract</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open detailed pop-up modal
    function openNikahCard(data) {
        if (!data) return;

        // 1. Set Date Header Format
        const datetime = data.nikah_datetime ? new Date(data.nikah_datetime.replace(/-/g, "/")) : null;
        document.getElementById('card-date-header').textContent = datetime
            ? datetime.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + " - " + datetime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
            : "---";

        // 2. Populate Groom Node Fields & Contacts
        document.getElementById('pop-groom-first-name').textContent = data.groom_first_name || "---";
        document.getElementById('pop-groom-last-name').textContent = data.groom_last_name || "---";
        document.getElementById('pop-groom-phone1').textContent = data.groom_phone1 || "---";
        document.getElementById('pop-groom-phone2').textContent = data.groom_phone2 || "None";

        const groomFatherStatus = parseInt(data.groom_father_status) === 0 ? " (Deceased)" : " (Alive)";
        const groomMotherStatus = parseInt(data.groom_mother_status) === 0 ? " (Deceased)" : " (Alive)";
        document.getElementById('pop-groom-father').textContent = data.groom_father ? data.groom_father + groomFatherStatus : "---";
        document.getElementById('pop-groom-mother').textContent = data.groom_mother ? data.groom_mother + groomMotherStatus : "---";

        document.getElementById('pop-groom-dob').textContent = data.groom_dob || "---";
        document.getElementById('pop-groom-age').textContent = data.groom_age ? data.groom_age + " Years" : "---";
        document.getElementById('pop-groom-status').textContent = data.groom_marriage_status || "---";
        document.getElementById('pop-groom-jamath').textContent = data.groom_jamath || "---";

        // 3. Populate Bride Node Fields & Contacts
        document.getElementById('pop-bride-name').textContent = data.bride_first_name || "---";
        document.getElementById('pop-bride-last-name').textContent = data.bride_last_name || "---";
        document.getElementById('pop-bride-phone1').textContent = data.bride_phone1 || "---";
        document.getElementById('pop-bride-phone2').textContent = data.bride_phone2 || "None";

        const brideFatherStatus = parseInt(data.bride_father_status) === 0 ? " (Deceased)" : " (Alive)";
        const brideMotherStatus = parseInt(data.bride_mother_status) === 0 ? " (Deceased)" : " (Alive)";
        document.getElementById('pop-bride-father').textContent = data.bride_father ? data.bride_father + brideFatherStatus : "---";
        document.getElementById('pop-bride-mother').textContent = data.bride_mother ? data.bride_mother + brideMotherStatus : "---";

        document.getElementById('pop-bride-dob').textContent = data.bride_dob || "---";
        document.getElementById('pop-bride-age').textContent = data.bride_age ? data.bride_age + " Years" : "---";
        document.getElementById('pop-bride-status').textContent = data.bride_marriage_status || "---";
        document.getElementById('pop-bride-jamath').textContent = data.bride_jamath || "---";

        // 4. Populate Multi-Line Structured Witness Address Tracks
        document.getElementById('pop-witness-groom-name').textContent = data.witness_groom_name || "---";
        document.getElementById('pop-witness-groom-relation').textContent = data.witness_groom_relationship || "---";
        document.getElementById('pop-witness-groom-phone').textContent = data.witness_groom_phone || "---";

        // Construct Multi-Line Output for Groom Witness
        if (data.witness_groom_addr_l1) {
            let groomAddrHTML = `${data.witness_groom_addr_l1}<br>`;
            if (data.witness_groom_addr_l2) groomAddrHTML += `${data.witness_groom_addr_l2}<br>`;
            groomAddrHTML += `${data.witness_groom_city || ''} - ${data.witness_groom_pincode || ''}<br>${data.witness_groom_country || 'India'}`;
            document.getElementById('pop-witness-groom-address').innerHTML = groomAddrHTML;
        } else {
            document.getElementById('pop-witness-groom-address').textContent = "---";
        }

        document.getElementById('pop-witness-bride-name').textContent = data.witness_bride_name || "---";
        document.getElementById('pop-witness-bride-relation').textContent = data.witness_bride_relationship || "---";
        document.getElementById('pop-witness-bride-phone').textContent = data.witness_bride_phone || "---";

        // Construct Multi-Line Output for Bride Witness
        if (data.witness_bride_addr_l1) {
            let brideAddrHTML = `${data.witness_bride_addr_l1}<br>`;
            if (data.witness_bride_addr_l2) brideAddrHTML += `${data.witness_bride_addr_l2}<br>`;
            brideAddrHTML += `${data.witness_bride_city || ''} - ${data.witness_bride_pincode || ''}<br>${data.witness_bride_country || 'India'}`;
            document.getElementById('pop-witness-bride-address').innerHTML = brideAddrHTML;
        } else {
            document.getElementById('pop-witness-bride-address').textContent = "---";
        }

        // 5. Ceremonial Base Infrastructure Data
        document.getElementById('pop-venue').textContent = data.venue || "---";
        document.getElementById('pop-officiator').textContent = parseInt(data.conducted_by_jamath) === 1 ? "NVK Muslim Jamaath Registry Board" : "Private Officiator";
        document.getElementById('pop-book').textContent = data.book_reference ? "Volume Reference Code: " + data.book_reference : "No book reference linked.";

        // 6. Dynamic File / Asset Link Renderer Sub-Engine
        renderPopPhoto('pop-groom-photo-wrap', 'pop-groom-photo-link', data.groom_photo);
        renderPopPhoto('pop-bride-photo-wrap', 'pop-bride-photo-link', data.bride_photo);

        renderPopDoc('pop-groom-doc-wrap', data.groom_id_type || 'Identity proof', data.groom_id_doc);
        renderPopDoc('pop-bride-doc-wrap', data.bride_id_type || 'Identity proof', data.bride_id_doc);

        renderPopWitnessDoc('pop-witness-groom-doc', 'Witness ID Proof', data.witness_groom_id_doc);
        renderPopWitnessDoc('pop-witness-bride-doc', 'Witness ID Proof', data.witness_bride_id_doc);

        // 7. Wire Up Action Redirect Links
        document.getElementById('pop-edit-btn').setAttribute('onclick', `populateEditNikah(${JSON.stringify(data)})`);
        document.getElementById('pop-cert-btn').className = "bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 transition-colors";
        document.getElementById('pop-cert-btn').setAttribute('onclick', `issueNikahCertificate(${JSON.stringify(data)})`);

        // 8. Open Modal
        document.getElementById('nikah-card-modal').classList.remove('hidden');
    }

    // Helper Function: Renders Circular Avatar Thumbnails
    function renderPopPhoto(wrapId, linkId, path) {
        const wrap = document.getElementById(wrapId);
        const link = document.getElementById(linkId);
        if (path && path.trim() !== "") {
            wrap.innerHTML = `<img src="${path}" class="h-full w-full object-cover" alt="Profile">`;
            link.href = path;
            link.style.pointerEvents = "auto";
        } else {
            wrap.innerHTML = `<i class="fa-solid fa-user text-xl text-slate-400"></i>`;
            link.href = "#";
            link.style.pointerEvents = "none";
        }
    }

    // Helper Function: Renders Main Document Badges
    function renderPopDoc(wrapId, docLabel, path) {
        const wrap = document.getElementById(wrapId);
        if (path && path.trim() !== "") {
            wrap.innerHTML = `<a href="${path}" target="_blank" class="text-teal-700 hover:text-teal-900 inline-flex items-center gap-1 bg-teal-50 px-2 py-1 rounded-md border border-teal-100 transition-colors"><i class="fa-solid fa-file-arrow-down"></i> Verified ${docLabel}</a>`;
        } else {
            wrap.innerHTML = `<span class="text-slate-400 font-normal italic">No ${docLabel} uploaded</span>`;
        }
    }

    // Helper Function: Renders Witness Document Rows
    function renderPopWitnessDoc(wrapId, docLabel, path) {
        const wrap = document.getElementById(wrapId);
        if (path && path.trim() !== "") {
            wrap.innerHTML = `<span class="text-slate-400 font-medium">Attachment:</span> <a href="${path}" target="_blank" class="text-teal-700 hover:underline font-bold font-mono ml-1"><i class="fa-solid fa-paperclip mr-0.5"></i> View ${docLabel}</a>`;
        } else {
            wrap.innerHTML = `<span class="text-slate-400 font-medium">Attachment:</span> <span class="text-slate-400 italic font-normal ml-1">None uploaded</span>`;
        }
    }

    function closeNikahCard() {
        document.getElementById('nikah-card-modal').classList.add('hidden');
    }

    // Toggle forms logic depending on origin choices
    // Dynamic Field Locking & Fallback Constraint State Management Engine
    function adjustFieldState(fieldId, isWithinJamath) {
        const el = document.getElementById(fieldId);
        if (!el) return;

        // Check if the current value of the field is empty
        const isEmptyValue = !el.value || el.value.trim() === "";

        if (isWithinJamath) {
            if (isEmptyValue) {
                // FALLBACK UNLOCK: Empty parameter from DB -> let user input it manually
                if (el.tagName === "SELECT") el.disabled = false;
                else el.readOnly = false;

                el.classList.remove('bg-slate-100', 'cursor-not-allowed');
                el.classList.add('bg-amber-50/40', 'border-amber-200'); // Optional subtle alert styling
                el.required = true; // Make it required since it's empty and needs manual submission data
            } else {
                // STRICT DATA GUARD: Valid info exists -> Lock securely from modifications
                if (el.tagName === "SELECT") el.disabled = true;
                else el.readOnly = true;

                el.classList.add('bg-slate-100', 'cursor-not-allowed');
                el.classList.remove('bg-amber-50/40', 'border-amber-200');
                el.required = false; // Bypass required rule restriction since it has fixed data
            }
        } else {
            // DEFAULT FREEDOM STATE: Outside Jamaath choice -> fully editable inputs
            if (el.tagName === "SELECT") el.disabled = false;
            else el.readOnly = false;

            el.classList.remove('bg-slate-100', 'cursor-not-allowed', 'bg-amber-50/40', 'border-amber-200');
            el.required = true;
        }
    }

    // Toggle forms logic depending on origin choices (Groom)
    function toggleGroomFields() {
        const isWithinJamath = document.getElementById('groom_origin_jamath').checked;
        const selectContainer = document.getElementById('groom_jamath_container');

        if (isWithinJamath) {
            selectContainer.classList.remove('hidden');
        } else {
            selectContainer.classList.add('hidden');
            document.getElementById('groom_select').value = "";
        }

        // Target fields matrix setup
        const fieldsToToggle = [
            'groom_first_name_field', 'groom_last_name_field',
            'groom_father_field', 'groom_father_status',
            'groom_mother_field', 'groom_mother_status',
            'groom_dob_field', 'groom_aadhar_field'
        ];

        // Evaluate state individually for every field mapping parameter
        fieldsToToggle.forEach(id => {
            adjustFieldState(id, isWithinJamath);
        });

        // Cross-Field Origin Validation Safeguard Loop
        const groomWithin = document.getElementById('groom_origin_jamath').checked;
        const brideWithin = document.getElementById('bride_origin_jamath').checked;

        if (!groomWithin && !brideWithin) {
            alert("⚠️ Policy Alert: Both parties cannot be from outside the Jamaath. Please ensure either the groom or the bride belongs to the Jamaath registry.");

            // Automatically revert back to true to self-heal the form choice status
            document.getElementById('groom_origin_jamath').checked = true;
            toggleGroomFields(); // Recursive sync execution thread
        }
    }

    // Toggle forms logic depending on origin choices (Bride)
    function toggleBrideFields() {
        const isWithinJamath = document.getElementById('bride_origin_jamath').checked;
        const selectContainer = document.getElementById('bride_jamath_container');

        if (isWithinJamath) {
            selectContainer.classList.remove('hidden');
        } else {
            selectContainer.classList.add('hidden');
            document.getElementById('bride_select').value = "";
        }

        // Target fields matrix setup
        const fieldsToToggle = [
            'bride_first_name_field', 'bride_last_name_field',
            'bride_father_field', 'bride_father_status',
            'bride_mother_field', 'bride_mother_status',
            'bride_dob_field', 'bride_aadhar_field'
        ];

        // Evaluate state individually for every field mapping parameter
        fieldsToToggle.forEach(id => {
            adjustFieldState(id, isWithinJamath);
        });

        // Cross-Field Origin Validation Safeguard Loop
        const groomWithin = document.getElementById('groom_origin_jamath').checked;
        const brideWithin = document.getElementById('bride_origin_jamath').checked;

        if (!groomWithin && !brideWithin) {
            alert("⚠️ Policy Alert: Both parties cannot be from outside the Jamaath. Please ensure either the groom or the bride belongs to the Jamaath registry.");

            // Automatically revert back to true to self-heal the form choice status
            document.getElementById('bride_origin_jamath').checked = true;
            toggleBrideFields(); // Recursive sync execution thread
        }
    }

    // MODIFICATION: Reads data-dob to auto-populate DOB and calculate live age
    function autoPopulateGroom() {
        const select = document.getElementById('groom_select');
        const selectedOption = select.options[select.selectedIndex];

        // Comprehensive clear when chosen selection is empty or removed
        if (!selectedOption || select.value === "") {
            document.getElementById('groom_first_name_field').value = "";
            document.getElementById('groom_last_name_field').value = "";
            if (document.getElementById('groom_phone1_field')) document.getElementById('groom_phone1_field').value = "";
            if (document.getElementById('groom_phone2_field')) document.getElementById('groom_phone2_field').value = "";
            document.getElementById('groom_father_field').value = "";
            document.getElementById('groom_father_status').value = "1";
            document.getElementById('groom_mother_field').value = "";
            document.getElementById('groom_mother_status').value = "1";
            document.getElementById('groom_dob_field').value = "";
            document.getElementById('groom_age_field').value = "";
            document.getElementById('groom_live_age_preview').textContent = "--";
            return;
        }

        // 1. Populate valid properties discovered inside the database schema
        document.getElementById('groom_first_name_field').value = selectedOption.getAttribute('data-first-name') || "";
        document.getElementById('groom_last_name_field').value = selectedOption.getAttribute('data-last-name') || "";

        if (document.getElementById('groom_phone1_field')) {
            document.getElementById('groom_phone1_field').value = selectedOption.getAttribute('data-phone1') || "";
        }

        document.getElementById('groom_father_field').value = selectedOption.getAttribute('data-father') || "";

        const dob = selectedOption.getAttribute('data-dob') || "";
        document.getElementById('groom_dob_field').value = dob;

        // 2. Explicitly wipe/reset fields NOT found in the members table schema
        if (document.getElementById('groom_phone2_field')) {
            document.getElementById('groom_phone2_field').value = ""; // Clear Alt Phone
        }
        document.getElementById('groom_mother_field').value = "";     // Mother name does not exist in schema
        document.getElementById('groom_father_status').value = "1";   // Revert defaults to Alive
        document.getElementById('groom_mother_status').value = "1";   // Revert defaults to Alive

        // 3. Dynamic layout recalculations
        if (dob) {
            calculateLiveAge(document.getElementById('groom_dob_field'), 'groom_live_age_preview', 'groom_age_field');
        }

        toggleGroomFields();
    }

    function autoPopulateBride() {
        const select = document.getElementById('bride_select');
        const selectedOption = select.options[select.selectedIndex];

        // Comprehensive clear when chosen selection is empty or removed
        if (!selectedOption || select.value === "") {
            document.getElementById('bride_first_name_field').value = "";
            document.getElementById('bride_last_name_field').value = "";
            if (document.getElementById('bride_phone1_field')) document.getElementById('bride_phone1_field').value = "";
            if (document.getElementById('bride_phone2_field')) document.getElementById('bride_phone2_field').value = "";
            document.getElementById('bride_father_field').value = "";
            document.getElementById('bride_father_status').value = "1";
            document.getElementById('bride_mother_field').value = "";
            document.getElementById('bride_mother_status').value = "1";
            document.getElementById('bride_dob_field').value = "";
            document.getElementById('bride_age_field').value = "";
            document.getElementById('bride_live_age_preview').textContent = "--";
            return;
        }

        // 1. Populate valid properties discovered inside the database schema
        document.getElementById('bride_first_name_field').value = selectedOption.getAttribute('data-first-name') || "";
        document.getElementById('bride_last_name_field').value = selectedOption.getAttribute('data-last-name') || "";

        if (document.getElementById('bride_phone1_field')) {
            document.getElementById('bride_phone1_field').value = selectedOption.getAttribute('data-phone1') || "";
        }

        document.getElementById('bride_father_field').value = selectedOption.getAttribute('data-father') || "";

        const dob = selectedOption.getAttribute('data-dob') || "";
        document.getElementById('bride_dob_field').value = dob;

        // 2. Explicitly wipe/reset fields NOT found in the members table schema
        if (document.getElementById('bride_phone2_field')) {
            document.getElementById('bride_phone2_field').value = ""; // Clear Alt Phone
        }
        document.getElementById('bride_mother_field').value = "";     // Mother name does not exist in schema
        document.getElementById('bride_father_status').value = "1";   // Revert defaults to Alive
        document.getElementById('bride_mother_status').value = "1";   // Revert defaults to Alive

        // 3. Dynamic layout recalculations
        if (dob) {
            calculateLiveAge(document.getElementById('bride_dob_field'), 'bride_live_age_preview', 'bride_age_field');
        }

        toggleBrideFields();
    }

    // NEW HELPER: Client-side Age computation and target hidden value logging
    function calculateLiveAge(dobInput, previewId, hiddenInputId) {
        const dobValue = dobInput.value;
        const previewEl = document.getElementById(previewId);
        const hiddenEl = document.getElementById(hiddenInputId);

        if (!dobValue) {
            previewEl.innerText = "--";
            hiddenEl.value = "";
            return;
        }

        const birthDate = new Date(dobValue);
        const today = new Date();

        let calculatedAge = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            calculatedAge--;
        }

        const runtimeAge = calculatedAge >= 0 ? calculatedAge : 0;
        previewEl.innerText = runtimeAge + " Years";
        hiddenEl.value = runtimeAge;
    }

    // NEW HELPER: Instantly creates a render canvas link preview container above input nodes
    function setupImagePreviewTrigger(inputName, previewContainerId) {
        const inputEl = document.querySelector(`input[name="${inputName}"]`);
        const container = document.getElementById(previewContainerId);

        if (!inputEl || !container) return;

        inputEl.onchange = function () {
            container.innerHTML = ""; // Clear out previous data cleanly
            const file = this.files[0];

            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = "h-full w-full object-cover rounded-md shadow-inner";
                        container.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Graceful fallback display layout formatting if uploading legal PDF proofs instead
                    container.innerHTML = `
                    <div class="text-center text-teal-700">
                        <i class="fa-solid fa-file-pdf text-lg"></i>
                        <span class="block text-[8px] font-bold uppercase tracking-tight mt-0.5">Attached</span>
                    </div>`;
                }
            }
        };
    }

    // Modal windows toggle managers
    function openNikahModal() {
        // REMOVED: resetNikahForm() is removed from here so it doesn't step on Edit mappings

        // MODIFICATION: Explicitly initialize all four image and document preview engines
        setupImagePreviewTrigger('groom_photo', 'groom_photo_preview_box');
        setupImagePreviewTrigger('groom_id_doc', 'groom_id_doc_preview_box');
        setupImagePreviewTrigger('bride_photo', 'bride_photo_preview_box');
        setupImagePreviewTrigger('bride_id_doc', 'bride_id_doc_preview_box');

        document.getElementById('nikah-modal').classList.remove('hidden');
    }

    function populateEditNikah(nikah) {
        openNikahModal();
        document.getElementById('nikah-form-title').textContent = "Update Certified Marriage Registry";
        document.getElementById('nikah-form-action').value = "edit_nikah";
        document.getElementById('nikah-form-id').value = nikah.id;
        document.getElementById('nikah-form-submit').textContent = "Save Changes";

        // Unhide and restore Jamaath panels safely
        if (document.getElementById('groom_jamath_container')) document.getElementById('groom_jamath_container').classList.remove('hidden');
        if (document.getElementById('bride_jamath_container')) document.getElementById('bride_jamath_container').classList.remove('hidden');

        // Grant text editing privileges across demographic rows
        const fieldsToEnable = [
            'groom_first_name_field', 'groom_last_name_field', 'groom_father_field', 'groom_mother_field', 'groom_dob_field', 'groom_jamath_field', 'groom_aadhar_field',
            'bride_first_name_field', 'bride_last_name_field', 'bride_father_field', 'bride_mother_field', 'bride_dob_field', 'bride_jamath_field', 'bride_aadhar_field'
        ];
        fieldsToEnable.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.readOnly = false;
                el.disabled = false;
                el.classList.remove('bg-slate-100', 'cursor-not-allowed');
            }
        });

        // Enable Parent select dropdowns explicitly
        const parentDropdowns = [
            'groom_father_status_field', 'groom_mother_status_field',
            'bride_father_status_field', 'bride_mother_status_field'
        ];
        parentDropdowns.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.disabled = false;
                el.classList.remove('bg-slate-100', 'cursor-not-allowed');
            }
        });

        // --- POPULATE GROOM BIOMETRICS ---
        if (document.getElementById('groom_first_name_field')) document.getElementById('groom_first_name_field').value = nikah.groom_first_name || '';
        if (document.getElementById('groom_last_name_field')) document.getElementById('groom_last_name_field').value = nikah.groom_last_name || '';
        if (document.getElementById('groom_father_field')) document.getElementById('groom_father_field').value = nikah.groom_father || '';
        if (document.getElementById('groom_father_status_field')) document.getElementById('groom_father_status_field').value = nikah.groom_father_status !== undefined ? nikah.groom_father_status : '1';
        if (document.getElementById('groom_mother_field')) document.getElementById('groom_mother_field').value = nikah.groom_mother || '';
        if (document.getElementById('groom_mother_status_field')) document.getElementById('groom_mother_status_field').value = nikah.groom_mother_status !== undefined ? nikah.groom_mother_status : '1';

        if (document.getElementById('groom_dob_field')) {
            document.getElementById('groom_dob_field').value = nikah.groom_dob || '';
            calculateLiveAge(document.getElementById('groom_dob_field'), 'groom_live_age_preview', 'groom_age_field');
        }
        if (document.getElementById('groom_marriage_field')) document.getElementById('groom_marriage_field').value = nikah.groom_marriage_status || 'First Marriage';
        if (document.getElementById('groom_id_type')) document.getElementById('groom_id_type').value = nikah.groom_id_type || '';
        if (document.getElementById('groom_aadhar_field')) document.getElementById('groom_aadhar_field').value = nikah.groom_aadhar || '';
        if (document.getElementById('groom_phone1_field')) document.getElementById('groom_phone1_field').value = nikah.groom_phone1 || '';
        if (document.getElementById('groom_phone2_field')) document.getElementById('groom_phone2_field').value = nikah.groom_phone2 || '';

        // --- POPULATE BRIDE BIOMETRICS ---
        if (document.getElementById('bride_first_name_field')) document.getElementById('bride_first_name_field').value = nikah.bride_first_name || '';
        if (document.getElementById('bride_last_name_field')) document.getElementById('bride_last_name_field').value = nikah.bride_last_name || '';
        if (document.getElementById('bride_father_field')) document.getElementById('bride_father_field').value = nikah.bride_father || '';
        if (document.getElementById('bride_father_status_field')) document.getElementById('bride_father_status_field').value = nikah.bride_father_status !== undefined ? nikah.bride_father_status : '1';
        if (document.getElementById('bride_mother_field')) document.getElementById('bride_mother_field').value = nikah.bride_mother || '';
        if (document.getElementById('bride_mother_status_field')) document.getElementById('bride_mother_status_field').value = nikah.bride_mother_status !== undefined ? nikah.bride_mother_status : '1';

        if (document.getElementById('bride_dob_field')) {
            document.getElementById('bride_dob_field').value = nikah.bride_dob || '';
            calculateLiveAge(document.getElementById('bride_dob_field'), 'bride_live_age_preview', 'bride_age_field');
        }
        if (document.getElementById('bride_marriage_field')) document.getElementById('bride_marriage_field').value = nikah.bride_marriage_status || 'First Marriage';
        if (document.getElementById('bride_id_type')) document.getElementById('bride_id_type').value = nikah.bride_id_type || '';
        if (document.getElementById('bride_aadhar_field')) document.getElementById('bride_aadhar_field').value = nikah.bride_aadhar || '';
        if (document.getElementById('bride_phone1_field')) document.getElementById('bride_phone1_field').value = nikah.bride_phone1 || '';
        if (document.getElementById('bride_phone2_field')) document.getElementById('bride_phone2_field').value = nikah.bride_phone2 || '';

        // --- MANAGEMENT FOR JAMAATH SELECTION DRIVERS ---
        if (nikah.groom_jamath && nikah.groom_jamath !== 'NVK Jamaath (Vadasery)') {
            if (document.getElementById('groom_origin_select')) document.getElementById('groom_origin_select').value = 'outside';
            if (document.getElementById('groom_jamath_field')) document.getElementById('groom_jamath_field').value = nikah.groom_jamath;
        } else {
            if (document.getElementById('groom_origin_select')) document.getElementById('groom_origin_select').value = 'within';
            if (document.getElementById('groom_jamath_field')) document.getElementById('groom_jamath_field').value = 'NVK Jamaath (Vadasery)';
        }

        if (nikah.bride_jamath && nikah.bride_jamath !== 'NVK Jamaath (Vadasery)') {
            if (document.getElementById('bride_origin_select')) document.getElementById('bride_origin_select').value = 'outside';
            if (document.getElementById('bride_jamath_field')) document.getElementById('bride_jamath_field').value = nikah.bride_jamath;
        } else {
            if (document.getElementById('bride_origin_select')) document.getElementById('bride_origin_select').value = 'within';
            if (document.getElementById('bride_jamath_field')) document.getElementById('bride_jamath_field').value = 'NVK Jamaath (Vadasery)';
        }

        // --- WITNESS PACKET POPULATION (WITH BROKEN-DOWN ADDRESS FIELDS) ---
        if (document.getElementsByName('witness_groom_name')[0]) document.getElementsByName('witness_groom_name')[0].value = nikah.witness_groom_name || '';
        if (document.getElementsByName('witness_groom_relationship')[0]) document.getElementsByName('witness_groom_relationship')[0].value = nikah.witness_groom_relationship || '';
        if (document.getElementsByName('witness_groom_phone')[0]) document.getElementsByName('witness_groom_phone')[0].value = nikah.witness_groom_phone || '';

        // Groom Address Components
        if (document.getElementsByName('witness_groom_addr_l1')[0]) document.getElementsByName('witness_groom_addr_l1')[0].value = nikah.witness_groom_addr_l1 || '';
        if (document.getElementsByName('witness_groom_addr_l2')[0]) document.getElementsByName('witness_groom_addr_l2')[0].value = nikah.witness_groom_addr_l2 || '';
        if (document.getElementsByName('witness_groom_city')[0]) document.getElementsByName('witness_groom_city')[0].value = nikah.witness_groom_city || '';
        if (document.getElementsByName('witness_groom_pincode')[0]) document.getElementsByName('witness_groom_pincode')[0].value = nikah.witness_groom_pincode || '';
        if (document.getElementsByName('witness_groom_country')[0]) document.getElementsByName('witness_groom_country')[0].value = nikah.witness_groom_country || 'India';

        if (document.getElementsByName('witness_bride_name')[0]) document.getElementsByName('witness_bride_name')[0].value = nikah.witness_bride_name || '';
        if (document.getElementsByName('witness_bride_relationship')[0]) document.getElementsByName('witness_bride_relationship')[0].value = nikah.witness_bride_relationship || '';
        if (document.getElementsByName('witness_bride_phone')[0]) document.getElementsByName('witness_bride_phone')[0].value = nikah.witness_bride_phone || '';

        // Bride Address Components
        if (document.getElementsByName('witness_bride_addr_l1')[0]) document.getElementsByName('witness_bride_addr_l1')[0].value = nikah.witness_bride_addr_l1 || '';
        if (document.getElementsByName('witness_bride_addr_l2')[0]) document.getElementsByName('witness_bride_addr_l2')[0].value = nikah.witness_bride_addr_l2 || '';
        if (document.getElementsByName('witness_bride_city')[0]) document.getElementsByName('witness_bride_city')[0].value = nikah.witness_bride_city || '';
        if (document.getElementsByName('witness_bride_pincode')[0]) document.getElementsByName('witness_bride_pincode')[0].value = nikah.witness_bride_pincode || '';
        if (document.getElementsByName('witness_bride_country')[0]) document.getElementsByName('witness_bride_country')[0].value = nikah.witness_bride_country || 'India';

        // --- CEREMONY META ARCHIVES ---
        if (document.getElementById('nikah-datetime-field')) document.getElementById('nikah-datetime-field').value = nikah.nikah_datetime ? nikah.nikah_datetime.replace(" ", "T").substring(0, 16) : '';
        if (document.getElementById('nikah-venue-field')) document.getElementById('nikah-venue-field').value = nikah.venue || '';
        if (document.getElementById('nikah-book-field')) document.getElementById('nikah-book-field').value = nikah.book_reference || '';
        if (document.getElementById('nikah-conducted-check')) document.getElementById('nikah-conducted-check').checked = (parseInt(nikah.conducted_by_jamath) === 1);

        // --- UNHIDE IDENTIFICATION WRAPPERS IF APPLICABLE ---
        if (nikah.groom_id_type && document.getElementById('groom_id_upload_wrapper')) {
            document.getElementById('groom_id_upload_wrapper').classList.remove('hidden');
        }
        if (nikah.bride_id_type && document.getElementById('bride_id_upload_wrapper')) {
            document.getElementById('bride_id_upload_wrapper').classList.remove('hidden');
        }

        // --- ASSET FILE THUMBNAIL PREVIEWS ---
        renderBoxPreview('groom_photo_preview_box', nikah.groom_photo);
        renderBoxPreview('groom_id_doc_preview_box', nikah.groom_id_doc);

        renderBoxPreview('bride_photo_preview_box', nikah.bride_photo);
        renderBoxPreview('bride_id_doc_preview_box', nikah.bride_id_doc);

        renderBoxPreview('witness_groom_preview_box', nikah.witness_groom_id_doc);
        renderBoxPreview('witness_bride_preview_box', nikah.witness_bride_id_doc);
    }

    // Add this helper function directly below your populateEditNikah function inside nikah.php
    function renderBoxPreview(boxId, path) {
        const box = document.getElementById(boxId);
        if (!box) return;

        if (path && path.trim() !== "") {
            if (path.toLowerCase().endsWith('.pdf')) {
                box.innerHTML = `<div class="text-center text-red-600 font-bold text-[8px] w-full"><i class="fa-solid fa-file-pdf text-sm"></i><br><a href="${path}" target="_blank" class="underline hover:text-red-800">View PDF</a></div>`;
            } else {
                box.innerHTML = `<img src="${path}" class="h-full w-full object-cover rounded-md cursor-pointer" onclick="window.open('${path}', '_blank')" title="Click to view full asset">`;
            }
        } else {
            box.innerHTML = `<span class="text-[9px] text-slate-400 font-normal italic">None</span>`;
        }
    }

    function resetNikahForm() {
        document.getElementById('nikah-form').reset();
        document.getElementById('nikah-form-title').textContent = "Register Certified Nikah Contract";
        document.getElementById('nikah-form-action').value = "add_nikah";
        document.getElementById('nikah-form-id').value = "";
        document.getElementById('nikah-form-submit').textContent = "Record Nikah Contract";

        document.getElementById('groom-origin-box').classList.remove('hidden');
        document.getElementById('bride-origin-box').classList.remove('hidden');

        document.getElementById('groom_origin_jamath').checked = true;
        document.getElementById('bride_origin_jamath').checked = true;

        // Re-hide conditional upload cards cleanly
        ['groom', 'bride'].forEach(party => {
            document.getElementById(`${party}_id_upload_wrapper`).classList.add("hidden");
            document.getElementById(`${party}_aadhar_upload_container`).classList.add("hidden");
        });

        // Reset indicator outputs
        document.getElementById('groom_live_age_preview').textContent = '--';
        document.getElementById('bride_live_age_preview').textContent = '--';

        // Wipe preview thumbnail grids
        const previewBoxes = [
            'groom_photo_preview_box', 'groom_id_doc_preview_box', 'groom_aadhar_preview_box',
            'bride_photo_preview_box', 'bride_id_doc_preview_box', 'bride_aadhar_preview_box',
            'witness_groom_preview_box', 'witness_bride_preview_box'
        ];
        previewBoxes.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = "";
        });

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
        if (!nikah) return;

        // 1. Build groom full name string cleanly using only your new split properties
        const groomFirst = nikah.groom_first_name || '';
        const groomLast = nikah.groom_last_name || '';
        const groomFullName = (groomFirst + ' ' + groomLast).trim();
        const groomText = groomFullName + (nikah.groom_father ? " (S/O: " + nikah.groom_father + ")" : "");

        // 2. Build bride full name string cleanly using only your new split properties
        const brideFirst = nikah.bride_first_name || '';
        const brideLast = nikah.bride_last_name || '';
        const brideFullName = (brideFirst + ' ' + brideLast).trim();
        const brideText = brideFullName + (nikah.bride_father ? " (D/O: " + nikah.bride_father + ")" : "");

        // Map remaining form parameters safely
        const formattedDate = typeof formatDateJS === 'function' ? formatDateJS(nikah.nikah_datetime) : (nikah.nikah_datetime || "");
        const venueText = nikah.venue || "";
        const detailsText = nikah.book_reference || "Registry Archive";

        // 3. Fix the filename crash by running the replacement against the newly compiled groomFullName variable
        const safeFilenameGroom = groomFullName.replace(/\s+/g, '_');
        const opt = {
            margin: 0.3,
            filename: `Nikah_Certificate_${safeFilenameGroom}.pdf`,
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
            
            <div style="position: absolute; top: 12px; left: 12px; color: #0f766e; font-size: 20px;">🕌</div>
            <div style="position: absolute; top: 12px; right: 12px; color: #0f766e; font-size: 20px;">🕌</div>
            <div style="position: absolute; bottom: 12px; left: 12px; color: #0f766e; font-size: 20px;">🕌</div>
            <div style="position: absolute; bottom: 12px; right: 12px; color: #0f766e; font-size: 20px;">🕌</div>
            
            <div style="text-align: center; margin-bottom: 15px;">
                <h1 style="margin: 0; color: #115e59; font-size: 30px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;">NVK Muslim Jamaath</h1>
                <p style="margin: 5px 0 0 0; font-size: 11px; text-transform: uppercase; letter-spacing: 4px; font-weight: bold; color: #0f766e;">Vadasery, Nagercoil, Kanyakumari District, Tamil Nadu</p>
                <div style="width: 250px; height: 3px; background: linear-gradient(to right, transparent, #b45309, transparent); margin: 12px auto 4px auto;"></div>
                <div style="width: 150px; height: 1px; background: #e2e8f0; margin: 0 auto;"></div>
            </div>

            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-family: Georgia, serif; font-style: italic; color: #b45309; font-size: 24px; margin: 5px 0;">Certificate of Marriage (Nikah)</h2>
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
                    <div style="border: 2px solid #047857; border-radius: 50%; width: 75px; height: 75px; padding-top: 22px; box-sizing: border-box; margin: 0 auto; color: #047857; font-size: 9px; font-weight: bold; text-transform: uppercase; transform: rotate(-8deg); font-family: sans-serif; text-align: center; line-height: 12px;">
                        Registry<br>Seal
                    </div>
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

    function previewFile(input, previewBoxId) {
        const previewBox = document.getElementById(previewBoxId);
        if (!previewBox) return;

        // Reset preview window clear state if empty
        previewBox.innerHTML = "";

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const maxSizeBytes = 2 * 1024 * 1024; // Strict 2 Megabyte calculation boundary

            // Enforce file size rule immediately in browser view
            if (file.size > maxSizeBytes) {
                alert(`File Rejected: "${file.name}" is too large! Maximum allowed profile asset file size constraint is 2MB.`);
                input.value = ""; // Erase bad file input trace cleanly
                return;
            }

            // Render Asset Thumbnails cleanly if it passes validation
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewBox.innerHTML = `<img src="${e.target.result}" class="h-full w-full object-cover rounded-md" alt="Preview">`;
                }
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                previewBox.innerHTML = `<div class="text-center text-red-600"><i class="fa-solid fa-file-pdf text-xl"></i><span class="block text-[8px] font-bold uppercase mt-0.5">PDF</span></div>`;
            } else {
                previewBox.innerHTML = `<div class="text-center text-slate-500"><i class="fa-solid fa-file text-xl"></i></div>`;
            }
        }
    }

    // Global tracking instances for phone input fields
    let itiGroom1, itiGroom2, itiBride1, itiBride2, itiWitnessGroom, itiWitnessBride;

    document.addEventListener("DOMContentLoaded", function () {
        // Initialize international telephone handling engines with Indian baseline defaults
        const options = {
            initialCountry: "in",
            separateDialCode: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
        };

        itiGroom1 = window.intlTelInput(document.getElementById("groom_phone1_field"), options);
        itiGroom2 = window.intlTelInput(document.getElementById("groom_phone2_field"), options);
        itiBride1 = window.intlTelInput(document.getElementById("bride_phone1_field"), options);
        itiBride2 = window.intlTelInput(document.getElementById("bride_phone2_field"), options);
        itiWitnessGroom = window.intlTelInput(document.getElementById("witness_groom_phone"), options);
        itiWitnessBride = window.intlTelInput(document.getElementById("witness_bride_phone"), options);
    });

    // Dynamic identity choice logic configuration
    function handleIdTypeChange(party) {
        const idTypeSelect = document.getElementById(`${party}_id_type`);
        const uploadWrapper = document.getElementById(`${party}_id_upload_wrapper`);
        const aadharUploadContainer = document.getElementById(`${party}_aadhar_upload_container`);
        const idFileInput = document.getElementById(`${party}_id_doc`);
        const aadharFileInput = document.getElementById(`${party}_aadhar_file`);

        if (!idTypeSelect.value) {
            uploadWrapper.classList.add("hidden");
            idFileInput.disabled = true;
            aadharUploadContainer.classList.add("hidden");
            aadharFileInput.disabled = true;
            return;
        }

        // Step A: Unhide and unlock basic selection upload container
        uploadWrapper.classList.remove("hidden");
        idFileInput.disabled = false;

        // Step B: Enforce fallback logic constraint if a non-Aadhaar option is chosen
        if (idTypeSelect.value !== "Aadhaar") {
            aadharUploadContainer.classList.remove("hidden");
            aadharFileInput.disabled = false;
            aadharFileInput.required = true;
        } else {
            aadharUploadContainer.classList.add("hidden");
            aadharFileInput.disabled = true;
            aadharFileInput.required = false;
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("nikah-form");
        if (!form) return;

        form.addEventListener("submit", function (e) {
            // Intercept standard dispatch temporarily to apply numbers formatting

            // Groom Primary Contact
            if (typeof itiGroom1 !== "undefined" && document.getElementById("groom_phone1_field").value.trim() !== "") {
                document.getElementById("groom_phone1_field").value = itiGroom1.getNumber();
            }
            // Groom Alternative Contact
            if (typeof itiGroom2 !== "undefined" && document.getElementById("groom_phone2_field").value.trim() !== "") {
                document.getElementById("groom_phone2_field").value = itiGroom2.getNumber();
            }
            // Bride Primary Contact
            if (typeof itiBride1 !== "undefined" && document.getElementById("bride_phone1_field").value.trim() !== "") {
                document.getElementById("bride_phone1_field").value = itiBride1.getNumber();
            }
            // Bride Alternative Contact
            if (typeof itiBride2 !== "undefined" && document.getElementById("bride_phone2_field").value.trim() !== "") {
                document.getElementById("bride_phone2_field").value = itiBride2.getNumber();
            }
            // Groom Witness Contact
            if (typeof itiWitnessGroom !== "undefined" && document.getElementById("witness_groom_phone").value.trim() !== "") {
                document.getElementById("witness_groom_phone").value = itiWitnessGroom.getNumber();
            }
            // Bride Witness Contact
            if (typeof itiWitnessBride !== "undefined" && document.getElementById("witness_bride_phone").value.trim() !== "") {
                document.getElementById("witness_bride_phone").value = itiWitnessBride.getNumber();
            }

            // Let the form proceed cleanly with updated values over the POST payload!
        });
    });
</script>

<?php require_once 'footer.php'; ?>
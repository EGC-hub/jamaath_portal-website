<?php
require_once 'db.php';
require_once 'helpers.php';

// Enforce Private Session Lock
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search queries
$where_clauses = [];
$params = [];
if (!empty($search)) {
    $where_clauses[] = "(heading LIKE ? OR caption LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Get count for pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM gallery $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1)
    $total_pages = 1;
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch paginated CMS rows
$fetch_stmt = $db->prepare("SELECT * FROM gallery $where_sql ORDER BY date_added DESC LIMIT $limit OFFSET $offset");
$fetch_stmt->execute($params);
$gallery_items = $fetch_stmt->fetchAll();

// Calculate total storage occupied
$size_stmt = $db->query("SELECT IFNULL(SUM(image_size), 0) FROM gallery");
$total_bytes = (int) $size_stmt->fetchColumn();
$limit_bytes = 1073741824; // 1GB Cap

$occupied_pct = min(100, round(($total_bytes / $limit_bytes) * 100, 2));
$total_mb = round($total_bytes / (1024 * 1024), 2);
$is_storage_full = ($total_bytes >= $limit_bytes);

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

require_once 'header.php';
?>

<div class="space-y-6 text-sm">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Workspace Column: Storage Metrics & Instructions -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h4 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-hard-drive text-emerald-700 animate-pulse"></i> Website Asset Storage Space
                </h4>

                <div class="space-y-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Storage
                                Occupancy</span>
                            <span class="text-xs font-bold text-slate-600"><?php echo $occupied_pct; ?>%</span>
                        </div>

                        <!-- Premium Interactive Progress Bar -->
                        <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden shadow-inner">
                            <div class="h-full rounded-full transition-all duration-550 <?php echo $is_storage_full ? 'bg-rose-600 animate-pulse' : 'bg-gradient-to-r from-emerald-500 to-teal-700'; ?>"
                                style="width: <?php echo $occupied_pct; ?>%"></div>
                        </div>

                        <div class="flex justify-between text-[11px] text-slate-500 font-semibold mt-2 font-mono">
                            <span><?php echo $total_mb; ?> MB Used</span>
                            <span>1,024 MB Limit (1 GB)</span>
                        </div>
                    </div>

                    <?php if ($is_storage_full): ?>
                        <div class="bg-rose-50 border border-rose-200 p-4 rounded-xl space-y-2 text-rose-800">
                            <p class="text-xs font-bold uppercase tracking-wider flex items-center gap-1">
                                <i class="fa-solid fa-triangle-exclamation"></i> Upload Blocked
                            </p>
                            <p class="text-[11px] leading-relaxed">
                                The collective website images storage cap has been occupied. Please contact
                                <a href="unassigned.php?feature=Storage+Expansion+Request"
                                    class="font-extrabold text-blue-800 hover:underline">Euro Global Consultancy</a>
                                to implement a storage extension for your environment.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="bg-emerald-50/50 border border-emerald-150 p-4 rounded-xl text-emerald-800">
                            <p class="text-xs font-bold uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved"></i> Upload Safe Rules
                            </p>
                            <ul class="text-[11px] list-disc list-inside space-y-1">
                                <li>Max file size allowed: <strong>5.0 MB</strong></li>
                                <li>System crops images to standard size in uniform 4:3 structures on the website</li>
                                <li>Supported formats: JPEG, PNG, GIF, WebP</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Workspace Column: CMS Registry & Controller -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">

                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Gallery CMS Portfolio</h3>
                        <p class="text-xs text-slate-500 font-medium">Add, modify, and delete the dynamic images
                            displayed on the public NVK website</p>
                    </div>

                    <?php if ($is_storage_full): ?>
                        <button disabled
                            class="bg-slate-100 text-slate-400 font-bold text-xs px-4 py-2.5 rounded-xl cursor-not-allowed flex items-center gap-1.5 border border-slate-200 shadow-sm"
                            title="Storage limit exceeded. Contact Euro Global Consultancy.">
                            <i class="fa-solid fa-lock"></i> Add New Image
                        </button>
                    <?php else: ?>
                        <button onclick="openGalleryModal()"
                            class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors shadow flex items-center gap-1.5">
                            <i class="fa-solid fa-image"></i> Add New Image
                        </button>
                    <?php endif; ?>
                </div>

                <!-- CMS Search workspace -->
                <form method="GET" action="" class="flex gap-2 mb-6">
                    <div class="relative flex-grow">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by heading or caption content..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none transition-all">
                    </div>
                    <button type="submit"
                        class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-colors flex items-center gap-1 shadow-sm">
                        Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="manage_gallery.php"
                            class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-4 py-2.5 rounded-xl transition-all flex items-center justify-center">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider font-semibold bg-slate-50/50">
                                <th class="py-3 px-4 rounded-l-xl">Image</th>
                                <th class="py-3 px-4">Heading / Title</th>
                                <th class="py-3 px-4">Size</th>
                                <th class="py-3 px-4">Published Date</th>
                                <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php if (empty($gallery_items)): ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 text-xs">No media assets found
                                        in CMS table registries.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($gallery_items as $item):
                                    $size_kb = round($item['image_size'] / 1024, 2);
                                    $size_label = ($size_kb > 1024) ? round($size_kb / 1024, 2) . ' MB' : $size_kb . ' KB';
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-3.5 px-4">
                                            <div
                                                class="w-16 h-12 rounded-lg border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center">
                                                <img src="<?php echo $item['image_path']; ?>" class="object-cover w-full h-full"
                                                    alt="Preview">
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($item['heading']); ?>
                                            <p class="text-[10px] text-slate-400 font-normal truncate max-w-xs mt-0.5">
                                                <?php echo htmlspecialchars($item['caption']); ?>
                                            </p>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono text-slate-500 font-semibold">
                                            <?php echo $size_label; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 font-semibold">
                                            <?php echo date('d M Y - h:i A', strtotime($item['date_added'])); ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button onclick='populateEditGallery(<?php echo json_encode($item); ?>)'
                                                    class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200 text-xs transition-colors"
                                                    title="Update Media Metadata">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <form method="POST" action="actions.php"
                                                    onsubmit="return confirm('Are you sure you want to delete this media asset? It will instantly disappear from the public website.');"
                                                    class="inline">
                                                    <input type="hidden" name="action" value="delete_gallery_item">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit"
                                                        class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200 text-xs transition-colors"
                                                        title="Remove Asset">
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

                <!-- Paginations -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-5">
                        <p class="text-xs text-slate-500 font-semibold">Page <span
                                class="text-slate-800 font-bold"><?php echo $page; ?></span> of <span
                                class="text-slate-800 font-bold"><?php echo $total_pages; ?></span> pages</p>
                        <div class="flex gap-1 text-xs">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"
                                    class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-bold hover:bg-slate-100">&laquo;
                                    Prev</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"
                                    class="px-3 py-1.5 border font-bold rounded-lg transition-all <?php echo $i == $page ? 'bg-emerald-700 border-emerald-700 text-white' : 'bg-slate-50 border-slate-200 hover:bg-slate-100'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"
                                    class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg font-bold hover:bg-slate-100">Next
                                    &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<!-- Modal Form: Add & Edit CMS Asset Details -->
<div id="gallery-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4 text-xs">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full p-6">
        <div class="flex justify-between items-center mb-2">
            <h4 id="gallery-form-title" class="text-base font-bold text-slate-800">Publish Image to Public Website</h4>
            <button onclick="closeGalleryModal()" class="text-slate-400 hover:text-slate-600"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <p class="text-xs text-slate-500 mb-4">Upload high-resolution media complete with professional titles and
            descriptions.</p>

        <form id="gallery-form" method="POST" action="actions.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" id="gallery-form-action" value="add_gallery_item">
            <input type="hidden" name="id" id="gallery-form-id" value="">

            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Heading / Title *</label>
                <input type="text" id="heading_field" name="heading" required
                    placeholder="e.g. Eid-ul-Fitr Congregation, Vadasery Mosque"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:bg-white transition-all text-xs">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Caption / Descriptive words
                    *</label>
                <textarea id="caption_field" name="caption" required
                    placeholder="Describe the event, date, or members present inside the scene..." rows="3"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:bg-white transition-all text-xs"></textarea>
            </div>

            <!-- Media Input Area -->
            <div class="p-4 border border-dashed border-slate-200 rounded-xl bg-slate-50/50 space-y-3">
                <label class="block text-[10px] font-bold text-slate-500 uppercase">Image Asset File *</label>

                <div class="flex items-center space-x-3">
                    <div class="w-24 h-16 rounded-xl border border-slate-200 overflow-hidden bg-slate-100 flex items-center justify-center relative shadow-inner"
                        id="cms-preview-container">
                        <img id="form-media-preview" src="" class="hidden w-full h-full object-cover absolute inset-0">
                        <div id="form-media-placeholder"
                            class="text-slate-400 flex flex-col items-center justify-center text-center">
                            <i class="fa-solid fa-images text-xl text-slate-300"></i>
                            <span class="text-[9px] font-bold uppercase tracking-widest mt-0.5 scale-90">No Media</span>
                        </div>
                    </div>
                    <div>
                        <input type="file" name="image" id="gallery_file_input" accept="image/*"
                            onchange="previewGalleryImage(event)"
                            class="text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                        <p id="image-requirement-note" class="text-[9px] text-red-500 mt-1">Image limit is 5MB.
                            Standard aspect ratio works best.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <button type="button" onclick="closeGalleryModal()"
                    class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-200">
                    Cancel
                </button>
                <button type="submit" id="gallery-form-submit"
                    class="w-1/2 bg-emerald-700 hover:bg-emerald-800 text-white py-3 rounded-xl font-bold shadow transition-colors">
                    Publish Media
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openGalleryModal() {
        // Reset the form fields completely
        document.getElementById('gallery-form').reset();

        // Set text boundaries for creating a new item
        document.getElementById('gallery-form-title').textContent = "Publish Image to Public Website";
        document.getElementById('gallery-form-action').value = "add_gallery_item";
        document.getElementById('gallery-form-id').value = "";
        document.getElementById('gallery-form-submit').textContent = "Publish Media";
        document.getElementById('gallery_file_input').required = true;
        document.getElementById('image-requirement-note').textContent = "Image limit is 5MB. Standard aspect ratio works best.";

        // Show the "No Media" placeholder text and hide the broken image box
        document.getElementById('form-media-preview').src = "";
        document.getElementById('form-media-preview').classList.add('hidden');
        document.getElementById('form-media-placeholder').classList.remove('hidden');

        // Reveal the popup modal screen
        document.getElementById('gallery-modal').classList.remove('hidden');
    }

    function populateEditGallery(item) {
        // 1. Run the default initialization first
        openGalleryModal();

        // 2. Adjust titles and inputs to handle an Edit operation instead
        document.getElementById('gallery-form-title').textContent = "Modify Gallery Asset";
        document.getElementById('gallery-form-action').value = "edit_gallery_item";
        document.getElementById('gallery-form-id').value = item.id;
        document.getElementById('gallery-form-submit').textContent = "Save Changes";
        document.getElementById('heading_field').value = item.heading;
        document.getElementById('caption_field').value = item.caption;
        document.getElementById('gallery_file_input').required = false;
        document.getElementById('image-requirement-note').textContent = "Leave blank to keep existing published image.";

        // 3. If the item has an existing image path on the disk, display it and hide the text placeholder
        if (item.image_path) {
            document.getElementById('form-media-preview').src = item.image_path;
            document.getElementById('form-media-preview').classList.remove('hidden');
            document.getElementById('form-media-placeholder').classList.add('hidden');
        }
    }
    function previewGalleryImage(event) {
        const input = event.target;
        const preview = document.getElementById('form-media-preview');
        const placeholder = document.getElementById('form-media-placeholder');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function closeGalleryModal() {
        document.getElementById('gallery-modal').classList.add('hidden');
    }
</script>

<?php require_once 'footer.php'; ?>
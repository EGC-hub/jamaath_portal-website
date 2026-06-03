<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch lists
$nikah_list = $db->query("SELECT * FROM nikah_registry ORDER BY date_added DESC, id DESC")->fetchAll();

require_once 'header.php';
?>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Marriage Certificate Registry (Nikah Logs)</h3>
            <p class="text-xs text-slate-500">Official certified wedding registry archives with precise recording timestamps</p>
        </div>
        <button onclick="openNikahModal()" class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-ring"></i> Register New Nikah
        </button>
    </div>

    <!-- Instant Search Workspace bar for Nikah -->
    <div class="relative mb-6">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
            <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" id="search-nikah" onkeyup="filterNikah()" placeholder="Search by Groom Name, Bride Name..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-teal-500 focus:bg-white focus:outline-none transition-all">
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                    <th class="py-3 px-4 font-semibold">Groom Name</th>
                    <th class="py-3 px-4 font-semibold">Bride Name</th>
                    <th class="py-3 px-4 font-semibold">Nikah Date & Time</th>
                    <th class="py-3 px-4 font-semibold">Recorded Timestamp</th>
                    <th class="py-3 px-4 font-semibold">Certificate Details / References</th>
                </tr>
            </thead>
            <tbody id="nikah-table-rows" class="divide-y divide-slate-100 text-sm">
                <?php if (empty($nikah_list)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400 text-xs">No entries archived inside Nikah certified logs.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($nikah_list as $nikah): ?>
                        <tr class="nikah-record-row hover:bg-slate-50/50"
                            data-groom="<?php echo htmlspecialchars(strtolower($nikah['groom_name'])); ?>"
                            data-bride="<?php echo htmlspecialchars(strtolower($nikah['bride_name'])); ?>">
                            <td class="py-4 px-4 font-bold text-slate-800 text-xs">🤵 <?php echo htmlspecialchars($nikah['groom_name']); ?></td>
                            <td class="py-4 px-4 font-bold text-slate-800 text-xs">👰 <?php echo htmlspecialchars($nikah['bride_name']); ?></td>
                            <td class="py-4 px-4 text-xs font-semibold text-teal-800">
                                <span class="bg-teal-50 px-2.5 py-1 rounded-md">
                                    <i class="fa-solid fa-clock mr-1"></i> <?php echo date('d M Y - h:i A', strtotime($nikah['nikah_datetime'])); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 text-xs text-slate-500 font-mono font-semibold"><?php echo date('d M Y - h:i A', strtotime($nikah['date_added'])); ?></td>
                            <td class="py-4 px-4 text-xs font-medium text-slate-600"><?php echo htmlspecialchars($nikah['details']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function filterNikah() {
        const searchVal = document.getElementById('search-nikah').value.trim().toLowerCase();
        const rows = document.querySelectorAll('.nikah-record-row');
        rows.forEach(row => {
            const groom = row.getAttribute('data-groom');
            const bride = row.getAttribute('data-bride');
            if (searchVal === '' || groom.includes(searchVal) || bride.includes(searchVal)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }
</script>

<?php require_once 'footer.php'; ?>
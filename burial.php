<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch lists
$burial_list = $db->query("SELECT * FROM burial_registry ORDER BY date_added DESC, id DESC")->fetchAll();

require_once 'header.php';
?>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Cemetery Plot & Burial Registry</h3>
            <p class="text-xs text-slate-500">Official log tracks mapping the final resting sites inside municipal graveyard locations</p>
        </div>
        <button onclick="openBurialModal()" class="bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-monument"></i> Record New Burial
        </button>
    </div>

    <!-- Instant Search Workspace bar for Burial -->
    <div class="relative mb-6">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
            <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" id="search-burial" onkeyup="filterBurial()" placeholder="Search by Deceased Name or Plot..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-rose-500 focus:bg-white focus:outline-none transition-all">
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                    <th class="py-3 px-4 font-semibold">Deceased Subject (Marhoom)</th>
                    <th class="py-3 px-4 font-semibold">Burial Date & Time</th>
                    <th class="py-3 px-4 font-semibold">Grave Plot Reference Location</th>
                    <th class="py-3 px-4 font-semibold">Recorded Timestamp</th>
                </tr>
            </thead>
            <tbody id="burial-table-rows" class="divide-y divide-slate-100 text-sm">
                <?php if (empty($burial_list)): ?>
                    <tr>
                        <td colspan="4" class="py-12 text-center text-slate-400 text-xs">No entries logged inside Burial plots directory.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($burial_list as $burial): ?>
                        <tr class="burial-record-row hover:bg-slate-50/50"
                            data-deceased="<?php echo htmlspecialchars(strtolower($burial['deceased_name'])); ?>"
                            data-plot="<?php echo htmlspecialchars(strtolower($burial['plot_details'])); ?>">
                            <td class="py-4 px-4 font-bold text-rose-800 text-xs">🕊️ <?php echo htmlspecialchars($burial['deceased_name']); ?></td>
                            <td class="py-4 px-4 text-xs font-semibold text-rose-800">
                                <span class="bg-rose-50 px-2.5 py-1 rounded-md">
                                    <i class="fa-solid fa-calendar-day mr-1"></i> <?php echo date('d M Y - h:i A', strtotime($burial['burial_datetime'])); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 text-xs font-medium text-slate-700">
                                <span class="bg-slate-100 px-2 py-1 rounded-lg">
                                    <i class="fa-solid fa-compass mr-1"></i> <?php echo htmlspecialchars($burial['plot_details']); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 text-xs text-slate-500 font-mono font-semibold"><?php echo date('d M Y - h:i A', strtotime($burial['date_added'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function filterBurial() {
        const searchVal = document.getElementById('search-burial').value.trim().toLowerCase();
        const rows = document.querySelectorAll('.burial-record-row');
        rows.forEach(row => {
            const deceased = row.getAttribute('data-deceased');
            const plot = row.getAttribute('data-plot');
            if (searchVal === '' || deceased.includes(searchVal) || plot.includes(searchVal)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }
</script>

<?php require_once 'footer.php'; ?>
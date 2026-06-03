<?php
require_once 'db.php';
require_once 'helpers.php';

// Calculate dyn stats
$pending_welfare = $db->query("SELECT COUNT(*) FROM welfare WHERE status = 'Pending'")->fetchColumn();
$total_welfare_granted = $db->query("SELECT SUM(amount) FROM welfare WHERE status = 'Approved'")->fetchColumn() ?: 0;
$total_reserves_available = 250000 - $total_welfare_granted;

// Fetch ledger details
$welfare_list = $db->query("SELECT * FROM welfare ORDER BY date_added DESC, id DESC")->fetchAll();

require_once 'header.php';
?>

<!-- Fund Breakdown analysis cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Grants Disbursed</p>
            <h3 class="text-3xl font-extrabold text-teal-800 mt-2">₹<?php echo number_format($total_welfare_granted); ?></h3>
            <p class="text-xs text-slate-500 mt-1">Disbursed community assistance</p>
        </div>
        <div class="bg-teal-50 text-teal-600 p-3 rounded-xl"><i class="fa-solid fa-graduation-cap text-xl"></i></div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Baitul-Mal Reserves</p>
            <h3 class="text-3xl font-extrabold text-indigo-800 mt-2">₹<?php echo number_format($total_reserves_available); ?></h3>
            <p class="text-xs text-slate-500 mt-1">Active collection liquidity</p>
        </div>
        <div class="bg-indigo-50 text-indigo-600 p-3 rounded-xl"><i class="fa-solid fa-heart text-xl"></i></div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Board Audits</p>
            <h3 class="text-3xl font-extrabold text-amber-600 mt-2"><?php echo $pending_welfare; ?></h3>
            <p class="text-xs text-slate-500 mt-1">Applications in verification</p>
        </div>
        <div class="bg-amber-50 text-amber-600 p-3 rounded-xl"><i class="fa-solid fa-clock-rotate-left text-xl"></i></div>
    </div>
</div>

<!-- Baitul-Mal Ledger Data Tables -->
<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm mt-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-lg font-bold text-slate-800">Baitul-Mal Welfare Board Ledger</h3>
            <p class="text-xs text-slate-500">Tracking and allocating financial assistance for registered families</p>
        </div>
        <button onclick="openWelfareModal()" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-circle-plus"></i> Log Aid Application
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                    <th class="py-3 px-4 font-semibold">Applicant Name</th>
                    <th class="py-3 px-4 font-semibold">Support Category</th>
                    <th class="py-3 px-4 font-semibold">Requested Amount</th>
                    <th class="py-3 px-4 font-semibold">Registration Timestamp</th>
                    <th class="py-3 px-4 font-semibold">Verification State</th>
                    <th class="py-3 px-4 font-semibold text-right">Review Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($welfare_list)): ?>
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-400 text-xs">No welfare records filed yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($welfare_list as $welf_case): ?>
                        <tr class="hover:bg-slate-50/50">
                            <td class="py-4 px-4 font-bold text-slate-800"><?php echo htmlspecialchars($welf_case['name']); ?></td>
                            <td class="py-4 px-4 text-xs font-semibold text-slate-600"><?php echo htmlspecialchars($welf_case['type']); ?></td>
                            <td class="py-4 px-4 font-bold text-slate-900">₹<?php echo number_format($welf_case['amount']); ?></td>
                            <td class="py-4 px-4 text-xs text-slate-500 font-mono font-semibold"><?php echo date('d M Y - h:i A', strtotime($welf_case['date_added'])); ?></td>
                            <td class="py-4 px-4">
                                <?php if ($welf_case['status'] === 'Approved'): ?>
                                    <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Approved</span>
                                <?php else: ?>
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <?php if ($welf_case['status'] === 'Pending'): ?>
                                    <form method="POST" action="actions.php">
                                        <input type="hidden" name="action" value="approve_welfare">
                                        <input type="hidden" name="id" value="<?php echo $welf_case['id']; ?>">
                                        <button type="submit" class="bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg hover:bg-emerald-800 transition-all">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 font-semibold"><i class="fa-solid fa-hand-holding-dollar"></i> Disbursed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
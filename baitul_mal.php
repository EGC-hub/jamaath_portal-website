<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch Editable baseline reserve amount from system settings table
$base_reserve_stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'baitulmal_base_reserve'");
$base_reserve = (int) ($base_reserve_stmt->fetchColumn() ?: 250000);

// Fetch dynamic inflows metrics
$total_inflows = $db->query("SELECT SUM(amount) FROM baitulmal_inflows")->fetchColumn() ?: 0;

// Fetch dynamic outflows metrics (Paid / Disbursed only)
$total_outflows_disbursed = $db->query("SELECT SUM(amount) FROM welfare WHERE status = 'Paid'")->fetchColumn() ?: 0;

// Calculate net Baitul-Mal balance dynamically
$total_reserves_available = $base_reserve + $total_inflows - $total_outflows_disbursed;

// General pending board audits counts
$pending_welfare_audits = $db->query("SELECT COUNT(*) FROM welfare WHERE status = 'Pending'")->fetchColumn() ?: 0;

// Fetch both tables for presentation
$outflows_list = $db->query("SELECT * FROM welfare ORDER BY date_added DESC")->fetchAll();
$inflows_list = $db->query("SELECT * FROM baitulmal_inflows ORDER BY date_added DESC")->fetchAll();

require_once 'header.php';
?>

<!-- Dynamic Balance Sheet Header Blocks -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-5">
    <!-- Stat block: Baseline reserves -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between relative group">
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Base Reserve Fund</p>
            <h3 class="text-2xl font-extrabold text-slate-800 mt-2">₹<?php echo formatIndianCurrency($base_reserve); ?></h3>
            <button onclick="openBaseReserveModal(<?php echo $base_reserve; ?>)" class="text-xs text-emerald-700 font-bold hover:underline mt-1.5 flex items-center gap-1">
                <i class="fa-solid fa-pen-to-square"></i> Configure Baseline
            </button>
        </div>
        <div class="bg-slate-50 text-slate-600 p-3 rounded-xl"><i class="fa-solid fa-building-columns text-lg"></i></div>
    </div>

    <!-- Stat block: Inflows ledger -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Received (Inflows)</p>
            <h3 class="text-2xl font-extrabold text-teal-800 mt-2">+ ₹<?php echo formatIndianCurrency($total_inflows); ?></h3>
            <p class="text-xs text-slate-500 mt-1.5">Zakath, Sadaqa & Chanda</p>
        </div>
        <div class="bg-teal-50 text-teal-600 p-3 rounded-xl"><i class="fa-solid fa-circle-arrow-down text-lg"></i></div>
    </div>

    <!-- Stat block: Outflows ledger -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Disbursed (Outflows)</p>
            <h3 class="text-2xl font-extrabold text-rose-800 mt-2">- ₹<?php echo formatIndianCurrency($total_outflows_disbursed); ?></h3>
            <p class="text-xs text-slate-500 mt-1.5"><?php echo $pending_welfare_audits; ?> Applications pending</p>
        </div>
        <div class="bg-rose-50 text-rose-600 p-3 rounded-xl"><i class="fa-solid fa-circle-arrow-up text-lg"></i></div>
    </div>

    <!-- Stat block: Net available Baitul Mal balance -->
    <div class="bg-gradient-to-br from-emerald-800 to-teal-950 p-5 rounded-2xl text-white flex items-center justify-between shadow">
        <div>
            <p class="text-[10px] font-bold text-emerald-300 uppercase tracking-wider">Net Available Reserves</p>
            <h3 class="text-2xl font-black text-white mt-2">₹<?php echo formatIndianCurrency($total_reserves_available); ?></h3>
            <p class="text-xs text-emerald-200 mt-1.5 flex items-center gap-1">
                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-ping"></span> Dynamic Liquidity Scale
            </p>
        </div>
        <div class="bg-white/10 text-emerald-300 p-3 rounded-xl"><i class="fa-solid fa-vault text-lg"></i></div>
    </div>
</div>

<!-- Ledger Section with Outflow vs Inflow Tabbed Views -->
<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm mt-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-4 mb-6 gap-4">
        <!-- Interactive Tab Selectors -->
        <div class="flex space-x-1.5 bg-slate-100 p-1 rounded-xl">
            <button onclick="toggleBaitulmalTab('outflows')" id="tab-btn-outflows" class="text-xs font-bold px-4 py-2 rounded-lg transition-all bg-white text-slate-800 shadow-sm">
                🤝 Welfare Outflows (Disbursements)
            </button>
            <button onclick="toggleBaitulmalTab('inflows')" id="tab-btn-inflows" class="text-xs font-bold px-4 py-2 rounded-lg transition-all text-slate-500 hover:text-slate-800">
                💰 Contribution Inflows (Collections)
            </button>
        </div>
        
        <!-- Tab-specific quick record creation keys -->
        <div>
            <button id="add-outflow-btn" onclick="openWelfareModal()" class="bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-hand-holding-hand"></i> Log Welfare Outflow
            </button>
            <button id="add-inflow-btn" onclick="openInflowModal()" class="hidden bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-cash-register"></i> Log Income Inflow
            </button>
        </div>
    </div>

    <!-- Tab Section A: Outflows Ledger Layout -->
    <div id="section-outflows" class="space-y-4">
        <div>
            <h4 class="text-sm font-bold text-slate-800">Disbursed Outflow Ledger</h4>
            <p class="text-[11px] text-slate-400">Verifies local welfare allocations with embedded image verification links</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3 px-4">Applicant Profile</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4">Requested Amount</th>
                        <th class="py-3 px-4">Payment Proof</th>
                        <th class="py-3 px-4">Status State</th>
                        <th class="py-3 px-4 text-right">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($outflows_list)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No welfare outflows filed yet inside system registries.</td>
                            </tr>
                    <?php else: ?>
                            <?php foreach ($outflows_list as $outflow): ?>
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-3.5 px-4">
                                            <p class="font-bold text-slate-800"><?php echo htmlspecialchars($outflow['name']); ?></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">Filed: <?php echo date('d M Y - h:i A', strtotime($outflow['date_added'])); ?></p>
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-600"><?php echo htmlspecialchars($outflow['type']); ?></td>
                                        <td class="py-3.5 px-4 font-bold text-slate-900">₹<?php echo formatIndianCurrency($outflow['amount']); ?></td>
                                        <td class="py-3.5 px-4">
                                            <?php if (!empty($outflow['proof_of_payment'])): ?>
                                                    <button onclick="viewPaymentProof('<?php echo htmlspecialchars($outflow['name']); ?>', '<?php echo $outflow['proof_of_payment']; ?>')" class="text-emerald-700 hover:underline flex items-center gap-1.5 font-bold">
                                                        <i class="fa-solid fa-receipt"></i> View Proof
                                                    </button>
                                            <?php else: ?>
                                                    <span class="text-slate-400 italic">No proof uploaded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if ($outflow['status'] === 'Paid'): ?>
                                                    <span class="bg-emerald-100 text-emerald-800 text-[9px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider">Paid to Recipient</span>
                                            <?php else: ?>
                                                    <span class="bg-amber-100 text-amber-800 text-[9px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider">Pending Audit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- Trigger Disbursement payment conversion if pending -->
                                                <?php if ($outflow['status'] === 'Pending'): ?>
                                                        <button onclick="triggerWelfarePayment(<?php echo $outflow['id']; ?>, '<?php echo htmlspecialchars($outflow['name']); ?>', <?php echo $outflow['amount']; ?>)" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] px-2.5 py-1.5 rounded-lg flex items-center gap-1.5 transition-colors">
                                                            <i class="fa-solid fa-cash-register"></i> Disburse Cash
                                                        </button>
                                                <?php endif; ?>

                                                <!-- Edit trigger -->
                                                <button onclick="openEditWelfareModal(<?php echo json_encode($outflow); ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-1.5 rounded-lg border border-slate-200 text-xs transition-all" title="Edit Outflow row">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <!-- Delete action -->
                                                <form method="POST" action="actions.php" onsubmit="return confirm('Are you sure you want to delete this outflow request permanently?');" class="inline">
                                                    <input type="hidden" name="action" value="delete_welfare">
                                                    <input type="hidden" name="id" value="<?php echo $outflow['id']; ?>">
                                                    <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 p-1.5 rounded-lg border border-rose-200 text-xs transition-all" title="Delete entry">
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
    </div>

    <!-- Tab Section B: Inflows Contribution Logs Layout -->
    <div id="section-inflows" class="hidden space-y-4">
        <div>
            <h4 class="text-sm font-bold text-slate-800">Received Contribution Ledger</h4>
            <p class="text-[11px] text-slate-400">Ledger details of all funds paid into the reserve</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3 px-4">Contributor / Source</th>
                        <th class="py-3 px-4">Fund Type</th>
                        <th class="py-3 px-4">Deposited Amount</th>
                        <th class="py-3 px-4">Receipt Date</th>
                        <th class="py-3 px-4 text-right">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($inflows_list)): ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">No incoming contributions recorded in ledger files.</td>
                            </tr>
                    <?php else: ?>
                            <?php foreach ($inflows_list as $inflow): ?>
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-3.5 px-4">
                                            <p class="font-bold text-slate-800"><?php echo htmlspecialchars($inflow['donor_name']); ?></p>
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-600">
                                            <span class="bg-emerald-50 text-emerald-800 border border-emerald-100 px-2 py-0.5 rounded font-mono">
                                                <?php echo htmlspecialchars($inflow['type']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-emerald-800">+ ₹<?php echo formatIndianCurrency($inflow['amount']); ?></td>
                                        <td class="py-3.5 px-4 font-mono font-semibold text-slate-500"><?php echo date('d M Y - h:i A', strtotime($inflow['date_added'])); ?></td>
                                        <td class="py-3.5 px-4 text-right font-semibold">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- Edit contribution button -->
                                                <button onclick="openEditInflowModal(<?php echo json_encode($inflow); ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-1.5 rounded-lg border border-slate-200 text-xs transition-all" title="Edit contribution entry">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                        
                                                <!-- Delete contribution form -->
                                                <form method="POST" action="actions.php" onsubmit="return confirm('Are you sure you want to delete this contribution entry permanently? This will adjust system calculations.');" class="inline">
                                                    <input type="hidden" name="action" value="delete_inflow">
                                                    <input type="hidden" name="id" value="<?php echo $inflow['id']; ?>">
                                                    <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 p-1.5 rounded-lg border border-rose-200 text-xs transition-all">
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
    </div>
</div>

<!-- ==============================================
     MODAL LAYOUT CONTAINER SCRIPTS & FORMS
     ============================================== -->

<!-- Modal: Edit Base Reserve amount -->
<div id="base-reserve-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4 animate-fade-in">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full p-6">
        <h4 class="text-base font-bold text-slate-800 mb-2">Configure Base Reserves</h4>
        <p class="text-xs text-slate-500 mb-4 font-medium">Configure the baseline reserve value. Available balance displays this amount combined with active cash flows.</p>
        
        <form method="POST" action="actions.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_base_reserve">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Reserve Amount (₹) *</label>
                <input type="number" name="base_reserve_amount" id="base_reserve_field" required placeholder="e.g. 250000" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div class="flex space-x-2 pt-2">
                <button type="button" onclick="closeBaseReserveModal()" class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold transition-colors">Cancel</button>
                <button type="submit" class="w-1/2 bg-emerald-700 hover:bg-emerald-800 text-white py-3 rounded-xl font-bold shadow-sm transition-colors">Apply Config</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Log contribution inflows -->
<div id="inflow-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6">
        <h4 id="inflow-modal-title" class="text-base font-bold text-slate-800 mb-2">Log Collection Inflow</h4>
        <p class="text-xs text-slate-500 mb-4">Record incoming funds directly into the Baitul-Mal reserve accounts.</p>

        <form id="inflow-modal-form" method="POST" action="actions.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" id="inflow-form-action" value="add_inflow">
            <input type="hidden" name="id" id="inflow-form-id" value="">

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Donor / Contribution Source *</label>
                <input type="text" name="donor_name" id="inflow-field-donor" required placeholder="Full Name or Group (e.g., General Sadaqa)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Deposit Type *</label>
                    <select name="type" id="inflow-field-type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="Chanda">Chanda Collections</option>
                        <option value="Sadaqa">Sadaqa Aid</option>
                        <option value="Zakath Fund">Zakath Fund</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Deposited Amount (₹) *</label>
                    <input type="number" name="amount" id="inflow-field-amount" required placeholder="Amount" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div class="flex space-x-2 pt-2">
                <button type="button" onclick="closeInflowModal()" class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold">Cancel</button>
                <button type="submit" id="inflow-submit-btn" class="w-1/2 bg-emerald-700 hover:bg-emerald-800 text-white py-3 rounded-xl font-bold shadow">Save Inflow</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create/Edit Welfare Request (Outflow) -->
<div id="outflow-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6">
        <h4 id="outflow-modal-title" class="text-base font-bold text-slate-800 mb-2">Log Welfare Aid Outflow</h4>
        <p class="text-xs text-slate-500 mb-4">Request assistance from Baitul-Mal on behalf of an active family.</p>

        <form id="outflow-modal-form" method="POST" action="actions.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" id="outflow-form-action" value="add_welfare">
            <input type="hidden" name="id" id="outflow-form-id" value="">

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Recipient Name *</label>
                <input type="text" name="name" id="outflow-field-name" required placeholder="Applicant Full Name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Support Category *</label>
                    <select name="type" id="outflow-field-type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                        <option value="Higher Education Aid">Higher Education Aid</option>
                        <option value="Marriage Assistance">Marriage Assistance</option>
                        <option value="Medical Aid">Medical Aid</option>
                        <option value="Widow Monthly Support">Widow Monthly Support</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Requested Amount (₹) *</label>
                    <input type="number" name="amount" id="outflow-field-amount" required placeholder="e.g. 15000" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
            </div>
            <div class="flex space-x-2 pt-2">
                <button type="button" onclick="closeWelfareModal()" class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold">Cancel</button>
                <button type="submit" id="outflow-submit-btn" class="w-1/2 bg-rose-700 hover:bg-rose-800 text-white py-3 rounded-xl font-bold shadow-sm">File Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Verified Proof of Disbursement upload -->
<div id="payment-proof-upload-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-sm w-full p-6 text-xs text-slate-700">
        <h4 class="text-base font-bold text-slate-800 mb-1">Disburse Aid & Upload Proof</h4>
        <p class="text-xs text-slate-500 mb-4">Complete dynamic cash payout. Record recipient signature or bank transaction proof.</p>

        <form method="POST" action="actions.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="pay_welfare">
            <input type="hidden" name="id" id="disburse-form-id" value="">
            
            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-1">
                <p class="text-slate-500 font-medium">Disbursing aid to:</p>
                <p id="disburse-recipient" class="font-bold text-slate-800 text-sm">---</p>
                <p id="disburse-amount" class="text-rose-700 font-extrabold mt-0.5 font-mono text-sm">₹0.00</p>
            </div>

            <div class="flex flex-col items-center justify-center p-3.5 border border-dashed border-slate-200 rounded-xl bg-slate-50">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Upload Proof Receipt / Photo</label>
                <div class="relative w-28 h-20 bg-slate-100 rounded-lg overflow-hidden mb-2 border border-slate-200 flex items-center justify-center">
                    <img id="disburse-proof-preview" src="data:image/svg+xml;utf8,<svg xmlns='[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23f1f5f9'/><text x='50%' y='50%' font-size='30' text-anchor='middle' alignment-baseline='middle'>📸</text></svg>" class="object-cover w-full h-full" alt="Preview">
                </div>
                <input type="file" name="proof_photo" id="disburse-proof-input" accept="image/*" onchange="handleProofPhotoChange(event)" class="hidden" required>
                <button type="button" onclick="document.getElementById('disburse-proof-input').click()" class="bg-white border border-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-bold hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-camera mr-1"></i> Snap / Upload
                </button>
            </div>

            <div class="flex space-x-2 pt-2">
                <button type="button" onclick="closeDisburseModal()" class="w-1/2 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold">Cancel</button>
                <button type="submit" class="w-1/2 bg-emerald-700 hover:bg-emerald-800 text-white py-3 rounded-xl font-bold shadow-sm">Confirm Disbursement</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Payment proof zoom presentation modal -->
<div id="payment-proof-view-modal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden flex flex-col">
        <div class="bg-gradient-to-r from-emerald-800 to-teal-950 p-4 text-white flex justify-between items-center">
            <h4 id="proof-viewer-title" class="font-bold text-sm">Disbursement Receipt Proof</h4>
            <button onclick="closePaymentProofView()" class="text-white/80 hover:text-white"><i class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <div class="p-6 flex justify-center bg-slate-50 items-center min-h-[250px]">
            <img id="proof-viewer-img" src="" class="max-w-full max-h-[400px] rounded-xl shadow-md border border-slate-200 object-contain" alt="Disbursement proof">
        </div>
        <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 text-center">
            <button onclick="closePaymentProofView()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs px-5 py-2.5 rounded-xl transition-all">
                Close Viewer
            </button>
        </div>
    </div>
</div>

<!-- ==============================================
     BAITUL-MAL UI INTERACTIVE JAVASCRIPT LOGIC
     ============================================== -->
<script>
    // Tab controller logic
    function toggleBaitulmalTab(tabName) {
        const sectOut = document.getElementById('section-outflows');
        const sectIn = document.getElementById('section-inflows');
        const tabOut = document.getElementById('tab-btn-outflows');
        const tabIn = document.getElementById('tab-btn-inflows');
        
        const outBtn = document.getElementById('add-outflow-btn');
        const inBtn = document.getElementById('add-inflow-btn');

        if (tabName === 'outflows') {
            sectOut.classList.remove('hidden');
            sectIn.classList.add('hidden');
            
            tabOut.className = "text-xs font-bold px-4 py-2 rounded-lg transition-all bg-white text-slate-800 shadow-sm";
            tabIn.className = "text-xs font-bold px-4 py-2 rounded-lg transition-all text-slate-500 hover:text-slate-800";
            
            outBtn.classList.remove('hidden');
            inBtn.classList.add('hidden');
        } else {
            sectOut.classList.add('hidden');
            sectIn.classList.remove('hidden');
            
            tabIn.className = "text-xs font-bold px-4 py-2 rounded-lg transition-all bg-white text-slate-800 shadow-sm";
            tabOut.className = "text-xs font-bold px-4 py-2 rounded-lg transition-all text-slate-500 hover:text-slate-800";
            
            outBtn.classList.add('hidden');
            inBtn.classList.remove('hidden');
        }
    }

    // Modal base reserve configs
    function openBaseReserveModal(currentVal) {
        document.getElementById('base_reserve_field').value = currentVal;
        document.getElementById('base-reserve-modal').classList.remove('hidden');
    }
    
    function closeBaseReserveModal() {
        document.getElementById('base-reserve-modal').classList.add('hidden');
    }

    // Modal Inflows controller
    function openInflowModal() {
        document.getElementById('inflow-form-action').value = "add_inflow";
        document.getElementById('inflow-form-id').value = "";
        document.getElementById('inflow-modal-title').textContent = "Log Collection Inflow";
        document.getElementById('inflow-submit-btn').textContent = "Save Inflow";
        
        document.getElementById('inflow-field-donor').value = "";
        document.getElementById('inflow-field-type').value = "Chanda";
        document.getElementById('inflow-field-amount').value = "";
        
        document.getElementById('inflow-modal').classList.remove('hidden');
    }

    function openEditInflowModal(inflowData) {
        document.getElementById('inflow-form-action').value = "edit_inflow";
        document.getElementById('inflow-form-id').value = inflowData.id;
        document.getElementById('inflow-modal-title').textContent = "Edit Contribution Inflow";
        document.getElementById('inflow-submit-btn').textContent = "Save Changes";
        
        document.getElementById('inflow-field-donor').value = inflowData.donor_name;
        document.getElementById('inflow-field-type').value = inflowData.type;
        document.getElementById('inflow-field-amount').value = inflowData.amount;
        
        document.getElementById('inflow-modal').classList.remove('hidden');
        toggleBaitulmalTab('inflows'); // highlight correct tab
    }
    
    function closeInflowModal() {
        document.getElementById('inflow-modal').classList.add('hidden');
    }

    // Modal Outflows controller
    function openWelfareModal() {
        document.getElementById('outflow-form-action').value = "add_welfare";
        document.getElementById('outflow-form-id').value = "";
        document.getElementById('outflow-modal-title').textContent = "Log Welfare Outflow";
        document.getElementById('outflow-submit-btn').textContent = "File Request";

        document.getElementById('outflow-field-name').value = "";
        document.getElementById('outflow-field-type').value = "Higher Education Aid";
        document.getElementById('outflow-field-amount').value = "";

        document.getElementById('outflow-modal').classList.remove('hidden');
    }

    function openEditWelfareModal(welfareData) {
        document.getElementById('outflow-form-action').value = "edit_welfare";
        document.getElementById('outflow-form-id').value = welfareData.id;
        document.getElementById('outflow-modal-title').textContent = "Edit Outflow Request";
        document.getElementById('outflow-submit-btn').textContent = "Save Changes";

        document.getElementById('outflow-field-name').value = welfareData.name;
        document.getElementById('outflow-field-type').value = welfareData.type;
        document.getElementById('outflow-field-amount').value = welfareData.amount;

        document.getElementById('outflow-modal').classList.remove('hidden');
        toggleBaitulmalTab('outflows');
    }

    function closeWelfareModal() {
        document.getElementById('outflow-modal').classList.add('hidden');
    }

    // Proof disbursement modal workflows
    function triggerWelfarePayment(id, recipientName, amount) {
        document.getElementById('disburse-form-id').value = id;
        document.getElementById('disburse-recipient').textContent = recipientName;
        document.getElementById('disburse-amount').textContent = "₹" + parseInt(amount).toLocaleString('en-IN');
        document.getElementById('disburse-proof-preview').src = "data:image/svg+xml;utf8,<svg xmlns='[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23f1f5f9'/><text x='50%' y='50%' font-size='30' text-anchor='middle' alignment-baseline='middle'>📸</text></svg>";
        document.getElementById('disburse-proof-input').value = "";
        
        document.getElementById('payment-proof-upload-modal').classList.remove('hidden');
    }

    function closeDisburseModal() {
        document.getElementById('payment-proof-upload-modal').classList.add('hidden');
    }

    function handleProofPhotoChange(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('disburse-proof-preview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    // Payment proof full presentation logic
    function viewPaymentProof(recipientName, imgBase64) {
        document.getElementById('proof-viewer-title').textContent = "Payment Receipt Verification: " + recipientName;
        document.getElementById('proof-viewer-img').src = imgBase64;
        document.getElementById('payment-proof-view-modal').classList.remove('hidden');
    }
    
    function closePaymentProofView() {
        document.getElementById('payment-proof-view-modal').classList.add('hidden');
    }
</script>

<?php require_once 'footer.php'; ?>
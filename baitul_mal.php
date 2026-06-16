<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch Editable baseline reserve amount from settings
$base_reserve_stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'baitulmal_base_reserve'");
$base_reserve = (int) ($base_reserve_stmt->fetchColumn() ?: 300000);

// Fetch metrics
$total_inflows = $db->query("SELECT SUM(amount) FROM baitulmal_inflows")->fetchColumn() ?: 0;
$total_outflows_disbursed = $db->query("SELECT SUM(amount) FROM welfare WHERE status = 'Paid'")->fetchColumn() ?: 0;
$total_reserves_available = $base_reserve + $total_inflows - $total_outflows_disbursed;

// Arrays for visual render
$inflows_list = $db->query("SELECT * FROM baitulmal_inflows ORDER BY date_added DESC")->fetchAll();
$outflows_list = $db->query("SELECT * FROM welfare ORDER BY date_added DESC")->fetchAll();
$applications_list = $db->query("SELECT * FROM baitulmal_applications ORDER BY date_added DESC")->fetchAll();

$pending_welfare = $db->query("SELECT COUNT(*) FROM welfare WHERE status = 'Pending'")->fetchColumn();

// FETCH LIVE MEMBERS LIST MATCHING VERIFIED PRODUCTION SCHEMA
$members_list = $db->query("SELECT id, first_name, last_name, father_husband_name, res_address_line1, res_address_line2, res_city, res_pincode, phone, photo FROM members WHERE status = 'Alive' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- 4 TOP CARDS GRID - RESTORED TO MATCH THE STYLING OF image_bbc7b2.png -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">

        <!-- CARD 1: BASE RESERVE FUND -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Base Reserve Fund</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1">₹<?php echo number_format($base_reserve); ?></h3>
                <button onclick="openBaselineModal()"
                    class="text-xs text-emerald-600 hover:text-emerald-700 font-semibold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-pen-to-square"></i> Configure Baseline
                </button>
            </div>
            <div class="bg-slate-50 text-slate-500 p-3.5 rounded-xl border border-slate-100">
                <i class="fa-solid fa-landmark text-lg"></i>
            </div>
        </div>

        <!-- CARD 2: TOTAL RECEIVED (INFLOWS) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Received (Inflows)</p>
                <h3 class="text-2xl font-black text-emerald-600 mt-1">+ ₹<?php echo number_format($total_inflows); ?>
                </h3>
                <p class="text-xs text-slate-400 mt-1">Zakath, Sadaqa & Chanda</p>
            </div>
            <div class="bg-emerald-50 text-emerald-600 p-3.5 rounded-xl">
                <i class="fa-solid fa-circle-arrow-down text-lg"></i>
            </div>
        </div>

        <!-- CARD 3: TOTAL DISBURSED (OUTFLOWS) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Disbursed (Outflows)</p>
                <h3 class="text-2xl font-black text-rose-600 mt-1">-
                    ₹<?php echo number_format($total_outflows_disbursed); ?></h3>
                <p class="text-xs text-slate-400 mt-1"><?php echo $pending_welfare; ?> Applications pending</p>
            </div>
            <div class="bg-rose-50 text-rose-600 p-3.5 rounded-xl">
                <i class="fa-solid fa-circle-arrow-up text-lg"></i>
            </div>
        </div>

        <!-- CARD 4: NET AVAILABLE RESERVES (STRIKING DARK GREEN MATCHING image_bbc7b2.png) -->
        <div
            class="bg-gradient-to-br from-emerald-900 to-teal-950 text-white p-5 rounded-2xl shadow-md flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">Net Available Reserves</p>
                <h3 class="text-2xl font-black text-white mt-1">₹<?php echo number_format($total_reserves_available); ?>
                </h3>
                <p class="text-xs text-emerald-300 mt-1">Dynamic Liquidity Scale</p>
            </div>
            <div class="bg-emerald-800/40 text-emerald-300 p-3.5 rounded-xl">
                <i class="fa-solid fa-vault text-lg"></i>
            </div>
        </div>
    </div>

    <!-- MAIN INTERACTIVE LEDGER CONTAINER MATCHING image_bbc7b2.png -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">

        <!-- Tab Bar and Actions Area -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-4">

            <!-- CAPSULE TABS BAR MATCHING image_bbc7b2.png -->
            <div class="bg-slate-100 p-1 rounded-xl flex flex-wrap gap-1 w-fit">
                <button onclick="switchTab('tab-outflows')" id="btn-tab-outflows"
                    class="tab-btn px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 focus:outline-none">
                    🤝 Welfare Outflows (Disbursements)
                </button>
                <button onclick="switchTab('tab-inflows')" id="btn-tab-inflows"
                    class="tab-btn px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 focus:outline-none">
                    💰 Contribution Inflows (Collections)
                </button>
                <button onclick="switchTab('tab-applications')" id="btn-tab-applications"
                    class="tab-btn px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 focus:outline-none">
                    📋 Aid Applications Queue
                </button>
            </div>

            <!-- ACTION BUTTON REGISTRY MATCHING RED BURGUNDY BRANDING -->
            <div class="flex items-center gap-2">
                <!-- Outflow tab button triggers application popup -->
                <!-- <button onclick="openApplicationModal()" id="btn-action-outflows"
                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-money-bill-transfer"></i> Log Welfare Outflow
                </button> -->
                <!-- Inflow tab button triggers donation popup -->
                <button onclick="openInflowModal()" id="btn-action-inflows"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer hidden">
                    <i class="fa-solid fa-circle-plus"></i> Log Contribution Inflow
                </button>
                <!-- Applications tab button -->
                <button onclick="openApplicationModal()" id="btn-action-applications"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer hidden">
                    <i class="fa-solid fa-file-signature"></i> File Aid Application
                </button>
            </div>
        </div>

        <!-- ==================== TAB CONTENT: OUTFLOWS ==================== -->
        <div id="tab-outflows" class="tab-content hidden">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-slate-800">Disbursed Outflow Ledger</h3>
                <p class="text-xs text-slate-400 mt-0.5">Verifies local welfare allocations with embedded image
                    verification links</p>
            </div>

            <?php if (empty($outflows_list)): ?>
                <div class="text-center py-12 text-slate-400 text-xs border border-dashed border-slate-150 rounded-xl">
                    No welfare outflows filed yet inside system registries.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                                <th class="py-3 px-4">Applicant Profile</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Requested Amount</th>
                                <th class="py-3 px-4">Payment Proof</th>
                                <th class="py-3 px-4">Status State</th>
                                <th class="py-3 px-4 text-right">Administrative Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php foreach ($outflows_list as $outflow): ?>
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-800">
                                        <?php echo htmlspecialchars($outflow['name']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500"><?php echo htmlspecialchars($outflow['type']); ?></td>
                                    <td class="py-3 px-4 font-black text-rose-600">
                                        ₹<?php echo number_format($outflow['amount']); ?></td>
                                    <td class="py-3 px-4">
                                        <?php if (!empty($outflow['proof_of_payment'])): ?>
                                            <button
                                                onclick="viewPaymentProof('<?php echo htmlspecialchars($outflow['name']); ?>', '<?php echo $outflow['proof_of_payment']; ?>')"
                                                class="bg-slate-50 hover:bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded-lg border border-slate-200 cursor-pointer">
                                                <i class="fa-solid fa-receipt"></i> View Proof
                                            </button>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[10px]">No document attached</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($outflow['status'] === 'Paid'): ?>
                                            <span
                                                class="bg-emerald-50 text-emerald-800 text-[10px] font-extrabold px-2.5 py-1 rounded-lg flex items-center gap-1 w-fit"><i
                                                    class="fa-solid fa-circle-check"></i> Disbursed</span>
                                        <?php else: ?>
                                            <span
                                                class="bg-amber-50 text-amber-800 text-[10px] font-extrabold px-2.5 py-1 rounded-lg flex items-center gap-1 w-fit"><i
                                                    class="fa-solid fa-clock"></i> Pending Await</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <?php if ($outflow['status'] !== 'Paid'): ?>
                                            <button onclick="openDisburseModal(<?php echo $outflow['id']; ?>)"
                                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] px-3 py-1.5 rounded-lg shadow-sm transition-all cursor-pointer">
                                                <i class="fa-solid fa-upload"></i> Complete Pay
                                            </button>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[10px]"><i class="fa-solid fa-lock"></i> Settle
                                                Confirmed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== TAB CONTENT: INFLOWS ==================== -->
        <div id="tab-inflows" class="tab-content hidden">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-slate-800">Contribution Inflows (Collections)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Summary ledger tracking incoming Sadaqah, Zakat, and Fitrah
                    collections</p>
            </div>

            <?php if (empty($inflows_list)): ?>
                <div class="text-center py-12 text-slate-400 text-xs border border-dashed border-slate-150 rounded-xl">
                    No contribution collection records logged yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                                <th class="py-3 px-4">Donor Label</th>
                                <th class="py-3 px-4">Donation Type</th>
                                <th class="py-3 px-4">Inward Amount</th>
                                <th class="py-3 px-4">Receipt Date</th>
                                <th class="py-3 px-4 text-right">Administrative Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php foreach ($inflows_list as $inflow): ?>
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-800">
                                        <?php echo htmlspecialchars($inflow['donor_name']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500"><?php echo htmlspecialchars($inflow['type']); ?></td>
                                    <td class="py-3 px-4 font-black text-teal-600">
                                        ₹<?php echo number_format($inflow['amount']); ?></td>
                                    <td class="py-3 px-4 text-slate-500 font-mono">
                                        <?php echo date('d M Y - h:i A', strtotime($inflow['date_added'])); ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button onclick='populateEditInflow(<?php echo json_encode($inflow); ?>)'
                                                class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <form action="actions.php" method="POST" class="inline"
                                                onsubmit="return confirm('Wipe this donation inward line?');">
                                                <input type="hidden" name="action" value="delete_inflow">
                                                <input type="hidden" name="id" value="<?php echo $inflow['id']; ?>">
                                                <button type="submit"
                                                    class="bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 p-1.5 rounded-lg border border-slate-200 hover:border-rose-200">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== TAB CONTENT: APPLICATIONS ==================== -->
        <div id="tab-applications" class="tab-content hidden">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-slate-800">Welfare Petitions Registry Queue</h3>
                <p class="text-xs text-slate-400 mt-0.5">Review incoming applications before generating corresponding
                    outflow ledger tracks</p>
            </div>

            <?php if (empty($applications_list)): ?>
                <div class="text-center py-12 text-slate-400 text-xs border border-dashed border-slate-150 rounded-xl">
                    No welfare aid applications logged in the registry system.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                                <th class="py-3 px-4">Applicant Particulars</th>
                                <th class="py-3 px-4">Aid Type</th>
                                <th class="py-3 px-4">Requested Amount</th>
                                <th class="py-3 px-4">Payment Parameters</th>
                                <th class="py-3 px-4">Status State</th>
                                <th class="py-3 px-4 text-right">Administrative Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php foreach ($applications_list as $app): ?>
                                <tr>
                                    <td class="py-3 px-4 flex items-center gap-2.5">
                                        <?php if (!empty($app['photo'])): ?>
                                            <img src="<?php echo (strpos($app['photo'], 'data:image') === 0 || file_exists($app['photo'])) ? $app['photo'] : 'uploads/welfare/photos/' . $app['photo']; ?>"
                                                class="w-8 h-8 rounded-full object-cover border shadow-xs">
                                        <?php else: ?>
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-[9px] text-slate-400 font-bold border">
                                                No Photo</div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-bold text-slate-800">
                                                <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                            </p>
                                            <p class="text-[10px] text-slate-400">Guardian:
                                                <?php echo htmlspecialchars($app['father_husband_name']); ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500"><?php echo htmlspecialchars($app['type']); ?></td>
                                    <td class="py-3 px-4 font-bold text-slate-900">₹<?php echo number_format($app['amount']); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="font-semibold text-slate-700">
                                            <?php echo htmlspecialchars($app['mode_of_payment']); ?>
                                        </p>
                                        <p class="text-[10px] text-slate-400">
                                            <?php echo $app['date_of_payment'] ? date('d M Y', strtotime($app['date_of_payment'])) : 'Immediate'; ?>
                                        </p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($app['status'] === 'Pending'): ?>
                                            <span
                                                class="bg-amber-50 text-amber-700 text-[10px] font-extrabold px-2.5 py-1 rounded-lg">Pending
                                                Review</span>
                                        <?php elseif ($app['status'] === 'Accepted'): ?>
                                            <span
                                                class="bg-emerald-50 text-emerald-700 text-[10px] font-extrabold px-2.5 py-1 rounded-lg">Approved</span>
                                        <?php else: ?>
                                            <span
                                                class="bg-rose-50 text-rose-700 text-[10px] font-extrabold px-2.5 py-1 rounded-lg">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button onclick='viewApplicationDetails(<?php echo json_encode($app); ?>)'
                                                class="bg-slate-50 hover:bg-slate-100 text-slate-600 p-1.5 rounded-lg border border-slate-200"
                                                title="View Profile Dossier">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            <?php if ($app['status'] === 'Pending'): ?>
                                                <form action="actions.php" method="POST" class="inline"
                                                    onsubmit="return confirm('Approve request and push to Outflows?');">
                                                    <input type="hidden" name="action" value="accept_application">
                                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                    <button type="submit"
                                                        class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 p-1.5 rounded-lg border border-emerald-200"
                                                        title="Accept / Push to Outflow">
                                                        <i class="fa-solid fa-circle-check"></i>
                                                    </button>
                                                </form>
                                                <form action="actions.php" method="POST" class="inline"
                                                    onsubmit="return confirm('Reject this welfare petition?');">
                                                    <input type="hidden" name="action" value="reject_application">
                                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                    <button type="submit"
                                                        class="bg-rose-50 hover:bg-rose-100 text-rose-800 p-1.5 rounded-lg border border-rose-200"
                                                        title="Reject Request">
                                                        <i class="fa-solid fa-circle-xmark"></i>
                                                    </button>
                                                </form>

                                                <button onclick='populateEditApplication(<?php echo json_encode($app); ?>)'
                                                    class="bg-teal-50 hover:bg-teal-100 text-teal-800 p-1.5 rounded-lg border border-teal-200"
                                                    title="Edit Parameters">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <form action="actions.php" method="POST" class="inline"
                                                    onsubmit="return confirm('Permanently wipe this application file?');">
                                                    <input type="hidden" name="action" value="delete_application">
                                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                                    <button type="submit"
                                                        class="bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 p-1.5 rounded-lg border border-slate-200 hover:border-rose-200"
                                                        title="Delete File">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!--                             SYSTEM MODALS MAP                             -->
<!-- ========================================================================= -->

<!-- MODAL: CONFIGURE BASELINE MODAL -->
<div id="baseline-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-800"><i class="fa-solid fa-sliders text-emerald-600"></i> Configure
                Baseline Reserves</h3>
            <button onclick="closeBaselineModal()" class="text-slate-400 hover:text-slate-600"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <form action="actions.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_baseline">
            <div>
                <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-2">Baseline Fund
                    Value (₹) *</label>
                <input type="number" name="baseline_amount" value="<?php echo $base_reserve; ?>" required
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <p class="text-[9px] text-slate-400 mt-1.5">Alters the baseline starting reserve value dynamically used
                    to calculate available ledger balances.</p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeBaselineModal()"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs px-4 py-2 rounded-lg transition-all">Cancel</button>
                <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-lg transition-all shadow-sm">Save
                    Config</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: AID APPLICATION FILING DIALOG -->
<div id="application-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div
        class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 id="app-modal-title" class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-blue-600"></i> File Welfare Aid Application
            </h3>
            <button onclick="closeApplicationModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>

        <form id="app-form" action="actions.php" method="POST" enctype="multipart/form-data"
            class="flex-grow overflow-y-auto p-6 space-y-4">
            <input type="hidden" name="action" id="app-form-action" value="add_application">
            <input type="hidden" name="id" id="app-form-id" value="">

            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-center justify-between">
                <div>
                    <h4 class="text-xs font-bold text-slate-700">Is the applicant a registered Jamaath Member?</h4>
                    <p class="text-[10px] text-slate-400">If active, user variables will auto-populate natively from
                        active folders.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_member" id="app_is_member" value="1"
                        onchange="toggleMemberField(this.checked)" class="sr-only peer">
                    <div
                        class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600">
                    </div>
                </label>
            </div>

            <!-- Dynamic Selector -->
            <div id="member-select-block" class="hidden">
                <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Select Jamaath
                    Member Profile *</label>
                <select id="app_member_id" name="member_id" onchange="autofillMemberData(this.value)"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                    <option value="" disabled selected>-- Scan Verified Member Directory --</option>
                    <?php foreach ($members_list as $m): ?>
                        <option value="<?php echo $m['id']; ?>">
                            <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?> (S/o:
                            <?php echo htmlspecialchars($m['father_husband_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">First Name
                        *</label>
                    <input type="text" id="app_first_name" name="first_name" required placeholder="First name"
                        class="app-field w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Last Name
                        *</label>
                    <input type="text" id="app_last_name" name="last_name" required placeholder="Last name"
                        class="app-field w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Father /
                        Husband Name *</label>
                    <input type="text" id="app_father_husband_name" name="father_husband_name" required
                        placeholder="Guardian name"
                        class="app-field w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 space-y-3">
                <span
                    class="text-[10px] font-bold uppercase tracking-widest text-slate-400 block border-b pb-1.5">Residential
                    Address Profile Details</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[9px] uppercase font-bold text-slate-400 mb-1">Address Line 1 *</label>
                        <input type="text" id="app_res_line1" name="res_address_line1" required
                            placeholder="Door / Street Block Details"
                            class="app-field w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] uppercase font-bold text-slate-400 mb-1">Address Line 2</label>
                        <input type="text" id="app_res_line2" name="res_address_line2"
                            placeholder="Locality / Landmark details"
                            class="app-field w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[9px] uppercase font-bold text-slate-400 mb-1">City / Town *</label>
                        <input type="text" id="app_res_city" name="res_city" required placeholder="e.g. Nagercoil"
                            class="app-field w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] uppercase font-bold text-slate-400 mb-1">Postal Pincode *</label>
                        <input type="text" id="app_res_pincode" name="res_pincode" required placeholder="e.g. 629001"
                            class="app-field w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Contact
                        Number *</label>
                    <input type="tel" id="app_phone" name="phone" required placeholder="Primary phone field"
                        class="app-field w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Welfare
                        Type / Reason *</label>
                    <select id="app_type" name="type" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="" disabled selected>-- Choose Classification --</option>
                        <option value="Educational Support">Educational Support</option>
                        <option value="Medical Emergencies">Medical Emergencies</option>
                        <option value="Marriage Fund Help">Marriage Fund Help</option>
                        <option value="Widow Pension Aid">Widow Pension Aid</option>
                        <option value="Monthly Grocery Help">Monthly Grocery Help</option>
                        <option value="General Poverty relief">General Poverty relief</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Requested
                        Budget Amount *</label>
                    <input type="number" id="app_amount" name="amount" required placeholder="INR Currency Amount"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Mode of
                        Payment *</label>
                    <select id="app_mode" name="mode_of_payment" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Target
                        Allocation Date</label>
                    <input type="date" id="app_date" name="date_of_payment"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider">Applicant Profile
                        Photo</label>

                    <div id="edit_photo_preview_block"
                        class="hidden items-center gap-2.5 bg-white p-2 rounded-xl border border-slate-100">
                        <img id="edit_photo_preview_img" src=""
                            class="w-20 h-20 rounded-full object-cover border shrink-0">
                        <span class="text-[10px] text-slate-400 font-medium italic self-center leading-none">Current
                            file kept on record</span>
                    </div>

                    <input type="file" name="photo" id="app_photo_input" accept="image/*"
                        onchange="previewLocalFile(this, 'edit_photo_preview_img', 'edit_photo_preview_block')"
                        class="text-xs text-slate-500 w-full">
                    <p class="text-[9px] text-slate-400 mt-1">Image uploads only (PNG, JPG, JPEG).</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider">ID Verification
                        File *</label>

                    <div id="edit_id_card_preview_block"
                        class="hidden items-center gap-2 bg-white p-2 rounded-xl border border-slate-100">
                        <i class="fa-solid fa-file-shield text-blue-600 text-sm"></i>
                        <a id="edit_id_card_preview_link" href="javascript:void(0);" onclick="handleIdPreviewClick()"
                            class="text-[10px] text-blue-600 font-bold hover:underline">View Current ID Document</a>
                    </div>

                    <input type="file" name="id_card" id="id_card_input" accept="image/*,application/pdf"
                        onchange="previewLocalIdCard(this, 'edit_id_card_preview_link', 'edit_id_card_preview_block')"
                        class="text-xs text-slate-500 w-full">
                    <p class="text-[9px] text-slate-400 mt-1">Required for verification processing (PDF or Image).</p>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4 flex justify-end gap-2">
                <button type="button" onclick="closeApplicationModal()"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs px-5 py-2.5 rounded-xl transition-all">Cancel</button>
                <button type="submit" id="app-submit-btn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all">Submit
                    Application</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: DETAIL REVIEW MODAL DOSSIER -->
<div id="view-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div
        class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-800"><i class="fa-solid fa-address-card text-emerald-600"></i>
                Review Applicant Profile</h3>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <div class="flex-grow overflow-y-auto p-6 space-y-5">
            <div class="flex items-center gap-4 border-b border-slate-100 pb-4">
                <img id="view_photo" src="" class="w-16 h-16 rounded-full object-cover border shadow-inner">
                <div>
                    <h4 id="view_name" class="text-base font-black text-slate-800"></h4>
                    <p id="view_father_name" class="text-xs text-slate-400 mt-0.5"></p>
                    <span id="view_is_member_badge" class="mt-2 block w-fit"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Mobile Number</p>
                    <p id="view_phone" class="text-slate-800 font-semibold mt-0.5"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Category</p>
                    <p id="view_type" class="text-slate-800 font-semibold mt-0.5"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Payment Channel</p>
                    <p id="view_mode" class="text-slate-800 font-semibold mt-0.5"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Target Payout Date</p>
                    <p id="view_date" class="text-slate-800 font-semibold mt-0.5"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Requested Budget</p>
                    <p id="view_amount" class="text-emerald-700 font-bold text-sm mt-0.5"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Application Status</p>
                    <p id="view_status" class="font-bold text-xs mt-0.5"></p>
                </div>
            </div>

            <div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Residential Address Dossier</p>
                <p id="view_address"
                    class="text-xs text-slate-600 mt-1 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
                </p>
            </div>

            <div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-2">Identification
                    Verification Document</p>
                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100 flex items-center justify-center">
                    <img id="view_id_card" src="" class="max-h-48 object-contain rounded-lg border">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: DISBURSEMENT VOUCHER PROOF UPLOAD -->
<div id="disburse-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-800"><i class="fa-solid fa-money-bill-wave text-emerald-600"></i>
                Audit Payment Disbursement</h3>
            <button onclick="closeDisburseModal()" class="text-slate-400 hover:text-slate-600"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <form action="actions.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="pay_welfare">
            <input type="hidden" name="id" id="disburse-id" value="">

            <div>
                <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-2">Upload Physical
                    Voucher Receipt *</label>
                <input type="file" name="proof_photo" required accept="image/*"
                    class="text-xs text-slate-500 w-full mb-2">
                <p class="text-[9px] text-slate-400 leading-relaxed">Please capture and upload a copy of the physical
                    transaction receipt to settle this file ledger row.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeDisburseModal()"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs px-4 py-2 rounded-lg transition-all">Cancel</button>
                <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-lg transition-all shadow-sm">Authorize
                    Settled Status</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: DONATIONS INWARD ENGINE -->
<div id="inflow-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 id="inflow-title" class="text-sm font-bold text-slate-800">Log Donation Inward</h3>
            <button onclick="closeInflowModal()" class="text-slate-400 hover:text-slate-600"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <form id="inflow-form" action="actions.php" method="POST" class="p-6 space-y-4 text-xs">
            <input type="hidden" name="action" id="inflow-action" value="add_inflow">
            <input type="hidden" name="id" id="inflow-id" value="">

            <div>
                <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Donor Identity
                    Details *</label>
                <input type="text" id="inflow_donor_name" name="donor_name" required
                    placeholder="Full Name or Anonymous"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Donation
                        Type *</label>
                    <select id="inflow_type" name="type" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 focus:outline-none cursor-pointer">
                        <option value="General Sadaqah">General Sadaqah</option>
                        <option value="Zakat Ledger Inward">Zakat Ledger Inward</option>
                        <option value="Fitrah Contributions">Fitrah Contributions</option>
                        <option value="Corporate / CSR Outreaches">Corporate / CSR Outreaches</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Voucher
                        Receipt Ref #</label>
                    <input type="text" id="inflow_ref" name="reference_no" placeholder="Book Sl No."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1.5">Total
                    Collected Amount *</label>
                <input type="number" id="inflow_amount" name="amount" required placeholder="₹ INR Currency"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 focus:outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeInflowModal()"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs px-4 py-2 rounded-lg transition-all">Cancel</button>
                <button type="submit" id="inflow-submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-lg transition-all shadow-sm">Save
                    Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- AUDIT FILE RECEIPT LIGHTBOX VIEWER -->
<div id="receipt-viewer-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden"
    onclick="closeReceiptViewer()">
    <div class="bg-white p-3 rounded-2xl max-w-lg w-full relative" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-2">
            <span id="receipt-viewer-title" class="text-xs font-bold text-slate-700">Audit Document Viewer</span>
            <button onclick="closeReceiptViewer()" class="text-slate-400 hover:text-slate-600"><i
                    class="fa-solid fa-circle-xmark text-lg"></i></button>
        </div>
        <img id="receipt-viewer-img" src="" class="w-full max-h-[70vh] object-contain rounded-lg border">
    </div>
</div>

<!-- ========================================================================= -->
<!--                           JAVASCRIPT CORE LOGIC                           -->
<!-- ========================================================================= -->
<script>
    // Safe memory encapsulation mapping matching Verified schema arrays
    const verifiedMembersMap = <?php echo json_encode($members_list); ?>;

    document.addEventListener("DOMContentLoaded", function () {
        switchTab('tab-outflows');
    });

    // RESTORE PILL NAVIGATION DESIGN ACCORDING TO image_bbc7b2.png
    function switchTab(tabId) {
        // Hide all blocks
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');

        // Hide all operational buttons first
        // document.getElementById('btn-action-outflows').classList.add('hidden');
        document.getElementById('btn-action-inflows').classList.add('hidden');
        document.getElementById('btn-action-applications').classList.add('hidden');

        // Show active contextual action buttons matching navigation context
        if (tabId === 'tab-outflows') {
            // document.getElementById('btn-action-outflows').classList.remove('hidden');
        } else if (tabId === 'tab-inflows') {
            document.getElementById('btn-action-inflows').classList.remove('hidden');
        } else if (tabId === 'tab-applications') {
            document.getElementById('btn-action-applications').classList.remove('hidden');
        }

        // Apply active background layout capsule switches on tabs matching image_bbc7b2.png
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'shadow-xs', 'text-slate-800');
            btn.classList.add('text-slate-500', 'hover:text-slate-700');

            if (btn.id === 'btn-' + tabId) {
                btn.classList.remove('text-slate-500', 'hover:text-slate-700');
                btn.classList.add('bg-white', 'shadow-xs', 'text-slate-800');
            }
        });
    }

    function openBaselineModal() {
        document.getElementById('baseline-modal').classList.remove('hidden');
    }

    function closeBaselineModal() {
        document.getElementById('baseline-modal').classList.add('hidden');
    }

    function openApplicationModal() {
        document.getElementById('app-form').reset();
        // Ensure old file previews are swept clean when opening a new blank application
        document.getElementById('edit_photo_preview_block').classList.add('hidden');
        document.getElementById('edit_photo_preview_block').classList.remove('flex');
        document.getElementById('edit_id_card_preview_block').classList.add('hidden');
        document.getElementById('edit_id_card_preview_block').classList.remove('flex');
        document.getElementById('id_card_input').required = true;
        document.getElementById('app-form-action').value = "add_application";
        document.getElementById('app-form-id').value = "";
        document.getElementById('app-modal-title').innerHTML = `<i class="fa-solid fa-file-invoice text-blue-600"></i> File Welfare Aid Application`;
        document.getElementById('app-submit-btn').textContent = "Submit Application";
        toggleMemberField(false);
        document.getElementById('application-modal').classList.remove('hidden');
    }

    function closeApplicationModal() {
        document.getElementById('application-modal').classList.add('hidden');
    }

    function toggleMemberField(isChecked) {
        const selectBlock = document.getElementById('member-select-block');
        const inputFields = document.querySelectorAll('.app-field');
        const idCardField = document.getElementById('id_card_input');

        document.getElementById('app_is_member').checked = isChecked;

        if (isChecked) {
            selectBlock.classList.remove('hidden');
            inputFields.forEach(input => {
                input.readOnly = true;
                input.classList.add('bg-slate-100', 'text-slate-500');
            });
            idCardField.required = false;
        } else {
            selectBlock.classList.add('hidden');
            document.getElementById('app_member_id').value = "";
            inputFields.forEach(input => {
                input.readOnly = false;
                input.classList.remove('bg-slate-100', 'text-slate-500');
            });
            idCardField.required = true;
        }
    }

    // Auto-populate split address lines exactly as required for the members columns
    function autofillMemberData(memberId) {
        const member = verifiedMembersMap.find(m => m.id == memberId);
        if (member) {
            document.getElementById('app_first_name').value = member.first_name;
            document.getElementById('app_last_name').value = member.last_name;
            document.getElementById('app_father_husband_name').value = member.father_husband_name;
            document.getElementById('app_phone').value = member.phone;

            document.getElementById('app_res_line1').value = member.res_address_line1 || '';
            document.getElementById('app_res_line2').value = member.res_address_line2 || '';
            document.getElementById('app_res_city').value = member.res_city || '';
            document.getElementById('app_res_pincode').value = member.res_pincode || '';
        }
    }

    function populateEditApplication(item) {
        openApplicationModal();
        document.getElementById('app-modal-title').innerHTML = `<i class="fa-solid fa-pen-to-square text-teal-600"></i> Modify Application Parameters`;
        document.getElementById('app-form-action').value = "edit_application";
        document.getElementById('app-form-id').value = item.id;

        const isMember = parseInt(item.is_member) === 1;
        toggleMemberField(isMember);

        if (isMember) {
            document.getElementById('app_member_id').value = item.member_id;
        }

        document.getElementById('app_first_name').value = item.first_name;
        document.getElementById('app_last_name').value = item.last_name;
        document.getElementById('app_father_husband_name').value = item.father_husband_name;
        document.getElementById('app_res_line1').value = item.res_address_line1;
        document.getElementById('app_res_line2').value = item.res_address_line2;
        document.getElementById('app_res_city').value = item.res_city;
        document.getElementById('app_res_pincode').value = item.res_pincode;

        // FIXED MAPPING: Fetches contact number column out of the app object
        document.getElementById('app_phone').value = item.contact_number || item.phone || '';

        document.getElementById('app_type').value = item.type;
        document.getElementById('app_amount').value = item.amount;
        document.getElementById('app_mode').value = item.mode_of_payment;
        document.getElementById('app_date').value = item.date_of_payment;
        document.getElementById('app-submit-btn').textContent = "Save Changes";

        // LIVE PREVIEW POPULATION LOGIC
        const photoBlock = document.getElementById('edit_photo_preview_block');
        const photoImg = document.getElementById('edit_photo_preview_img');
        if (item.photo && item.photo.trim() !== '') {
            photoImg.src = (item.photo.indexOf('data:image') === 0 || item.photo.indexOf('uploads/') === 0) ? item.photo : 'uploads/welfare/photos/' + item.photo;
            photoBlock.classList.remove('hidden');
            photoBlock.classList.add('flex');
        } else {
            photoBlock.classList.add('hidden');
            photoBlock.classList.remove('flex');
        }

        const idBlock = document.getElementById('edit_id_card_preview_block');
        const idLink = document.getElementById('edit_id_card_preview_link');
        const idInput = document.getElementById('id_card_input');

        // Reset state memories for clean modal tracking toggles
        currentIdCardDataUrl = "";
        currentIdCardFileName = "";
        idLink.removeAttribute('data-server-path');

        if (item.id_card && item.id_card.trim() !== '') {
            const resolvedPath = (item.id_card.indexOf('uploads/') === 0) ? item.id_card : 'uploads/welfare/id_cards/' + item.id_card;

            idLink.textContent = "View Current ID Document";
            idLink.setAttribute('data-server-path', resolvedPath); // Mount path reference
            idBlock.classList.remove('hidden');
            idBlock.classList.add('flex');
            idInput.required = false;
        } else {
            idBlock.classList.add('hidden');
            idBlock.classList.remove('flex');
            if (!isMember) idInput.required = true;
        }
    }

    // Format address dynamically for detailed preview dossier matching member styling
    function viewApplicationDetails(app) {
        document.getElementById('view_name').textContent = app.first_name + " " + app.last_name;
        document.getElementById('view_father_name').textContent = "Guardian S/D/W of: " + app.father_husband_name;
        document.getElementById('view_phone').textContent = app.contact_number;
        document.getElementById('view_type').textContent = app.type;
        document.getElementById('view_amount').textContent = "₹" + parseInt(app.amount).toLocaleString('en-IN');
        document.getElementById('view_mode').textContent = app.mode_of_payment;
        document.getElementById('view_date').textContent = app.date_of_payment ? new Date(app.date_of_payment).toLocaleDateString('en-GB') : 'Immediate';

        let compiledAddress = [app.res_address_line1, app.res_address_line2, app.res_city, app.res_pincode]
            .filter(line => line && line.trim() !== '')
            .join(', ');
        document.getElementById('view_address').textContent = compiledAddress || 'No Address Logged';

        const badge = document.getElementById('view_is_member_badge');
        if (parseInt(app.is_member) === 1) {
            badge.className = "bg-emerald-50 text-emerald-700 text-[10px] font-extrabold px-3 py-1 rounded-full border border-emerald-200";
            badge.innerHTML = `<i class="fa-solid fa-user-shield"></i> Jamaath Member`;
        } else {
            badge.className = "bg-slate-100 text-slate-600 text-[10px] font-extrabold px-3 py-1 rounded-full border border-slate-200";
            badge.innerHTML = `<i class="fa-solid fa-user"></i> Non-Member Residence`;
        }

        const statusLbl = document.getElementById('view_status');
        if (app.status === 'Pending') {
            statusLbl.className = "text-amber-600 font-extrabold";
            statusLbl.textContent = "Pending Audit Check";
        } else if (app.status === 'Accepted') {
            statusLbl.className = "text-emerald-600 font-extrabold";
            statusLbl.textContent = "Approved & Routed to Outflows";
        } else {
            statusLbl.className = "text-rose-600 font-extrabold";
            statusLbl.textContent = "Rejected";
        }

        const photoEl = document.getElementById('view_photo');
        if (app.photo && app.photo !== '') {
            photoEl.src = (app.photo.indexOf('data:image') === 0 || app.photo.indexOf('uploads/') === 0) ? app.photo : 'uploads/welfare/photos/' + app.photo;
        } else {
            photoEl.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23f1f5f9"/><text x="50%" y="50%" font-size="30" text-anchor="middle" alignment-baseline="middle">👤</text></svg>';
        }

        const idEl = document.getElementById('view_id_card');
        if (app.id_card && app.id_card !== '') {
            idEl.src = (app.id_card.indexOf('uploads/') === 0) ? app.id_card : 'uploads/welfare/id_cards/' + app.id_card;
            idEl.parentElement.classList.remove('hidden');
        } else {
            idEl.src = '';
            idEl.parentElement.classList.add('hidden');
        }

        document.getElementById('view-modal').classList.remove('hidden');
    }

    function closeViewModal() { document.getElementById('view-modal').classList.add('hidden'); }
    function openDisburseModal(id) { document.getElementById('disburse-id').value = id; document.getElementById('disburse-modal').classList.remove('hidden'); }
    function closeDisburseModal() { document.getElementById('disburse-modal').classList.add('hidden'); }
    function openInflowModal() { document.getElementById('inflow-form').reset(); document.getElementById('inflow-action').value = "add_inflow"; document.getElementById('inflow-modal').classList.remove('hidden'); }
    function closeInflowModal() { document.getElementById('inflow-modal').classList.add('hidden'); }

    function populateEditInflow(item) {
        openInflowModal();
        document.getElementById('inflow-title').textContent = "Modify Donation Parameters";
        document.getElementById('inflow-action').value = "edit_inflow";
        document.getElementById('inflow-id').value = item.id;
        document.getElementById('inflow_donor_name').value = item.donor_name;
        document.getElementById('inflow_type').value = item.inflow_type;
        document.getElementById('inflow_ref').value = item.reference_no;
        document.getElementById('inflow_amount').value = item.amount;
    }

    function viewPaymentProof(recipient, path, customTitle) {
        // Fallback to old string layout if no custom title is passed
        const modalTitle = customTitle ? customTitle + ": " + recipient : "Payment Voucher Proof: " + recipient;
        document.getElementById('receipt-viewer-title').textContent = modalTitle;
        document.getElementById('receipt-viewer-img').src = path;
        document.getElementById('receipt-viewer-modal').classList.remove('hidden');
    }
    function closeReceiptViewer() { document.getElementById('receipt-viewer-modal').classList.add('hidden'); }

    // Global variable tracking path/stream state to manage modal previews safely
    let currentIdCardDataUrl = "";
    let currentIdCardFileName = "";

    // Handles reading local image streams for the profile photo preview with 2MB limit
    function previewLocalFile(input, imgElementId, blockId) {
        const file = input.files[0];
        if (!file) return;

        // 2MB Size Guard Rule Validation Check
        if (file.size > 2 * 1024 * 1024) {
            alert("Security Limit Exception: Profile image file size must not exceed 2MB.");
            input.value = ""; // Flush selected asset string
            document.getElementById(blockId).classList.add('hidden');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById(imgElementId).src = e.target.result;
            const block = document.getElementById(blockId);
            block.classList.remove('hidden');
            block.classList.add('flex');
        }
        reader.readAsDataURL(file);
    }

    // Handles reading local file streams for the verification document with 2MB limit
    function previewLocalIdCard(input, linkElementId, blockId) {
        const file = input.files[0];
        if (!file) return;

        // 2MB Size Guard Rule Validation Check
        if (file.size > 2 * 1024 * 1024) {
            alert("Security Limit Exception: ID Verification file size must not exceed 2MB.");
            input.value = ""; // Flush selected asset string
            document.getElementById(blockId).classList.add('hidden');
            currentIdCardDataUrl = "";
            currentIdCardFileName = "";
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            currentIdCardDataUrl = e.target.result;
            currentIdCardFileName = file.name;

            const link = document.getElementById(linkElementId);
            link.textContent = "Preview Selected File (" + file.name + ")";

            const block = document.getElementById(blockId);
            block.classList.remove('hidden');
            block.classList.add('flex');
        }
        reader.readAsDataURL(file);
    }

    // Directs routing actions to prevent opening empty blank tabs for non-uploaded assets
    function handleIdPreviewClick() {
        const linkElement = document.getElementById('edit_id_card_preview_link');

        // Scenario A: It's a newly selected local file stream
        if (currentIdCardDataUrl !== "") {
            if (currentIdCardDataUrl.startsWith("data:image/")) {
                // Instantly present image files using your expanded high-fidelity lightbox component with clear context
                viewPaymentProof(currentIdCardFileName, currentIdCardDataUrl, "ID Verification Preview");
            } else {
                // Clean fallback guidance for local un-uploaded PDFs
                alert("Local PDF Selected: '" + currentIdCardFileName + "' is loaded cleanly and ready for upload. Local PDFs will be viewable in tabs once saved to the system registry server.");
            }
        }
        // Scenario B: It's a legacy file reference saved previously on disk storage
        else {
            const preservedPath = linkElement.getAttribute('data-server-path');
            if (preservedPath) {
                window.open(preservedPath, '_blank');
            }
        }
    }
</script>

<?php require_once 'footer.php'; ?>
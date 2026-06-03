<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch active members to populate the informant selector dropdown inside the modal
$active_members = $db->query("SELECT id, first_name, last_name, card_no FROM members WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();

// Fetch burial registry joined with members table to get informant member details if applicable
$burial_list = $db->query("
    SELECT b.*, m.first_name AS rep_first, m.last_name AS rep_last, m.card_no AS rep_card
    FROM burial_registry b
    LEFT JOIN members m ON b.reporter_member_id = m.id
    ORDER BY b.date_added DESC, b.id DESC
")->fetchAll();

require_once 'header.php';
?>

<!-- HTML2PDF CDN Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Burial Registry</h3>
            <p class="text-xs text-slate-500">Official registry tracking final resting sites and death timelines inside committee cemetery grounds</p>
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
        <input type="text" id="search-burial" onkeyup="filterBurial()" placeholder="Search by Deceased Name, Plot, Informant..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-rose-500 focus:bg-white focus:outline-none transition-all">
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                    <th class="py-3 px-4 font-semibold">Deceased (Marhoom) & Death Date</th>
                    <th class="py-3 px-4 font-semibold">Burial Date & Time</th>
                    <th class="py-3 px-4 font-semibold">Grave Location Reference</th>
                    <th class="py-3 px-4 font-semibold">Reported / Informant Details</th>
                    <th class="py-3 px-4 font-semibold">Recorded Timestamp</th>
                    <th class="py-3 px-4 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="burial-table-rows" class="divide-y divide-slate-100 text-sm">
                <?php if (empty($burial_list)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 text-xs">No entries logged inside the Burial registry.</td>
                        </tr>
                <?php else: ?>
                        <?php foreach ($burial_list as $burial):
                            // Prepare informant descriptive details safely for JavaScript execution and search indexing
                            $informant_type = $burial['reported_by_member'] == 1 ? 'Member' : 'Non-Member';
                            if ($burial['reported_by_member'] == 1) {
                                $informant_summary = htmlspecialchars($burial['rep_first'] . ' ' . $burial['rep_last'] . ' (Card: ' . $burial['rep_card'] . ')');
                            } else {
                                $informant_summary = htmlspecialchars($burial['reporter_name'] . ' [' . $burial['reporter_relationship'] . '] - Tel: ' . $burial['reporter_phone']);
                            }
                            ?>
                                <tr class="burial-record-row hover:bg-slate-50/50"
                                    data-deceased="<?php echo htmlspecialchars(strtolower($burial['deceased_name'])); ?>"
                                    data-plot="<?php echo htmlspecialchars(strtolower($burial['plot_details'])); ?>"
                                    data-informant="<?php echo strtolower($informant_summary); ?>">
                                    <td class="py-4 px-4 text-xs">
                                        <p class="font-bold text-rose-900">🕊️ <?php echo htmlspecialchars($burial['deceased_name']); ?></p>
                                        <p class="mt-1 text-slate-500">
                                            <?php if (!empty($burial['death_datetime'])): ?>
                                                    <i class="fa-solid fa-dove text-slate-400 mr-1"></i>Died: <?php echo date('d M Y - h:i A', strtotime($burial['death_datetime'])); ?>
                                            <?php else: ?>
                                                    <i class="fa-solid fa-dove text-slate-300 mr-1"></i>Death date not provided
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                    <td class="py-4 px-4 text-xs font-semibold text-rose-800">
                                        <span class="bg-rose-50 px-2.5 py-1 rounded-md">
                                            <i class="fa-solid fa-calendar-day mr-1"></i> <?php echo date('d M Y - h:i A', strtotime($burial['burial_datetime'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-xs font-semibold text-slate-700">
                                        <span class="bg-slate-150 px-2 py-1 rounded-lg border border-slate-200">
                                            <i class="fa-solid fa-compass mr-1"></i> <?php echo htmlspecialchars($burial['plot_details']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-xs">
                                        <?php if ($burial['reported_by_member'] == 1): ?>
                                                <span class="bg-teal-100 text-teal-800 text-[9px] font-extrabold px-1.5 py-0.5 rounded uppercase tracking-wider block w-max mb-1">Jamath Member</span>
                                                <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($burial['rep_first'] . ' ' . $burial['rep_last']); ?></p>
                                                <p class="text-[10px] text-slate-400">Card: <?php echo htmlspecialchars($burial['rep_card']); ?></p>
                                        <?php else: ?>
                                                <span class="bg-amber-100 text-amber-800 text-[9px] font-extrabold px-1.5 py-0.5 rounded uppercase tracking-wider block w-max mb-1">External Informant</span>
                                                <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($burial['reporter_name']); ?></p>
                                                <p class="text-[10px] text-slate-400 font-medium">Rel: <?php echo htmlspecialchars($burial['reporter_relationship']); ?> | <?php echo htmlspecialchars($burial['reporter_phone']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-xs text-slate-500 font-mono font-semibold">
                                        <?php echo date('d M Y - h:i A', strtotime($burial['date_added'])); ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <button 
                                            onclick="issueBurialCertificate(
                                        '<?php echo addslashes(htmlspecialchars($burial['deceased_name'])); ?>',
                                        '<?php echo !empty($burial['death_datetime']) ? date('d M Y - h:i A', strtotime($burial['death_datetime'])) : 'N/A'; ?>',
                                        '<?php echo date('d M Y - h:i A', strtotime($burial['burial_datetime'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($burial['plot_details'])); ?>',
                                        '<?php echo $informant_type; ?>',
                                        '<?php echo addslashes($informant_summary); ?>',
                                        '<?php echo date('d M Y - h:i A', strtotime($burial['date_added'])); ?>'
                                    )"
                                            class="bg-rose-700 hover:bg-rose-800 text-white font-bold text-[10px] px-3 py-1.5 rounded-xl shadow-sm transition-colors flex items-center gap-1.5 ml-auto">
                                            <i class="fa-solid fa-file-pdf"></i> Download PDF
                                        </button>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Record Direct Burial Plot -->
<div id="burial-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full p-6 transition-all">
        <div class="flex justify-between items-start mb-2">
            <h4 class="text-lg font-bold text-slate-800">Record Burial Log</h4>
            <button onclick="closeBurialModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="text-xs text-slate-500 mb-4">Log regional demise records, death schedules, burial location, and informant details.</p>

        <form method="POST" action="actions.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_burial">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Deceased Name (Marhoom) *</label>
                    <input type="text" name="deceased_name" required placeholder="e.g. Shahul Hameed" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Grave Location Reference *</label>
                    <input type="text" name="plot_details" required placeholder="e.g. Main Graveyard" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Date & Time of Death</label>
                    <input type="datetime-local" name="death_datetime" id="death-datetime-field" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Date & Time of Burial *</label>
                    <input type="datetime-local" name="burial_datetime" required id="burial-datetime-field" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
            </div>

            <!-- Informant Selection Segment -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="reported_by_member" value="1" checked id="reported_by_member_check" onchange="toggleReporterFields()" class="h-4 w-4 text-rose-600 focus:ring-rose-500 border-slate-300 rounded">
                    <label for="reported_by_member_check" class="text-xs text-slate-700 font-bold select-none">Reported by an active Jamath Member</label>
                </div>

                <!-- Case A: Reporter is an active database member -->
                <div id="reporter_member_container" class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Select Informant Member *</label>
                    <select name="reporter_member_id" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                        <option value="">-- Choose Member --</option>
                        <?php foreach ($active_members as $act_m): ?>
                                <option value="<?php echo $act_m['id']; ?>"><?php echo htmlspecialchars($act_m['first_name'] . ' ' . $act_m['last_name'] . ' (' . $act_m['card_no'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Case B: Reporter is an external non-member -->
                <div id="reporter_guest_container" class="hidden space-y-3 pt-1 border-t border-slate-200">
                    <p class="text-[10px] font-bold text-rose-800 tracking-wide uppercase"><i class="fa-solid fa-circle-info mr-1"></i> Provide External Informant Details</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Full Name</label>
                            <input type="text" name="reporter_name" placeholder="e.g. Hameed" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phone Number</label>
                            <input type="tel" name="reporter_phone" placeholder="e.g. 9486012455" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Relationship</label>
                            <input type="text" name="reporter_relationship" placeholder="e.g. Cousin, Son" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-2 text-xs focus:ring-1 focus:ring-rose-500">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <button type="button" onclick="closeBurialModal()" class="w-1/2 bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold hover:bg-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="w-1/2 bg-rose-700 hover:bg-rose-800 text-white py-2.5 rounded-xl text-xs font-semibold shadow transition-colors">
                    Save Burial Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterBurial() {
        const searchVal = document.getElementById('search-burial').value.trim().toLowerCase();
        const rows = document.querySelectorAll('.burial-record-row');
        rows.forEach(row => {
            const deceased = row.getAttribute('data-deceased');
            const plot = row.getAttribute('data-plot');
            const informant = row.getAttribute('data-informant');
            if (searchVal === '' || deceased.includes(searchVal) || plot.includes(searchVal) || informant.includes(searchVal)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    function toggleReporterFields() {
        const check = document.getElementById('reported_by_member_check');
        const mCont = document.getElementById('reporter_member_container');
        const gCont = document.getElementById('reporter_guest_container');
        if (check.checked) {
            mCont.classList.remove('hidden');
            gCont.classList.add('hidden');
        } else {
            mCont.classList.add('hidden');
            gCont.classList.remove('hidden');
        }
    }

    function openBurialModal() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        const fBurial = document.getElementById('burial-datetime-field');
        const fDeath = document.getElementById('death-datetime-field');
        if (fBurial) fBurial.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        if (fDeath) fDeath.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        document.getElementById('burial-modal').classList.remove('hidden');
    }

    function closeBurialModal() {
        document.getElementById('burial-modal').classList.add('hidden');
    }

    // High-Fidelity Respectful Burial Certificate Generator
    function issueBurialCertificate(deceased, death_time, burial_time, plot, reporter_type, reporter_info, date_added) {
        const opt = {
            margin:       0.3,
            filename:     `Burial_Certificate_${deceased.replace(/\s+/g, '_')}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
        };

        const certTemplate = document.createElement('div');
        certTemplate.style.width = '10.4in';
        certTemplate.style.height = '7.5in';
        certTemplate.style.padding = '0.4in';
        certTemplate.style.boxSizing = 'border-box';
        certTemplate.style.background = '#ffffff';
        certTemplate.style.fontFamily = 'Georgia, serif';

        certTemplate.innerHTML = `
            <div style="border: 15px double #334155; padding: 25px; height: 100%; box-sizing: border-box; position: relative; background-image: radial-gradient(circle, #f8fafc 1px, transparent 1px); background-size: 20px 20px; background-color: #fafbfb;">
                
                <!-- Corner Symbols -->
                <div style="position: absolute; top: 12px; left: 12px; color: #475569; font-size: 20px;">🕌</div>
                <div style="position: absolute; top: 12px; right: 12px; color: #475569; font-size: 20px;">🕌</div>
                <div style="position: absolute; bottom: 12px; left: 12px; color: #475569; font-size: 20px;">🕌</div>
                <div style="position: absolute; bottom: 12px; right: 12px; color: #475569; font-size: 20px;">🕌</div>
                
                <!-- Header Crest -->
                <div style="text-align: center; margin-bottom: 15px;">
                    <h1 style="margin: 0; color: #1e293b; font-size: 28px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;">NVK Jamaath Committee</h1>
                    <p style="margin: 5px 0 0 0; font-size: 11px; text-transform: uppercase; letter-spacing: 4px; font-weight: bold; color: #475569;">Vadasery, Nagercoil, Kanyakumari District, Tamil Nadu</p>
                    <div style="width: 250px; height: 3px; background: linear-gradient(to right, transparent, #475569, transparent); margin: 12px auto 4px auto;"></div>
                    <div style="width: 150px; height: 1px; background: #e2e8f0; margin: 0 auto;"></div>
                </div>

                <div style="text-align: center; margin-bottom: 25px;">
                    <h2 style="font-family: Georgia, serif; font-style: italic; color: #0f172a; font-size: 22px; margin: 5px 0;">Official Burial Registration</h2>
                    <p style="font-size: 11px; color: #64748b; margin: 0; font-family: sans-serif;">This is to certify that the demise and final burial details of the following individual have been officially recorded within our jamaath registries.</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px;">
                    <tr>
                        <td colspan="2" style="padding: 10px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px;">
                                <strong style="color: #475569; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Deceased Subject (Marhoom)</strong>
                                <span style="font-size: 16px; color: #0f172a; font-weight: bold;">🕊️ ${deceased}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; padding: 10px; vertical-align: top; padding-top: 15px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px;">
                                <strong style="color: #475569; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Date & Time of Demise</strong>
                                <span style="font-size: 14px; color: #0f172a; font-weight: 600;">${death_time}</span>
                            </div>
                        </td>
                        <td style="width: 50%; padding: 10px; vertical-align: top; padding-top: 15px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px;">
                                <strong style="color: #475569; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Date & Time of Burial</strong>
                                <span style="font-size: 14px; color: #0f172a; font-weight: 600;">${burial_time}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; vertical-align: top; padding-top: 15px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px;">
                                <strong style="color: #475569; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Grave Plot Reference</strong>
                                <span style="font-size: 14px; color: #0f172a; font-weight: 600;">📍 ${plot}</span>
                            </div>
                        </td>
                        <td style="padding: 10px; vertical-align: top; padding-top: 15px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px;">
                                <strong style="color: #475569; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Informant / Reporter Information (${reporter_type})</strong>
                                <span style="font-size: 13px; color: #334155; font-style: italic;">${reporter_info}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 35px; display: flex; justify-content: space-between; align-items: flex-end; padding: 0 30px;">
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 11px; color: #475569; font-weight: 600; font-family: sans-serif;">Burial Registrar Signature</div>
                    </div>
                    <div style="text-align: center; width: 140px;">
                        <div style="border: 2px solid #334155; border-radius: 50%; width: 70px; height: 70px; line-height: 70px; margin: 0 auto; color: #334155; font-size: 10px; font-weight: bold; text-transform: uppercase; transform: rotate(-8deg); font-family: sans-serif;">Registry Seal</div>
                    </div>
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 11px; color: #475569; font-weight: 600; font-family: sans-serif;">Chief Imam Inspector</div>
                    </div>
                </div>
            </div>
        `;

        html2pdf().set(opt).from(certTemplate).save();
    }
</script>

<?php require_once 'footer.php'; ?>
<?php
require_once 'db.php';
require_once 'helpers.php';

// Fetch lists
$nikah_list = $db->query("SELECT * FROM nikah_registry ORDER BY date_added DESC, id DESC")->fetchAll();

require_once 'header.php';
?>

<!-- HTML2PDF CDN Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-800">Marriage Certificate Registry (Nikah)</h3>
            <p class="text-xs text-slate-500">Official certified wedding registry archives with precise recording
                timestamps</p>
        </div>
        <button onclick="openNikahModal()"
            class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-ring"></i> Register New Nikah
        </button>
    </div>

    <!-- Instant Search Workspace bar for Nikah -->
    <div class="relative mb-6">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
            <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" id="search-nikah" onkeyup="filterNikah()" placeholder="Search by Groom Name, Bride Name or Venue..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs focus:ring-2 focus:ring-teal-500 focus:bg-white focus:outline-none transition-all">
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                    <th class="py-3 px-4 font-semibold">Groom Name</th>
                    <th class="py-3 px-4 font-semibold">Bride Name</th>
                    <th class="py-3 px-4 font-semibold">Venue & Officiator</th>
                    <th class="py-3 px-4 font-semibold">Nikah Date & Time</th>
                    <th class="py-3 px-4 font-semibold">Recorded Timestamp</th>
                    <th class="py-3 px-4 font-semibold">Certificate Details / References</th>
                    <th class="py-3 px-4 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="nikah-table-rows" class="divide-y divide-slate-100 text-sm">
                <?php if (empty($nikah_list)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400 text-xs">No entries archived inside Nikah
                            certified logs.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($nikah_list as $nikah): ?>
                        <tr class="nikah-record-row hover:bg-slate-50/50"
                            data-groom="<?php echo htmlspecialchars(strtolower($nikah['groom_name'])); ?>"
                            data-bride="<?php echo htmlspecialchars(strtolower($nikah['bride_name'])); ?>"
                            data-venue="<?php echo htmlspecialchars(strtolower($nikah['venue'])); ?>">
                            <td class="py-4 px-4 font-bold text-slate-800 text-xs">🤵
                                <?php echo htmlspecialchars($nikah['groom_name']); ?></td>
                            <td class="py-4 px-4 font-bold text-slate-800 text-xs">👰
                                <?php echo htmlspecialchars($nikah['bride_name']); ?></td>
                            <td class="py-4 px-4 text-xs">
                                <p class="font-semibold text-slate-700">
                                    <i
                                        class="fa-solid fa-map-location-dot text-teal-600 mr-1.5"></i><?php echo htmlspecialchars($nikah['venue']); ?>
                                </p>
                                <p class="mt-1">
                                    <?php if (!empty($nikah['conducted_by_jamath']) && $nikah['conducted_by_jamath'] == 1): ?>
                                        <span
                                            class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                                            <i class="fa-solid fa-mosque mr-1"></i>Conducted by Jamath
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                                            <i class="fa-solid fa-user mr-1"></i>Private Event
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td class="py-4 px-4 text-xs font-semibold text-teal-800">
                                <span class="bg-teal-50 px-2.5 py-1 rounded-md">
                                    <i class="fa-solid fa-clock mr-1"></i>
                                    <?php echo date('d M Y - h:i A', strtotime($nikah['nikah_datetime'])); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 text-xs text-slate-500 font-mono font-semibold">
                                <?php echo date('d M Y - h:i A', strtotime($nikah['date_added'])); ?></td>
                            <td class="py-4 px-4 text-xs font-medium text-slate-600">
                                <?php echo htmlspecialchars($nikah['details']); ?></td>
                            <td class="py-4 px-4 text-right">
                                <?php if (!empty($nikah['conducted_by_jamath']) && $nikah['conducted_by_jamath'] == 1): ?>
                                    <button onclick="issueNikahCertificate(
                                            '<?php echo addslashes(htmlspecialchars($nikah['groom_name'])); ?>',
                                            '<?php echo addslashes(htmlspecialchars($nikah['bride_name'])); ?>',
                                            '<?php echo addslashes(htmlspecialchars($nikah['venue'])); ?>',
                                            '<?php echo date('d M Y - h:i A', strtotime($nikah['nikah_datetime'])); ?>',
                                            '<?php echo addslashes(htmlspecialchars($nikah['details'])); ?>'
                                        )"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] px-3 py-1.5 rounded-xl shadow-sm transition-colors flex items-center gap-1.5 ml-auto">
                                        <i class="fa-solid fa-file-pdf"></i> Issue Certificate
                                    </button>
                                <?php else: ?>
                                    <button disabled
                                        title="Official Certificate issuance is restricted to weddings fully officiated by our Jamath Committee."
                                        class="bg-slate-100 text-slate-400 font-bold text-[10px] px-3 py-1.5 rounded-xl cursor-not-allowed flex items-center gap-1.5 ml-auto">
                                        <i class="fa-solid fa-lock"></i> Locked
                                    </button>
                                <?php endif; ?>
                            </td>
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
            const venue = row.getAttribute('data-venue');
            if (searchVal === '' || groom.includes(searchVal) || bride.includes(searchVal) || venue.includes(searchVal)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    // High-Fidelity Landscape PDF Wedding Certificate Engine
    function issueNikahCertificate(groom, bride, venue, datetime, details) {
        const opt = {
            margin: 0.3,
            filename: `Nikah_Certificate_${groom.replace(/\s+/g, '_')}.pdf`,
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
                    <h1 style="margin: 0; color: #115e59; font-size: 30px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;">NVK Jamath Committee</h1>
                    <p style="margin: 5px 0 0 0; font-size: 11px; text-transform: uppercase; letter-spacing: 4px; font-weight: bold; color: #0f766e;">Vadasery, Nagercoil, Kanyakumari District, Tamil Nadu</p>
                    <div style="width: 250px; height: 3px; background: linear-gradient(to right, transparent, #b45309, transparent); margin: 12px auto 4px auto;"></div>
                    <div style="width: 150px; height: 1px; background: #e2e8f0; margin: 0 auto;"></div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="font-family: Georgia, serif; font-style: italic; color: #b45309; font-size: 24px; margin: 5px 0;">Certificate of Islamic Marriage</h2>
                    <p style="font-size: 12px; color: #64748b; margin: 0; font-family: sans-serif;">This is to certify that the marriage contract (Nikah) has been solemnly completed and registered under our Jamath.</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 35px; font-size: 15px;">
                    <tr>
                        <td style="width: 50%; padding: 12px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">The Groom (Bridegroom)</strong>
                                <span style="font-size: 17px; color: #1e293b; font-weight: bold;">${groom}</span>
                            </div>
                        </td>
                        <td style="width: 50%; padding: 12px; vertical-align: top;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">The Bride</strong>
                                <span style="font-size: 17px; color: #1e293b; font-weight: bold;">${bride}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Solemnization Date & Time</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: 600;">${datetime}</span>
                            </div>
                        </td>
                        <td style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Nikah Venue</strong>
                                <span style="font-size: 15px; color: #1e293b; font-weight: 600;">${venue}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 12px; vertical-align: top; padding-top: 20px;">
                            <div style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                <strong style="color: #0f766e; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px; font-family: sans-serif; tracking-wider">Registry Books & References</strong>
                                <span style="font-size: 14px; color: #334155; font-style: italic;">${details}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 45px; display: flex; justify-content: space-between; align-items: flex-end; padding: 0 30px;">
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 12px; color: #475569; font-weight: 600; font-family: sans-serif;">Groom's Signature</div>
                    </div>
                    <div style="text-align: center; width: 140px;">
                        <div style="border: 2px solid #0d9488; border-radius: 50%; width: 75px; height: 75px; line-height: 75px; margin: 0 auto; color: #0d9488; font-size: 10px; font-weight: bold; text-transform: uppercase; transform: rotate(-8deg); font-family: sans-serif;">Registry Seal</div>
                    </div>
                    <div style="text-align: center; width: 180px;">
                        <div style="border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 12px; color: #475569; font-weight: 600; font-family: sans-serif;">Chief Imam Registrar</div>
                    </div>
                </div>
            </div>
        `;

        html2pdf().set(opt).from(certTemplate).save();
    }
</script>

<?php require_once 'footer.php'; ?>
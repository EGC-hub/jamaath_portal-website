<?php
?>
</main>

<!-- Modal: Record Member Demise -->
<div id="deceased-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6 transition-all duration-300">
        <h4 class="text-lg font-bold text-slate-800 mb-2">Record Member Demise</h4>
        <p class="text-xs text-slate-500 mb-4">Confirm the date and time of death. This action will flag the member as
            Marhoom, waive further Chanda subscriptions, and automatically reserve plot records in the burial register.
        </p>

        <form method="POST" action="actions.php" class="space-y-4">
            <input type="hidden" name="action" value="mark_deceased">
            <input type="hidden" name="id" id="modal-member-id">

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Date & Time of
                    Demise *</label>
                <input type="datetime-local" name="burial_datetime" required id="modal-burial-datetime"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Burial Plot
                    Location/Details *</label>
                <input type="text" name="plot_details" required placeholder="e.g. Block C, Row 4, Grave #12"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div class="flex items-center space-x-2 pt-2">
                <button type="button" onclick="closeDeceasedModal()"
                    class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2.5 rounded-xl text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="w-1/2 bg-rose-600 hover:bg-rose-700 text-white py-2.5 rounded-xl text-xs font-semibold shadow transition-colors">
                    Confirm Marhoom
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Baitul-Mal Aid Request -->
<div id="welfare-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6">
        <h4 class="text-lg font-bold text-slate-800 mb-2">New Baitul-Mal Aid Application</h4>
        <p class="text-xs text-slate-500 mb-4">Log a petition on behalf of an active under-privileged Jamaath family.
        </p>

        <form method="POST" action="actions.php" class="space-y-4">
            <input type="hidden" name="action" value="add_welfare">

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Applicant Full
                    Name *</label>
                <input type="text" name="name" required placeholder="Full Name"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Assistance Type
                    *</label>
                <select name="type" required
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="Higher Education Aid">Higher Education Aid</option>
                    <option value="Marriage Assistance">Marriage Assistance</option>
                    <option value="Medical Aid">Medical Aid</option>
                    <option value="Widow Monthly Support">Widow Monthly Support</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Requested Amount
                    (₹) *</label>
                <input type="number" name="amount" required placeholder="e.g. 15000"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="flex items-center space-x-2 pt-2">
                <button type="button" onclick="closeWelfareModal()"
                    class="w-1/2 bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold hover:bg-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="w-1/2 bg-emerald-700 hover:bg-emerald-800 text-white py-2.5 rounded-xl text-xs font-semibold shadow transition-colors">
                    File Application
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Nikah Ceremony Registration -->
<div id="nikah-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6">
        <h4 class="text-lg font-bold text-slate-800 mb-2">Register Nikah Ceremony</h4>
        <p class="text-xs text-slate-500 mb-4">Archive a certified Nikah marriage contract record directly within the
            regional ledger.</p>

        <form method="POST" action="actions.php" class="space-y-4">
            <input type="hidden" name="action" value="add_nikah">

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Groom Name
                    *</label>
                <input type="text" name="groom_name" required placeholder="e.g. Mohamed Anas"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Bride Name
                    *</label>
                <input type="text" name="bride_name" required placeholder="e.g. Shahana Fathima"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Date & Time of
                    Nikah *</label>
                <input type="datetime-local" name="nikah_datetime" required id="nikah-datetime-field"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Nikah Venue
                    (Place of Wedding) *</label>
                <input type="text" name="venue" required placeholder="e.g. Kuthpa Pallivasal, Vadasery"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>

            <div class="flex items-center space-x-2 pt-1">
                <input type="checkbox" name="conducted_by_jamath" value="1" checked id="conducted-by-jamath-check"
                    class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-slate-300 rounded">
                <label for="conducted-by-jamath-check" class="text-xs text-slate-700 font-medium select-none">Wedding
                    was conducted/officiated by this Jamaath</label>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Book &
                    Registration References *</label>
                <input type="text" name="details" required placeholder="e.g. Book #14, Page 104"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div class="flex items-center space-x-2 pt-2">
                <button type="button" onclick="closeNikahModal()"
                    class="w-1/2 bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold hover:bg-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="w-1/2 bg-teal-700 hover:bg-teal-800 text-white py-2.5 rounded-xl text-xs font-semibold shadow transition-colors">
                    Record Nikah
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Record Direct Burial Plot -->
<div id="burial-modal"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6">
        <h4 class="text-lg font-bold text-slate-800 mb-2">Record Burial Log</h4>
        <p class="text-xs text-slate-500 mb-4">Log cemetery plot information directly for deceased members.</p>

        <form method="POST" action="actions.php" class="space-y-4">
            <input type="hidden" name="action" value="add_burial">

            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Deceased Name
                    (Marhoom) *</label>
                <input type="text" name="deceased_name" required placeholder="e.g. Shahul Hameed"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Date & Time of
                    Burial *</label>
                <input type="datetime-local" name="burial_datetime" required id="burial-datetime-field"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Grave Location
                    Reference *</label>
                <input type="text" name="plot_details" required placeholder="e.g. Block C, Row 4, Grave #12"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
            </div>
            <div class="flex items-center space-x-2 pt-2">
                <button type="button" onclick="closeBurialModal()"
                    class="w-1/2 bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold hover:bg-slate-200 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="w-1/2 bg-rose-700 hover:bg-rose-800 text-white py-2.5 rounded-xl text-xs font-semibold shadow transition-colors">
                    Save Burial Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Slide-in notification banner -->
<div id="toast"
    class="fixed bottom-6 right-6 bg-slate-900 text-white px-5 py-3 rounded-xl shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50 flex items-center space-x-2 text-xs">
    <span id="toast-icon">✨</span>
    <span id="toast-message">Task updated!</span>
</div>

<!-- Footer area -->
<footer class="bg-slate-100 border-t border-slate-200 py-6 mt-12">
    <div class="max-w-7xl mx-auto px-4 text-center flex flex-col sm:flex-row items-center justify-between gap-4">
        <!-- Left Side: Copyright -->
        <p class="text-xs text-slate-500">&copy; <?php echo date('Y'); ?> NVK Muslim Jamaath, Nagercoil.</p>

        <!-- Right Side: Powered By Credit -->
        <p class="text-xs text-slate-500 font-medium">
            Powered by <a href="https://euroglobalconsultancy.com" target="_blank"
                class="text-blue-800 hover:text-blue-900 hover:underline font-bold transition-colors">Euro Global
                Consultancy</a>
        </p>
    </div>
</footer>

<script>

    // Slide-in toast controller
    function showToast(message, icon = "✨") {
        const toast = document.getElementById('toast');
        document.getElementById('toast-icon').textContent = icon;
        document.getElementById('toast-message').textContent = message;

        toast.classList.remove('translate-y-20', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');

        setTimeout(() => {
            toast.classList.remove('translate-y-0', 'opacity-100');
            toast.classList.add('translate-y-20', 'opacity-0');
        }, 3500);
    }

    // Member portrait base64 preview handler
    function handlePhotoChange(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('photo-preview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    // Toggle forms depending on active life status
    function toggleFormDeceasedDate(val) {
        const cont = document.getElementById('form-deceased-date-container');
        const field = document.getElementById('form-deceased-date-field');
        if (val === 'Deceased') {
            if (cont) cont.classList.remove('hidden');
            if (field) {
                field.required = true;
                field.value = new Date().toISOString().split('T')[0];
            }
        } else {
            if (cont) cont.classList.add('hidden');
            if (field) field.required = false;
        }
    }

    // Modal windows management
    function triggerDeceasedModal(id) {
        document.getElementById('modal-member-id').value = id;
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('modal-burial-datetime').value = `${year}-${month}-${day}T${hours}:${minutes}`;
        document.getElementById('deceased-modal').classList.remove('hidden');
    }

    function closeDeceasedModal() {
        document.getElementById('deceased-modal').classList.add('hidden');
    }

    function openWelfareModal() {
        document.getElementById('welfare-modal').classList.remove('hidden');
    }

    function closeWelfareModal() {
        document.getElementById('welfare-modal').classList.add('hidden');
    }

    function openNikahModal() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const f = document.getElementById('nikah-datetime-field');
        if (f) f.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        document.getElementById('nikah-modal').classList.remove('hidden');
    }

    function closeNikahModal() {
        document.getElementById('nikah-modal').classList.add('hidden');
    }

    function openBurialModal() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const f = document.getElementById('burial-datetime-field');
        if (f) f.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        document.getElementById('burial-modal').classList.remove('hidden');
    }

    function closeBurialModal() {
        document.getElementById('burial-modal').classList.add('hidden');
    }
</script>

<!-- Trigger system messages if redirects pass status text -->
<?php if (isset($_GET['msg'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            showToast(<?php echo json_encode($_GET['msg']); ?>, "✅");
        });
    </script>
<?php endif; ?>

</body>

</html>
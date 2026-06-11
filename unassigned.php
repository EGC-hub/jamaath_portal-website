<?php
require_once 'db.php';
require_once 'helpers.php';
require_once 'header.php';

// Grab the requested feature name from GET parameters
$feature_name = isset($_GET['feature']) ? htmlspecialchars($_GET['feature']) : 'Requested Module';
?>

<div class="max-w-2xl mx-auto my-12">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden">

        <!-- Premium Emerald/Teal Gradient Header Banner -->
        <div class="bg-gradient-to-r from-emerald-800 to-teal-950 p-8 text-white text-center relative overflow-hidden">
            <div class="absolute -right-12 -bottom-12 w-32 h-32 bg-emerald-700/20 rounded-full"></div>
            <div class="absolute -left-12 -top-12 w-32 h-32 bg-teal-700/20 rounded-full"></div>

            <span class="bg-emerald-500/20 text-emerald-300 text-3xl p-4 rounded-2xl inline-block shadow-inner mb-4">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </span>
            <h3 class="text-2xl font-extrabold serif-title uppercase tracking-wide">Feature Deactivated</h3>
            <p class="text-xs text-emerald-300 uppercase tracking-widest mt-1.5 font-semibold">Environment License
                Restriction</p>
        </div>

        <!-- System Message & Actions -->
        <div class="p-8 text-center space-y-6">
            <div class="space-y-2">
                <h4 class="text-lg font-bold text-slate-800">
                    The <span class="text-emerald-700">"
                        <?php echo $feature_name; ?>"
                    </span> module is offline
                </h4>
                <p class="text-xs text-slate-500 leading-relaxed max-w-md mx-auto">
                    This administrative workspace has not been assigned or deployed to your active organization's
                    environment. Security parameters have locked database routes to this node.
                </p>
            </div>

            <!-- Layman-friendly notice box -->
            <div
                class="bg-amber-50 border border-amber-200 p-4 rounded-2xl max-w-md mx-auto flex items-start gap-3.5 text-left text-amber-900">
                <span class="text-amber-600 text-lg mt-0.5"><i class="fa-solid fa-circle-exclamation"></i></span>
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-wide">How do I implement this?</p>
                    <p class="text-[11px] leading-relaxed mt-1 font-medium text-slate-600">
                        Please contact <strong class="text-slate-800">Euro Global Consultancy</strong> to implement this
                        feature as this feature has not been assigned to your environment yet.
                    </p>
                </div>
            </div>

            <!-- System Integrator Contact Desk -->
            <div class="border-t border-slate-150 pt-6 space-y-4">
                <div
                    class="inline-flex flex-col items-center justify-center p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs space-y-1">
                    <span class="font-extrabold text-slate-400 uppercase tracking-wider text-[9px]">Authorized System
                        Integrator</span>
                    <span class="text-sm font-bold text-emerald-800 serif-title">Euro Global Consultancy</span>
                </div>

                <div class="flex justify-center gap-3">
                    <a href="dashboard.php"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-5 py-3.5 rounded-xl transition-all shadow-sm flex items-center gap-1.5 uppercase">
                        <i class="fa-solid fa-arrow-left"></i> Return to Dashboard
                    </a>
                    <a href="mailto:enquiry@euroglobalconsultancy.com?subject=NVK%20Jamaath%20System%20-%20Module%20Activation%20Request%20(<?php echo urlencode($feature_name); ?>)"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-6 py-3.5 rounded-xl transition-all shadow flex items-center gap-1.5 uppercase tracking-wide">
                        <i class="fa-solid fa-paper-plane"></i> Request Activation
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>
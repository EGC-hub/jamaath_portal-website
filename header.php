<?php
require_once 'helpers.php';

// Enforce Private Session Lock - Redirect back to public login if unauthorized
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Find current active script name to highlight navigation dynamically
$active_script = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NVK Jamaath Portal</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Premium Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
        rel="stylesheet">
    <!-- Icon sets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .serif-title {
            font-family: 'Playfair Display', serif;
        }

        /* Hidden scrollbar utilities */
        .scrollbar-none::-webkit-scrollbar {
            display: none;
        }

        .scrollbar-none {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

    <!-- Header and Regional Navigation Banner -->
    <header class="bg-gradient-to-r from-emerald-800 to-teal-950 text-white shadow-md relative overflow-hidden">
        <div
            class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-emerald-700/30 via-transparent to-transparent">
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="flex items-center space-x-4">
                    <div
                        class="bg-gradient-to-br from-emerald-400 to-teal-600 text-teal-950 p-3 rounded-2xl shadow-xl font-bold text-2xl flex items-center justify-center animate-pulse">
                        🕌
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight serif-title">NVK Jamath PORTAL
                        </h1>
                        <p
                            class="text-xs md:text-sm text-emerald-300 font-semibold tracking-wider uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-map-location-dot"></i> NVK Jamaath Registry
                        </p>
                    </div>
                </div>
                <!-- Logout Action -->
                <div class="flex items-center space-x-3">
                    <div
                        class="hidden sm:flex items-center space-x-2 bg-emerald-950/60 backdrop-blur px-4 py-2 rounded-xl border border-emerald-700/50 shadow-inner">
                        <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-pulse"></span>
                        <span class="text-xs text-emerald-100 font-semibold">Authorized Session</span>
                    </div>
                    <a href="logout.php"
                        class="bg-rose-700/90 hover:bg-rose-800 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow flex items-center space-x-1.5">
                        <i class="fa-solid fa-power-off text-[10px]"></i>
                        <span>Log Out</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs pointing directly to modular php pages -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-1 overflow-x-auto py-2.5 scrollbar-none">

                <a href="dashboard.php"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'dashboard.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-chart-line"></i> <span>Dashboard</span>
                </a>

                <a href="members.php"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'members.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-users"></i> <span>Members Directory</span>
                </a>

                <a href="baitul_mal.php"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'baitul_mal.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-handshake-angle"></i> <span>Baitul-Mal (Welfare)</span>
                </a>

                <a href="nikah.php"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'nikah.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-ring"></i> <span>Nikah Registry</span>
                </a>

                <a href="burial.php"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'burial.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-monument"></i> <span>Burial Registry</span>
                </a>

            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
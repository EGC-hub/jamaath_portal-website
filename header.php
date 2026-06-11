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
    <title>NVK Muslim Jamaath Portal</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Premium Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
        rel="stylesheet">
    <!-- Icon sets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Globally scale base font size to be larger and more legible */
        html {
            font-size: 16px;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            /* Upgraded base size from standard 14px */
            line-height: 1.6;
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
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight serif-title">NVK Muslim Jamaath
                            Portal
                        </h1>
                        <p
                            class="text-xs md:text-sm text-emerald-300 font-semibold tracking-wider uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-map-location-dot"></i>Jamaath Registry
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
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm relative group">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative flex items-center">

            <button onclick="scrollNavbar('-') rectangular"
                class="absolute left-2 z-40 bg-white/90 backdrop-blur border border-slate-200 text-slate-600 hover:text-emerald-800 hover:border-emerald-300 w-8 h-8 rounded-lg shadow-sm flex items-center justify-center transition-all md:opacity-0 group-hover:opacity-100 focus:opacity-100 active:scale-95 cursor-pointer"
                title="Scroll Left">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </button>

            <div id="scrolling-navbar-strip"
                class="flex space-x-1 overflow-x-auto py-2.5 flex-row flex-nowrap scroll-smooth w-full style-custom-scrollbar pr-10">

                <a href="dashboard.php"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'dashboard.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-chart-line"></i> <span>Dashboard</span>
                </a>

                <a href="members.php"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'members.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-users"></i> <span>Members Directory</span>
                </a>

                <a href="baitul_mal.php"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'baitul_mal.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-handshake-angle"></i> <span>Baitul-Mal (Welfare)</span>
                </a>

                <a href="nikah.php"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'nikah.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-ring"></i> <span>Nikah Registry</span>
                </a>

                <a href="burial.php"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'burial.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-monument"></i> <span>Burial Registry</span>
                </a>

                <a href="manage_gallery.php"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo ($active_script == 'manage_gallery.php') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-images"></i> <span>Website CMS Gallery</span>
                </a>

                <a href="unassigned.php?feature=Backup+and+Restore"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Backup and Restore') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-database text-slate-400"></i> <span>Backup & Restore</span>
                </a>

                <a href="unassigned.php?feature=Academy"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Academy') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-graduation-cap text-slate-400"></i> <span>Academy</span>
                </a>

                <a href="unassigned.php?feature=Income+and+Expenses"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Income and Expenses') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-money-bill-transfer text-slate-400"></i> <span>Income & Expenses</span>
                </a>

                <a href="unassigned.php?feature=Arabic+School"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Arabic School') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-book-quran text-slate-400"></i> <span>Arabic School</span>
                </a>

                <a href="unassigned.php?feature=Employee+Salary"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Employee Salary') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-wallet text-slate-400"></i> <span>Employee Salary</span>
                </a>

                <a href="unassigned.php?feature=Transportation"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Transportation') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-bus text-slate-400"></i> <span>Transportation</span>
                </a>

                <a href="unassigned.php?feature=Property+Register"
                    class="whitespace-nowrap flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center space-x-2 <?php echo (isset($_GET['feature']) && $_GET['feature'] === 'Property Register') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                    <i class="fa-solid fa-building text-slate-400"></i> <span>Property Register</span>
                </a>
            </div>

            <button onclick="scrollNavbar('+')"
                class="absolute right-2 z-40 bg-white/90 backdrop-blur border border-slate-200 text-slate-600 hover:text-emerald-800 hover:border-emerald-300 w-8 h-8 rounded-lg shadow-sm flex items-center justify-center transition-all md:opacity-0 group-hover:opacity-100 focus:opacity-100 active:scale-95 cursor-pointer"
                title="Scroll Right">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </button>

        </div>
    </nav>

    <style>
        /* Premium Thin Custom Scrollbar for modern web engines */
        .style-custom-scrollbar::-webkit-scrollbar {
            height: 5px;
            /* Ultra sleek, non-intrusive thickness */
        }

        .style-custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            /* Light slate rail */
            border-radius: 10px;
        }

        .style-custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            /* Subtle default thumb state */
            border-radius: 10px;
            transition: background 0.2s ease;
        }

        .style-custom-scrollbar:hover::-webkit-scrollbar-thumb {
            background: #059669;
            /* Snaps to crisp brand Emerald green when working within the nav bar area */
        }
    </style>

    <script>
        /**
         * Native Smooth Step Scroller Engine
         * Moves the navigation container horizontally when arrows are clicked
         */
        function scrollNavbar(direction) {
            const navContainer = document.getElementById('scrolling-navbar-strip');
            if (navContainer) {
                // Scroll by a fixed 220px step interval per click
                const scrollAmount = direction === '+' ? 220 : -220;
                navContainer.scrollBy({
                    left: scrollAmount,
                    behavior: 'smooth'
                });
            }
        }
    </script>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
<?php
// Start dynamic session tracker
session_start();

// If already authenticated, bypass login gate immediately
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = null;

// Mock login parameters
$default_username = 'admin';
$default_password = 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Authenticatable for demo: username is 'admin', password is 'password'
    if ($username === $default_username && $password === $default_password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header("Location: dashboard.php?msg=Welcome back, administrator!");
        exit;
    } else {
        $error = "Invalid username or password credentials. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NVK Jamath Portal - Sign In</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Premium Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .serif-title {
            font-family: 'Playfair Display', serif;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col justify-between">

    <!-- Top Navigation Header -->
    <header class="bg-gradient-to-r from-emerald-800 to-teal-950 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <a href="index.html" class="flex items-center space-x-2.5 hover:opacity-90 transition-opacity">
                <span class="bg-emerald-700/60 p-1.5 rounded-lg">🕌</span>
                <span class="font-bold serif-title text-sm tracking-wide">NVK Jamath Portal</span>
            </a>
            <a href="index.html" class="text-xs text-emerald-300 hover:text-white font-semibold transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Home
            </a>
        </div>
    </header>

    <!-- Login card container workspace -->
    <main class="flex-grow flex items-center justify-center py-12 px-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <!-- Header banner inside login card -->
            <div class="bg-gradient-to-r from-emerald-800 to-teal-950 p-6 text-white text-center">
                <span class="text-3xl">🔒</span>
                <h3 class="text-lg font-bold serif-title mt-2">Private Access Gateway</h3>
                <p class="text-[10px] text-emerald-300 uppercase tracking-widest mt-1">Authorized Administrators Only</p>
            </div>

            <!-- Login input forms -->
            <form method="POST" action="" class="p-6 space-y-4 text-xs">
                
                <?php if ($error): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-xl flex items-center space-x-2">
                        <span class="text-sm">⚠️</span>
                        <p class="font-medium leading-relaxed"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Username / Email Address *</label>
                    <input type="text" name="username" required placeholder="admin" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Password *</label>
                    <input type="password" name="password" required placeholder="password" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-3 rounded-xl shadow-lg transition-colors text-xs uppercase tracking-wider">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer area -->
    <footer class="bg-slate-200 text-slate-500 text-center py-4 border-t border-slate-300 text-[10px]">
        <p>&copy; 2026 NVK Jamath, Nagercoil. Secure Management Access.</p>
    </footer>

</body>
</html>
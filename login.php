<?php
// Start dynamic session tracker
session_start();

// If already authenticated, bypass login gate immediately
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php"); // Updated fallback to match homepage landing indices
    exit;
}

$error = null;

// Multi-user dictionary holding initial roles (Hardcoded RBAC baseline)
$authorized_users = [
    'admin' => [
        'password' => 'password',
        'display_name' => 'Administrator',
        'role' => 'Admin'
    ],
    'president' => [
        'password' => 'president123',
        'display_name' => 'President',
        'role' => 'President'
    ],
    'treasurer' => [
        'password' => 'treasurer123',
        'display_name' => 'Treasurer',
        'role' => 'Treasurer'
    ],
    'secretary' => [
        'password' => 'secretary123',
        'display_name' => 'Secretary',
        'role' => 'Secretary'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    // Evaluate credentials matching
    if (array_key_exists($username, $authorized_users) && $authorized_users[$username]['password'] === $password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['display_name'] = $authorized_users[$username]['display_name'];
        $_SESSION['user_role'] = $authorized_users[$username]['role']; // Saved role to construct RBAC limits later

        header("Location: dashboard.php?msg=Welcome back, " . urlencode($authorized_users[$username]['display_name']) . "!");
        exit;
    } else {
        $error = "Invalid username or password credentials. Please check your spelling and try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NVK Muslim Jamaath Portal - Sign In</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Premium Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap"
        rel="stylesheet">
    <!-- FontAwesome Vector Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .serif-title {
            font-family: 'Playfair Display', Georgia, serif;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen flex flex-col justify-between">

    <!-- Primary login structure container -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200 shadow-2xl overflow-hidden">
            <!-- Decorative mosque background cover -->
            <div class="bg-gradient-to-r from-emerald-800 to-teal-950 p-8 text-center text-white relative">
                <div class="absolute -right-8 -bottom-8 w-24 h-24 bg-emerald-700/20 rounded-full"></div>
                <div class="text-4xl mb-3">🕌</div>
                <h2 class="text-2xl font-bold serif-title">NVK Muslim Jamaath</h2>
                <p class="text-xs text-emerald-200 mt-1 uppercase tracking-widest font-semibold">Vadasery Central Portal
                </p>
            </div>

            <!-- Login submission block -->
            <form method="POST" action="" class="p-8 space-y-4">
                <?php if ($error): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-xl flex items-start gap-2.5">
                        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                        <p class="text-xs font-semibold leading-relaxed"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Username
                        *</label>
                    <input type="text" name="username" required placeholder="e.g. president, treasurer..."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Password
                        *</label>
                    <input type="password" name="password" required placeholder="••••••••"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none focus:bg-white transition-all">
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-3.5 rounded-xl shadow-lg transition-colors text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In to Portal
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer area -->
    <footer
        class="bg-slate-200 text-slate-500 text-center py-4 border-t border-slate-300 text-[10px] font-bold uppercase tracking-wider">
        © <?php echo date('Y'); ?> NVK Muslim Jamaath Vadasery. All Rights Reserved.
    </footer>

</body>

</html>
<?php
require_once 'db.php';

// Pagination configurations
$limit = 6; // Standard 3-column desktop layout displays beautifully in sets of 6
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

// Retrieve total counts
$count_stmt = $db->query("SELECT COUNT(*) FROM gallery");
$total_records = $count_stmt->fetchColumn();

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1)
    $total_pages = 1;
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch active gallery items
$fetch_stmt = $db->prepare("SELECT * FROM gallery ORDER BY date_added DESC LIMIT ? OFFSET ?");
$fetch_stmt->bindValue(1, $limit, PDO::PARAM_INT);
$fetch_stmt->bindValue(2, $offset, PDO::PARAM_INT);
$fetch_stmt->execute();
$gallery_items = $fetch_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NVK Muslim Jamaath Nagercoil - Gallery Directory</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Premium Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
        rel="stylesheet">
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

<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col justify-between">

    <header class="bg-gradient-to-r from-emerald-800 to-teal-950 text-white sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <span
                        class="bg-gradient-to-br from-emerald-400 to-teal-600 text-teal-950 p-2.5 rounded-xl text-xl shadow-md">🕌</span>
                    <div>
                        <h1 class="text-lg md:text-xl font-bold tracking-tight serif-title uppercase">NVK Muslim Jamaath
                        </h1>
                        <p class="text-[9px] text-emerald-300 font-semibold tracking-wider uppercase">Nagercoil,
                            Kanyakumari District</p>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex space-x-8 text-sm font-semibold">
                    <a href="index.html" class="text-slate-300 hover:text-white transition-colors">Home</a>
                    <a href="gallery.php" class="text-emerald-300 hover:text-white transition-colors">Gallery</a>
                    <a href="contact.html" class="text-slate-300 hover:text-white transition-colors">Contact Us</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center space-x-3">
                    <a href="login.php"
                        class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold text-xs px-5 py-2.5 rounded-xl shadow-lg transition-all duration-150 flex items-center space-x-1.5">
                        <i class="fa-solid fa-lock text-[10px]"></i>
                        <span>Sign In</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Banner Hero Area -->
    <section class="bg-gradient-to-r from-emerald-950 to-teal-900 text-white py-12 relative overflow-hidden">
        <div
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-teal-700/30 via-transparent to-transparent">
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-2">
            <h2 class="text-3xl md:text-4xl font-black serif-title uppercase">NVK Gallery</h2>
            <p class="text-xs md:text-sm text-emerald-200 font-medium tracking-wide max-w-lg mx-auto">Archiving and
                documenting our cooperative religious congregations, welfare distribution assemblies, and cemetery
                maintenance efforts.</p>
        </div>
    </section>

    <!-- Gallery Container -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">

        <?php if (empty($gallery_items)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-3">
                <span class="text-4xl">📸</span>
                <h4 class="text-base font-bold text-slate-800">No public gallery items published</h4>
                <p class="text-xs text-slate-500">The Jamaath administration team has not published public imagery
                    yet. Please sign in to the portal to add media assets.</p>
            </div>
        <?php else: ?>

            <!-- Cards Grid (Strictly cropped uniformly for beautiful alignment) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($gallery_items as $item): ?>
                    <div onclick='openImageLightbox(<?php echo json_encode($item); ?>)'
                        class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm hover:shadow-lg hover:border-slate-300 transition-all duration-300 cursor-pointer flex flex-col group">

                        <!-- Uniform cropped image frame (w-full h-64 object-cover) -->
                        <div class="w-full h-64 bg-slate-150 overflow-hidden relative">
                            <img src="<?php echo $item['image_path']; ?>"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                alt="Published Event">
                            <div
                                class="absolute inset-0 bg-slate-950/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span
                                    class="bg-white/90 text-slate-800 p-2.5 rounded-full text-xs font-bold shadow-md flex items-center gap-1.5 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                                    <i class="fa-solid fa-expand"></i> View High-Res
                                </span>
                            </div>
                        </div>

                        <!-- Card text block -->
                        <div class="p-5 flex-grow flex flex-col justify-between space-y-2">
                            <div>
                                <h3
                                    class="font-extrabold text-slate-800 text-sm group-hover:text-emerald-700 transition-colors">
                                    <?php echo htmlspecialchars($item['heading']); ?>
                                </h3>
                                <p class="text-xs text-slate-500 leading-relaxed mt-1 line-clamp-3">
                                    <?php echo htmlspecialchars($item['caption']); ?>
                                </p>
                            </div>
                            <div
                                class="border-t border-slate-100 pt-3 flex items-center justify-between text-[10px] text-slate-400 font-bold font-mono">
                                <span>📅
                                    <?php echo date('d M Y', strtotime($item['date_added'])); ?>
                                </span>
                                <span class="text-emerald-700 tracking-wider uppercase">NVK Published</span>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Public Pagination Grid controls -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center space-x-1.5 pt-12 text-xs">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>"
                            class="bg-white border border-slate-200 px-3 py-2 rounded-lg font-bold text-slate-700 hover:bg-slate-100 transition-all">&laquo;
                            Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>"
                            class="px-3 py-2 rounded-lg font-bold border transition-all <?php echo $i == $page ? 'bg-emerald-700 border-emerald-700 text-white' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>"
                            class="bg-white border border-slate-200 px-3 py-2 rounded-lg font-bold text-slate-700 hover:bg-slate-100 transition-all">Next
                            &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- Modal Lightbox (Popup for full image viewing) -->
    <div id="lightbox-modal" onclick="closeImageLightbox()"
        class="fixed inset-0 bg-slate-950/90 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4 transition-all duration-300">
        <div class="max-w-4xl w-full flex flex-col justify-center items-center relative animate-fade-in"
            onclick="event.stopPropagation()">

            <button onclick="closeImageLightbox()"
                class="absolute -top-10 right-0 md:-right-10 text-white/80 hover:text-white transition-colors text-2xl">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>

            <!-- Lightbox Frame -->
            <div
                class="bg-white rounded-2xl border border-slate-800 shadow-2xl overflow-hidden w-full flex flex-col max-h-[85vh]">
                <div class="flex-grow overflow-hidden bg-slate-950 flex items-center justify-center min-h-[300px]">
                    <img id="lightbox-image" src="" class="max-w-full max-h-[60vh] object-contain">
                </div>
                <div class="p-6 bg-white space-y-1 border-t border-slate-100 text-xs text-slate-600">
                    <h4 id="lightbox-title" class="font-extrabold text-slate-900 text-base">---</h4>
                    <p id="lightbox-caption" class="leading-relaxed">---</p>
                    <p id="lightbox-meta" class="text-[10px] text-slate-400 font-bold font-mono pt-2">---</p>
                </div>
            </div>

        </div>
    </div>

    <script>
        function openImageLightbox(item) {
            document.getElementById('lightbox-image').src = item.image_path;
            document.getElementById('lightbox-title').textContent = item.heading;
            document.getElementById('lightbox-caption').textContent = item.caption;

            const options = { day: '2-digit', month: 'short', year: 'numeric' };
            const dateStr = new Date(item.date_added).toLocaleDateString('en-US', options);
            document.getElementById('lightbox-meta').textContent = "Uploaded Registry Date: " + dateStr + " | NVK Published";

            document.getElementById('lightbox-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeImageLightbox() {
            document.getElementById('lightbox-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Close lightbox on Escape key
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeImageLightbox();
            }
        });
    </script>

    <!-- Footer area -->
    <footer class="bg-slate-950 text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="space-y-3">
                <p class="text-white serif-title font-bold text-lg">NVK Muslim Jamaath Registry</p>
                <p class="text-xs leading-relaxed">Maintaining peace, transparency, and cooperative community service in
                    the Vadasery region, Nagercoil, Kanyakumari District, Tamil Nadu.</p>
            </div>
            <div>
                <p class="text-white font-bold text-sm mb-3">Quick Navigation</p>
                <ul class="text-xs space-y-2 font-semibold">
                    <li><a href="index.html" class="hover:text-white transition-colors">Home</a></li>
                    <li><a href="gallery.php" class="hover:text-white transition-colors">Gallery Directory</a></li>
                    <li><a href="contact.html" class="hover:text-white transition-colors">Contact Office</a></li>
                </ul>
            </div>
            <div>
                <p class="text-white font-bold text-sm mb-3">Office Desk Address</p>
                <p class="text-xs leading-relaxed">
                    Secretary, Kuthpa Pallivasal, MS Road,<br>
                    Vadasery, Nagercoil - 629001.<br>
                    Tamil Nadu, India.
                </p>
            </div>
        </div>
        <div
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 mt-8 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-slate-500">&copy;
                <?php echo date('Y'); ?> NVK Muslim Jamaath, Nagercoil. All Rights Reserved.
            </p>
            <p class="text-xs text-slate-500 font-semibold">
                Powered by <a href="https://euroglobalconsultancy.com" target="_blank"
                    class="text-blue-500 hover:text-blue-400 hover:underline transition-colors">Euro Global
                    Consultancy</a>
            </p>
        </div>
    </footer>

</body>

</html>
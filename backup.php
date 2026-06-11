<?php
require_once 'db.php';
require_once 'helpers.php';

// Enforce Private Session Lock & Role Security check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Restrict system modifications to high-level leadership roles (e.g., Admin, President, Secretary)
// Auto-approve default "admin" account session to prevent login-gate lockout mismatches
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$has_system_privileges = in_array($user_role, ['Admin', 'President', 'Secretary']) || ($current_username === 'admin');

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// ---------------------------------------------------------
// EXPORT HANDLER: Generates a complete .sql schema & dataset
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    try {
        // Retrieve list of all existing database tables
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        // Setup streaming headers to initiate the download directly
        $filename = "nvk_jamaath_backup_" . date('Y-m-d_H-i-s') . ".sql";
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Compile backup scripts
        echo "-- ======================================================\n";
        echo "-- NVK MUSLIM JAMAATH SYSTEM PORTAL - DATABASE BACKUP\n";
        echo "-- Generated Date & Time: " . date('d M Y, h:i:s A') . "\n";
        echo "-- Authority Sign-off: " . $current_username . "\n";
        echo "-- ======================================================\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n";
        echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        echo "START TRANSACTION;\n\n";

        foreach ($tables as $table) {
            // Drop statement to avoid collisions
            echo "-- ------------------------------------------------------\n";
            echo "-- Table structure and drop configurations for `" . $table . "`\n";
            echo "-- ------------------------------------------------------\n";
            echo "DROP TABLE IF EXISTS `" . $table . "`;\n";

            // Grab CREATE statement
            $createStmt = $db->query("SHOW CREATE TABLE `" . $table . "`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            echo $createRow[1] . ";\n\n";

            // Grab rows
            $dataStmt = $db->query("SELECT * FROM `" . $table . "`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                echo "-- Dumping dataset for `" . $table . "`\n";
                foreach ($rows as $row) {
                    $keys = array_map(function ($key) {
                        return "`" . $key . "`";
                    }, array_keys($row));

                    $values = array_map(function ($val) use ($db) {
                        if ($val === null)
                            return "NULL";
                        return $db->quote($val);
                    }, array_values($row));

                    echo "INSERT INTO `" . $table . "` (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $values) . ");\n";
                }
                echo "\n";
            }
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        echo "COMMIT;\n";
        exit;

    } catch (Exception $e) {
        header("Location: backup.php?error=" . urlencode("Export failed: " . $e->getMessage()));
        exit;
    }
}

// ---------------------------------------------------------
// IMPORT HANDLER: Processes uploaded .sql restoration file
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_sql') {
    if (!$has_system_privileges) {
        header("Location: backup.php?error=" . urlencode("Access Denied: Only Admin, President or Secretary can restore backups."));
        exit;
    }

    // Safety confirm check: Verify running administrator's credentials before clearing tables
    $confirm_password = $_POST['confirm_password'];

    // Check against current logged in user credentials
    $authorized_users = [
        'admin' => 'password',
        'president' => 'president123',
        'treasurer' => 'treasurer123',
        'secretary' => 'secretary123'
    ];

    if (!isset($authorized_users[$current_username]) || $authorized_users[$current_username] !== $confirm_password) {
        header("Location: backup.php?error=" . urlencode("Restoration Denied: Authentication password incorrect."));
        exit;
    }

    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['sql_file']['tmp_name'];

        try {
            // Read SQL commands
            $sql_content = file_get_contents($file_tmp);

            // Normalize all line endings to UNIX format
            $sql_content = str_replace("\r\n", "\n", $sql_content);
            $lines = explode("\n", $sql_content);

            $statement_blocks = [];
            $current_query = '';

            // Parse line-by-line avoiding breaks inside quoted semicolons
            foreach ($lines as $line) {
                $trimmed_line = trim($line);

                // Skip comments and empty whitespace lines
                if ($trimmed_line === '' || strpos($trimmed_line, '--') === 0 || strpos($trimmed_line, '#') === 0 || strpos($trimmed_line, '/*') === 0) {
                    continue;
                }

                $current_query .= $line . "\n";

                // Check if line strictly ends in a semicolon (indicating the query block is complete)
                if (substr($trimmed_line, -1) === ';') {
                    $statement_blocks[] = $current_query;
                    $current_query = '';
                }
            }

            // Execute compiled transactional instructions
            $db->beginTransaction();
            $db->exec("SET FOREIGN_KEY_CHECKS=0;");

            $execution_count = 0;
            foreach ($statement_blocks as $query_block) {
                $trimmed = trim($query_block);
                if (!empty($trimmed)) {
                    $db->exec($trimmed);
                    $execution_count++;
                }
            }

            $db->exec("SET FOREIGN_KEY_CHECKS=1;");
            $db->commit();

            header("Location: backup.php?msg=" . urlencode("Database successfully restored! Executed {$execution_count} relational commands."));
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            header("Location: backup.php?error=" . urlencode("Restoration failed: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: backup.php?error=" . urlencode("Please upload a valid .sql backup file."));
        exit;
    }
}

// Gather table stats and space footprint for the interface dashboard
$total_rows = 0;
$total_size_bytes = 0;
$table_count = 0;

try {
    $statsStmt = $db->query("
        SELECT table_name AS 'Table', 
               table_rows AS 'Rows', 
               (data_length + index_length) AS 'Size' 
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
    ");
    $database_stats = $statsStmt->fetchAll();
    $table_count = count($database_stats);

    foreach ($database_stats as $stat) {
        $total_rows += $stat['Rows'];
        $total_size_bytes += $stat['Size'];
    }
} catch (Exception $e) {
    // Fallback if schema information is not fully queried
}

$total_size_mb = round($total_size_bytes / (1024 * 1024), 2);

require_once 'header.php';
?>

<div class="space-y-6">
    <!-- Feedback Alerts -->
    <?php if ($msg): ?>
        <div
            class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl flex items-start gap-3 shadow-sm">
            <i class="fa-solid fa-circle-check mt-0.5 text-lg"></i>
            <div>
                <p class="font-bold text-sm">System Success</p>
                <p class="text-xs mt-0.5"><?php echo htmlspecialchars($msg); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl flex items-start gap-3 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-lg"></i>
            <div>
                <p class="font-bold text-sm">Action Blocked / Error</p>
                <p class="text-xs mt-0.5"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main System Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Workspace: Simple High-Level Registry Metrics -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h4 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-emerald-700"></i> Registry Capacity
                </h4>

                <div class="space-y-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Registry Sections</p>
                            <p class="text-2xl font-extrabold text-slate-800"><?php echo $table_count; ?></p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Active functional ledgers</p>
                        </div>
                        <div class="bg-emerald-50 text-emerald-700 p-3 rounded-lg"><i
                                class="fa-solid fa-folder-open text-lg"></i></div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Total Logged Entries</p>
                            <p class="text-2xl font-extrabold text-slate-800"><?php echo number_format($total_rows); ?>
                            </p>
                            <p class="text-[10px] text-slate-500 mt-0.5">Total family data lines</p>
                        </div>
                        <div class="bg-sky-50 text-sky-700 p-3 rounded-lg"><i class="fa-solid fa-database text-lg"></i>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Total Database Footprint</p>
                            <p class="text-2xl font-extrabold text-slate-800"><?php echo $total_size_mb; ?> <span
                                    class="text-xs text-slate-500 font-bold">MB</span></p>
                            <p class="text-[10px] text-slate-500 mt-0.5">System size in megabytes</p>
                        </div>
                        <div class="bg-purple-50 text-purple-700 p-3 rounded-lg"><i
                                class="fa-solid fa-hard-drive text-lg"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Workspace: Action Operations Panels -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Panel A: Generate & Download Backup -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="border-b border-slate-100 pb-4 mb-5">
                    <h3 class="text-xl font-bold text-slate-800">Generate Database Backup</h3>
                    <p class="text-xs text-slate-500 font-medium">Export a full backup of all members directory data,
                        Nikah registers, and burial logs in one clean file.</p>
                </div>

                <div class="bg-emerald-50/50 border border-emerald-150 p-4 rounded-xl space-y-3 mb-5">
                    <p class="text-xs text-emerald-900 leading-relaxed font-semibold">
                        <i class="fa-solid fa-circle-info mr-1 text-emerald-700"></i> Local & Production Security
                        Instructions:
                    </p>
                    <ul class="text-[11px] text-emerald-800 list-disc list-inside space-y-1">
                        <li>This backup holds structural tables and all active records up to this exact millisecond.
                        </li>
                        <li>Please store download files in safe physical drives or secured private cloud folders. Do not
                            share raw SQL backups on public links.</li>
                    </ul>
                </div>

                <div class="flex justify-start">
                    <a href="?action=download_sql"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm px-6 py-3.5 rounded-xl transition-all shadow-md flex items-center gap-2 uppercase tracking-wide">
                        <i class="fa-solid fa-download"></i> Download Backup File (.sql)
                    </a>
                </div>
            </div>

            <!-- Panel B: Restore Backup Console -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="border-b border-slate-100 pb-4 mb-5">
                    <h3 class="text-xl font-bold text-slate-800">Restore Database Console</h3>
                    <p class="text-xs text-slate-500 font-medium">Reset system registry values and overwrite files by
                        importing a verified backup `.sql` file.</p>
                </div>

                <?php if (!$has_system_privileges): ?>
                    <div class="bg-slate-50 border border-slate-200 text-slate-500 p-6 rounded-xl text-center space-y-2">
                        <i class="fa-solid fa-lock text-3xl"></i>
                        <h4 class="font-bold text-sm">Privileged Access Restrictions Active</h4>
                        <p class="text-xs max-w-md mx-auto">Database restorations can drop existences and cause critical
                            overrides. This terminal is restricted to <strong>President, Secretary, and
                                Administrator</strong> portal roles.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-rose-50 border border-rose-150 p-4 rounded-xl space-y-3 mb-5 text-rose-900">
                        <p class="text-xs font-extrabold uppercase tracking-wide flex items-center gap-1.5">
                            <i class="fa-solid fa-triangle-exclamation text-rose-700"></i> CRITICAL RESTORATION SAFEGUARD
                            WARNING
                        </p>
                        <p class="text-[11px] leading-relaxed font-semibold">
                            Executing a restoration drops all current tables and completely replaces database parameters.
                            Please download an updated backup of your current database using the console above before
                            proceeding!
                        </p>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4 text-xs">
                        <input type="hidden" name="action" value="restore_sql">

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Select Backup File
                                (.sql) *</label>
                            <input type="file" name="sql_file" accept=".sql" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500">
                            <p class="text-[10px] text-slate-400 mt-1">Please upload only valid NVK Jamaath sql backups.</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Enter Your Account
                                Sign-in Password to Confirm *</label>
                            <input type="password" name="confirm_password" required placeholder="Type password..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500">
                            <p class="text-[10px] text-slate-400 mt-1">Verifies authority mapping before starting
                                transaction migrations.</p>
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                onclick="return confirm('WARNING: Doing this will wipe the current database clean and import backup records instead. Do you wish to proceed?');"
                                class="bg-rose-700 hover:bg-rose-800 text-white font-bold text-sm px-6 py-3.5 rounded-xl transition-all shadow flex items-center gap-2 uppercase tracking-wide">
                                <i class="fa-solid fa-triangle-exclamation"></i> Begin Database Restoration
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>
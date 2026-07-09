<?php
require_once 'db.php';
require_once 'helpers.php';

if (!isset($_GET['enrollment_id'])) {
    die("<p style='font-family:sans-serif; text-align:center; margin-top:50px; color:#ef4444; font-weight:bold;'>Security Intercept: Missing enrollment token context.</p>");
}

try {
    $enrollmentId = intval($_GET['enrollment_id']);

    // Core query expanded to capture lifecycle timeline strings
    $query = "
        SELECT 
            ae.id AS enrollment_id,
            ae.status,
            ae.start_date,
            ae.end_date,
            s.first_name,
            s.last_name,
            s.student_reg_no,
            ac.course_code,
            ac.course_name,
            ac.standard_fee,
            ac.duration_value,
            ac.duration_unit,
            CASE 
                WHEN ac.duration_unit = 'Days' THEN (ac.standard_fee / 30.0) * ac.duration_value
                ELSE ac.standard_fee * ac.duration_value
            END AS computed_liability,
            COALESCE((SELECT SUM(p.amount_paid) FROM academic_fee_payments p WHERE p.enrollment_id = ae.id), 0.00) AS total_paid
        FROM academic_enrollments ae
        INNER JOIN academic_students s ON ae.student_id = s.id
        INNER JOIN academic_courses ac ON ae.course_id = ac.id
        WHERE ae.id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$enrollmentId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die("<p style='font-family:sans-serif; text-align:center; margin-top:50px; color:#ef4444; font-weight:bold;'>Data Fault: Academic track lookup returned zero results.</p>");
    }

    // Evaluate exact Phase 2 functional condition guardrails
    $liability = round(floatval($record['computed_liability']), 2);
    $paid = round(floatval($record['total_paid']), 2);
    $outstanding = max(0.00, $liability - $paid);

    if ($record['status'] !== 'completed' || $outstanding >= 0.01) {
        die("<p style='font-family:sans-serif; text-align:center; margin-top:50px; color:#b45309; font-weight:bold;'>Security Intercept: Profile ineligible for certificate minting.</p>");
    }

    // Construct system cryptographic verification tracking sequence
    $uniqueCertHash = "CERT-" . date('Y') . "-" . $record['enrollment_id'] . "-" . str_pad($record['enrollment_id'], 4, '0', STR_PAD_LEFT);
    $fullName = trim($record['first_name'] . ' ' . $record['last_name']);

    // Standardize timeline text variables (DD-MM-YYYY)
    $startDateFormatted = $record['start_date'] ? date('d-m-Y', strtotime($record['start_date'])) : '--/--/----';
    $endDateFormatted = $record['end_date'] ? date('d-m-Y', strtotime($record['end_date'])) : date('d-m-Y');

    // Compile clean human-readable duration metrics strings (e.g., "5 Months" / "45 Days")
    $durationFootprint = $record['duration_value'] . ' ' . $record['duration_unit'];

} catch (PDOException $e) {
    die("Database Engine Fault: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Certificate —
        <?php echo htmlspecialchars($fullName); ?>
    </title>
    <style>
        /* Base page reset and landscape print parameters configuration */
        html,
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size: letter landscape;
            margin: 0;
        }

        /* Printable structural container chassis */
        .certificate-page-canvas {
            width: 11in;
            height: 8.5in;
            padding: 0.5in;
            box-sizing: border-box;
            background: #ffffff;
            font-family: 'Georgia', serif;
            position: relative;
            overflow: hidden;
        }

        .certificate-border-frame {
            border: 12px double #064e3b;
            padding: 45px;
            height: 100%;
            box-sizing: border-box;
            position: relative;
            background-image: radial-gradient(circle, #f0fdf4 1px, transparent 1px);
            background-size: 24px 24px;
            background-color: #fbfdfb;
        }

        /* Corner accents styled symmetrically */
        .corner-star {
            position: absolute;
            color: #047857;
            font-size: 16px;
            font-weight: bold;
            font-family: sans-serif;
        }

        .top-left {
            top: 15px;
            left: 15px;
        }

        .top-right {
            top: 15px;
            right: 15px;
        }

        .bottom-left {
            bottom: 15px;
            left: 15px;
        }

        .bottom-right {
            bottom: 15px;
            right: 15px;
        }

        /* Typography Styles */
        .header-title {
            text-align: center;
            margin-bottom: 25px;
            padding-top: 5px;
        }

        .header-title h1 {
            margin: 0;
            color: #064e3b;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .header-title p {
            margin: 6px 0 0 0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 4px;
            font-weight: bold;
            color: #047857;
            font-family: sans-serif;
        }

        .divider-line {
            width: 250px;
            height: 2px;
            background: linear-gradient(to right, transparent, #d97706, transparent);
            margin: 12px auto 4px auto;
        }

        .statement-block {
            text-align: center;
            margin-top: 35px;
            margin-bottom: 35px;
        }

        .statement-block h2 {
            font-family: 'Times New Roman', Times, serif;
            font-style: italic;
            color: #b45309;
            font-size: 28px;
            margin: 5px 0;
            font-weight: 500;
        }

        .statement-block p {
            font-size: 14px;
            color: #475569;
            margin: 18px 0 6px 0;
            font-family: sans-serif;
            line-height: 1.6;
            letter-spacing: 0.5px;
        }

        .student-focus-box {
            text-align: center;
            margin-bottom: 45px;
            padding: 0 40px;
            margin-top: 30px;
        }

        .student-name-line {
            font-size: 30px;
            color: #0f172a;
            font-weight: bold;
            border-bottom: 2px solid #064e3b;
            padding-bottom: 6px;
            display: inline-block;
            min-w: 450px;
            font-family: 'Georgia', serif;
        }

        .student-reg-sub {
            display: block;
            font-size: 11px;
            color: #64748b;
            font-family: monospace;
            text-transform: uppercase;
            margin-top: 10px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .course-title-display {
            margin: 12px 0;
            color: #064e3b;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 0.5px;
            font-family: 'Georgia', serif;
        }

        /* Footer Authority Blocks */
        .footer-signatures-wrapper {
            position: absolute;
            bottom: 45px;
            left: 45px;
            right: 45px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-line-block {
            text-align: center;
            width: 200px;
        }

        .signature-label {
            border-top: 1px dashed #94a3b8;
            padding-top: 8px;
            font-size: 11px;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-family: sans-serif;
            letter-spacing: 0.5px;
        }

        .center-verification-badge {
            text-align: center;
            min-w: 240px;
        }

        .system-id-label {
            font-family: monospace;
            font-size: 10px;
            color: #64748b;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .audit-trail-pill {
            border: 1px solid #059669;
            border-radius: 4px;
            background: #f0fdf4;
            padding: 6px 14px;
            font-family: sans-serif;
            font-size: 10px;
            color: #047857;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
        }

        /* Standard top interaction controls bar for screen preview navigation layout */
        .print-control-header-bar {
            background: #0f172a;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: sans-serif;
            color: #ffffff;
            border-bottom: 1px solid #334155;
        }

        .print-btn-action {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .print-btn-action:hover {
            background: #059669;
        }

        @media print {
            .print-control-header-bar {
                display: none !important;
            }

            .certificate-page-canvas {
                width: 11in;
                height: 8.5in;
            }
        }
    </style>
</head>

<body>

    <div class="print-control-header-bar">
        <div style="display: flex; items-center: center; gap: 8px;">
            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
            <span
                style="font-size: 11px; font-weight: bold; text-transform: uppercase; font-family: monospace; color: #cbd5e1;">Curriculum
                Registry Print Workspace</span>
        </div>
        <button class="print-btn-action" onclick="window.print();">Print / Save as PDF</button>
    </div>

    <div class="certificate-page-canvas">
        <div class="certificate-border-frame"
            style="display: flex; flex-direction: column; justify-content: space-between; padding: 50px;">

            <div class="corner-star top-left">✦</div>
            <div class="corner-star top-right">✦</div>
            <div class="corner-star bottom-left">✦</div>
            <div class="corner-star bottom-right">✦</div>

            <div class="header-title" style="margin-bottom: 0;">
                <h1>Academic Management Center</h1>
                <p>Official Verification & Curriculum Registry Engine</p>
                <div class="divider-line"></div>
            </div>

            <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; margin: 20px 0;">

                <div class="statement-block" style="margin-top: 0; margin-bottom: 25px;">
                    <h2>Certificate of Merit & Completion</h2>
                    <p style="margin-top: 12px; margin-bottom: 0;">This document certifies that the undergraduate
                        student record identifier named below has fulfilled<br>all dynamic institutional obligations,
                        academic modules, and trailing financial ledger reconciliations.</p>
                </div>

                <div class="student-focus-box" style="margin-top: 0; margin-bottom: 0; padding: 0 40px;">
                    <div style="margin-bottom: 15px;">
                        <span class="student-name-line"><?php echo htmlspecialchars($fullName); ?></span>
                        <span class="student-reg-sub">Registration Number:
                            <?php echo htmlspecialchars($record['student_reg_no']); ?></span>
                    </div>

                    <div style="margin-top: 25px;">
                        <p style="margin: 0; font-size: 14px; color: #475569; font-family: sans-serif;">
                            has successfully achieved graduation parameters for a program of <strong
                                style="color: #1e293b;"><?php echo htmlspecialchars($durationFootprint); ?></strong> in
                            the specialized curriculum:
                        </p>
                        <h3 class="course-title-display" style="margin: 12px 0;">
                            <?php echo htmlspecialchars($record['course_name']); ?>
                            (<?php echo htmlspecialchars($record['course_code']); ?>)</h3>

                        <p
                            style="margin: 12px 0 0 0; font-size: 12px; color: #64748b; font-family: sans-serif; letter-spacing: 0.5px;">
                            Course Tenure Duration Lifecycle: <span
                                style="font-family: monospace; font-weight: bold; color: #334155; border-bottom: 1px dashed #cbd5e1; padding: 0 4px;"><?php echo $startDateFormatted; ?></span>
                            to <span
                                style="font-family: monospace; font-weight: bold; color: #334155; border-bottom: 1px dashed #cbd5e1; padding: 0 4px;"><?php echo $endDateFormatted; ?></span>
                        </p>
                    </div>
                </div>

            </div>

            <div class="footer-signatures-wrapper"
                style="position: relative; bottom: auto; left: auto; right: auto; padding: 0;">
                <div class="signature-line-block">
                    <div class="signature-label">Authorized Registrar</div>
                </div>

                <div class="center-verification-badge">
                    <div class="system-id-label">SYSTEM ID: <?php echo htmlspecialchars($uniqueCertHash); ?></div>
                    <div class="audit-trail-pill">✓ Verified Audit Trail Ledger Clear</div>
                </div>

                <div class="signature-line-block">
                    <div class="signature-label">Head of Institution</div>
                </div>
            </div>

        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => { window.print(); }, 300);
        });
    </script>
</body>

</html>
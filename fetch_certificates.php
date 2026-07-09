<?php
require_once 'db.php';
require_once 'helpers.php';

header('Content-Type: application/json');

if (!isset($_GET['student_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing student identification token']);
    exit;
}

try {
    $studentId = intval($_GET['student_id']);

    // Exact SQL execution mapping Phase 2 formulas directly in the database
    $query = "
    SELECT 
        ae.id AS enrollment_id,
        ae.status,
        ae.cancellation_source,
        ae.start_date,
        ae.end_date,
        ae.accumulated_pause_days,
        ac.course_code,
        ac.course_name,
        ac.standard_fee,
        ac.duration_value,
        ac.duration_unit,
        
        -- Master Financial Engine Alignment Layer
        CASE 
            -- Rule A: Institutional Cancellation Multi-Variable Pro-Ration Formula
            WHEN ae.status = 'cancelled' AND ae.cancellation_source = 'institution' THEN
                ROUND(
                    (ac.standard_fee / 30.0) * (DATEDIFF(IFNULL(ae.end_date, CURRENT_DATE), ae.start_date) - ae.accumulated_pause_days),
                    2
                )
            
            -- Rule B: Student Cancellation Matrix Adjustment (Rewrites liability to equal aggregate payments logged)
            WHEN ae.status = 'cancelled' AND ae.cancellation_source = 'student' THEN
                ROUND(
                    COALESCE((SELECT SUM(p2.amount_paid) FROM academic_fee_payments p2 WHERE p2.enrollment_id = ae.id), 0.00),
                    2
                )
                
            -- Rule C: Standard Duration Cost Formulas (Days Metric Fractional Unit vs Month Baseline)
            WHEN ac.duration_unit = 'Days' THEN 
                ROUND((ac.standard_fee / 30.0) * ac.duration_value, 2)
            ELSE 
                ROUND(ac.standard_fee * ac.duration_value, 2)
        END AS computed_liability,

        COALESCE((
            SELECT SUM(p.amount_paid) 
            FROM academic_fee_payments p 
            WHERE p.enrollment_id = ae.id
        ), 0.00) AS total_paid
    FROM academic_enrollments ae
    INNER JOIN academic_courses ac ON ae.course_id = ac.id
    WHERE ae.student_id = ?
    ORDER BY ac.course_code ASC
";

    $stmt = $db->prepare($query);
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Final precision balancing normalization loop
    $tracks = [];
    foreach ($rows as $row) {
        $liability = round(floatval($row['computed_liability']), 2);
        $paid = round(floatval($row['total_paid']), 2);
        $balance = max(0.00, $liability - $paid);

        $tracks[] = [
            'enrollment_id' => intval($row['enrollment_id']),
            'status' => $row['status'],
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'computed_liability' => $liability,
            'total_paid' => $paid,
            'outstanding_balance' => $balance
        ];
    }

    echo json_encode(['success' => true, 'tracks' => $tracks]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database operation error: ' . $e->getMessage()]);
}
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Action: Register New Member with Relational Dependents (File Directory Mode)
        if ($_POST['action'] === 'add_member') {
            $db->beginTransaction();
            try {
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $family_name = trim($_POST['family_name']);
                $father = trim($_POST['father_husband_name']);
                $card = trim($_POST['card_no']);
                $dependents_count = (int) $_POST['dependents_count'];
                $dob = $_POST['dob'];
                $gender = $_POST['gender'];
                $marital_status = $_POST['marital_status'];
                $blood = $_POST['blood_group'];
                $mahallah = $_POST['mahallah'];
                $phone = trim($_POST['phone']);
                $occupation = trim($_POST['occupation']);
                $designation = $_POST['designation'];

                // Address Split Handling
                $res_address_line1 = trim($_POST['res_address_line1']);
                $res_address_line2 = trim($_POST['res_address_line2']);
                $res_city = trim($_POST['res_city']);
                $res_pincode = trim($_POST['res_pincode']);

                $comm_address_line1 = trim($_POST['comm_address_line1']);
                $comm_address_line2 = trim($_POST['comm_address_line2']);
                $comm_city = trim($_POST['comm_city']);
                $comm_pincode = trim($_POST['comm_pincode']);

                $status = $_POST['status'];
                $dec_date = ($status === 'Deceased') ? $_POST['deceased_date'] : null;

                // Configure modern file storage pathway directory
                $upload_dir = 'uploads/members/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Default placeholder avatar if no file is selected
                $photo_data = "https://placehold.co/150x150/0f766e/ffffff?text=" . urlencode($first_name . '+' . $last_name);

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['photo']['tmp_name'];
                    $file_name = $_FILES['photo']['name'];
                    $file_size = $_FILES['photo']['size'];

                    // Enforce 5MB limit check per photo to ensure smooth server storage operation
                    if ($file_size <= 5 * 1024 * 1024) {
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $safe_file_name = 'member_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $target_destination = $upload_dir . $safe_file_name;

                        if (move_uploaded_file($file_tmp, $target_destination)) {
                            $photo_data = $target_destination;
                        }
                    }
                }

                $stmt = $db->prepare("INSERT INTO members (card_no, first_name, last_name, family_name, father_husband_name, dob, gender, marital_status, mahallah, phone, blood_group, occupation, designation, res_address_line1, res_address_line2, res_city, res_pincode, comm_address_line1, comm_address_line2, comm_city, comm_pincode, status, deceased_date, chanda_status, photo, dependents_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?, ?)");
                $stmt->execute([$card, $first_name, $last_name, $family_name, $father, $dob, $gender, $marital_status, $mahallah, $phone, $blood, $occupation, $designation, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $comm_address_line1, $comm_address_line2, $comm_city, $comm_pincode, $status, $dec_date, $photo_data, $dependents_count]);

                $member_id = $db->lastInsertId();

                // Loop and save dependents list (with updated life status mappings)
                if ($dependents_count > 0 && isset($_POST['dep_name'])) {
                    $dep_stmt = $db->prepare("INSERT INTO member_dependents (member_id, name, relationship, dob, gender, status) VALUES (?, ?, ?, ?, ?, ?)");
                    for ($i = 0; $i < $dependents_count; $i++) {
                        if (!empty($_POST['dep_name'][$i])) {
                            $dep_status = isset($_POST['dep_status'][$i]) ? $_POST['dep_status'][$i] : 'Alive';
                            $dep_stmt->execute([
                                $member_id, // For edit_member, replace this with $id
                                trim($_POST['dep_name'][$i]),
                                trim($_POST['dep_relationship'][$i]),
                                $_POST['dep_dob'][$i],
                                $_POST['dep_gender'][$i],
                                $dep_status
                            ]);
                        }
                    }
                }

                $db->commit();
                header("Location: members.php?msg=Member and dependents registered successfully");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                die("Failed to save member: " . htmlspecialchars($e->getMessage()));
            }
        }

        // Action: Edit / Update Existing Member & Dependents (File Directory Mode)
        if ($_POST['action'] === 'edit_member') {
            $db->beginTransaction();
            try {
                $id = (int) $_POST['id'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $family_name = trim($_POST['family_name']);
                $father = trim($_POST['father_husband_name']);
                $card = trim($_POST['card_no']);
                $dependents_count = (int) $_POST['dependents_count'];
                $dob = $_POST['dob'];
                $gender = $_POST['gender'];
                $marital_status = $_POST['marital_status'];
                $blood = $_POST['blood_group'];
                $mahallah = $_POST['mahallah'];
                $phone = trim($_POST['phone']);
                $occupation = trim($_POST['occupation']);
                $designation = $_POST['designation'];

                $res_address_line1 = trim($_POST['res_address_line1']);
                $res_address_line2 = trim($_POST['res_address_line2']);
                $res_city = trim($_POST['res_city']);
                $res_pincode = trim($_POST['res_pincode']);

                $comm_address_line1 = trim($_POST['comm_address_line1']);
                $comm_address_line2 = trim($_POST['comm_address_line2']);
                $comm_city = trim($_POST['comm_city']);
                $comm_pincode = trim($_POST['comm_pincode']);

                $status = $_POST['status'];
                $dec_date = ($status === 'Deceased') ? $_POST['deceased_date'] : null;

                // Photo update handling via file manager system pathways
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['photo']['tmp_name'];
                    $file_name = $_FILES['photo']['name'];
                    $file_size = $_FILES['photo']['size'];

                    if ($file_size <= 5 * 1024 * 1024) {
                        $upload_dir = 'uploads/members/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // Track down previous file location string to clean up server space
                        $old_photo_stmt = $db->prepare("SELECT photo FROM members WHERE id = ?");
                        $old_photo_stmt->execute([$id]);
                        $old_photo_path = $old_photo_stmt->fetchColumn();

                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $safe_file_name = 'member_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $target_destination = $upload_dir . $safe_file_name;

                        if (move_uploaded_file($file_tmp, $target_destination)) {
                            // Drop old image from local server disk if it isn't an external API link
                            if (!empty($old_photo_path) && file_exists($old_photo_path) && strpos($old_photo_path, 'http') === false) {
                                @unlink($old_photo_path);
                            }

                            $stmt = $db->prepare("UPDATE members SET photo = ? WHERE id = ?");
                            $stmt->execute([$target_destination, $id]);
                        }
                    }
                }

                // Update core record parameters
                $stmt = $db->prepare("UPDATE members SET card_no = ?, first_name = ?, last_name = ?, family_name = ?, father_husband_name = ?, dob = ?, gender = ?, marital_status = ?, mahallah = ?, phone = ?, blood_group = ?, occupation = ?, designation = ?, res_address_line1 = ?, res_address_line2 = ?, res_city = ?, res_pincode = ?, comm_address_line1 = ?, comm_address_line2 = ?, comm_city = ?, comm_pincode = ?, status = ?, deceased_date = ?, dependents_count = ? WHERE id = ?");
                $stmt->execute([$card, $first_name, $last_name, $family_name, $father, $dob, $gender, $marital_status, $mahallah, $phone, $blood, $occupation, $designation, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $comm_address_line1, $comm_address_line2, $comm_city, $comm_pincode, $status, $dec_date, $dependents_count, $id]);

                // Sync Dependents table properties accurately
                $del_stmt = $db->prepare("DELETE FROM member_dependents WHERE member_id = ?");
                $del_stmt->execute([$id]);

                if ($dependents_count > 0 && isset($_POST['dep_name'])) {
                    $dep_stmt = $db->prepare("INSERT INTO member_dependents (member_id, name, relationship, dob, gender, status) VALUES (?, ?, ?, ?, ?, ?)");
                    for ($i = 0; $i < $dependents_count; $i++) {
                        if (!empty($_POST['dep_name'][$i])) {
                            $dep_status = isset($_POST['dep_status'][$i]) ? $_POST['dep_status'][$i] : 'Alive';
                            $dep_stmt->execute([
                                $id,
                                trim($_POST['dep_name'][$i]),
                                trim($_POST['dep_relationship'][$i]),
                                $_POST['dep_dob'][$i],
                                $_POST['dep_gender'][$i],
                                $dep_status
                            ]);
                        }
                    }
                }

                $db->commit();
                header("Location: members.php?msg=Member and dependents updated successfully");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                die("Failed to update member records: " . htmlspecialchars($e->getMessage()));
            }
        }

        // Action: Safe Delete Member Record (Checks cross-module relational dependencies first)
        if ($_POST['action'] === 'delete_member') {
            $id = (int) $_POST['id'];

            // 1. Check Bait-ul-Mal Aid Applications Registry
            $check_mal = $db->prepare("SELECT COUNT(*) FROM baitulmal_applications WHERE member_id = ?");
            $check_mal->execute([$id]);
            if ($check_mal->fetchColumn() > 0) {
                header("Location: members.php?error=" . urlencode("Cannot delete member. Active aid applications exist inside the Bait-ul-Mal registry."));
                exit;
            }

            // 2. Check Burial Registry (Both Deceased and Reporter links)
            $check_burial = $db->prepare("SELECT COUNT(*) FROM burial_registry WHERE deceased_member_id = ? OR reporter_member_id = ?");
            $check_burial->execute([$id, $id]);
            if ($check_burial->fetchColumn() > 0) {
                header("Location: members.php?error=" . urlencode("Cannot delete member. Attached record files discovered inside the Burial Ground registry."));
                exit;
            }

            // 3. Check Family Dependents Registry
            $check_dependents = $db->prepare("SELECT COUNT(*) FROM member_dependents WHERE member_id = ?");
            $check_dependents->execute([$id]);
            if ($check_dependents->fetchColumn() > 0) {
                header("Location: members.php?error=" . urlencode("Cannot delete member. Family dependent records are currently mapped to this profile."));
                exit;
            }

            // ==================== ALL INTEGRITY CHECKS PASSED: PROCEED TO DELETE ====================

            // Track photo path reference before deleting database records
            $photo_stmt = $db->prepare("SELECT photo FROM members WHERE id = ?");
            $photo_stmt->execute([$id]);
            $member_photo_path = $photo_stmt->fetchColumn();

            // Clear database row properties
            $stmt = $db->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$id]);

            // Clean up disk footprint
            if (!empty($member_photo_path) && file_exists($member_photo_path) && strpos($member_photo_path, 'http') === false) {
                @unlink($member_photo_path);
            }

            header("Location: members.php?msg=" . urlencode("Member record deleted permanently from portal database registry."));
            exit;
        }

        // Action: Collect Chanda dynamically for a specified period (From Month/Year to To Month/Year)
        if ($_POST['action'] === 'update_chanda_period') {
            $id = (int) $_POST['id'];
            $chanda_from = $_POST['chanda_paid_from'] . '-01'; // Append first day to match SQL DATE format
            $chanda_to = $_POST['chanda_paid_to'] . '-01';

            // MODIFICATION: Capture the new amount from form input casting it safely to float
            $total_amount = isset($_POST['total_amount']) ? (float) $_POST['total_amount'] : 0.00;

            // MODIFICATION: Dynamically capture the authenticated user from active session
            $recorded_by = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Admin';

            // Server-Side Asymmetric Strict Date Boundaries Validation
            $min_allowed_boundary = date('Y-m-01', strtotime('-2 years')); // Past 2 years boundary

            $max_from_boundary = date('Y-m-01'); // Paid From max = Current Active Month
            $max_to_boundary = date('Y-12-01');  // Paid To max = December of Current Year

            // Validate Paid From separately
            if ($chanda_from < $min_allowed_boundary || $chanda_from > $max_from_boundary) {
                die("Failed to update: 'Paid From' must be within the past 2 years and cannot exceed the current month.");
            }

            // Validate Paid To separately
            if ($chanda_to < $min_allowed_boundary || $chanda_to > $max_to_boundary) {
                die("Failed to update: 'Paid To' must be within the past 2 years and cannot exceed December of " . date('Y') . ".");
            }

            if ($chanda_to < $chanda_from) {
                die("Failed to update: The 'Paid To' month cannot be earlier than the 'Paid From' month.");
            }

            // Calculate if the payment period covers up to the previous month dynamically
            $prev_month = date('Y-m-01', strtotime('first day of last month'));
            $chanda_status = ($chanda_to >= $prev_month) ? 'Paid' : 'Unpaid';

            // MODIFICATION 1: Insert an entirely new historical ledger line entry into chanda_payments
            $insert_stmt = $db->prepare("
                INSERT INTO chanda_payments (member_id, paid_from, paid_to, total_amount, recorded_by, date_recorded) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insert_stmt->execute([$id, $chanda_from, $chanda_to, $total_amount, $recorded_by]);

            // MODIFICATION 2: Update only the fast chanda_status status flag inside the parent members entity
            $update_stmt = $db->prepare("UPDATE members SET chanda_status = ? WHERE id = ?");
            $update_stmt->execute([$chanda_status, $id]);

            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'members.php';
            header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "msg=Chanda payment period successfully recorded in ledger");
            exit;
        }

        // Action: Collect Chanda Directly (Fallback quick collection action - sets targeted past period)
        if ($_POST['action'] === 'collect_chanda') {
            $id = (int) $_POST['id'];

            // Boot sessions to ensure user attribution is logged accurately
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $recorded_by = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Admin';

            // Establish the quick collect target boundary (First day of the previous calendar month)
            $target_to_month = date('Y-m-01', strtotime('first day of last month'));
            $default_quick_amount = 0.00; // Standard fallback allocation amount

            // Look up if this member already has an existing payment history ledger entry
            $history_check = $db->prepare("
                SELECT paid_to 
                FROM chanda_payments 
                WHERE member_id = ? 
                ORDER BY paid_to DESC 
                LIMIT 1
            ");
            $history_check->execute([$id]);
            $last_payment = $history_check->fetch(PDO::FETCH_ASSOC);

            if ($last_payment) {
                // MODIFICATION: If history exists, start from the immediate next month following their last record
                $default_from = date('Y-m-01', strtotime('+1 month', strtotime($last_payment['paid_to'])));

                // Safety checkpoint check: if the next month would push past the target month, normalize it to the target month
                if ($default_from > $target_to_month) {
                    $default_from = $target_to_month;
                }
            } else {
                // MODIFICATION: If never paid, collect just for that one single target month alone
                $default_from = $target_to_month;
            }

            // 1. Insert transaction historical trace entry into the separate ledger table
            $insert_stmt = $db->prepare("
                INSERT INTO chanda_payments (member_id, paid_from, paid_to, total_amount, recorded_by, date_recorded) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insert_stmt->execute([$id, $default_from, $target_to_month, $default_quick_amount, $recorded_by]);

            // 2. Sync the fast cache flag update state back on the primary member record
            $update_stmt = $db->prepare("UPDATE members SET chanda_status = 'Paid' WHERE id = ?");
            $update_stmt->execute([$id]);

            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
            header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "msg=Chanda quick collection recorded successfully");
            exit;
        }

        // Action: Record Member Demise
        if ($_POST['action'] === 'mark_deceased') {
            $id = (int) $_POST['id'];
            $burial_datetime = $_POST['burial_datetime'];
            $plot = trim($_POST['plot_details']);

            // Fetch member details
            $stmt = $db->prepare("SELECT first_name, last_name FROM members WHERE id = ?");
            $stmt->execute([$id]);
            $m = $stmt->fetch();
            $fullname = $m['first_name'] . ' ' . $m['last_name'] . ' (Marhoom)';

            // Update member status
            $stmt = $db->prepare("UPDATE members SET status = 'Deceased', deceased_date = ?, chanda_status = 'Paid' WHERE id = ?");
            $date_only = substr($burial_datetime, 0, 10);
            $stmt->execute([$date_only, $id]);

            // Save to Burial records
            $stmt = $db->prepare("INSERT INTO burial_registry (deceased_id, deceased_name, burial_datetime, plot_details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $fullname, $burial_datetime, $plot]);

            header("Location: burial.php?msg=Recorded demise & logged burial registry");
            exit;
        }

        // Action: Revert status to Active
        if ($_POST['action'] === 'revert_active') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("UPDATE members SET status = 'Active', deceased_date = NULL WHERE id = ?");
            $stmt->execute([$id]);

            // Delete associated burial log
            $stmt = $db->prepare("DELETE FROM burial_registry WHERE deceased_id = ?");
            $stmt->execute([$id]);

            header("Location: members.php?msg=Status reverted back to Active");
            exit;
        }

        // ==========================================
        // BAITUL-MAL WELFARE ACTION ROUTINES (OUTFLOWS)
        // ==========================================

        // Action: Add Welfare Application
        if ($_POST['action'] === 'add_welfare') {
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("INSERT INTO welfare (name, type, amount, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$name, $type, $amount]);

            header("Location: baitul_mal.php?msg=Welfare petition filed in queue");
            exit;
        }

        // Action: Edit/Update Existing Welfare Request
        if ($_POST['action'] === 'edit_welfare') {
            $id = (int) $_POST['id'];
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("UPDATE welfare SET name = ?, type = ?, amount = ? WHERE id = ?");
            $stmt->execute([$name, $type, $amount, $id]);

            header("Location: baitul_mal.php?msg=Welfare application updated successfully");
            exit;
        }

        // Action: Disburse Outflow Payment (Transitions to Paid & Saves Proof)
        if ($_POST['action'] === 'pay_welfare') {
            $id = (int) $_POST['id'];

            // Photo Conversion to Base64 (Welfare Receipt / Transaction Proof)
            $proof_data = "";
            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['proof_photo']['tmp_name'];
                $file_type = $_FILES['proof_photo']['type'];
                $data = file_get_contents($file_tmp);
                $proof_data = 'data:' . $file_type . ';base64,' . base64_encode($data);
            } else {
                $proof_data = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23f1f5f9'/><text x='50%' y='50%' font-size='12' text-anchor='middle' alignment-baseline='middle' fill='%2364748b' font-family='sans-serif'>Verified Cash Paid</text></svg>";
            }

            $stmt = $db->prepare("UPDATE welfare SET status = 'Paid', proof_of_payment = ? WHERE id = ?");
            $stmt->execute([$proof_data, $id]);

            header("Location: baitul_mal.php?msg=Payment disbursed to recipient and logged with proof");
            exit;
        }

        // Action: Delete Welfare Log Row
        if ($_POST['action'] === 'delete_welfare') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("DELETE FROM welfare WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: baitul_mal.php?msg=Welfare entry deleted permanently from ledger");
            exit;
        }

        // ==========================================
        // BAITUL-MAL INFLOW ACTION ROUTINES
        // ==========================================

        // ACTION: Log New Aid Application File
        if ($_POST['action'] === 'add_application') {
            $is_member = isset($_POST['is_member']) ? 1 : 0;
            $member_id = $is_member && !empty($_POST['member_id']) ? (int) $_POST['member_id'] : null;

            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $father_husband_name = trim($_POST['father_husband_name']);
            $res_address_line1 = trim($_POST['res_address_line1']);
            $res_address_line2 = trim($_POST['res_address_line2']);
            $res_city = trim($_POST['res_city']);
            $res_pincode = trim($_POST['res_pincode']);
            $phone = trim($_POST['phone']);
            $amount = (int) $_POST['amount'];
            $type = $_POST['type'];
            $mode = $_POST['mode_of_payment'];
            $date = !empty($_POST['date_of_payment']) ? $_POST['date_of_payment'] : null;

            // Define folder paths explicitly to eliminate undefined variable warnings
            $photo_dir = 'uploads/welfare/photos/';
            $id_card_dir = 'uploads/welfare/id_cards/';

            // Process image upload blocks safely matching table configurations
            $photo_payload = "";
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $safe_photo_name = 'app_' . time() . '_' . uniqid() . '.' . $ext;

                // INLINE CHECK: Create photo folder if missing
                if (!is_dir($photo_dir)) {
                    mkdir($photo_dir, 0755, true);
                }

                $target_photo = $photo_dir . $safe_photo_name;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_photo)) {
                    $photo_payload = $target_photo;
                }
            } elseif ($is_member && $member_id) {
                $mem_img_stmt = $db->prepare("SELECT photo FROM members WHERE id = ?");
                $mem_img_stmt->execute([$member_id]);
                $photo_payload = $mem_img_stmt->fetchColumn() ?: "";
            }

            $id_card_path = "";
            if (isset($_FILES['id_card']) && $_FILES['id_card']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['id_card']['name'], PATHINFO_EXTENSION));
                $safe_id_name = 'id_' . time() . '_' . uniqid() . '.' . $ext;

                // INLINE CHECK: Create ID folder if missing
                if (!is_dir($id_card_dir)) {
                    mkdir($id_card_dir, 0755, true);
                }

                $target_id = $id_card_dir . $safe_id_name;
                if (move_uploaded_file($_FILES['id_card']['tmp_name'], $target_id)) {
                    $id_card_path = $target_id;
                }
            }

            $stmt = $db->prepare("INSERT INTO baitulmal_applications 
            (is_member, member_id, first_name, last_name, father_husband_name, res_address_line1, res_address_line2, res_city, res_pincode, contact_number, amount, type, mode_of_payment, date_of_payment, photo, id_card, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$is_member, $member_id, $first_name, $last_name, $father_husband_name, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $phone, $amount, $type, $mode, $date, $photo_payload, $id_card_path]);

            header("Location: baitul_mal.php?msg=Aid application filed into committee queue.");
            exit;
        }

        // ACTION: Save Modified Application Record Rows
        if ($_POST['action'] === 'edit_application') {
            $id = (int) $_POST['id'];
            $is_member = isset($_POST['is_member']) ? 1 : 0;
            $member_id = $is_member && !empty($_POST['member_id']) ? (int) $_POST['member_id'] : null;

            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $father_husband_name = trim($_POST['father_husband_name']);
            $res_address_line1 = trim($_POST['res_address_line1']);
            $res_address_line2 = trim($_POST['res_address_line2']);
            $res_city = trim($_POST['res_city']);
            $res_pincode = trim($_POST['res_pincode']);
            $phone = trim($_POST['phone']);
            $amount = (int) $_POST['amount'];
            $type = $_POST['type'];
            $mode = $_POST['mode_of_payment'];
            $date = !empty($_POST['date_of_payment']) ? $_POST['date_of_payment'] : null;

            // Define folder paths explicitly here too
            $photo_dir = 'uploads/welfare/photos/';
            $id_card_dir = 'uploads/welfare/id_cards/';

            $old_files_stmt = $db->prepare("SELECT photo, id_card FROM baitulmal_applications WHERE id = ?");
            $old_files_stmt->execute([$id]);
            $old = $old_files_stmt->fetch(PDO::FETCH_ASSOC);

            $photo_payload = $old['photo'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $safe_photo_name = 'app_' . time() . '_' . uniqid() . '.' . $ext;

                // INLINE CHECK: Create photo folder if missing during modification
                if (!is_dir($photo_dir)) {
                    mkdir($photo_dir, 0755, true);
                }

                $target_photo = $photo_dir . $safe_photo_name;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_photo)) {
                    if (!empty($old['photo']) && file_exists($old['photo']) && strpos($old['photo'], 'uploads/') === 0) {
                        @unlink($old['photo']);
                    }
                    $photo_payload = $target_photo;
                }
            }

            $id_card_path = $old['id_card'];
            if (isset($_FILES['id_card']) && $_FILES['id_card']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['id_card']['name'], PATHINFO_EXTENSION));
                $safe_id_name = 'id_' . time() . '_' . uniqid() . '.' . $ext;

                // INLINE CHECK: Create ID folder if missing during modification
                if (!is_dir($id_card_dir)) {
                    mkdir($id_card_dir, 0755, true);
                }

                $target_id = $id_card_dir . $safe_id_name;
                if (move_uploaded_file($_FILES['id_card']['tmp_name'], $target_id)) {
                    if (!empty($old['id_card']) && file_exists($old['id_card']) && strpos($old['id_card'], 'uploads/') === 0) {
                        @unlink($old['id_card']);
                    }
                    $id_card_path = $target_id;
                }
            }

            $stmt = $db->prepare("UPDATE baitulmal_applications SET 
            is_member = ?, member_id = ?, first_name = ?, last_name = ?, father_husband_name = ?, res_address_line1 = ?, res_address_line2 = ?, res_city = ?, res_pincode = ?, contact_number = ?, amount = ?, type = ?, mode_of_payment = ?, date_of_payment = ?, photo = ?, id_card = ? 
            WHERE id = ?");
            $stmt->execute([$is_member, $member_id, $first_name, $last_name, $father_husband_name, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $phone, $amount, $type, $mode, $date, $photo_payload, $id_card_path, $id]);

            header("Location: baitul_mal.php?msg=Aid application updated safely.");
            exit;
        }

        // ACTION: Accept Application & Generate Payout Outflow Row
        if ($_POST['action'] === 'accept_application') {
            $id = (int) $_POST['id'];
            $app_stmt = $db->prepare("SELECT * FROM baitulmal_applications WHERE id = ?");
            $app_stmt->execute([$id]);
            $app = $app_stmt->fetch(PDO::FETCH_ASSOC);

            if ($app && $app['status'] === 'Pending') {
                $db->beginTransaction();
                try {
                    $up_stmt = $db->prepare("UPDATE baitulmal_applications SET status = 'Accepted' WHERE id = ?");
                    $up_stmt->execute([$id]);

                    $fullName = $app['first_name'] . ' ' . $app['last_name'];
                    $outflow_stmt = $db->prepare("INSERT INTO welfare (name, type, amount, status, application_id) VALUES (?, ?, ?, 'Pending', ?)");
                    $outflow_stmt->execute([$fullName, $app['type'], $app['amount'], $id]);

                    $db->commit();
                    header("Location: baitul_mal.php?msg=Application approved and pushed to Outflows.");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    header("Location: baitul_mal.php?msg=Error mapping transactional files: " . urlencode($e->getMessage()));
                    exit;
                }
            }
        }

        // ACTION: Reject Welfare Application
        if ($_POST['action'] === 'reject_application') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("UPDATE baitulmal_applications SET status = 'Rejected' WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: baitul_mal.php?msg=Aid application marked as rejected.");
            exit;
        }

        // ACTION: Delete Welfare Application Permanently
        if ($_POST['action'] === 'delete_application') {
            $id = (int) $_POST['id'];

            $files_stmt = $db->prepare("SELECT photo, id_card FROM baitulmal_applications WHERE id = ?");
            $files_stmt->execute([$id]);
            $row = $files_stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if (!empty($row['photo']) && file_exists($row['photo']) && strpos($row['photo'], 'uploads/') === 0) {
                    @unlink($row['photo']);
                }
                if (!empty($row['id_card']) && file_exists($row['id_card']) && strpos($row['id_card'], 'uploads/') === 0) {
                    @unlink($row['id_card']);
                }
            }

            $stmt = $db->prepare("DELETE FROM baitulmal_applications WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: baitul_mal.php?msg=Welfare application deleted permanently.");
            exit;
        }

        // ACTION: Configure/Update Base Reserve Fund
        if ($_POST['action'] === 'update_baseline') {
            $amount = (int) $_POST['baseline_amount'];
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'baitulmal_base_reserve'");
            $stmt->execute([$amount]);
            header("Location: baitul_mal.php?msg=Baseline reserve fund configured successfully.");
            exit;
        }

        // Action: Record Money Contribution (Inflow)
        if ($_POST['action'] === 'add_inflow') {
            $donor_name = trim($_POST['donor_name']);
            $type = $_POST['type'];
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("INSERT INTO baitulmal_inflows (donor_name, type, amount) VALUES (?, ?, ?)");
            $stmt->execute([$donor_name, $type, $amount]);

            header("Location: baitul_mal.php?msg=Contribution logged in Bait-Ul-Mal registry");
            exit;
        }

        // ACTION: Modify Donation Parameters
        if ($_POST['action'] === 'edit_inflow') {
            $id = (int) $_POST['id'];
            $donor_name = trim($_POST['donor_name']);
            $inflow_type = $_POST['inflow_type'];
            $reference_no = trim($_POST['reference_no']);
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("UPDATE baitulmal_inflows SET donor_name = ?, inflow_type = ?, reference_no = ?, amount = ? WHERE id = ?");
            $stmt->execute([$donor_name, $inflow_type, $reference_no, $amount, $id]);
            header("Location: baitul_mal.php?msg=Donation inward parameters modified successfully.");
            exit;
        }

        // ACTION: Delete Donation Inward
        if ($_POST['action'] === 'delete_inflow') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("DELETE FROM baitulmal_inflows WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: baitul_mal.php?msg=Donation inward record deleted.");
            exit;
        }

        // ACTION: Confirm Outflow Disbursement with Receipt Upload
        if ($_POST['action'] === 'pay_welfare') {
            $id = (int) $_POST['id'];

            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION));
                $safe_name = 'proof_' . time() . '_' . uniqid() . '.' . $ext;
                $target_path = 'uploads/welfare/proofs/' . $safe_name;

                if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $target_path)) {
                    $stmt = $db->prepare("UPDATE welfare SET status = 'Paid', proof_of_payment = ? WHERE id = ?");
                    $stmt->execute([$target_path, $id]);
                    header("Location: baitul_mal.php?msg=Disbursement finalized and payout receipt uploaded.");
                    exit;
                }
            }

            header("Location: baitul_mal.php?msg=Disbursement failed. Valid receipt required.");
            exit;
        }

        // ==================== BAIT-UL-MAL REPORT LIVE PREVIEW ROUTE ====================
        if (isset($_GET['action']) && $_GET['action'] === 'fetch_mal_report') {
            $type = $_GET['type'] ?? '';
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';

            if (empty($start_date) || empty($end_date)) {
                echo '<div class="p-4 text-xs font-bold text-amber-700 bg-amber-50 rounded-lg">Please choose both date parameters first.</div>';
                exit;
            }

            $whereClauses = ["DATE(date_added) >= :start_date", "DATE(date_added) <= :end_date"];
            $params = [':start_date' => $start_date, ':end_date' => $end_date];
            $whereSql = " WHERE " . implode(" AND ", $whereClauses);

            try {
                if ($type === 'inflows') {
                    $stmt = $db->prepare("SELECT donor_name AS name, type, amount, date_added FROM baitulmal_inflows" . $whereSql . " ORDER BY date_added DESC");
                    $title = "Contribution Inflows Summary";
                    $text_color = "text-emerald-600";
                } elseif ($type === 'outflows') {
                    $stmt = $db->prepare("SELECT name, type, amount, date_added FROM welfare" . $whereSql . " ORDER BY date_added DESC");
                    $title = "Welfare Disbursements Summary";
                    $text_color = "text-rose-600";
                } else {
                    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name, type, amount, date_added FROM baitulmal_applications" . $whereSql . " ORDER BY date_added DESC");
                    $title = "Aid Applications Summary";
                    $text_color = "text-blue-600";
                }

                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total = 0;
                foreach ($rows as $r) {
                    $total += $r['amount'];
                }

                if (empty($rows)) {
                    echo '<div class="text-center py-12 text-slate-400 text-xs border border-dashed border-slate-200 rounded-xl">No verified data streams match these parameters inside the designated timeframe.</div>';
                    exit;
                }

                // Professional Chanda-Style Dashboard Preview Panel Container Matrix Layout
                echo '<div class="mb-6 p-5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">';
                echo '<div><h4 class="text-xs font-bold text-slate-700 uppercase tracking-wide">' . $title . '</h4>';
                echo '<p class="text-[10px] text-slate-400 mt-0.5">' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)) . '</p></div>';
                echo '<div class="text-right"><span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Aggregate Sum</span>';
                echo '<span class="text-lg font-black ' . $text_color . '">₹' . number_format($total) . '</span> <span class="text-xs text-slate-400 font-medium">(' . count($rows) . ' entries)</span></div>';
                echo '</div>';

                echo '<div class="overflow-x-auto"><table class="w-full text-left border-collapse text-xs"><thead>';
                echo '<tr class="border-b border-slate-200 text-[10px] uppercase font-bold text-slate-400 tracking-wider">';
                echo '<th class="py-2.5 px-4 text-slate-500">Line Participant</th><th class="py-2.5 px-4 text-slate-500">Category Type</th><th class="py-2.5 px-4 text-slate-500">Filing Date</th><th class="py-2.5 px-4 text-right text-slate-500">Value Amount</th>';
                echo '</tr></thead><tbody class="divide-y divide-slate-100 font-medium text-slate-700">';

                foreach ($rows as $row) {
                    echo '<tr class="hover:bg-slate-50/50 transition-all">';
                    echo '<td class="py-3 px-4 font-bold text-slate-800">' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td class="py-3 px-4 text-slate-500">' . htmlspecialchars($row['type']) . '</td>';
                    echo '<td class="py-3 px-4 text-slate-400 font-mono">' . date('d M Y', strtotime($row['date_added'])) . '</td>';
                    echo '<td class="py-3 px-4 text-right font-black text-slate-900">₹' . number_format($row['amount']) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table></div>';
                exit;

            } catch (Exception $e) {
                echo '<div class="p-4 text-xs font-bold text-rose-700 bg-rose-50 rounded-lg">Database Link Interrupted.</div>';
                exit;
            }
        }

        // ==========================================
        // OTHER SYSTEM SERVICES
        // ==========================================

        // Action: Register Nikah Ceremony Registry
        if ($_POST['action'] === 'add_nikah') {
            $groom_name = trim($_POST['groom_name']);
            $groom_father = trim($_POST['groom_father']);
            $groom_age = (int) $_POST['groom_age'];
            $groom_marriage_status = $_POST['groom_marriage_status'];
            $groom_jamath = trim($_POST['groom_jamath']) ?: 'NVK Jamath (Vadasery)';

            $bride_name = trim($_POST['bride_name']);
            $bride_father = trim($_POST['bride_father']);
            $bride_age = (int) $_POST['bride_age'];
            $bride_marriage_status = $_POST['bride_marriage_status'];
            $bride_jamath = trim($_POST['bride_jamath']) ?: 'NVK Jamath (Vadasery)';

            $datetime = $_POST['nikah_datetime'];
            $venue = trim($_POST['venue']);
            $book_reference = trim($_POST['book_reference']);
            $conducted_by_jamath = isset($_POST['conducted_by_jamath']) ? 1 : 0;

            // Strict Server-Side Legal Age Validation
            if ($groom_age < 21) {
                die("Validation Failed: The Groom must be at least 21 years of age according to Indian legal marriage boundaries.");
            }
            if ($bride_age < 18) {
                die("Validation Failed: The Bride must be at least 18 years of age according to Indian legal marriage boundaries.");
            }

            $stmt = $db->prepare("INSERT INTO nikah_registry (groom_name, groom_father, groom_age, groom_marriage_status, groom_jamath, bride_name, bride_father, bride_age, bride_marriage_status, bride_jamath, venue, conducted_by_jamath, nikah_datetime, book_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$groom_name, $groom_father, $groom_age, $groom_marriage_status, $groom_jamath, $bride_name, $bride_father, $bride_age, $bride_marriage_status, $bride_jamath, $venue, $conducted_by_jamath, $datetime, $book_reference]);

            header("Location: nikah.php?msg=Marriage certified registry logged with dynamic validations");
            exit;
        }

        // Action: Update Existing Nikah Record
        if ($_POST['action'] === 'edit_nikah') {
            $id = (int) $_POST['id'];
            $groom_name = trim($_POST['groom_name']);
            $groom_father = trim($_POST['groom_father']);
            $groom_age = (int) $_POST['groom_age'];
            $groom_marriage_status = $_POST['groom_marriage_status'];
            $groom_jamath = trim($_POST['groom_jamath']) ?: 'NVK Jamath (Vadasery)';

            $bride_name = trim($_POST['bride_name']);
            $bride_father = trim($_POST['bride_father']);
            $bride_age = (int) $_POST['bride_age'];
            $bride_marriage_status = $_POST['bride_marriage_status'];
            $bride_jamath = trim($_POST['bride_jamath']) ?: 'NVK Jamath (Vadasery)';

            $datetime = $_POST['nikah_datetime'];
            $venue = trim($_POST['venue']);
            // Capture checkbox: if checked it sends "1", if unchecked it's empty, so default to "0"
            $conducted_by_jamath = isset($_POST['conducted_by_jamath']) ? 1 : 0;

            if ($groom_age < 21 || $bride_age < 18) {
                die("Validation Failed: Legal marriage ages (21 for Grooms, 18 for Brides) must be met.");
            }

            $stmt = $db->prepare("UPDATE nikah_registry SET groom_name = ?, groom_father = ?, groom_age = ?, groom_marriage_status = ?, groom_jamath = ?, bride_name = ?, bride_father = ?, bride_age = ?, bride_marriage_status = ?, bride_jamath = ?, venue = ?, conducted_by_jamath = ?, nikah_datetime = ?, book_reference = ? WHERE id = ?");
            $stmt->execute([$groom_name, $groom_father, $groom_age, $groom_marriage_status, $groom_jamath, $bride_name, $bride_father, $bride_age, $bride_marriage_status, $bride_jamath, $venue, $conducted_by_jamath, $datetime, $book_reference, $id]);

            header("Location: nikah.php?msg=Certified marriage record updated successfully");
            exit;
        }

        // Action: Permanent Delete Nikah Record
        if ($_POST['action'] === 'delete_nikah') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("DELETE FROM nikah_registry WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: nikah.php?msg=Certified marriage record permanently deleted");
            exit;
        }

        // Action: Add Certified Burial Log Record
        if ($_POST['action'] === 'add_burial') {
            $db->beginTransaction();
            try {
                $is_jamaath_member_input = (int) $_POST['is_jamaath_member'];
                $deceased_member_id = null;
                $deceased_dependent_id = null;
                $deceased_name = '';
                $deceased_father_husband = '';
                $deceased_age = null;
                $deceased_gender = '';
                $deceased_jamath = 'NVK Jamath (Vadasery)';
                $noc_provided = 0;
                $actual_is_jamaath = 0; // Stored as 1 in DB for all Jamath residents (both members & dependents)

                if ($is_jamaath_member_input === 1) {
                    // Primary Member
                    $actual_is_jamaath = 1;
                    $deceased_member_id = (int) $_POST['deceased_member_id'];
                    $m_stmt = $db->prepare("SELECT first_name, last_name, father_husband_name, gender, dob FROM members WHERE id = ?");
                    $m_stmt->execute([$deceased_member_id]);
                    $member = $m_stmt->fetch();
                    if ($member) {
                        $deceased_name = $member['first_name'] . ' ' . $member['last_name'];
                        $deceased_father_husband = $member['father_husband_name'];
                        $deceased_gender = $member['gender'];
                        if (!empty($member['dob'])) {
                            $deceased_age = calculateAge($member['dob']);
                        }
                        // AUTOMATIC: Set life status of primary member to Deceased
                        $up_stmt = $db->prepare("UPDATE members SET status = 'Deceased', deceased_date = ? WHERE id = ?");
                        $up_stmt->execute([substr($_POST['burial_datetime'], 0, 10), $deceased_member_id]);
                    }
                } elseif ($is_jamaath_member_input === 2) {
                    // Dependent
                    $actual_is_jamaath = 1;
                    $deceased_dependent_id = (int) $_POST['deceased_dependent_id'];
                    $d_stmt = $db->prepare("
                        SELECT d.*, m.first_name AS prim_first, m.last_name AS prim_last, m.father_husband_name AS prim_father
                        FROM member_dependents d
                        JOIN members m ON d.member_id = m.id
                        WHERE d.id = ?
                    ");
                    $d_stmt->execute([$deceased_dependent_id]);
                    $dep = $d_stmt->fetch();
                    if ($dep) {
                        $deceased_name = $dep['name'];
                        $deceased_father_husband = ($dep['relationship'] === 'Son' || $dep['relationship'] === 'Daughter') ? ($dep['prim_first'] . ' ' . $dep['prim_last']) : $dep['prim_father'];
                        $deceased_gender = $dep['gender'];
                        if (!empty($dep['dob'])) {
                            $deceased_age = calculateAge($dep['dob']);
                        }
                        // AUTOMATIC: Set life status of dependent to Deceased
                        $up_stmt = $db->prepare("UPDATE member_dependents SET status = 'Deceased' WHERE id = ?");
                        $up_stmt->execute([$deceased_dependent_id]);
                    }
                } else {
                    // Non-Jamaath Profile
                    $actual_is_jamaath = 0;
                    $deceased_name = trim($_POST['manual_deceased_name']);
                    $deceased_father_husband = trim($_POST['manual_deceased_father']);
                    $deceased_age = (int) $_POST['manual_deceased_age'];
                    $deceased_gender = $_POST['manual_deceased_gender'];
                    $deceased_jamath = trim($_POST['manual_deceased_jamath']) ?: 'Outside Jamath';
                    $noc_provided = isset($_POST['noc_provided']) ? 1 : 0;
                }

                $death_datetime = !empty($_POST['death_datetime']) ? $_POST['death_datetime'] : null;
                $burial_datetime = $_POST['burial_datetime'];
                $plot = trim($_POST['plot_details']);

                // Informant/Reporter details
                $reported_by_member = isset($_POST['reported_by_member']) ? 1 : 0;
                $reporter_member_id = ($reported_by_member && !empty($_POST['reporter_member_id'])) ? (int) $_POST['reporter_member_id'] : null;
                $reporter_name = (!$reported_by_member && !empty($_POST['reporter_name'])) ? trim($_POST['reporter_name']) : null;
                $reporter_phone = (!$reported_by_member && !empty($_POST['reporter_phone'])) ? trim($_POST['reporter_phone']) : null;
                $reporter_relationship = !empty($_POST['reporter_relationship']) ? trim($_POST['reporter_relationship']) : null;

                // MODIFICATION: Server-side validation safeguard checking time boundaries
                $death_timestamp = strtotime($_POST['death_datetime']);
                $burial_timestamp = strtotime($_POST['burial_datetime']);

                if ($death_timestamp !== false && $burial_timestamp <= $death_timestamp) {
                    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'burial.php';
                    header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "error=Burial time must be after demise time");
                    exit;
                }

                $ins_stmt = $db->prepare("
                    INSERT INTO burial_registry (
                        is_jamaath_member, deceased_member_id, deceased_dependent_id, deceased_name, 
                        deceased_father_husband, deceased_age, deceased_gender, deceased_jamath,
                        death_datetime, burial_datetime, plot_details, noc_provided, 
                        reported_by_member, reporter_member_id, reporter_name, reporter_phone, reporter_relationship
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([
                    $actual_is_jamaath,
                    $deceased_member_id,
                    $deceased_dependent_id,
                    $deceased_name,
                    $deceased_father_husband,
                    $deceased_age,
                    $deceased_gender,
                    $deceased_jamath,
                    $death_datetime,
                    $burial_datetime,
                    $plot,
                    $noc_provided,
                    $reported_by_member,
                    $reporter_member_id,
                    $reporter_name,
                    $reporter_phone,
                    $reporter_relationship
                ]);

                $db->commit();
                header("Location: burial.php?msg=Certified burial record successfully archived");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                die("Failed to register burial record: " . htmlspecialchars($e->getMessage()));
            }
        }


        // Action: Edit/Update Certified Burial Log Record
        if ($_POST['action'] === 'edit_burial') {
            $db->beginTransaction();
            try {
                $id = (int) $_POST['id'];

                // Fetch original record to manage status rollback safely
                $orig_stmt = $db->prepare("SELECT * FROM burial_registry WHERE id = ?");
                $orig_stmt->execute([$id]);
                $orig = $orig_stmt->fetch();

                if (!$orig) {
                    throw new Exception("Burial registry record not found.");
                }

                // If previous was primary member, temporarily revert them to Alive status
                if (!empty($orig['deceased_member_id'])) {
                    $rev_stmt = $db->prepare("UPDATE members SET status = 'Alive', deceased_date = NULL WHERE id = ?");
                    $rev_stmt->execute([$orig['deceased_member_id']]);
                }

                // If previous was dependent, temporarily revert them to Alive status
                if (!empty($orig['deceased_dependent_id'])) {
                    $rev_stmt = $db->prepare("UPDATE member_dependents SET status = 'Alive' WHERE id = ?");
                    $rev_stmt->execute([$orig['deceased_dependent_id']]);
                }

                $is_jamaath_member_input = (int) $_POST['is_jamaath_member'];
                $deceased_member_id = null;
                $deceased_dependent_id = null;
                $deceased_name = '';
                $deceased_father_husband = '';
                $deceased_age = null;
                $deceased_gender = '';
                $deceased_jamath = 'NVK Jamath (Vadasery)';
                $noc_provided = 0;
                $actual_is_jamaath = 0;

                if ($is_jamaath_member_input === 1) {
                    $actual_is_jamaath = 1;
                    $deceased_member_id = (int) $_POST['deceased_member_id'];
                    $m_stmt = $db->prepare("SELECT first_name, last_name, father_husband_name, gender, dob FROM members WHERE id = ?");
                    $m_stmt->execute([$deceased_member_id]);
                    $member = $m_stmt->fetch();
                    if ($member) {
                        // MODIFICATION: Appended ' (Marhoom)' to keep naming matches unified for print certificates
                        $deceased_name = $member['first_name'] . ' ' . $member['last_name'] . ' (Marhoom)';
                        $deceased_father_husband = $member['father_husband_name'];
                        $deceased_gender = $member['gender'];
                        if (!empty($member['dob'])) {
                            $deceased_age = calculateAge($member['dob']);
                        }
                        // Set primary member status to Deceased
                        $up_stmt = $db->prepare("UPDATE members SET status = 'Deceased', deceased_date = ?, chanda_status = 'Paid' WHERE id = ?");
                        $up_stmt->execute([substr($_POST['burial_datetime'], 0, 10), $deceased_member_id]);
                    }
                } elseif ($is_jamaath_member_input === 2) {
                    $actual_is_jamaath = 1;
                    $deceased_dependent_id = (int) $_POST['deceased_dependent_id'];
                    $d_stmt = $db->prepare("
                SELECT d.*, m.first_name AS prim_first, m.last_name AS prim_last, m.father_husband_name AS prim_father
                FROM member_dependents d
                JOIN members m ON d.member_id = m.id
                WHERE d.id = ?
            ");
                    $d_stmt->execute([$deceased_dependent_id]);
                    $dep = $d_stmt->fetch();
                    if ($dep) {
                        // MODIFICATION: Appended ' (Marhoom)' to dependents as well for name registry standard mapping
                        $deceased_name = $dep['name'] . ' (Marhoom)';
                        $deceased_father_husband = ($dep['relationship'] === 'Son' || $dep['relationship'] === 'Daughter') ? ($dep['prim_first'] . ' ' . $dep['prim_last']) : $dep['prim_father'];
                        $deceased_gender = $dep['gender'];
                        if (!empty($dep['dob'])) {
                            $deceased_age = calculateAge($dep['dob']);
                        }
                        // Set dependent's status to Deceased
                        $up_stmt = $db->prepare("UPDATE member_dependents SET status = 'Deceased' WHERE id = ?");
                        $up_stmt->execute([$deceased_dependent_id]);
                    }
                } else {
                    $actual_is_jamaath = 0;
                    $deceased_name = trim($_POST['manual_deceased_name']);
                    $deceased_father_husband = trim($_POST['manual_deceased_father']);
                    $deceased_age = (int) $_POST['manual_deceased_age'];
                    $deceased_gender = $_POST['manual_deceased_gender'];
                    $deceased_jamath = trim($_POST['manual_deceased_jamath']) ?: 'Outside Jamath';
                    $noc_provided = isset($_POST['noc_provided']) ? 1 : 0;
                }

                $death_datetime = !empty($_POST['death_datetime']) ? $_POST['death_datetime'] : null;
                $burial_datetime = $_POST['burial_datetime'];
                $plot = trim($_POST['plot_details']);

                // Informant/Reporter details
                $reported_by_member = isset($_POST['reported_by_member']) ? 1 : 0;
                $reporter_member_id = ($reported_by_member && !empty($_POST['reporter_member_id'])) ? (int) $_POST['reporter_member_id'] : null;
                $reporter_name = (!$reported_by_member && !empty($_POST['reporter_name'])) ? trim($_POST['reporter_name']) : null;
                $reporter_phone = (!$reported_by_member && !empty($_POST['reporter_phone'])) ? trim($_POST['reporter_phone']) : null;
                $reporter_relationship = !empty($_POST['reporter_relationship']) ? trim($_POST['reporter_relationship']) : null;

                // MODIFICATION: Server-side validation safeguard checking time boundaries
                $death_timestamp = strtotime($_POST['death_datetime']);
                $burial_timestamp = strtotime($_POST['burial_datetime']);

                if ($death_timestamp !== false && $burial_timestamp <= $death_timestamp) {
                    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'burial.php';
                    header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "error=Burial time must be after demise time");
                    exit;
                }

                $upd_stmt = $db->prepare("
            UPDATE burial_registry SET 
                is_jamaath_member = ?, deceased_member_id = ?, deceased_dependent_id = ?, 
                deceased_name = ?, deceased_father_husband = ?, deceased_age = ?, 
                deceased_gender = ?, deceased_jamath = ?, death_datetime = ?, 
                burial_datetime = ?, plot_details = ?, noc_provided = ?, 
                reported_by_member = ?, reporter_member_id = ?, reporter_name = ?, 
                reporter_phone = ?, reporter_relationship = ?
            WHERE id = ?
        ");
                $upd_stmt->execute([
                    $actual_is_jamaath,
                    $deceased_member_id,
                    $deceased_dependent_id,
                    $deceased_name,
                    $deceased_father_husband,
                    $deceased_age,
                    $deceased_gender,
                    $deceased_jamath,
                    $death_datetime,
                    $burial_datetime,
                    $plot,
                    $noc_provided,
                    $reported_by_member,
                    $reporter_member_id,
                    $reporter_name,
                    $reporter_phone,
                    $reporter_relationship,
                    $id
                ]);

                $db->commit();
                header("Location: burial.php?msg=Certified burial record successfully updated");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                die("Failed to update burial record: " . htmlspecialchars($e->getMessage()));
            }
        }

        // Action: Permanent Delete Certified Burial Log Record
        if ($_POST['action'] === 'delete_burial') {
            $db->beginTransaction();
            try {
                $id = (int) $_POST['id'];

                // Fetch details to check if status rollback is needed
                $stmt = $db->prepare("SELECT deceased_member_id, deceased_dependent_id FROM burial_registry WHERE id = ?");
                $stmt->execute([$id]);
                $rec = $stmt->fetch();

                if ($rec) {
                    if (!empty($rec['deceased_member_id'])) {
                        $rev_stmt = $db->prepare("UPDATE members SET status = 'Alive', deceased_date = NULL WHERE id = ?");
                        $rev_stmt->execute([$rec['deceased_member_id']]);
                    }
                    if (!empty($rec['deceased_dependent_id'])) {
                        $rev_stmt = $db->prepare("UPDATE member_dependents SET status = 'Alive' WHERE id = ?");
                        $rev_stmt->execute([$rec['deceased_dependent_id']]);
                    }
                }

                $del_stmt = $db->prepare("DELETE FROM burial_registry WHERE id = ?");
                $del_stmt->execute([$id]);

                $db->commit();
                header("Location: burial.php?msg=Certified burial record permanently deleted");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                die("Failed to delete burial record: " . htmlspecialchars($e->getMessage()));
            }
        }

        // ==========================================
        // DYNAMIC CMS GALLERY ACTIONS (FILE SYSTEM MODE)
        // ==========================================

        // Action: Add Gallery Item with 1GB Cap Limits
        if ($_POST['action'] === 'add_gallery_item') {
            $heading = trim($_POST['heading']);
            $caption = trim($_POST['caption']);

            // Fetch current storage sum
            $size_stmt = $db->query("SELECT IFNULL(SUM(image_size), 0) FROM gallery");
            $total_bytes = (int) $size_stmt->fetchColumn();
            $limit_bytes = 1073741824; // 1GB in bytes

            if ($total_bytes >= $limit_bytes) {
                header("Location: manage_gallery.php?error=" . urlencode("Restricted: The 1GB collective storage limit is fully occupied. Please contact Euro Global Consultancy to upgrade your environment."));
                exit;
            }

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image']['tmp_name'];
                $file_name = $_FILES['image']['name'];
                $file_size = $_FILES['image']['size'];

                // Enforce 5MB upload safeguard per image
                if ($file_size > 5 * 1024 * 1024) {
                    header("Location: manage_gallery.php?error=" . urlencode("Upload Failed: Individual images are capped at 5MB to preserve collective space."));
                    exit;
                }

                if (($total_bytes + $file_size) > $limit_bytes) {
                    header("Location: manage_gallery.php?error=" . urlencode("Upload Failed: This image would breach the 1GB collective storage cap. Please contact Euro Global Consultancy."));
                    exit;
                }

                // Prepare safe storage target paths
                $upload_dir = 'uploads/gallery/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $safe_file_name = 'gallery_' . time() . '_' . uniqid() . '.' . $file_ext;
                $target_destination = $upload_dir . $safe_file_name;

                // Move from temporary execution space to storage folder
                if (move_uploaded_file($file_tmp, $target_destination)) {
                    $ins = $db->prepare("INSERT INTO gallery (heading, caption, image_path, image_size) VALUES (?, ?, ?, ?)");
                    $ins->execute([$heading, $caption, $target_destination, $file_size]);

                    header("Location: manage_gallery.php?msg=" . urlencode("Gallery asset published to public website successfully!"));
                    exit;
                } else {
                    header("Location: manage_gallery.php?error=" . urlencode("Server Disk Error: Failed to write uploaded image to target folder directory."));
                    exit;
                }
            } else {
                header("Location: manage_gallery.php?error=" . urlencode("Please choose a valid image file to upload."));
                exit;
            }
        }

        // Action: Update Gallery Item
        if ($_POST['action'] === 'edit_gallery_item') {
            $id = (int) $_POST['id'];
            $heading = trim($_POST['heading']);
            $caption = trim($_POST['caption']);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image']['tmp_name'];
                $file_name = $_FILES['image']['name'];
                $file_size = $_FILES['image']['size'];

                if ($file_size > 5 * 1024 * 1024) {
                    header("Location: manage_gallery.php?error=" . urlencode("Upload Failed: Individual images are limited to 5MB."));
                    exit;
                }

                // Fetch current item parameters to get previous image path and disk size
                $orig_stmt = $db->prepare("SELECT image_path, image_size FROM gallery WHERE id = ?");
                $orig_stmt->execute([$id]);
                $orig = $orig_stmt->fetch();
                $orig_size = $orig ? (int) $orig['image_size'] : 0;
                $old_file_path = $orig ? $orig['image_path'] : '';

                $size_stmt = $db->query("SELECT IFNULL(SUM(image_size), 0) FROM gallery");
                $total_bytes = (int) $size_stmt->fetchColumn();
                $limit_bytes = 1073741824;

                if (($total_bytes - $orig_size + $file_size) > $limit_bytes) {
                    header("Location: manage_gallery.php?error=" . urlencode("Upload Failed: Exceeds the 1GB collective storage. Contact Euro Global Consultancy."));
                    exit;
                }

                // Prepare storage directory
                $upload_dir = 'uploads/gallery/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $safe_file_name = 'gallery_' . time() . '_' . uniqid() . '.' . $file_ext;
                $target_destination = $upload_dir . $safe_file_name;

                if (move_uploaded_file($file_tmp, $target_destination)) {
                    // Delete the old file from disk if it exists
                    if (!empty($old_file_path) && file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }

                    $up = $db->prepare("UPDATE gallery SET heading = ?, caption = ?, image_path = ?, image_size = ? WHERE id = ?");
                    $up->execute([$heading, $caption, $target_destination, $file_size, $id]);
                } else {
                    header("Location: manage_gallery.php?error=" . urlencode("Server Disk Error: Failed to save modified asset image file."));
                    exit;
                }
            } else {
                $up = $db->prepare("UPDATE gallery SET heading = ?, caption = ? WHERE id = ?");
                $up->execute([$heading, $caption, $id]);
            }

            header("Location: manage_gallery.php?msg=" . urlencode("Gallery asset modified successfully!"));
            exit;
        }

        // Action: Delete Gallery Item
        if ($_POST['action'] === 'delete_gallery_item') {
            $id = (int) $_POST['id'];

            // Fetch the physical file location before wiping reference parameters
            $file_stmt = $db->prepare("SELECT image_path FROM gallery WHERE id = ?");
            $file_stmt->execute([$id]);
            $file_path = $file_stmt->fetchColumn();

            // Clear database row parameters
            $del = $db->prepare("DELETE FROM gallery WHERE id = ?");
            $del->execute([$id]);

            // Physically drop the asset from the web server disk storage
            if (!empty($file_path) && file_exists($file_path)) {
                @unlink($file_path);
            }

            header("Location: manage_gallery.php?msg=" . urlencode("Gallery asset deleted successfully from CMS."));
            exit;
        }
    }
}
?>
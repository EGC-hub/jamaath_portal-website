<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Action: Register New Member with Relational Dependents
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

                // Photo Conversion to Base64
                $photo_data = "https://placehold.co/150x150/0f766e/ffffff?text=" . urlencode($first_name . '+' . $last_name);
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['photo']['tmp_name'];
                    $file_type = $_FILES['photo']['type'];
                    $data = file_get_contents($file_tmp);
                    $photo_data = 'data:' . $file_type . ';base64,' . base64_encode($data);
                }

                $stmt = $db->prepare("INSERT INTO members (card_no, first_name, last_name, family_name, father_husband_name, dob, gender, marital_status, mahallah, phone, blood_group, occupation, designation, res_address_line1, res_address_line2, res_city, res_pincode, comm_address_line1, comm_address_line2, comm_city, comm_pincode, status, deceased_date, chanda_status, photo, dependents_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?, ?)");
                $stmt->execute([$card, $first_name, $last_name, $family_name, $father, $dob, $gender, $marital_status, $mahallah, $phone, $blood, $occupation, $designation, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $comm_address_line1, $comm_address_line2, $comm_city, $comm_pincode, $status, $dec_date, $photo_data, $dependents_count]);

                $member_id = $db->lastInsertId();

                // Loop and save dependents list
                if ($dependents_count > 0 && isset($_POST['dep_name'])) {
                    $dep_stmt = $db->prepare("INSERT INTO member_dependents (member_id, name, relationship, dob, gender) VALUES (?, ?, ?, ?, ?)");
                    for ($i = 0; $i < $dependents_count; $i++) {
                        if (!empty($_POST['dep_name'][$i])) {
                            $dep_stmt->execute([
                                $member_id,
                                trim($_POST['dep_name'][$i]),
                                trim($_POST['dep_relationship'][$i]),
                                $_POST['dep_dob'][$i],
                                $_POST['dep_gender'][$i]
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

        // Action: Edit / Update Existing Member & Dependents
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

                // Photo update handling (if uploaded)
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['photo']['tmp_name'];
                    $file_type = $_FILES['photo']['type'];
                    $data = file_get_contents($file_tmp);
                    $photo_data = 'data:' . $file_type . ';base64,' . base64_encode($data);

                    $stmt = $db->prepare("UPDATE members SET photo = ? WHERE id = ?");
                    $stmt->execute([$photo_data, $id]);
                }

                // Update core record
                $stmt = $db->prepare("UPDATE members SET card_no = ?, first_name = ?, last_name = ?, family_name = ?, father_husband_name = ?, dob = ?, gender = ?, marital_status = ?, mahallah = ?, phone = ?, blood_group = ?, occupation = ?, designation = ?, res_address_line1 = ?, res_address_line2 = ?, res_city = ?, res_pincode = ?, comm_address_line1 = ?, comm_address_line2 = ?, comm_city = ?, comm_pincode = ?, status = ?, deceased_date = ?, dependents_count = ? WHERE id = ?");
                $stmt->execute([$card, $first_name, $last_name, $family_name, $father, $dob, $gender, $marital_status, $mahallah, $phone, $blood, $occupation, $designation, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $comm_address_line1, $comm_address_line2, $comm_city, $comm_pincode, $status, $dec_date, $dependents_count, $id]);

                // Sync Dependents: Delete existing dependents and insert current set to keep clean integrity
                $del_stmt = $db->prepare("DELETE FROM member_dependents WHERE member_id = ?");
                $del_stmt->execute([$id]);

                if ($dependents_count > 0 && isset($_POST['dep_name'])) {
                    $dep_stmt = $db->prepare("INSERT INTO member_dependents (member_id, name, relationship, dob, gender) VALUES (?, ?, ?, ?, ?)");
                    for ($i = 0; $i < $dependents_count; $i++) {
                        if (!empty($_POST['dep_name'][$i])) {
                            $dep_stmt->execute([
                                $id,
                                trim($_POST['dep_name'][$i]),
                                trim($_POST['dep_relationship'][$i]),
                                $_POST['dep_dob'][$i],
                                $_POST['dep_gender'][$i]
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

        // Action: Permanent Delete Member Record (Automatically cascades and deletes dependents)
        if ($_POST['action'] === 'delete_member') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: members.php?msg=Member record deleted permanently");
            exit;
        }

        // Action: Collect Chanda dynamically for a specified period (From Month/Year to To Month/Year)
        if ($_POST['action'] === 'update_chanda_period') {
            $id = (int) $_POST['id'];
            $chanda_from = $_POST['chanda_paid_from'] . '-01'; // Append first day to match SQL DATE format
            $chanda_to = $_POST['chanda_paid_to'] . '-01';

            // Server-Side Strict Date Boundaries Validation
            $max_allowed_boundary = date('Y-m-01', strtotime('first day of last month')); // May 2026
            $min_allowed_boundary = date('Y-m-01', strtotime('-2 years')); // June 2024 (24 months ago)

            if (
                $chanda_from < $min_allowed_boundary || $chanda_from > $max_allowed_boundary ||
                $chanda_to < $min_allowed_boundary || $chanda_to > $max_allowed_boundary
            ) {
                die("Failed to update: Chanda payments can only be processed for periods within the last 2 years, ending no later than the previous month.");
            }

            if ($chanda_to < $chanda_from) {
                die("Failed to update: The 'Paid To' month cannot be earlier than the 'Paid From' month.");
            }

            // Calculate if the payment period covers up to the previous month dynamically
            $prev_month = date('Y-m-01', strtotime('first day of last month'));
            $chanda_status = ($chanda_to >= $prev_month) ? 'Paid' : 'Unpaid';

            $stmt = $db->prepare("UPDATE members SET chanda_paid_from = ?, chanda_paid_to = ?, chanda_status = ? WHERE id = ?");
            $stmt->execute([$chanda_from, $chanda_to, $chanda_status, $id]);

            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'members.php';
            header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "msg=Chanda payment period successfully updated");
            exit;
        }

        // Action: Collect Chanda Directly (Fallback quick collection action - sets previous month as paid)
        if ($_POST['action'] === 'collect_chanda') {
            $id = (int) $_POST['id'];

            // Set paid period to cover up to last month (e.g., from 6 months ago up to last month)
            $default_from = date('Y-m-01', strtotime('-6 months'));
            $last_month = date('Y-m-01', strtotime('first day of last month'));

            $stmt = $db->prepare("UPDATE members SET chanda_paid_from = ?, chanda_paid_to = ?, chanda_status = 'Paid' WHERE id = ?");
            $stmt->execute([$default_from, $last_month, $id]);

            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
            header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "msg=Chanda collection updated successfully");
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

        // Action: Record Money Contribution (Inflow)
        if ($_POST['action'] === 'add_inflow') {
            $donor_name = trim($_POST['donor_name']);
            $type = $_POST['type'];
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("INSERT INTO baitulmal_inflows (donor_name, type, amount) VALUES (?, ?, ?)");
            $stmt->execute([$donor_name, $type, $amount]);

            header("Location: baitul_mal.php?msg=Contribution logged in Baitul-Mal registry");
            exit;
        }

        // Action: Edit Contribution Log
        if ($_POST['action'] === 'edit_inflow') {
            $id = (int) $_POST['id'];
            $donor_name = trim($_POST['donor_name']);
            $type = $_POST['type'];
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("UPDATE baitulmal_inflows SET donor_name = ?, type = ?, amount = ? WHERE id = ?");
            $stmt->execute([$donor_name, $type, $amount, $id]);

            header("Location: baitul_mal.php?msg=Contribution entry successfully modified");
            exit;
        }

        // Action: Delete Contribution Log Entry
        if ($_POST['action'] === 'delete_inflow') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("DELETE FROM baitulmal_inflows WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: baitul_mal.php?msg=Contribution record deleted permanently");
            exit;
        }

        // Action: Update Baseline Reserve Configuration
        if ($_POST['action'] === 'update_base_reserve') {
            $amount = (int) $_POST['base_reserve_amount'];

            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('baitulmal_base_reserve', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$amount, $amount]);

            header("Location: baitul_mal.php?msg=Baitul-Mal base reserve successfully configured");
            exit;
        }

        // ==========================================
        // OTHER SYSTEM SERVICES
        // ==========================================

        // Action: Register Nikah Ceremony Registry
        if ($_POST['action'] === 'add_nikah') {
            $groom = trim($_POST['groom_name']);
            $bride = trim($_POST['bride_name']);
            $datetime = $_POST['nikah_datetime'];
            $venue = trim($_POST['venue']);
            $conducted_by_jamath = isset($_POST['conducted_by_jamath']) ? 1 : 0;
            $details = trim($_POST['details']);

            $stmt = $db->prepare("INSERT INTO nikah_registry (groom_name, bride_name, venue, conducted_by_jamath, nikah_datetime, details) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$groom, $bride, $venue, $conducted_by_jamath, $datetime, $details]);

            header("Location: nikah.php?msg=Marriage certified registry logged");
            exit;
        }

        // Action: Add Direct Burial log
        if ($_POST['action'] === 'add_burial') {
            $name = trim($_POST['deceased_name']);
            $death_datetime = !empty($_POST['death_datetime']) ? $_POST['death_datetime'] : null;
            $burial_datetime = $_POST['burial_datetime'];
            $plot = trim($_POST['plot_details']);

            $reported_by_member = isset($_POST['reported_by_member']) ? 1 : 0;
            $reporter_member_id = ($reported_by_member && !empty($_POST['reporter_member_id'])) ? (int) $_POST['reporter_member_id'] : null;

            $reporter_name = (!$reported_by_member && !empty($_POST['reporter_name'])) ? trim($_POST['reporter_name']) : null;
            $reporter_phone = (!$reported_by_member && !empty($_POST['reporter_phone'])) ? trim($_POST['reporter_phone']) : null;
            $reporter_relationship = (!$reported_by_member && !empty($_POST['reporter_relationship'])) ? trim($_POST['reporter_relationship']) : null;

            $stmt = $db->prepare("INSERT INTO burial_registry (deceased_name, death_datetime, reported_by_member, reporter_member_id, reporter_name, reporter_phone, reporter_relationship, burial_datetime, plot_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $death_datetime, $reported_by_member, $reporter_member_id, $reporter_name, $reporter_phone, $reporter_relationship, $burial_datetime, $plot]);

            header("Location: burial.php?msg=Burial plot logged to archives");
            exit;
        }
    }
}
?>
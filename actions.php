<?php
require_once 'db.php';
require_once 'helpers.php';

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

                // If previous was primary member, temporarily revert them to Alive
                if (!empty($orig['deceased_member_id'])) {
                    $rev_stmt = $db->prepare("UPDATE members SET status = 'Alive', deceased_date = NULL WHERE id = ?");
                    $rev_stmt->execute([$orig['deceased_member_id']]);
                }
                // If previous was dependent, temporarily revert them to Alive
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
                        $deceased_name = $member['first_name'] . ' ' . $member['last_name'];
                        $deceased_father_husband = $member['father_husband_name'];
                        $deceased_gender = $member['gender'];
                        if (!empty($member['dob'])) {
                            $deceased_age = calculateAge($member['dob']);
                        }
                        // Set primary member status to Deceased
                        $up_stmt = $db->prepare("UPDATE members SET status = 'Deceased', deceased_date = ? WHERE id = ?");
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
                        $deceased_name = $dep['name'];
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
    }
}
?>
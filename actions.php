<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Action: Register New Member (actions.php update)
        if ($_POST['action'] === 'add_member') {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $family_name = trim($_POST['family_name']);
            $father = trim($_POST['father_husband_name']);
            $card = trim($_POST['card_no']);
            $dependents = (int) $_POST['dependents_count'];
            $dob = $_POST['dob'];
            $gender = $_POST['gender'];
            $blood = $_POST['blood_group'];
            $mahallah = $_POST['mahallah'];
            $phone = trim($_POST['phone']);
            $occupation = trim($_POST['occupation']);
            $designation = $_POST['designation'];

            // Addresses
            $res_address_line1 = trim($_POST['res_address_line1']);
            $res_address_line2 = trim($_POST['res_address_line2']);
            $res_city = trim($_POST['res_city']);
            $res_pincode = trim($_POST['res_pincode']);

            $comm_address_line1 = trim($_POST['comm_address_line1']);
            $comm_address_line2 = trim($_POST['comm_address_line2']);
            $comm_city = trim($_POST['comm_city']);
            $comm_pincode = trim($_POST['comm_pincode']);

            $status = $_POST['status'];
            $chanda = $_POST['chanda_status'];
            $dec_date = ($status === 'Deceased') ? $_POST['deceased_date'] : null;

            // Handle Photo upload base64 formatting
            $photo_data = "https://placehold.co/150x150/0f766e/ffffff?text=" . urlencode($first_name . '+' . $last_name);
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['photo']['tmp_name'];
                $file_type = $_FILES['photo']['type'];
                $data = file_get_contents($file_tmp);
                $photo_data = 'data:' . $file_type . ';base64,' . base64_encode($data);
            }

            $stmt = $db->prepare("INSERT INTO members (card_no, first_name, last_name, family_name, father_husband_name, dob, gender, mahallah, phone, blood_group, occupation, designation, res_address_line1, res_address_line2, res_city, res_pincode, comm_address_line1, comm_address_line2, comm_city, comm_pincode, status, deceased_date, chanda_status, photo, dependents_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$card, $first_name, $last_name, $family_name, $father, $dob, $gender, $mahallah, $phone, $blood, $occupation, $designation, $res_address_line1, $res_address_line2, $res_city, $res_pincode, $comm_address_line1, $comm_address_line2, $comm_city, $comm_pincode, $status, $dec_date, $chanda, $photo_data, $dependents]);

            header("Location: members.php?msg=Member registered successfully");
            exit;
        }

        // Action: Collect Chanda Directly
        if ($_POST['action'] === 'collect_chanda') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("UPDATE members SET chanda_status = 'Paid' WHERE id = ?");
            $stmt->execute([$id]);

            // Return to previous file elegantly
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
            header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "msg=Subscription marked as paid");
            exit;
        }

        // Action: Record Member Demise & Register automatically to Burial Registry
        if ($_POST['action'] === 'mark_deceased') {
            $id = (int) $_POST['id'];
            $burial_datetime = $_POST['burial_datetime'];
            $plot = trim($_POST['plot_details']);

            // Get member details
            $stmt = $db->prepare("SELECT first_name, last_name FROM members WHERE id = ?");
            $stmt->execute([$id]);
            $m = $stmt->fetch();
            $fullname = $m['first_name'] . ' ' . $m['last_name'] . ' (Marhoom)';

            // Update member status
            $stmt = $db->prepare("UPDATE members SET status = 'Deceased', deceased_date = ?, chanda_status = 'Paid' WHERE id = ?");
            $date_only = substr($burial_datetime, 0, 10);
            $stmt->execute([$date_only, $id]);

            // Save automatically to Burial records with precise timestamp
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

            // Delete associated burial log to keep sync
            $stmt = $db->prepare("DELETE FROM burial_registry WHERE deceased_id = ?");
            $stmt->execute([$id]);

            header("Location: members.php?msg=Status reverted back to Active");
            exit;
        }

        // Action: Add Welfare Application
        if ($_POST['action'] === 'add_welfare') {
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            $amount = (int) $_POST['amount'];

            $stmt = $db->prepare("INSERT INTO welfare (name, type, amount, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$name, $type, $amount]);

            header("Location: welfare.php?msg=Welfare petition filed in queue");
            exit;
        }

        // Action: Approve Welfare aid
        if ($_POST['action'] === 'approve_welfare') {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("UPDATE welfare SET status = 'Approved' WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: welfare.php?msg=Welfare assistance grant approved");
            exit;
        }

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
            $book_reference = trim($_POST['book_reference']);
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

        // Action: Add Direct Burial log
        if ($_POST['action'] === 'add_burial') {
            $name = trim($_POST['deceased_name']);
            $death_datetime = !empty($_POST['death_datetime']) ? $_POST['death_datetime'] : null;
            $burial_datetime = $_POST['burial_datetime'];
            $plot = trim($_POST['plot_details']);

            // Check if reported by active member
            $reported_by_member = isset($_POST['reported_by_member']) ? 1 : 0;
            $reporter_member_id = ($reported_by_member && !empty($_POST['reporter_member_id'])) ? (int) $_POST['reporter_member_id'] : null;

            // Otherwise gather fallback informant details
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
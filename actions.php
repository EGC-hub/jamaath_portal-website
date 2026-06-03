<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // Action: Register New Member
        if ($_POST['action'] === 'add_member') {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $father = trim($_POST['father_husband_name']);
            $card = trim($_POST['card_no']);
            $dependents = (int)$_POST['dependents_count'];
            $dob = $_POST['dob'];
            $gender = $_POST['gender'];
            $blood = $_POST['blood_group'];
            $mahallah = $_POST['mahallah'];
            $phone = trim($_POST['phone']);
            $occupation = trim($_POST['occupation']);
            $address = trim($_POST['address']);
            $status = $_POST['status'];
            $chanda = $_POST['chanda_status'];
            $dec_date = ($status === 'Deceased') ? $_POST['deceased_date'] : null;

            // Handle Photo upload conversions to base64
            $photo_data = "https://placehold.co/150x150/0f766e/ffffff?text=" . urlencode($first_name . '+' . $last_name);
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['photo']['tmp_name'];
                $file_type = $_FILES['photo']['type'];
                $data = file_get_contents($file_tmp);
                $photo_data = 'data:' . $file_type . ';base64,' . base64_encode($data);
            }

            $stmt = $db->prepare("INSERT INTO members (card_no, first_name, last_name, father_husband_name, dob, gender, mahallah, address, phone, blood_group, occupation, status, deceased_date, chanda_status, photo, dependents_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$card, $first_name, $last_name, $father, $dob, $gender, $mahallah, $address, $phone, $blood, $occupation, $status, $dec_date, $chanda, $photo_data, $dependents]);

            header("Location: members.php?msg=Member registered successfully");
            exit;
        }

        // Action: Collect Chanda Directly
        if ($_POST['action'] === 'collect_chanda') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE members SET chanda_status = 'Paid' WHERE id = ?");
            $stmt->execute([$id]);

            // Return to previous file elegantly
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
            header("Location: " . $referrer . (strpos($referrer, '?') !== false ? '&' : '?') . "msg=Subscription marked as paid");
            exit;
        }

        // Action: Record Member Demise & Register automatically to Burial Registry
        if ($_POST['action'] === 'mark_deceased') {
            $id = (int)$_POST['id'];
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
            $id = (int)$_POST['id'];
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
            $amount = (int)$_POST['amount'];

            $stmt = $db->prepare("INSERT INTO welfare (name, type, amount, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$name, $type, $amount]);

            header("Location: welfare.php?msg=Welfare petition filed in queue");
            exit;
        }

        // Action: Approve Welfare aid
        if ($_POST['action'] === 'approve_welfare') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE welfare SET status = 'Approved' WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: welfare.php?msg=Welfare assistance grant approved");
            exit;
        }

        // Action: Register Nikah Ceremony Registry
        if ($_POST['action'] === 'add_nikah') {
            $groom = trim($_POST['groom_name']);
            $bride = trim($_POST['bride_name']);
            $datetime = $_POST['nikah_datetime'];
            $details = trim($_POST['details']);

            $stmt = $db->prepare("INSERT INTO nikah_registry (groom_name, bride_name, nikah_datetime, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$groom, $bride, $datetime, $details]);

            header("Location: nikah.php?msg=Marriage certified registry logged");
            exit;
        }

        // Action: Add Direct Burial log
        if ($_POST['action'] === 'add_burial') {
            $name = trim($_POST['deceased_name']);
            $datetime = $_POST['burial_datetime'];
            $plot = trim($_POST['plot_details']);

            $stmt = $db->prepare("INSERT INTO burial_registry (deceased_name, burial_datetime, plot_details) VALUES (?, ?, ?)");
            $stmt->execute([$name, $datetime, $plot]);

            header("Location: burial.php?msg=Burial plot logged to archives");
            exit;
        }
    }
}
?>
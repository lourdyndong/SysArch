<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user_data'];
if (empty($user['role']) || $user['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$brokenStatusFile = __DIR__ . '/pc_broken_status.json';

function redirectAdminDashboard($section, $message = '') {
    if ($message !== '') {
        $_SESSION['admindashboard_msg'] = $message;
    }
    if ($section !== '') {
        $_SESSION['admindashboard_section'] = $section;
    }
    header("Location: admindashboard.php");
    exit;
}

/* ---------------------------------------------------------------
   Handle PC broken status toggle (AJAX)
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pc_broken'])) {
    $pcKey = trim($_POST['pc_key'] ?? '');
    if ($pcKey !== '') {
        $broken = [];
        if (file_exists($brokenStatusFile)) {
            $broken = json_decode(file_get_contents($brokenStatusFile), true) ?: [];
        }
        if (in_array($pcKey, $broken)) {
            $broken = array_values(array_filter($broken, fn($k) => $k !== $pcKey));
            $nowBroken = false;
        } else {
            $broken[] = $pcKey;
            $nowBroken = true;
        }
        file_put_contents($brokenStatusFile, json_encode($broken));
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'broken' => $nowBroken, 'key' => $pcKey]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit;
}

/* ---------------------------------------------------------------
   Export Sit-in Records (CSV, PDF, DOC)
   --------------------------------------------------------------- */
if (isset($_GET['export_sitins'])) {
    $format = $_GET['export_sitins'] ?? 'csv';
    
    $conn = getConnection();
    $result = $conn->query("SELECT * FROM sit_in_records ORDER BY id DESC");
    $records = [];
    if ($result) while ($row = $result->fetch_assoc()) $records[] = $row;
    $conn->close();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sit_in_records.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'ID Number', 'Student Name', 'Purpose', 'Lab', 'Session', 'Status', 'Reward Points', 'Task Points', 'Created At', 'Timeout At']);
        foreach ($records as $r) {
            fputcsv($output, [
                $r['id'],
                $r['id_number'],
                $r['student_name'],
                $r['purpose'],
                $r['lab'],
                $r['session'],
                $r['status'],
                $r['reward_points'],
                $r['task_completed_points'],
                $r['created_at'],
                $r['timeout_at']
            ]);
        }
        fclose($output);
        exit;
    } elseif ($format === 'pdf') {
        require_once 'db.php';
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Sit-in Records</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
                th { background-color: #4CAF50; color: white; }
                tr:nth-child(even) { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <h1>Sit-in Records</h1>
            <table>
                <tr>
                    <th>ID</th>
                    <th>ID Number</th>
                    <th>Student Name</th>
                    <th>Purpose</th>
                    <th>Lab</th>
                    <th>Session</th>
                    <th>Status</th>
                    <th>Reward Pts</th>
                    <th>Task Pts</th>
                    <th>Created</th>
                    <th>Timeout</th>
                </tr>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= $r['id_number'] ?></td>
                    <td><?= $r['student_name'] ?></td>
                    <td><?= $r['purpose'] ?></td>
                    <td><?= $r['lab'] ?></td>
                    <td><?= $r['session'] ?></td>
                    <td><?= $r['status'] ?></td>
                    <td><?= $r['reward_points'] ?></td>
                    <td><?= $r['task_completed_points'] ?></td>
                    <td><?= $r['created_at'] ?></td>
                    <td><?= $r['timeout_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </body>
        </html>
        <?php
        exit;
    } elseif ($format === 'doc') {
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename="sit_in_records.doc"');
        echo '<html><head><meta charset="utf-8"><style>';
        echo 'body { font-family: Arial; } table { border-collapse: collapse; width: 100%; } ';
        echo 'th, td { border: 1px solid #000; padding: 5px; } </style></head><body>';
        echo '<h1>Sit-in Records</h1>';
        echo '<table><tr><th>ID</th><th>ID Number</th><th>Student Name</th><th>Purpose</th><th>Lab</th><th>Session</th><th>Status</th><th>Reward Pts</th><th>Task Pts</th><th>Created</th><th>Timeout</th></tr>';
        foreach ($records as $r) {
            echo '<tr>';
            echo '<td>' . $r['id'] . '</td>';
            echo '<td>' . $r['id_number'] . '</td>';
            echo '<td>' . $r['student_name'] . '</td>';
            echo '<td>' . $r['purpose'] . '</td>';
            echo '<td>' . $r['lab'] . '</td>';
            echo '<td>' . $r['session'] . '</td>';
            echo '<td>' . $r['status'] . '</td>';
            echo '<td>' . $r['reward_points'] . '</td>';
            echo '<td>' . $r['task_completed_points'] . '</td>';
            echo '<td>' . $r['created_at'] . '</td>';
            echo '<td>' . $r['timeout_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
    header("Location: admindashboard.php");
    exit;
}

/* ---------------------------------------------------------------
   AJAX: Get available PCs for a room
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_available_pcs'])) {
    $room = trim($_POST['room'] ?? '');
    if ($room !== '') {
        $conn = getConnection();
        $occupied = [];
        $occRes = $conn->query("SELECT lab FROM sit_in_records WHERE status = 'active'");
        if ($occRes) while ($row = $occRes->fetch_assoc()) $occupied[] = $row['lab'];
        $conn->close();

        $broken = [];
        if (file_exists($brokenStatusFile)) {
            $broken = json_decode(file_get_contents($brokenStatusFile), true) ?: [];
        }

        $available = [];
        for ($i = 1; $i <= 50; $i++) {
            $label = $room . ' PC ' . str_pad($i, 2, '0', STR_PAD_LEFT);
            if (!in_array($label, $occupied) && !in_array($label, $broken)) {
                $available[] = $label;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'available' => $available, 'room' => $room]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit;
}

/* ---------------------------------------------------------------
   Handle announcement delete POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $announcement_id = intval($_POST['announcement_id'] ?? 0);
    if ($announcement_id > 0) {
        $conn = getConnection();
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        redirectAdminDashboard('home', 'success:Announcement deleted.');
    }
    redirectAdminDashboard('home', 'error:Invalid announcement selected.');
}

/* ---------------------------------------------------------------
   Handle announcement POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement'])) {
    $text = trim($_POST['announcement']);
    if ($text !== '') {
        $conn  = getConnection();
        $admin = 'CCS Admin';
        $stmt  = $conn->prepare("INSERT INTO announcements (admin_name, content, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $admin, $text);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        redirectAdminDashboard('home', 'success:Announcement posted.');
    }
    redirectAdminDashboard('home', 'error:Announcement cannot be empty.');
}

/* ---------------------------------------------------------------
   Handle sit-in POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sitin_submit'])) {
    $id_number    = trim($_POST['id_number'] ?? '');
    $student_name = trim($_POST['student_name'] ?? '');
    $purpose      = trim($_POST['purpose'] ?? '');
    $lab          = trim($_POST['lab'] ?? '');

    if ($id_number !== '' && $student_name !== '' && $purpose !== '' && $lab !== '') {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, remaining_sessions FROM accounts WHERE id_number = ? AND role = 'student' LIMIT 1");
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $student_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM sit_in_records WHERE id_number = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $already_active = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($already_active) {
            $conn->close();
            redirectAdminDashboard('sitinrecords', 'error:Student already has an active sit-in session. Please time them out first.');
        }

        if ($student_row && (int)$student_row['remaining_sessions'] > 0) {
            $remaining = (int)$student_row['remaining_sessions'];
            $stmt = $conn->prepare("INSERT INTO sit_in_records (id_number, student_name, purpose, lab, session, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("ssssi", $id_number, $student_name, $purpose, $lab, $remaining);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE accounts SET remaining_sessions = remaining_sessions - 1 WHERE id = ?");
            $stmt->bind_param("i", $student_row['id']);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            redirectAdminDashboard('sitinrecords', 'success:Sit-in recorded successfully!');
        }

        $conn->close();
        redirectAdminDashboard('sitinrecords', 'error:Student has no remaining sessions. Cannot sit in.');
    }
    redirectAdminDashboard('sitinrecords', 'error:Please fill in all required fields.');
}

/* ---------------------------------------------------------------
   Handle reservation approve with PC assignment
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_action'])) {
    $res_id = intval($_POST['reservation_id'] ?? 0);
    $action = $_POST['reservation_action'] === 'approve' ? 'approved' : 'declined';

    if ($res_id > 0) {
        $conn = getConnection();

        $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $res_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($action === 'approved' && $res) {
            $id_number    = $res['id_number'];
            $student_name = $res['student_name'];
            $purpose      = $res['purpose'];
            $assigned_pc  = trim($_POST['assigned_pc'] ?? '');

            if ($assigned_pc === '') {
                $conn->close();
                redirectAdminDashboard('reservation', 'error:Please select a PC to assign to the student.');
            }

            $stmt = $conn->prepare("SELECT id FROM sit_in_records WHERE lab = ? AND status = 'active' LIMIT 1");
            $stmt->bind_param("s", $assigned_pc);
            $stmt->execute();
            $pc_occupied = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($pc_occupied) {
                $conn->close();
                redirectAdminDashboard('reservation', 'error:The selected PC is already occupied. Please choose another.');
            }

            $broken = [];
            if (file_exists($brokenStatusFile)) {
                $broken = json_decode(file_get_contents($brokenStatusFile), true) ?: [];
            }
            if (in_array($assigned_pc, $broken)) {
                $conn->close();
                redirectAdminDashboard('reservation', 'error:The selected PC is out of service. Please choose another.');
            }

            $stmt = $conn->prepare("SELECT id, remaining_sessions FROM accounts WHERE id_number = ? AND role = 'student' LIMIT 1");
            $stmt->bind_param("s", $id_number);
            $stmt->execute();
            $student_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare("SELECT id FROM sit_in_records WHERE id_number = ? AND status = 'active' LIMIT 1");
            $stmt->bind_param("s", $id_number);
            $stmt->execute();
            $already_active = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare("SELECT id FROM reservations WHERE id_number = ? AND status = 'approved' AND id != ? LIMIT 1");
            $stmt->bind_param("si", $id_number, $res_id);
            $stmt->execute();
            $already_approved_res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($already_active) {
                $conn->close();
                redirectAdminDashboard('reservation', 'error:Student already has an active sit-in session. Cannot approve.');
            }

            if ($already_approved_res) {
                $conn->close();
                redirectAdminDashboard('reservation', 'error:This student already has an approved reservation. Resolve it first before approving another.');
            }

            if ($student_row && (int)$student_row['remaining_sessions'] > 0) {
                $stmt = $conn->prepare("UPDATE reservations SET status = 'approved', assigned_pc = ? WHERE id = ?");
                $stmt->bind_param("si", $assigned_pc, $res_id);
                $stmt->execute();
                $stmt->close();

                $remaining = (int)$student_row['remaining_sessions'];
                $stmt = $conn->prepare("INSERT INTO sit_in_records (id_number, student_name, purpose, lab, session, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->bind_param("ssssi", $id_number, $student_name, $purpose, $assigned_pc, $remaining);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE accounts SET remaining_sessions = remaining_sessions - 1 WHERE id = ?");
                $stmt->bind_param("i", $student_row['id']);
                $stmt->execute();
                $stmt->close();

                $conn->close();
                redirectAdminDashboard('reservation', 'success:Reservation approved! Student assigned to ' . $assigned_pc . '.');
            }

            $conn->close();
            redirectAdminDashboard('reservation', 'error:Student has no remaining sessions. Cannot approve.');
        }

        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $action, $res_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        redirectAdminDashboard('reservation', 'success:Reservation declined.');
    }
    redirectAdminDashboard('reservation', 'error:Invalid reservation selected.');
}

/* ---------------------------------------------------------------
   Handle timeout sit-in POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_reward'])) {
    $award_id = intval($_POST['reward_id'] ?? 0);
    if ($award_id > 0) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT reward_points FROM sit_in_records WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $award_id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($record && intval($record['reward_points']) === 0) {
            $stmt = $conn->prepare("UPDATE sit_in_records SET reward_points = 1 WHERE id = ?");
            $stmt->bind_param("i", $award_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            redirectAdminDashboard('sitinrecords', 'success:Reward point awarded for this session.');
        }
        $conn->close();
        redirectAdminDashboard('sitinrecords', 'error:Reward point has already been awarded or record not found.');
    }
    redirectAdminDashboard('sitinrecords', 'error:Invalid sit-in record selected.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeout_sitin'])) {
    $timeout_id = intval($_POST['timeout_id'] ?? 0);
    if ($timeout_id > 0) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id_number FROM sit_in_records WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("i", $timeout_id);
        $stmt->execute();
        $sitin_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE sit_in_records SET status = 'done', timeout_at = NOW() WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $timeout_id);
        $stmt->execute();
        $stmt->close();

        if ($sitin_row) {
            $id_num = $sitin_row['id_number'];
            $stmt = $conn->prepare("UPDATE reservations SET status = 'completed' WHERE id_number = ? AND status = 'approved'");
            $stmt->bind_param("s", $id_num);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();
        redirectAdminDashboard('sitinrecords', 'success:Student has been timed out successfully.');
    }
    redirectAdminDashboard('sitinrecords', 'error:Unable to time out the sit-in record.');
}

/* ---------------------------------------------------------------
   Handle delete sit-in POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sitin'])) {
    $delete_id = intval($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id_number FROM sit_in_records WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $sitin_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM sit_in_records WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        if ($sitin_row) {
            $id_num = $sitin_row['id_number'];
            $stmt = $conn->prepare("UPDATE reservations SET status = 'completed' WHERE id_number = ? AND status = 'approved'");
            $stmt->bind_param("s", $id_num);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();
        redirectAdminDashboard('sitinrecords', 'success:Sit-in record deleted successfully.');
    }
    redirectAdminDashboard('sitinrecords', 'error:Unable to delete the sit-in record.');
}

/* ---------------------------------------------------------------
   Handle delete feedback POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
    $fb_id = intval($_POST['feedback_id'] ?? 0);
    if ($fb_id > 0) {
        $conn = getConnection();
        $stmt = $conn->prepare("DELETE FROM feedbacks WHERE id = ?");
        $stmt->bind_param("i", $fb_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        redirectAdminDashboard('feedbackreports', 'success:Feedback deleted.');
    }
    redirectAdminDashboard('feedbackreports', 'error:Invalid feedback selected.');
}

/* ---------------------------------------------------------------
   Fetch all data
   --------------------------------------------------------------- */
$conn = getConnection();

$conn->query("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS assigned_pc VARCHAR(50) DEFAULT NULL");

$statsRegistered   = $conn->query("SELECT COUNT(*) AS c FROM accounts WHERE role='student'")->fetch_assoc()['c'] ?? 0;
$statsCurrentSitin = $conn->query("SELECT COUNT(*) AS c FROM sit_in_records WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$statsTotalSitin   = $conn->query("SELECT COUNT(*) AS c FROM sit_in_records")->fetch_assoc()['c'] ?? 0;

$pieData = [];
$pieResult = $conn->query("SELECT purpose, COUNT(*) as cnt FROM sit_in_records GROUP BY purpose");
if ($pieResult) while ($row = $pieResult->fetch_assoc()) $pieData[] = $row;

$announcements = [];
$annRes = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");
if ($annRes) while ($row = $annRes->fetch_assoc()) $announcements[] = $row;

$students = [];
$stuRes = $conn->query("SELECT id, id_number, first_name, middle_name, last_name, course, course_level, email, address, remaining_sessions FROM accounts WHERE role='student' ORDER BY id_number ASC");
if ($stuRes) while ($row = $stuRes->fetch_assoc()) $students[] = $row;

$sitins = [];
$siRes = $conn->query("SELECT * FROM sit_in_records ORDER BY id DESC LIMIT 100");
if ($siRes) while ($row = $siRes->fetch_assoc()) $sitins[] = $row;

$reservations = [];
$resResult = $conn->query("SELECT * FROM reservations ORDER BY FIELD(status,'pending','approved','declined','completed'), created_at DESC");
if ($resResult) while ($row = $resResult->fetch_assoc()) $reservations[] = $row;

$pendingCount = count(array_filter($reservations, fn($r) => ($r['status'] ?? '') === 'pending'));

/* Feedbacks */
$feedbacks = [];
$fbResult = $conn->query("
    SELECT f.*, s.purpose AS sit_purpose
    FROM feedbacks f
    LEFT JOIN sit_in_records s ON f.sit_in_id = s.id
    ORDER BY f.created_at DESC
");
if ($fbResult) while ($row = $fbResult->fetch_assoc()) $feedbacks[] = $row;
$feedbackCount = count($feedbacks);

/* Avg rating per lab */
$labRatings = [];
$lrResult = $conn->query("SELECT lab, ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total FROM feedbacks GROUP BY lab ORDER BY avg_rating DESC");
if ($lrResult) while ($row = $lrResult->fetch_assoc()) $labRatings[] = $row;

/* ---------------------------------------------------------------
   Leaderboard
   --------------------------------------------------------------- */
$leaderboard = [];
$lbRes = $conn->query("
  SELECT
    s.id_number,
    s.student_name,
    ROUND(SUM(s.reward_points) * 0.6 + SUM(TIMESTAMPDIFF(MINUTE, s.created_at, s.timeout_at)) / 60.0 * 0.2 + SUM(s.task_completed_points) * 0.2, 2) AS total_score
  FROM sit_in_records s
  WHERE s.status = 'done' AND s.timeout_at IS NOT NULL
  GROUP BY s.id_number, s.student_name
  ORDER BY total_score DESC
  LIMIT 3
");
if ($lbRes) while ($row = $lbRes->fetch_assoc()) $leaderboard[] = $row;

$conn->close();

/* PC status */
$brokenPcs = [];
if (file_exists($brokenStatusFile)) {
    $brokenPcs = json_decode(file_get_contents($brokenStatusFile), true) ?: [];
}

$occupiedPcs = [];
foreach ($sitins as $s) {
    if (($s['status'] ?? '') === 'active' && !empty($s['lab'])) {
        $occupiedPcs[$s['lab']] = $s['student_name'] ?? 'Student';
    }
}

$admindashboardMsg = $_SESSION['admindashboard_msg'] ?? '';
$openSection = $_SESSION['admindashboard_section'] ?? 'home';
unset($_SESSION['admindashboard_msg'], $_SESSION['admindashboard_section']);
if (!empty($_GET['section'])) {
    $openSection = htmlspecialchars($_GET['section']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — CCS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="design.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    body { background: var(--navy); }

    .admin-topnav { position:sticky;top:0;z-index:1000;height:52px;background:linear-gradient(90deg,#0e2b6e 0%,#153d90 50%,#1a47a8 100%);border-bottom:1px solid rgba(255,255,255,0.09);box-shadow:0 2px 20px rgba(0,0,0,0.4);display:flex;align-items:center;padding:0 24px;justify-content:space-between;gap:16px; }
    .admin-topnav-brand { font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:#fff;white-space:nowrap;letter-spacing:.01em; }
    .admin-topnav-links { display:flex;align-items:center;gap:1px;flex-wrap:nowrap; }
    .admin-topnav-links a,.admin-topnav-links button { background:none;border:none;color:rgba(255,255,255,0.88);font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:500;padding:6px 11px;border-radius:6px;text-decoration:none;cursor:pointer;white-space:nowrap;transition:background .18s,color .18s;display:inline-flex;align-items:center;gap:4px;line-height:1; }
    .admin-topnav-links a:hover,.admin-topnav-links button:hover { background:rgba(255,255,255,0.1);color:#fff; }
    .admin-topnav-links a.tnav-active { background:rgba(255,255,255,0.13);color:#fff; }
    .btn-tnav-logout { background:linear-gradient(135deg,#f0b429,#d4940f) !important;color:#0d1a36 !important;font-weight:700 !important;border-radius:6px !important;padding:6px 16px !important;box-shadow:0 2px 10px rgba(240,180,41,0.38); }
    .btn-tnav-logout:hover { background:linear-gradient(135deg,#fbc842,#e0a010) !important;box-shadow:0 4px 16px rgba(240,180,41,0.55) !important;transform:translateY(-1px);color:#0d1a36 !important; }
    .nav-badge { display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;padding:0 4px;border-radius:20px;background:var(--gold);color:#0d1a36;font-size:.6rem;font-weight:800;margin-left:3px; }

    .admin-main { flex:1;position:relative;z-index:1;padding:32px 24px 60px;display:flex;flex-direction:column;align-items:center;gap:28px; }
    .admin-section { width:100%;max-width:1200px;display:none;flex-direction:column;gap:20px;animation:riseIn 0.4s cubic-bezier(0.22,0.85,0.45,1) both; }
    .admin-section.active { display:flex; }

    .a-card { background:rgba(12,26,54,0.72);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid var(--navy-border);border-radius:20px;box-shadow:var(--shadow-card);overflow:hidden;display:flex;flex-direction:column; }
    .a-card-header { padding:14px 20px;background:linear-gradient(90deg,rgba(30,111,255,0.18) 0%,transparent 100%);border-bottom:1px solid var(--navy-border);display:flex;align-items:center;gap:10px; }
    .a-card-header-icon { width:30px;height:30px;border-radius:8px;background:rgba(30,111,255,0.2);border:1px solid rgba(30,111,255,0.3);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0; }
    .a-card-header-label { font-family:'Sora',sans-serif;font-size:.78rem;font-weight:700;color:#fff;letter-spacing:.04em;text-transform:uppercase; }
    .a-card-body { padding:22px; }

    .home-grid-top { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px; }

    .stat-pills { display:flex;flex-direction:column;gap:12px;margin-bottom:20px; }
    .stat-pill { display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:12px; }
    .stat-pill-label { font-size:.82rem;color:var(--text-muted);font-weight:500; }
    .stat-pill-val { font-family:'Sora',sans-serif;font-size:1.15rem;font-weight:800;color:#fff; }
    .stat-pill-val.gold { color:var(--gold); }
    .stat-pill-val.green { color:var(--success-c); }
    .chart-wrap { position:relative;height:220px;display:flex;align-items:center;justify-content:center; }

    .announce-form { display:flex;flex-direction:column;gap:10px;margin-bottom:20px; }
    .announce-textarea { width:100%;background:var(--input-bg);border:1.5px solid var(--input-border);border-radius:12px;padding:12px 14px;font-family:'DM Sans',sans-serif;font-size:.85rem;color:var(--text);outline:none;resize:vertical;min-height:80px;transition:border-color .2s,box-shadow .2s; }
    .announce-textarea:focus { border-color:var(--blue);box-shadow:0 0 0 4px var(--input-focus); }
    .btn-post { align-self:flex-start;padding:9px 22px;background:linear-gradient(135deg,#1e6fff,#0f4fd6);color:#fff;font-family:'Sora',sans-serif;font-size:.8rem;font-weight:700;border:none;border-radius:9px;cursor:pointer;box-shadow:0 4px 14px rgba(30,111,255,0.4);transition:box-shadow .2s,transform .18s; }
    .btn-post:hover { transform:translateY(-1px);box-shadow:0 6px 20px rgba(30,111,255,0.55); }
    .posted-label { font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:#fff;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--navy-border); }
    .ann-item { padding:12px 0;border-bottom:1px solid var(--navy-border); }
    .ann-item:last-child { border-bottom:none; }
    .ann-meta { font-size:.75rem;font-weight:700;color:rgba(255,255,255,0.55);margin-bottom:5px; }
    .ann-text { font-size:.82rem;color:var(--text-muted);line-height:1.55; }

    /* LEADERBOARD */
    .lb-body { padding:20px 22px;display:flex;flex-direction:column;gap:0; }
    .lb-subtitle { font-size:.72rem;color:var(--text-muted);text-align:center;margin-bottom:18px;letter-spacing:.04em;text-transform:uppercase; }
    .lb-podium { display:flex;align-items:flex-end;justify-content:center;gap:12px;margin-bottom:24px;padding:0 8px; }
    .lb-podium-slot { display:flex;flex-direction:column;align-items:center;gap:8px;flex:1;max-width:110px; }
    .lb-crown { font-size:1.4rem;line-height:1; }
    .lb-rank-1 .lb-crown { filter:drop-shadow(0 0 8px rgba(240,180,41,0.8)); }
    .lb-rank-2 .lb-crown { filter:drop-shadow(0 0 6px rgba(168,168,168,0.6)); }
    .lb-rank-3 .lb-crown { filter:drop-shadow(0 0 6px rgba(180,100,30,0.6)); }
    .lb-avatar { width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:#fff;flex-shrink:0;position:relative; }
    .lb-rank-1 .lb-avatar { width:64px;height:64px;font-size:1.3rem;background:linear-gradient(135deg,#f0b429,#d4940f);border:3px solid rgba(240,180,41,0.5);box-shadow:0 0 0 6px rgba(240,180,41,0.12),0 8px 24px rgba(240,180,41,0.3); }
    .lb-rank-2 .lb-avatar { background:linear-gradient(135deg,#9ca3af,#6b7280);border:2px solid rgba(156,163,175,0.4); }
    .lb-rank-3 .lb-avatar { background:linear-gradient(135deg,#cd7c3a,#a0522d);border:2px solid rgba(205,124,58,0.35); }
    .lb-badge-num { position:absolute;bottom:-4px;right:-4px;width:20px;height:20px;border-radius:50%;font-size:.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid #0c1a36; }
    .lb-rank-1 .lb-badge-num { background:var(--gold);color:#0d1a36; }
    .lb-rank-2 .lb-badge-num { background:#9ca3af;color:#0d1a36; }
    .lb-rank-3 .lb-badge-num { background:#cd7c3a;color:#fff; }
    .lb-podium-base { width:100%;border-radius:10px 10px 6px 6px;display:flex;align-items:flex-end;justify-content:center;padding-bottom:6px; }
    .lb-rank-1 .lb-podium-base { height:64px;background:linear-gradient(180deg,rgba(240,180,41,0.22),rgba(240,180,41,0.06));border:1px solid rgba(240,180,41,0.25); }
    .lb-rank-2 .lb-podium-base { height:46px;background:linear-gradient(180deg,rgba(156,163,175,0.15),rgba(156,163,175,0.04));border:1px solid rgba(156,163,175,0.18); }
    .lb-rank-3 .lb-podium-base { height:34px;background:linear-gradient(180deg,rgba(205,124,58,0.14),rgba(205,124,58,0.03));border:1px solid rgba(205,124,58,0.18); }
    .lb-name { font-family:'Sora',sans-serif;font-size:.72rem;font-weight:700;color:#fff;text-align:center;line-height:1.3;max-width:90px;word-break:break-word; }
    .lb-hours { font-family:'Sora',sans-serif;font-size:.78rem;font-weight:800;text-align:center; }
    .lb-rank-1 .lb-hours { color:var(--gold); }
    .lb-rank-2 .lb-hours { color:#9ca3af; }
    .lb-rank-3 .lb-hours { color:#cd7c3a; }
    .lb-list { display:flex;flex-direction:column;gap:6px; }
    .lb-list-item { display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:12px; }
    .lb-list-rank { font-family:'Sora',sans-serif;font-size:.8rem;font-weight:800;color:var(--text-muted);width:20px;text-align:center;flex-shrink:0; }
    .lb-list-avatar { width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:.82rem;font-weight:700;color:#fff;flex-shrink:0; }
    .lb-list-info { flex:1;min-width:0; }
    .lb-list-name { font-size:.82rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .lb-list-id   { font-size:.68rem;color:var(--text-muted); }
    .lb-list-stat { text-align:right;flex-shrink:0; }
    .lb-list-hours { font-family:'Sora',sans-serif;font-size:.85rem;font-weight:800;color:var(--blue-soft); }
    .lb-empty { text-align:center;padding:28px 20px;font-size:.8rem;color:rgba(255,255,255,0.2);display:flex;flex-direction:column;align-items:center;gap:8px; }
    .lb-empty-icon { font-size:2rem;opacity:.4; }
    .lb-divider { border:none;border-top:1px solid var(--navy-border);margin:16px 0 14px; }

    /* ROOM SELECTOR */
    .room-selector { display:flex;flex-direction:column;gap:0; }
    .room-tabs { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px; }
    .room-tab,.home-room-tab { padding:7px 16px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:8px;color:var(--text-muted);font-family:'Sora',sans-serif;font-size:.76rem;font-weight:600;cursor:pointer;transition:background .18s,color .18s,border-color .18s; }
    .room-tab:hover,.home-room-tab:hover { background:rgba(30,111,255,0.1);border-color:rgba(30,111,255,0.3);color:#fff; }
    .room-tab.active,.home-room-tab.active { background:linear-gradient(135deg,#1e6fff,#0f4fd6);border-color:transparent;color:#fff;box-shadow:0 3px 12px rgba(30,111,255,0.35); }
    .room-info,.room-info-text { font-size:.76rem;color:var(--text-muted);margin-bottom:12px;padding:8px 14px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:8px;line-height:1.5; }
    .room-grid-toolbar { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px; }
    .room-legend { display:flex;align-items:center;gap:14px;flex-wrap:wrap; }
    .legend-dot { display:inline-flex;align-items:center;gap:5px;font-size:.7rem;color:var(--text-muted); }
    .legend-dot span { display:inline-block;width:11px;height:11px;border-radius:3px; }
    .legend-dot .dot-avail { background:var(--input-bg);border:1px solid var(--navy-border); }
    .legend-dot .dot-occupied { background:rgba(52,211,153,0.35);border:1px solid rgba(52,211,153,0.6); }
    .legend-dot .dot-broken { background:rgba(248,113,113,0.35);border:1px solid rgba(248,113,113,0.5); }
    .btn-maintenance { padding:6px 14px;background:rgba(240,180,41,0.1);border:1px solid rgba(240,180,41,0.3);color:var(--gold);font-family:'Sora',sans-serif;font-size:.72rem;font-weight:700;border-radius:7px;cursor:pointer;transition:background .18s,box-shadow .18s;white-space:nowrap; }
    .btn-maintenance:hover { background:rgba(240,180,41,0.2); }
    .btn-maintenance.active-mode { background:rgba(240,180,41,0.22);border-color:rgba(240,180,41,0.6);color:#f0b429; }
    .room-grid { display:grid;grid-template-columns:repeat(10,1fr);gap:5px; }
    .room-button { padding:8px 2px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:7px;color:var(--text-muted);font-family:'DM Sans',sans-serif;font-size:.67rem;font-weight:500;cursor:pointer;transition:background .15s,color .15s,border-color .15s,transform .12s;text-align:center;position:relative; }
    .room-button:hover { background:rgba(30,111,255,0.18);border-color:rgba(30,111,255,0.45);color:#fff;transform:translateY(-1px); }
    .room-button.pc-occupied { background:rgba(52,211,153,0.18);border-color:rgba(52,211,153,0.5);color:#34d399;cursor:default; }
    .room-button.pc-broken { background:rgba(248,113,113,0.12);border-color:rgba(248,113,113,0.4);color:rgba(248,113,113,0.7);cursor:not-allowed; }
    .maintenance-on .room-button:not(.pc-occupied):not(.pc-broken) { border-style:dashed;border-color:rgba(240,180,41,0.45);cursor:crosshair; }
    .maintenance-on .room-button:not(.pc-occupied):not(.pc-broken):hover { background:rgba(240,180,41,0.12);border-color:rgba(240,180,41,0.65);color:var(--gold);transform:translateY(-1px); }
    .maintenance-on .room-button.pc-broken { cursor:pointer;border-style:solid; }
    .maintenance-on .room-button.pc-broken:hover { background:rgba(52,211,153,0.12);border-color:rgba(52,211,153,0.4);color:#34d399;transform:translateY(-1px); }
    @media(max-width:860px){ .room-grid{grid-template-columns:repeat(5,1fr);} }

    .page-title { font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;text-align:center;letter-spacing:-0.02em;margin-bottom:4px; }

    .table-toolbar { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap; }
    .table-toolbar-left { display:flex;align-items:center;gap:10px; }
    .table-search-wrap { display:flex;align-items:center;gap:8px; }
    .table-search-wrap label { font-size:.78rem;color:var(--text-muted); }
    .tbl-search-input { background:var(--input-bg);border:1px solid var(--input-border);border-radius:8px;padding:7px 12px;font-family:'DM Sans',sans-serif;font-size:.8rem;color:var(--text);outline:none;width:180px;transition:border-color .2s; }
    .tbl-search-input:focus { border-color:var(--blue); }
    .entries-select { background:var(--input-bg);border:1px solid var(--input-border);border-radius:7px;padding:6px 10px;font-family:'DM Sans',sans-serif;font-size:.78rem;color:var(--text);outline:none;cursor:pointer; }
    .tbl-wrap { overflow-x:auto; }
    table.a-table { width:100%;border-collapse:collapse;font-size:.82rem; }
    .a-table thead th { padding:10px 14px;text-align:left;font-family:'Sora',sans-serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);border-bottom:1px solid var(--navy-border);white-space:nowrap;cursor:pointer;user-select:none; }
    .a-table thead th:hover { color:#fff; }
    .a-table thead th .sort-arrow { opacity:.4;font-size:.6rem;margin-left:4px; }
    .a-table thead th.sort-asc .sort-arrow::after { content:' ▲';opacity:1; }
    .a-table thead th.sort-desc .sort-arrow::after { content:' ▼';opacity:1; }
    .a-table tbody tr { border-bottom:1px solid var(--navy-border);transition:background .15s; }
    .a-table tbody tr:last-child { border-bottom:none; }
    .a-table tbody tr:hover { background:rgba(30,111,255,0.06); }
    .a-table td { padding:10px 14px;color:var(--text);vertical-align:middle; }
    .a-table td.muted { color:var(--text-muted); }
    .tbl-empty { text-align:center;padding:28px;color:rgba(255,255,255,0.2);font-size:.82rem; }
    .tbl-footer { display:flex;align-items:center;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:10px; }
    .tbl-info { font-size:.75rem;color:var(--text-muted); }
    .tbl-pages { display:flex;gap:4px; }
    .tbl-pages button { width:28px;height:28px;border-radius:6px;border:1px solid var(--navy-border);background:var(--input-bg);color:var(--text-muted);font-size:.75rem;cursor:pointer;transition:background .15s,color .15s; }
    .tbl-pages button:hover,.tbl-pages button.active { background:var(--blue);color:#fff;border-color:var(--blue); }

    .btn-add { padding:8px 18px;background:linear-gradient(135deg,#1e6fff,#0f4fd6);color:#fff;font-family:'Sora',sans-serif;font-size:.78rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;box-shadow:0 3px 12px rgba(30,111,255,0.35);transition:box-shadow .2s,transform .18s; }
    .btn-add:hover { transform:translateY(-1px); }
    .btn-reset { padding:8px 18px;background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.3);color:var(--error);font-family:'Sora',sans-serif;font-size:.78rem;font-weight:700;border-radius:8px;cursor:pointer; }
    .btn-edit { padding:5px 12px;background:rgba(30,111,255,0.15);border:1px solid rgba(30,111,255,0.3);color:var(--blue-soft);font-size:.73rem;font-weight:600;border-radius:6px;cursor:pointer; }
    .btn-delete { padding:5px 12px;background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.25);color:var(--error);font-size:.73rem;font-weight:600;border-radius:6px;cursor:pointer; }
    .btn-delete:hover { background:rgba(248,113,113,0.22); }
    .btn-timeout { padding:5px 12px;background:rgba(240,180,41,0.12);border:1px solid rgba(240,180,41,0.25);color:var(--gold);font-size:.73rem;font-weight:600;border-radius:6px;cursor:pointer; }
    .btn-approve { padding:5px 12px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.25);color:var(--success-c);font-size:.73rem;font-weight:600;border-radius:6px;cursor:pointer; }
    .btn-decline { padding:5px 12px;background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.25);color:var(--error);font-size:.73rem;font-weight:600;border-radius:6px;cursor:pointer; }

    .badge { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase; }
    .badge-active    { background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.25);color:var(--success-c); }
    .badge-done      { background:rgba(107,127,163,0.12);border:1px solid rgba(107,127,163,0.2);color:var(--text-muted); }
    .badge-pending   { background:rgba(240,180,41,0.12);border:1px solid rgba(240,180,41,0.25);color:var(--gold); }
    .badge-approved  { background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.25);color:var(--success-c); }
    .badge-declined  { background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.25);color:var(--error); }
    .badge-completed { background:rgba(107,127,163,0.12);border:1px solid rgba(107,127,163,0.2);color:var(--text-muted); }

    .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);z-index:2000;display:none;align-items:center;justify-content:center;padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:#0d1e40;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:28px 32px;width:100%;max-width:480px;position:relative;box-shadow:0 24px 72px rgba(0,0,0,0.6);animation:riseIn 0.3s cubic-bezier(0.22,0.85,0.45,1) both; }
    .modal-title { font-family:'Sora',sans-serif;font-size:1.05rem;font-weight:700;color:#fff;margin-bottom:20px; }
    .modal-close { position:absolute;top:16px;right:16px;width:28px;height:28px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.05);color:var(--text-muted);font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center; }
    .modal-close:hover { background:rgba(255,255,255,0.12);color:#fff; }
    .modal-field { margin-bottom:14px; }
    .modal-field label { display:block;font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px; }
    .modal-field input,.modal-field select { width:100%;background:var(--input-bg);border:1.5px solid var(--input-border);border-radius:10px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--text);outline:none;transition:border-color .2s;box-sizing:border-box; }
    .modal-field input:focus,.modal-field select:focus { border-color:var(--blue);box-shadow:0 0 0 4px var(--input-focus); }
    .modal-field input[readonly] { opacity:.6;cursor:default; }
    .modal-actions { display:flex;justify-content:flex-end;gap:10px;margin-top:22px; }
    .btn-modal-cancel { padding:9px 20px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:var(--text-muted);font-size:.82rem;font-weight:600;cursor:pointer; }
    .btn-modal-cancel:hover { background:rgba(255,255,255,0.1);color:#fff; }
    .btn-modal-confirm { padding:9px 22px;background:linear-gradient(135deg,#1e6fff,#0f4fd6);color:#fff;font-family:'Sora',sans-serif;font-size:.82rem;font-weight:700;border:none;border-radius:9px;cursor:pointer;box-shadow:0 4px 14px rgba(30,111,255,0.4); }
    .btn-modal-confirm:hover { transform:translateY(-1px); }
    .search-modal-input { width:100%;background:var(--input-bg);border:1.5px solid var(--input-border);border-radius:10px;padding:11px 14px;font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--text);outline:none;margin-bottom:14px;transition:border-color .2s;box-sizing:border-box; }
    .search-modal-input:focus { border-color:var(--blue);box-shadow:0 0 0 4px var(--input-focus); }
    .search-results { margin-top:10px;display:flex;flex-direction:column;gap:8px; }
    .search-result-item { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:10px;cursor:pointer; }
    .search-result-item:hover { border-color:rgba(30,111,255,0.3); }
    .sri-name { font-size:.85rem;font-weight:600;color:#fff; }
    .sri-id   { font-size:.73rem;color:var(--text-muted); }

    /* APPROVE MODAL */
    .approve-modal-info { display:flex;flex-direction:column;gap:8px;margin-bottom:18px;padding:14px 16px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:12px; }
    .approve-modal-info .ami-row { display:flex;justify-content:space-between;align-items:center; }
    .approve-modal-info .ami-label { font-size:.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em; }
    .approve-modal-info .ami-value { font-size:.82rem;color:#fff;font-weight:600; }
    .approve-room-tabs { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px; }
    .approve-room-tab { padding:7px 16px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:8px;color:var(--text-muted);font-family:'Sora',sans-serif;font-size:.76rem;font-weight:600;cursor:pointer;transition:background .18s,color .18s,border-color .18s; }
    .approve-room-tab:hover { background:rgba(30,111,255,0.1);border-color:rgba(30,111,255,0.3);color:#fff; }
    .approve-room-tab.active { background:linear-gradient(135deg,#1e6fff,#0f4fd6);border-color:transparent;color:#fff; }
    .approve-pc-grid { display:grid;grid-template-columns:repeat(5,1fr);gap:5px;max-height:260px;overflow-y:auto;padding:4px 0; }
    .approve-pc-btn { padding:8px 2px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:7px;color:var(--text-muted);font-family:'DM Sans',sans-serif;font-size:.7rem;font-weight:500;cursor:pointer;transition:all .15s;text-align:center; }
    .approve-pc-btn:hover { background:rgba(52,211,153,0.18);border-color:rgba(52,211,153,0.5);color:#34d399;transform:translateY(-1px); }
    .approve-pc-btn.selected { background:rgba(52,211,153,0.25);border-color:rgba(52,211,153,0.7);color:#34d399;box-shadow:0 0 0 2px rgba(52,211,153,0.3); }
    .approve-pc-btn.pc-unavailable { background:rgba(248,113,113,0.08);border-color:rgba(248,113,113,0.2);color:rgba(248,113,113,0.4);cursor:not-allowed;pointer-events:none; }
    .approve-selected-pc { margin-top:12px;padding:10px 14px;background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.25);border-radius:10px;font-size:.82rem;color:var(--success-c);font-weight:600;text-align:center;display:none; }
    .approve-selected-pc.visible { display:block; }
    .approve-loading { text-align:center;padding:20px;font-size:.78rem;color:var(--text-muted); }

    /* FEEDBACK REPORTS */
    .fb-summary-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px; }
    .fb-stat-card { padding:16px 18px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:14px;display:flex;flex-direction:column;gap:6px; }
    .fb-stat-label { font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600; }
    .fb-stat-val { font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800;color:#fff; }
    .fb-stat-sub { font-size:.7rem;color:var(--text-muted); }
    .fb-stars { color:#f0b429;letter-spacing:2px;font-size:.95rem; }
    .fb-lab-ratings { display:flex;flex-direction:column;gap:8px;margin-bottom:24px; }
    .fb-lab-row { display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--input-bg);border:1px solid var(--navy-border);border-radius:10px; }
    .fb-lab-name { font-size:.82rem;font-weight:700;color:#fff;min-width:80px; }
    .fb-lab-bar-wrap { flex:1;height:8px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden; }
    .fb-lab-bar { height:100%;background:linear-gradient(90deg,#1e6fff,#34d399);border-radius:4px;transition:width .6s ease; }
    .fb-lab-avg { font-family:'Sora',sans-serif;font-size:.8rem;font-weight:700;color:var(--gold);min-width:40px;text-align:right; }
    .fb-lab-count { font-size:.7rem;color:var(--text-muted);min-width:60px;text-align:right; }

    .feedback-message-cell { max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer; }
    .feedback-message-cell:hover { white-space:normal;overflow:visible; }

    @media (max-width:1100px) { .home-grid-top { grid-template-columns:1fr; } }
    @media (max-width:860px) { .admin-topnav-links{gap:0;} .admin-topnav-links a,.admin-topnav-links button{padding:6px 8px;font-size:.72rem;} }
  </style>
</head>
<body>
<div class="scene-bg"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>

<header class="admin-topnav">
  <div class="admin-topnav-brand">College of Computer Studies Admin</div>
  <nav class="admin-topnav-links">
    <a href="#" id="nav-home" class="tnav-active" onclick="showSection('home',this);return false;">Home</a>
    <a href="#" onclick="openSearchModal();return false;">Search</a>
    <a href="#" id="nav-students" onclick="showSection('students',this);return false;">Students</a>
    <a href="#" onclick="openSearchModal();return false;">Sit-in</a>
    <a href="#" id="nav-sitinrecords" onclick="showSection('sitinrecords',this);return false;">View Sit-in Records</a>
    <a href="#" id="nav-sitinreports" onclick="showSection('sitinreports',this);return false;">Sit-in Reports</a>
    <a href="#" id="nav-feedbackreports" onclick="showSection('feedbackreports',this);return false;">
      Feedback Reports<?php if ($feedbackCount > 0): ?><span class="nav-badge"><?= $feedbackCount ?></span><?php endif; ?>
    </a>
    <a href="#" id="nav-reservation" onclick="showSection('reservation',this);return false;">
      Reservation<?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a href="logout.php" class="btn-tnav-logout">Log out</a>
  </nav>
</header>

<main class="admin-main">

  <!-- HOME -->
  <div class="admin-section active" id="sec-home">

    <div class="home-grid-top">
      <div class="a-card">
        <div class="a-card-header"><div class="a-card-header-icon">📊</div><span class="a-card-header-label">Statistics</span></div>
        <div class="a-card-body">
          <div class="stat-pills">
            <div class="stat-pill"><span class="stat-pill-label">Students Registered</span><span class="stat-pill-val gold"><?= $statsRegistered ?></span></div>
            <div class="stat-pill"><span class="stat-pill-label">Currently Sit-in</span><span class="stat-pill-val green"><?= $statsCurrentSitin ?></span></div>
            <div class="stat-pill"><span class="stat-pill-label">Total Sit-in</span><span class="stat-pill-val"><?= $statsTotalSitin ?></span></div>
          </div>
          <div class="chart-wrap"><canvas id="pieChart"></canvas></div>
        </div>
      </div>

      <div class="a-card">
        <div class="a-card-header"><div class="a-card-header-icon">📢</div><span class="a-card-header-label">Announcement</span></div>
        <div class="a-card-body">
          <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="announce-form">
            <textarea name="announcement" class="announce-textarea" placeholder="New Announcement…"></textarea>
            <button type="submit" class="btn-post">Submit</button>
          </form>
          <div class="posted-label">Posted Announcement</div>
          <?php if (empty($announcements)): ?>
            <p style="font-size:.78rem;color:var(--text-muted);">No announcements yet.</p>
          <?php else: foreach ($announcements as $ann): ?>
            <div class="ann-item">
              <div class="ann-meta">
                <span>CCS Admin | <?= date('Y-M-d', strtotime($ann['created_at'])) ?></span>
                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline-block;margin-left:12px;">
                  <input type="hidden" name="delete_announcement" value="1">
                  <input type="hidden" name="announcement_id" value="<?= intval($ann['id']) ?>">
                  <button type="submit" class="btn-delete" onclick="return confirm('Delete this announcement?');">Delete</button>
                </form>
              </div>
              <?php if (!empty($ann['content'])): ?><div class="ann-text"><?= htmlspecialchars($ann['content']) ?></div><?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Leaderboard -->
    <div class="a-card">
      <div class="a-card-header">
        <div class="a-card-header-icon">🏆</div>
        <span class="a-card-header-label">Top Sit-in Leaderboard</span>
        <span style="margin-left:auto;font-size:.7rem;color:var(--text-muted);font-style:italic;">Top 3 sit-in students</span>
      </div>
      <?php if (empty($leaderboard)): ?>
        <div class="lb-body"><div class="lb-empty"><span class="lb-empty-icon">🏆</span><span>No completed sit-in sessions yet.</span></div></div>
      <?php else: ?>
        <div class="lb-body">
          <div class="lb-subtitle">Ranked by points</div>
          <?php
            $medals    = ['🥇','🥈','🥉'];
            $rankClass = ['lb-rank-1','lb-rank-2','lb-rank-3'];
            $podiumOrder = [];
            if (count($leaderboard) >= 2) $podiumOrder[] = 1;
            if (count($leaderboard) >= 1) $podiumOrder[] = 0;
            if (count($leaderboard) >= 3) $podiumOrder[] = 2;
          ?>
          <div class="lb-podium">
            <?php foreach ($podiumOrder as $idx): $entry = $leaderboard[$idx]; $rankNum = $idx + 1; ?>
              <div class="lb-podium-slot <?= $rankClass[$idx] ?>">
                <div class="lb-crown"><?= $medals[$idx] ?></div>
                <div class="lb-avatar"><?= strtoupper(substr($entry['student_name'], 0, 1)) ?><span class="lb-badge-num"><?= $rankNum ?></span></div>
                <div class="lb-name"><?= htmlspecialchars($entry['student_name']) ?></div>
                <div class="lb-hours" style="font-size:0.9em;color:#2196f3;font-weight:bold;"><?= number_format((float)$entry['total_score'], 2) ?> pts</div>
                <div class="lb-podium-base"></div>
              </div>
            <?php endforeach; ?>
          </div>
          <hr class="lb-divider">
          <div class="lb-list">
            <?php $listColors = ['rgba(240,180,41,0.15)','rgba(156,163,175,0.12)','rgba(205,124,58,0.12)']; foreach ($leaderboard as $idx => $entry): ?>
              <div class="lb-list-item">
                <div class="lb-list-rank"><?= $medals[$idx] ?></div>
                <div class="lb-list-avatar" style="background:<?= $listColors[$idx] ?? 'rgba(30,111,255,0.15)' ?>;border:1px solid rgba(255,255,255,0.08);"><?= strtoupper(substr($entry['student_name'], 0, 1)) ?></div>
                <div class="lb-list-info"><div class="lb-list-name"><?= htmlspecialchars($entry['student_name']) ?></div><div class="lb-list-id"><?= htmlspecialchars($entry['id_number']) ?></div></div>
                <div class="lb-list-stat"><div class="lb-list-hours" style="color:#2196f3;"><?= number_format((float)$entry['total_score'], 2) ?> pts</div></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Lab Room PC Selector -->
    <div class="a-card">
      <div class="a-card-header">
        <div class="a-card-header-icon">🖥️</div>
        <span class="a-card-header-label">Lab Room PC Availability</span>
        <span style="margin-left:auto;font-size:.7rem;color:var(--text-muted);font-style:italic;">Click a PC to start sit-in</span>
      </div>
      <div class="a-card-body">
        <div class="room-selector">
          <div class="room-tabs" id="homeRoomTabs"></div>
          <div class="room-grid-toolbar">
            <div class="room-legend">
              <span class="legend-dot"><span class="dot-avail"></span>Available</span>
              <span class="legend-dot"><span class="dot-occupied"></span>Occupied</span>
              <span class="legend-dot"><span class="dot-broken"></span>Out of Service</span>
            </div>
            <button type="button" class="btn-maintenance" id="homeMaintenanceBtn" onclick="toggleMaintenanceMode('home')">⚙ Maintenance Mode</button>
          </div>
          <div class="room-info-text" id="homeRoomInfo">Select a room above, then click a PC to begin sit-in.</div>
          <div class="room-grid" id="homeRoomPcGrid"></div>
        </div>
      </div>
    </div>

  </div>

  <!-- STUDENTS -->
  <div class="admin-section" id="sec-students">
    <div class="page-title">Students Information</div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="table-toolbar">
          <div class="table-toolbar-left">
            <button class="btn-add" onclick="openAddStudentModal()">+ Add Students</button>
            <button class="btn-reset" onclick="confirmResetAllSessions()">Reset All Session</button>
            <label style="font-size:.78rem;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
              <select class="entries-select" id="stuEntries" onchange="renderStudentsTable()"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select> entries per page
            </label>
          </div>
          <div class="table-search-wrap"><label>Search:</label><input type="text" class="tbl-search-input" id="stuSearch" oninput="renderStudentsTable()" placeholder="Search…"></div>
        </div>
        <div class="tbl-wrap">
          <table class="a-table" id="stuTable">
            <thead><tr>
              <th onclick="sortTable('stu','id_number',this)">ID Number <span class="sort-arrow"></span></th>
              <th onclick="sortTable('stu','name',this)">Name <span class="sort-arrow"></span></th>
              <th onclick="sortTable('stu','year',this)">Year Level <span class="sort-arrow"></span></th>
              <th onclick="sortTable('stu','course',this)">Course <span class="sort-arrow"></span></th>
              <th onclick="sortTable('stu','sessions',this)">Remaining Session <span class="sort-arrow"></span></th>
              <th>Actions</th>
            </tr></thead>
            <tbody id="stuTbody"></tbody>
          </table>
        </div>
        <div class="tbl-footer"><span class="tbl-info" id="stuInfo"></span><div class="tbl-pages" id="stuPages"></div></div>
      </div>
    </div>
  </div>

  <!-- VIEW SIT-IN RECORDS -->
  <div class="admin-section" id="sec-sitinrecords">
    <div class="page-title">Sit-in Records</div>
    <div style="margin-bottom:12px;font-size:.9rem;color:var(--text-muted);">Admin may award 1 reward point for a well-performed session.</div>
    <div style="margin-bottom:15px;">
      <span style="font-weight:600;margin-right:10px;">Export:</span>
      <a href="?export_sitins=csv" class="btn-export" style="background:#28a745;color:#fff;padding:6px 12px;border-radius:4px;text-decoration:none;margin-right:5px;font-size:0.85rem;">📄 CSV</a>
      <a href="?export_sitins=pdf" class="btn-export" style="background:#dc3545;color:#fff;padding:6px 12px;border-radius:4px;text-decoration:none;margin-right:5px;font-size:0.85rem;">📑 PDF</a>
      <a href="?export_sitins=doc" class="btn-export" style="background:#007bff;color:#fff;padding:6px 12px;border-radius:4px;text-decoration:none;font-size:0.85rem;">📝 DOC</a>
    </div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="room-selector">
          <div class="room-tabs" id="roomTabs"></div>
          <div class="room-grid-toolbar">
            <div class="room-legend">
              <span class="legend-dot"><span class="dot-avail"></span>Available</span>
              <span class="legend-dot"><span class="dot-occupied"></span>Occupied</span>
              <span class="legend-dot"><span class="dot-broken"></span>Out of Service</span>
            </div>
            <button type="button" class="btn-maintenance" id="sitrMaintenanceBtn" onclick="toggleMaintenanceMode('sitr')">⚙ Maintenance Mode</button>
          </div>
          <div class="room-info" id="roomInfo">Select a room above, then choose a PC to start sit-in.</div>
          <div class="room-grid" id="roomPcGrid"></div>
        </div>
      </div>
    </div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="tbl-wrap">
          <table class="a-table">
            <thead><tr><th>Sit ID</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>Session</th><th>Status</th><th>Reward</th><th>Task</th><th>Time In</th><th>Time Out</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($sitins)): ?>
                <tr><td colspan="12" class="tbl-empty">No sit-in records available.</td></tr>
              <?php else: foreach ($sitins as $s): $st = $s['status'] ?? 'done'; ?>
                <tr>
                  <td><?= htmlspecialchars($s['id']) ?></td>
                  <td><?= htmlspecialchars($s['id_number'] ?? '') ?></td>
                  <td><?= htmlspecialchars($s['student_name'] ?? '') ?></td>
                  <td><?= htmlspecialchars($s['purpose'] ?? '') ?></td>
                  <td><?= htmlspecialchars($s['lab'] ?? '') ?></td>
                  <td><?= htmlspecialchars($s['session'] ?? '') ?></td>
                  <td><span class="badge <?= $st === 'active' ? 'badge-active' : 'badge-done' ?>"><?= $st === 'active' ? '● Active' : '✓ Done' ?></span></td>
                  <td><?= intval($s['reward_points'] ?? 0) ?></td>
                  <td><?= intval($s['task_completed_points'] ?? 0) ?></td>
                  <td class="muted"><?= date('M d, Y g:i A', strtotime($s['created_at'] ?? 'now')) ?></td>
                  <td class="muted">
                    <?php if (!empty($s['timeout_at'])): ?><?= date('M d, Y g:i A', strtotime($s['timeout_at'])) ?>
                    <?php elseif ($st === 'active'): ?><span style="color:var(--success-c);font-size:.72rem;">Still active</span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                      <?php if ($st === 'active'): ?>
                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;">
                          <input type="hidden" name="timeout_sitin" value="1">
                          <input type="hidden" name="timeout_id" value="<?= $s['id'] ?>">
                          <button type="submit" class="btn-timeout" onclick="return confirm('Time out this student?')">⏱ Time-out</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($st === 'done' && intval($s['reward_points'] ?? 0) === 0): ?>
                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;">
                          <input type="hidden" name="award_reward" value="1">
                          <input type="hidden" name="reward_id" value="<?= $s['id'] ?>">
                          <button type="submit" class="btn-timeout" onclick="return confirm('Award 1 reward point?')">⭐ Award</button>
                        </form>
                      <?php elseif (intval($s['reward_points'] ?? 0) > 0): ?>
                        <span style="font-weight:700;color:#f59e0b;">Awarded</span>
                      <?php endif; ?>
                      <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;">
                        <input type="hidden" name="delete_sitin" value="1">
                        <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn-delete" onclick="return confirm('Delete this record?')">🗑 Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- SIT-IN REPORTS -->
  <div class="admin-section" id="sec-sitinreports">
    <div class="page-title">Sit-in Reports</div>
    <div class="a-card"><div class="a-card-body"><div class="chart-wrap" style="height:300px;"><canvas id="barChart"></canvas></div></div></div>
  </div>

  <!-- ============================================================
       FEEDBACK REPORTS
       ============================================================ -->
  <div class="admin-section" id="sec-feedbackreports">
    <div class="page-title">Feedback Reports</div>

    <!-- Summary cards -->
    <?php
      $totalFb   = count($feedbacks);
      $avgRating = $totalFb > 0 ? round(array_sum(array_column($feedbacks, 'rating')) / $totalFb, 2) : 0;
      $fiveStars = count(array_filter($feedbacks, fn($f) => intval($f['rating']) === 5));
    ?>
    <div class="fb-summary-grid">
      <div class="fb-stat-card">
        <div class="fb-stat-label">Total Feedbacks</div>
        <div class="fb-stat-val"><?= $totalFb ?></div>
        <div class="fb-stat-sub">from students</div>
      </div>
      <div class="fb-stat-card">
        <div class="fb-stat-label">Average Rating</div>
        <div class="fb-stat-val" style="color:var(--gold);"><?= number_format($avgRating, 1) ?> / 5</div>
        <div class="fb-stars"><?= str_repeat('★', round($avgRating)) ?><?= str_repeat('☆', 5 - round($avgRating)) ?></div>
      </div>
      <div class="fb-stat-card">
        <div class="fb-stat-label">5-Star Reviews</div>
        <div class="fb-stat-val" style="color:var(--success-c);"><?= $fiveStars ?></div>
        <div class="fb-stat-sub"><?= $totalFb > 0 ? round($fiveStars / $totalFb * 100) : 0 ?>% of total</div>
      </div>
    </div>

    <!-- Per-lab ratings -->
    <?php if (!empty($labRatings)): ?>
    <div class="a-card">
      <div class="a-card-header"><div class="a-card-header-icon">🖥️</div><span class="a-card-header-label">Average Rating by Lab / PC</span></div>
      <div class="a-card-body">
        <div class="fb-lab-ratings">
          <?php foreach ($labRatings as $lr): ?>
            <div class="fb-lab-row">
              <div class="fb-lab-name"><?= htmlspecialchars($lr['lab']) ?></div>
              <div class="fb-lab-bar-wrap">
                <div class="fb-lab-bar" style="width:<?= min(100, ($lr['avg_rating'] / 5) * 100) ?>%"></div>
              </div>
              <div class="fb-stars" style="font-size:.75rem;"><?= str_repeat('★', round($lr['avg_rating'])) ?><?= str_repeat('☆', 5 - round($lr['avg_rating'])) ?></div>
              <div class="fb-lab-avg"><?= number_format($lr['avg_rating'], 1) ?></div>
              <div class="fb-lab-count"><?= $lr['total'] ?> review<?= $lr['total'] != 1 ? 's' : '' ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- All feedback table -->
    <div class="a-card">
      <div class="a-card-header"><div class="a-card-header-icon">💬</div><span class="a-card-header-label">All Student Feedbacks</span></div>
      <div class="a-card-body">
        <div class="tbl-wrap">
          <table class="a-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Student</th>
                <th>ID Number</th>
                <th>Lab / PC</th>
                <th>Purpose</th>
                <th>Rating</th>
                <th>Feedback</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($feedbacks)): ?>
                <tr><td colspan="9" class="tbl-empty">No feedback submitted yet.</td></tr>
              <?php else: foreach ($feedbacks as $fb): ?>
                <tr>
                  <td class="muted"><?= htmlspecialchars($fb['id']) ?></td>
                  <td style="font-weight:600;color:#e8edf8;"><?= htmlspecialchars($fb['student_name'] ?? '') ?></td>
                  <td class="muted"><?= htmlspecialchars($fb['id_number'] ?? '') ?></td>
                  <td><?= htmlspecialchars($fb['lab'] ?? '') ?></td>
                  <td class="muted"><?= htmlspecialchars($fb['sit_purpose'] ?? '—') ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:6px;">
                      <span class="fb-stars" style="font-size:.85rem;letter-spacing:1px;"><?= str_repeat('★', intval($fb['rating'] ?? 0)) ?><?= str_repeat('☆', 5 - intval($fb['rating'] ?? 0)) ?></span>
                      <span style="font-size:.72rem;color:var(--text-muted);">(<?= intval($fb['rating'] ?? 0) ?>/5)</span>
                    </div>
                  </td>
                  <td class="feedback-message-cell" title="<?= htmlspecialchars($fb['message'] ?? '') ?>"><?= htmlspecialchars($fb['message'] ?? '') ?></td>
                  <td class="muted" style="white-space:nowrap;"><?= date('M d, Y g:i A', strtotime($fb['created_at'] ?? 'now')) ?></td>
                  <td>
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;">
                      <input type="hidden" name="delete_feedback" value="1">
                      <input type="hidden" name="feedback_id" value="<?= intval($fb['id']) ?>">
                      <button type="submit" class="btn-delete" onclick="return confirm('Delete this feedback?')">🗑</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- RESERVATION -->
  <div class="admin-section" id="sec-reservation">
    <div class="page-title">Reservation Requests</div>
    <div style="margin-bottom:12px;font-size:.9rem;color:var(--text-muted);">Approve a reservation to assign a specific PC to the student.</div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="tbl-wrap">
          <table class="a-table">
            <thead><tr><th>#</th><th>Student</th><th>ID Number</th><th>Requested Lab</th><th>Purpose</th><th>Date</th><th>Time</th><th>Status</th><th>Assigned PC</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($reservations)): ?>
                <tr><td colspan="10" class="tbl-empty">No reservation requests yet.</td></tr>
              <?php else: foreach ($reservations as $r): $rs = $r['status'] ?? 'pending'; ?>
                <tr>
                  <td class="muted"><?= htmlspecialchars($r['id']) ?></td>
                  <td><?= htmlspecialchars($r['student_name'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['id_number'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['lab'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['purpose'] ?? '') ?></td>
                  <td class="muted"><?= date('M d, Y', strtotime($r['reservation_date'] ?? 'now')) ?></td>
                  <td class="muted"><?= date('g:i A', strtotime($r['reservation_time'] ?? '00:00')) ?></td>
                  <td><span class="badge badge-<?= $rs ?>"><?= $rs==='pending'?'⏳ Pending':($rs==='approved'?'✅ Approved':($rs==='completed'?'Completed':'❌ Declined')) ?></span></td>
                  <td>
                    <?php if ($rs === 'approved' && !empty($r['assigned_pc'])): ?>
                      <span class="badge badge-approved">🖥️ <?= htmlspecialchars($r['assigned_pc']) ?></span>
                    <?php elseif ($rs === 'approved'): ?>
                      <span class="badge badge-approved"><?= htmlspecialchars($r['lab'] ?? 'Assigned') ?></span>
                    <?php else: ?><span style="font-size:.72rem;color:var(--text-muted);">—</span><?php endif; ?>
                  </td>
                  <td>
                    <?php if ($rs === 'pending'): ?>
                      <div style="display:flex;gap:6px;">
                        <button type="button" class="btn-approve" onclick='openApproveModal(<?= json_encode(["id"=>$r["id"],"student_name"=>$r["student_name"]??"","id_number"=>$r["id_number"]??"","lab"=>$r["lab"]??"","purpose"=>$r["purpose"]??""]) ?>)'>✅ Approve</button>
                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:inline;">
                          <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                          <input type="hidden" name="reservation_action" value="decline">
                          <button type="submit" class="btn-decline">❌ Decline</button>
                        </form>
                      </div>
                    <?php else: ?><span style="font-size:.72rem;color:var(--text-muted);">—</span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</main>

<!-- SEARCH MODAL -->
<div class="modal-overlay" id="searchModal">
  <div class="modal-box" style="max-width:420px;">
    <button class="modal-close" onclick="closeModal('searchModal')">✕</button>
    <div class="modal-title">🔍 Search Student</div>
    <div class="room-info-text" id="searchSelectedLab">Search student to start sit-in.</div>
    <input type="text" class="search-modal-input" id="searchModalInput" placeholder="Search by name or ID…" oninput="liveSearchStudents()">
    <div class="search-results" id="searchResults"></div>
    <div class="modal-actions"><button class="btn-modal-cancel" onclick="closeModal('searchModal')">Close</button><button class="btn-modal-confirm" onclick="liveSearchStudents()">Search</button></div>
  </div>
</div>

<!-- SIT-IN FORM MODAL -->
<div class="modal-overlay" id="sitinFormModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('sitinFormModal')">✕</button>
    <div class="modal-title">📋 Sit In Form</div>
    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
      <input type="hidden" name="sitin_submit" value="1">
      <div class="modal-field"><label>ID Number</label><input type="text" name="id_number" id="sitin_id" readonly></div>
      <div class="modal-field"><label>Student Name</label><input type="text" name="student_name" id="sitin_name" readonly></div>
      <div class="modal-field"><label>Purpose</label><input type="text" name="purpose" id="sitin_purpose" placeholder="e.g. C Programming" required></div>
      <div class="modal-field"><label>Lab</label><input type="text" name="lab" id="sitin_lab" placeholder="e.g. 524 PC 01" required></div>
      <div class="modal-field"><label>Remaining Session</label><input type="text" name="remaining_session" id="sitin_session" readonly></div>
      <div class="modal-actions"><button type="button" class="btn-modal-cancel" onclick="closeModal('sitinFormModal')">Close</button><button type="submit" class="btn-modal-confirm">Sit In</button></div>
    </form>
  </div>
</div>

<!-- APPROVE RESERVATION MODAL -->
<div class="modal-overlay" id="approveModal">
  <div class="modal-box" style="max-width:540px;">
    <button class="modal-close" onclick="closeModal('approveModal')">✕</button>
    <div class="modal-title">✅ Approve Reservation — Assign PC</div>
    <div class="approve-modal-info" id="approveInfo">
      <div class="ami-row"><span class="ami-label">Student</span><span class="ami-value" id="approve-student-name">—</span></div>
      <div class="ami-row"><span class="ami-label">ID Number</span><span class="ami-value" id="approve-id-number">—</span></div>
      <div class="ami-row"><span class="ami-label">Requested Lab</span><span class="ami-value" id="approve-req-lab">—</span></div>
      <div class="ami-row"><span class="ami-label">Purpose</span><span class="ami-value" id="approve-purpose">—</span></div>
    </div>
    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:10px;">Select a room, then click an available PC to assign:</div>
    <div class="approve-room-tabs" id="approveRoomTabs"></div>
    <div id="approvePcContainer"><div class="approve-loading">Select a room tab above to view available PCs.</div></div>
    <div class="approve-selected-pc" id="approveSelectedPcLabel">Selected: —</div>
    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="approveForm">
      <input type="hidden" name="reservation_id" id="approve-res-id" value="">
      <input type="hidden" name="reservation_action" value="approve">
      <input type="hidden" name="assigned_pc" id="approve-assigned-pc" value="">
      <div class="modal-actions">
        <button type="button" class="btn-modal-cancel" onclick="closeModal('approveModal')">Cancel</button>
        <button type="submit" class="btn-modal-confirm" id="approveSubmitBtn" disabled style="opacity:.5;cursor:not-allowed;">Confirm & Approve</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD/EDIT STUDENT MODAL -->
<div class="modal-overlay" id="studentModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('studentModal')">✕</button>
    <div class="modal-title" id="studentModalTitle">Add Student</div>
    <form method="post" action="process_student.php">
      <input type="hidden" name="action" id="studentAction" value="add">
      <input type="hidden" name="student_id" id="studentId">
      <div class="modal-field"><label>ID Number</label><input type="text" name="id_number" id="modal_id_number" required></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="modal-field"><label>First Name</label><input type="text" name="first_name" id="modal_first_name" required></div>
        <div class="modal-field"><label>Last Name</label><input type="text" name="last_name" id="modal_last_name" required></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="modal-field"><label>Course</label><input type="text" name="course" id="modal_course" required></div>
        <div class="modal-field"><label>Year Level</label><input type="number" name="course_level" id="modal_year" min="1" max="5" required></div>
      </div>
      <div class="modal-field"><label>Remaining Sessions</label><input type="number" name="remaining_sessions" id="modal_sessions" value="30" min="0"></div>
      <div class="modal-actions"><button type="button" class="btn-modal-cancel" onclick="closeModal('studentModal')">Cancel</button><button type="submit" class="btn-modal-confirm">Save</button></div>
    </form>
  </div>
</div>

<div class="toasts" id="toasts"></div>

<script>
const PHP_STUDENTS  = <?= json_encode(array_map(function($s){return['id'=>(int)$s['id'],'id_number'=>$s['id_number'],'name'=>trim(($s['first_name']??'').' '.($s['middle_name']?$s['middle_name'][0].'. ':'').($s['last_name']??'')),'year'=>$s['course_level']??'','course'=>$s['course']??'','sessions'=>$s['remaining_sessions']??30];}, $students)) ?>;
const PHP_PIE       = <?= json_encode($pieData) ?>;
const SITIN_ROOMS   = ['544','542','530','528','526','524'];

let BROKEN_PCS   = <?= json_encode(array_values($brokenPcs)) ?>;
let OCCUPIED_PCS = <?= json_encode($occupiedPcs) ?>;

let selectedSitInLab = '';
let homeMaintenanceOn = false;
let sitrMaintenanceOn = false;
let approveSelectedPc = '';
let approveReservationData = null;

/* Section navigation */
function showSection(id,el){
  document.querySelectorAll('.admin-section').forEach(s=>s.classList.remove('active'));
  document.getElementById('sec-'+id).classList.add('active');
  document.querySelectorAll('.admin-topnav-links a').forEach(a=>a.classList.remove('tnav-active'));
  if(el) el.classList.add('tnav-active');
  else{const m={home:'nav-home',students:'nav-students',sitinrecords:'nav-sitinrecords',sitinreports:'nav-sitinreports',feedbackreports:'nav-feedbackreports',reservation:'nav-reservation'};if(m[id])document.getElementById(m[id])?.classList.add('tnav-active');}
}

/* Charts */
const pieColors=['#1e6fff','#f0b429','#34d399','#f87171','#a78bfa','#fb923c','#38bdf8'];
new Chart(document.getElementById('pieChart'),{type:'pie',data:{labels:PHP_PIE.map(d=>d.purpose),datasets:[{data:PHP_PIE.map(d=>parseInt(d.cnt)),backgroundColor:pieColors,borderColor:'#0c1a36',borderWidth:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{color:'rgba(255,255,255,0.7)',font:{size:11},boxWidth:14,padding:14}}}}});
new Chart(document.getElementById('barChart'),{type:'bar',data:{labels:PHP_PIE.map(d=>d.purpose),datasets:[{label:'Sit-in Count',data:PHP_PIE.map(d=>parseInt(d.cnt)),backgroundColor:pieColors,borderRadius:6,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'rgba(255,255,255,0.7)'}}},scales:{x:{ticks:{color:'rgba(255,255,255,0.55)'},grid:{color:'rgba(255,255,255,0.05)'}},y:{ticks:{color:'rgba(255,255,255,0.55)'},grid:{color:'rgba(255,255,255,0.05)'}}}}});

/* Students table */
let stuPage=1,stuSortKey='id_number',stuSortDir='asc';
function sortTable(tbl,key,th){if(tbl==='stu'){stuSortDir=(stuSortKey===key&&stuSortDir==='asc')?'desc':'asc';stuSortKey=key;document.querySelectorAll('#stuTable th').forEach(t=>t.classList.remove('sort-asc','sort-desc'));th.classList.add('sort-'+stuSortDir);stuPage=1;renderStudentsTable();}}
function renderStudentsTable(){
  const query=((document.getElementById('stuSearch').value)||'').toLowerCase();
  const perPage=parseInt(document.getElementById('stuEntries').value);
  let data=PHP_STUDENTS.filter(s=>s.name.toLowerCase().includes(query)||String(s.id_number).toLowerCase().includes(query)||s.course.toLowerCase().includes(query));
  data.sort((a,b)=>{let va=a[stuSortKey]??'',vb=b[stuSortKey]??'';if(!isNaN(va)&&!isNaN(vb)){va=Number(va);vb=Number(vb);}else{va=String(va).toLowerCase();vb=String(vb).toLowerCase();}return stuSortDir==='asc'?(va>vb?1:-1):(va<vb?1:-1);});
  const total=data.length,pages=Math.max(1,Math.ceil(total/perPage));stuPage=Math.min(stuPage,pages);
  const slice=data.slice((stuPage-1)*perPage,stuPage*perPage);
  document.getElementById('stuTbody').innerHTML=slice.length===0?`<tr><td colspan="6" class="tbl-empty">No students found.</td></tr>`:slice.map(s=>`<tr><td>${s.id_number}</td><td>${s.name}</td><td class="muted">${s.year}</td><td>${s.course}</td><td>${s.sessions}</td><td style="display:flex;gap:6px;"><button class="btn-edit" onclick="openEditStudentModal(${s.id})">Edit</button><button class="btn-delete" onclick="deleteStudent(${s.id})">Delete</button></td></tr>`).join('');
  document.getElementById('stuInfo').textContent=total===0?'No entries':`Showing ${(stuPage-1)*perPage+1} to ${Math.min(stuPage*perPage,total)} of ${total} entries`;
  const pDiv=document.getElementById('stuPages');pDiv.innerHTML='';
  ['«','‹',...Array.from({length:pages},(_,i)=>i+1),'›','»'].forEach(p=>{const btn=document.createElement('button');btn.textContent=p;if(p===stuPage)btn.classList.add('active');btn.onclick=()=>{if(p==='«')stuPage=1;else if(p==='‹')stuPage=Math.max(1,stuPage-1);else if(p==='›')stuPage=Math.min(pages,stuPage+1);else if(p==='»')stuPage=pages;else stuPage=p;renderStudentsTable();};pDiv.appendChild(btn);});
}

/* Modals */
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(m=>{m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');});});

function openSearchModal(room='',pc=''){
  document.getElementById('searchModalInput').value='';
  document.getElementById('searchResults').innerHTML='';
  selectedSitInLab=room&&pc?`${room} ${pc}`:'';
  document.getElementById('searchSelectedLab').textContent=selectedSitInLab?`Selected PC: ${selectedSitInLab}`:'Search student to start sit-in.';
  openModal('searchModal');
}

/* PC Grid */
function buildGrid(gridId,infoId,activeRoom,maintenanceOn){
  const grid=document.getElementById(gridId);
  const info=document.getElementById(infoId);
  if(info)info.textContent=maintenanceOn?`MAINTENANCE MODE — click a PC to toggle out-of-service status in Room ${activeRoom}.`:`Select a PC in Room ${activeRoom} to begin sit-in.`;
  if(!grid)return;
  const parent=grid.closest('.room-selector');
  if(parent)parent.classList.toggle('maintenance-on',maintenanceOn);
  grid.innerHTML='';
  for(let i=1;i<=50;i++){
    const label=`PC ${String(i).padStart(2,'0')}`;
    const key=`${activeRoom} ${label}`;
    const isOccupied=OCCUPIED_PCS.hasOwnProperty(key);
    const isBroken=BROKEN_PCS.includes(key);
    let cls='room-button';
    let titleAttr=label;
    if(isBroken){cls+=' pc-broken';titleAttr=`${label} — Out of Service`;}
    else if(isOccupied){cls+=' pc-occupied';titleAttr=`${label} — ${OCCUPIED_PCS[key]}`;}
    grid.innerHTML+=`<button type="button" class="${cls}" title="${titleAttr}" onclick="handlePcClick('${activeRoom}','${label}','${gridId}')">${label}</button>`;
  }
}

function handlePcClick(room,pc,gridId){
  const key=`${room} ${pc}`;
  const isOccupied=OCCUPIED_PCS.hasOwnProperty(key);
  const isBroken=BROKEN_PCS.includes(key);
  const isHome=gridId==='homeRoomPcGrid';
  const maintOn=isHome?homeMaintenanceOn:sitrMaintenanceOn;
  if(maintOn){if(isOccupied){toast(`Cannot mark an occupied PC as out of service.`,'error');return;}togglePcBroken(key,isHome);return;}
  if(isBroken){toast(`${pc} is out of service.`,'error');return;}
  if(isOccupied){toast(`${pc} is occupied by ${OCCUPIED_PCS[key]}.`,'');return;}
  selectPc(room,pc);
}

function togglePcBroken(key,isHome){
  const formData=new FormData();
  formData.append('toggle_pc_broken','1');
  formData.append('pc_key',key);
  fetch(window.location.pathname,{method:'POST',body:formData}).then(r=>r.json()).then(data=>{
    if(!data.success)return;
    const idx=BROKEN_PCS.indexOf(key);
    if(data.broken){if(idx===-1)BROKEN_PCS.push(key);toast(`${key} marked as out of service.`,'error');}
    else{if(idx>-1)BROKEN_PCS.splice(idx,1);toast(`${key} restored to available.`,'success');}
    renderHomeRoomGrid();renderRoomGrid();
  }).catch(()=>toast('Failed to update PC status.','error'));
}

function toggleMaintenanceMode(which){
  if(which==='home'){homeMaintenanceOn=!homeMaintenanceOn;const btn=document.getElementById('homeMaintenanceBtn');btn.classList.toggle('active-mode',homeMaintenanceOn);btn.textContent=homeMaintenanceOn?'✕ Exit Maintenance':'⚙ Maintenance Mode';renderHomeRoomGrid();}
  else{sitrMaintenanceOn=!sitrMaintenanceOn;const btn=document.getElementById('sitrMaintenanceBtn');btn.classList.toggle('active-mode',sitrMaintenanceOn);btn.textContent=sitrMaintenanceOn?'✕ Exit Maintenance':'⚙ Maintenance Mode';renderRoomGrid();}
}

function renderHomeRoomTabs(){const tabs=document.getElementById('homeRoomTabs');if(!tabs)return;tabs.innerHTML=SITIN_ROOMS.map((r,i)=>`<button type="button" class="home-room-tab${i===0?' active':''}" onclick="showHomeRoom('${r}')">${r}</button>`).join('');}
function renderHomeRoomGrid(){const activeRoom=document.querySelector('#homeRoomTabs .home-room-tab.active')?.textContent||SITIN_ROOMS[0];buildGrid('homeRoomPcGrid','homeRoomInfo',activeRoom,homeMaintenanceOn);}
function showHomeRoom(room){document.querySelectorAll('#homeRoomTabs .home-room-tab').forEach(t=>t.classList.toggle('active',t.textContent===room));renderHomeRoomGrid();}

function renderRoomTabs(){const tabs=document.getElementById('roomTabs');if(!tabs)return;tabs.innerHTML=SITIN_ROOMS.map((r,i)=>`<button type="button" class="room-tab${i===0?' active':''}" onclick="showRoom('${r}')">${r}</button>`).join('');}
function renderRoomGrid(){const activeRoom=document.querySelector('#roomTabs .room-tab.active')?.textContent||SITIN_ROOMS[0];buildGrid('roomPcGrid','roomInfo',activeRoom,sitrMaintenanceOn);}
function showRoom(room){document.querySelectorAll('#roomTabs .room-tab').forEach(t=>t.classList.toggle('active',t.textContent===room));renderRoomGrid();}

function selectPc(room,pc){openSearchModal(room,pc);}

/* Search */
function liveSearchStudents(){
  const q=((document.getElementById('searchModalInput').value)||'').toLowerCase();
  const res=document.getElementById('searchResults');
  const matches=PHP_STUDENTS.filter(s=>s.name.toLowerCase().includes(q)||String(s.id_number).toLowerCase().includes(q)).slice(0,8);
  res.innerHTML=matches.length===0?(q?'<p style="font-size:.78rem;color:var(--text-muted);text-align:center;padding:10px;">No students found.</p>':''):matches.map(s=>`<div class="search-result-item" onclick='openSitinFor(${JSON.stringify(s)})'><div><div class="sri-name">${s.name}</div><div class="sri-id">${s.id_number} · ${s.course} ${s.year}</div></div><button class="btn-edit" style="pointer-events:none;">Sit In</button></div>`).join('');
}
function openSitinFor(s){
  closeModal('searchModal');
  document.getElementById('sitin_id').value=s.id_number;
  document.getElementById('sitin_name').value=s.name;
  document.getElementById('sitin_session').value=s.sessions;
  document.getElementById('sitin_purpose').value='';
  document.getElementById('sitin_lab').value=selectedSitInLab;
  openModal('sitinFormModal');
}

/* Approve modal */
function openApproveModal(resData){
  approveReservationData=resData;approveSelectedPc='';
  document.getElementById('approve-student-name').textContent=resData.student_name||'—';
  document.getElementById('approve-id-number').textContent=resData.id_number||'—';
  document.getElementById('approve-req-lab').textContent=resData.lab||'—';
  document.getElementById('approve-purpose').textContent=resData.purpose||'—';
  document.getElementById('approve-res-id').value=resData.id;
  document.getElementById('approve-assigned-pc').value='';
  const selLabel=document.getElementById('approveSelectedPcLabel');
  selLabel.textContent='Selected: —';selLabel.classList.remove('visible');
  const submitBtn=document.getElementById('approveSubmitBtn');
  submitBtn.disabled=true;submitBtn.style.opacity='.5';submitBtn.style.cursor='not-allowed';
  const reqRoom=extractRoomNumber(resData.lab);
  const tabs=document.getElementById('approveRoomTabs');
  tabs.innerHTML=SITIN_ROOMS.map(r=>`<button type="button" class="approve-room-tab${r===reqRoom?' active':''}" onclick="showApproveRoom('${r}')">${r}</button>`).join('');
  const activeRoom=reqRoom&&SITIN_ROOMS.includes(reqRoom)?reqRoom:SITIN_ROOMS[0];
  document.querySelectorAll('#approveRoomTabs .approve-room-tab').forEach(t=>t.classList.toggle('active',t.textContent===activeRoom));
  loadApproveRoomPcs(activeRoom);
  openModal('approveModal');
}

function extractRoomNumber(labStr){
  if(!labStr)return SITIN_ROOMS[0];
  const match=labStr.match(/\b(544|542|530|528|526|524)\b/);
  return match?match[1]:SITIN_ROOMS[0];
}

function showApproveRoom(room){
  document.querySelectorAll('#approveRoomTabs .approve-room-tab').forEach(t=>t.classList.toggle('active',t.textContent===room));
  loadApproveRoomPcs(room);
}

function loadApproveRoomPcs(room){
  const container=document.getElementById('approvePcContainer');
  container.innerHTML='<div class="approve-loading">⏳ Loading available PCs…</div>';
  const formData=new FormData();
  formData.append('get_available_pcs','1');formData.append('room',room);
  fetch(window.location.pathname,{method:'POST',body:formData}).then(r=>r.json()).then(data=>{
    if(!data.success){container.innerHTML='<div class="approve-loading">Failed to load PCs.</div>';return;}
    const availableSet=new Set(data.available);
    let html='<div class="approve-pc-grid">';
    for(let i=1;i<=50;i++){
      const label=`PC ${String(i).padStart(2,'0')}`;
      const key=`${room} ${label}`;
      const isAvailable=availableSet.has(key);
      const isSelected=approveSelectedPc===key;
      if(isAvailable){html+=`<button type="button" class="approve-pc-btn${isSelected?' selected':''}" onclick="selectApprovePC('${key}',this)">${label}</button>`;}
      else{const isOccupied=OCCUPIED_PCS.hasOwnProperty(key);const isBroken=BROKEN_PCS.includes(key);let tooltip=label;if(isOccupied)tooltip+=' (Occupied)';else if(isBroken)tooltip+=' (Out of Service)';html+=`<button type="button" class="approve-pc-btn pc-unavailable" title="${tooltip}" disabled>${label}</button>`;}
    }
    html+='</div>';
    if(data.available.length===0)html+='<div style="text-align:center;padding:12px;font-size:.78rem;color:var(--text-muted);">No available PCs in Room '+room+'.</div>';
    container.innerHTML=html;
  }).catch(()=>{container.innerHTML='<div class="approve-loading">Failed to load PCs.</div>';});
}

function selectApprovePC(pcKey,btnEl){
  approveSelectedPc=pcKey;
  document.querySelectorAll('.approve-pc-btn').forEach(b=>b.classList.remove('selected'));
  btnEl.classList.add('selected');
  document.getElementById('approve-assigned-pc').value=pcKey;
  const selLabel=document.getElementById('approveSelectedPcLabel');
  selLabel.textContent=`✅ Selected: ${pcKey}`;selLabel.classList.add('visible');
  const submitBtn=document.getElementById('approveSubmitBtn');
  submitBtn.disabled=false;submitBtn.style.opacity='1';submitBtn.style.cursor='pointer';
}

document.getElementById('approveForm').addEventListener('submit',function(e){
  if(!approveSelectedPc){e.preventDefault();toast('Please select a PC to assign before approving.','error');return false;}
  return confirm(`Approve reservation and assign ${approveSelectedPc} to this student?`);
});

/* Student modal */
function openAddStudentModal(){document.getElementById('studentModalTitle').textContent='Add Student';document.getElementById('studentAction').value='add';document.getElementById('studentId').value='';['modal_id_number','modal_first_name','modal_last_name','modal_course','modal_year'].forEach(id=>{document.getElementById(id).value='';});document.getElementById('modal_sessions').value='30';openModal('studentModal');}
function openEditStudentModal(id){const s=PHP_STUDENTS.find(x=>x.id===id);if(!s)return;document.getElementById('studentModalTitle').textContent='Edit Student';document.getElementById('studentAction').value='edit';document.getElementById('studentId').value=s.id;document.getElementById('modal_id_number').value=s.id_number;const parts=s.name.split(' ');document.getElementById('modal_first_name').value=parts[0]||'';document.getElementById('modal_last_name').value=parts[parts.length-1]||'';document.getElementById('modal_course').value=s.course;document.getElementById('modal_year').value=s.year;document.getElementById('modal_sessions').value=s.sessions;openModal('studentModal');}
function deleteStudent(id){if(confirm('Delete this student?'))window.location.href='delete_student.php?id='+id;}
function confirmResetAllSessions(){if(confirm('Reset ALL student sessions to 30? This cannot be undone.'))window.location.href='reset_sessions.php';}

/* Toast */
function toast(msg,type=''){const c=document.getElementById('toasts');const t=document.createElement('div');t.className=`toast ${type}`;const icons={error:'❌',success:'✅','':'ℹ️'};t.innerHTML=`<span>${icons[type]||'ℹ️'}</span>${msg}`;c.appendChild(t);setTimeout(()=>{t.classList.add('fade');setTimeout(()=>t.remove(),350);},3200);}

/* Init */
renderStudentsTable();
renderHomeRoomTabs();
renderHomeRoomGrid();
renderRoomTabs();
renderRoomGrid();

const _openSec='<?= $openSection ?>';
if(_openSec!=='home')showSection(_openSec,null);

<?php if (!empty($admindashboardMsg)): ?>
(function(){const parts='<?= addslashes($admindashboardMsg) ?>'.split(':');const type=parts[0]==='success'?'success':'error';const msg=parts.slice(1).join(':');toast(msg,type);})();
<?php endif; ?>
</script>
<?php include 'footer.php'; ?>
</body>
</html>

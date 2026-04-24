<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user_data'];
if (!empty($user['role']) && $user['role'] === 'admin') {
    header("Location: admindashboard.php");
    exit;
}

/* ---------------------------------------------------------------
   AJAX endpoint for live notifications
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'notifications') {
    $seenId = intval($_GET['seen_id'] ?? 0);
    $conn = getConnection();

    $totalRow = $conn->query("SELECT COUNT(*) AS c FROM announcements")->fetch_assoc();
    $total = intval($totalRow['c'] ?? 0);

    $latestRow = $conn->query("SELECT id FROM announcements ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
    $latestId = intval($latestRow['id'] ?? 0);

    $unseen = 0;
    if ($latestId > $seenId) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM announcements WHERE id > ?");
        $stmt->bind_param("i", $seenId);
        $stmt->execute();
        $unseenRow = $stmt->get_result()->fetch_assoc();
        $unseen = intval($unseenRow['c'] ?? 0);
        $stmt->close();
    }

    $announcementsData = [];
    $annRes = $conn->query("SELECT id, admin_name, content, created_at FROM announcements ORDER BY created_at DESC LIMIT 3");
    if ($annRes) while ($row = $annRes->fetch_assoc()) $announcementsData[] = $row;

    $conn->close();
    header('Content-Type: application/json');
    echo json_encode(['latest_id' => $latestId, 'total' => $total, 'unseen' => $unseen, 'announcements' => $announcementsData]);
    exit;
}

/* ---------------------------------------------------------------
   Handle photo upload
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $msg  = '';
    $file = $_FILES['photo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif'];
        if (!in_array($file['type'], $allowed)) {
            $msg = 'Only JPG/PNG/GIF images allowed.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $msg = 'Photo must be smaller than 2MB.';
        } else {
            $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
            $base    = preg_replace('/[^a-z0-9_-]/i', '', $_SESSION['user_data']['id_number'] ?? uniqid());
            $newName = $base . '_' . time() . '.' . $ext;
            $dest    = __DIR__ . "/pictures/" . $newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $conn = getConnection();
                $stmt = $conn->prepare("UPDATE accounts SET profile_photo = ? WHERE id = ?");
                $stmt->bind_param("si", $newName, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $_SESSION['user_data']['profile_photo'] = $newName;
                    $msg = 'Photo updated successfully.';
                } else {
                    $msg = 'Failed to save photo.';
                }
                $stmt->close();
                $conn->close();
            } else {
                $msg = 'Unable to move uploaded file.';
            }
        }
    } else {
        $msg = 'Error uploading file.';
    }
    $_SESSION['photo_msg'] = $msg;
    header("Location: dashboard.php");
    exit;
}

/* ---------------------------------------------------------------
   Handle profile update POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name   = trim($_POST['first_name']      ?? '');
    $last_name    = trim($_POST['last_name']       ?? '');
    $middle_name  = trim($_POST['middle_name']     ?? '');
    $email        = trim($_POST['email']           ?? '');
    $address      = trim($_POST['address']         ?? '');
    $course       = trim($_POST['course']          ?? '');
    $year         = intval($_POST['course_level']  ?? 1);
    $new_password = $_POST['new_password']         ?? '';
    $confirm_pw   = $_POST['confirm_password']     ?? '';

    if ($first_name === '' || $last_name === '' || $email === '') {
        $_SESSION['profile_msg'] = 'error:First name, last name, and email are required.';
    } elseif ($new_password !== '' && $new_password !== $confirm_pw) {
        $_SESSION['profile_msg'] = 'error:New passwords do not match.';
    } elseif ($new_password !== '' && strlen($new_password) < 6) {
        $_SESSION['profile_msg'] = 'error:Password must be at least 6 characters.';
    } else {
        $conn = getConnection();
        if ($new_password !== '') {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE accounts SET first_name=?,last_name=?,middle_name=?,email=?,address=?,course=?,course_level=?,password=? WHERE id=?");
            $stmt->bind_param("ssssssisi", $first_name, $last_name, $middle_name, $email, $address, $course, $year, $hashed, $_SESSION['user_id']);
        } else {
            $stmt = $conn->prepare("UPDATE accounts SET first_name=?,last_name=?,middle_name=?,email=?,address=?,course=?,course_level=? WHERE id=?");
            $stmt->bind_param("sssssisi", $first_name, $last_name, $middle_name, $email, $address, $course, $year, $_SESSION['user_id']);
        }
        if ($stmt->execute()) {
            $_SESSION['user_data']['first_name']   = $first_name;
            $_SESSION['user_data']['last_name']    = $last_name;
            $_SESSION['user_data']['middle_name']  = $middle_name;
            $_SESSION['user_data']['email']        = $email;
            $_SESSION['user_data']['address']      = $address;
            $_SESSION['user_data']['course']       = $course;
            $_SESSION['user_data']['course_level'] = $year;
            $_SESSION['profile_msg'] = 'success:Profile updated successfully!';
        } else {
            $_SESSION['profile_msg'] = 'error:Failed to update profile. Please try again.';
        }
        $stmt->close();
        $conn->close();
    }
    header("Location: dashboard.php?section=editprofile");
    exit;
}

/* ---------------------------------------------------------------
   Handle reservation POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_reservation'])) {
    $res_date    = trim($_POST['res_date']    ?? '');
    $res_time    = trim($_POST['res_time']    ?? '');
    $res_lab     = trim($_POST['res_lab']     ?? '');
    $res_purpose = trim($_POST['res_purpose'] ?? '');

    $connCheck = getConnection();
    $chkStmt = $connCheck->prepare("SELECT id FROM sit_in_records WHERE id_number = ? AND status = 'active' LIMIT 1");
    $chkStmt->bind_param("s", $user['id_number']);
    $chkStmt->execute();
    $hasActive = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();

    $chkStmt2 = $connCheck->prepare("SELECT id FROM reservations WHERE id_number = ? AND status = 'pending' LIMIT 1");
    $chkStmt2->bind_param("s", $user['id_number']);
    $chkStmt2->execute();
    $hasPending = $chkStmt2->get_result()->fetch_assoc();
    $chkStmt2->close();
    $connCheck->close();

    if ($hasActive) {
        $_SESSION['reservation_msg'] = 'error:You already have an active sit-in session. Please wait for the admin to time you out before making a new reservation.';
    } elseif ($hasPending) {
        $_SESSION['reservation_msg'] = 'error:You already have a pending reservation. Please wait for the admin to approve or decline it before submitting another.';
    } elseif ($res_date === '' || $res_time === '' || $res_lab === '' || $res_purpose === '') {
        $_SESSION['reservation_msg'] = 'error:Please fill in all reservation fields.';
    } else {
        $conn = getConnection();
        $id_num   = $user['id_number'] ?? '';
        $stu_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $stmt = $conn->prepare("INSERT INTO reservations (id_number, student_name, lab, purpose, reservation_date, reservation_time, status, created_at) VALUES (?,?,?,?,?,?,'pending',NOW())");
        $stmt->bind_param("ssssss", $id_num, $stu_name, $res_lab, $res_purpose, $res_date, $res_time);
        if ($stmt->execute()) {
            $_SESSION['reservation_msg'] = 'success:Reservation submitted! Waiting for admin approval.';
        } else {
            $_SESSION['reservation_msg'] = 'error:Failed to submit reservation. Please try again.';
        }
        $stmt->close();
        $conn->close();
    }
    header("Location: dashboard.php?section=reservation");
    exit;
}

/* ---------------------------------------------------------------
   Handle active session end from student dashboard
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_session'])) {
    $activeId = intval($_POST['active_id'] ?? 0);
    $taskCompleted = isset($_POST['task_completed']) && $_POST['task_completed'] === '1' ? 2 : 0;
    if ($activeId > 0) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id_number FROM sit_in_records WHERE id = ? AND id_number = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("is", $activeId, $user['id_number']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $stmt = $conn->prepare("UPDATE sit_in_records SET status = 'done', timeout_at = NOW(), task_completed_points = ? WHERE id = ? AND status = 'active'");
            $stmt->bind_param("ii", $taskCompleted, $activeId);
            $stmt->execute();
            $stmt->close();

            $id_num = $row['id_number'];
            $stmt = $conn->prepare("UPDATE reservations SET status = 'completed' WHERE id_number = ? AND status = 'approved'");
            $stmt->bind_param("s", $id_num);
            $stmt->execute();
            $stmt->close();
            $_SESSION['session_end_msg'] = 'success:Session ended successfully.';
        } else {
            $_SESSION['session_end_msg'] = 'error:Active session not found or already ended.';
        }
        $conn->close();
    } else {
        $_SESSION['session_end_msg'] = 'error:Invalid session selected.';
    }
    header("Location: dashboard.php");
    exit;
}

/* ---------------------------------------------------------------
   Handle feedback POST
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $sit_in_id    = intval($_POST['sit_in_id'] ?? 0);
    $lab          = trim($_POST['feedback_lab'] ?? '');
    $rating       = intval($_POST['rating'] ?? 0);
    $message      = trim($_POST['feedback_message'] ?? '');
    $id_number    = $user['id_number'] ?? '';
    $student_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    if ($message === '') {
        $_SESSION['feedback_msg'] = 'error:Feedback message cannot be empty.';
    } elseif ($rating < 1 || $rating > 5) {
        $_SESSION['feedback_msg'] = 'error:Please select a star rating (1–5).';
    } else {
        $conn = getConnection();
        $chk = $conn->prepare("SELECT id FROM feedbacks WHERE sit_in_id = ? AND id_number = ? LIMIT 1");
        $chk->bind_param("is", $sit_in_id, $id_number);
        $chk->execute();
        $already = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($already) {
            $_SESSION['feedback_msg'] = 'error:You have already submitted feedback for this session.';
        } else {
            $stmt = $conn->prepare("INSERT INTO feedbacks (sit_in_id, id_number, student_name, lab, rating, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssis", $sit_in_id, $id_number, $student_name, $lab, $rating, $message);
            if ($stmt->execute()) {
                $_SESSION['feedback_msg'] = 'success:Feedback submitted! Thank you.';
            } else {
                $_SESSION['feedback_msg'] = 'error:Failed to submit feedback. Please try again.';
            }
            $stmt->close();
        }
        $conn->close();
    }
    header("Location: dashboard.php?section=history");
    exit;
}

/* ---------------------------------------------------------------
   Fetch all data
   --------------------------------------------------------------- */
$conn = getConnection();

$announcements = [];
$annRes = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 20");
if ($annRes) while ($row = $annRes->fetch_assoc()) $announcements[] = $row;

$sessRow = $conn->query("SELECT remaining_sessions FROM accounts WHERE id = " . intval($_SESSION['user_id']))->fetch_assoc();
$remainingSessions = $sessRow['remaining_sessions'] ?? 30;

$myReservations = [];
$resRes = $conn->query("SELECT * FROM reservations WHERE id_number = '" . $conn->real_escape_string($user['id_number'] ?? '') . "' ORDER BY created_at DESC LIMIT 20");
if ($resRes) while ($row = $resRes->fetch_assoc()) $myReservations[] = $row;

$myHistory = [];
$histRes = $conn->query("SELECT * FROM sit_in_records WHERE id_number = '" . $conn->real_escape_string($user['id_number'] ?? '') . "' ORDER BY id DESC LIMIT 50");
if ($histRes) while ($row = $histRes->fetch_assoc()) $myHistory[] = $row;

$activeSession = null;
$activeRes = $conn->query("SELECT * FROM sit_in_records WHERE id_number = '" . $conn->real_escape_string($user['id_number'] ?? '') . "' AND status = 'active' LIMIT 1");
if ($activeRes) $activeSession = $activeRes->fetch_assoc();

// Get sit_in IDs that already have feedback from this student
$myFeedbackIds = [];
$fbRes = $conn->query("SELECT sit_in_id FROM feedbacks WHERE id_number = '" . $conn->real_escape_string($user['id_number'] ?? '') . "'");
if ($fbRes) while ($row = $fbRes->fetch_assoc()) $myFeedbackIds[] = intval($row['sit_in_id']);

/* ---------------------------------------------------------------
   Leaderboard: Top 3 students by weighted score
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

$myRankRow = $conn->query("
    SELECT rank_pos FROM (
        SELECT id_number, RANK() OVER (ORDER BY SUM(reward_points) * 0.6 + SUM(TIMESTAMPDIFF(MINUTE, created_at, timeout_at)) / 60.0 * 0.2 + SUM(task_completed_points) * 0.2 DESC) AS rank_pos
        FROM sit_in_records
        WHERE status = 'done' AND timeout_at IS NOT NULL
        GROUP BY id_number
    ) ranked WHERE id_number = '" . $conn->real_escape_string($user['id_number'] ?? '') . "'
");
$myRank = $myRankRow ? ($myRankRow->fetch_assoc()['rank_pos'] ?? null) : null;

$conn->close();

$user        = $_SESSION['user_data'];
$id_number   = htmlspecialchars($user['id_number']    ?? '');
$first_name  = htmlspecialchars($user['first_name']   ?? '');
$last_name   = htmlspecialchars($user['last_name']    ?? '');
$middle_name = htmlspecialchars($user['middle_name']  ?? '');
$course      = htmlspecialchars($user['course']       ?? '');
$year        = htmlspecialchars($user['course_level'] ?? '');
$email       = htmlspecialchars($user['email']        ?? '');
$address     = htmlspecialchars($user['address']      ?? '');
$photoFile   = !empty($user['profile_photo']) ? $user['profile_photo'] : 'register.png';
$fullName    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Student';

function ordinal($n) {
    $n = intval($n);
    $suffix = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($suffix[($v-20)%10] ?? $suffix[min($v,3)] ?? 'th');
}
$yearDisplay = $year ? ordinal($year) . ' Year' : '';

$notifItems = array_slice($announcements, 0, 3);
$notifCount = count($announcements);
$latestAnnouncementId = $announcements[0]['id'] ?? 0;
$showWelcomeToast = $_SESSION['show_welcome_toast'] ?? false;
unset($_SESSION['show_welcome_toast']);

$profileMsg      = $_SESSION['profile_msg']      ?? '';  unset($_SESSION['profile_msg']);
$reservationMsg  = $_SESSION['reservation_msg']  ?? '';  unset($_SESSION['reservation_msg']);
$sessionEndMsg   = $_SESSION['session_end_msg']  ?? '';  unset($_SESSION['session_end_msg']);
$feedbackMsg     = $_SESSION['feedback_msg']     ?? '';  unset($_SESSION['feedback_msg']);

$openSection = 'home';
if (!empty($_GET['section']))  $openSection = htmlspecialchars($_GET['section']);
if ($profileMsg !== '')        $openSection = 'editprofile';
if ($reservationMsg !== '')    $openSection = 'reservation';
if ($feedbackMsg !== '')       $openSection = 'history';

$hasPendingReservation = false;
foreach ($myReservations as $r) {
    if (($r['status'] ?? '') === 'pending') { $hasPendingReservation = true; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — CCS Sit-In Monitoring</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="design.css">
  <style>
    body { background: var(--navy); }

    .dash-section { display: none; width: 100%; max-width: 1200px; flex-direction: column; gap: 20px; animation: riseIn 0.4s cubic-bezier(0.22,0.85,0.45,1) both; }
    .dash-section.active { display: flex; }

    .dash-main {
      flex: 1; position: relative; z-index: 1;
      padding: 32px 20px 60px;
      display: flex; flex-direction: column; align-items: center; gap: 24px;
    }

    .dash-greeting { width: 100%; max-width: 1200px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .greeting-left { display: flex; flex-direction: column; gap: 4px; }
    .greeting-eyebrow { font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--blue-soft); }
    .greeting-name { font-family:'Sora',sans-serif; font-size:1.55rem; font-weight:800; color:#fff; letter-spacing:-0.02em; line-height:1.2; }
    .greeting-sub  { font-size:.82rem; color:var(--text-muted); }
    .greeting-session-pill {
      display:inline-flex; align-items:center; gap:8px; padding:10px 20px;
      background:rgba(240,180,41,0.1); border:1px solid rgba(240,180,41,0.25); border-radius:40px;
      font-size:.8rem; font-weight:700; color:var(--gold); letter-spacing:.04em; text-transform:uppercase; flex-shrink:0;
    }
    .session-dot { width:8px; height:8px; border-radius:50%; background:var(--gold); box-shadow:0 0 0 3px rgba(240,180,41,0.25); animation:pulse 2s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{box-shadow:0 0 0 3px rgba(240,180,41,0.25);}50%{box-shadow:0 0 0 6px rgba(240,180,41,0.1);} }

    .page-title { font-family:'Sora',sans-serif; font-size:1.45rem; font-weight:800; color:#fff; letter-spacing:-0.02em; text-align:center; margin-bottom:4px; }
    .page-sub   { font-size:.8rem; color:var(--text-muted); text-align:center; margin-bottom:8px; }

    .dash-grid { display:grid; grid-template-columns:300px 1fr 340px; gap:20px; }

    .dash-card {
      background:rgba(12,26,54,0.72); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
      border:1px solid var(--navy-border); border-radius:20px; box-shadow:var(--shadow-card);
      overflow:hidden; display:flex; flex-direction:column;
    }
    .dash-card-header {
      padding:14px 20px;
      background:linear-gradient(90deg,rgba(30,111,255,0.18) 0%,transparent 100%);
      border-bottom:1px solid var(--navy-border);
      display:flex; align-items:center; gap:10px;
    }
    .dash-card-header-icon { width:30px; height:30px; border-radius:8px; background:rgba(30,111,255,0.2); border:1px solid rgba(30,111,255,0.3); display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
    .dash-card-header-label { font-family:'Sora',sans-serif; font-size:.78rem; font-weight:700; color:#fff; letter-spacing:.04em; text-transform:uppercase; }

    .student-body { padding:24px 20px; display:flex; flex-direction:column; align-items:center; gap:16px; flex:1; }
    .avatar-wrap  { position:relative; width:100px; height:100px; }
    .avatar-img   { width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid rgba(30,111,255,0.4); box-shadow:0 0 0 6px rgba(30,111,255,0.08),0 12px 32px rgba(0,0,0,0.35); }
    .avatar-badge { position:absolute; bottom:2px; right:2px; width:22px; height:22px; border-radius:50%; background:var(--blue); border:2px solid var(--navy); display:flex; align-items:center; justify-content:center; font-size:10px; cursor:pointer; transition:transform .18s,box-shadow .18s; }
    .avatar-badge:hover { transform:scale(1.15); box-shadow:0 4px 12px rgba(30,111,255,0.5); }
    .student-name { font-family:'Sora',sans-serif; font-size:1rem; font-weight:700; color:#fff; text-align:center; line-height:1.3; }
    .student-course-tag { display:inline-flex; align-items:center; gap:6px; padding:5px 14px; background:rgba(30,111,255,0.12); border:1px solid rgba(30,111,255,0.22); border-radius:20px; font-size:.72rem; font-weight:600; color:var(--blue-soft); letter-spacing:.04em; }
    .info-list { list-style:none; width:100%; }
    .info-list li { display:flex; align-items:flex-start; gap:12px; padding:11px 0; border-bottom:1px solid var(--navy-border); font-size:.82rem; color:var(--text); }
    .info-list li:last-child { border-bottom:none; }
    .info-icon { font-size:.95rem; width:20px; flex-shrink:0; margin-top:1px; }
    .info-val  { display:flex; flex-direction:column; gap:1px; }
    .info-label{ font-size:.65rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; }
    .info-text { color:#e8edf8; font-weight:500; }
    .photo-upload-row { width:100%; display:flex; align-items:center; gap:8px; padding:10px 14px; background:var(--input-bg); border:1px dashed var(--input-border); border-radius:10px; cursor:pointer; transition:border-color .2s,background .2s; }
    .photo-upload-row:hover { border-color:rgba(30,111,255,0.4); background:rgba(30,111,255,0.05); }
    .photo-upload-row input[type=file] { display:none; }
    .photo-upload-label { font-size:.76rem; color:var(--text-muted); flex:1; cursor:pointer; }
    .btn-small-blue { font-size:.72rem; padding:5px 12px; border:none; border-radius:7px; background:linear-gradient(135deg,#1e6fff,#0f4fd6); color:#fff; font-weight:700; cursor:pointer; transition:opacity .2s,transform .18s; flex-shrink:0; }
    .btn-small-blue:hover { opacity:.88; transform:translateY(-1px); }

    .mid-col { display:flex; flex-direction:column; gap:20px; }

    .announce-body { padding:20px; display:flex; flex-direction:column; gap:14px; flex:1; overflow-y:auto; max-height:400px; }
    .announce-body::-webkit-scrollbar { width:4px; }
    .announce-body::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.1); border-radius:4px; }
    .announce-item { background:var(--input-bg); border:1px solid var(--navy-border); border-radius:14px; overflow:hidden; transition:border-color .2s; }
    .announce-item:hover { border-color:rgba(30,111,255,0.3); }
    .announce-meta { display:flex; align-items:center; padding:12px 16px 0; }
    .announce-author { display:flex; align-items:center; gap:8px; }
    .announce-avatar { width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,#1e6fff,#0f4fd6); display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:700; color:#fff; flex-shrink:0; }
    .announce-name { font-size:.78rem; font-weight:600; color:#fff; }
    .announce-date { font-size:.68rem; color:var(--text-muted); }
    .announce-content { padding:8px 16px 14px; font-size:.82rem; color:var(--text-muted); line-height:1.6; }
    .announce-empty { border:1px dashed rgba(255,255,255,0.06); border-radius:14px; padding:28px 20px; text-align:center; font-size:.82rem; color:rgba(255,255,255,0.2); }

    /* LEADERBOARD */
    .lb-body { padding:20px 22px; display:flex; flex-direction:column; gap:0; }
    .lb-subtitle { font-size:.7rem; color:var(--text-muted); text-align:center; margin-bottom:16px; letter-spacing:.04em; text-transform:uppercase; }
    .lb-podium { display:flex; align-items:flex-end; justify-content:center; gap:10px; margin-bottom:20px; padding:0 4px; }
    .lb-podium-slot { display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; max-width:100px; }
    .lb-crown { font-size:1.25rem; line-height:1; }
    .lb-rank-1 .lb-crown { filter:drop-shadow(0 0 8px rgba(240,180,41,0.8)); }
    .lb-rank-2 .lb-crown { filter:drop-shadow(0 0 6px rgba(168,168,168,0.6)); }
    .lb-rank-3 .lb-crown { filter:drop-shadow(0 0 6px rgba(180,100,30,0.6)); }
    .lb-avatar { width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'Sora',sans-serif; font-size:.95rem; font-weight:800; color:#fff; flex-shrink:0; position:relative; }
    .lb-rank-1 .lb-avatar { width:58px; height:58px; font-size:1.1rem; background:linear-gradient(135deg,#f0b429,#d4940f); border:3px solid rgba(240,180,41,0.5); box-shadow:0 0 0 5px rgba(240,180,41,0.1),0 8px 20px rgba(240,180,41,0.3); }
    .lb-rank-2 .lb-avatar { background:linear-gradient(135deg,#9ca3af,#6b7280); border:2px solid rgba(156,163,175,0.4); box-shadow:0 0 0 4px rgba(156,163,175,0.08),0 5px 14px rgba(0,0,0,0.3); }
    .lb-rank-3 .lb-avatar { background:linear-gradient(135deg,#cd7c3a,#a0522d); border:2px solid rgba(205,124,58,0.35); box-shadow:0 0 0 4px rgba(205,124,58,0.08),0 5px 14px rgba(0,0,0,0.3); }
    .lb-badge-num { position:absolute; bottom:-4px; right:-4px; width:18px; height:18px; border-radius:50%; font-size:.6rem; font-weight:800; display:flex; align-items:center; justify-content:center; border:2px solid #0c1a36; }
    .lb-rank-1 .lb-badge-num { background:var(--gold); color:#0d1a36; }
    .lb-rank-2 .lb-badge-num { background:#9ca3af; color:#0d1a36; }
    .lb-rank-3 .lb-badge-num { background:#cd7c3a; color:#fff; }
    .lb-podium-base { width:100%; border-radius:8px 8px 5px 5px; display:flex; align-items:flex-end; justify-content:center; padding-bottom:5px; }
    .lb-rank-1 .lb-podium-base { height:52px; background:linear-gradient(180deg,rgba(240,180,41,0.2),rgba(240,180,41,0.05)); border:1px solid rgba(240,180,41,0.22); }
    .lb-rank-2 .lb-podium-base { height:38px; background:linear-gradient(180deg,rgba(156,163,175,0.13),rgba(156,163,175,0.03)); border:1px solid rgba(156,163,175,0.16); }
    .lb-rank-3 .lb-podium-base { height:28px; background:linear-gradient(180deg,rgba(205,124,58,0.12),rgba(205,124,58,0.02)); border:1px solid rgba(205,124,58,0.16); }
    .lb-name { font-family:'Sora',sans-serif; font-size:.67rem; font-weight:700; color:#fff; text-align:center; line-height:1.3; max-width:85px; word-break:break-word; }
    .lb-hours { font-family:'Sora',sans-serif; font-size:.73rem; font-weight:800; text-align:center; }
    .lb-rank-1 .lb-hours { color:var(--gold); }
    .lb-rank-2 .lb-hours { color:#9ca3af; }
    .lb-rank-3 .lb-hours { color:#cd7c3a; }
    .lb-hours-label { font-size:.57rem; color:var(--text-muted); text-align:center; margin-top:-2px; }
    .lb-list { display:flex; flex-direction:column; gap:5px; }
    .lb-list-item { display:flex; align-items:center; gap:10px; padding:9px 12px; background:var(--input-bg); border:1px solid var(--navy-border); border-radius:10px; transition:border-color .2s,background .2s; }
    .lb-list-item:hover { border-color:rgba(30,111,255,0.22); background:rgba(30,111,255,0.04); }
    .lb-list-rank { font-family:'Sora',sans-serif; font-size:.75rem; font-weight:800; color:var(--text-muted); width:18px; text-align:center; flex-shrink:0; }
    .lb-list-avatar { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-family:'Sora',sans-serif; font-size:.75rem; font-weight:700; color:#fff; flex-shrink:0; }
    .lb-list-info { flex:1; min-width:0; }
    .lb-list-name { font-size:.78rem; font-weight:600; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .lb-list-id   { font-size:.65rem; color:var(--text-muted); }
    .lb-list-stat { text-align:right; flex-shrink:0; }
    .lb-list-hours { font-family:'Sora',sans-serif; font-size:.8rem; font-weight:800; color:var(--blue-soft); }
    .lb-list-sessions { font-size:.62rem; color:var(--text-muted); }
    .lb-my-rank-banner { margin-top:12px; padding:10px 14px; border-radius:10px; background:rgba(30,111,255,0.08); border:1px solid rgba(30,111,255,0.2); display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .lb-my-rank-label { font-size:.73rem; color:var(--blue-soft); font-weight:600; }
    .lb-my-rank-num { font-family:'Sora',sans-serif; font-size:.9rem; font-weight:800; color:#fff; }
    .lb-empty { text-align:center; padding:24px 16px; font-size:.78rem; color:rgba(255,255,255,0.2); display:flex; flex-direction:column; align-items:center; gap:6px; }
    .lb-empty-icon { font-size:1.8rem; opacity:.4; }
    .lb-divider { border:none; border-top:1px solid var(--navy-border); margin:14px 0 12px; }

    .rules-body { padding:20px; flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:16px; max-height:520px; }
    .rules-body::-webkit-scrollbar { width:4px; }
    .rules-body::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.1); border-radius:4px; }
    .rules-header-block { text-align:center; padding-bottom:14px; border-bottom:1px solid var(--navy-border); }
    .rules-uni    { font-family:'Sora',sans-serif; font-size:.88rem; font-weight:700; color:#fff; margin-bottom:3px; }
    .rules-college{ font-size:.7rem; color:var(--gold); text-transform:uppercase; letter-spacing:.08em; font-weight:600; }
    .rules-section-label { font-size:.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--blue-soft); }
    .rule-item { display:flex; gap:12px; padding:12px 14px; background:var(--input-bg); border:1px solid var(--navy-border); border-radius:12px; font-size:.8rem; color:var(--text-muted); line-height:1.6; transition:border-color .2s,background .2s; }
    .rule-item:hover { border-color:rgba(30,111,255,0.25); background:rgba(30,111,255,0.04); color:var(--text); }
    .rule-num { width:24px; height:24px; border-radius:8px; background:rgba(30,111,255,0.15); border:1px solid rgba(30,111,255,0.25); color:var(--blue-soft); font-size:.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }

    /* Edit Profile */
    .edit-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-field { display:flex; flex-direction:column; gap:6px; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-size:.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; }
    .form-input { width:100%; background:var(--input-bg); border:1.5px solid var(--input-border); border-radius:10px; padding:11px 14px; font-family:'DM Sans',sans-serif; font-size:.88rem; color:var(--text); outline:none; transition:border-color .2s,box-shadow .2s; box-sizing:border-box; }
    .form-input:focus { border-color:var(--blue); box-shadow:0 0 0 4px var(--input-focus); }
    .form-input[readonly] { opacity:.6; cursor:default; }
    .form-select { width:100%; background:var(--input-bg); border:1.5px solid var(--input-border); border-radius:10px; padding:11px 14px; font-family:'DM Sans',sans-serif; font-size:.88rem; color:var(--text); outline:none; cursor:pointer; transition:border-color .2s; box-sizing:border-box; }
    .form-select:focus { border-color:var(--blue); box-shadow:0 0 0 4px var(--input-focus); }
    .form-divider { grid-column:1/-1; border:none; border-top:1px solid var(--navy-border); margin:8px 0; }
    .form-section-label { grid-column:1/-1; font-family:'Sora',sans-serif; font-size:.75rem; font-weight:700; color:var(--blue-soft); text-transform:uppercase; letter-spacing:.08em; }
    .btn-save { padding:11px 28px; background:linear-gradient(135deg,#1e6fff,#0f4fd6); color:#fff; font-family:'Sora',sans-serif; font-size:.85rem; font-weight:700; border:none; border-radius:10px; cursor:pointer; box-shadow:0 4px 14px rgba(30,111,255,0.4); transition:box-shadow .2s,transform .18s; }
    .btn-save:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(30,111,255,0.55); }
    .edit-avatar-col { display:flex; flex-direction:column; align-items:center; gap:14px; padding:28px 20px; border-right:1px solid var(--navy-border); }
    .edit-avatar-img { width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid rgba(30,111,255,0.4); box-shadow:0 0 0 6px rgba(30,111,255,0.08),0 12px 32px rgba(0,0,0,0.35); }
    .edit-avatar-name { font-family:'Sora',sans-serif; font-size:.95rem; font-weight:700; color:#fff; text-align:center; }
    .edit-avatar-id   { font-size:.73rem; color:var(--text-muted); }
    .edit-layout { display:grid; grid-template-columns:200px 1fr; }
    .edit-form-col { padding:28px; }

    /* Reservation */
    .res-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .badge-pending  { background:rgba(240,180,41,0.12); border:1px solid rgba(240,180,41,0.25); color:var(--gold); }
    .badge-approved { background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.25); color:var(--success-c); }
    .badge-declined { background:rgba(248,113,113,0.12); border:1px solid rgba(248,113,113,0.25); color:var(--error); }
    .badge-completed { background:rgba(107,127,163,0.12); border:1px solid rgba(107,127,163,0.2); color:var(--text-muted); }

    .tbl-wrap { overflow-x:auto; }
    table.a-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .a-table thead th { padding:10px 14px; text-align:left; font-family:'Sora',sans-serif; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); border-bottom:1px solid var(--navy-border); white-space:nowrap; }
    .a-table tbody tr { border-bottom:1px solid var(--navy-border); transition:background .15s; }
    .a-table tbody tr:last-child { border-bottom:none; }
    .a-table tbody tr:hover { background:rgba(30,111,255,0.06); }
    .a-table td { padding:10px 14px; color:var(--text); vertical-align:middle; }
    .a-table td.muted { color:var(--text-muted); }
    .tbl-empty { text-align:center; padding:28px; color:rgba(255,255,255,0.2); font-size:.82rem; }

    /* Header */
    .dash-topnav { position:sticky; top:0; z-index:1000; height:52px; background:linear-gradient(90deg,#0e2b6e 0%,#153d90 50%,#1a47a8 100%); border-bottom:1px solid rgba(255,255,255,0.09); box-shadow:0 2px 20px rgba(0,0,0,0.4); display:flex; align-items:center; padding:0 28px; justify-content:space-between; gap:16px; }
    .dash-topnav-brand { font-family:'Sora',sans-serif; font-size:.95rem; font-weight:700; color:#fff; letter-spacing:.01em; white-space:nowrap; }
    .dash-topnav-links { display:flex; align-items:center; gap:2px; }
    .dash-topnav-links a, .dash-topnav-links button { background:none; border:none; color:rgba(255,255,255,0.88); font-family:'DM Sans',sans-serif; font-size:.82rem; font-weight:500; padding:6px 14px; border-radius:6px; text-decoration:none; cursor:pointer; white-space:nowrap; transition:background .18s,color .18s; display:inline-flex; align-items:center; gap:5px; line-height:1; }
    .dash-topnav-links a:hover, .dash-topnav-links button:hover { background:rgba(255,255,255,0.1); color:#fff; }
    .dash-topnav-links a.tnav-active { background:rgba(255,255,255,0.13); color:#fff; }
    .tnav-dropdown { position:relative; }
    .tnav-dropdown button { position:relative; z-index:310; }
    .tnav-dropdown-panel { position:absolute; top:100%; right:0; min-width:300px; background:#0d1e40; border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:6px; box-shadow:0 16px 48px rgba(0,0,0,0.55),0 0 0 1px rgba(30,111,255,0.1); opacity:0; pointer-events:none; transform:translateY(0); transition:opacity .2s,transform .2s; z-index:300; }
    .tnav-dropdown:hover .tnav-dropdown-panel, .tnav-dropdown:focus-within .tnav-dropdown-panel { opacity:1; pointer-events:auto; transform:translateY(0); }
    .tnav-drop-header { padding:10px 14px 8px; font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,0.35); border-bottom:1px solid rgba(255,255,255,0.06); margin-bottom:4px; display:flex; align-items:center; justify-content:space-between; }
    .tnav-drop-count  { background:var(--blue); color:#fff; font-size:.6rem; font-weight:800; padding:2px 7px; border-radius:20px; }
    .tnav-drop-item   { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border-radius:9px; transition:background .15s; }
    .tnav-drop-item:hover { background:rgba(30,111,255,0.1); }
    .tnav-drop-avatar { width:28px; height:28px; border-radius:7px; background:linear-gradient(135deg,#1e6fff,#0f4fd6); display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; color:#fff; flex-shrink:0; margin-top:1px; }
    .tnav-drop-body   { display:flex; flex-direction:column; gap:2px; flex:1; min-width:0; }
    .tnav-drop-author { font-size:.75rem; font-weight:600; color:#fff; }
    .tnav-drop-text   { font-size:.76rem; color:rgba(255,255,255,0.6); line-height:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tnav-drop-date   { font-size:.65rem; color:rgba(255,255,255,0.28); margin-top:1px; }
    .tnav-drop-empty  { padding:18px 12px; font-size:.78rem; color:rgba(255,255,255,0.25); text-align:center; }
    .tnav-drop-footer { border-top:1px solid rgba(255,255,255,0.06); margin-top:4px; padding:8px 12px 4px; text-align:center; font-size:.72rem; color:var(--blue-soft); cursor:pointer; transition:color .18s; }
    .tnav-drop-footer:hover { color:#fff; }
    .tnav-chevron { font-size:.55rem; opacity:.65; transition:transform .2s; }
    .tnav-dropdown:hover .tnav-chevron { transform:rotate(180deg); }
    .notif-pip { display:inline-flex; align-items:center; justify-content:center; min-width:16px; height:16px; padding:0 4px; border-radius:20px; background:var(--gold); color:#0d1a36; font-size:.6rem; font-weight:800; margin-left:2px; position:relative; z-index:311; }
    .btn-tnav-logout { background:linear-gradient(135deg,#f0b429,#d4940f) !important; color:#0d1a36 !important; font-weight:700 !important; border-radius:6px !important; padding:6px 18px !important; box-shadow:0 2px 10px rgba(240,180,41,0.38); }
    .btn-tnav-logout:hover { background:linear-gradient(135deg,#fbc842,#e0a010) !important; box-shadow:0 4px 16px rgba(240,180,41,0.55) !important; transform:translateY(-1px); color:#0d1a36 !important; }

    .btn-session-end { padding:10px 22px; background:linear-gradient(135deg,#f0b429,#d4940f); color:#0d1a36; font-family:'Sora',sans-serif; font-size:.82rem; font-weight:700; border:none; border-radius:9px; cursor:pointer; box-shadow:0 4px 14px rgba(240,180,41,0.4); transition:box-shadow .2s,transform .18s; white-space:nowrap; flex-shrink:0; }
    .btn-session-end:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(240,180,41,0.55); }

    /* Feedback modal styles */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.65); backdrop-filter:blur(6px); z-index:2000; display:none; align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:#0d1e40; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:28px 32px; width:100%; max-width:480px; position:relative; box-shadow:0 24px 72px rgba(0,0,0,0.6); animation:riseIn 0.3s cubic-bezier(0.22,0.85,0.45,1) both; }
    .modal-title { font-family:'Sora',sans-serif; font-size:1.05rem; font-weight:700; color:#fff; margin-bottom:20px; }
    .modal-close { position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:var(--text-muted); font-size:.85rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s,color .15s; }
    .modal-close:hover { background:rgba(255,255,255,0.12); color:#fff; }
    .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; }
    .btn-modal-cancel { padding:9px 20px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:9px; color:var(--text-muted); font-size:.82rem; font-weight:600; cursor:pointer; transition:background .18s,color .18s; }
    .btn-modal-cancel:hover { background:rgba(255,255,255,0.1); color:#fff; }
    .btn-modal-confirm { padding:9px 22px; background:linear-gradient(135deg,#1e6fff,#0f4fd6); color:#fff; font-family:'Sora',sans-serif; font-size:.82rem; font-weight:700; border:none; border-radius:9px; cursor:pointer; box-shadow:0 4px 14px rgba(30,111,255,0.4); transition:box-shadow .2s,transform .18s; }
    .btn-modal-confirm:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(30,111,255,0.55); }

    /* Star rating */
    .star-rating-wrap { display:flex; gap:8px; font-size:1.8rem; cursor:pointer; margin-top:4px; }
    .star-rating-wrap span { transition:transform .12s; line-height:1; }
    .star-rating-wrap span:hover { transform:scale(1.2); }

    /* Feedback btn submitted */
    .btn-feedback-done { font-size:.72rem; padding:5px 12px; border:1px solid rgba(52,211,153,0.3); border-radius:7px; background:rgba(52,211,153,0.08); color:var(--success-c); font-weight:700; cursor:default; }

    @media (max-width:1024px) { .dash-grid { grid-template-columns:260px 1fr; } .dash-grid > *:nth-child(3) { grid-column:1/-1; } .res-grid { grid-template-columns:1fr; } }
    @media (max-width:680px)  { .dash-grid { grid-template-columns:1fr; } .greeting-session-pill { display:none; } .greeting-name { font-size:1.2rem; } .edit-layout { grid-template-columns:1fr; } .edit-avatar-col { border-right:none; border-bottom:1px solid var(--navy-border); } }
  </style>
</head>
<body>

<div class="scene-bg">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<header class="dash-topnav">
  <div class="dash-topnav-brand">Dashboard</div>
  <nav class="dash-topnav-links">

    <div class="tnav-dropdown">
      <button type="button">
        🔔 Notification
        <?php if ($notifCount > 0): ?>
          <span class="notif-pip"><?= $notifCount ?></span>
        <?php endif; ?>
        <span class="tnav-chevron">▼</span>
      </button>
      <div class="tnav-dropdown-panel">
        <div class="tnav-drop-header">
          Notifications
          <?php if ($notifCount > 0): ?><span class="tnav-drop-count"><?= $notifCount ?></span><?php endif; ?>
        </div>
        <?php if (empty($notifItems)): ?>
          <div class="tnav-drop-empty">No notifications yet.</div>
        <?php else: foreach ($notifItems as $n): ?>
          <div class="tnav-drop-item">
            <div class="tnav-drop-avatar">CA</div>
            <div class="tnav-drop-body">
              <span class="tnav-drop-author"><?= htmlspecialchars($n['admin_name']) ?></span>
              <span class="tnav-drop-text"><?= htmlspecialchars($n['content']) ?></span>
              <span class="tnav-drop-date"><?= date('M d, Y · g:i A', strtotime($n['created_at'])) ?></span>
            </div>
          </div>
        <?php endforeach; endif; ?>
        <?php if ($notifCount > 3): ?>
          <div class="tnav-drop-footer" onclick="showSection('home',null);document.getElementById('announceCard').scrollIntoView({behavior:'smooth'})">
            View all <?= $notifCount ?> announcements ↓
          </div>
        <?php endif; ?>
      </div>
    </div>

    <a href="#" onclick="showSection('home',this);return false;" id="nav-home" class="tnav-active">Home</a>
    <a href="#" onclick="showSection('editprofile',this);return false;" id="nav-editprofile">Edit Profile</a>
    <a href="#" onclick="showSection('history',this);return false;" id="nav-history">History</a>
    <a href="#" onclick="showSection('reservation',this);return false;" id="nav-reservation">Reservation</a>
    <a href="logout.php" class="btn-tnav-logout">Log out</a>
  </nav>
</header>

<main class="dash-main">

  <!-- ============================================================
       HOME SECTION
       ============================================================ -->
  <div class="dash-section active" id="sec-home">

    <?php if ($activeSession): ?>
    <div style="width:100%;max-width:1200px;background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.3);border-radius:16px;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:10px;height:10px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,0.2);animation:pulse 2s infinite;flex-shrink:0;"></div>
        <div>
          <div style="font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:#34d399;margin-bottom:2px;">Active Sit-in Session</div>
          <div style="font-size:.78rem;color:var(--text-muted);">
            Lab <strong style="color:#e8edf8;"><?= htmlspecialchars($activeSession['lab']) ?></strong>
            &nbsp;·&nbsp; Purpose: <strong style="color:#e8edf8;"><?= htmlspecialchars($activeSession['purpose']) ?></strong>
            &nbsp;·&nbsp; Started: <strong style="color:#e8edf8;"><?= date('M d, Y g:i A', strtotime($activeSession['created_at'])) ?></strong>
          </div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;justify-content:flex-end;">
        <div style="font-size:.75rem;color:rgba(52,211,153,0.7);font-style:italic;max-width:320px;">Click Session End once you have completed your sit-in purpose. If yes, you will receive +2 task points.</div>
        <form method="post" action="dashboard.php" style="margin:0;">
          <input type="hidden" name="end_session" value="1">
          <input type="hidden" name="active_id" value="<?= intval($activeSession['id']) ?>">
          <input type="hidden" name="task_completed" id="taskCompletedInput" value="0">
          <button type="submit" class="btn-session-end" onclick="if(confirm('Did you complete the purpose of your sit-in? Click OK for yes, Cancel for no.')){document.getElementById('taskCompletedInput').value='1';}else{document.getElementById('taskCompletedInput').value='0';} return true;">⏹ Session End</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="dash-greeting">
      <div class="greeting-left">
        <span class="greeting-eyebrow">📅 <?= date('l, F j Y') ?></span>
        <h1 class="greeting-name">Welcome back, <?= $first_name ?>! 👋</h1>
        <p class="greeting-sub">Here's your student portal overview</p>
      </div>
      <div class="greeting-session-pill">
        <span class="session-dot"></span>
        <?= $remainingSessions ?> Sessions Remaining
      </div>
    </div>

    <div class="dash-grid">

      <!-- Student Info -->
      <div class="dash-card">
        <div class="dash-card-header">
          <div class="dash-card-header-icon">👤</div>
          <span class="dash-card-header-label">Student Information</span>
        </div>
        <div class="student-body">
          <div class="avatar-wrap">
            <img src="pictures/<?= htmlspecialchars($photoFile) ?>" alt="Avatar" class="avatar-img" id="avatarImg">
            <label for="photoInput" class="avatar-badge" title="Change photo">✏️</label>
          </div>
          <div class="student-name"><?= $fullName ?></div>
          <div class="student-course-tag">🎓 <?= $course ?><?= $yearDisplay ? ' · '.$yearDisplay : '' ?></div>

          <form action="dashboard.php" method="post" enctype="multipart/form-data" style="width:100%">
            <label class="photo-upload-row" for="photoInput">
              <input type="file" name="photo" id="photoInput" accept="image/*">
              <span class="photo-upload-label" id="photoLabel">📎 Click pencil to change photo…</span>
              <button type="submit" class="btn-small-blue" id="photoSubmitBtn" style="display:none">Upload</button>
            </label>
          </form>

          <?php if (!empty($_SESSION['photo_msg'])): ?>
            <div style="font-size:.78rem;color:var(--success-c);text-align:center;"><?= htmlspecialchars($_SESSION['photo_msg']) ?></div>
            <?php unset($_SESSION['photo_msg']); ?>
          <?php endif; ?>

          <ul class="info-list">
            <li><span class="info-icon">🪪</span><div class="info-val"><span class="info-label">ID Number</span><span class="info-text"><?= $id_number ?></span></div></li>
            <li><span class="info-icon">✉️</span><div class="info-val"><span class="info-label">Email</span><span class="info-text"><?= $email ?></span></div></li>
            <li><span class="info-icon">📍</span><div class="info-val"><span class="info-label">Address</span><span class="info-text"><?= $address ?></span></div></li>
            <li><span class="info-icon">🎓</span><div class="info-val"><span class="info-label">Course</span><span class="info-text"><?= $course ?></span></div></li>
            <li><span class="info-icon">📚</span><div class="info-val"><span class="info-label">Year Level</span><span class="info-text"><?= $yearDisplay ?></span></div></li>
            <li><span class="info-icon">⏱️</span><div class="info-val"><span class="info-label">Sessions Left</span><span class="info-text" style="color:var(--gold);font-weight:700"><?= $remainingSessions ?> sessions</span></div></li>
          </ul>
        </div>
      </div>

      <!-- Middle column: Announcements + Leaderboard -->
      <div class="mid-col">

        <!-- Announcements -->
        <div class="dash-card" id="announceCard">
          <div class="dash-card-header">
            <div class="dash-card-header-icon">📢</div>
            <span class="dash-card-header-label">Announcements</span>
          </div>
          <div class="announce-body">
            <?php if (empty($announcements)): ?>
              <div class="announce-empty">📭 No announcements yet — check back later.</div>
            <?php else: foreach ($announcements as $ann): ?>
              <div class="announce-item">
                <div class="announce-meta">
                  <div class="announce-author">
                    <div class="announce-avatar">CA</div>
                    <div>
                      <div class="announce-name"><?= htmlspecialchars($ann['admin_name']) ?></div>
                      <div class="announce-date"><?= date('M d, Y', strtotime($ann['created_at'])) ?></div>
                    </div>
                  </div>
                </div>
                <?php if (!empty($ann['content'])): ?>
                  <div class="announce-content"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- Leaderboard -->
        <div class="dash-card">
          <div class="dash-card-header">
            <div class="dash-card-header-icon">🏆</div>
            <span class="dash-card-header-label">Top Sit-in Leaderboard</span>
            <span style="margin-left:auto;font-size:.7rem;color:var(--text-muted);font-style:italic;">Top 3 sit-in students</span>
          </div>
          <?php if (empty($leaderboard)): ?>
            <div class="lb-body">
              <div class="lb-empty">
                <span class="lb-empty-icon">🏆</span>
                <span>No completed sit-in sessions yet.<br>Rankings will appear once students have timed out.</span>
              </div>
            </div>
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
                <?php foreach ($podiumOrder as $idx): ?>
                  <?php $entry = $leaderboard[$idx]; $rankNum = $idx + 1; ?>
                  <div class="lb-podium-slot <?= $rankClass[$idx] ?>">
                    <div class="lb-crown"><?= $medals[$idx] ?></div>
                    <div class="lb-avatar">
                      <?= strtoupper(substr($entry['student_name'], 0, 1)) ?>
                      <span class="lb-badge-num"><?= $rankNum ?></span>
                    </div>
                    <div class="lb-name"><?= htmlspecialchars($entry['student_name']) ?></div>
                    <div class="lb-hours" style="font-size:0.9em;color:#2196f3;font-weight:bold;">
                      <?= number_format((float)$entry['total_score'], 2) ?> pts
                    </div>
                    <div class="lb-podium-base"></div>
                  </div>
                <?php endforeach; ?>
              </div>
              <hr class="lb-divider">
              <div class="lb-list">
                <?php
                $listColors = ['rgba(240,180,41,0.15)','rgba(156,163,175,0.12)','rgba(205,124,58,0.12)'];
                foreach ($leaderboard as $idx => $entry):
                ?>
                  <div class="lb-list-item">
                    <div class="lb-list-rank"><?= $medals[$idx] ?></div>
                    <div class="lb-list-avatar" style="background:<?= $listColors[$idx] ?? 'rgba(30,111,255,0.15)' ?>;border:1px solid rgba(255,255,255,0.08);">
                      <?= strtoupper(substr($entry['student_name'], 0, 1)) ?>
                    </div>
                    <div class="lb-list-info">
                      <div class="lb-list-name"><?= htmlspecialchars($entry['student_name']) ?></div>
                      <div class="lb-list-id"><?= htmlspecialchars($entry['id_number']) ?></div>
                    </div>
                    <div class="lb-list-stat">
                      <div class="lb-list-hours" style="font-size:1em;color:#2196f3;font-weight:bold;">
                        <?= number_format((float)$entry['total_score'], 2) ?> pts
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Rules -->
      <div class="dash-card">
        <div class="dash-card-header">
          <div class="dash-card-header-icon">📜</div>
          <span class="dash-card-header-label">Rules &amp; Regulations</span>
        </div>
        <div class="rules-body">
          <div class="rules-header-block">
            <div class="rules-uni">University of Cebu</div>
            <div class="rules-college">College of Information &amp; Computer Studies</div>
          </div>
          <div class="rules-section-label">Laboratory Rules</div>
          <div class="rule-item"><div class="rule-num">1</div><span>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal equipment must be switched off.</span></div>
          <div class="rule-item"><div class="rule-num">2</div><span>Games are not allowed inside the lab. This includes computer-related games, card games, and any activity that may disturb lab operations.</span></div>
          <div class="rule-item"><div class="rule-num">3</div><span>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing software are strictly prohibited.</span></div>
          <div class="rule-item"><div class="rule-num">4</div><span>Eating and drinking inside the laboratory are strictly prohibited to maintain cleanliness and protect equipment.</span></div>
          <div class="rule-item"><div class="rule-num">5</div><span>Students must log in and out properly using the sit-in monitoring system at the start and end of every session.</span></div>
        </div>
      </div>

    </div>
  </div>

  <!-- ============================================================
       EDIT PROFILE SECTION
       ============================================================ -->
  <div class="dash-section" id="sec-editprofile">
    <div class="page-title">Edit Profile</div>
    <div class="page-sub">Update your personal information and change your password</div>

    <div class="dash-card" style="max-width:860px;align-self:center;width:100%;">
      <div class="edit-layout">
        <div class="edit-avatar-col">
          <img src="pictures/<?= htmlspecialchars($photoFile) ?>" alt="Avatar" class="edit-avatar-img" id="editAvatarImg">
          <div class="edit-avatar-name"><?= $fullName ?></div>
          <div class="edit-avatar-id"><?= $id_number ?></div>
          <form action="dashboard.php" method="post" enctype="multipart/form-data" style="width:100%;text-align:center;">
            <label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;">
              <input type="file" name="photo" id="editPhotoInput" accept="image/*" style="display:none">
              <span style="font-size:.75rem;color:var(--text-muted);">📎 Change photo</span>
              <button type="submit" class="btn-small-blue" id="editPhotoBtn" style="display:none">Upload</button>
            </label>
          </form>
        </div>
        <div class="edit-form-col">
          <form method="post" action="dashboard.php">
            <input type="hidden" name="update_profile" value="1">
            <div class="edit-grid">
              <div class="form-section-label">Personal Information</div>
              <div class="form-field"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-input" value="<?= $first_name ?>" required></div>
              <div class="form-field"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-input" value="<?= $last_name ?>" required></div>
              <div class="form-field"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-input" value="<?= $middle_name ?>"></div>
              <div class="form-field"><label class="form-label">ID Number</label><input type="text" class="form-input" value="<?= $id_number ?>" readonly></div>
              <div class="form-field full"><label class="form-label">Email Address *</label><input type="email" name="email" class="form-input" value="<?= $email ?>" required></div>
              <div class="form-field full"><label class="form-label">Address</label><input type="text" name="address" class="form-input" value="<?= $address ?>"></div>
              <div class="form-field"><label class="form-label">Course</label><input type="text" name="course" class="form-input" value="<?= $course ?>"></div>
              <div class="form-field"><label class="form-label">Year Level</label><select name="course_level" class="form-select"><?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>><?= $i ?><?= ['st','nd','rd','th','th'][$i-1] ?> Year</option><?php endfor; ?></select></div>
              <hr class="form-divider">
              <div class="form-section-label">Change Password <span style="font-weight:400;color:var(--text-muted);text-transform:none;letter-spacing:0;">(leave blank to keep current)</span></div>
              <div class="form-field"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-input" placeholder="Min. 6 characters"></div>
              <div class="form-field"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-input" placeholder="Repeat new password"></div>
              <div class="form-field full" style="margin-top:8px;"><button type="submit" class="btn-save">💾 Save Changes</button></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================
       HISTORY SECTION
       ============================================================ -->
  <div class="dash-section" id="sec-history">
    <div class="page-title">Sit-in History</div>
    <div class="page-sub">Your complete sit-in session records — leave feedback for completed sessions</div>
    <div class="dash-card">
      <div class="dash-card-header"><div class="dash-card-header-icon">📋</div><span class="dash-card-header-label">Session History</span></div>
      <div style="padding:20px;">
        <div class="tbl-wrap">
          <table class="a-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Purpose</th>
                <th>Lab / PC</th>
                <th>Session Used</th>
                <th>Status</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Feedback</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($myHistory)): ?>
                <tr><td colspan="8" class="tbl-empty">No sit-in history yet.</td></tr>
              <?php else: foreach ($myHistory as $h): ?>
                <?php
                  $st = $h['status'] ?? 'done';
                  $alreadyFeedback = in_array(intval($h['id']), $myFeedbackIds);
                ?>
                <tr>
                  <td class="muted"><?= htmlspecialchars($h['id']) ?></td>
                  <td><?= htmlspecialchars($h['purpose'] ?? '') ?></td>
                  <td><?= htmlspecialchars($h['lab'] ?? '') ?></td>
                  <td><?= htmlspecialchars($h['session'] ?? '') ?></td>
                  <td>
                    <span class="badge <?= $st === 'active' ? 'badge-approved' : '' ?>"
                      style="<?= $st !== 'active' ? 'background:rgba(107,127,163,0.12);border:1px solid rgba(107,127,163,0.2);color:var(--text-muted)' : '' ?>">
                      <?= $st === 'active' ? '● Active' : '✓ Done' ?>
                    </span>
                  </td>
                  <td class="muted"><?= date('M d, Y g:i A', strtotime($h['created_at'] ?? 'now')) ?></td>
                  <td class="muted">
                    <?php if (!empty($h['timeout_at'])): ?>
                      <?= date('M d, Y g:i A', strtotime($h['timeout_at'])) ?>
                    <?php elseif ($st === 'active'): ?>
                      <span style="color:var(--success-c);font-size:.72rem;">Still active</span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <?php if ($st === 'done'): ?>
                      <?php if ($alreadyFeedback): ?>
                        <span class="btn-feedback-done">✓ Submitted</span>
                      <?php else: ?>
                        <button class="btn-small-blue" style="white-space:nowrap;"
                          onclick='openFeedbackModal(<?= json_encode([
                            "id"      => (int)$h["id"],
                            "lab"     => $h["lab"] ?? "",
                            "purpose" => $h["purpose"] ?? ""
                          ]) ?>)'>
                          💬 Feedback
                        </button>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="font-size:.72rem;color:var(--text-muted);">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================
       RESERVATION SECTION
       ============================================================ -->
  <div class="dash-section" id="sec-reservation">
    <div class="page-title">Reservation</div>
    <div class="page-sub">Reserve a lab session — admin will approve or decline your request</div>
    <div class="res-grid">
      <div class="dash-card">
        <div class="dash-card-header"><div class="dash-card-header-icon">📅</div><span class="dash-card-header-label">New Reservation</span></div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:14px;">
          <?php if ($activeSession): ?>
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:32px 20px;text-align:center;">
              <div style="font-size:2.5rem;">🔒</div>
              <div style="font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:#34d399;">You have an active sit-in session</div>
              <div style="font-size:.82rem;color:var(--text-muted);line-height:1.7;">You are currently sitting in at <strong style="color:#e8edf8;">Lab <?= htmlspecialchars($activeSession['lab']) ?></strong> for <strong style="color:#e8edf8;"><?= htmlspecialchars($activeSession['purpose']) ?></strong>.<br>You cannot make a new reservation until the admin times you out.</div>
              <div style="margin-top:6px;padding:10px 20px;background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2);border-radius:10px;font-size:.75rem;color:rgba(52,211,153,0.8);">⏳ Waiting for admin to time you out…</div>
            </div>
          <?php elseif ($hasPendingReservation): ?>
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:32px 20px;text-align:center;">
              <div style="font-size:2.5rem;">⏳</div>
              <div style="font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--gold);">Reservation Pending</div>
              <div style="font-size:.82rem;color:var(--text-muted);line-height:1.7;">You already have a pending reservation.<br>Please wait for the admin to approve or decline it before submitting a new one.</div>
            </div>
          <?php else: ?>
            <form method="post" action="dashboard.php" style="display:flex;flex-direction:column;gap:14px;">
              <input type="hidden" name="make_reservation" value="1">
              <div class="form-field"><label class="form-label">Date *</label><input type="date" name="res_date" class="form-input" min="<?= date('Y-m-d') ?>" required></div>
              <div class="form-field"><label class="form-label">Time *</label><input type="time" name="res_time" class="form-input" required></div>
              <div class="form-field"><label class="form-label">Lab *</label><select name="res_lab" class="form-select" required><option value="">— Select Lab —</option><option value="524">Lab 524</option><option value="526">Lab 526</option><option value="528">Lab 528</option><option value="530">Lab 530</option><option value="542">Lab 542</option><option value="544">Lab 544</option></select></div>
              <div class="form-field"><label class="form-label">Purpose *</label><input type="text" name="res_purpose" class="form-input" placeholder="e.g. C Programming, Research…" required></div>
              <div class="form-field"><label class="form-label">Your Name</label><input type="text" class="form-input" value="<?= $fullName ?>" readonly></div>
              <div class="form-field"><label class="form-label">ID Number</label><input type="text" class="form-input" value="<?= $id_number ?>" readonly></div>
              <button type="submit" class="btn-save" style="margin-top:4px;">📤 Submit Reservation</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="dash-card">
        <div class="dash-card-header"><div class="dash-card-header-icon">📋</div><span class="dash-card-header-label">My Reservations</span></div>
        <div style="padding:20px;">
          <div class="tbl-wrap">
            <table class="a-table">
              <thead><tr><th>Lab</th><th>Purpose</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (empty($myReservations)): ?>
                  <tr><td colspan="5" class="tbl-empty">No reservations yet.</td></tr>
                <?php else: foreach ($myReservations as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['lab'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['purpose'] ?? '') ?></td>
                    <td class="muted"><?= date('M d, Y', strtotime($r['reservation_date'] ?? 'now')) ?></td>
                    <td class="muted"><?= htmlspecialchars(date('g:i A', strtotime($r['reservation_time'] ?? '00:00'))) ?></td>
                    <td><?php $rs = $r['status'] ?? 'pending'; ?><span class="badge badge-<?= $rs ?>"><?= $rs === 'pending' ? '⏳ Pending' : ($rs === 'approved' ? '✅ Approved' : ($rs === 'completed' ? '✓ Completed' : '❌ Declined')) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</main>

<!-- ============================================================
     FEEDBACK MODAL
     ============================================================ -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal-box" style="max-width:460px;">
    <button class="modal-close" onclick="closeFeedbackModal()">✕</button>
    <div class="modal-title">💬 Leave Feedback</div>

    <div id="feedbackModalInfo" style="padding:12px 16px;background:var(--input-bg,rgba(255,255,255,0.04));border:1px solid rgba(255,255,255,0.08);border-radius:12px;font-size:.82rem;color:var(--text-muted,#8fa3c8);margin-bottom:18px;line-height:1.7;"></div>

    <form method="post" action="dashboard.php" style="display:flex;flex-direction:column;gap:16px;">
      <input type="hidden" name="submit_feedback" value="1">
      <input type="hidden" name="sit_in_id"    id="fb_sit_in_id">
      <input type="hidden" name="feedback_lab" id="fb_lab">
      <input type="hidden" name="rating"        id="fb_rating" value="0">

      <div>
        <div class="form-label" style="margin-bottom:8px;">Rating *</div>
        <div class="star-rating-wrap" id="starRating">
          <span data-val="1">☆</span>
          <span data-val="2">☆</span>
          <span data-val="3">☆</span>
          <span data-val="4">☆</span>
          <span data-val="5">☆</span>
        </div>
        <div id="ratingLabel" style="font-size:.72rem;color:var(--text-muted,#8fa3c8);margin-top:6px;">Click to rate your experience</div>
      </div>

      <div>
        <div class="form-label" style="margin-bottom:6px;">Your Experience *</div>
        <textarea name="feedback_message" id="fb_message"
          style="width:100%;background:var(--input-bg,rgba(255,255,255,0.04));border:1.5px solid var(--input-border,rgba(255,255,255,0.1));border-radius:10px;padding:12px 14px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--text,#c8d8f0);outline:none;resize:vertical;min-height:100px;box-sizing:border-box;transition:border-color .2s;"
          placeholder="Describe your experience in this lab session — was the PC working well? Any issues?" required></textarea>
      </div>

      <div class="modal-actions" style="margin-top:0;">
        <button type="button" class="btn-modal-cancel" onclick="closeFeedbackModal()">Cancel</button>
        <button type="submit" class="btn-modal-confirm">📤 Submit Feedback</button>
      </div>
    </form>
  </div>
</div>

<div class="toasts" id="toasts"></div>

<script>
function showSection(id, el) {
  document.querySelectorAll('.dash-section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
  document.querySelectorAll('.dash-topnav-links a').forEach(a => a.classList.remove('tnav-active'));
  if (el) el.classList.add('tnav-active');
  else {
    const map = {home:'nav-home',editprofile:'nav-editprofile',history:'nav-history',reservation:'nav-reservation'};
    if (map[id]) document.getElementById(map[id])?.classList.add('tnav-active');
  }
}

const photoInput     = document.getElementById('photoInput');
const photoLabel     = document.getElementById('photoLabel');
const photoSubmitBtn = document.getElementById('photoSubmitBtn');
const avatarImg      = document.getElementById('avatarImg');

photoInput?.addEventListener('change', () => {
  if (photoInput.files.length) {
    photoLabel.textContent = '📎 ' + photoInput.files[0].name;
    photoSubmitBtn.style.display = 'inline-block';
    const reader = new FileReader();
    reader.onload = e => { if(avatarImg) avatarImg.src = e.target.result; };
    reader.readAsDataURL(photoInput.files[0]);
  }
});

const editPhotoInput = document.getElementById('editPhotoInput');
const editPhotoBtn   = document.getElementById('editPhotoBtn');
const editAvatarImg  = document.getElementById('editAvatarImg');

editPhotoInput?.addEventListener('change', () => {
  if (editPhotoInput.files.length) {
    editPhotoBtn.style.display = 'inline-block';
    const reader = new FileReader();
    reader.onload = e => { if(editAvatarImg) editAvatarImg.src = e.target.result; };
    reader.readAsDataURL(editPhotoInput.files[0]);
  }
});

function toast(msg, type='') {
  const c = document.getElementById('toasts');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const icons = {error:'❌', success:'✅', '':'ℹ️'};
  t.innerHTML = `<span>${icons[type]||'ℹ️'}</span>${msg}`;
  c.appendChild(t);
  setTimeout(() => { t.classList.add('fade'); setTimeout(() => t.remove(), 350); }, 3200);
}

/* ---------------------------------------------------------------
   Feedback Modal
   --------------------------------------------------------------- */
const ratingLabels = ['','😞 Poor','😐 Fair','🙂 Good','😊 Very Good','🌟 Excellent'];

function openFeedbackModal(data) {
  document.getElementById('fb_sit_in_id').value = data.id;
  document.getElementById('fb_lab').value = data.lab;
  document.getElementById('fb_rating').value = '0';
  document.getElementById('fb_message').value = '';
  document.getElementById('ratingLabel').textContent = 'Click to rate your experience';
  document.getElementById('feedbackModalInfo').innerHTML =
    `<strong style="color:#e8edf8;">Lab / PC:</strong> ${data.lab || '—'} &nbsp;·&nbsp; <strong style="color:#e8edf8;">Purpose:</strong> ${data.purpose || '—'}`;
  // Reset stars
  document.querySelectorAll('#starRating span').forEach(s => {
    s.textContent = '☆';
    s.style.color = '';
  });
  document.getElementById('feedbackModal').classList.add('open');
}

function closeFeedbackModal() {
  document.getElementById('feedbackModal').classList.remove('open');
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
  if (e.target === this) closeFeedbackModal();
});

// Star rating interactions
document.querySelectorAll('#starRating span').forEach(star => {
  star.addEventListener('click', function() {
    const val = parseInt(this.dataset.val);
    document.getElementById('fb_rating').value = val;
    document.getElementById('ratingLabel').textContent = ratingLabels[val] || '';
    document.querySelectorAll('#starRating span').forEach((s, i) => {
      s.textContent = i < val ? '★' : '☆';
      s.style.color = i < val ? '#f0b429' : '';
    });
  });
  star.addEventListener('mouseover', function() {
    const val = parseInt(this.dataset.val);
    document.querySelectorAll('#starRating span').forEach((s, i) => {
      s.textContent = i < val ? '★' : '☆';
      s.style.color = i < val ? 'rgba(240,180,41,0.7)' : '';
    });
  });
  star.addEventListener('mouseout', function() {
    const current = parseInt(document.getElementById('fb_rating').value);
    document.querySelectorAll('#starRating span').forEach((s, i) => {
      s.textContent = i < current ? '★' : '☆';
      s.style.color = i < current ? '#f0b429' : '';
    });
  });
});

/* ---------------------------------------------------------------
   Open section on load
   --------------------------------------------------------------- */
const openSection = '<?= $openSection ?>';
if (openSection !== 'home') showSection(openSection, null);

<?php if ($profileMsg !== ''): ?>
(function(){ const parts='<?= addslashes($profileMsg) ?>'.split(':'); toast(parts.slice(1).join(':'), parts[0]); })();
<?php endif; ?>
<?php if ($reservationMsg !== ''): ?>
(function(){ const parts='<?= addslashes($reservationMsg) ?>'.split(':'); toast(parts.slice(1).join(':'), parts[0]); })();
<?php endif; ?>
<?php if ($sessionEndMsg !== ''): ?>
(function(){ const parts='<?= addslashes($sessionEndMsg) ?>'.split(':'); toast(parts.slice(1).join(':'), parts[0]); })();
<?php endif; ?>
<?php if ($feedbackMsg !== ''): ?>
(function(){ const parts='<?= addslashes($feedbackMsg) ?>'.split(':'); toast(parts.slice(1).join(':'), parts[0]); })();
<?php endif; ?>
<?php if ($showWelcomeToast): ?>
setTimeout(() => toast('Welcome back, <?= addslashes($first_name) ?>! 👋', 'success'), 600);
<?php endif; ?>

/* ---------------------------------------------------------------
   Live notifications
   --------------------------------------------------------------- */
const notifDropdownButton = document.querySelector('.tnav-dropdown > button');
const notifPip = document.querySelector('.notif-pip');
let notifDropCount = document.querySelector('.tnav-drop-count');
let latestAnnouncementId = <?= intval($latestAnnouncementId) ?>;
const unseenLocalKey = 'dashboardNotificationsLastSeen';
let seenAnnouncementId = Number(localStorage.getItem(unseenLocalKey) || '0');
let prevUnseen = null;

function hideNotificationBadge() { if (notifPip) notifPip.style.display = 'none'; }
function showNotificationBadge(count) { if (notifPip) { notifPip.style.display = 'inline-flex'; notifPip.textContent = count; } }
function markNotificationsSeen() { localStorage.setItem(unseenLocalKey, String(latestAnnouncementId)); seenAnnouncementId = latestAnnouncementId; hideNotificationBadge(); }

function renderNotificationPanel(data) {
  const panel = document.querySelector('.tnav-dropdown-panel');
  if (!panel) return;
  const headerCount = data.total > 0 ? `<span class="tnav-drop-count">${data.total}</span>` : '';
  let bodyHtml = '';
  if (!data.announcements || data.announcements.length === 0) {
    bodyHtml = '<div class="tnav-drop-empty">No notifications yet.</div>';
  } else {
    bodyHtml = data.announcements.map(a => {
      const content = a.content ? a.content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
      const admin = a.admin_name ? a.admin_name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : 'Admin';
      const date = new Date(a.created_at).toLocaleString('en-US', { month:'short', day:'2-digit', year:'numeric', hour:'numeric', minute:'2-digit' });
      return `<div class="tnav-drop-item"><div class="tnav-drop-avatar">CA</div><div class="tnav-drop-body"><span class="tnav-drop-author">${admin}</span><span class="tnav-drop-text">${content}</span><span class="tnav-drop-date">${date}</span></div></div>`;
    }).join('');
  }
  if (data.total > 3) bodyHtml += `<div class="tnav-drop-footer" onclick="showSection('home',null);document.getElementById('announceCard').scrollIntoView({behavior:'smooth'})">View all ${data.total} announcements ↓</div>`;
  panel.innerHTML = `<div class="tnav-drop-header">Notifications${headerCount}</div>${bodyHtml}`;
}

function updateNotificationState(data) {
  latestAnnouncementId = data.latest_id;
  if (data.unseen > 0) showNotificationBadge(data.unseen); else hideNotificationBadge();
  if (data.announcements) { renderNotificationPanel(data); notifDropCount = document.querySelector('.tnav-drop-count'); }
  if (notifDropCount && data.total !== undefined) notifDropCount.textContent = data.total;
  if (prevUnseen !== null && prevUnseen === 0 && data.unseen > 0) toast('New announcement available', 'success');
  prevUnseen = data.unseen;
}

function refreshNotificationStatus() {
  fetch(`dashboard.php?ajax=notifications&seen_id=${seenAnnouncementId}`).then(r => r.json()).then(updateNotificationState).catch(() => {});
}

if (latestAnnouncementId > seenAnnouncementId) showNotificationBadge(latestAnnouncementId - seenAnnouncementId || 1); else hideNotificationBadge();

notifDropdownButton?.addEventListener('mouseenter', markNotificationsSeen);
notifDropdownButton?.addEventListener('focus', markNotificationsSeen);
notifDropdownButton?.addEventListener('click', markNotificationsSeen);
setInterval(refreshNotificationStatus, 8000);
refreshNotificationStatus();
</script>
<?php include 'footer.php'; ?>
</body>
</html>
<?php
require_once 'db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number        = trim($_POST['id_number'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $first_name       = trim($_POST['first_name'] ?? '');
    $middle_name      = trim($_POST['middle_name'] ?? '');
    $course_level     = intval($_POST['course_level'] ?? 1);
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $course           = trim($_POST['course'] ?? 'BSIT');
    $address          = trim($_POST['address'] ?? '');

    // default settings for new accounts
    $role             = 'student';
    $photoPath        = 'register.png';

    if (empty($id_number))   $errors['id_number']   = 'ID Number is required.';
    if (empty($last_name))   $errors['last_name']   = 'Last name is required.';
    if (empty($first_name))  $errors['first_name']  = 'First name is required.';
    if (empty($email))       $errors['email']       = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if (empty($password))    $errors['password']    = 'Password is required.';
    elseif (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
    if ($password !== $confirm_password) $errors['confirm_password'] = 'Passwords do not match.';
    if (empty($address))     $errors['address']     = 'Address is required.';

    // handle uploaded photo if supplied
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'Error uploading photo.';
        } else {
            $allowed = ['image/jpeg','image/png','image/gif'];
            if (!in_array($file['type'], $allowed)) {
                $errors['photo'] = 'Only JPG/PNG/GIF images are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors['photo'] = 'Photo must be smaller than 2MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $base = preg_replace('/[^a-z0-9_-]/i','', $id_number ?: uniqid());
                $newName = $base . '_' . time() . '.' . $ext;
                $dest = __DIR__ . "/pictures/" . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $photoPath = $newName;
                } else {
                    $errors['photo'] = 'Failed to save uploaded photo.';
                }
            }
        }
    }

    if (empty($errors)) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE email = ? OR id_number = ?");
        $stmt->bind_param("ss", $email, $id_number);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors['general'] = 'An account with this email or ID number already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO accounts (id_number, last_name, first_name, middle_name, course_level, email, password, course, address, profile_photo, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssissssss", $id_number, $last_name, $first_name, $middle_name, $course_level, $email, $hashed, $course, $address, $photoPath, $role);
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $errors['general'] = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
        $conn->close();
    }
}

function old($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — CCS Sit-In Monitoring</title>
  <link rel="stylesheet" href="design.css">
</head>
<body class="reg-page">

<div class="scene-bg">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<?php include 'header.php'; ?>

<main>
  <div class="reg-container">

    <!-- Visual panel -->
    <div class="reg-visual">
      <img src="pictures/ccs.png" alt="CCS Logo" class="reg-visual-img">
      <div class="visual-title">Create your account</div>
      <div class="visual-sub">Join the CCS Student Portal in just a few steps.</div>

      <!-- Step tracker -->
      <ul class="steps-list" id="stepsList">
        <li class="step-active" data-step="1">
          <span class="step-num">1</span>Personal Info
        </li>
        <li data-step="2">
          <span class="step-num">2</span>Account Details
        </li>
        <li data-step="3">
          <span class="step-num">3</span>Academic Info
        </li>
      </ul>
    </div>

    <!-- Form panel -->
    <div class="reg-form-panel">

      <!-- Header row -->
      <div class="panel-header">
        <a href="login.php" class="btn-back">← Back to Login</a>
        <span class="step-badge" id="stepBadge">Step 1 of 3</span>
      </div>

      <!-- Progress bar -->
      <div class="prog-track">
        <div class="prog-seg active" id="ps1"></div>
        <div class="prog-seg"        id="ps2"></div>
        <div class="prog-seg"        id="ps3"></div>
      </div>

      <?php if (!empty($errors['general'])): ?>
        <div class="error-alert">⚠ <?= htmlspecialchars($errors['general']) ?></div>
      <?php endif; ?>

      <form id="regForm" action="register.php" method="post" enctype="multipart/form-data" novalidate>

        <!-- ===== STEP 1 ===== -->
        <div class="form-step" id="step1">
          <p class="form-eyebrow">Step 1 — Personal</p>
          <h2 class="form-title">Tell us about yourself</h2>

          <div class="field">
            <span class="field-icon">#</span>
            <input type="text" name="id_number" id="id_number" placeholder=" "
              class="<?= isset($errors['id_number']) ? 'is-error' : '' ?>"
              value="<?= old('id_number') ?>">
            <label for="id_number">Student ID Number</label>
            <?php if (isset($errors['id_number'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['id_number']) ?></div>
            <?php endif; ?>
          </div>

          <div class="field-row">
            <div class="field">
              <span class="field-icon">👤</span>
              <input type="text" name="last_name" id="last_name" placeholder=" "
                class="<?= isset($errors['last_name']) ? 'is-error' : '' ?>"
                value="<?= old('last_name') ?>">
              <label for="last_name">Last Name</label>
              <?php if (isset($errors['last_name'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['last_name']) ?></div>
              <?php endif; ?>
            </div>
            <div class="field">
              <span class="field-icon">👤</span>
              <input type="text" name="first_name" id="first_name" placeholder=" "
                class="<?= isset($errors['first_name']) ? 'is-error' : '' ?>"
                value="<?= old('first_name') ?>">
              <label for="first_name">First Name</label>
              <?php if (isset($errors['first_name'])): ?>
                <div class="field-error">⚠ <?= htmlspecialchars($errors['first_name']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="field">
            <span class="field-icon">👤</span>
            <input type="text" name="middle_name" id="middle_name" placeholder=" " value="<?= old('middle_name') ?>">
            <label for="middle_name">Middle Name <span style="opacity:.45;">(optional)</span></label>
          </div>

          <div class="btn-row">
            <button type="button" class="btn-next" onclick="goStep(2)">Continue →</button>
          </div>
        </div>

        <!-- ===== STEP 2 ===== -->
        <div class="form-step" id="step2" style="display:none;">
          <p class="form-eyebrow">Step 2 — Account</p>
          <h2 class="form-title">Set your credentials</h2>

          <div class="field">
            <span class="field-icon">✉</span>
            <input type="email" name="email" id="email" placeholder=" "
              class="<?= isset($errors['email']) ? 'is-error' : '' ?>"
              value="<?= old('email') ?>">
            <label for="email">Email Address</label>
            <?php if (isset($errors['email'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <span class="field-icon">🔒</span>
            <input type="password" name="password" id="reg_password" placeholder=" "
              class="<?= isset($errors['password']) ? 'is-error' : '' ?>"
              oninput="updateStrength(this.value)" style="padding-right:44px;">
            <label for="reg_password">Password</label>
            <button type="button" class="pw-toggle" data-target="reg_password">
              <span class="eye">👁</span>
            </button>
            <?php if (isset($errors['password'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['password']) ?></div>
            <?php endif; ?>
          </div>

          <div class="strength-wrap">
            <div class="strength-track">
              <div class="strength-fill" id="strengthFill"></div>
            </div>
            <span class="strength-label" id="strengthLabel">Enter a password</span>
          </div>

          <div class="field">
            <span class="field-icon">🔒</span>
            <input type="password" name="confirm_password" id="confirm_password" placeholder=" "
              class="<?= isset($errors['confirm_password']) ? 'is-error' : '' ?>"
              style="padding-right:44px;">
            <label for="confirm_password">Confirm Password</label>
            <button type="button" class="pw-toggle" data-target="confirm_password">
              <span class="eye">👁</span>
            </button>
            <?php if (isset($errors['confirm_password'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['confirm_password']) ?></div>
            <?php endif; ?>
          </div>

          <div class="btn-row">
            <button type="button" class="btn-prev" onclick="goStep(1)">←</button>
            <button type="button" class="btn-next" onclick="goStep(3)">Continue →</button>
          </div>
        </div>

        <!-- ===== STEP 3 ===== -->
        <div class="form-step" id="step3" style="display:none;">
          <p class="form-eyebrow">Step 3 — Academic</p>
          <h2 class="form-title">Your academic details</h2>

          <div class="field-row">
            <div class="field">
              <span class="field-icon">🎓</span>
              <input type="text" name="course" id="course" placeholder=" " value="<?= old('course', 'BSIT') ?>">
              <label for="course">Course</label>
            </div>
            <div class="field">
              <span class="field-icon">📊</span>
              <input type="number" name="course_level" id="course_level" placeholder=" "
                value="<?= old('course_level', '1') ?>" min="1" max="5">
              <label for="course_level">Year Level</label>
            </div>
          </div>

          <div class="field">
            <span class="field-icon">📍</span>
            <input type="text" name="address" id="address" placeholder=" "
              class="<?= isset($errors['address']) ? 'is-error' : '' ?>"
              value="<?= old('address') ?>">
            <label for="address">Address</label>
            <?php if (isset($errors['address'])): ?>
              <div class="field-error">⚠ <?= htmlspecialchars($errors['address']) ?></div>
            <?php endif; ?>
          </div>

          <div class="btn-row">
            <button type="button" class="btn-prev" onclick="goStep(2)">←</button>
            <button type="submit" class="btn-submit" id="submitBtn">✅ Create Account</button>
          </div>
        </div>

      </form>

      <p class="form-footer-link">
        Already have an account? <a href="login.php">Sign in →</a>
      </p>

    </div>
  </div>
</main>

<div class="toasts" id="toasts"></div>

<?php include 'footer.php'; ?>

<script>
// Floating label polyfill
document.querySelectorAll('.field input').forEach(inp => {
  const sync = () => inp.value ? inp.classList.add('has-val') : inp.classList.remove('has-val');
  inp.addEventListener('input', sync);
  setTimeout(sync, 200);
});

// Password toggle
document.querySelectorAll('.pw-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.getElementById(btn.dataset.target);
    const eye = btn.querySelector('.eye');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    eye.textContent = inp.type === 'password' ? '👁' : '🙈';
  });
});

// Ripple
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-next, .btn-submit');
  if (!btn) return;
  const r = btn.getBoundingClientRect();
  const d = Math.max(btn.clientWidth, btn.clientHeight);
  const rip = document.createElement('span');
  rip.className = 'ripple';
  rip.style.cssText = `width:${d}px;height:${d}px;left:${e.clientX-r.left-d/2}px;top:${e.clientY-r.top-d/2}px;`;
  btn.appendChild(rip);
  setTimeout(() => rip.remove(), 650);
});

// Password strength
function updateStrength(val) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 6)        score++;
  if (val.length >= 10)       score++;
  if (/[A-Z]/.test(val))      score++;
  if (/[0-9]/.test(val))      score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const map = [
    { w:'0%',   c:'rgba(255,255,255,0.1)', t:'Enter a password' },
    { w:'25%',  c:'#ef4444',              t:'😟 Weak' },
    { w:'50%',  c:'#f59e0b',              t:'😐 Fair' },
    { w:'75%',  c:'#3b82f6',              t:'🙂 Good' },
    { w:'90%',  c:'#10b981',              t:'😎 Strong' },
    { w:'100%', c:'#059669',              t:'💪 Very strong!' },
  ];
  const lvl = val.length === 0 ? 0 : Math.min(score, 5);
  fill.style.width = map[lvl].w;
  fill.style.background = map[lvl].c;
  label.textContent = map[lvl].t;
  label.style.color = map[lvl].c;
}

// Multi-step
let current = 1;
const stepLabels = ['Step 1 of 3', 'Step 2 of 3', 'Step 3 of 3'];

<?php if (!empty($errors)): ?>
const errKeys = <?= json_encode(array_keys($errors)) ?>;
if (errKeys.some(k => ['id_number','last_name','first_name'].includes(k))) current = 1;
else if (errKeys.some(k => ['email','password','confirm_password'].includes(k))) current = 2;
else current = 3;
showStep(current);
<?php endif; ?>

function goStep(n) {
  if (n > current) {
    if (current === 1) {
      const id = document.getElementById('id_number').value.trim();
      const ln = document.getElementById('last_name').value.trim();
      const fn = document.getElementById('first_name').value.trim();
      if (!id || !ln || !fn) { toast('Please fill in all required fields.', 'error'); return; }
    }
    if (current === 2) {
      const em = document.getElementById('email').value.trim();
      const pw = document.getElementById('reg_password').value;
      const cp = document.getElementById('confirm_password').value;
      if (!em || !pw) { toast('Email and password are required.', 'error'); return; }
      if (pw.length < 6) { toast('Password must be at least 6 characters.', 'error'); return; }
      if (pw !== cp) { toast('Passwords do not match.', 'error'); return; }
    }
  }
  current = n;
  showStep(n);
}

function showStep(n) {
  document.querySelectorAll('.form-step').forEach((el, i) => {
    el.style.display = (i + 1 === n) ? 'block' : 'none';
  });
  // Progress segments
  for (let i = 1; i <= 3; i++) {
    const seg = document.getElementById('ps' + i);
    seg.className = 'prog-seg' + (i < n ? ' done' : i === n ? ' active' : '');
  }
  // Badge
  document.getElementById('stepBadge').textContent = stepLabels[n - 1];
  // Side steps list
  document.querySelectorAll('#stepsList li').forEach(li => {
    const s = parseInt(li.dataset.step);
    li.className = s < n ? 'step-done' : s === n ? 'step-active' : '';
    if (s < n) li.querySelector('.step-num').textContent = '✓';
    else li.querySelector('.step-num').textContent = s;
  });
}

// Submit spinner
document.getElementById('regForm').addEventListener('submit', () => {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Creating account…';
});

// Toast
function toast(msg, type = '') {
  const c = document.getElementById('toasts');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const icons = { error:'❌', success:'✅', '':'ℹ️' };
  t.innerHTML = `<span>${icons[type]}</span>${msg}`;
  c.appendChild(t);
  setTimeout(() => {
    t.classList.add('fade');
    setTimeout(() => t.remove(), 350);
  }, 3200);
}

<?php if (!empty($errors['general'])): ?>
toast('<?= addslashes($errors['general']) ?>', 'error');
<?php endif; ?>
</script>
</body>
</html>
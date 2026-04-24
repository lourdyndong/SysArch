<?php
require_once 'db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email))    $errors['email']    = 'ID number is required.';
    if (empty($password)) $errors['password'] = 'Password is required.';

    if (empty($errors)) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE id_number = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_data'] = $user;
            $_SESSION['show_welcome_toast'] = true;
            // redirect according to role
            if (!empty($user['role']) && $user['role'] === 'admin') {
                header("Location: admindashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $errors['general'] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — CCS Sit-In Monitoring</title>
  <link rel="stylesheet" href="design.css">
</head>
<body>

<div class="scene-bg">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<?php include 'header.php'; ?>

<main>
  <div class="login-container">

    <!-- Visual panel -->
    <div class="login-visual">
      <img src="pictures/ccs.png" alt="CCS Logo" class="visual-img">
      <div class="visual-title">CCS Sit-In Monitoring</div>
      <div class="visual-sub">Track sessions, manage resources, and stay on top of lab usage.</div>
      <div class="visual-badge">⚡ Student Portal</div>
    </div>

    <!-- Form panel -->
    <div class="login-form-panel">

      <p class="form-eyebrow">Welcome back</p>
      <h1 class="form-title">Sign in to your<br>account</h1>
      <p class="form-sub">Enter your credentials to continue</p>

      <?php if (!empty($errors['general'])): ?>
        <div class="error-alert">
          <span>⚠</span>
          <?= htmlspecialchars($errors['general']) ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" action="login.php" method="post" novalidate>

        <!-- Email / ID -->
        <div class="field">
          <span class="field-icon">🪪</span>
          <input
            type="text"
            name="email"
            id="email"
            placeholder=" "
            autocomplete="username"
            class="<?= isset($errors['email']) ? 'is-error' : '' ?>"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          >
          <label for="email">ID Number</label>
          <?php if (isset($errors['email'])): ?>
            <span class="field-error">⚠ <?= htmlspecialchars($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="field">
          <span class="field-icon">🔒</span>
          <input
            type="password"
            name="password"
            id="password"
            placeholder=" "
            autocomplete="current-password"
            class="<?= isset($errors['password']) ? 'is-error' : '' ?>"
            style="padding-right: 44px;"
          >
          <label for="password">Password</label>
          <button type="button" class="pw-toggle" data-target="password" aria-label="Toggle password">
            <span class="eye">👁</span>
          </button>
          <?php if (isset($errors['password'])): ?>
            <span class="field-error">⚠ <?= htmlspecialchars($errors['password']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Forgot -->
        <div class="form-extras">
          <a href="#">Forgot password?</a>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn-submit" id="loginBtn">
          Sign In
        </button>

      </form>

      <p class="form-footer-link">
        Don't have an account? <a href="register.php">Create one →</a>
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
  const btn = e.target.closest('.btn-submit');
  if (!btn) return;
  const r = btn.getBoundingClientRect();
  const d = Math.max(btn.clientWidth, btn.clientHeight);
  const ripple = document.createElement('span');
  ripple.className = 'ripple';
  ripple.style.cssText = `width:${d}px;height:${d}px;left:${e.clientX-r.left-d/2}px;top:${e.clientY-r.top-d/2}px;`;
  btn.appendChild(ripple);
  setTimeout(() => ripple.remove(), 650);
});

// Spinner on submit
document.getElementById('loginForm').addEventListener('submit', () => {
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Signing in…';
});

// Toast
function toast(msg, type = '') {
  const c = document.getElementById('toasts');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const icons = { error: '❌', success: '✅', '': 'ℹ️' };
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
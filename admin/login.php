<?php
/*
Registration key: URS-REGISTRAR-2026
*/
session_start();
require_once '../config/db.php';

define('ADMIN_REG_KEY', 'URS-REGISTRAR-2026');

if (isset($_SESSION['admin_id'])) {
  header('Location: dashboard.php');
  exit;
}

$activeTab   = 'login';
$loginError  = '';
$signupError = '';
$oldUsername = '';
$oldFullName = '';

function startAdminSession(array $admin): void
{
  $_SESSION['admin_id']       = $admin['id'];
  $_SESSION['admin_username'] = $admin['username'];
  $_SESSION['admin_name']     = $admin['full_name'];
  $_SESSION['admin_photo']    = $admin['photo'] ?? null;
  header('Location: dashboard.php');
  exit;
}

require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $formType = $_POST['form_type'] ?? 'login';

  if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    if ($formType === 'signup') {
      $activeTab = 'signup';
      $signupError = 'Your form session expired. Please try again.';
    } else {
      $loginError = 'Your form session expired. Please try again.';
    }
  }

  // ---------------------------------------------------------------
  // Log in
  // ---------------------------------------------------------------
  elseif ($formType === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
      $loginError = 'Enter your username and password.';
    } else {
      $stmt = $conn->prepare("SELECT id, username, password, full_name, photo FROM admin WHERE username = ?");
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $res = $stmt->get_result();

      if ($res->num_rows === 1) {
        $admin = $res->fetch_assoc();
        $stmt->close();
        if (password_verify($password, $admin['password'])) {
          startAdminSession($admin);
        }
      } else {
        $stmt->close();
      }
      $loginError = 'That username and password do not match an account.';
    }
  }

  // ---------------------------------------------------------------
  // Create account
  // ---------------------------------------------------------------
  elseif ($formType === 'signup') {
    $activeTab = 'signup';

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['new_username'] ?? '');
    $password = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $regKey   = trim($_POST['reg_key'] ?? '');

    $oldFullName = $fullName;
    $oldUsername = $username;

    if ($fullName === '' || $username === '' || $password === '' || $regKey === '') {
      $signupError = 'Fill in every field to create your account.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
      $signupError = 'Username must be 4-50 characters, using only letters, numbers, dot, underscore or dash.';
    } elseif (strlen($password) < 8) {
      $signupError = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
      $signupError = 'The two passwords do not match.';
    } elseif (!hash_equals(ADMIN_REG_KEY, $regKey)) {
      $signupError = 'That registration key is not valid. Ask the registrar for the current key.';
    } else {
      $check = $conn->prepare("SELECT id FROM admin WHERE username = ?");
      $check->bind_param('s', $username);
      $check->execute();
      $taken = $check->get_result()->num_rows > 0;
      $check->close();

      if ($taken) {
        $signupError = 'That username is already taken. Choose another one.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins  = $conn->prepare("INSERT INTO admin (username, password, full_name) VALUES (?, ?, ?)");
        $ins->bind_param('sss', $username, $hash, $fullName);

        if ($ins->execute()) {
          $newId = $ins->insert_id;
          $ins->close();
          startAdminSession([
            'id'        => $newId,
            'username'  => $username,
            'full_name' => $fullName,
          ]);
        } else {
          $ins->close();
          $signupError = 'The account could not be created. Try again in a moment.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrar Admin | Document Request System</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-body">
  <main class="auth-page">
    <div class="auth-shell">

      <div class="auth-intro">
        <h1> <span class="brand-icon" aria-hidden="true"></span>
          Registrar Admin</h1>
        <p>Review document requests, mark them ready for pickup, and close them out
          once a student has claimed their copy.</p>
      </div>

      <section class="auth-card" aria-labelledby="authHeading">
        <h2 id="authHeading" class="auth-card-title">Registrar Admin Access</h2>


        <!-- ---------------- Log in ---------------- -->
        <div class="auth-panel" id="panel-login" role="tabpanel" aria-labelledby="tab-login">
          <?php if ($loginError): ?>
            <div class="alert alert-error auth-alert"><?php echo htmlspecialchars($loginError); ?></div>
          <?php endif; ?>

          <form method="POST" class="auth-form">
            <input type="hidden" name="form_type" value="login" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" autocomplete="nope-username" required>
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary auth-submit">Log in</button>
          </form>

          <p class="auth-switch">No account yet?
            <button type="button" class="auth-link" data-panel="signup">Create one</button>
          </p>
        </div>

        <!-- ---------------- Create account ---------------- -->
        <div class="auth-panel" id="panel-signup" role="tabpanel" aria-labelledby="tab-signup" hidden>
          <?php if ($signupError): ?>
            <div class="alert alert-error auth-alert"><?php echo htmlspecialchars($signupError); ?></div>
          <?php endif; ?>

          <form method="POST" class="auth-form">
            <input type="hidden" name="form_type" value="signup" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div class="form-group">
              <label for="full_name">Full name</label>
              <input type="text" id="full_name" name="full_name" autocomplete="off"
                value="<?php echo htmlspecialchars($oldFullName); ?>" required>
            </div>

            <div class="form-group">
              <label for="new_username">Username</label>
              <input type="text" id="new_username" name="new_username" autocomplete="off"
                value="<?php echo htmlspecialchars($oldUsername); ?>" required>
            </div>

            <div class="auth-row">
              <div class="form-group">
                <label for="new_password">Password</label>
                <input type="password" id="new_password" name="new_password"
                  autocomplete="new-password" minlength="8" required>
              </div>

              <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                  autocomplete="new-password" minlength="8" required>
              </div>
            </div>

            <div class="form-group">
              <label for="reg_key">Registration key</label>
              <input type="password" id="reg_key" name="reg_key" autocomplete="one-time-code" required> <span class="auth-hint">The registrar's office issues this key to authorised staff.</span>
            </div>

            <button type="submit" class="btn btn-primary auth-submit">Create account</button>
          </form>

          <p class="auth-switch">Already have an account?
            <button type="button" class="auth-link" data-panel="login">Log in</button>
          </p>
        </div>
      </section>
    </div>
  </main>

  <script>
    (function() {
      var tabs = document.querySelectorAll('.auth-tab');
      var panels = {
        login: document.getElementById('panel-login'),
        signup: document.getElementById('panel-signup')
      };

      function show(name) {
        Object.keys(panels).forEach(function(key) {
          panels[key].hidden = key !== name;
        });
        tabs.forEach(function(tab) {
          var on = tab.dataset.panel === name;
          tab.classList.toggle('is-active', on);
          tab.setAttribute('aria-selected', on ? 'true' : 'false');
          tab.setAttribute('tabindex', on ? '0' : '-1');
        });
        var first = panels[name].querySelector('input');
        if (first && document.activeElement !== document.body) first.focus();
      }

      document.querySelectorAll('[data-panel]').forEach(function(el) {
        el.addEventListener('click', function() {
          show(el.dataset.panel);
        });
      });

      show(<?php echo json_encode($activeTab); ?>);
    })();
  </script>
</body>

</html>
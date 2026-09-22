<?php
/*
Registration key: URS-REGISTRAR-2026
*/
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
  header('Location: statistics.php');
  exit;
}

$activeTab   = 'login';
$loginError  = '';
$signupError = '';
$oldUsername = '';
$oldFullName = '';

function startAdminSession(array $admin, mysqli $conn): void
{
  $_SESSION['admin_id']       = $admin['id'];
  $_SESSION['admin_username'] = $admin['username'];
  $_SESSION['admin_name']     = $admin['full_name'];
  $_SESSION['admin_photo']    = $admin['photo'] ?? null;
  $_SESSION['admin_role']     = $admin['role'] ?? 'full_admin';

  $_SESSION['admin_doc_scope'] = [];
  if ($_SESSION['admin_role'] === 'document_admin') {
    $scopeStmt = $conn->prepare("SELECT document_type FROM admin_document_scope WHERE admin_id = ?");
    $scopeStmt->bind_param('i', $admin['id']);
    $scopeStmt->execute();
    $res = $scopeStmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $_SESSION['admin_doc_scope'][] = $row['document_type'];
    }
    $scopeStmt->close();
  }

  header('Location: statistics.php');
  exit;
}

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
      $stmt = $conn->prepare("SELECT id, username, password, full_name, photo, role FROM admin WHERE username = ?");
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $res = $stmt->get_result();

      if ($res->num_rows === 1) {
        $admin = $res->fetch_assoc();
        $stmt->close();
        if (password_verify($password, $admin['password'])) {
          startAdminSession($admin, $conn);
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
    $adminRole = ($_POST['admin_role'] ?? 'full_admin') === 'document_admin' ? 'document_admin' : 'full_admin';

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
        $ins  = $conn->prepare("INSERT INTO admin (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $ins->bind_param('ssss', $username, $hash, $fullName, $adminRole);

        if ($ins->execute()) {
          $newId = $ins->insert_id;
          $ins->close();

          // Document type scope (for document_admin accounts) is no longer
          // chosen at signup — it starts empty and is assigned afterward
          // by a Full Admin via manage_admins.php.

          startAdminSession([
            'id'        => $newId,
            'username'  => $username,
            'full_name' => $fullName,
            'role'      => $adminRole,
          ], $conn);
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

      <div class="auth-intro" id="authIntro" <?php echo $activeTab === 'signup' ? ' hidden' : ''; ?>>
        <h1> <span class="brand-icon" aria-hidden="true"></span>
          Registrar Admin</h1>
        <p>Review document requests, mark them ready for pickup, and close them out
          once a student has claimed their copy.</p>
      </div>

      <section class="auth-card<?php echo $activeTab === 'signup' ? ' auth-card-wide' : ''; ?>" id="authCard" aria-labelledby="authHeading">
        <h2 id="authHeading" class="auth-card-title">Registrar Admin Access</h2>


        <!-- ---------------- Log in ---------------- -->
        <div class="auth-panel" id="panel-login" role="tabpanel" aria-labelledby="tab-login">
          <?php if ($loginError): ?>
            <div class="alert alert-error auth-alert"><?php echo htmlspecialchars($loginError); ?></div>
          <?php elseif (isset($_GET['reset'])): ?>
            <div class="alert alert-success auth-alert">
              <p>Your password has been reset. Log in with your new password.</p>
            </div>
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

          <p class="auth-switch">
            <a href="forgot_password.php" class="auth-link" style="text-decoration:none;">Forgot password?</a>
          </p>

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
              <label>Account Type</label>
              <div class="role-toggle">
                <label class="role-option">
                  <input type="radio" name="admin_role" value="full_admin" checked>
                  <span>Full Admin <small>Sees and manages every request</small></span>
                </label>
                <label class="role-option">
                  <input type="radio" name="admin_role" value="document_admin">
                  <span>Document Admin <small>Only sees requests for chosen document types</small></span>
                </label>
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

      var authIntro = document.getElementById('authIntro');
      var authCard = document.getElementById('authCard');
      var authShell = document.querySelector('.auth-shell');

      function applyPanelSwitch(name) {
        Object.keys(panels).forEach(function(key) {
          panels[key].hidden = key !== name;
        });
        tabs.forEach(function(tab) {
          var on = tab.dataset.panel === name;
          tab.classList.toggle('is-active', on);
          tab.setAttribute('aria-selected', on ? 'true' : 'false');
          tab.setAttribute('tabindex', on ? '0' : '-1');
        });

        var isSignup = name === 'signup';
        if (authIntro) authIntro.hidden = isSignup;
        if (authCard) authCard.classList.toggle('auth-card-wide', isSignup);
        if (authShell) authShell.classList.toggle('auth-shell-signup', isSignup);

        var first = panels[name].querySelector('input');
        if (first && document.activeElement !== document.body) first.focus();
      }

      function show(name) {
        var current = Object.keys(panels).map(function(k) {
            return panels[k];
          })
          .find(function(p) {
            return !p.hidden;
          });

        if (!current || current === panels[name]) {
          applyPanelSwitch(name);
          return;
        }

        current.classList.add('auth-panel-out');
        setTimeout(function() {
          current.classList.remove('auth-panel-out');
          applyPanelSwitch(name);
        }, 180);
      }

      document.querySelectorAll('[data-panel]').forEach(function(el) {
        el.addEventListener('click', function() {
          show(el.dataset.panel);
        });
      });

      document.querySelectorAll('a.auth-link, a.auth-back').forEach(function(link) {
        link.addEventListener('click', function(e) {
          var href = link.getAttribute('href');
          if (!href || e.metaKey || e.ctrlKey || e.shiftKey) return;
          e.preventDefault();
          document.body.classList.add('auth-leaving');
          setTimeout(function() {
            window.location.href = href;
          }, 220);
        });
      });

      show(<?php echo json_encode($activeTab); ?>);
    })();
  </script>
</body>

</html>
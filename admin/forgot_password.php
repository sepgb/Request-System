<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: statistics.php');
    exit;
}

$error = '';
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $regKey   = trim($_POST['reg_key'] ?? '');
    $password = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $oldUsername = $username;

    $clientIp = getClientIp();

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Please try again.';
    } elseif (!checkRateLimit($conn, $clientIp, 5, 15)) {
        $error = 'Too many attempts from this connection. Please wait a few minutes and try again.';
    } elseif ($username === '' || $regKey === '' || $password === '' || $confirm === '') {
        $error = 'Fill in every field.';
    } elseif (strlen($password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif (!hash_equals(ADMIN_REG_KEY, $regKey)) {
        $error = 'That registration key is not valid. Ask the registrar for the current key.';
    } else {
        recordLookupAttempt($conn, $clientIp);

        $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $admin = $res->fetch_assoc();
            $stmt->close();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $update->bind_param('si', $hash, $admin['id']);
            $update->execute();
            $update->close();

            header('Location: login.php?reset=1');
            exit;
        } else {
            $stmt->close();
            // Same generic message whether the username exists or not,
            // so this form can't be used to enumerate valid usernames.
            $error = 'We could not reset that account. Check the username and registration key.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Document Request System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-body">
    <main class="auth-page">
        <div class="auth-shell auth-shell-signup">

            <section class="auth-card auth-card-wide" aria-labelledby="resetHeading">
                <h2 id="resetHeading" class="auth-card-title">Reset Password</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error auth-alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" autocomplete="off"
                            value="<?php echo htmlspecialchars($oldUsername); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_key">Registration key</label>
                        <input type="password" id="reg_key" name="reg_key" autocomplete="one-time-code" required>
                        <span class="auth-hint">The registrar's office issues this key to authorised staff.</span>
                    </div>

                    <div class="auth-row">
                        <div class="form-group">
                            <label for="new_password">New password</label>
                            <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm password</label>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">Reset Password</button>
                </form>

                <p class="auth-switch">Remembered it? <a href="login.php" class="auth-link" style="text-decoration:none;">Log in</a></p>
            </section>
        </div>
    </main>

    <script>
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
    </script>
</body>

</html>
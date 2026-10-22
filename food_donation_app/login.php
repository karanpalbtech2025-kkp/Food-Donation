<?php
require_once 'config.php';

// Set to true to diagnose "Invalid email or password". Set back to false when done.
const DEBUG_LOGIN = true;

$loginErr   = '';
$regSuccess = '';
$regErr     = '';

// ---------------------------------------------------------------
// Handle Login
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_login'])) {
    if (function_exists('verifyCSRFToken') && !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $loginErr = "CSRF verification failed.";
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        try {
            $stmt = $pdo->prepare("SELECT * FROM `USER` WHERE LOWER(TRIM(Email)) = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Use PasswordHash if it has a value, otherwise fall back to Password
                $dbPassword = !empty($user['PasswordHash'])
                    ? $user['PasswordHash']
                    : ($user['Password'] ?? '');

                // ---------- DEBUG BLOCK (remove after testing) ----------
                if (DEBUG_LOGIN) {
                    die(
                        'User found.<br>'
                        . 'Stored length: ' . strlen($dbPassword) . '<br>'
                        . 'Starts with: ' . htmlspecialchars(substr($dbPassword, 0, 7)) . '<br>'
                        . 'password_verify result: ' . (password_verify($password, $dbPassword) ? 'TRUE' : 'FALSE') . '<br>'
                        . 'Plain-text match: ' . ($password === $dbPassword ? 'TRUE' : 'FALSE')
                    );
                }
                // --------------------------------------------------------

                // Verify hashed password OR direct plain-text match
                $isVerified = password_verify($password, $dbPassword) || ($password === $dbPassword);

                if ($isVerified) {
                    $_SESSION['user_id']   = $user['UserID'];
                    $_SESSION['full_name'] = $user['FullName'];
                    $_SESSION['role']      = $user['Role'];

                    $roleUpper = strtoupper(trim($user['Role']));

                    // Default sub-role ID, overridden below if a child row exists
                    $_SESSION['sub_id'] = $user['UserID'];

                    if (strpos($roleUpper, 'DONOR') !== false) {
                        try {
                            $s = $pdo->prepare("SELECT DonorID FROM DONOR WHERE UserID = ?");
                            $s->execute([$user['UserID']]);
                            $subId = $s->fetchColumn();
                            if ($subId) $_SESSION['sub_id'] = $subId;
                        } catch (Exception $e) {}
                    } elseif (strpos($roleUpper, 'NGO') !== false || strpos($roleUpper, 'VOLUNTEER') !== false) {
                        try {
                            $s = $pdo->prepare("SELECT VolunteerID FROM NGO_VOLUNTEER WHERE UserID = ?");
                            $s->execute([$user['UserID']]);
                            $subId = $s->fetchColumn();
                            if ($subId) $_SESSION['sub_id'] = $subId;
                        } catch (Exception $e) {}
                    }

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $loginErr = "Invalid email or password.";
                }
            } else {
                if (DEBUG_LOGIN) {
                    die('No user found for email: ' . htmlspecialchars($email));
                }
                $loginErr = "Invalid email or password.";
            }
        } catch (Exception $e) {
            $loginErr = "Login error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ---------------------------------------------------------------
// Handle Registration
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    if (function_exists('verifyCSRFToken') && !verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $regErr = "CSRF verification failed.";
    } else {
        $fullName = function_exists('sanitize') ? sanitize($_POST['full_name'] ?? '') : trim($_POST['full_name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $phone    = function_exists('sanitize') ? sanitize($_POST['phone'] ?? '') : trim($_POST['phone'] ?? '');
        $password = password_hash(trim($_POST['password'] ?? ''), PASSWORD_BCRYPT);
        $role     = 'Donor';

        try {
            $checkStmt = $pdo->prepare("SELECT UserID FROM `USER` WHERE LOWER(TRIM(Email)) = ?");
            $checkStmt->execute([$email]);

            if ($checkStmt->fetch()) {
                $regErr = "An account with this email already exists.";
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO `USER` (FullName, Email, PasswordHash, PhoneNumber, Role) VALUES (?, ?, ?, ?, ?)");
                try {
                    $stmt->execute([$fullName, $email, $password, $phone, $role]);
                } catch (Exception $ex) {
                    // Fallback if the column is named Password instead of PasswordHash
                    $stmtAlt = $pdo->prepare("INSERT INTO `USER` (FullName, Email, Password, PhoneNumber, Role) VALUES (?, ?, ?, ?, ?)");
                    $stmtAlt->execute([$fullName, $email, $password, $phone, $role]);
                }

                $userId = $pdo->lastInsertId();

                $donorType = function_exists('sanitize') ? sanitize($_POST['donor_type'] ?? 'Individual') : 'Individual';
                $address   = function_exists('sanitize') ? sanitize($_POST['address'] ?? '') : '';
                $city      = function_exists('sanitize') ? sanitize($_POST['city'] ?? '') : '';

                $stmtD = $pdo->prepare("INSERT INTO DONOR (UserID, DonorType, Address, City) VALUES (?, ?, ?, ?)");
                $stmtD->execute([$userId, $donorType, $address, $city]);

                $pdo->commit();
                $regSuccess = "Donor account created successfully! You can now log in.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $regErr = "Registration failed: " . htmlspecialchars($e->getMessage());
        }
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-5">
    <div class="row justify-content-center g-4">
        <!-- Login Box -->
        <div class="col-md-5">
            <div class="card shadow border-0">
                <div class="card-header bg-success text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="fa-solid fa-lock me-2"></i>Login</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($loginErr): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($loginErr) ?></div>
                    <?php endif; ?>
                    <?php if ($regSuccess): ?>
                        <div class="alert alert-success py-2"><?= htmlspecialchars($regSuccess) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <?php if (function_exists('generateCSRFToken')): ?>
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="admin@foodshare.org" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="123456" required>
                        </div>
                        <button type="submit" name="action_login" class="btn btn-success w-100 fw-bold py-2">Log In</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Registration Box -->
        <div class="col-md-7">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Register as Donor</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($regErr): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($regErr) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" id="regForm">
                        <?php if (function_exists('generateCSRFToken')): ?>
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select" readonly>
                                    <option value="Donor" selected>Donor</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Donor Type</label>
                                <select name="donor_type" class="form-select">
                                    <option value="Individual">Individual</option>
                                    <option value="Restaurant">Restaurant</option>
                                    <option value="Hotel">Hotel</option>
                                    <option value="Corporate">Corporate</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control">
                            </div>
                        </div>

                        <button type="submit" name="action_register" class="btn btn-dark w-100 fw-bold py-2 mt-4">Create Donor Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

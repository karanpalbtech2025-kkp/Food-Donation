<?php
require_once 'config.php';
checkAuth();

$msg = '';$err = '';

// Handle New Donation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_donation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {$err = "Invalid Security Token";
    } else {
        $donorId =$_SESSION['sub_id'] ?? null;
        $categoryId = sanitize($_POST['category_id']);
        $foodName = sanitize($_POST['food_name']);
        $quantity = sanitize($_POST['quantity']);
        $unit = sanitize($_POST['unit']);
        $prepDate = sanitize($_POST['prep_date']);
        $expiryDate = sanitize($_POST['expiry_date']);
        $address = sanitize($_POST['pickup_address']);

        if (!$donorId && $_SESSION['role'] === 'Admin') {$err = "Admin cannot create donations without specifying a valid Donor record.";
        } else {
            try {
                $stmt =$pdo->prepare("INSERT INTO DONATION (DonorID, CategoryID, FoodName, Quantity, Unit, PreparationDate, ExpiryDate, PickupAddress, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt->execute([$donorId, $categoryId,$foodName, $quantity,$unit, $prepDate,$expiryDate, $address]);$msg = "Donation submitted successfully and is pending admin verification.";
            } catch (Exception $e) {$err = "Error creating donation: " . $e->getMessage();
            }
        }
    }
}

// Handle Admin Verification
if (isset($_GET['verify']) &&$_SESSION['role'] === 'Admin') {
    $donationId = (int)$_GET['verify'];
    $status = sanitize($_GET['status']);
    $adminId =$_SESSION['sub_id'] ?? null;

    try {
        $stmt =$pdo->prepare("UPDATE DONATION SET Status = ?, VerifiedBy = ? WHERE DonationID = ?");
        $stmt->execute([$status,$adminId, $donationId]);$msg = "Donation status updated to " . htmlspecialchars($status) . ".";
    } catch (Exception $e) {$err = "Error verifying donation: " . $e->getMessage();
    }
}

// Fetch Query based on user role
if ($_SESSION['role'] === 'Donor') {
    $stmt =$pdo->prepare("SELECT D.*, C.CategoryName FROM DONATION D JOIN FOOD_CATEGORY C ON D.CategoryID = C.CategoryID WHERE D.DonorID = ? ORDER BY D.DonationID DESC");
    $stmt->execute([$_SESSION['sub_id']]);
} else {
    $stmt =$pdo->prepare("SELECT D.*, C.CategoryName, U.FullName as DonorName FROM DONATION D JOIN FOOD_CATEGORY C ON D.CategoryID = C.CategoryID JOIN DONOR DN ON D.DonorID = DN.DonorID JOIN `USER` U ON DN.UserID = U.UserID ORDER BY D.DonationID DESC");
    $stmt->execute();
}
$donations =$stmt->fetchAll();
$categories =$pdo->query("SELECT * FROM FOOD_CATEGORY")->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fa-solid fa-boxes-stacked me-2"></i> Food Donations</h3>
        <?php if ($_SESSION['role'] === 'Donor'): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#donationModal"><i class="fa-solid fa-plus me-1"></i> Add Donation</button>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Food Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] === 'Admin'): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as$d): ?>
                        <tr>
                            <td>#<?= $d['DonationID'] ?></td>
                            <td><strong><?= sanitize($d['FoodName']) ?></strong></td>
                            <td><span class="badge bg-outline-info text-dark border"><?= sanitize($d['CategoryName']) ?></span></td>
                            <td><?= $d['Quantity'] . ' ' . sanitize($d['Unit']) ?></td>
                            <td><?= $d['ExpiryDate'] ?></td>
                            <td>
                                <span class="badge bg-<?= $d['Status'] === 'Approved' ? 'success' : ($d['Status'] === 'Pending' ? 'warning' : 'secondary') ?>">
                                    <?= sanitize($d['Status']) ?>
                                </span>
                            </td>
                            <?php if ($_SESSION['role'] === 'Admin'): ?>
                            <td>
                                <?php if ($d['Status'] === 'Pending'): ?>
                                    <a href="donations.php?verify=<?= $d['DonationID'] ?>&status=Approved" class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i> Approve</a>
                                    <a href="donations.php?verify=<?= $d['DonationID'] ?>&status=Rejected" class="btn btn-sm btn-danger"><i class="fa-solid fa-xmark"></i> Reject</a>
                                <?php else: ?>
                                    <span class="text-muted">Verified</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($donations)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No donations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Donors -->
<?php if ($_SESSION['role'] === 'Donor'): ?>
<div class="modal fade" id="donationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="donations.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">New Food Donation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Food Item Name</label>
                    <input type="text" name="food_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as$cat): ?>
                            <option value="<?= $cat['CategoryID'] ?>"><?= sanitize($cat['CategoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" step="0.01" name="quantity" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" placeholder="kg, meals" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Preparation Date</label>
                    <input type="datetime-local" name="prep_date" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expiry Date</label>
                    <input type="datetime-local" name="expiry_date" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Pickup Address</label>
                    <textarea name="pickup_address" class="form-control" rows="2" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="create_donation" class="btn btn-success fw-bold">Submit Donation</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
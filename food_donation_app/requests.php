<?php
require_once 'config.php';
checkAuth(['Admin', 'NGO_Volunteer']);

$msg = '';$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {$err = "Invalid Security Token";
    } else {
        $volId =$_SESSION['sub_id'] ?? null;
        $categoryId = sanitize($_POST['category_id']);
        $reqQty = sanitize($_POST['requested_quantity']);
        $benCount = sanitize($_POST['beneficiary_count']);
        $requiredBy = sanitize($_POST['required_by_date']);
        $targetLoc = sanitize($_POST['target_location']);

        try {
            $stmt =$pdo->prepare("INSERT INTO FOOD_REQUEST (VolunteerID, CategoryID, RequestedQuantity, BeneficiaryCount, RequiredByDate, TargetLocation, Status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$volId, $categoryId,$reqQty, $benCount,$requiredBy, $targetLoc]);$msg = "Food request submitted successfully.";
        } catch (Exception $e) {$err = "Error submitting request: " . $e->getMessage();
        }
    }
}

// Admin Request Approval/Rejection
if (isset($_GET['action_id']) &&$_SESSION['role'] === 'Admin') {
    $reqId = (int)$_GET['action_id'];
    $status = sanitize($_GET['status']);
    try {
        $stmt =$pdo->prepare("UPDATE FOOD_REQUEST SET Status = ? WHERE RequestID = ?");
        $stmt->execute([$status, $reqId]);$msg = "Request status updated to " . htmlspecialchars($status) . ".";
    } catch (Exception $e) {$err = "Error updating request: " . $e->getMessage();
    }
}

if ($_SESSION['role'] === 'NGO_Volunteer') {
    $stmt =$pdo->prepare("SELECT R.*, C.CategoryName FROM FOOD_REQUEST R JOIN FOOD_CATEGORY C ON R.CategoryID = C.CategoryID WHERE R.VolunteerID = ? ORDER BY R.RequestID DESC");
    $stmt->execute([$_SESSION['sub_id']]);
} else {
    $stmt =$pdo->prepare("SELECT R.*, C.CategoryName, U.FullName as VolunteerName FROM FOOD_REQUEST R JOIN FOOD_CATEGORY C ON R.CategoryID = C.CategoryID JOIN NGO_VOLUNTEER NV ON R.VolunteerID = NV.VolunteerID JOIN `USER` U ON NV.UserID = U.UserID ORDER BY R.RequestID DESC");
    $stmt->execute();
}
$requests =$stmt->fetchAll();
$categories =$pdo->query("SELECT * FROM FOOD_CATEGORY")->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fa-solid fa-clipboard-list me-2"></i> Food Requests</h3>
        <?php if ($_SESSION['role'] === 'NGO_Volunteer'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal"><i class="fa-solid fa-plus me-1"></i> Submit Request</button>
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
                            <th>Req ID</th>
                            <th>Category</th>
                            <th>Req Quantity</th>
                            <th>Beneficiaries</th>
                            <th>Required By</th>
                            <th>Target Location</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] === 'Admin'): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as$r): ?>
                        <tr>
                            <td>#<?= $r['RequestID'] ?></td>
                            <td><?= sanitize($r['CategoryName']) ?></td>
                            <td><?= $r['RequestedQuantity'] ?></td>
                            <td><?= $r['BeneficiaryCount'] ?> people</td>
                            <td><?= $r['RequiredByDate'] ?></td>
                            <td><?= sanitize($r['TargetLocation']) ?></td>
                            <td><span class="badge bg-<?= $r['Status'] === 'Approved' ? 'success' : ($r['Status'] === 'Pending' ? 'warning' : 'danger') ?>"><?= sanitize($r['Status']) ?></span></td>
                            <?php if ($_SESSION['role'] === 'Admin'): ?>
                            <td>
                                <?php if ($r['Status'] === 'Pending'): ?>
                                    <a href="requests.php?action_id=<?= $r['RequestID'] ?>&status=Approved" class="btn btn-sm btn-success">Approve</a>
                                    <a href="requests.php?action_id=<?= $r['RequestID'] ?>&status=Rejected" class="btn btn-sm btn-danger">Reject</a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Volunteers -->
<?php if ($_SESSION['role'] === 'NGO_Volunteer'): ?>
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="requests.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">New Food Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Category Needed</label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as$cat): ?>
                            <option value="<?= $cat['CategoryID'] ?>"><?= sanitize($cat['CategoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Requested Quantity</label>
                    <input type="number" step="0.01" name="requested_quantity" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Beneficiary Count</label>
                    <input type="number" name="beneficiary_count" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Required By Date</label>
                    <input type="date" name="required_by_date" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Target Location / Shelter Address</label>
                    <input type="text" name="target_location" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="create_request" class="btn btn-primary fw-bold">Submit Request</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
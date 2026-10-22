<?php
require_once 'config.php';
checkAuth(['Admin']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_allocation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = "Invalid Security Token";
    } else {
        $donationId = (int)$_POST['donation_id'];
        $requestId = (int)$_POST['request_id'];
        $allocQty = (float)$_POST['allocated_quantity'];

        try {
            $pdo->beginTransaction();
            // MySQL trigger trg_allocation_before_insert will execute automatically
            $stmt = $pdo->prepare("INSERT INTO ALLOCATION (DonationID, RequestID, AllocatedQuantity, AllocationDate) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$donationId, $requestId, $allocQty]);

            $pdo->commit();
            $msg = "Allocation created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = "Allocation Failed: " . $e->getMessage();
        }
    }
}

$allocations = $pdo->query("SELECT A.*, D.FoodName, R.TargetLocation FROM ALLOCATION A JOIN DONATION D ON A.DonationID = D.DonationID JOIN FOOD_REQUEST R ON A.RequestID = R.RequestID ORDER BY A.AllocationID DESC")->fetchAll();
$approvedDonations = $pdo->query("SELECT * FROM DONATION WHERE Status = 'Approved'")->fetchAll();
$approvedRequests = $pdo->query("SELECT * FROM FOOD_REQUEST WHERE Status = 'Approved'")->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fa-solid fa-diagram-project me-2"></i> Food Allocations</h3>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#allocModal"><i class="fa-solid fa-link me-1"></i> New Allocation</button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Alloc ID</th>
                            <th>Donated Item</th>
                            <th>Target Location</th>
                            <th>Allocated Quantity</th>
                            <th>Allocation Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allocations as $a): ?>
                        <tr>
                            <td>#<?= $a['AllocationID'] ?></td>
                            <td><?= sanitize($a['FoodName']) ?></td>
                            <td><?= sanitize($a['TargetLocation']) ?></td>
                            <td><strong><?= $a['AllocatedQuantity'] ?></strong></td>
                            <td><?= $a['AllocationDate'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allocations)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No allocations created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="allocModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="allocations.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Allocate Donation to Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Select Approved Donation</label>
                    <select name="donation_id" class="form-select" required>
                        <?php foreach ($approvedDonations as $d): ?>
                            <option value="<?= $d['DonationID'] ?>"><?= sanitize($d['FoodName']) ?> (Available: <?= $d['Quantity'] ?> <?= $d['Unit'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Select Approved Request</label>
                    <select name="request_id" class="form-select" required>
                        <?php foreach ($approvedRequests as $r): ?>
                            <option value="<?= $r['RequestID'] ?>">Request #<?= $r['RequestID'] ?> - <?= sanitize($r['TargetLocation']) ?> (Needed: <?= $r['RequestedQuantity'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Allocated Quantity</label>
                    <input type="number" step="0.01" name="allocated_quantity" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="create_allocation" class="btn btn-dark fw-bold">Execute Allocation</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
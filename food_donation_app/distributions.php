<?php
require_once 'config.php';
checkAuth();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_distribution'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = "Invalid Token";
    } else {
        $allocId = (int)$_POST['allocation_id'];
        $dispatchTime = !empty($_POST['dispatch_time']) ? date('Y-m-d H:i:s', strtotime($_POST['dispatch_time'])) : NULL;
        $deliveryTime = !empty($_POST['delivery_time']) ? date('Y-m-d H:i:s', strtotime($_POST['delivery_time'])) : NULL;
        $status = sanitize($_POST['distribution_status']);

        // Determine valid VolunteerID (Auto-fetch from Allocation/Request if logged in as Admin)
        $volId = $_SESSION['sub_id'] ?? null;

        // Verify if session sub_id is a valid VolunteerID
        $checkVol = $pdo->prepare("SELECT VolunteerID FROM NGO_VOLUNTEER WHERE VolunteerID = ?");
        $checkVol->execute([$volId]);
        $isValidVol = $checkVol->fetchColumn();

        if (!$isValidVol) {
            // Fetch the VolunteerID directly from the linked FOOD_REQUEST for this Allocation
            $fetchVol = $pdo->prepare("
                SELECT R.VolunteerID 
                FROM ALLOCATION A 
                JOIN FOOD_REQUEST R ON A.RequestID = R.RequestID 
                WHERE A.AllocationID = ?
            ");
            $fetchVol->execute([$allocId]);
            $volId = $fetchVol->fetchColumn();
        }

        if ($volId) {
            try {
                $stmt = $pdo->prepare("INSERT INTO DISTRIBUTION (AllocationID, VolunteerID, DispatchTime, DeliveryTime, DistributionStatus) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$allocId, $volId, $dispatchTime, $deliveryTime, $status]);
                $msg = "Distribution dispatch recorded successfully.";
            } catch (Exception $e) {
                $err = "Distribution Recording Failed: " . $e->getMessage();
            }
        } else {
            $err = "Distribution Recording Failed: Could not find a valid Volunteer associated with this Allocation.";
        }
    }
}

$distributions = $pdo->query("SELECT DS.*, D.FoodName, R.TargetLocation FROM DISTRIBUTION DS JOIN ALLOCATION A ON DS.AllocationID = A.AllocationID JOIN DONATION D ON A.DonationID = D.DonationID JOIN FOOD_REQUEST R ON A.RequestID = R.RequestID ORDER BY DS.DistributionID DESC")->fetchAll();
$unfulfilledAllocations = $pdo->query("SELECT A.*, D.FoodName FROM ALLOCATION A JOIN DONATION D ON A.DonationID = D.DonationID WHERE A.AllocationID NOT IN (SELECT AllocationID FROM DISTRIBUTION)")->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fa-solid fa-truck-ramp-box me-2"></i> Food Distributions</h3>
        <?php if (in_array($_SESSION['role'], ['Admin', 'NGO_Volunteer'])): ?>
            <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#distModal"><i class="fa-solid fa-plus me-1"></i> Record Distribution</button>
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
                            <th>Dist ID</th>
                            <th>Alloc ID</th>
                            <th>Food Name</th>
                            <th>Destination</th>
                            <th>Dispatch Time</th>
                            <th>Delivery Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distributions as $dist): ?>
                        <tr>
                            <td>#<?= $dist['DistributionID'] ?></td>
                            <td>#<?= $dist['AllocationID'] ?></td>
                            <td><?= sanitize($dist['FoodName']) ?></td>
                            <td><?= sanitize($dist['TargetLocation']) ?></td>
                            <td><?= $dist['DispatchTime'] ?></td>
                            <td><?= $dist['DeliveryTime'] ?></td>
                            <td><span class="badge bg-success"><?= sanitize($dist['DistributionStatus']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($distributions)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No distribution records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="distModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="distributions.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Record New Distribution</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Select Allocation</label>
                    <select name="allocation_id" class="form-select" required>
                        <?php foreach ($unfulfilledAllocations as $ua): ?>
                            <option value="<?= $ua['AllocationID'] ?>">Allocation #<?= $ua['AllocationID'] ?> - <?= sanitize($ua['FoodName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dispatch Time</label>
                    <input type="datetime-local" name="dispatch_time" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Delivery Time</label>
                    <input type="datetime-local" name="delivery_time" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Status</label>
                    <select name="distribution_status" class="form-select" required>
                        <option value="In Transit">In Transit</option>
                        <option value="Delivered">Delivered</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="record_distribution" class="btn btn-secondary fw-bold">Record Dispatch</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
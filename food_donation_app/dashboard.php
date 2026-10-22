<?php
require_once 'config.php';
checkAuth();

include 'includes/header.php';
include 'includes/navbar.php';

// Aggregate Statistics
$totalDonations =$pdo->query("SELECT COUNT(*) FROM `donation`")->fetchColumn();
$pendingDonations =$pdo->query("SELECT COUNT(*) FROM `donation` WHERE Status='Pending'")->fetchColumn();
$approvedDonations =$pdo->query("SELECT COUNT(*) FROM `donation` WHERE Status='Approved'")->fetchColumn();

$totalRequests =$pdo->query("SELECT COUNT(*) FROM `food_request`")->fetchColumn();
$pendingRequests =$pdo->query("SELECT COUNT(*) FROM `food_request` WHERE Status='Pending'")->fetchColumn();
$completedDistributions =$pdo->query("SELECT COUNT(*) FROM `distribution` WHERE DistributionStatus='Delivered'")->fetchColumn();

// Execute SQL Function if existing
$totalQuantity = 0;
try {
    $totalQuantity =$pdo->query("SELECT GetTotalDonatedFood()")->fetchColumn();
} catch (Exception $e) {
    $totalQuantity =$pdo->query("SELECT COALESCE(SUM(Quantity), 0) FROM `donation`")->fetchColumn();
}
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-gauge me-2"></i> System Dashboard</h2>
        <span class="badge bg-secondary fs-6">Role: <?= sanitize($_SESSION['role']) ?></span>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?= sanitize($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fa-solid fa-boxes-stacked display-6 mb-2"></i>
                    <h6>Total Donations</h6>
                    <h3 class="fw-bold mb-0"><?= $totalDonations ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-dark bg-warning shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fa-solid fa-clock-rotate-left display-6 mb-2"></i>
                    <h6>Pending Donations</h6>
                    <h3 class="fw-bold mb-0"><?= $pendingDonations ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fa-solid fa-check-circle display-6 mb-2"></i>
                    <h6>Approved Donations</h6>
                    <h3 class="fw-bold mb-0"><?= $approvedDonations ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fa-solid fa-scale-balanced display-6 mb-2"></i>
                    <h6>Total Food Vol (Units)</h6>
                    <h3 class="fw-bold mb-0"><?= $totalQuantity ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Hub -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header bg-white fw-bold"><i class="fa-solid fa-bolt me-2 text-warning"></i>Quick Actions</div>
        <div class="card-body d-flex flex-wrap gap-2">
            <?php if ($_SESSION['role'] === 'Donor'): ?>
                <a href="donations.php?action=create" class="btn btn-success"><i class="fa-solid fa-plus me-1"></i> Submit New Donation</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'NGO_Volunteer'): ?>
                <a href="requests.php?action=create" class="btn btn-primary"><i class="fa-solid fa-hand-holding me-1"></i> Request Available Food</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'Admin'): ?>
                <a href="donations.php" class="btn btn-outline-dark"><i class="fa-solid fa-clipboard-check me-1"></i> Verify Pending Donations</a>
                <a href="allocations.php" class="btn btn-outline-primary"><i class="fa-solid fa-diagram-project me-1"></i> Create Allocation</a>
            <?php endif; ?>
            <a href="distributions.php" class="btn btn-outline-secondary"><i class="fa-solid fa-truck me-1"></i> View Distribution Status</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
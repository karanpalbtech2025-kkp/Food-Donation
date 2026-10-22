<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-5">
    <div class="row align-items-center">
        <div class="col-md-7">
            <h1 class="display-4 fw-bold text-success mb-3">Reduce Food Waste, Feed Hungry Lives</h1>
            <p class="lead text-muted">A centralized platform connecting generous food donors, NGOs, and volunteers to seamlessly track, allocate, and distribute surplus food.</p>
            <hr class="my-4">
            <a href="login.php" class="btn btn-success btn-lg px-4 me-2"><i class="fa-solid fa-right-to-bracket me-2"></i> Access Platform</a>
        </div>
        <div class="col-md-5 text-center">
            <div class="card shadow border-0 p-4">
                <i class="fa-solid fa-hand-holding-heart text-success display-1 mb-3"></i>
                <h4 class="fw-bold">DBMS Project Portal</h4>
                <p class="text-secondary">Supported Roles: Admin, Donor, NGO Volunteer</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
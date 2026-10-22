<?php
$role = $_SESSION['role'] ?? 'Guest';
$fullName = $_SESSION['full_name'] ?? 'User';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-success" href="dashboard.php">
            <i class="fa-solid fa-hand-holding-heart me-2"></i>Food Share Portal
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isset($_SESSION['user_id'])): ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-chart-line me-1"></i> Dashboard</a>
                </li>
                <?php if ($role === 'Admin'): ?>
                <li class="nav-item"><a class="nav-link" href="categories.php"><i class="fa-solid fa-list me-1"></i> Categories</a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="fa-solid fa-users me-1"></i> Users</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="donations.php"><i class="fa-solid fa-box-tissue me-1"></i> Donations</a></li>
                <?php if (in_array($role, ['Admin', 'NGO_Volunteer'])): ?>
                <li class="nav-item"><a class="nav-link" href="requests.php"><i class="fa-solid fa-clipboard-list me-1"></i> Requests</a></li>
                <?php endif; ?>
                <?php if ($role === 'Admin'): ?>
                <li class="nav-item"><a class="nav-link" href="allocations.php"><i class="fa-solid fa-diagram-project me-1"></i> Allocations</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="distributions.php"><i class="fa-solid fa-truck-ramp-box me-1"></i> Distributions</a></li>
            </ul>
            <div class="d-flex align-items-center text-white">
                <span class="me-3"><i class="fa-solid fa-user-circle me-1"></i> <?= sanitize($fullName) ?> (<strong><?= sanitize($role) ?></strong>)</span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
            <?php else: ?>
            <div class="ms-auto">
                <a href="login.php" class="btn btn-success btn-sm"><i class="fa-solid fa-right-to-bracket"></i> Login / Register</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
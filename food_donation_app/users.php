<?php
require_once 'config.php';
checkAuth(['Admin']);

$users =$pdo->query("SELECT U.UserID, U.FullName, U.Email, U.PhoneNumber, U.Role, U.CreatedAt, A.AdminID, DN.DonorID, NV.VolunteerID FROM `USER` U LEFT JOIN ADMIN A ON U.UserID = A.UserID LEFT JOIN DONOR DN ON U.UserID = DN.UserID LEFT JOIN NGO_VOLUNTEER NV ON U.UserID = NV.UserID ORDER BY U.UserID DESC")->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-4">
    <h3 class="mb-3"><i class="fa-solid fa-users me-2"></i> Registered System Users</h3>
    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Sub ID</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as$u): ?>
                        <tr>
                            <td>#<?= $u['UserID'] ?></td>
                            <td><strong><?= sanitize($u['FullName']) ?></strong></td>
                            <td><?= sanitize($u['Email']) ?></td>
                            <td><?= sanitize($u['PhoneNumber']) ?></td>
                            <td><span class="badge bg-secondary"><?= sanitize($u['Role']) ?></span></td>
                            <td>
                                <?= $u['AdminID'] ? 'Admin #' . $u['AdminID'] : ($u['DonorID'] ? 'Donor #' . $u['DonorID'] : ($u['VolunteerID'] ? 'Volunteer #' . $u['VolunteerID'] : '-')) ?>
                            </td>
                            <td><?= $u['CreatedAt'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
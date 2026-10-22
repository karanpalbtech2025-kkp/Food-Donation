<?php
require_once 'config.php';
checkAuth(['Admin']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = "CSRF Token Validation Failed";
    } else {
        $catName = sanitize($_POST['category_name']);
        $instructions = sanitize($_POST['storage_instructions']);

        try {
            $stmt = $pdo->prepare("INSERT INTO FOOD_CATEGORY (CategoryName, StorageInstructions) VALUES (?, ?)");
            $stmt->execute([$catName, $instructions]);
            $msg = "Category added successfully.";
        } catch (Exception $e) {
            $err = "Failed to add category: " . $e->getMessage();
        }
    }
}

$categories = $pdo->query("SELECT * FROM FOOD_CATEGORY ORDER BY CategoryID DESC")->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fa-solid fa-list me-2"></i> Food Categories</h3>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#catModal"><i class="fa-solid fa-plus me-1"></i> Add Category</button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

    <div class="card shadow border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Category ID</th>
                        <th>Category Name</th>
                        <th>Storage Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td>#<?= $c['CategoryID'] ?></td>
                        <td><strong><?= sanitize($c['CategoryName']) ?></strong></td>
                        <td><?= sanitize($c['StorageInstructions']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="categories.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">New Food Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="category_name" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Storage Instructions</label>
                    <textarea name="storage_instructions" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_category" class="btn btn-dark fw-bold">Save Category</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
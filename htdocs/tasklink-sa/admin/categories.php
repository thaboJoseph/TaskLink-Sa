<?php
$page_title = 'Manage Categories';
require_once '../includes/db.php';
require_once 'includes/header.php';

$success = '';
$error   = '';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['action']) && 
    $_POST['action'] === 'add') {
    $name = trim($_POST['category_name'] ?? '');
    if (empty($name)) {
        $error = 'Category name cannot be empty.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT category_id FROM categories 
             WHERE category_name = ?"
        );
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $error = 'That category already exists.';
        } else {
            $pdo->prepare(
                "INSERT INTO categories (category_name) VALUES (?)"
            )->execute([$name]);
            $success = "Category '$name' added successfully.";
        }
    }
}

// Delete category
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        $pdo->prepare(
            "DELETE FROM categories WHERE category_id = ?"
        )->execute([$del_id]);
        $success = 'Category deleted.';
    } catch(Exception $e) {
        $error = 'Cannot delete — services are using this category.';
    }
}

// Edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['action']) && 
    $_POST['action'] === 'edit') {
    $edit_id   = (int)$_POST['edit_id'];
    $edit_name = trim($_POST['edit_name'] ?? '');
    if (!empty($edit_name)) {
        $pdo->prepare(
            "UPDATE categories SET category_name = ? 
             WHERE category_id = ?"
        )->execute([$edit_name, $edit_id]);
        $success = 'Category updated.';
    }
}

// Get all categories with service count
$categories = $pdo->query("
    SELECT c.*, COUNT(s.service_id) as service_count
    FROM categories c
    LEFT JOIN services s ON s.category_id = c.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name
")->fetchAll();
?>

<?php if($success): ?>
    <div class="alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:300px 1fr; gap:24px;">

    <!-- Add Category Form -->
    <div>
        <div class="admin-table-wrap">
            <div class="admin-table-header">
                <h2>Add New Category</h2>
            </div>
            <div style="padding:20px;">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name"
                               placeholder="e.g. Pest Control"
                               required>
                    </div>
                    <button type="submit" class="btn-primary"
                            style="width:100%;">
                        + Add Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="admin-table-wrap">
        <div class="admin-table-header">
            <h2>All Categories</h2>
            <span style="font-size:13px; color:#718096;">
                <?php echo count($categories); ?> categories
            </span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Services</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categories as $cat): ?>
                <tr>
                    <td style="color:#A0AEC0;">
                        #<?php echo $cat['category_id']; ?>
                    </td>
                    <td>
                        <form method="POST" action="" 
                              style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="edit_id" 
                                   value="<?php echo $cat['category_id']; ?>">
                            <input type="text" name="edit_name"
                                   value="<?php echo htmlspecialchars($cat['category_name']); ?>"
                                   style="padding:6px 10px; border:1px solid #E2E8F0; 
                                          border-radius:6px; font-size:13px; width:180px;">
                            <button type="submit" class="btn-sm btn-green">
                                Save
                            </button>
                        </form>
                    </td>
                    <td style="text-align:center;">
                        <span style="background:#E8F5EE; color:#1B6B3A; 
                                     padding:3px 10px; border-radius:12px; 
                                     font-size:12px; font-weight:600;">
                            <?php echo $cat['service_count']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($cat['service_count'] == 0): ?>
                            <a href="?delete=<?php echo $cat['category_id']; ?>"
                               class="btn-sm btn-danger"
                               onclick="return confirm('Delete this category?')">
                                Delete
                            </a>
                        <?php else: ?>
                            <span style="font-size:11px; color:#A0AEC0;">
                                In use
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
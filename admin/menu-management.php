<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session-check.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/images/menu/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imagePath = 'assets/images/menu/' . $fileName;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, category, image, dietary_info, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'] ?? '',
            $_POST['description'] ?? '',
            $_POST['price'] ?? 0,
            $_POST['category'] ?? '',
            $imagePath,
            $_POST['dietary_info'] ?? '',
            isset($_POST['is_available']) ? 1 : 0,
        ]);
    } elseif ($action === 'edit') {
        $itemId = (int) ($_POST['id'] ?? 0);
        $imagePath = $_POST['current_image'] ?? '';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/images/menu/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                if (!empty($_POST['current_image'])) {
                    $oldImagePath = __DIR__ . '/../' . $_POST['current_image'];
                    if (is_file($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $imagePath = 'assets/images/menu/' . $fileName;
            }
        }

        $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, image = ?, dietary_info = ?, is_available = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'] ?? '',
            $_POST['description'] ?? '',
            $_POST['price'] ?? 0,
            $_POST['category'] ?? '',
            $imagePath,
            $_POST['dietary_info'] ?? '',
            isset($_POST['is_available']) ? 1 : 0,
            $itemId,
        ]);
    } elseif ($action === 'delete') {
        $itemId = (int) ($_POST['id'] ?? 0);

        $imageStmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
        $imageStmt->execute([$itemId]);
        $existingItem = $imageStmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($existingItem['image'])) {
            $imageFilePath = __DIR__ . '/../' . $existingItem['image'];
            if (is_file($imageFilePath)) {
                unlink($imageFilePath);
            }
        }

        $deleteStmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $deleteStmt->execute([$itemId]);
    }

    header('Location: menu-management.php');
    exit();
}

$categories = [
    'Entrees',
    'Mains',
    'Burgers',
    'Sides',
    'Kids',
    'Desserts',
    'Drinks',
];

$editItem = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $editStmt->execute([(int) $_GET['edit']]);
    $editItem = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$menuItemsStmt = $pdo->query("SELECT * FROM menu_items ORDER BY category ASC, name ASC");
$menuItemsRaw = $menuItemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$menuItems = [];

foreach ($categories as $category) {
    $menuItems[$category] = [];
}

foreach ($menuItemsRaw as $item) {
    $category = $item['category'] ?? 'Uncategorized';
    if (!isset($menuItems[$category])) {
        $menuItems[$category] = [];
    }
    $menuItems[$category][] = $item;
}

$adminPageTitle = 'Menu Management';
$adminPageIcon = 'fa-utensils';
$adminNotificationCount = 0;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'menu';
$adminSidebarPathPrefix = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/admin-head.php'; ?>
    <style>
        :root {
            --mn-bg: var(--dm-bg);
            --mn-surface: var(--dm-surface);
            --mn-line: var(--dm-border);
            --mn-text: var(--dm-text);
            --mn-muted: var(--dm-text-muted);
            --mn-navy: var(--dm-accent-dark);
            --mn-radius: var(--dm-radius-md);
            --mn-shadow: var(--dm-shadow-sm);
        }
        * { box-sizing: border-box; }
        body { background: var(--mn-bg); color: var(--mn-text); }
        .admin-container { padding: 24px; }
        .mn-page-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
        .mn-page-header h1 { font-size: 20px; font-weight: 700; margin: 0; color: var(--mn-text); }
        /* Form card */
        .menu-form-container { background: var(--mn-surface); border: 1px solid var(--mn-line); border-radius: var(--mn-radius); padding: 20px 24px; margin-bottom: 20px; box-shadow: var(--mn-shadow); }
        .menu-form-container h3 { font-size: 14px; font-weight: 700; color: var(--mn-muted); text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 16px; }
        .menu-form { display: grid; gap: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: 600; color: var(--mn-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .form-group input, .form-group select, .form-group textarea { padding: 9px 10px; border: 1px solid var(--dm-border-strong); border-radius: var(--dm-radius-sm); font-size: 13px; font-family: inherit; color: var(--mn-text); background: #ffffff; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #bdc9da; box-shadow: var(--dm-focus-ring); }
        .form-group textarea { resize: vertical; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; }
        .form-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        /* Category blocks */
        .menu-category { background: var(--mn-surface); border: 1px solid var(--mn-line); border-radius: var(--mn-radius); padding: 20px 24px; margin-bottom: 16px; box-shadow: var(--mn-shadow); }
        .menu-category h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--mn-muted); margin: 0 0 16px; padding-bottom: 10px; border-bottom: 1px solid var(--mn-line); }
        .no-items { color: var(--mn-muted); font-size: 13px; font-style: italic; }
        .menu-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
        /* Item cards */
        .menu-item-card { border: 1px solid var(--mn-line); border-radius: var(--dm-radius-sm); overflow: hidden; background: #ffffff; }
        .menu-item-card.unavailable { opacity: 0.6; }
        .item-image { height: 140px; overflow: hidden; }
        .item-image img { width: 100%; height: 100%; object-fit: cover; }
        .item-details { padding: 12px; }
        .item-details h4 { margin: 0 0 6px; font-size: 14px; font-weight: 600; color: var(--mn-text); }
        .description { color: var(--mn-muted); margin: 0 0 10px; font-size: 13px; }
        .item-footer { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .price { font-weight: 700; color: var(--mn-text); font-size: 14px; }
        .dietary { background: #f0f4fa; padding: 2px 7px; border-radius: 4px; font-size: 11px; color: var(--mn-muted); }
        .item-actions { padding: 8px 12px; background: var(--dm-surface-muted); border-top: 1px solid var(--mn-line); display: flex; gap: 8px; justify-content: flex-end; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .admin-container { padding: 16px; } .menu-items-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__ . '/admin-sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>

        <div class="admin-container">
            <div class="mn-page-header">
                <h1>Menu Management</h1>
                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Analytics</a>
            </div>

            <!-- Add/Edit Form -->
            <div class="menu-form-container">
        <h3><?php echo $editItem ? 'Edit Menu Item' : 'Add New Menu Item'; ?></h3>
        <form method="POST" enctype="multipart/form-data" class="menu-form">
            <input type="hidden" name="action" value="<?php echo $editItem ? 'edit' : 'add'; ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                <input type="hidden" name="current_image" value="<?php echo $editItem['image']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Item Name *</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo $editItem ? htmlspecialchars($editItem['name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="price">Price *</label>
                    <input type="number" id="price" name="price" step="0.01" required
                           value="<?php echo $editItem ? $editItem['price'] : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category; ?>"
                                <?php echo ($editItem && $editItem['category'] === $category) ? 'selected' : ''; ?>>
                            <?php echo $category; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo $editItem ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="dietary_info">Dietary Info</label>
                    <input type="text" id="dietary_info" name="dietary_info"
                           value="<?php echo $editItem ? htmlspecialchars($editItem['dietary_info']) : ''; ?>"
                           placeholder="e.g., V, GF, V GF">
                </div>
                <div class="form-group">
                    <label for="image">Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <?php if ($editItem && !empty($editItem['image'])): ?>
                        <div class="current-image">
                            <img src="../<?php echo $editItem['image']; ?>" alt="Current image" style="max-width: 100px; margin-top: 5px;">
                            <small>Leave empty to keep current image</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_available" <?php echo (!$editItem || $editItem['is_available']) ? 'checked' : ''; ?>>
                    Available
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $editItem ? 'Update Item' : 'Add Item'; ?>
                </button>
                <?php if ($editItem): ?>
                    <a href="menu-management.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

            <!-- Menu Items Display -->
            <?php foreach ($menuItems as $category => $items): ?>
                <div class="menu-category">
                    <h3><?php echo $category; ?> (<?php echo count($items); ?> items)</h3>
                    <?php if (empty($items)): ?>
                        <p class="no-items">No items in this category.</p>
                    <?php else: ?>
                        <div class="menu-items-grid">
                            <?php foreach ($items as $item): ?>
                                <div class="menu-item-card <?php echo !$item['is_available'] ? 'unavailable' : ''; ?>">
                                    <?php if (!empty($item['image'])): ?>
                                        <div class="item-image">
                                            <img src="../<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="item-footer">
                                            <span class="price">$<?php echo number_format($item['price'], 2); ?></span>
                                            <?php if (!empty($item['dietary_info'])): ?>
                                                <span class="dietary"><?php echo htmlspecialchars($item['dietary_info']); ?></span>
                                            <?php endif; ?>
                                            <span class="status-tag <?php echo $item['is_available'] ? 'available' : 'unavailable'; ?>">
                                                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="item-actions">
                                        <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
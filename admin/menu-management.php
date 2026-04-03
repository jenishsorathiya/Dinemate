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
<html>
<head>
    <title>Menu Management | DineMate Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard-theme.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .admin-container {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background-color: #f8f9fa;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border: 1px solid #e7ecf3;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15,23,42,0.06);
        }

        .admin-header h1 {
            color: #2c3e50;
            margin: 0;
        }

        .menu-form-container {
            background: white;
            padding: 25px;
            border: 1px solid #e7ecf3;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15,23,42,0.06);
            margin-bottom: 30px;
        }

        .menu-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal !important;
        }

        .form-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
        }

        .btn-edit:hover {
            background: #e67e22;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .menu-category {
            background: white;
            padding: 25px;
            border: 1px solid #e7ecf3;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15,23,42,0.06);
            margin-bottom: 20px;
        }

        .menu-category h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .no-items {
            color: #7f8c8d;
            font-style: italic;
        }

        .menu-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-item-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            transition: all 0.3s;
        }

        .menu-item-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .menu-item-card.unavailable {
            opacity: 0.6;
            border-color: #e74c3c;
        }

        .item-image {
            height: 150px;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            padding: 15px;
        }

        .item-details h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .description {
            color: #7f8c8d;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price {
            font-weight: bold;
            color: #27ae60;
            font-size: 16px;
        }

        .dietary {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            color: #2c3e50;
        }

        .status {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .status.available {
            background: #d4edda;
            color: #155724;
        }

        .status.unavailable {
            background: #f8d7da;
            color: #721c24;
        }

        .item-actions {
            padding: 10px 15px;
            background: #f8f9fa;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 88px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .admin-container {
                padding: 20px;
            }

            .admin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .menu-items-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__ . '/admin-sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>

        <div class="admin-container">
            <div class="admin-header">
                <h1><i class="fas fa-utensils"></i> Menu Management</h1>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Analytics
                </a>
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
                        <p class="no-items">No items in this category yet.</p>
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
                                            <span class="status <?php echo $item['is_available'] ? 'available' : 'unavailable'; ?>">
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
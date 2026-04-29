<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session-check.php';

requireAdmin();

$resolveMenuImageUrl = static function ($imagePath): string {
    $path = trim((string) $imagePath);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $path) || stripos($path, 'data:') === 0) {
        return $path;
    }

    if ($path[0] === '/') {
        return $path;
    }

    $normalizedPath = preg_replace('#^(?:\.\.?/)+#', '', $path);
    return appPath($normalizedPath ?: $path);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $imagePath = '';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/images/menu/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imagePath = 'assets/images/menu/' . $fileName;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO menu_items (name, description, price, category, image, dietary_info, is_available)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
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
            $uploadDir = __DIR__ . '/../../assets/images/menu/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                if (!empty($_POST['current_image'])) {
                    $oldImagePath = __DIR__ . '/../../' . $_POST['current_image'];
                    if (is_file($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $imagePath = 'assets/images/menu/' . $fileName;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE menu_items
            SET name = ?, description = ?, price = ?, category = ?, image = ?, dietary_info = ?, is_available = ?
            WHERE id = ?
        ");
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
            $imageFilePath = __DIR__ . '/../../' . $existingItem['image'];
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

$menuItemsByCategory = [];
foreach ($categories as $category) {
    $menuItemsByCategory[$category] = [];
}
foreach ($menuItemsRaw as $item) {
    $category = $item['category'] ?? 'Uncategorized';
    if (!isset($menuItemsByCategory[$category])) {
        $menuItemsByCategory[$category] = [];
    }
    $menuItemsByCategory[$category][] = $item;
}

$totalItems = count($menuItemsRaw);
$availableItems = count(array_filter($menuItemsRaw, fn($item) => (int) $item['is_available'] === 1));
$unavailableItems = $totalItems - $availableItems;

$adminPageTitle = 'Menu';
$adminPageIcon = 'fa-utensils';
$adminNotificationCount = 0;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'menu';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <style>
        :root {
            --mn-bg: var(--dm-bg);
            --mn-surface: var(--dm-surface);
            --mn-surface-muted: var(--dm-surface-muted);
            --mn-line: var(--dm-border);
            --mn-line-strong: var(--dm-border-strong);
            --mn-text: var(--dm-text);
            --mn-muted: var(--dm-text-muted);
            --mn-soft: var(--dm-text-soft);
            --mn-accent: var(--dm-accent-dark);
            --mn-radius: var(--dm-radius-md);
            --mn-radius-sm: var(--dm-radius-sm);
            --mn-shadow: var(--dm-shadow-sm);
            --mn-shadow-lg: var(--dm-shadow-md);
        }

        * { box-sizing: border-box; }

        body {
            background: var(--mn-bg);
            color: var(--mn-text);
        }

        .admin-container {
            padding: 24px;
        }

        .mn-shell {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .mn-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .mn-header-copy h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--mn-text);
        }

        .mn-header-copy p {
            margin: 6px 0 0;
            color: var(--mn-muted);
            font-size: 14px;
        }

        .mn-header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mn-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .mn-stat {
            background: var(--mn-surface);
            border: 1px solid var(--mn-line);
            border-radius: 12px;
            box-shadow: var(--mn-shadow);
            padding: 16px 18px;
        }

        .mn-stat-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--mn-soft);
        }

        .mn-stat-value {
            margin-top: 6px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--mn-text);
        }

        .mn-stat-note {
            margin-top: 4px;
            font-size: 12px;
            color: var(--mn-muted);
        }

        .mn-toolbar {
            background: var(--mn-surface);
            border: 1px solid var(--mn-line);
            border-radius: 12px;
            box-shadow: var(--mn-shadow);
            padding: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .mn-toolbar-left,
        .mn-toolbar-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .mn-search {
            min-width: 260px;
        }

        .mn-select,
        .mn-search-input {
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--mn-line-strong);
            background: var(--mn-surface);
            color: var(--mn-text);
            padding: 0 12px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .mn-select:focus,
        .mn-search-input:focus {
            border-color: var(--mn-accent);
            box-shadow: var(--dm-focus-ring);
        }

        .mn-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
            align-items: start;
        }

        .mn-main {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .mn-panel {
            background: var(--mn-surface);
            border: 1px solid var(--mn-line);
            border-radius: 14px;
            box-shadow: var(--mn-shadow-lg);
        }

        .mn-panel-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--mn-line);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .mn-panel-title-wrap h2,
        .mn-panel-title-wrap h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--mn-text);
        }

        .mn-panel-title-wrap p {
            margin: 4px 0 0;
            color: var(--mn-muted);
            font-size: 13px;
        }

        .mn-panel-body {
            padding: 18px 20px 20px;
        }

        .mn-category-section {
            display: none;
        }

        .mn-category-section.is-visible {
            display: block;
        }

        .mn-category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .mn-category-title {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--mn-muted);
        }

        .mn-category-count {
            font-size: 12px;
            color: var(--mn-soft);
        }

        .mn-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        .mn-item {
            display: flex;
            flex-direction: column;
            border: 1px solid var(--mn-line);
            border-radius: 12px;
            overflow: hidden;
            background: var(--mn-surface);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .mn-item:hover {
            transform: translateY(-1px);
            border-color: rgba(17, 24, 39, 0.14);
            box-shadow: var(--mn-shadow);
        }

        .mn-item.is-hidden {
            display: none;
        }

        .mn-item.unavailable {
            opacity: 0.68;
        }

        .mn-item-image {
            height: 160px;
            background: var(--mn-surface-muted);
            overflow: hidden;
        }

        .mn-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mn-item-image-placeholder {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: var(--mn-soft);
            font-size: 13px;
            background:
                linear-gradient(180deg, rgba(0,0,0,0.015), rgba(0,0,0,0.03));
        }

        .mn-item-body {
            padding: 14px 14px 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }

        .mn-item-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .mn-item-name {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--mn-text);
            line-height: 1.3;
        }

        .mn-price {
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            color: var(--mn-text);
        }

        .mn-description {
            margin: 0;
            color: var(--mn-muted);
            font-size: 13px;
            line-height: 1.5;
            min-height: 38px;
        }

        .mn-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mn-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: var(--mn-surface-muted);
            color: var(--mn-muted);
            border: 1px solid rgba(17, 24, 39, 0.06);
        }

        .mn-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 2px;
        }

        .mn-empty {
            background: var(--mn-surface);
            border: 1px dashed var(--mn-line-strong);
            border-radius: 12px;
            padding: 28px 20px;
            text-align: center;
            color: var(--mn-muted);
            font-size: 14px;
        }

        .mn-form-panel {
            position: sticky;
            top: 24px;
        }

        .mn-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .mn-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .mn-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .mn-form-group label {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--mn-muted);
        }

        .mn-form-group input,
        .mn-form-group select,
        .mn-form-group textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--mn-line-strong);
            background: var(--mn-surface);
            color: var(--mn-text);
            padding: 10px 12px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .mn-form-group input:focus,
        .mn-form-group select:focus,
        .mn-form-group textarea:focus {
            border-color: var(--mn-accent);
            box-shadow: var(--dm-focus-ring);
        }

        .mn-form-group textarea {
            resize: vertical;
            min-height: 108px;
        }

        .mn-current-image {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
            padding: 10px;
            border-radius: 10px;
            background: var(--mn-surface-muted);
            border: 1px solid var(--mn-line);
        }

        .mn-current-image img {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--mn-line);
        }

        .mn-current-image small {
            display: block;
            color: var(--mn-muted);
            line-height: 1.4;
        }

        .mn-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--mn-text);
            font-weight: 500;
        }

        .mn-form-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 4px;
        }

        .mn-helper {
            color: var(--mn-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .mn-inline-note {
            font-size: 12px;
            color: var(--mn-muted);
        }

        @media (max-width: 1200px) {
            .mn-layout {
                grid-template-columns: 1fr;
            }

            .mn-form-panel {
                position: static;
            }
        }

        @media (max-width: 900px) {
            .mn-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 16px;
            }

            .mn-header {
                align-items: stretch;
            }

            .mn-header-actions,
            .mn-toolbar-left,
            .mn-toolbar-right {
                width: 100%;
            }

            .mn-search {
                min-width: unset;
                width: 100%;
            }

            .mn-search-input {
                width: 100%;
            }

            .mn-form-grid {
                grid-template-columns: 1fr;
            }

            .mn-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .mn-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="admin-container">
            <div class="mn-shell">

                <div class="mn-header">
                    <div class="mn-header-copy">
                        <h1>Menu</h1>
                        <p>Manage dishes, pricing, categories, and availability across your restaurant menu.</p>
                    </div>

                    <div class="mn-header-actions">
                        <a href="analytics.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Analytics
                        </a>
                        <?php if ($editItem): ?>
                            <a href="menu-management.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Dish
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mn-stats">
                    <div class="mn-stat">
                        <div class="mn-stat-label">Total Items</div>
                        <div class="mn-stat-value"><?php echo $totalItems; ?></div>
                        <div class="mn-stat-note">All menu items currently listed</div>
                    </div>

                    <div class="mn-stat">
                        <div class="mn-stat-label">Available</div>
                        <div class="mn-stat-value"><?php echo $availableItems; ?></div>
                        <div class="mn-stat-note">Currently visible for service</div>
                    </div>

                    <div class="mn-stat">
                        <div class="mn-stat-label">Unavailable</div>
                        <div class="mn-stat-value"><?php echo $unavailableItems; ?></div>
                        <div class="mn-stat-note">Temporarily hidden or out of stock</div>
                    </div>

                    <div class="mn-stat">
                        <div class="mn-stat-label">Categories</div>
                        <div class="mn-stat-value"><?php echo count($categories); ?></div>
                        <div class="mn-stat-note">Structured menu sections</div>
                    </div>
                </div>

                <div class="mn-toolbar">
                    <div class="mn-toolbar-left">
                        <div class="mn-search">
                            <input
                                type="text"
                                id="menuSearch"
                                class="mn-search-input"
                                placeholder="Search dishes, dietary tags, or descriptions..."
                            >
                        </div>

                        <select id="categoryFilter" class="mn-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="availabilityFilter" class="mn-select">
                            <option value="all">All Status</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                    </div>

                    <div class="mn-toolbar-right">
                        <span class="mn-inline-note" id="resultsSummary">
                            Showing <?php echo $totalItems; ?> items
                        </span>
                    </div>
                </div>

                <div class="mn-layout">
                    <div class="mn-main">
                        <div class="mn-panel">
                            <div class="mn-panel-header">
                                <div class="mn-panel-title-wrap">
                                    <h2>Menu Items</h2>
                                    <p>Browse items by category and quickly update availability, pricing, or dish details.</p>
                                </div>
                            </div>

                            <div class="mn-panel-body">
                                <?php foreach ($menuItemsByCategory as $category => $items): ?>
                                    <section
                                        class="mn-category-section is-visible"
                                        data-category-section="<?php echo htmlspecialchars($category); ?>"
                                    >
                                        <div class="mn-category-header">
                                            <h3 class="mn-category-title"><?php echo htmlspecialchars($category); ?></h3>
                                            <span class="mn-category-count">
                                                <?php echo count($items); ?> item<?php echo count($items) === 1 ? '' : 's'; ?>
                                            </span>
                                        </div>

                                        <?php if (empty($items)): ?>
                                            <div class="mn-empty">No items have been added to this category yet.</div>
                                        <?php else: ?>
                                            <div class="mn-grid">
                                                <?php foreach ($items as $item): ?>
                                                    <?php
                                                    $itemName = htmlspecialchars($item['name']);
                                                    $itemDescription = htmlspecialchars($item['description'] ?? '');
                                                    $itemDietary = htmlspecialchars($item['dietary_info'] ?? '');
                                                    $itemCategory = htmlspecialchars($item['category'] ?? '');
                                                    $itemAvailability = (int) $item['is_available'] === 1 ? 'available' : 'unavailable';
                                                    ?>
                                                    <article
                                                        class="mn-item <?php echo $itemAvailability === 'unavailable' ? 'unavailable' : ''; ?>"
                                                        data-name="<?php echo strtolower(trim($item['name'] ?? '')); ?>"
                                                        data-description="<?php echo strtolower(trim($item['description'] ?? '')); ?>"
                                                        data-dietary="<?php echo strtolower(trim($item['dietary_info'] ?? '')); ?>"
                                                        data-category="<?php echo strtolower(trim($item['category'] ?? '')); ?>"
                                                        data-availability="<?php echo $itemAvailability; ?>"
                                                    >
                                                        <div class="mn-item-image">
                                                            <?php if (!empty($item['image'])): ?>
                                                                <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $itemName; ?>">
                                                            <?php else: ?>
                                                                <div class="mn-item-image-placeholder">No image uploaded</div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="mn-item-body">
                                                            <div class="mn-item-head">
                                                                <h4 class="mn-item-name"><?php echo $itemName; ?></h4>
                                                                <div class="mn-price">$<?php echo number_format((float) $item['price'], 2); ?></div>
                                                            </div>

                                                            <p class="mn-description">
                                                                <?php echo $itemDescription !== '' ? $itemDescription : 'No description added yet.'; ?>
                                                            </p>

                                                            <div class="mn-meta">
                                                                <?php if (!empty($item['dietary_info'])): ?>
                                                                    <span class="mn-chip"><?php echo $itemDietary; ?></span>
                                                                <?php endif; ?>

                                                                <span class="status-tag <?php echo $itemAvailability === 'available' ? 'available' : 'unavailable'; ?>">
                                                                    <?php echo $itemAvailability === 'available' ? 'Available' : 'Unavailable'; ?>
                                                                </span>

                                                                <span class="mn-chip"><?php echo $itemCategory; ?></span>
                                                            </div>

                                                            <div class="mn-actions">
                                                                <a href="?edit=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-edit">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </a>

                                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-delete">
                                                                        <i class="fas fa-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </section>
                                <?php endforeach; ?>

                                <div class="mn-empty" id="noResultsMessage" style="display:none; margin-top: 4px;">
                                    No menu items match your current search or filters.
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="mn-form-panel">
                        <div class="mn-panel">
                            <div class="mn-panel-header">
                                <div class="mn-panel-title-wrap">
                                    <h3><?php echo $editItem ? 'Edit Dish' : 'New Dish'; ?></h3>
                                    <p>
                                        <?php echo $editItem
                                            ? 'Update pricing, description, image, or availability for this dish.'
                                            : 'Add a new menu item with category, dietary details, and availability.'; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="mn-panel-body">
                                <form method="POST" enctype="multipart/form-data" class="mn-form">
                                    <input type="hidden" name="action" value="<?php echo $editItem ? 'edit' : 'add'; ?>">

                                    <?php if ($editItem): ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $editItem['id']; ?>">
                                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($editItem['image'] ?? ''); ?>">
                                    <?php endif; ?>

                                    <div class="mn-form-group">
                                        <label for="name">Dish Name</label>
                                        <input
                                            type="text"
                                            id="name"
                                            name="name"
                                            required
                                            value="<?php echo $editItem ? htmlspecialchars($editItem['name']) : ''; ?>"
                                            placeholder="e.g. Truffle Mushroom Burger"
                                        >
                                    </div>

                                    <div class="mn-form-grid">
                                        <div class="mn-form-group">
                                            <label for="price">Price</label>
                                            <input
                                                type="number"
                                                id="price"
                                                name="price"
                                                step="0.01"
                                                required
                                                value="<?php echo $editItem ? htmlspecialchars((string) $editItem['price']) : ''; ?>"
                                                placeholder="0.00"
                                            >
                                        </div>

                                        <div class="mn-form-group">
                                            <label for="category">Category</label>
                                            <select id="category" name="category" required>
                                                <option value="">Select category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option
                                                        value="<?php echo htmlspecialchars($category); ?>"
                                                        <?php echo ($editItem && ($editItem['category'] ?? '') === $category) ? 'selected' : ''; ?>
                                                    >
                                                        <?php echo htmlspecialchars($category); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mn-form-group">
                                        <label for="description">Description</label>
                                        <textarea
                                            id="description"
                                            name="description"
                                            placeholder="Write a short menu description for guests."
                                        ><?php echo $editItem ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
                                    </div>

                                    <div class="mn-form-grid">
                                        <div class="mn-form-group">
                                            <label for="dietary_info">Dietary Info</label>
                                            <input
                                                type="text"
                                                id="dietary_info"
                                                name="dietary_info"
                                                value="<?php echo $editItem ? htmlspecialchars($editItem['dietary_info']) : ''; ?>"
                                                placeholder="e.g. V, GF, DF"
                                            >
                                            <div class="mn-helper">Use short dietary tags separated by commas or spaces.</div>
                                        </div>

                                        <div class="mn-form-group">
                                            <label for="image">Dish Image</label>
                                            <input type="file" id="image" name="image" accept="image/*">
                                            <div class="mn-helper">Upload a new image to replace the current one.</div>

                                            <?php if ($editItem && !empty($editItem['image'])): ?>
                                                <div class="mn-current-image">
                                                    <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($editItem['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="Current image">
                                                    <div>
                                                        <small>Current image attached.</small>
                                                        <small>Leave this field empty to keep it.</small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mn-form-group">
                                        <label class="mn-checkbox">
                                            <input
                                                type="checkbox"
                                                name="is_available"
                                                <?php echo (!$editItem || (int) $editItem['is_available'] === 1) ? 'checked' : ''; ?>
                                            >
                                            Mark as available
                                        </label>
                                    </div>

                                    <div class="mn-form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i>
                                            <?php echo $editItem ? 'Update Dish' : 'Add Dish'; ?>
                                        </button>

                                        <?php if ($editItem): ?>
                                            <a href="menu-management.php" class="btn btn-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const searchInput = document.getElementById('menuSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const availabilityFilter = document.getElementById('availabilityFilter');
        const items = Array.from(document.querySelectorAll('.mn-item'));
        const categorySections = Array.from(document.querySelectorAll('[data-category-section]'));
        const noResultsMessage = document.getElementById('noResultsMessage');
        const resultsSummary = document.getElementById('resultsSummary');

        function normalize(value) {
            return (value || '').toString().trim().toLowerCase();
        }

        function applyFilters() {
            const query = normalize(searchInput.value);
            const selectedCategory = normalize(categoryFilter.value);
            const selectedAvailability = normalize(availabilityFilter.value);

            let visibleCount = 0;

            items.forEach(item => {
                const name = normalize(item.dataset.name);
                const description = normalize(item.dataset.description);
                const dietary = normalize(item.dataset.dietary);
                const category = normalize(item.dataset.category);
                const availability = normalize(item.dataset.availability);

                const matchesQuery =
                    !query ||
                    name.includes(query) ||
                    description.includes(query) ||
                    dietary.includes(query);

                const matchesCategory =
                    selectedCategory === 'all' || category === selectedCategory;

                const matchesAvailability =
                    selectedAvailability === 'all' || availability === selectedAvailability;

                const isVisible = matchesQuery && matchesCategory && matchesAvailability;
                item.classList.toggle('is-hidden', !isVisible);

                if (isVisible) {
                    visibleCount++;
                }
            });

            categorySections.forEach(section => {
                const visibleItems = section.querySelectorAll('.mn-item:not(.is-hidden)').length;
                section.classList.toggle('is-visible', visibleItems > 0);
            });

            noResultsMessage.style.display = visibleCount === 0 ? 'block' : 'none';
            resultsSummary.textContent = `Showing ${visibleCount} item${visibleCount === 1 ? '' : 's'}`;
        }

        searchInput.addEventListener('input', applyFilters);
        categoryFilter.addEventListener('change', applyFilters);
        availabilityFilter.addEventListener('change', applyFilters);

        applyFilters();
    })();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




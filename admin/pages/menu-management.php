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

$categories = [
    'Entrees',
    'Mains',
    'Burgers',
    'Sides',
    'Kids',
    'Desserts',
    'Drinks',
];

$menuCsrfToken = csrfToken('admin_actions');
$menuUploadDir = __DIR__ . '/../../assets/images/menu/';
$menuUploadPrefix = 'assets/images/menu/';

$resolveStoredMenuImagePath = static function ($imagePath) use ($menuUploadDir): ?string {
    $path = trim((string) $imagePath);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || stripos($path, 'data:') === 0) {
        return null;
    }

    $normalizedPath = preg_replace('#^(?:\.\.?/)+#', '', $path);
    if (strpos($normalizedPath, 'assets/images/menu/') !== 0) {
        return null;
    }

    $uploadRoot = realpath($menuUploadDir);
    $candidate = realpath(__DIR__ . '/../../' . $normalizedPath);
    if (!$uploadRoot || !$candidate) {
        return null;
    }

    $uploadRoot = rtrim(str_replace('\\', '/', $uploadRoot), '/') . '/';
    $candidate = str_replace('\\', '/', $candidate);

    return strpos($candidate, $uploadRoot) === 0 ? $candidate : null;
};

$handleMenuImageUpload = static function (array $file) use ($menuUploadDir, $menuUploadPrefix): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['', null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['', 'Image upload failed. Please choose another file.'];
    }

    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        return ['', 'Image files must be 4 MB or smaller.'];
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $imageInfo = $tmpPath !== '' ? @getimagesize($tmpPath) : false;
    $mimeType = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    if (!isset($extensions[$mimeType])) {
        return ['', 'Please upload a JPG, PNG, WebP, or AVIF image.'];
    }

    if (!is_dir($menuUploadDir) && !mkdir($menuUploadDir, 0755, true)) {
        return ['', 'Image upload directory could not be prepared.'];
    }

    $fileName = bin2hex(random_bytes(12)) . '.' . $extensions[$mimeType];
    $targetPath = $menuUploadDir . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return ['', 'Image upload failed. Please try again.'];
    }

    return [$menuUploadPrefix . $fileName, null];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireValidCsrfToken('admin_actions', ['redirect' => appPath('admin/pages/menu-management.php')]);

    $action = (string) $_POST['action'];
    $redirectUrl = 'menu-management.php';

    try {
        if (in_array($action, ['add', 'edit'], true)) {
            $itemId = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = (float) ($_POST['price'] ?? 0);
            $category = trim((string) ($_POST['category'] ?? ''));
            $dietaryInfo = trim((string) ($_POST['dietary_info'] ?? ''));
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;

            if ($action === 'edit' && $itemId > 0) {
                $redirectUrl = 'menu-management.php?edit=' . $itemId;
            }

            if ($name === '' || strlen($name) > 120) {
                throw new RuntimeException('Dish name is required and must be 120 characters or fewer.');
            }

            if ($category === '' || !in_array($category, $categories, true)) {
                throw new RuntimeException('Please choose a valid menu category.');
            }

            if ($price < 0 || $price > 9999) {
                throw new RuntimeException('Please enter a valid price.');
            }

            if (strlen($description) > 1000 || strlen($dietaryInfo) > 180) {
                throw new RuntimeException('Description or dietary information is too long.');
            }

            [$uploadedImagePath, $uploadError] = $handleMenuImageUpload($_FILES['image'] ?? []);
            if ($uploadError !== null) {
                throw new RuntimeException($uploadError);
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO menu_items (name, description, price, category, image, dietary_info, is_available)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    $description,
                    $price,
                    $category,
                    $uploadedImagePath,
                    $dietaryInfo,
                    $isAvailable,
                ]);
                setFlashMessage('success', 'Menu item added.');
            } else {
                if ($itemId < 1) {
                    throw new RuntimeException('Menu item not found.');
                }

                $imageStmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
                $imageStmt->execute([$itemId]);
                $existingItem = $imageStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existingItem) {
                    throw new RuntimeException('Menu item not found.');
                }

                $imagePath = $uploadedImagePath !== '' ? $uploadedImagePath : (string) ($existingItem['image'] ?? '');
                $stmt = $pdo->prepare("
                    UPDATE menu_items
                    SET name = ?, description = ?, price = ?, category = ?, image = ?, dietary_info = ?, is_available = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $description,
                    $price,
                    $category,
                    $imagePath,
                    $dietaryInfo,
                    $isAvailable,
                    $itemId,
                ]);

                if ($uploadedImagePath !== '') {
                    $oldImagePath = $resolveStoredMenuImagePath($existingItem['image'] ?? '');
                    if ($oldImagePath && is_file($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                setFlashMessage('success', 'Menu item updated.');
                $redirectUrl = 'menu-management.php';
            }
        } elseif ($action === 'delete') {
            $itemId = (int) ($_POST['id'] ?? 0);
            if ($itemId < 1) {
                throw new RuntimeException('Menu item not found.');
            }

            $imageStmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
            $imageStmt->execute([$itemId]);
            $existingItem = $imageStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {
                $imageFilePath = $resolveStoredMenuImagePath($existingItem['image'] ?? '');
                if ($imageFilePath && is_file($imageFilePath)) {
                    unlink($imageFilePath);
                }
            }

            $deleteStmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
            $deleteStmt->execute([$itemId]);
            setFlashMessage('success', 'Menu item deleted.');
        }
    } catch (RuntimeException $e) {
        setFlashMessage('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Menu management action failed: ' . $e->getMessage());
        setFlashMessage('error', 'Menu action failed. Please try again.');
    }

    header('Location: ' . $redirectUrl);
    exit();
}

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
$flash = getFlashMessage();

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
    <?php include __DIR__ . '/../partials/admin-modernize.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/pages/admin-menu.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="admin-container">
            <div class="mn-shell admin-workspace">

                <header class="admin-page-heading">
                    <div>
                        <p class="admin-page-kicker">Menu Operations</p>
                        <h1 class="admin-page-title">Menu</h1>
                        <p class="admin-page-copy">Keep the public menu accurate: update pricing, availability, categories, dietary tags, and item photography.</p>
                    </div>

                    <div class="admin-actions">
                        <span class="admin-chip is-success"><?php echo number_format($availableItems); ?> available</span>
                        <span class="admin-chip <?php echo $unavailableItems > 0 ? 'is-warning' : ''; ?>"><?php echo number_format($unavailableItems); ?> unavailable</span>
                        <?php if ($editItem): ?>
                            <a href="menu-management.php" class="primary-btn">
                                <i class="bi bi-plus-lg"></i> Add New Dish
                            </a>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if ($flash): ?>
                    <div class="admin-empty" role="status">
                        <?php echo htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="admin-command-bar">
                    <div class="admin-command-group">
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

                    <div class="admin-command-group">
                        <span class="admin-command-note" id="resultsSummary">
                            Showing <?php echo number_format($totalItems); ?> items across <?php echo number_format(count($categories)); ?> categories
                        </span>
                    </div>
                </div>

                <div class="mn-layout">
                    <div class="mn-main">
                        <div class="admin-panel mn-panel">
                            <div class="admin-panel-header mn-panel-header">
                                <div class="mn-panel-title-wrap">
                                    <h2 class="admin-panel-title">Menu Items</h2>
                                    <p class="admin-panel-copy">Searchable working list for service changes and dish maintenance.</p>
                                </div>
                            </div>

                            <div class="admin-panel-body mn-panel-body">
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
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($menuCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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
                        <div class="admin-panel mn-panel">
                            <div class="admin-panel-header mn-panel-header">
                                <div class="mn-panel-title-wrap">
                                    <h3 class="admin-panel-title"><?php echo $editItem ? 'Edit Dish' : 'New Dish'; ?></h3>
                                    <p class="admin-panel-copy">
                                        <?php echo $editItem
                                            ? 'Update pricing, description, image, or availability for this dish.'
                                            : 'Add a new menu item with category, dietary details, and availability.'; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="admin-panel-body mn-panel-body">
                                <form method="POST" enctype="multipart/form-data" class="mn-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($menuCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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




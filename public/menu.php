<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

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

// Fetch menu items grouped by category
$categories = ['Small Plates', 'Large Plates', 'House Specials', 'Burgers', 'Sides', 'Kiddies', 'Desserts'];
$menuItems = [];

foreach ($categories as $category) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE category = ? AND is_available = 1 ORDER BY name");
    $stmt->execute([$category]);
    $menuItems[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Menu | DineMate';
$extraStylesheets = ['assets/css/pages/menu.css'];
include __DIR__ . '/../includes/header.php';
?>


<div class="menu-container">
    <div class="menu-header">
        <h1>Our Menu</h1>
        <p>Experience fine dining at The Old Canberra Inn</p>
    </div>

    <?php foreach ($menuItems as $category => $items): ?>
        <?php if (!empty($items)): ?>
            <section class="menu-section">
                <h2 class="section-title"><?php echo $category; ?></h2>
                <?php if ($category === 'Burgers'): ?>
                    <p class="section-subtitle">Served on a milk bun with a choice of chips or salad</p>
                <?php elseif ($category === 'Sides'): ?>
                    <p class="section-subtitle">All $11 as Small Plates</p>
                <?php elseif ($category === 'Kiddies'): ?>
                    <p class="section-subtitle">Served with your choice of chips or salad</p>
                <?php endif; ?>

                <?php if ($category === 'Sides'): ?>
                    <div class="menu-cards">
                        <?php foreach ($items as $item): ?>
                            <div class="menu-card">
                                <?php if (!empty($item['image'])): ?>
                                    <div class="card-image">
                                        <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <span class="price">$<?php echo number_format($item['price'] ?: 11, 2); ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['dietary_info'])): ?>
                                        <span class="badge"><?php echo htmlspecialchars($item['dietary_info']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="sauces-note"><strong>Sauces:</strong> Mushroom, Peppercorn, Gravy, Chimichurri, Café De Paris Butter</p>
                <?php else: ?>
                    <div class="menu-cards">
                        <?php foreach ($items as $item): ?>
                            <div class="menu-card <?php echo ($category === 'House Specials') ? 'featured' : ''; ?>">
                                <?php if (!empty($item['image'])): ?>
                                    <div class="card-image">
                                        <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <span class="price">$<?php echo number_format($item['price'], 2); ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['dietary_info'])): ?>
                                        <span class="badge"><?php echo htmlspecialchars($item['dietary_info']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- LEGEND -->
    <div class="menu-legend">
        <p><span class="badge-info">V</span> Vegan | <span class="badge-info">GF</span> Gluten Free</p>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>

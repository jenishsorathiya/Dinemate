<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../config/db.php';

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
?>

<style>
.menu-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    background-color: transparent;
}

.menu-header {
    text-align: center;
    margin-bottom: 50px;
    padding: 30px 0;
    border-bottom: 3px solid var(--dm-accent-dark);
}

.menu-header h1 {
    font-size: 3em;
    color: var(--dm-text);
    margin: 0 0 10px 0;
    font-weight: 700;
    letter-spacing: 2px;
}

.menu-header p {
    font-size: 1.2em;
    color: var(--dm-text-muted);
    margin: 0;
    font-style: italic;
}

.menu-section {
    margin-bottom: 60px;
}

.section-title {
    font-size: 2.2em;
    color: var(--dm-accent-dark);
    text-align: center;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    padding-bottom: 15px;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 2px;
    background-color: var(--dm-accent-dark);
}

.section-subtitle {
    text-align: center;
    color: var(--dm-text-muted);
    font-style: italic;
    margin: -5px 0 25px 0;
}

.menu-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 20px;
}

.menu-card {
    background: white;
    border: 1px solid var(--dm-border);
    border-radius: 10px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
    overflow: hidden;
}

.menu-card.featured {
    border-left: 3px solid var(--dm-accent-dark);
    background: var(--dm-surface-muted);
}

.card-image {
    height: 200px;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-content {
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.card-header h3 {
    margin: 0;
    color: var(--dm-text);
    font-size: 1.3em;
    flex: 1;
    font-weight: 600;
}

.price {
    color: var(--dm-accent-dark);
    font-size: 1.4em;
    font-weight: 700;
    white-space: nowrap;
    margin-left: 15px;
}

.description {
    color: var(--dm-text-muted);
    font-size: 0.95em;
    margin: 8px 0 10px 0;
    line-height: 1.4;
}

.badge {
    display: inline-block;
    background-color: var(--dm-border);
    color: var(--dm-accent-dark);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 8px;
}

.sides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.side-item {
    background: white;
    padding: 15px;
    border: 1px solid var(--dm-border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(15,23,42,0.05);
}

.side-item h4 {
    margin: 0;
    color: var(--dm-text);
    font-size: 1.1em;
}

.sauces-note {
    text-align: center;
    color: var(--dm-text-muted);
    font-size: 0.95em;
    margin-top: 15px;
}

.menu-legend {
    text-align: center;
    padding: 20px;
    color: var(--dm-text-muted);
    font-size: 0.95em;
    border-top: 1px solid var(--dm-border);
    margin-top: 40px;
}

.badge-info {
    font-weight: 600;
    color: var(--dm-accent-dark);
}

@media (max-width: 768px) {
    .menu-header h1 {
        font-size: 2em;
    }

    .section-title {
        font-size: 1.6em;
    }

    .menu-cards {
        grid-template-columns: 1fr;
    }

    .sides-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

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
                    <div class="sides-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="side-item">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
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
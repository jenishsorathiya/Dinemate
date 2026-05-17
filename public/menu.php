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

$categories = ['Small Plates', 'Large Plates', 'House Specials', 'Burgers', 'Sides', 'Kiddies', 'Desserts'];
$menuItems = [];

foreach ($categories as $category) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE category = ? AND is_available = 1 ORDER BY name");
    $stmt->execute([$category]);
    $menuItems[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Menu | DineMate';
include __DIR__ . '/../includes/header.php';
?>

<main class="guest-main">
    <section class="guest-page-hero" style="--guest-hero-image: url('<?= htmlspecialchars(appPath('assets/images/editorial/menu-hero.jpg'), ENT_QUOTES, 'UTF-8') ?>'); --guest-hero-position: center;">
        <div class="guest-hero-inner">
            <p class="guest-kicker">Menu</p>
            <h1 class="guest-page-title">Browse first. Book when it feels right.</h1>
            <p class="guest-page-copy">Take a look at the food, choose your favourites, then reserve a table for the people you want around it.</p>
            <div class="guest-action-row">
                <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">Book a Table</a>
                <a class="guest-button-outline" href="<?= htmlspecialchars(appPath('public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Ask a Question</a>
            </div>
        </div>
    </section>

    <section class="guest-section is-paper">
        <div class="guest-container guest-content-band">
            <div>
                <p class="guest-section-kicker">Old Canberra Inn</p>
                <h2 class="guest-section-title">Comfort food, shared plates, cold drinks, and good company.</h2>
                <p class="guest-section-copy">From pub favourites to plates made for sharing, the menu is built for relaxed meals that can stretch into the evening.</p>
            </div>
            <div class="guest-proof-list">
                <article>
                    <span><i class="fa fa-utensils"></i></span>
                    <div>
                        <h3>Browse by category</h3>
                        <p>Move from small plates to mains, burgers, sides, and desserts.</p>
                    </div>
                </article>
                <article>
                    <span><i class="fa fa-leaf"></i></span>
                    <div>
                        <h3>Dietary notes</h3>
                        <p>Look for item notes, then add any special requests when booking.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <div class="menu-container">
        <div class="menu-header">
            <p class="guest-section-kicker">Food & Drinks</p>
            <h1>Menu</h1>
            <p>Browse what is available today, then book a table when you are ready.</p>
        </div>

        <?php foreach ($menuItems as $category => $items): ?>
            <?php if (!empty($items)): ?>
                <section class="menu-section">
                    <h2 class="section-title"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php if ($category === 'Burgers'): ?>
                        <p class="section-subtitle">Served on a milk bun with a choice of chips or salad.</p>
                    <?php elseif ($category === 'Sides'): ?>
                        <p class="section-subtitle">Built for sharing or adding to the table.</p>
                    <?php elseif ($category === 'Kiddies'): ?>
                        <p class="section-subtitle">Smaller plates for younger guests.</p>
                    <?php endif; ?>

                    <div class="menu-cards">
                        <?php foreach ($items as $item): ?>
                            <article class="menu-card <?php echo ($category === 'House Specials') ? 'featured' : ''; ?>">
                                <?php if (!empty($item['image'])): ?>
                                    <div class="card-image">
                                        <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <span class="price">$<?php echo number_format((float) ($item['price'] ?: ($category === 'Sides' ? 11 : 0)), 2); ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="description"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['dietary_info'])): ?>
                                        <span class="badge"><?php echo htmlspecialchars($item['dietary_info'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="menu-legend">
            <p><span class="badge-info">V</span> Vegan | <span class="badge-info">GF</span> Gluten Free</p>
        </div>
    </div>

    <section class="guest-section is-green">
        <div class="guest-container guest-cta-panel">
            <div>
                <p class="guest-section-kicker">Ready to eat?</p>
                <h2 class="guest-section-title">Turn the menu into a reservation.</h2>
                <p class="guest-section-copy">Choose a time, add any dietary notes, and let the team prepare for your table.</p>
            </div>
            <div class="guest-inline-actions">
                <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">Book a Table</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

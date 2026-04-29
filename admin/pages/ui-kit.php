<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

$adminPageTitle = 'UI Kit';
$adminPageIcon = 'fa-palette';
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'ui-kit';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <div class="main-content">
            <main class="admin-container">
                <div class="ui-kit-shell">
                    <section class="ui-kit-header">
                        <div>
                            <h1 class="ui-kit-title">DineMate UI Kit</h1>
                            <p class="ui-kit-subtitle">Reusable components for forms, actions, status, and dashboard blocks.</p>
                        </div>
                        <span class="ui-chip ui-chip-neutral">Version 1.0</span>
                    </section>

                    <nav class="ui-breadcrumb" aria-label="Breadcrumb">
                        <ol class="ui-breadcrumb-list">
                            <li class="ui-breadcrumb-item"><a href="../pages/analytics.php">Admin</a></li>
                            <li class="ui-breadcrumb-item"><a href="../pages/bookings-management.php">Bookings</a></li>
                            <li class="ui-breadcrumb-item" aria-current="page">UI Kit</li>
                        </ol>
                    </nav>

                    <section class="ui-panel">
                        <h2 class="ui-panel-title">Buttons and Status Chips</h2>
                        <div class="ui-row">
                            <button type="button" class="ui-button ui-button-primary">Primary Action</button>
                            <button type="button" class="ui-button ui-button-secondary">Secondary</button>
                            <button type="button" class="ui-button ui-button-ghost">Ghost</button>
                            <button type="button" class="ui-button ui-button-danger">Delete</button>
                        </div>
                        <div class="ui-row mt-3">
                            <span class="ui-chip ui-chip-neutral">Draft</span>
                            <span class="ui-chip ui-chip-success">Confirmed</span>
                            <span class="ui-chip ui-chip-warning">Pending</span>
                            <span class="ui-chip ui-chip-danger">Cancelled</span>
                        </div>
                    </section>

                    <section class="ui-kit-grid">
                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Form Controls</h2>
                            <div class="ui-stack">
                                <label class="ui-field">
                                    <span class="ui-label">Guest Name</span>
                                    <input class="ui-input" type="text" placeholder="Enter full name">
                                </label>
                                <label class="ui-field">
                                    <span class="ui-label">Booking Area</span>
                                    <select class="ui-select">
                                        <option>Main Floor</option>
                                        <option>Courtyard</option>
                                        <option>Private Room</option>
                                    </select>
                                </label>
                                <label class="ui-field">
                                    <span class="ui-label">Notes</span>
                                    <textarea class="ui-textarea" placeholder="Add booking notes"></textarea>
                                </label>
                                <span class="ui-help">Tip: add keyboard hints with <span class="ui-kbd">Ctrl</span> + <span class="ui-kbd">S</span>.</span>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Feedback Alerts</h2>
                            <div class="ui-stack">
                                <div class="ui-alert ui-alert-info">Table layout updated successfully.</div>
                                <div class="ui-alert ui-alert-success">Booking confirmed and guest notified.</div>
                                <div class="ui-alert ui-alert-danger">Unable to assign this booking to selected tables.</div>
                            </div>
                        </article>
                    </section>

                    <section class="ui-panel">
                        <h2 class="ui-panel-title">Stat Cards</h2>
                        <div class="ui-stat-grid">
                            <article class="ui-stat">
                                <div class="ui-stat-label">Today Bookings</div>
                                <div class="ui-stat-value">42</div>
                                <div class="ui-stat-note">+12% from yesterday</div>
                            </article>
                            <article class="ui-stat">
                                <div class="ui-stat-label">Avg Party Size</div>
                                <div class="ui-stat-value">3.8</div>
                                <div class="ui-stat-note">Based on last 7 days</div>
                            </article>
                            <article class="ui-stat">
                                <div class="ui-stat-label">No-show Rate</div>
                                <div class="ui-stat-value">6%</div>
                                <div class="ui-stat-note">Within target range</div>
                            </article>
                        </div>
                    </section>

                    <section class="ui-kit-grid">
                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Tabs</h2>
                            <div class="ui-tabs" data-ui-tabs>
                                <div class="ui-tab-list" role="tablist" aria-label="Booking views">
                                    <button type="button" class="ui-tab is-active" id="tab-bookings" role="tab" aria-selected="true" aria-controls="panel-bookings">Bookings</button>
                                    <button type="button" class="ui-tab" id="tab-analytics" role="tab" aria-selected="false" aria-controls="panel-analytics" tabindex="-1">Analytics</button>
                                    <button type="button" class="ui-tab" id="tab-settings" role="tab" aria-selected="false" aria-controls="panel-settings" tabindex="-1">Settings</button>
                                </div>
                                <div class="ui-tab-panel" id="panel-bookings" role="tabpanel" aria-labelledby="tab-bookings">
                                    <strong>Bookings</strong>
                                    <p class="ui-muted">Review table requests, confirmations, and guest notes.</p>
                                </div>
                                <div class="ui-tab-panel" id="panel-analytics" role="tabpanel" aria-labelledby="tab-analytics" hidden>
                                    <strong>Analytics</strong>
                                    <p class="ui-muted">Track covers, no-shows, and busy dining windows.</p>
                                </div>
                                <div class="ui-tab-panel" id="panel-settings" role="tabpanel" aria-labelledby="tab-settings" hidden>
                                    <strong>Settings</strong>
                                    <p class="ui-muted">Tune booking limits, seating areas, and availability rules.</p>
                                </div>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Dropdowns and Menus</h2>
                            <div class="ui-row">
                                <details class="ui-dropdown">
                                    <summary class="ui-button ui-button-secondary">Booking actions <i class="fa fa-chevron-down" aria-hidden="true"></i></summary>
                                    <div class="ui-menu" role="menu">
                                        <button type="button" class="ui-menu-item" role="menuitem"><i class="fa fa-pen" aria-hidden="true"></i>Edit booking</button>
                                        <button type="button" class="ui-menu-item" role="menuitem"><i class="fa fa-check" aria-hidden="true"></i>Confirm guest</button>
                                        <button type="button" class="ui-menu-item is-danger" role="menuitem"><i class="fa fa-trash" aria-hidden="true"></i>Delete</button>
                                    </div>
                                </details>
                                <details class="ui-dropdown">
                                    <summary class="ui-button ui-button-ghost">Filter <i class="fa fa-filter" aria-hidden="true"></i></summary>
                                    <div class="ui-menu" role="menu">
                                        <label class="ui-menu-item"><input type="checkbox" checked> Confirmed</label>
                                        <label class="ui-menu-item"><input type="checkbox"> Pending</label>
                                        <label class="ui-menu-item"><input type="checkbox"> Cancelled</label>
                                    </div>
                                </details>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Tooltips</h2>
                            <div class="ui-row">
                                <span class="ui-tooltip-trigger">
                                    <button type="button" class="ui-button ui-button-secondary" aria-describedby="tip-capacity">
                                        <i class="fa fa-circle-info" aria-hidden="true"></i>Capacity
                                    </button>
                                    <span class="ui-tooltip" id="tip-capacity" role="tooltip">Shows seats already assigned against the service limit.</span>
                                </span>
                                <span class="ui-tooltip-trigger">
                                    <button type="button" class="ui-button ui-button-ghost" aria-describedby="tip-status">
                                        <i class="fa fa-question" aria-hidden="true"></i>Status
                                    </button>
                                    <span class="ui-tooltip" id="tip-status" role="tooltip">Pending bookings need staff review before the guest is notified.</span>
                                </span>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Progress Bars</h2>
                            <div class="ui-stack">
                                <div>
                                    <div class="ui-progress-meta"><span>Booking setup</span><strong>66%</strong></div>
                                    <div class="ui-progress" aria-label="Booking setup progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="66" role="progressbar">
                                        <span class="ui-progress-bar" style="width: 66%;"></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="ui-progress-meta"><span>Dining room capacity</span><strong>82%</strong></div>
                                    <div class="ui-progress ui-progress-warning" aria-label="Dining room capacity" aria-valuemin="0" aria-valuemax="100" aria-valuenow="82" role="progressbar">
                                        <span class="ui-progress-bar" style="width: 82%;"></span>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Avatars</h2>
                            <div class="ui-row">
                                <span class="ui-avatar ui-avatar-sm" aria-label="Maya Chen">MC</span>
                                <span class="ui-avatar" aria-label="Jordan Lee">JL</span>
                                <span class="ui-avatar ui-avatar-lg" aria-label="Sam Patel">SP</span>
                                <div class="ui-avatar-group" aria-label="Assigned team">
                                    <span class="ui-avatar">AM</span>
                                    <span class="ui-avatar">RK</span>
                                    <span class="ui-avatar">+3</span>
                                </div>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Pagination</h2>
                            <nav class="ui-pagination" aria-label="Bookings pagination">
                                <a class="ui-page-link" href="#" aria-label="Previous page"><i class="fa fa-chevron-left" aria-hidden="true"></i></a>
                                <a class="ui-page-link is-active" href="#" aria-current="page">1</a>
                                <a class="ui-page-link" href="#">2</a>
                                <a class="ui-page-link" href="#">3</a>
                                <span class="ui-page-link is-disabled">...</span>
                                <a class="ui-page-link" href="#">8</a>
                                <a class="ui-page-link" href="#" aria-label="Next page"><i class="fa fa-chevron-right" aria-hidden="true"></i></a>
                            </nav>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Loading Indicators</h2>
                            <div class="ui-row">
                                <span class="ui-spinner" aria-label="Loading"></span>
                                <button type="button" class="ui-button ui-button-primary"><span class="ui-spinner ui-spinner-sm" aria-hidden="true"></span>Saving</button>
                            </div>
                            <div class="ui-loading-overlay mt-3" aria-busy="true">
                                <span class="ui-spinner"></span>
                                <span>Loading analytics</span>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Accordions</h2>
                            <div class="ui-accordion">
                                <details class="ui-accordion-item" open>
                                    <summary class="ui-accordion-toggle">Booking details <i class="fa fa-chevron-down" aria-hidden="true"></i></summary>
                                    <div class="ui-accordion-content">Party of four, courtyard preferred, celebrating an anniversary.</div>
                                </details>
                                <details class="ui-accordion-item">
                                    <summary class="ui-accordion-toggle">Guest preferences <i class="fa fa-chevron-down" aria-hidden="true"></i></summary>
                                    <div class="ui-accordion-content">Window table if available, low-noise seating, vegetarian options.</div>
                                </details>
                            </div>
                        </article>

                        <article class="ui-panel">
                            <h2 class="ui-panel-title">Switches and Toggles</h2>
                            <div class="ui-stack">
                                <label class="ui-switch">
                                    <input type="checkbox" checked>
                                    <span class="ui-toggle" aria-hidden="true"></span>
                                    <span>Accept online bookings</span>
                                </label>
                                <label class="ui-switch">
                                    <input type="checkbox">
                                    <span class="ui-toggle" aria-hidden="true"></span>
                                    <span>Notify staff on new requests</span>
                                </label>
                            </div>
                        </article>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-ui-tabs]').forEach((tabs) => {
            const tabButtons = Array.from(tabs.querySelectorAll('[role="tab"]'));
            const panels = Array.from(tabs.querySelectorAll('[role="tabpanel"]'));

            const activateTab = (tab) => {
                tabButtons.forEach((button) => {
                    const isActive = button === tab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', String(isActive));
                    button.tabIndex = isActive ? 0 : -1;
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.id !== tab.getAttribute('aria-controls');
                });
            };

            tabButtons.forEach((tab, index) => {
                tab.addEventListener('click', () => activateTab(tab));
                tab.addEventListener('keydown', (event) => {
                    const nextKeys = ['ArrowRight', 'ArrowDown'];
                    const previousKeys = ['ArrowLeft', 'ArrowUp'];
                    if (![...nextKeys, ...previousKeys, 'Home', 'End'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();
                    let nextIndex = index;
                    if (nextKeys.includes(event.key)) nextIndex = (index + 1) % tabButtons.length;
                    if (previousKeys.includes(event.key)) nextIndex = (index - 1 + tabButtons.length) % tabButtons.length;
                    if (event.key === 'Home') nextIndex = 0;
                    if (event.key === 'End') nextIndex = tabButtons.length - 1;
                    tabButtons[nextIndex].focus();
                    activateTab(tabButtons[nextIndex]);
                });
            });
        });
    </script>
</body>
</html>

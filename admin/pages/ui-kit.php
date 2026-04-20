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
            <?php include __DIR__ . '/../partials/admin-topbar.php'; ?>

            <main class="admin-container">
                <div class="ui-kit-shell">
                    <section class="ui-kit-header">
                        <div>
                            <h1 class="ui-kit-title">DineMate UI Kit</h1>
                            <p class="ui-kit-subtitle">Reusable components for forms, actions, status, and dashboard blocks.</p>
                        </div>
                        <span class="ui-chip ui-chip-neutral">Version 1.0</span>
                    </section>

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
                </div>
            </main>
        </div>
    </div>
</body>
</html>

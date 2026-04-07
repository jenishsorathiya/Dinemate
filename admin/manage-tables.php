<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";

// Check if user is admin
if(!isAdmin()) {
    header("Location: ../auth/login.php");
    exit();
}

// Handle Add Table
if(isset($_POST['add_table'])) {
    $table_number = intval($_POST['table_number']);
    $capacity = intval($_POST['capacity']);
    $status = sanitize($_POST['status']);
    
    // Check if table number already exists
    $stmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE table_number = ?");
    $stmt->execute([$table_number]);
    
    if($stmt->rowCount() > 0) {
        setFlashMessage('error', 'Table number already exists!');
    } elseif($table_number <= 0 || $capacity <= 0) {
        setFlashMessage('error', 'Table number and capacity must be positive numbers!');
    } else {
        $stmt = $pdo->prepare("INSERT INTO restaurant_tables (table_number, capacity, status) VALUES (?, ?, ?)");
        $stmt->execute([$table_number, $capacity, $status]);
        setFlashMessage('success', 'Table added successfully!');
    }
    header("Location: manage-tables.php");
    exit();
}

// Handle Edit Table
if(isset($_POST['edit_table'])) {
    $table_id = intval($_POST['table_id']);
    $table_number = intval($_POST['table_number']);
    $capacity = intval($_POST['capacity']);
    $status = sanitize($_POST['status']);
    
    // Check if table number exists for another table
    $stmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE table_number = ? AND table_id != ?");
    $stmt->execute([$table_number, $table_id]);
    
    if($stmt->rowCount() > 0) {
        setFlashMessage('error', 'Table number already exists!');
    } elseif($table_number <= 0 || $capacity <= 0) {
        setFlashMessage('error', 'Table number and capacity must be positive numbers!');
    } else {
        $stmt = $pdo->prepare("UPDATE restaurant_tables SET table_number = ?, capacity = ?, status = ? WHERE table_id = ?");
        $stmt->execute([$table_number, $capacity, $status, $table_id]);
        setFlashMessage('success', 'Table updated successfully!');
    }
    header("Location: manage-tables.php");
    exit();
}

// Handle Delete Table
if(isset($_GET['delete'])) {
    $table_id = intval($_GET['delete']);
    
    // Check if table has future bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings 
        WHERE table_id = ? AND booking_date >= CURDATE() AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$table_id]);
    $futureBookings = $stmt->fetchColumn();
    
    if($futureBookings > 0) {
        setFlashMessage('warning', "Cannot delete table with $futureBookings future booking(s).");
    } else {
        $stmt = $pdo->prepare("DELETE FROM restaurant_tables WHERE table_id = ?");
        $stmt->execute([$table_id]);
        setFlashMessage('success', 'Table deleted successfully!');
    }
    header("Location: manage-tables.php");
    exit();
}

// Handle Toggle Table Status (Available/Unavailable)
if(isset($_GET['toggle'])) {
    $table_id = intval($_GET['toggle']);
    
    $stmt = $pdo->prepare("SELECT status FROM restaurant_tables WHERE table_id = ?");
    $stmt->execute([$table_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $newStatus = ($current['status'] == 'available') ? 'unavailable' : 'available';
    
    $stmt = $pdo->prepare("UPDATE restaurant_tables SET status = ? WHERE table_id = ?");
    $stmt->execute([$newStatus, $table_id]);
    
    setFlashMessage('success', 'Table status updated successfully!');
    header("Location: manage-tables.php");
    exit();
}

// Fetch all tables with booking count
$stmt = $pdo->query("
    SELECT t.*, 
           COUNT(CASE WHEN b.booking_date >= CURDATE() AND b.status IN ('pending', 'confirmed') THEN 1 END) AS future_bookings
    FROM restaurant_tables t
    LEFT JOIN bookings b ON t.table_id = b.table_id
    GROUP BY t.table_id
    ORDER BY t.table_number ASC
");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get table for editing if edit parameter exists
$editTable = null;
if(isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM restaurant_tables WHERE table_id = ?");
    $stmt->execute([$edit_id]);
    $editTable = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Tables | DineMate Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
     <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
        }
        
        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            background: #111827;
            color: white;
            padding: 25px;
            left: 0;
            top: 0;
        }
        
        .sidebar h4 {
            color: #f4b400;
            margin-bottom: 35px;
            font-weight: 700;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            color: #ddd;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: 0.3s;
        }
        
        .sidebar a i {
            margin-right: 10px;
            width: 24px;
        }
        
        .sidebar a:hover {
            background: #1f2937;
            color: #f4b400;
        }
        
        .sidebar a.active {
            background: #f4b400;
            color: #111827;
        }
        
        /* TOPBAR */
        .topbar {
            margin-left: 260px;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            position: fixed;
            right: 0;
            left: 260px;
            top: 0;
            z-index: 99;
        }
        
        /* MAIN CONTENT */
        .main {
            margin-left: 260px;
            padding: 90px 30px 30px 30px;
        }
        
        /* CARDS */
        .card-custom {
            background: white;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .card-custom h5 {
            font-weight: 600;
            margin-bottom: 20px;
            color: #1f2937;
        }
        
        /* TABLE GRID */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: 0.3s;
            border: 1px solid #e5e7eb;
        }
        
        .table-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .table-header {
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .table-header.available {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .table-header.unavailable {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        
        .table-header i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .table-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .table-body {
            padding: 20px;
        }
        
        .table-body p {
            margin-bottom: 10px;
            color: #4b5563;
        }
        
        .table-body p i {
            width: 24px;
            color: #f4b400;
        }
        
        .table-actions {
            padding: 15px 20px;
            background: #f9fafb;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-available {
            background: #22c55e;
            color: white;
        }
        
        .status-unavailable {
            background: #6b7280;
            color: white;
        }
        
        /* FORM */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #f4b400;
            box-shadow: 0 0 0 3px rgba(244,180,0,0.2);
        }
        
        /* BUTTONS */
        .btn-warning {
            background: #f4b400;
            border: none;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        
        /* SEARCH */
        .search-box {
            position: relative;
            width: 300px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 30px;
        }
        
        /* MODAL */
        .modal-content {
            border-radius: 14px;
        }
        
        .modal-header {
            background: #f4b400;
            color: #111827;
            border-radius: 14px 14px 0 0;
        }
        
        /* ALERTS */
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        /* STATS */
        .stat-mini {
            background: #f9fafb;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-mini h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: #f4b400;
        }
        
        .stat-mini p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h4><i class="fa fa-utensils"></i> DineMate</h4>
    <a href="dashboard.php">
        <i class="fa fa-chart-line"></i> Dashboard
    </a>
    <a href="manage-bookings.php">
        <i class="fa fa-calendar-check"></i> Bookings
    </a>
    <a href="manage-tables.php" class="active">
        <i class="fa fa-chair"></i> Tables
    </a>
    <a href="manage-users.php">
        <i class="fa fa-users"></i> Users
    </a>
    <a href="../auth/logout.php">
        <i class="fa fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <h5 class="mb-0"><i class="fa fa-chair"></i> Manage Tables</h5>
    <div>
        <span><i class="fa fa-user-circle"></i> <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    
    <!-- Flash Messages -->
    <?php
    $flash = getFlashMessage();
    if($flash):
    ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-mini">
                <h3><?= count($tables) ?></h3>
                <p>Total Tables</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-mini">
                <h3><?= count(array_filter($tables, function($t) { return $t['status'] == 'available'; })) ?></h3>
                <p>Available Tables</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-mini">
                <h3><?= count(array_filter($tables, function($t) { return $t['status'] == 'unavailable'; })) ?></h3>
                <p>Unavailable Tables</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-mini">
                <h3><?= array_sum(array_column($tables, 'capacity')) ?></h3>
                <p>Total Capacity</p>
            </div>
        </div>
    </div>
    
    <!-- Add Table Form -->
    <div class="card-custom" id="add-table-form">
        <h5><i class="fa fa-plus-circle"></i> Add New Table</h5>
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Table Number *</label>
                    <input type="number" name="table_number" class="form-control" min="1" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Capacity (Seats) *</label>
                    <input type="number" name="capacity" class="form-control" min="1" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="submit" name="add_table" class="btn btn-warning w-100">
                        <i class="fa fa-plus"></i> Add Table
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tables Grid -->
    <div class="card-custom">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5><i class="fa fa-list"></i> Restaurant Tables (<?= count($tables) ?>)</h5>
            <div class="search-box">
                <i class="fa fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search by table number...">
            </div>
        </div>
        
        <div class="tables-grid" id="tablesGrid">
            <?php foreach($tables as $table): ?>
            <div class="table-card" data-table-number="<?= $table['table_number'] ?>">
                <div class="table-header <?= $table['status'] ?>">
                    <i class="fa fa-chair"></i>
                    <h3>Table <?= $table['table_number'] ?></h3>
                </div>
                <div class="table-body">
                    <p><i class="fa fa-users"></i> Capacity: <strong><?= $table['capacity'] ?> seats</strong></p>
                    <p><i class="fa fa-calendar"></i> Future Bookings: <strong><?= $table['future_bookings'] ?></strong></p>
                    <p>
                        <i class="fa fa-circle"></i> Status: 
                        <span class="status-badge status-<?= $table['status'] ?>">
                            <?= ucfirst($table['status']) ?>
                        </span>
                    </p>
                </div>
                <div class="table-actions">
                    <a href="?toggle=<?= $table['table_id'] ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Change table status?')">
                        <i class="fa fa-exchange-alt"></i> Toggle Status
                    </a>
                    <a href="?edit=<?= $table['table_id'] ?>" class="btn btn-sm btn-primary">
                        <i class="fa fa-edit"></i> Edit
                    </a>
                    <?php if($table['future_bookings'] == 0): ?>
                        <a href="?delete=<?= $table['table_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this table? This action cannot be undone.')">
                            <i class="fa fa-trash"></i> Delete
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-danger" disabled title="Cannot delete table with <?= $table['future_bookings'] ?> future booking(s)">
                            <i class="fa fa-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if(count($tables) == 0): ?>
            <p class="text-muted text-center mb-0">No tables found. Click "Add New Table" to create one.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Table Modal -->
<?php if($editTable): ?>
<div class="modal show d-block" id="editModal" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-edit"></i> Edit Table <?= $editTable['table_number'] ?></h5>
                <button type="button" class="btn-close" onclick="window.location.href='manage-tables.php'"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="table_id" value="<?= $editTable['table_id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Table Number</label>
                        <input type="number" name="table_number" class="form-control" value="<?= $editTable['table_number'] ?>" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity (Seats)</label>
                        <input type="number" name="capacity" class="form-control" value="<?= $editTable['capacity'] ?>" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="available" <?= $editTable['status'] == 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="unavailable" <?= $editTable['status'] == 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                    <?php if($editTable['future_bookings'] > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i>
                            This table has <?= $editTable['future_bookings'] ?> future booking(s). Changing capacity may affect existing reservations.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='manage-tables.php'">Cancel</button>
                    <button type="submit" name="edit_table" class="btn btn-warning">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let cards = document.querySelectorAll('.table-card');
    
    cards.forEach(card => {
        let tableNumber = card.getAttribute('data-table-number');
        if(tableNumber && tableNumber.includes(value)) {
            card.style.display = '';
        } else if(card.innerText.toLowerCase().includes(value)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// Auto-hide alerts after 3 seconds
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 3000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
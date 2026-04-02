<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";

// Check if user is admin
if(!isAdmin()) {
    header("Location: ../auth/login.php");
    exit();
}

// Handle Promote User to Admin
if(isset($_GET['promote'])) {
    $user_id = intval($_GET['promote']);
    
    // Don't allow promoting self
    if($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?");
        $stmt->execute([$user_id]);
        setFlashMessage('success', 'User promoted to admin successfully!');
    } else {
        setFlashMessage('error', 'You cannot promote yourself!');
    }
    header("Location: manage-users.php");
    exit();
}

// Handle Demote User to Customer
if(isset($_GET['demote'])) {
    $user_id = intval($_GET['demote']);
    
    // Don't allow demoting self
    if($user_id != $_SESSION['user_id']) {
        // Check if this is the last admin
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $adminCount = $stmt->fetchColumn();
        
        if($adminCount > 1) {
            $stmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            setFlashMessage('success', 'User demoted to customer successfully!');
        } else {
            setFlashMessage('error', 'Cannot demote the last admin!');
        }
    } else {
        setFlashMessage('error', 'You cannot demote yourself!');
    }
    header("Location: manage-users.php");
    exit();
}

// Handle Delete User
if(isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Don't allow deleting self
    if($user_id != $_SESSION['user_id']) {
        // Check if user has bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $bookingCount = $stmt->fetchColumn();
        
        if($bookingCount > 0) {
            setFlashMessage('warning', "Cannot delete user with $bookingCount existing booking(s). Delete bookings first.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            setFlashMessage('success', 'User deleted successfully!');
        }
    } else {
        setFlashMessage('error', 'You cannot delete yourself!');
    }
    header("Location: manage-users.php");
    exit();
}

// Handle Add New User
if(isset($_POST['add_user'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    
    // Validate
    $errors = [];
    if(empty($name)) $errors[] = "Name is required";
    if(empty($email)) $errors[] = "Email is required";
    if(empty($password)) $errors[] = "Password is required";
    if(strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if($stmt->rowCount() > 0) {
        $errors[] = "Email already exists";
    }
    
    if(empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $phone, $password, $role]);
        setFlashMessage('success', 'User added successfully!');
        header("Location: manage-users.php");
        exit();
    } else {
        $_SESSION['add_user_errors'] = $errors;
        $_SESSION['add_user_data'] = $_POST;
        header("Location: manage-users.php#add-user-form");
        exit();
    }
}

// Handle Edit User
if(isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    
    // Don't allow editing self if it would remove admin role
    if($user_id == $_SESSION['user_id'] && $role != 'admin') {
        setFlashMessage('error', 'You cannot remove your own admin privileges!');
        header("Location: manage-users.php");
        exit();
    }
    
    // Check if email exists for another user
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $user_id]);
    if($stmt->rowCount() > 0) {
        setFlashMessage('error', 'Email already exists for another user!');
        header("Location: manage-users.php");
        exit();
    }
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, email = ?, phone = ?, role = ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$name, $email, $phone, $role, $user_id]);
    setFlashMessage('success', 'User updated successfully!');
    header("Location: manage-users.php");
    exit();
}

// Fetch all users with booking count
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(b.booking_id) AS booking_count
    FROM users u
    LEFT JOIN bookings b ON u.user_id = b.user_id
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing if edit parameter exists
$editUser = null;
if(isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$edit_id]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users | DineMate Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
        }

        .admin-layout {
            display: flex;
            height: 100vh;
        }
        
        /* SIDEBAR */
        .sidebar {
            width: 88px;
            background: #111827;
            color: white;
            padding: 20px;
            overflow-y: auto;
            overflow-x: hidden;
            flex-shrink: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: width 0.25s ease;
        }

        .sidebar:hover {
            width: 260px;
        }
        
        .sidebar h4 {
            color: #f4b400;
            margin-bottom: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
            white-space: nowrap;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 15px;
            color: #ddd;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: background 0.2s ease, color 0.2s ease, justify-content 0.2s ease;
            white-space: nowrap;
        }

        .sidebar:hover a {
            justify-content: flex-start;
        }
        
        .sidebar h4 i,
        .sidebar a i {
            width: 24px;
            min-width: 24px;
            text-align: center;
            font-size: 20px;
        }

        .brand-label,
        .nav-label {
            opacity: 0;
            max-width: 0;
            margin-left: 0;
            overflow: hidden;
            transition: opacity 0.2s ease, max-width 0.25s ease, margin-left 0.25s ease;
        }

        .sidebar:hover .brand-label,
        .sidebar:hover .nav-label {
            opacity: 1;
            max-width: 180px;
            margin-left: 12px;
        }

        .sidebar:not(:hover) h4 {
            justify-content: center;
        }
        
        .sidebar a:hover,
        .sidebar a.active {
            background: #f4b400;
            color: #111827;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* TOPBAR */
        .topbar {
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }
        
        /* MAIN CONTENT */
        .main {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
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
        
        /* TABLE */
        .table-custom {
            width: 100%;
        }
        
        .table-custom th {
            background: #f9fafb;
            padding: 12px;
            font-weight: 600;
            color: #374151;
        }
        
        .table-custom td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        
        .table-custom tr:hover {
            background: #f9fafb;
        }
        
        /* BADGES */
        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-admin {
            background: #f4b400;
            color: #111827;
        }
        
        .role-customer {
            background: #3b82f6;
            color: white;
        }
        
        /* BUTTONS */
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
            margin: 2px;
        }
        
        .btn-promote {
            background: #22c55e;
            color: white;
            border: none;
        }
        
        .btn-demote {
            background: #f59e0b;
            color: white;
            border: none;
        }
        
        .btn-edit {
            background: #3b82f6;
            color: white;
            border: none;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
            border: none;
        }
        
        .btn-promote:hover, .btn-demote:hover, .btn-edit:hover, .btn-delete:hover {
            opacity: 0.8;
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
        
        /* SEARCH */
        .search-box {
            position: relative;
            margin-bottom: 20px;
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
    </style>
</head>
<body>

<div class="admin-layout">

<!-- SIDEBAR -->
<div class="sidebar">
    <h4><i class="fa fa-utensils"></i><span class="brand-label">DineMate</span></h4>
    <a href="dashboard.php">
        <i class="fa fa-chart-line"></i><span class="nav-label">Analytics</span>
    </a>
    <a href="timeline/new-dashboard.php">
        <i class="fa fa-calendar-days"></i><span class="nav-label">Timeline</span>
    </a>
    <a href="menu-management.php">
        <i class="fa fa-utensils"></i><span class="nav-label">Menu</span>
    </a>
    <a href="manage-users.php" class="active">
        <i class="fa fa-users"></i><span class="nav-label">Users</span>
    </a>
    <a href="../auth/logout.php">
        <i class="fa fa-sign-out-alt"></i><span class="nav-label">Logout</span>
    </a>
</div>

<div class="main-content">
    <!-- TOPBAR -->
    <div class="topbar">
        <h5 class="mb-0"><i class="fa fa-users"></i> Manage Users</h5>
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
    
    <!-- Add User Form -->
    <div class="card-custom" id="add-user-form">
        <h5><i class="fa fa-user-plus"></i> Add New User</h5>
        
        <?php if(isset($_SESSION['add_user_errors'])): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach($_SESSION['add_user_errors'] as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['add_user_errors']); ?>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" 
                           value="<?= htmlspecialchars($_SESSION['add_user_data']['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($_SESSION['add_user_data']['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($_SESSION['add_user_data']['phone'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="customer" <?= (($_SESSION['add_user_data']['role'] ?? '') == 'customer') ? 'selected' : '' ?>>Customer</option>
                        <option value="admin" <?= (($_SESSION['add_user_data']['role'] ?? '') == 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_user" class="btn btn-warning">
                <i class="fa fa-plus"></i> Add User
            </button>
        </form>
        <?php unset($_SESSION['add_user_data']); ?>
    </div>
    
    <!-- Edit User Modal -->
    <?php if($editUser): ?>
    <div class="modal show d-block" id="editModal" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-edit"></i> Edit User</h5>
                    <button type="button" class="btn-close" onclick="window.location.href='manage-users.php'"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editUser['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editUser['phone']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="customer" <?= $editUser['role'] == 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="admin" <?= $editUser['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='manage-users.php'">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-warning">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Users List -->
    <div class="card-custom">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5><i class="fa fa-list"></i> Registered Users (<?= count($users) ?>)</h5>
            <div class="search-box" style="width: 300px;">
                <i class="fa fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email...">
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table-custom" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Bookings</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?= $user['user_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <?php if($user['user_id'] == $_SESSION['user_id']): ?>
                                <span class="badge bg-info ms-1">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= $user['phone'] ?: '-' ?></td>
                        <td>
                            <span class="role-badge role-<?= $user['role'] ?>">
                                <i class="fa fa-<?= $user['role'] == 'admin' ? 'crown' : 'user' ?>"></i>
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= $user['booking_count'] ?></span>
                        </td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                <?php if($user['role'] == 'customer'): ?>
                                    <a href="?promote=<?= $user['user_id'] ?>" class="btn btn-promote btn-sm" onclick="return confirm('Promote this user to admin?')">
                                        <i class="fa fa-arrow-up"></i> Promote
                                    </a>
                                <?php else: ?>
                                    <a href="?demote=<?= $user['user_id'] ?>" class="btn btn-demote btn-sm" onclick="return confirm('Demote this user to customer?')">
                                        <i class="fa fa-arrow-down"></i> Demote
                                    </a>
                                <?php endif; ?>
                                
                                <a href="?edit=<?= $user['user_id'] ?>" class="btn btn-edit btn-sm">
                                    <i class="fa fa-edit"></i> Edit
                                </a>
                                
                                <?php if($user['booking_count'] == 0): ?>
                                    <a href="?delete=<?= $user['user_id'] ?>" class="btn btn-delete btn-sm" onclick="return confirm('Delete this user? This action cannot be undone.')">
                                        <i class="fa fa-trash"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-delete btn-sm" disabled title="Cannot delete user with <?= $user['booking_count'] ?> booking(s)">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-edit btn-sm" onclick="alert('You cannot edit yourself here. Go to profile page.')">
                                    <i class="fa fa-edit"></i> Edit
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(count($users) == 0): ?>
            <p class="text-muted text-center mb-0">No users found.</p>
        <?php endif; ?>
    </div>
    </div>
</div>
</div>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(value) ? '' : 'none';
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
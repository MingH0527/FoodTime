<?php
session_start();
include 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['position'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name = htmlspecialchars($_SESSION['username']);

$success = '';
$error   = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $categoryid  = trim($_POST['categoryid'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (empty($name) || empty($categoryid) || empty($price)) {
        $error = "❌ Name, category and price are required.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "❌ Price must be a positive number.";
    } else {
        // Generate new product ID
        $last = mysqli_fetch_assoc(mysqli_query($connect, "SELECT productid FROM products ORDER BY productid DESC LIMIT 1"));
        if ($last) {
            $last_num = intval(substr($last['productid'], 2));
            $new_id = 'PR' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $new_id = 'PR001';
        }

        // Handle image upload
        $image_filename = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            if (!in_array($file_type, $allowed_types)) {
                $error = "❌ Invalid image type. Only JPG, PNG, GIF, WEBP allowed.";
            } else {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image_filename = $new_id . '_' . time() . '.' . strtolower($ext);
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_filename)) {
                    $error = "❌ Failed to upload image.";
                    $image_filename = null;
                }
            }
        }

        if (empty($error)) {
            $stmt = $connect->prepare("INSERT INTO products (productid, categoryid, name, price, image, description, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdssi", $new_id, $categoryid, $name, $price, $image_filename, $description, $is_available);
            if ($stmt->execute()) {
                $success = "✅ '{$name}' has been added to the menu! (ID: {$new_id})";
            } else {
                $error = "❌ Database error. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Fetch categories
$categories = mysqli_query($connect, "SELECT * FROM categories ORDER BY categoryid ASC");

// Fetch all products for the list
$products = mysqli_query($connect, "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.categoryid = c.categoryid ORDER BY p.productid DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Food — Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0d0d0d;--surface:#161616;--surface2:#1e1e1e;--border:#2a2a2a;--accent:#ff6b35;--accent2:#ffb347;--text:#f0f0f0;--muted:#888;--success:#4ade80;--warning:#facc15;--danger:#f87171;--info:#60a5fa}
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex}
        .sidebar{width:260px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:100}
        .sidebar-logo{padding:28px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}
        .logo-text{font-family:'Syne',sans-serif;font-weight:800;font-size:18px}
        .logo-sub{font-size:11px;color:var(--muted);letter-spacing:2px;text-transform:uppercase}
        .sidebar-nav{padding:20px 0;flex:1}
        .nav-section-label{padding:8px 24px;font-size:10px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;font-weight:600;margin-top:10px}
        .nav-item{display:flex;align-items:center;gap:12px;padding:12px 24px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:500;transition:all .2s;position:relative}
        .nav-item:hover,.nav-item.active{color:var(--text);background:rgba(255,107,53,.08)}
        .nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent);border-radius:0 3px 3px 0}
        .nav-item i{width:18px;text-align:center}
        .sidebar-footer{padding:20px 24px;border-top:1px solid var(--border)}
        .admin-info{display:flex;align-items:center;gap:12px;margin-bottom:16px}
        .admin-avatar{width:38px;height:38px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:white}
        .admin-name{font-size:14px;font-weight:600}
        .admin-role{font-size:11px;color:var(--accent)}
        .btn-logout{display:flex;align-items:center;gap:8px;padding:10px 16px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.2);color:var(--danger);border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;width:100%;justify-content:center}
        .main{margin-left:260px;flex:1;display:flex;flex-direction:column;min-height:100vh}
        .topbar{padding:20px 36px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface);position:sticky;top:0;z-index:50}
        .topbar-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700}
        .topbar-title span{color:var(--accent)}
        .content{padding:36px}

        /* Layout */
        .page-grid{display:grid;grid-template-columns:420px 1fr;gap:28px;align-items:start}

        /* Form card */
        .form-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;position:sticky;top:90px}
        .card-header{padding:20px 24px;border-bottom:1px solid var(--border)}
        .card-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;display:flex;align-items:center;gap:10px}
        .card-title i{color:var(--accent)}
        .card-body{padding:24px}

        /* Flash */
        .flash{padding:14px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px}
        .flash.success{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.25);color:var(--success)}
        .flash.error{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.25);color:var(--danger)}

        /* Form fields */
        .form-group{margin-bottom:18px}
        .form-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px}
        .form-label span{color:var(--danger)}
        .form-input{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:.2s}
        .form-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(255,107,53,.1)}
        .form-input::placeholder{color:var(--muted)}
        select.form-input{cursor:pointer}
        textarea.form-input{resize:vertical;min-height:90px}

        /* Image upload */
        .upload-area{border:2px dashed var(--border);border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:.2s;position:relative;background:var(--surface2)}
        .upload-area:hover,.upload-area.dragover{border-color:var(--accent);background:rgba(255,107,53,.05)}
        .upload-area input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .upload-icon{font-size:32px;margin-bottom:10px;color:var(--muted)}
        .upload-text{font-size:13px;color:var(--muted)}
        .upload-preview{display:none;margin-top:12px}
        .upload-preview img{max-width:100%;max-height:140px;border-radius:8px;object-fit:cover}

        /* Toggle */
        .toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
        .toggle-label{font-size:14px;font-weight:500}
        .toggle-sub{font-size:12px;color:var(--muted);margin-top:2px}
        .toggle-switch{position:relative;width:46px;height:26px}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;inset:0;background:#333;border-radius:13px;cursor:pointer;transition:.3s}
        .toggle-slider::before{content:'';position:absolute;width:18px;height:18px;left:4px;bottom:4px;background:white;border-radius:50%;transition:.3s}
        .toggle-switch input:checked+.toggle-slider{background:var(--accent)}
        .toggle-switch input:checked+.toggle-slider::before{transform:translateX(20px)}

        /* Submit button */
        .btn-submit{width:100%;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;color:white;padding:14px;border-radius:10px;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;letter-spacing:.5px;margin-top:8px}
        .btn-submit:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,107,53,.3)}

        /* Products list */
        .products-panel{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden}
        .products-grid-table{width:100%;border-collapse:collapse}
        .products-grid-table th{padding:12px 20px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        .products-grid-table td{padding:14px 20px;font-size:13px;border-bottom:1px solid rgba(42,42,42,.5);vertical-align:middle}
        .products-grid-table tr:last-child td{border-bottom:none}
        .products-grid-table tr:hover td{background:rgba(255,107,53,.02)}
        .prod-img{width:44px;height:44px;background:linear-gradient(135deg,var(--surface2),#333);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:18px;overflow:hidden;flex-shrink:0}
        .prod-img img{width:100%;height:100%;object-fit:cover}
        .prod-info{display:flex;align-items:center;gap:12px}
        .avail-dot{width:8px;height:8px;border-radius:50%;display:inline-block}
        .avail-yes{background:var(--success)}
        .avail-no{background:var(--danger)}

        @media(max-width:1100px){.page-grid{grid-template-columns:1fr}.form-card{position:static}}
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">🍔</div>
        <div><div class="logo-text">FOODTIME</div><div class="logo-sub">Admin Panel</div></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="Admin.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <div class="nav-section-label">Management</div>
        <a href="user.php" class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
        <a href="order.php" class="nav-item"><i class="fas fa-receipt"></i> All Orders</a>
        <a href="add_food.php" class="nav-item active"><i class="fas fa-plus-circle"></i> Add Food</a>
        <div class="nav-section-label">System</div>
        <a href="Product.php" class="nav-item"><i class="fas fa-store"></i> View Store</a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
            <div><div class="admin-name"><?= $admin_name ?></div><div class="admin-role">Administrator</div></div>
        </div>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Add New <span>Food</span></div>
    </div>

    <div class="content">
        <div class="page-grid">
            <!-- Form -->
            <div class="form-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> New Menu Item</div>
                </div>
                <div class="card-body">
                    <?php if($success): ?>
                    <div class="flash success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                    <div class="flash error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Food Name <span>*</span></label>
                            <input type="text" name="name" class="form-input" placeholder="e.g. Grilled Chicken Rice" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category <span>*</span></label>
                            <select name="categoryid" class="form-input" required>
                                <option value="">— Select Category —</option>
                                <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $cat['categoryid'] ?>" <?= (isset($_POST['categoryid']) && $_POST['categoryid'] == $cat['categoryid']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Price (RM) <span>*</span></label>
                            <input type="number" name="price" class="form-input" placeholder="0.00" step="0.01" min="0.01" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input" placeholder="Describe the dish…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Food Image</label>
                            <div class="upload-area" id="uploadArea">
                                <input type="file" name="image" id="imageInput" accept="image/*" onchange="previewImage(this)">
                                <div class="upload-icon">📷</div>
                                <div class="upload-text">Click or drag image here<br><small>JPG, PNG, GIF, WEBP</small></div>
                                <div class="upload-preview" id="uploadPreview">
                                    <img id="previewImg" src="" alt="preview">
                                </div>
                            </div>
                        </div>

                        <div class="toggle-row">
                            <div>
                                <div class="toggle-label">Available on Menu</div>
                                <div class="toggle-sub">Customers can see & order this item</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_available" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-plus"></i> Add to Menu
                        </button>
                    </form>
                </div>
            </div>

            <!-- Current products list -->
            <div class="products-panel">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-utensils"></i> Current Menu (<?= mysqli_num_rows($products) ?> items)</div>
                </div>
                <table class="products-grid-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Available</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($prod = mysqli_fetch_assoc($products)): ?>
                    <tr>
                        <td>
                            <div class="prod-info">
                                <div class="prod-img">
                                    <?php if($prod['image']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($prod['image']) ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-utensils"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($prod['name']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)"><?= $prod['productid'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($prod['category_name'] ?? '—') ?></td>
                        <td style="font-weight:700;color:var(--accent)">RM <?= number_format($prod['price'], 2) ?></td>
                        <td>
                            <span class="avail-dot <?= $prod['is_available'] ? 'avail-yes' : 'avail-no' ?>"></span>
                            <?= $prod['is_available'] ? 'Yes' : 'No' ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('uploadPreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('imageInput').files = e.dataTransfer.files;
        previewImage(document.getElementById('imageInput'));
    }
});
</script>
</body>
</html>
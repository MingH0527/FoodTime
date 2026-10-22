<?php
session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin');
$current_page = 'products';

$flash = null;
$edit  = null;

/* ── ID Generator ────────────────────────────────────────────────── */
function next_product_id($connect): string {
    $r   = mysqli_query($connect, "SELECT productid FROM products ORDER BY productid DESC LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    if (!$row) return 'PRD001';
    preg_match('/(\d+)$/', $row['productid'], $m);
    $n = $m ? intval($m[1]) + 1 : 1;
    return 'PRD' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

/* ── Default options per category type ──────────────────────────── */
function getDefaultOptions(string $prod_type): array {
    if ($prod_type === 'beverage') {
        return [
            'spice'    => false,
            'beverage' => true,
            'dessert'  => false,
            'remove'   => [
                ['key' => 'no_sugar', 'label' => '🍬 Less Sugar'],
                ['key' => 'no_ice',   'label' => '🧊 Less Ice'],
            ],
            'addons'   => [
                ['key' => 'add_sugar', 'label' => '🍬 Extra Sugar', 'price' => 0.00],
                ['key' => 'add_milk',  'label' => '🥛 Extra Milk',  'price' => 0.50],
            ],
        ];
    } elseif ($prod_type === 'dessert') {
        return [
            'spice'    => false,
            'beverage' => false,
            'dessert'  => true,
            'remove'   => [],
            'addons'   => [
                ['key' => 'add_cream', 'label' => '🍦 Add Ice Cream', 'price' => 3.00],
                ['key' => 'add_sauce', 'label' => '🍯 Extra Sauce',   'price' => 1.00],
            ],
        ];
    } else {
        return [
            'spice'    => true,
            'beverage' => false,
            'dessert'  => false,
            'remove'   => [
                ['key' => 'no_onion',     'label' => '🧅 No Onion'],
                ['key' => 'no_chili',     'label' => '🌶 No Chili'],
                ['key' => 'no_coriander', 'label' => '🌿 No Coriander'],
                ['key' => 'no_sauce',     'label' => '🥫 No Sauce'],
            ],
            'addons'   => [
                ['key' => 'add_rice',  'label' => '🍚 Add Rice',    'price' => 2.00],
                ['key' => 'add_sauce', 'label' => '🥫 Extra Sauce', 'price' => 1.00],
                ['key' => 'add_meat',  'label' => '🥩 Extra Meat',  'price' => 5.00],
            ],
        ];
    }
}

/* ── Determine prod_type from category name ──────────────────────── */
function prodTypeFromCatName(string $cat_name): string {
    $l = strtolower($cat_name);
    if (in_array($l, ['beverages','drinks','juice','coffee','tea','water','beverage'])) return 'beverage';
    if (in_array($l, ['desserts','dessert'])) return 'dessert';
    return 'food';
}

/* ── buildCustomOptions ──────────────────────────────────────────── */
function buildCustomOptions(): string {
    $prod_type = $_POST['prod_type'] ?? 'food';
    $has_spice = ($prod_type === 'food') && isset($_POST['has_spice']);

    $remove = [];
    foreach ($_POST['remove_opts'] ?? [] as $json) {
        $item = json_decode($json, true);
        if (is_array($item) && !empty($item['key']) && !empty($item['label'])) {
            $remove[] = ['key' => trim($item['key']), 'label' => trim($item['label'])];
        }
    }

    $addons = [];
    foreach ($_POST['addon_opts'] ?? [] as $json) {
        $item = json_decode($json, true);
        if (is_array($item) && !empty($item['key']) && !empty($item['label'])) {
            // Clamp addon price server-side: 0.00 – 10.00
            $addon_price = round(floatval($item['price'] ?? 0), 2);
            $addon_price = max(0.00, min(10.00, $addon_price));
            $addons[] = [
                'key'   => trim($item['key']),
                'label' => trim($item['label']),
                'price' => $addon_price,
            ];
        }
    }

    return json_encode([
        'spice'    => $has_spice,
        'beverage' => ($prod_type === 'beverage'),
        'dessert'  => ($prod_type === 'dessert'),
        'remove'   => $remove,
        'addons'   => $addons,
    ], JSON_UNESCAPED_UNICODE);
}

/* ── Image upload helper ─────────────────────────────────────────── */
define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('UPLOADS_URL_PATH', '/ASSFOOD/app/uploads/');

function handleImageUpload($pid, &$flash) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $flash = ['type'=>'error','msg'=>'Image upload error (code '.$_FILES['image']['error'].'). Please try again.']; return false;
    }
    $file = $_FILES['image']; $maxSize = 5*1024*1024;
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) { $flash=['type'=>'error','msg'=>'The file does not appear to be a valid image.']; return false; }
    $allowedTypes = [IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP];
    if (!in_array($imgInfo[2],$allowedTypes)) { $flash=['type'=>'error','msg'=>'Only JPG, PNG, and WEBP images are accepted.']; return false; }
    if ($file['size']>$maxSize) { $flash=['type'=>'error','msg'=>'Image too large. Maximum size is 5MB.']; return false; }
    $extMap=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
    $ext=$extMap[$imgInfo[2]]; $filename=$pid.'_'.time().'.'.$ext;
    if (!is_dir(UPLOADS_DIR)) { if (!mkdir(UPLOADS_DIR,0755,true)) { $flash=['type'=>'error','msg'=>'Could not create uploads directory.']; return false; } }
    $dest=UPLOADS_DIR.$filename;
    if (!move_uploaded_file($file['tmp_name'],$dest)) { $flash=['type'=>'error','msg'=>'Could not save the image. Check that '.UPLOADS_DIR.' is writable.']; return false; }
    return $filename;
}

const MAX_STOCK       = 1000;
const MAX_PRICE       = 999.99;
const MIN_PRICE       = 0.01;
const MAX_ADDON_PRICE = 10.00;

/* ── Name validation helper ──────────────────────────────────────── */
function validateProductName(string $name): ?string {
    if ($name === '') return 'Item name is required.';
    if (strlen($name) > 50) return 'Item name cannot exceed 50 characters.';
    if (!preg_match('/[A-Za-z]/', $name)) return 'Item name must contain at least one letter (cannot be numbers only).';
    if (!preg_match('/^[A-Za-z0-9\s\-\'&.()\[\]\/]+$/', $name)) return 'Item name contains invalid characters. Only letters, numbers, spaces, and basic punctuation are allowed.';
    return null;
}

/* ── POST ────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add','edit'])) {
        $name           = trim($_POST['name'] ?? '');
        $catid          = trim($_POST['categoryid'] ?? '');
        $price          = round(floatval($_POST['price'] ?? 0), 2);
        $avail          = isset($_POST['is_available']) ? 1 : 0;
        $stock          = min(MAX_STOCK, max(0, intval($_POST['stock'] ?? 0)));
        $custom_options = buildCustomOptions();

        $name_error = validateProductName($name);
        if ($name_error) {
            $flash = ['type'=>'error','msg'=>$name_error];
        } elseif ($catid === '') {
            $flash = ['type'=>'error','msg'=>'Please select a category.'];
        } elseif ($price < MIN_PRICE) {
            $flash = ['type'=>'error','msg'=>'Price must be at least RM 0.01. Free products are not allowed.'];
        } elseif ($price > MAX_PRICE) {
            $flash = ['type'=>'error','msg'=>'Price cannot exceed RM 999.99.'];
        } else {
            if ($action === 'add') {
                $pid = next_product_id($connect);
                $image_filename = handleImageUpload($pid, $flash);
                if ($image_filename !== false) {
                    $img_to_save=$image_filename??''; $desc='';
                    $stmt=mysqli_prepare($connect,"INSERT INTO products (productid,categoryid,name,price,image,description,is_available,stock,custom_options) VALUES (?,?,?,?,?,?,?,?,?)");
                    mysqli_stmt_bind_param($stmt,"sssdssiss",$pid,$catid,$name,$price,$img_to_save,$desc,$avail,$stock,$custom_options);
                    if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); header("Location: Product.php?flash=added&name=".urlencode($name)); exit(); }
                    else { $flash=['type'=>'error','msg'=>'Database error: '.mysqli_error($connect)]; mysqli_stmt_close($stmt); }
                }
            } else {
                $pid=trim($_POST['productid']??'');
                $image_filename=handleImageUpload($pid,$flash);
                if ($image_filename!==false) {
                    if ($image_filename!==null) { $stmt=mysqli_prepare($connect,"UPDATE products SET image=? WHERE productid=?"); mysqli_stmt_bind_param($stmt,"ss",$image_filename,$pid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                    $stmt=mysqli_prepare($connect,"UPDATE products SET categoryid=?,name=?,price=?,is_available=?,stock=?,custom_options=? WHERE productid=?");
                    mysqli_stmt_bind_param($stmt,"ssdisss",$catid,$name,$price,$avail,$stock,$custom_options,$pid);
                    if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); header("Location: Product.php?flash=updated&name=".urlencode($name)); exit(); }
                    else { $flash=['type'=>'error','msg'=>'Database error: '.mysqli_error($connect)]; mysqli_stmt_close($stmt); }
                }
            }
        }
    }
}

/* ── Edit mode ───────────────────────────────────────────────────── */
$edit_id = trim($_GET['edit'] ?? '');
if ($edit_id !== '') {
    $stmt=mysqli_prepare($connect,"SELECT * FROM products WHERE productid=?");
    mysqli_stmt_bind_param($stmt,"s",$edit_id); mysqli_stmt_execute($stmt);
    $edit=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
}

$edit_opts=null; $edit_prod_type='food';
if ($edit) {
    if (!empty($edit['custom_options'])) {
        $parsed=json_decode($edit['custom_options'],true);
        if (is_array($parsed)) {
            $edit_opts=$parsed;
            if (!empty($edit_opts['beverage'])) $edit_prod_type='beverage';
            elseif (!empty($edit_opts['dessert'])) $edit_prod_type='dessert';
        }
    }
    if (!$edit_opts) {
        $cat_stmt=mysqli_prepare($connect,"SELECT category_name FROM categories WHERE categoryid=? LIMIT 1");
        mysqli_stmt_bind_param($cat_stmt,'s',$edit['categoryid']); mysqli_stmt_execute($cat_stmt);
        $cat_row=mysqli_fetch_assoc(mysqli_stmt_get_result($cat_stmt)); mysqli_stmt_close($cat_stmt);
        $edit_prod_type=prodTypeFromCatName($cat_row['category_name']??'');
        $edit_opts=getDefaultOptions($edit_prod_type);
        $opts_json=json_encode($edit_opts,JSON_UNESCAPED_UNICODE);
        $upd=mysqli_prepare($connect,"UPDATE products SET custom_options=? WHERE productid=?");
        mysqli_stmt_bind_param($upd,'ss',$opts_json,$edit['productid']); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
    }
}

/* ── Categories ──────────────────────────────────────────────────── */
$cats_result=mysqli_query($connect,"SELECT * FROM categories ORDER BY categoryid");
$cats=mysqli_fetch_all($cats_result,MYSQLI_ASSOC);

/* ── Option libraries ────────────────────────────────────────────── */
$common_removes=[
    ['key'=>'no_onion','label'=>'🧅 No Onion'],['key'=>'no_chili','label'=>'🌶 No Chili'],
    ['key'=>'no_garlic','label'=>'🧄 No Garlic'],['key'=>'no_ginger','label'=>'🫚 No Ginger'],
    ['key'=>'no_sauce','label'=>'🥫 No Sauce'],['key'=>'no_egg','label'=>'🥚 No Egg'],
    ['key'=>'no_coriander','label'=>'🌿 No Coriander'],['key'=>'no_peanut','label'=>'🥜 No Peanut'],
    ['key'=>'no_mushroom','label'=>'🍄 No Mushroom'],['key'=>'no_tomato','label'=>'🍅 No Tomato'],
    ['key'=>'no_pepper','label'=>'🫑 No Pepper'],['key'=>'no_lemon','label'=>'🍋 No Lemon'],
    ['key'=>'no_cream','label'=>'🥛 No Cream'],['key'=>'no_butter','label'=>'🧈 No Butter'],
    ['key'=>'no_cheese','label'=>'🧀 No Cheese'],['key'=>'no_prawn','label'=>'🦐 No Prawn'],
    ['key'=>'no_meat','label'=>'🥩 No Meat'],['key'=>'no_lettuce','label'=>'🥬 No Lettuce'],
    ['key'=>'no_pickle','label'=>'🥒 No Pickle'],['key'=>'no_vinegar','label'=>'🍶 No Vinegar'],
    ['key'=>'no_capsicum','label'=>'🫑 No Capsicum'],['key'=>'no_pineapple','label'=>'🍍 No Pineapple'],
    ['key'=>'no_nuts','label'=>'🌰 No Nuts'],['key'=>'no_sausage','label'=>'🌭 No Sausage'],
    ['key'=>'no_corn','label'=>'🌽 No Corn'],['key'=>'no_curry_leaf','label'=>'🌿 No Curry Leaf'],
    ['key'=>'no_basil','label'=>'🌿 No Basil'],['key'=>'no_lemongrass','label'=>'🌾 No Lemongrass'],
    ['key'=>'no_carrot','label'=>'🥕 No Carrot'],['key'=>'no_pancake','label'=>'🫓 No Pancake'],
    ['key'=>'no_cabbage','label'=>'🥬 No Cabbage'],['key'=>'no_parmesan','label'=>'🧀 No Parmesan'],
];
$common_addons=[
    ['key'=>'add_rice','label'=>'🍚 Add Rice','price'=>2.00],
    ['key'=>'add_sauce','label'=>'🥫 Extra Sauce','price'=>1.00],
    ['key'=>'add_meat','label'=>'🥩 Extra Meat','price'=>5.00],
    ['key'=>'add_egg','label'=>'🥚 Extra Egg','price'=>2.00],
    ['key'=>'add_prawn','label'=>'🦐 Extra Prawn','price'=>6.00],
    ['key'=>'add_cheese','label'=>'🧀 Extra Cheese','price'=>2.00],
    ['key'=>'add_tofu','label'=>'🟨 Extra Tofu','price'=>2.00],
    ['key'=>'add_mushroom','label'=>'🍄 Extra Mushroom','price'=>2.00],
    ['key'=>'add_bread','label'=>'🍞 Bread / Bun','price'=>2.00],
    ['key'=>'add_cream','label'=>'🍦 Whipped Cream','price'=>1.00],
    ['key'=>'add_peanut','label'=>'🥜 Extra Peanut','price'=>1.00],
    ['key'=>'add_extra','label'=>'➕ Extra Portion','price'=>3.00],
    ['key'=>'add_salad','label'=>'🥗 Side Salad','price'=>2.00],
    ['key'=>'add_sugar','label'=>'🍬 Extra Sugar','price'=>0.00],
    ['key'=>'add_milk','label'=>'🥛 Extra Milk','price'=>0.50],
    ['key'=>'add_shot','label'=>'☕ Extra Shot','price'=>2.00],
    ['key'=>'add_pancake','label'=>'🫓 Extra Pancake','price'=>2.00],
    ['key'=>'add_onion','label'=>'🧅 Extra Onion','price'=>0.50],
    ['key'=>'add_nuts','label'=>'🌰 Extra Nuts','price'=>1.00],
    ['key'=>'add_coriander','label'=>'🌿 Extra Coriander','price'=>0.50],
];
$bev_removes=[
    ['key'=>'no_lemon','label'=>'🍋 Less Lemon'],['key'=>'no_sugar','label'=>'🍬 Less Sugar'],
    ['key'=>'no_ice','label'=>'🧊 Less Ice'],['key'=>'no_cream','label'=>'🥛 Less Cream'],
    ['key'=>'no_milk','label'=>'🥛 Less Milk'],['key'=>'no_sauce','label'=>'🥫 Less Syrup'],
];
$bev_addons=[
    ['key'=>'add_sugar','label'=>'🍬 Extra Sugar','price'=>0.00],
    ['key'=>'add_milk','label'=>'🥛 Extra Milk','price'=>0.50],
    ['key'=>'add_shot','label'=>'☕ Extra Shot','price'=>2.00],
    ['key'=>'add_cream','label'=>'🍦 Whipped Cream','price'=>1.00],
    ['key'=>'add_sauce','label'=>'🥫 Extra Syrup','price'=>0.50],
];
$dessert_addons=[
    ['key'=>'add_cream','label'=>'🍦 Add Ice Cream','price'=>3.00],
    ['key'=>'add_sauce','label'=>'🍯 Extra Sauce','price'=>1.00],
    ['key'=>'add_nuts','label'=>'🌰 Extra Nuts','price'=>1.00],
    ['key'=>'add_extra','label'=>'➕ Extra Portion','price'=>3.00],
];

function isRemoveSelected($key,$edit_opts){if(!$edit_opts||empty($edit_opts['remove']))return false;foreach($edit_opts['remove']as $r){if($r['key']===$key)return true;}return false;}
function isAddonSelected($key,$edit_opts){if(!$edit_opts||empty($edit_opts['addons']))return false;foreach($edit_opts['addons']as $a){if($a['key']===$key)return true;}return false;}
function getAddonPrice($key,$edit_opts,$default){if(!$edit_opts||empty($edit_opts['addons']))return $default;foreach($edit_opts['addons']as $a){if($a['key']===$key)return $a['price'];}return $default;}

$all_known_remove_keys=array_unique(array_merge(array_column($common_removes,'key'),array_column($bev_removes,'key')));
$all_known_addon_keys=array_unique(array_merge(array_column($common_addons,'key'),array_column($bev_addons,'key'),array_column($dessert_addons,'key')));

$page_title=$edit?'Edit: '.htmlspecialchars($edit['name']):'Add New Item';
$back_url='Product.php'.(isset($_GET['cat'])?'?cat='.urlencode($_GET['cat']).'&q='.urlencode($_GET['q']??'').'&page='.intval($_GET['page']??1):'');
$current_stock=$edit?(int)($edit['stock']??0):0;
$current_price=$edit?floatval($edit['price']??''):'';

/* Helper: clamp & format addon price for PHP output */
function ap(float $v): string { return number_format(max(0.00, min(10.00, $v)), 2, '.', ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .form-page{max-width:860px;margin:0 auto}
        .back-link{display:inline-flex;align-items:center;gap:7px;color:var(--muted);font-size:13px;font-weight:600;text-decoration:none;margin-bottom:20px;transition:color .15s}
        .back-link:hover{color:var(--accent)}
        .form-cols{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        @media(max-width:700px){.form-cols{grid-template-columns:1fr}}
        .form-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:20px}
        .form-card-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;background:var(--surface-2)}
        .form-card-head h3{font-size:14px;font-weight:800;color:var(--text);margin:0}
        .form-card-head i{width:28px;height:28px;background:var(--accent-bg);color:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px}
        .form-card-body{padding:20px 22px}
        .fg{margin-bottom:16px}.fg:last-child{margin-bottom:0}
        .fg label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
        .fi{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-weight:500;color:var(--text);background:var(--surface-2);outline:none;transition:border-color .15s;box-sizing:border-box}
        .fi:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(255,107,53,.12)}
        .fi::placeholder{color:var(--muted)}
        .fi.fi-error{border-color:var(--danger)!important;box-shadow:0 0 0 3px rgba(220,38,38,.12)!important}
        select.fi{cursor:pointer}
        .req{color:var(--danger)}
        .field-hint{font-size:11px;color:var(--muted);margin-top:5px;font-weight:500;display:flex;justify-content:space-between;align-items:center;gap:8px}
        .field-hint .hint-msg{flex:1}
        .field-hint .hint-counter{flex-shrink:0;font-weight:700}
        .field-hint .hint-counter.ok{color:var(--success,#16a34a)}
        .field-hint .hint-counter.warn{color:var(--warning,#b45309)}
        .field-hint.error .hint-msg{color:var(--danger);font-weight:700}
        .stock-presets{display:flex;gap:5px;flex-wrap:wrap;margin-top:8px}
        .stock-preset{padding:4px 10px;border-radius:6px;border:1.5px solid var(--accent);background:transparent;color:var(--accent);font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
        .stock-preset:hover{background:var(--accent);color:#fff}
        .stock-preview{display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;font-weight:700;color:var(--muted)}
        .stock-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800}
        .stock-ok{background:var(--success-bg);color:var(--success);border:1px solid rgba(62,207,142,.3)}
        .stock-low{background:var(--warning-bg);color:var(--warning);border:1px solid rgba(245,200,66,.3)}
        .stock-out{background:var(--danger-bg);color:var(--danger);border:1px solid rgba(241,107,107,.3)}
        .upload-zone{border:2px dashed var(--border);border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;background:var(--surface-2);overflow:hidden}
        .upload-zone:hover,.upload-zone.dragover{border-color:var(--accent);background:var(--accent-bg)}
        .upload-zone input[type="file"]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:10;font-size:0}
        .upload-zone-inner{position:relative;z-index:1;pointer-events:none}
        .upload-icon{font-size:30px;margin-bottom:8px;transition:transform .2s}
        .upload-zone:hover .upload-icon{transform:scale(1.15)}
        .upload-text{font-size:13px;color:var(--muted);font-weight:600}
        .upload-text small{display:block;font-size:11px;margin-top:3px;opacity:.7}
        .img-preview-panel{display:none;margin-top:14px;border-radius:12px;border:1.5px solid var(--accent);background:var(--accent-bg);overflow:hidden}
        .img-preview-panel.show{display:block}
        .img-preview-top{display:flex;align-items:center;gap:14px;padding:14px}
        .img-preview-top img{width:90px;height:90px;object-fit:cover;border-radius:10px;border:2px solid var(--border);flex-shrink:0}
        .img-preview-info{flex:1;min-width:0}
        .img-preview-name{font-size:13px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
        .img-preview-meta{display:flex;flex-wrap:wrap;gap:6px}
        .img-meta-badge{font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;background:var(--surface-3);color:var(--muted);border:1px solid var(--border)}
        .img-preview-remove{flex-shrink:0;width:30px;height:30px;border-radius:8px;border:1.5px solid var(--danger);background:transparent;color:var(--danger);font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
        .img-preview-remove:hover{background:var(--danger);color:#fff}
        .path-debug{font-size:11px;color:var(--muted);margin-top:8px;padding:8px 10px;background:var(--surface-3);border-radius:6px;display:none}
        .path-debug.show{display:block}
        .current-img-wrap{margin-bottom:12px;display:flex;align-items:center;gap:12px}
        .current-img-wrap img{width:72px;height:72px;border-radius:10px;object-fit:cover;border:2px solid var(--border)}
        .current-img-lbl{font-size:12px;color:var(--muted);font-weight:600}
        .current-img-lbl strong{display:block;font-size:13px;color:var(--text);margin-bottom:2px}
        .type-pills{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
        .type-pill{padding:14px 8px;border-radius:12px;border:2px solid var(--border);background:var(--surface-2);cursor:pointer;text-align:center;transition:all .15s;user-select:none;color:var(--text)}
        .type-pill:hover{border-color:var(--accent);background:var(--accent-bg)}
        .type-pill.active{border-color:var(--accent);background:var(--accent);color:#fff}
        .type-pill input{display:none}
        .type-pill-icon{font-size:22px;display:block;margin-bottom:5px}
        .type-pill-label{font-size:12px;font-weight:700}
        .spice-row{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--accent-bg);border-radius:10px;border:1.5px solid var(--accent-border);margin-bottom:16px}
        .spice-row-label{font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
        .opts-section-title{font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 10px;display:flex;align-items:center;gap:6px}
        .opts-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:14px}
        @media(max-width:500px){.opts-grid{grid-template-columns:1fr}}
        .opts-cb{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9px;border:1.5px solid var(--border);background:var(--surface-2);cursor:pointer;transition:all .15s;user-select:none}
        .opts-cb:hover{border-color:var(--accent);background:var(--accent-bg)}
        .opts-cb.checked{border-color:var(--accent);background:var(--accent-bg)}
        .opts-cb.custom-item{border-style:dashed}
        .opts-cb.custom-item.checked{border-style:solid}
        .opts-cb input[type="hidden"]{display:none}
        .opts-cb-box{width:18px;height:18px;border-radius:5px;border:2px solid var(--border);background:var(--surface-3);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
        .opts-cb.checked .opts-cb-box{background:var(--accent);border-color:var(--accent)}
        .opts-cb-lbl{font-size:12px;font-weight:600;color:var(--text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .opts-cb-del{flex-shrink:0;width:18px;height:18px;border-radius:4px;border:none;background:transparent;color:var(--muted);font-size:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:color .15s;pointer-events:all}
        .opts-cb-del:hover{color:var(--danger)}
        .addon-price-wrap{display:none;align-items:center;gap:3px;margin-left:auto;flex-shrink:0}
        .addon-price-wrap.show{display:flex}
        .addon-price-pfx{font-size:10px;font-weight:700;color:var(--muted)}
        .addon-price-inp{
            width:54px;padding:3px 5px;
            border:1.5px solid var(--border);border-radius:6px;
            font-size:11px;font-weight:700;color:var(--accent);
            text-align:center;outline:none;
            background:var(--surface-3);transition:border-color .15s;
            -moz-appearance:textfield;
        }
        .addon-price-inp::-webkit-inner-spin-button,
        .addon-price-inp::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
        .addon-price-inp:focus{border-color:var(--accent)}
        .addon-price-inp.ap-over{border-color:var(--danger)!important;color:var(--danger)!important;}
        .custom-add-row{display:flex;gap:6px;margin-bottom:10px;align-items:stretch}
        .custom-add-row input[type="text"]{flex:1;padding:8px 11px;border:1.5px solid var(--border);border-radius:9px;font-size:12px;font-weight:600;color:var(--text);background:var(--surface-2);outline:none;transition:border-color .15s}
        .custom-add-row input[type="text"]:focus{border-color:var(--accent)}
        .new-addon-price{
            width:72px;padding:8px 6px;
            border:1.5px solid var(--border);border-radius:9px;
            font-size:12px;font-weight:700;color:var(--accent);
            background:var(--surface-2);outline:none;
            transition:border-color .15s;text-align:center;
            -moz-appearance:textfield;
        }
        .new-addon-price::-webkit-inner-spin-button,
        .new-addon-price::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
        .new-addon-price:focus{border-color:var(--accent)}
        .new-addon-price.ap-over{border-color:var(--danger)!important;color:var(--danger)!important;}
        .custom-add-btn{padding:8px 14px;border:none;border-radius:9px;background:var(--accent);color:#fff;font-size:12px;font-weight:800;cursor:pointer;transition:opacity .15s;white-space:nowrap;display:flex;align-items:center;gap:5px}
        .custom-add-btn:hover{opacity:.85}
        .hint{font-size:11px;color:var(--muted);margin-top:4px;line-height:1.5}
        .toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;margin-bottom:16px;background:var(--surface-2)}
        .toggle-info .toggle-lbl{font-size:13px;font-weight:700;color:var(--text)}
        .toggle-info .toggle-sub{font-size:11px;color:var(--muted);margin-top:2px}
        .btn-submit{width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;font-size:15px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s,transform .15s;margin-top:4px}
        .btn-submit:hover{opacity:.9;transform:translateY(-1px)}
        .default-notice{display:flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(59,130,246,.08);border:1.5px solid rgba(59,130,246,.25);border-radius:10px;margin-bottom:14px;font-size:12px;font-weight:700;color:#2563eb}
        .section-food{display:block}.section-bev{display:none}.section-dessert{display:none}.section-spice{display:block}
        body[data-ptype="beverage"] .section-food{display:none}
        body[data-ptype="beverage"] .section-bev{display:block}
        body[data-ptype="beverage"] .section-dessert{display:none}
        body[data-ptype="beverage"] .section-spice{display:none}
        body[data-ptype="dessert"] .section-food{display:none}
        body[data-ptype="dessert"] .section-bev{display:none}
        body[data-ptype="dessert"] .section-dessert{display:block}
        body[data-ptype="dessert"] .section-spice{display:none}
        .info-banner{border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px}
        .info-banner-blue{background:var(--info-bg,rgba(91,164,245,.08));border:1.5px solid rgba(91,164,245,.25);color:var(--info,#3b82f6)}
        .info-banner-purple{background:rgba(167,139,250,.08);border:1.5px solid rgba(167,139,250,.25);color:#7c3aed}
        .toggle-switch{position:relative;display:inline-block;width:52px;height:26px}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#ccc;transition:.3s;border-radius:26px}
        .toggle-slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background-color:white;transition:.3s;border-radius:50%}
        input:checked+.toggle-slider{background-color:var(--accent)}
        input:checked+.toggle-slider:before{transform:translateX(26px)}
        @keyframes chipIn{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:scale(1)}}
        .opts-cb.custom-item{animation:chipIn .2s ease}
        #imgError{display:none;margin-top:10px;padding:10px 14px;background:var(--danger-bg);border:1px solid rgba(241,107,107,.3);border-radius:8px;color:var(--danger);font-size:12px;font-weight:600;align-items:center;gap:8px}
        .price-preview{display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;font-weight:700}
        .price-ok{color:var(--success,#16a34a)}
        .price-err{color:var(--danger,#dc2626)}
        .cap-note{font-size:10px;font-weight:500;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:4px;}
    </style>
</head>
<body data-ptype="<?= $edit_prod_type ?>">

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title"><?= $edit ? 'Edit' : 'Add New' ?> <span>Item</span></div>
    </div>
    <div class="content">
        <div class="form-page">

            <a href="<?= htmlspecialchars($back_url) ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Menu Items
            </a>

            <?php if ($flash): ?>
            <div class="flash <?= $flash['type'] ?>" style="margin-bottom:16px;">
                <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="productForm" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
                <?php if ($edit): ?>
                    <input type="hidden" name="productid" value="<?= htmlspecialchars($edit['productid']) ?>">
                <?php endif; ?>

                <!-- ── Basic Information ── -->
                <div class="form-card">
                    <div class="form-card-head"><i class="fas fa-info-circle"></i><h3>Basic Information</h3></div>
                    <div class="form-card-body">
                        <div class="form-cols">

                            <div class="fg">
                                <label>Item Name <span class="req">*</span></label>
                                <input type="text" name="name" id="nameInput" class="fi" required maxlength="50"
                                       placeholder="e.g. Grilled Chicken Rice"
                                       value="<?= htmlspecialchars($edit['name'] ?? '') ?>"
                                       oninput="onNameInput(this)" onblur="onNameBlur(this)">
                                <div class="field-hint" id="nameHint">
                                    <span class="hint-msg">Letters required. Alphanumeric + basic punctuation only.</span>
                                    <span class="hint-counter" id="nameCounter"><?= strlen($edit['name'] ?? '') ?>/50</span>
                                </div>
                            </div>

                            <div class="fg">
                                <label>Category <span class="req">*</span></label>
                                <select name="categoryid" class="fi" required id="categorySelect" onchange="onCategoryChange(this)">
                                    <option value="">— Select category —</option>
                                    <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['categoryid'] ?>" data-name="<?= htmlspecialchars($c['category_name']) ?>"
                                        <?= (isset($edit['categoryid'])&&$edit['categoryid']===$c['categoryid'])?'selected':'' ?>>
                                        <?= htmlspecialchars($c['category_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- PRICE: min RM 0.01, max RM 999.99, max 2 decimal places -->
                            <div class="fg">
                                <label>Price (RM) <span class="req">*</span>
                                    <span style="font-size:10px;font-weight:500;color:var(--muted);text-transform:none;letter-spacing:0;">(RM 0.01 – 999.99)</span>
                                </label>
                                <input type="text" inputmode="decimal" name="price" id="priceInput" class="fi"
                                       required placeholder="0.00"
                                       value="<?= $current_price !== '' ? number_format((float)$current_price,2,'.','') : '' ?>"
                                       oninput="onPriceInput(this)" onblur="onPriceBlur(this)"
                                       onkeydown="blockPriceKeys(event)" onpaste="handlePricePaste(event)">
                                <div class="price-preview" id="pricePreview">
                                    <?php if ($current_price !== '' && (float)$current_price > 0): ?>
                                        <span class="price-ok"><i class="fas fa-check-circle"></i> RM <?= number_format((float)$current_price,2) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="field-hint" id="priceHint" style="display:none;">
                                    <span class="hint-msg" style="color:var(--danger);font-weight:700;">Free products are not allowed. Minimum price is RM 0.01.</span>
                                </div>
                            </div>

                            <!-- STOCK: integer only, no leading zeros, 0–1000 -->
                            <div class="fg">
                                <label>Stock <span class="req">*</span> <span style="font-size:10px;color:var(--muted);font-weight:500;">(0 – 1000)</span></label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*"
                                       name="stock" class="fi" id="stockInput" required
                                       placeholder="e.g. 100" value="<?= $current_stock ?>"
                                       oninput="onStockInput(this)" onblur="onStockBlur(this)"
                                       onkeydown="blockStockKeys(event)" onpaste="handleStockPaste(event)">
                                <div class="stock-presets">
                                    <button type="button" class="stock-preset" onclick="setStock(10)">10</button>
                                    <button type="button" class="stock-preset" onclick="setStock(50)">50</button>
                                    <button type="button" class="stock-preset" onclick="setStock(100)">100</button>
                                    <button type="button" class="stock-preset" onclick="setStock(200)">200</button>
                                    <button type="button" class="stock-preset" onclick="setStock(500)">500</button>
                                </div>
                                <div class="stock-preview" id="stockPreview"></div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ── Image Upload ── -->
                <div class="form-card">
                    <div class="form-card-head">
                        <i class="fas fa-image"></i>
                        <h3>Item Image <?php if($edit): ?><span style="font-size:11px;font-weight:500;color:var(--muted);">(leave empty to keep current)</span><?php endif; ?></h3>
                    </div>
                    <div class="form-card-body">
                        <?php if ($edit && !empty($edit['image'])): ?>
                        <div class="current-img-wrap" id="currentImgWrap">
                            <img src="<?= UPLOADS_URL_PATH.htmlspecialchars($edit['image']) ?>" alt="Current image" onerror="this.style.opacity='.3'">
                            <div class="current-img-lbl"><strong>Current image</strong>Upload a new one below to replace it</div>
                        </div>
                        <?php endif; ?>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/webp">
                            <div class="upload-zone-inner">
                                <div class="upload-icon" id="uploadIcon">📷</div>
                                <div class="upload-text" id="uploadText">Click or drag image here<small>JPG · PNG · WEBP · Max 5MB</small></div>
                            </div>
                        </div>
                        <div class="img-preview-panel" id="imgPreviewPanel">
                            <div class="img-preview-top">
                                <img id="previewImg" src="" alt="Preview">
                                <div class="img-preview-info">
                                    <div class="img-preview-name" id="previewName">—</div>
                                    <div class="img-preview-meta">
                                        <span class="img-meta-badge" id="previewSize">—</span>
                                        <span class="img-meta-badge" id="previewDims">—</span>
                                        <span class="img-meta-badge" id="previewType">—</span>
                                    </div>
                                </div>
                                <button type="button" class="img-preview-remove" onclick="clearImage()"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div id="imgError"><i class="fas fa-exclamation-circle"></i><span id="imgErrorText"></span></div>
                        <div class="path-debug" id="pathDebug">
                            Uploads folder: <code><?= htmlspecialchars(UPLOADS_DIR) ?></code><br>
                            Writable: <strong><?= is_writable(UPLOADS_DIR)?'✅ Yes':(is_dir(UPLOADS_DIR)?'❌ No':'⚠️ Does not exist yet') ?></strong>
                        </div>
                        <button type="button" onclick="document.getElementById('pathDebug').classList.toggle('show')"
                                style="margin-top:8px;font-size:10px;color:var(--muted);background:none;border:none;cursor:pointer;padding:0;">🔧 Debug upload path</button>
                    </div>
                </div>

                <!-- ── Customization Options ── -->
                <div class="form-card">
                    <div class="form-card-head"><i class="fas fa-sliders-h"></i><h3>Customization Options</h3></div>
                    <div class="form-card-body">

                        <?php if (!$edit): ?>
                        <div class="default-notice"><i class="fas fa-info-circle"></i>Default options pre-selected based on category. Customise below.</div>
                        <?php endif; ?>

                        <div class="opts-section-title"><i class="fas fa-tag" style="font-size:10px;"></i> Product Type</div>
                        <div class="type-pills">
                            <label class="type-pill <?= $edit_prod_type==='food'?'active':'' ?>" onclick="setProdType('food',this)">
                                <input type="radio" name="prod_type" value="food" <?= $edit_prod_type==='food'?'checked':'' ?>>
                                <span class="type-pill-icon">🍽</span><span class="type-pill-label">Food</span>
                            </label>
                            <label class="type-pill <?= $edit_prod_type==='beverage'?'active':'' ?>" onclick="setProdType('beverage',this)">
                                <input type="radio" name="prod_type" value="beverage" <?= $edit_prod_type==='beverage'?'checked':'' ?>>
                                <span class="type-pill-icon">🧋</span><span class="type-pill-label">Beverage</span>
                            </label>
                            <label class="type-pill <?= $edit_prod_type==='dessert'?'active':'' ?>" onclick="setProdType('dessert',this)">
                                <input type="radio" name="prod_type" value="dessert" <?= $edit_prod_type==='dessert'?'checked':'' ?>>
                                <span class="type-pill-icon">🍰</span><span class="type-pill-label">Dessert</span>
                            </label>
                        </div>

                        <!-- Spice toggle -->
                        <div class="section-spice">
                            <div class="spice-row">
                                <div class="spice-row-label"><span style="font-size:18px;">🌶</span> Enable Spice Level (Original / Mild / Spicy)</div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="has_spice" id="hasSpiceToggle" <?= !empty($edit_opts['spice'])?'checked':'' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- ══ FOOD ══ -->
                        <div class="section-food">
                            <div class="opts-section-title"><span>🚫</span> Remove Options</div>
                            <div class="opts-grid" id="food-remove-grid">
                                <?php foreach ($common_removes as $r):
                                    $ck=isRemoveSelected($r['key'],$edit_opts);
                                    $jv=htmlspecialchars(json_encode(['key'=>$r['key'],'label'=>$r['label']],JSON_UNESCAPED_UNICODE));
                                ?>
                                <label class="opts-cb <?= $ck?'checked':'' ?>" onclick="toggleRemoveCb(this)">
                                    <?php if($ck): ?><input type="hidden" name="remove_opts[]" value="<?= $jv ?>"><?php endif; ?>
                                    <div class="opts-cb-box"><?php if($ck): ?><?= checkSVG() ?><?php endif; ?></div>
                                    <span class="opts-cb-lbl"><?= $r['label'] ?></span>
                                    <span style="display:none" data-key="<?= $r['key'] ?>" data-label="<?= htmlspecialchars($r['label']) ?>"></span>
                                </label>
                                <?php endforeach; ?>
                                <?php if ($edit_opts): foreach ($edit_opts['remove']??[] as $r): if(!in_array($r['key'],$all_known_remove_keys)):
                                    $jv=htmlspecialchars(json_encode(['key'=>$r['key'],'label'=>$r['label']],JSON_UNESCAPED_UNICODE)); ?>
                                <label class="opts-cb checked custom-item" onclick="toggleRemoveCb(this)">
                                    <input type="hidden" name="remove_opts[]" value="<?= $jv ?>">
                                    <div class="opts-cb-box"><?= checkSVG() ?></div>
                                    <span class="opts-cb-lbl"><?= htmlspecialchars($r['label']) ?></span>
                                    <span style="display:none" data-key="<?= htmlspecialchars($r['key']) ?>" data-label="<?= htmlspecialchars($r['label']) ?>"></span>
                                    <button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>
                                </label>
                                <?php endif;endforeach;endif; ?>
                            </div>
                            <div class="custom-add-row">
                                <input type="text" id="food-remove-label" placeholder="e.g. 🧅 No Spring Onion" maxlength="60">
                                <button type="button" class="custom-add-btn" onclick="addCustomRemove('food')"><i class="fas fa-plus"></i> Add</button>
                            </div>
                            <div class="hint">Type a label then click <strong>Add</strong>.</div>

                            <div class="opts-section-title" style="margin-top:18px;"><span>➕</span> Add-on Options<span class="cap-note">(RM 0.00 free · max RM 10.00)</span></div>
                            <div class="opts-grid" id="food-addon-grid">
                                <?php foreach ($common_addons as $a):
                                    $ck=isAddonSelected($a['key'],$edit_opts);
                                    $pv=(float)ap(getAddonPrice($a['key'],$edit_opts,$a['price']));
                                    $jv=htmlspecialchars(json_encode(['key'=>$a['key'],'label'=>$a['label'],'price'=>$pv],JSON_UNESCAPED_UNICODE));
                                ?>
                                <label class="opts-cb <?= $ck?'checked':'' ?>" onclick="toggleAddonCb(this)">
                                    <?php if($ck): ?><input type="hidden" name="addon_opts[]" value="<?= $jv ?>"><?php endif; ?>
                                    <div class="opts-cb-box"><?php if($ck): ?><?= checkSVG() ?><?php endif; ?></div>
                                    <span class="opts-cb-lbl"><?= $a['label'] ?></span>
                                    <span style="display:none" data-key="<?= $a['key'] ?>" data-label="<?= htmlspecialchars($a['label']) ?>" data-price="<?= $pv ?>"></span>
                                    <div class="addon-price-wrap <?= $ck?'show':'' ?>">
                                        <span class="addon-price-pfx">RM</span>
                                        <input type="text" inputmode="decimal" class="addon-price-inp" value="<?= ap($pv) ?>"
                                               onclick="event.stopPropagation()"
                                               oninput="onAddonPriceInput(this)"
                                               onblur="onAddonPriceBlur(this)"
                                               onkeydown="blockAddonPriceKeys(event)"
                                               onpaste="handleAddonPricePaste(event)">
                                    </div>
                                </label>
                                <?php endforeach; ?>
                                <?php if ($edit_opts): foreach ($edit_opts['addons']??[] as $a): if(!in_array($a['key'],$all_known_addon_keys)):
                                    $pv=(float)ap($a['price']);
                                    $jv=htmlspecialchars(json_encode(['key'=>$a['key'],'label'=>$a['label'],'price'=>$pv],JSON_UNESCAPED_UNICODE)); ?>
                                <label class="opts-cb checked custom-item" onclick="toggleAddonCb(this)">
                                    <input type="hidden" name="addon_opts[]" value="<?= $jv ?>">
                                    <div class="opts-cb-box"><?= checkSVG() ?></div>
                                    <span class="opts-cb-lbl"><?= htmlspecialchars($a['label']) ?></span>
                                    <span style="display:none" data-key="<?= htmlspecialchars($a['key']) ?>" data-label="<?= htmlspecialchars($a['label']) ?>" data-price="<?= $pv ?>"></span>
                                    <div class="addon-price-wrap show">
                                        <span class="addon-price-pfx">RM</span>
                                        <input type="text" inputmode="decimal" class="addon-price-inp" value="<?= ap($pv) ?>"
                                               onclick="event.stopPropagation()"
                                               oninput="onAddonPriceInput(this)" onblur="onAddonPriceBlur(this)"
                                               onkeydown="blockAddonPriceKeys(event)" onpaste="handleAddonPricePaste(event)">
                                    </div>
                                    <button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>
                                </label>
                                <?php endif;endforeach;endif; ?>
                            </div>
                            <div class="custom-add-row">
                                <input type="text" id="food-addon-label" placeholder="e.g. 🍳 Extra Egg" maxlength="60">
                                <input type="text" inputmode="decimal" id="food-addon-price" class="new-addon-price"
                                       placeholder="0.00" value="0.00"
                                       onkeydown="blockAddonPriceKeys(event)"
                                       oninput="onNewAddonPriceInput(this)"
                                       onblur="onNewAddonPriceBlur(this)"
                                       onpaste="handleAddonPricePaste(event)">
                                <button type="button" class="custom-add-btn" onclick="addCustomAddon('food')"><i class="fas fa-plus"></i> Add</button>
                            </div>
                            <div class="hint">RM 0.00 = free add-on · Maximum RM 10.00 per add-on.</div>
                        </div>

                        <!-- ══ BEVERAGE ══ -->
                        <div class="section-bev">
                            <div class="info-banner info-banner-blue"><span style="font-size:18px;">🧋</span>Beverages automatically include <strong>temperature selection</strong>.</div>
                            <div class="opts-section-title"><span>🚫</span> Adjust Options</div>
                            <div class="opts-grid" id="bev-remove-grid">
                                <?php foreach ($bev_removes as $r):
                                    $ck=($edit_prod_type==='beverage')&&isRemoveSelected($r['key'],$edit_opts);
                                    $jv=htmlspecialchars(json_encode(['key'=>$r['key'],'label'=>$r['label']],JSON_UNESCAPED_UNICODE));
                                ?>
                                <label class="opts-cb <?= $ck?'checked':'' ?>" onclick="toggleRemoveCb(this)">
                                    <?php if($ck): ?><input type="hidden" name="remove_opts[]" value="<?= $jv ?>"><?php endif; ?>
                                    <div class="opts-cb-box"><?php if($ck): ?><?= checkSVG() ?><?php endif; ?></div>
                                    <span class="opts-cb-lbl"><?= $r['label'] ?></span>
                                    <span style="display:none" data-key="<?= $r['key'] ?>" data-label="<?= htmlspecialchars($r['label']) ?>"></span>
                                </label>
                                <?php endforeach; ?>
                                <?php if ($edit_opts&&$edit_prod_type==='beverage'): foreach ($edit_opts['remove']??[] as $r): if(!in_array($r['key'],$all_known_remove_keys)):
                                    $jv=htmlspecialchars(json_encode(['key'=>$r['key'],'label'=>$r['label']],JSON_UNESCAPED_UNICODE)); ?>
                                <label class="opts-cb checked custom-item" onclick="toggleRemoveCb(this)">
                                    <input type="hidden" name="remove_opts[]" value="<?= $jv ?>">
                                    <div class="opts-cb-box"><?= checkSVG() ?></div>
                                    <span class="opts-cb-lbl"><?= htmlspecialchars($r['label']) ?></span>
                                    <span style="display:none" data-key="<?= htmlspecialchars($r['key']) ?>" data-label="<?= htmlspecialchars($r['label']) ?>"></span>
                                    <button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>
                                </label>
                                <?php endif;endforeach;endif; ?>
                            </div>
                            <div class="custom-add-row">
                                <input type="text" id="bev-remove-label" placeholder="e.g. 🍯 Less Honey" maxlength="60">
                                <button type="button" class="custom-add-btn" onclick="addCustomRemove('bev')"><i class="fas fa-plus"></i> Add</button>
                            </div>
                            <div class="hint">Type a label then click <strong>Add</strong>.</div>

                            <div class="opts-section-title" style="margin-top:18px;"><span>➕</span> Add-on Options<span class="cap-note">(RM 0.00 free · max RM 10.00)</span></div>
                            <div class="opts-grid" id="bev-addon-grid">
                                <?php foreach ($bev_addons as $a):
                                    $ck=($edit_prod_type==='beverage')&&isAddonSelected($a['key'],$edit_opts);
                                    $pv=(float)ap(($edit_prod_type==='beverage')?getAddonPrice($a['key'],$edit_opts,$a['price']):$a['price']);
                                    $jv=htmlspecialchars(json_encode(['key'=>$a['key'],'label'=>$a['label'],'price'=>$pv],JSON_UNESCAPED_UNICODE));
                                ?>
                                <label class="opts-cb <?= $ck?'checked':'' ?>" onclick="toggleAddonCb(this)">
                                    <?php if($ck): ?><input type="hidden" name="addon_opts[]" value="<?= $jv ?>"><?php endif; ?>
                                    <div class="opts-cb-box"><?php if($ck): ?><?= checkSVG() ?><?php endif; ?></div>
                                    <span class="opts-cb-lbl"><?= $a['label'] ?></span>
                                    <span style="display:none" data-key="<?= $a['key'] ?>" data-label="<?= htmlspecialchars($a['label']) ?>" data-price="<?= $pv ?>"></span>
                                    <div class="addon-price-wrap <?= $ck?'show':'' ?>">
                                        <span class="addon-price-pfx">RM</span>
                                        <input type="text" inputmode="decimal" class="addon-price-inp" value="<?= ap($pv) ?>"
                                               onclick="event.stopPropagation()"
                                               oninput="onAddonPriceInput(this)" onblur="onAddonPriceBlur(this)"
                                               onkeydown="blockAddonPriceKeys(event)" onpaste="handleAddonPricePaste(event)">
                                    </div>
                                </label>
                                <?php endforeach; ?>
                                <?php if ($edit_opts&&$edit_prod_type==='beverage'): foreach ($edit_opts['addons']??[] as $a): if(!in_array($a['key'],$all_known_addon_keys)):
                                    $pv=(float)ap($a['price']);
                                    $jv=htmlspecialchars(json_encode(['key'=>$a['key'],'label'=>$a['label'],'price'=>$pv],JSON_UNESCAPED_UNICODE)); ?>
                                <label class="opts-cb checked custom-item" onclick="toggleAddonCb(this)">
                                    <input type="hidden" name="addon_opts[]" value="<?= $jv ?>">
                                    <div class="opts-cb-box"><?= checkSVG() ?></div>
                                    <span class="opts-cb-lbl"><?= htmlspecialchars($a['label']) ?></span>
                                    <span style="display:none" data-key="<?= htmlspecialchars($a['key']) ?>" data-label="<?= htmlspecialchars($a['label']) ?>" data-price="<?= $pv ?>"></span>
                                    <div class="addon-price-wrap show">
                                        <span class="addon-price-pfx">RM</span>
                                        <input type="text" inputmode="decimal" class="addon-price-inp" value="<?= ap($pv) ?>"
                                               onclick="event.stopPropagation()"
                                               oninput="onAddonPriceInput(this)" onblur="onAddonPriceBlur(this)"
                                               onkeydown="blockAddonPriceKeys(event)" onpaste="handleAddonPricePaste(event)">
                                    </div>
                                    <button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>
                                </label>
                                <?php endif;endforeach;endif; ?>
                            </div>
                            <div class="custom-add-row">
                                <input type="text" id="bev-addon-label" placeholder="e.g. 🧃 Extra Serving" maxlength="60">
                                <input type="text" inputmode="decimal" id="bev-addon-price" class="new-addon-price"
                                       placeholder="0.00" value="0.00"
                                       onkeydown="blockAddonPriceKeys(event)"
                                       oninput="onNewAddonPriceInput(this)" onblur="onNewAddonPriceBlur(this)"
                                       onpaste="handleAddonPricePaste(event)">
                                <button type="button" class="custom-add-btn" onclick="addCustomAddon('bev')"><i class="fas fa-plus"></i> Add</button>
                            </div>
                            <div class="hint">RM 0.00 = free add-on · Maximum RM 10.00 per add-on.</div>
                        </div>

                        <!-- ══ DESSERT ══ -->
                        <div class="section-dessert">
                            <div class="info-banner info-banner-purple"><span style="font-size:18px;">🍰</span>Desserts automatically include <strong>serving time selection</strong>.</div>
                            <div class="opts-section-title"><span>➕</span> Add-on Options<span class="cap-note">(RM 0.00 free · max RM 10.00)</span></div>
                            <div class="opts-grid" id="dessert-addon-grid">
                                <?php foreach ($dessert_addons as $a):
                                    $ck=($edit_prod_type==='dessert')&&isAddonSelected($a['key'],$edit_opts);
                                    $pv=(float)ap(($edit_prod_type==='dessert')?getAddonPrice($a['key'],$edit_opts,$a['price']):$a['price']);
                                    $jv=htmlspecialchars(json_encode(['key'=>$a['key'],'label'=>$a['label'],'price'=>$pv],JSON_UNESCAPED_UNICODE));
                                ?>
                                <label class="opts-cb <?= $ck?'checked':'' ?>" onclick="toggleAddonCb(this)">
                                    <?php if($ck): ?><input type="hidden" name="addon_opts[]" value="<?= $jv ?>"><?php endif; ?>
                                    <div class="opts-cb-box"><?php if($ck): ?><?= checkSVG() ?><?php endif; ?></div>
                                    <span class="opts-cb-lbl"><?= $a['label'] ?></span>
                                    <span style="display:none" data-key="<?= $a['key'] ?>" data-label="<?= htmlspecialchars($a['label']) ?>" data-price="<?= $pv ?>"></span>
                                    <div class="addon-price-wrap <?= $ck?'show':'' ?>">
                                        <span class="addon-price-pfx">RM</span>
                                        <input type="text" inputmode="decimal" class="addon-price-inp" value="<?= ap($pv) ?>"
                                               onclick="event.stopPropagation()"
                                               oninput="onAddonPriceInput(this)" onblur="onAddonPriceBlur(this)"
                                               onkeydown="blockAddonPriceKeys(event)" onpaste="handleAddonPricePaste(event)">
                                    </div>
                                </label>
                                <?php endforeach; ?>
                                <?php if ($edit_opts&&$edit_prod_type==='dessert'): foreach ($edit_opts['addons']??[] as $a): if(!in_array($a['key'],$all_known_addon_keys)):
                                    $pv=(float)ap($a['price']);
                                    $jv=htmlspecialchars(json_encode(['key'=>$a['key'],'label'=>$a['label'],'price'=>$pv],JSON_UNESCAPED_UNICODE)); ?>
                                <label class="opts-cb checked custom-item" onclick="toggleAddonCb(this)">
                                    <input type="hidden" name="addon_opts[]" value="<?= $jv ?>">
                                    <div class="opts-cb-box"><?= checkSVG() ?></div>
                                    <span class="opts-cb-lbl"><?= htmlspecialchars($a['label']) ?></span>
                                    <span style="display:none" data-key="<?= htmlspecialchars($a['key']) ?>" data-label="<?= htmlspecialchars($a['label']) ?>" data-price="<?= $pv ?>"></span>
                                    <div class="addon-price-wrap show">
                                        <span class="addon-price-pfx">RM</span>
                                        <input type="text" inputmode="decimal" class="addon-price-inp" value="<?= ap($pv) ?>"
                                               onclick="event.stopPropagation()"
                                               oninput="onAddonPriceInput(this)" onblur="onAddonPriceBlur(this)"
                                               onkeydown="blockAddonPriceKeys(event)" onpaste="handleAddonPricePaste(event)">
                                    </div>
                                    <button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>
                                </label>
                                <?php endif;endforeach;endif; ?>
                            </div>
                            <div class="custom-add-row">
                                <input type="text" id="dessert-addon-label" placeholder="e.g. 🍓 Extra Berries" maxlength="60">
                                <input type="text" inputmode="decimal" id="dessert-addon-price" class="new-addon-price"
                                       placeholder="0.00" value="0.00"
                                       onkeydown="blockAddonPriceKeys(event)"
                                       oninput="onNewAddonPriceInput(this)" onblur="onNewAddonPriceBlur(this)"
                                       onpaste="handleAddonPricePaste(event)">
                                <button type="button" class="custom-add-btn" onclick="addCustomAddon('dessert')"><i class="fas fa-plus"></i> Add</button>
                            </div>
                            <div class="hint">RM 0.00 = free add-on · Maximum RM 10.00 per add-on.</div>
                            <div class="opts-section-title" style="margin-top:18px;"><span>🚫</span> Remove / Adjust Options</div>
                            <div class="opts-grid" id="dessert-remove-grid">
                                <?php if ($edit_opts&&$edit_prod_type==='dessert'): foreach ($edit_opts['remove']??[] as $r): if(!in_array($r['key'],$all_known_remove_keys)):
                                    $jv=htmlspecialchars(json_encode(['key'=>$r['key'],'label'=>$r['label']],JSON_UNESCAPED_UNICODE)); ?>
                                <label class="opts-cb checked custom-item" onclick="toggleRemoveCb(this)">
                                    <input type="hidden" name="remove_opts[]" value="<?= $jv ?>">
                                    <div class="opts-cb-box"><?= checkSVG() ?></div>
                                    <span class="opts-cb-lbl"><?= htmlspecialchars($r['label']) ?></span>
                                    <span style="display:none" data-key="<?= htmlspecialchars($r['key']) ?>" data-label="<?= htmlspecialchars($r['label']) ?>"></span>
                                    <button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>
                                </label>
                                <?php endif;endforeach;endif; ?>
                            </div>
                            <div class="custom-add-row">
                                <input type="text" id="dessert-remove-label" placeholder="e.g. 🥛 No Cream" maxlength="60">
                                <button type="button" class="custom-add-btn" onclick="addCustomRemove('dessert')"><i class="fas fa-plus"></i> Add</button>
                            </div>
                            <div class="hint">Type a label then click <strong>Add</strong>.</div>
                        </div>

                    </div>
                </div>

                <!-- ── Availability ── -->
                <div class="form-card">
                    <div class="form-card-head"><i class="fas fa-eye"></i><h3>Availability</h3></div>
                    <div class="form-card-body">
                        <div class="toggle-row">
                            <div class="toggle-info">
                                <div class="toggle-lbl">Available on Menu</div>
                                <div class="toggle-sub">Customers can see and order this item</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_available" <?= (!$edit||$edit['is_available'])?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-<?= $edit?'save':'plus' ?>"></i>
                            <?= $edit?'Save Changes':'Add to Menu' ?>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
const MAX_PRICE   = 999.99;
const MIN_PRICE   = 0.01;
const MAX_STOCK   = 1000;
const MAX_ADDON   = 10.00;
const MIN_ADDON   = 0.00;
const CHECK_SVG   = '<svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1.5,5 4,7.5 8.5,2.5" stroke="white" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>';
const CAT_BEVERAGE_IDS = ['CAT012'];
const CAT_DESSERT_IDS  = ['CAT011'];

/* ═══════════════════════════════════════════════════════════
   SHARED 2-DECIMAL HELPERS
   ─────────────────────────────────────────────────────────
   _enforce2dp   – strips any digit beyond 2dp while typing
   _clampAndFix  – parse → clamp → toFixed(2) on blur
   _block2dpKey  – keydown guard: blocks a digit key when
                   the cursor sits after 2 decimals already
   ═══════════════════════════════════════════════════════════ */

/**
 * Strip characters beyond 2 decimal places from the raw string value.
 * Called on every oninput so the field never holds >2dp.
 */
function _enforce2dp(inp) {
    const val = inp.value;
    const dot  = val.indexOf('.');
    if (dot !== -1 && val.length - dot - 1 > 2) {
        // Preserve cursor position as best we can
        const sel = inp.selectionStart;
        inp.value = val.slice(0, dot + 3);          // keep up to 2dp
        inp.setSelectionRange(
            Math.min(sel, inp.value.length),
            Math.min(sel, inp.value.length)
        );
    }
}

/**
 * Keydown guard shared by price + addon price inputs.
 * Blocks typing a digit when 2 decimal places already exist
 * AND the cursor is not inside the integer part.
 */
function _block2dpKey(e) {
    if (!/^\d$/.test(e.key)) return;           // not a digit → skip
    const inp = e.target;
    const val  = inp.value;
    const dot  = val.indexOf('.');
    if (dot === -1) return;                    // no dot yet → allow
    const selStart = inp.selectionStart;
    const selEnd   = inp.selectionEnd;
    // Only block if cursor is strictly after the dot
    // AND there are already 2 decimal digits AND nothing is selected
    if (selStart === selEnd && selStart > dot && (val.length - dot - 1) >= 2) {
        e.preventDefault();
    }
}

/* ═══════════════════════════════════════════════════════════
   NAME VALIDATION
   ═══════════════════════════════════════════════════════════ */
const NAME_MAX     = 50;
const NAME_ALLOWED = /^[A-Za-z0-9\s\-'&.()\[\]\/]+$/;
const NAME_HAS_LTR = /[A-Za-z]/;

function onNameInput(inp) {
    const val = inp.value, len = val.length;
    const hint = document.getElementById('nameHint');
    const ctr  = document.getElementById('nameCounter');
    ctr.textContent  = len + '/' + NAME_MAX;
    ctr.className    = 'hint-counter ' + (len >= NAME_MAX ? 'warn' : 'ok');
    if (!len) {
        inp.classList.remove('fi-error');
        hint.className = 'field-hint';
        hint.querySelector('.hint-msg').textContent = 'Letters required. Alphanumeric + basic punctuation only.';
        return;
    }
    const err = getNameError(val);
    if (err) {
        inp.classList.add('fi-error');
        hint.className = 'field-hint error';
        hint.querySelector('.hint-msg').textContent = err;
    } else {
        inp.classList.remove('fi-error');
        hint.className = 'field-hint';
        hint.querySelector('.hint-msg').textContent = 'Letters required. Alphanumeric + basic punctuation only.';
    }
}
function onNameBlur(inp) { onNameInput(inp); }
function getNameError(val) {
    val = val.trim();
    if (!val)              return 'Item name is required.';
    if (val.length > NAME_MAX) return 'Max ' + NAME_MAX + ' characters.';
    if (!NAME_HAS_LTR.test(val)) return 'Must contain at least one letter — cannot be numbers only.';
    if (!NAME_ALLOWED.test(val)) return "Only letters, numbers, spaces, and basic punctuation ( - ' & . ) are allowed.";
    return null;
}

/* ═══════════════════════════════════════════════════════════
   PRODUCT PRICE  (RM 0.01 – 999.99, max 2dp)
   ═══════════════════════════════════════════════════════════ */

/** Block e/E/+/- and also 3rd decimal digit */
function blockPriceKeys(e) {
    if (['e', 'E', '+', '-'].includes(e.key)) { e.preventDefault(); return; }
    _block2dpKey(e);
}

function handlePricePaste(e) {
    e.preventDefault();
    const raw   = (e.clipboardData || window.clipboardData).getData('text');
    // Keep only digits and the first dot
    const clean = raw.replace(/[^0-9.]/g, '').replace(/^(\d*\.?\d{0,2}).*$/, '$1');
    const val   = parseFloat(clean);
    if (!isNaN(val) && val > 0) {
        e.target.value = Math.min(MAX_PRICE, val).toFixed(2);
    } else {
        e.target.value = clean;   // let onPriceInput show error
    }
    onPriceInput(e.target);
}

function onPriceInput(inp) {
    _enforce2dp(inp);                           // strip >2dp immediately
    const raw = inp.value;
    const val = parseFloat(raw);
    const prev = document.getElementById('pricePreview');
    const hint = document.getElementById('priceHint');

    if (raw === '') {
        prev.innerHTML = ''; hint.style.display = 'none';
        inp.classList.remove('fi-error'); return;
    }
    if (isNaN(val) || val <= 0) {
        inp.classList.add('fi-error');
        prev.innerHTML = '<span class="price-err"><i class="fas fa-exclamation-circle"></i> Free products are not allowed</span>';
        hint.style.display = 'flex'; return;
    }
    hint.style.display = 'none';
    if (val > MAX_PRICE) {
        inp.value = MAX_PRICE.toFixed(2);
        inp.classList.add('fi-error');
        prev.innerHTML = '<span class="price-err"><i class="fas fa-exclamation-circle"></i> Maximum price is RM 999.99</span>';
        setTimeout(() => {
            inp.classList.remove('fi-error');
            prev.innerHTML = '<span class="price-ok"><i class="fas fa-check-circle"></i> RM ' + parseFloat(inp.value).toFixed(2) + '</span>';
        }, 1500);
        return;
    }
    inp.classList.remove('fi-error');
    prev.innerHTML = '<span class="price-ok"><i class="fas fa-check-circle"></i> RM ' + val.toFixed(2) + '</span>';
}

function onPriceBlur(inp) {
    const prev = document.getElementById('pricePreview');
    const hint = document.getElementById('priceHint');
    // Fix leading dot: ".5" → "0.5"
    if (inp.value.startsWith('.')) inp.value = '0' + inp.value;
    const val = parseFloat(inp.value);
    if (inp.value === '' || isNaN(val)) {
        inp.classList.add('fi-error');
        prev.innerHTML = '<span class="price-err"><i class="fas fa-exclamation-circle"></i> Price is required</span>';
        hint.style.display = 'flex';
        hint.querySelector('.hint-msg').textContent = 'Price is required. Minimum is RM 0.01.';
        return;
    }
    if (val < MIN_PRICE) {
        inp.value = '';
        inp.classList.add('fi-error');
        prev.innerHTML = '<span class="price-err"><i class="fas fa-exclamation-circle"></i> Free products are not allowed. Enter at least RM 0.01.</span>';
        hint.style.display = 'flex';
        hint.querySelector('.hint-msg').textContent = 'Free products are not allowed. Minimum price is RM 0.01.';
        return;
    }
    inp.value = Math.min(MAX_PRICE, val).toFixed(2);
    inp.classList.remove('fi-error');
    hint.style.display = 'none';
    prev.innerHTML = '<span class="price-ok"><i class="fas fa-check-circle"></i> RM ' + inp.value + '</span>';
}

/* ═══════════════════════════════════════════════════════════
   ADDON PRICE  (RM 0.00 – 10.00, max 2dp)
   ═══════════════════════════════════════════════════════════ */

function clampAddon(v) {
    return Math.min(MAX_ADDON, Math.max(MIN_ADDON, isNaN(v) ? 0 : v));
}

/** Block e/E/+/- and also 3rd decimal digit for addon inputs */
function blockAddonPriceKeys(e) {
    if (['e', 'E', '+', '-'].includes(e.key)) { e.preventDefault(); return; }
    _block2dpKey(e);
}

function handleAddonPricePaste(e) {
    e.preventDefault();
    const raw   = (e.clipboardData || window.clipboardData).getData('text');
    const clean = raw.replace(/[^0-9.]/g, '').replace(/^(\d*\.?\d{0,2}).*$/, '$1');
    const val   = parseFloat(clean);
    e.target.value = clampAddon(isNaN(val) ? 0 : val).toFixed(2);
    if (e.target.classList.contains('addon-price-inp')) updateAddonPrice(e.target);
}

/** Existing checkbox addon price — oninput */
function onAddonPriceInput(inp) {
    _enforce2dp(inp);                           // strip >2dp immediately
    const val = parseFloat(inp.value);
    if (!isNaN(val) && val > MAX_ADDON) {
        inp.value = MAX_ADDON.toFixed(2);
        inp.classList.add('ap-over');
        setTimeout(() => inp.classList.remove('ap-over'), 900);
    } else {
        inp.classList.remove('ap-over');
    }
    updateAddonPrice(inp);
}

/** Existing checkbox addon price — onblur */
function onAddonPriceBlur(inp) {
    let raw = inp.value.trim();
    if (raw === '' || raw === '.') raw = '0';
    if (raw.startsWith('.'))       raw = '0' + raw;
    inp.value = clampAddon(parseFloat(raw)).toFixed(2);
    inp.classList.remove('ap-over');
    updateAddonPrice(inp);
}

/** Custom-add-row "new addon" price — oninput */
function onNewAddonPriceInput(inp) {
    _enforce2dp(inp);                           // strip >2dp immediately
    const val = parseFloat(inp.value);
    if (!isNaN(val) && val > MAX_ADDON) {
        inp.value = MAX_ADDON.toFixed(2);
        inp.classList.add('ap-over');
        setTimeout(() => inp.classList.remove('ap-over'), 900);
    } else {
        inp.classList.remove('ap-over');
    }
}

/** Custom-add-row "new addon" price — onblur */
function onNewAddonPriceBlur(inp) {
    let raw = inp.value.trim();
    if (raw === '' || raw === '.') raw = '0';
    if (raw.startsWith('.'))       raw = '0' + raw;
    inp.value = clampAddon(parseFloat(raw)).toFixed(2);
    inp.classList.remove('ap-over');
}

/* ═══════════════════════════════════════════════════════════
   STOCK  (integer 0 – 1000, no decimals, no leading zeros)
   ═══════════════════════════════════════════════════════════ */
const STOCK_BLOCKED = ['e', 'E', '+', '-', '.'];

function blockStockKeys(e) {
    const nav = ['Backspace','Delete','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Tab','Home','End'];
    if (nav.includes(e.key)) return;
    if (STOCK_BLOCKED.includes(e.key)) { e.preventDefault(); return; }
    if (!/^\d$/.test(e.key)) e.preventDefault();
}

function handleStockPaste(e) {
    e.preventDefault();
    const clean = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
    if (!clean) return;
    e.target.value = Math.min(MAX_STOCK, Math.max(0, parseInt(clean, 10))).toString();
    onStockInput(e.target);
}

function onStockInput(inp) {
    const digits = inp.value.replace(/\D/g, '');
    if (digits !== inp.value) inp.value = digits;
    if (digits.length > 1 && digits.startsWith('0')) {
        const s = String(parseInt(digits, 10));
        inp.value = s;
        renderStockPreview(parseInt(s, 10));
        return;
    }
    let num = parseInt(digits, 10);
    if (isNaN(num)) { renderStockPreview(0); return; }
    if (num > MAX_STOCK) { num = MAX_STOCK; inp.value = MAX_STOCK.toString(); }
    renderStockPreview(num);
}

function onStockBlur(inp) {
    if (!inp.value) inp.value = '0';
    const num = Math.min(MAX_STOCK, Math.max(0, parseInt(inp.value, 10) || 0));
    inp.value = num.toString();
    renderStockPreview(num);
}

function renderStockPreview(num) {
    const p = document.getElementById('stockPreview'); if (!p) return;
    if (num <= 0)
        p.innerHTML = 'Preview: <span class="stock-badge stock-out"><i class="fas fa-ban"></i> Out of Stock</span>';
    else if (num <= 10)
        p.innerHTML = 'Preview: <span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle"></i> Low: ' + num + '</span>';
    else
        p.innerHTML = 'Preview: <span class="stock-badge stock-ok"><i class="fas fa-check"></i> ' + num + ' left</span>';
}

function setStock(val) {
    const i = document.getElementById('stockInput');
    i.value = Math.min(MAX_STOCK, Math.max(0, val)).toString();
    renderStockPreview(parseInt(i.value, 10));
}

/* ═══════════════════════════════════════════════════════════
   FORM SUBMIT VALIDATION
   ═══════════════════════════════════════════════════════════ */
function validateForm() {
    // Name
    const ni = document.getElementById('nameInput');
    const ne = getNameError(ni.value);
    if (ne) { ni.classList.add('fi-error'); ni.focus(); alert(ne); return false; }

    // Product price
    const pi = document.getElementById('priceInput');
    if (pi.value.startsWith('.')) pi.value = '0' + pi.value;
    const pv = parseFloat(pi.value);
    if (isNaN(pv) || pv < MIN_PRICE) {
        pi.classList.add('fi-error'); pi.value = ''; pi.focus();
        alert('Price must be at least RM 0.01. Free products are not allowed.');
        return false;
    }
    if (pv > MAX_PRICE) {
        pi.value = MAX_PRICE.toFixed(2); pi.classList.add('fi-error'); pi.focus();
        alert('Price cannot exceed RM 999.99.');
        return false;
    }
    // Ensure exactly 2dp on submit
    pi.value = pv.toFixed(2);

    // Stock
    const si = document.getElementById('stockInput');
    const sv = parseInt(si.value, 10);
    if (isNaN(sv) || sv < 0 || sv > MAX_STOCK) {
        si.classList.add('fi-error'); si.focus();
        alert('Stock must be a whole number between 0 and 1000.');
        return false;
    }
    si.value = sv.toString();

    // Addon prices — clamp & normalise all to 2dp on submit
    document.querySelectorAll('.addon-price-inp').forEach(inp => {
        let v = parseFloat(inp.value);
        inp.value = clampAddon(isNaN(v) ? 0 : v).toFixed(2);
        updateAddonPrice(inp);
    });

    return true;
}

/* ═══════════════════════════════════════════════════════════
   CATEGORY / PRODUCT TYPE
   ═══════════════════════════════════════════════════════════ */
function onCategoryChange(select) {
    if (<?= $edit ? 'true' : 'false' ?>) return;
    const val = select.value;
    if (CAT_BEVERAGE_IDS.includes(val))
        setProdType('beverage', document.querySelector('.type-pill[onclick*="beverage"]'));
    else if (CAT_DESSERT_IDS.includes(val))
        setProdType('dessert', document.querySelector('.type-pill[onclick*="dessert"]'));
    else
        setProdType('food', document.querySelector('.type-pill[onclick*="food"]'));
}

function setProdType(type, label) {
    document.querySelectorAll('.type-pill').forEach(p => p.classList.remove('active'));
    if (label) label.classList.add('active');
    document.body.dataset.ptype = type;
}

/* ═══════════════════════════════════════════════════════════
   IMAGE UPLOAD
   ═══════════════════════════════════════════════════════════ */
const imageInput = document.getElementById('imageInput');
const panel      = document.getElementById('imgPreviewPanel');
const zone       = document.getElementById('uploadZone');

if (imageInput) {
    imageInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        hideImgError();
        if (!file) { clearImage(); return; }
        if (file.size > 5 * 1024 * 1024) {
            showImgError('File too large (' + (file.size / 1024 / 1024).toFixed(1) + 'MB). Max 5MB.');
            this.value = ''; clearImage(); return;
        }
        const r = new FileReader();
        r.onload = function (e) {
            document.getElementById('previewImg').src = e.target.result;
            panel.classList.add('show');
            document.getElementById('previewName').textContent = file.name;
            document.getElementById('previewSize').textContent = formatBytes(file.size);
            document.getElementById('previewType').textContent = file.type.split('/')[1].toUpperCase();
            const t = new Image();
            t.onload = function () {
                document.getElementById('previewDims').textContent = t.naturalWidth + '×' + t.naturalHeight + 'px';
            };
            t.src = e.target.result;
            document.getElementById('uploadIcon').textContent = '✅';
            document.getElementById('uploadText').innerHTML = '<strong style="color:var(--accent)">Image selected!</strong><small>Click above to change</small>';
            const c = document.getElementById('currentImgWrap'); if (c) c.style.opacity = '0.4';
        };
        r.readAsDataURL(file);
    });
}

if (zone) {
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('dragover');
        if (!e.dataTransfer?.files?.[0]) return;
        try {
            const dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
            imageInput.files = dt.files; imageInput.dispatchEvent(new Event('change'));
        } catch (err) {
            const f = e.dataTransfer.files[0];
            if (['image/jpeg','image/png','image/webp'].includes(f.type)) {
                const r = new FileReader();
                r.onload = ev => { document.getElementById('previewImg').src = ev.target.result; panel.classList.add('show'); };
                r.readAsDataURL(f);
            }
        }
    });
}

function clearImage() {
    if (imageInput) imageInput.value = '';
    if (panel) panel.classList.remove('show');
    document.getElementById('uploadIcon').textContent = '📷';
    document.getElementById('uploadText').innerHTML = 'Click or drag image here<small>JPG · PNG · WEBP · Max 5MB</small>';
    const c = document.getElementById('currentImgWrap'); if (c) c.style.opacity = '1';
}
function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1024 * 1024) return (b / 1024).toFixed(0) + ' KB';
    return (b / 1024 / 1024).toFixed(1) + ' MB';
}
function showImgError(msg) {
    const b = document.getElementById('imgError');
    document.getElementById('imgErrorText').textContent = msg;
    b.style.display = 'flex';
}
function hideImgError() { document.getElementById('imgError').style.display = 'none'; }

/* ═══════════════════════════════════════════════════════════
   CHECKBOX TOGGLE LOGIC
   ═══════════════════════════════════════════════════════════ */
function _getMeta(label) {
    const s = label.querySelector('span[data-key]'); return s ? s.dataset : null;
}
function _setHidden(label, name, value) {
    let inp = label.querySelector('input[type="hidden"]');
    if (!inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = name; label.insertBefore(inp, label.firstChild); }
    inp.value = value;
}
function _removeHidden(label) {
    const inp = label.querySelector('input[type="hidden"]'); if (inp) inp.remove();
}

function toggleRemoveCb(label) {
    if (event?.target?.classList.contains('opts-cb-del')) return;
    const meta = _getMeta(label); if (!meta) return;
    const box = label.querySelector('.opts-cb-box');
    const on  = label.classList.toggle('checked');
    box.innerHTML = on ? CHECK_SVG : '';
    if (on) _setHidden(label, 'remove_opts[]', JSON.stringify({ key: meta.key, label: meta.label }));
    else    _removeHidden(label);
}

function toggleAddonCb(label) {
    if (event?.target?.classList.contains('opts-cb-del'))       return;
    if (event?.target?.classList.contains('addon-price-inp'))   return;
    const meta = _getMeta(label); if (!meta) return;
    const box  = label.querySelector('.opts-cb-box');
    const pw   = label.querySelector('.addon-price-wrap');
    const pinp = label.querySelector('.addon-price-inp');
    const on   = label.classList.toggle('checked');
    box.innerHTML = on ? CHECK_SVG : '';
    if (pw) pw.classList.toggle('show', on);
    if (on) {
        let price = pinp ? parseFloat(pinp.value) : parseFloat(meta.price) || 0;
        price = clampAddon(price);
        if (pinp) pinp.value = price.toFixed(2);
        _setHidden(label, 'addon_opts[]', JSON.stringify({ key: meta.key, label: meta.label, price }));
    } else {
        _removeHidden(label);
    }
}

function updateAddonPrice(inp) {
    const label = inp.closest('.opts-cb');
    if (!label || !label.classList.contains('checked')) return;
    const meta  = _getMeta(label); if (!meta) return;
    const price = clampAddon(parseFloat(inp.value) || 0);
    _setHidden(label, 'addon_opts[]', JSON.stringify({ key: meta.key, label: meta.label, price }));
}

function deleteChip(e, btn) {
    e.stopPropagation();
    const chip = btn.closest('.opts-cb');
    chip.style.transition = 'opacity .15s,transform .15s';
    chip.style.opacity    = '0';
    chip.style.transform  = 'scale(0.85)';
    setTimeout(() => chip.remove(), 150);
}

function addCustomRemove(section) {
    const li  = document.getElementById(section + '-remove-label');
    const lbl = li.value.trim(); if (!lbl) { li.focus(); return; }
    const key  = 'no_' + lbl.replace(/[^a-z0-9]/gi, '_').toLowerCase().replace(/^_+|_+$/g, '');
    const grid = document.getElementById(section + '-remove-grid');
    const data = JSON.stringify({ key, label: lbl });
    const chip = document.createElement('label');
    chip.className = 'opts-cb checked custom-item';
    chip.setAttribute('onclick', 'toggleRemoveCb(this)');
    chip.innerHTML = `<input type="hidden" name="remove_opts[]" value="${escHtml(data)}"><div class="opts-cb-box">${CHECK_SVG}</div><span class="opts-cb-lbl">${escHtml(lbl)}</span><span style="display:none" data-key="${escHtml(key)}" data-label="${escHtml(lbl)}"></span><button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>`;
    grid.appendChild(chip);
    li.value = ''; li.focus();
}

function addCustomAddon(section) {
    const li   = document.getElementById(section + '-addon-label');
    const pi   = document.getElementById(section + '-addon-price');
    const lbl  = li.value.trim(); if (!lbl) { li.focus(); return; }
    // Clamp to 2dp and [0.00, 10.00]
    let price  = clampAddon(parseFloat(pi.value) || 0);
    price      = parseFloat(price.toFixed(2));
    const key  = 'add_' + lbl.replace(/[^a-z0-9]/gi, '_').toLowerCase().replace(/^_+|_+$/g, '');
    const grid = document.getElementById(section + '-addon-grid');
    const data = JSON.stringify({ key, label: lbl, price });
    const chip = document.createElement('label');
    chip.className = 'opts-cb checked custom-item';
    chip.setAttribute('onclick', 'toggleAddonCb(this)');
    chip.innerHTML = `<input type="hidden" name="addon_opts[]" value="${escHtml(data)}"><div class="opts-cb-box">${CHECK_SVG}</div><span class="opts-cb-lbl">${escHtml(lbl)}</span><span style="display:none" data-key="${escHtml(key)}" data-label="${escHtml(lbl)}" data-price="${price}"></span><div class="addon-price-wrap show"><span class="addon-price-pfx">RM</span><input type="text" inputmode="decimal" class="addon-price-inp" value="${price.toFixed(2)}" onclick="event.stopPropagation()" oninput="onAddonPriceInput(this)" onblur="onAddonPriceBlur(this)" onkeydown="blockAddonPriceKeys(event)" onpaste="handleAddonPricePaste(event)"></div><button type="button" class="opts-cb-del" onclick="deleteChip(event,this)">✕</button>`;
    grid.appendChild(chip);
    pi.value = '0.00'; li.value = ''; li.focus();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ═══════════════════════════════════════════════════════════
   INIT
   ═══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    // Stock preview
    const si = document.getElementById('stockInput'); if (si) onStockBlur(si);
    // Price preview
    const pi = document.getElementById('priceInput'); if (pi && pi.value) onPriceInput(pi);
    // Name counter
    const ni = document.getElementById('nameInput');  if (ni && ni.value) onNameInput(ni);

    // Clamp + normalise any existing addon prices that may exceed 10 or have >2dp (stale DB data)
    document.querySelectorAll('.addon-price-inp').forEach(inp => {
        let v = parseFloat(inp.value);
        inp.value = clampAddon(isNaN(v) ? 0 : v).toFixed(2);
    });

    // Enter key in custom-add text inputs triggers the Add button
    document.querySelectorAll('.custom-add-row input[type="text"]').forEach(i => {
        i.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('.custom-add-row').querySelector('.custom-add-btn').click();
            }
        });
    });
});
</script>
</body>
</html>
<?php
function checkSVG(): string {
    return '<svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1.5,5 4,7.5 8.5,2.5" stroke="white" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>';
}
?>
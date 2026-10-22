<?php
/* ============================================================
   admin_profile.php — FOODTIME Admin Profile
   Editable: phone, address, password, profile picture
   Locked:   username, email (display only)
   ============================================================ */
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id      = $_SESSION['user_id'] ?? '';
$admin_id     = $user_id;  // ← FIX: sidebar needs $admin_id
$admin_name   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin');
$success_msg  = '';
$error_msg    = '';
$current_page = 'profile';

// ── Sidebar profile picture ──
$sidebar_pic = null;
$sp = mysqli_prepare($connect, "SELECT profile_picture FROM users WHERE userid = ?");
mysqli_stmt_bind_param($sp, 's', $user_id);
mysqli_stmt_execute($sp);
$sp_row = mysqli_fetch_assoc(mysqli_stmt_get_result($sp));
mysqli_stmt_close($sp);
if (!empty($sp_row['profile_picture']) && file_exists('uploads/profiles/' . $sp_row['profile_picture'])) {
    $sidebar_pic = 'uploads/profiles/' . htmlspecialchars($sp_row['profile_picture']);
}

// ── Fetch current admin data ──
$stmt = mysqli_prepare($connect,
    "SELECT userid, username, email, address, phone, profile_picture FROM users WHERE userid = ?");
mysqli_stmt_bind_param($stmt, 's', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---------- PROFILE PICTURE ---------- */
    if ($action === 'upload_pic') {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'Please select a file to upload.';
        } else {
            $file    = $_FILES['profile_picture'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $error_msg = 'Only PNG and JPEG images are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error_msg = 'File size must not exceed 2MB.';
            } else {
                $ext      = ($mime === 'image/png') ? 'png' : 'jpg';
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $dest     = 'uploads/profiles/' . $filename;

                if (!is_dir('uploads/profiles')) mkdir('uploads/profiles', 0755, true);

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (!empty($user['profile_picture']) &&
                        $user['profile_picture'] !== 'default.png' &&
                        file_exists('uploads/profiles/' . $user['profile_picture'])) {
                        unlink('uploads/profiles/' . $user['profile_picture']);
                    }
                    $s2 = mysqli_prepare($connect, "UPDATE users SET profile_picture = ? WHERE userid = ?");
                    mysqli_stmt_bind_param($s2, 'ss', $filename, $user_id);
                    mysqli_stmt_execute($s2);
                    mysqli_stmt_close($s2);
                    $user['profile_picture'] = $filename;
                    $success_msg = 'Profile picture updated successfully!';
                } else {
                    $error_msg = 'Failed to upload file. Please try again.';
                }
            }
        }
    }

    /* ---------- UPDATE INFO (phone + address only) ---------- */
    elseif ($action === 'update_info') {
        $new_phone   = trim($_POST['phone']   ?? '');
        $new_address = trim($_POST['address'] ?? '');

        // Basic phone validation — allow empty or digits/spaces/dashes/plus
        if ($new_phone !== '' && !preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $new_phone)) {
            $error_msg = 'Please enter a valid phone number (7–20 digits).';
        } else {
            $upd = mysqli_prepare($connect,
                "UPDATE users SET phone = ?, address = ? WHERE userid = ?");
            mysqli_stmt_bind_param($upd, 'sss', $new_phone, $new_address, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $user['phone']   = $new_phone;
            $user['address'] = $new_address;
            $success_msg = 'Contact information updated successfully!';
        }
    }

    /* ---------- CHANGE PASSWORD ---------- */
    elseif ($action === 'change_password') {
        $old_pw  = $_POST['old_password']     ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
        $conf_pw = $_POST['confirm_password'] ?? '';

        if ($old_pw === '' || $new_pw === '' || $conf_pw === '') {
            $error_msg = 'All password fields are required.';
        } elseif (strlen($new_pw) < 6) {
            $error_msg = 'New password must be at least 6 characters.';
        } elseif ($new_pw !== $conf_pw) {
            $error_msg = 'New password and confirmation do not match.';
        } else {
            $ps = mysqli_prepare($connect, "SELECT password FROM users WHERE userid = ?");
            mysqli_stmt_bind_param($ps, 's', $user_id);
            mysqli_stmt_execute($ps);
            $pw_row = mysqli_fetch_assoc(mysqli_stmt_get_result($ps));
            mysqli_stmt_close($ps);

            if (!$pw_row || !password_verify($old_pw, $pw_row['password'])) {
                $error_msg = 'Current password is incorrect.';
            } else {
                $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $pu = mysqli_prepare($connect, "UPDATE users SET password = ? WHERE userid = ?");
                mysqli_stmt_bind_param($pu, 'ss', $new_hash, $user_id);
                mysqli_stmt_execute($pu);
                mysqli_stmt_close($pu);
                $success_msg = 'Password changed successfully!';
            }
        }
    }
}

// ── Avatar path ──
$avatar_src = (!empty($user['profile_picture']) &&
               file_exists('uploads/profiles/' . $user['profile_picture']))
    ? 'uploads/profiles/' . htmlspecialchars($user['profile_picture'])
    : null;

$user_initial = strtoupper(mb_substr($user['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — FOODTIME Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/admin.css">
<style>
#pic-file-input{ display:none !important; }

.profile-grid{ display:grid; grid-template-columns:300px 1fr; gap:22px; align-items:start; }
@media(max-width:900px){ .profile-grid{ grid-template-columns:1fr } }

.pr-card{ background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.pr-card-header{ padding:16px 20px 13px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:10px; background:#ffffff; }
.pr-card-icon{ width:34px; height:34px; border-radius:10px; background:#fff3e8; color:#d4650f; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.pr-card-title{ font-size:14px; font-weight:700; color:#1c1e21; }
.pr-card-sub{ font-size:12px; color:#6b7280; margin-top:1px; }
.pr-card-body{ padding:20px; background:#ffffff; }

.avatar-card-body{ padding:26px 20px 22px; display:flex; flex-direction:column; align-items:center; gap:14px; text-align:center; background:#ffffff; }
.av-wrap{ position:relative; width:110px; height:110px; flex-shrink:0; }
.av-img,.av-placeholder{ width:110px; height:110px; border-radius:50%; border:3.5px solid #d4650f; object-fit:cover; display:block; box-shadow:0 4px 16px rgba(212,101,15,.28); }
.av-placeholder{ background:linear-gradient(135deg,#d4650f,#f0874a); color:#fff; font-size:42px; font-weight:900; display:flex; align-items:center; justify-content:center; user-select:none; }
.av-cam{ position:absolute; bottom:3px; right:3px; width:30px; height:30px; border-radius:50%; background:#d4650f; color:#fff; border:2.5px solid #fff; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; transition:transform .2s,background .15s; box-shadow:0 2px 8px rgba(0,0,0,.18); }
.av-cam:hover{ transform:scale(1.15); background:#b3540c; }
.av-name{ font-size:17px; font-weight:800; color:#1c1e21; }
.av-role{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#fff; background:#d4650f; padding:3px 10px; border-radius:20px; margin-top:-6px; }
.av-id{ font-size:11px; color:#6b7280; font-family:monospace; padding:2px 9px; background:#f3f4f6; border-radius:20px; }

.btn-source{ flex:1; padding:9px 10px; border:1.5px solid #e5e7eb; border-radius:9px; background:#f9fafb; color:#374151; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:7px; transition:border-color .15s,background .15s,color .15s; font-family:inherit; }
.btn-source:hover{ border-color:#d4650f; background:#fff3e8; color:#d4650f; }

.upload-drop-area{ width:100%; border:2px dashed #d1d5db; border-radius:10px; padding:16px 14px; text-align:center; cursor:pointer; transition:border-color .15s,background .15s; position:relative; background:#f9fafb; }
.upload-drop-area:hover,.upload-drop-area.dragover{ border-color:#d4650f; background:#fff3e8; }
.upload-drop-area i{ font-size:22px; color:#9ca3af; display:block; margin-bottom:6px; }
.upload-drop-area p{ font-size:12px; color:#6b7280; line-height:1.5; margin:0; }
.upload-drop-area strong{ color:#d4650f; }
.upload-preview{ display:none; width:60px; height:60px; border-radius:50%; object-fit:cover; margin:0 auto 8px; border:2.5px solid #d4650f; box-shadow:0 2px 8px rgba(212,101,15,.2); }
.upload-preview.show{ display:block; }
.btn-upload{ width:100%; margin-top:10px; padding:10px; background:#d4650f; color:#fff; border:none; border-radius:9px; font-size:13px; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:7px; transition:background .15s; font-family:inherit; }
.btn-upload:hover{ background:#b3540c; }
.btn-upload:disabled{ background:#d1d5db; cursor:not-allowed; color:#9ca3af; }
.upload-hint{ font-size:11px; color:#9ca3af; margin-top:7px; text-align:center; }

.pr-form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media(max-width:600px){ .pr-form-grid{ grid-template-columns:1fr } }
.pr-field{ display:flex; flex-direction:column; gap:5px; }
.pr-label{ font-size:13px; font-weight:700; color:#374151; display:flex; align-items:center; gap:6px; }
.pr-label i{ color:#d4650f; font-size:12px; }

/* Editable input */
.pr-input{ padding:10px 13px; border:1.5px solid #e5e7eb; border-radius:9px; font-size:14px; font-family:inherit; color:#1c1e21; background:#f9fafb; outline:none; transition:border-color .15s,box-shadow .15s; }
.pr-input:focus{ border-color:#d4650f !important; box-shadow:0 0 0 3px rgba(212,101,15,.12); background:#ffffff; }
.pr-input::placeholder{ color:#9ca3af; }
textarea.pr-input{ resize:vertical; min-height:80px; }

/* Locked / read-only display */
.pr-readonly{ padding:10px 13px; border:1.5px solid #e5e7eb; border-radius:9px; font-size:14px; font-family:inherit; color:#6b7280; background:#f3f4f6; cursor:not-allowed; display:flex; align-items:center; gap:8px; }
.pr-readonly i{ color:#9ca3af; font-size:13px; }
.pr-readonly-hint{ font-size:11px; color:#9ca3af; margin-top:3px; display:flex; align-items:center; gap:4px; }

.pw-wrap{ position:relative; }
.pw-wrap .pr-input{ padding-right:42px; }
.pw-toggle{ position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#9ca3af; font-size:14px; padding:4px; display:flex; align-items:center; }
.pw-toggle:hover{ color:#d4650f; }
.pw-strength{ height:4px; border-radius:4px; margin-top:5px; background:#e5e7eb; overflow:hidden; }
.pw-strength-bar{ height:100%; border-radius:4px; transition:width .3s,background .3s; width:0; }
.pw-strength-label{ font-size:11px; color:#9ca3af; margin-top:3px; }

.btn-save{ padding:10px 26px; background:#d4650f; color:#fff; border:none; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; margin-top:6px; display:inline-flex; align-items:center; gap:8px; transition:background .15s,transform .1s; font-family:inherit; }
.btn-save:hover{ background:#b3540c; }
.btn-save:active{ transform:scale(.97); }

.pr-alert{ display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:18px; animation:prSlide .3s ease; }
@keyframes prSlide{ from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.pr-alert-success{ background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.pr-alert-error{ background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* Lightbox */
#picLightbox{ display:none; position:fixed; inset:0; z-index:9000; background:rgba(0,0,0,.88); align-items:center; justify-content:center; padding:20px; cursor:zoom-out; }
#picLightbox.show{ display:flex; }
.lb-inner{ position:relative; display:flex; flex-direction:column; align-items:center; gap:14px; cursor:default; }
.lb-img-wrap{ width:88vmin; height:88vmin; max-width:85vh; max-height:85vh; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; border-radius:16px; box-shadow:0 12px 60px rgba(0,0,0,.6); background:#111; }
#lbImg{ max-width:100%; max-height:100%; object-fit:contain; transition:transform .3s ease; border-radius:12px; }
.lb-close{ position:absolute; top:-14px; right:-14px; width:36px; height:36px; border-radius:50%; background:#fff; color:#1c1e21; border:none; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 12px rgba(0,0,0,.3); transition:transform .15s,background .15s; z-index:1; }
.lb-close:hover{ transform:scale(1.12); background:#fee2e2; color:#dc2626; }
.lb-caption{ color:rgba(255,255,255,.75); font-size:13px; font-weight:600; letter-spacing:.3px; }
.lb-toolbar{ display:flex; align-items:center; gap:8px; flex-shrink:0; }
.lb-btn{ width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.2); cursor:pointer; font-size:15px; display:flex; align-items:center; justify-content:center; transition:background .15s,transform .15s; }
.lb-btn:hover{ background:rgba(255,255,255,.25); transform:scale(1.08); }
.lb-btn:active{ transform:scale(.94); }
.lb-divider{ width:1px; height:24px; background:rgba(255,255,255,.2); margin:0 2px; }

/* Camera modal */
#cameraModal{ display:none; position:fixed; inset:0; z-index:8000; background:rgba(0,0,0,.72); align-items:center; justify-content:center; padding:20px; }
#cameraModal.show{ display:flex; }
.cam-box{ background:#fff; border-radius:18px; width:100%; max-width:480px; box-shadow:0 12px 48px rgba(0,0,0,.35); overflow:hidden; animation:camIn .22s ease; display:flex; flex-direction:column; }
@keyframes camIn{ from{transform:scale(.93);opacity:0} to{transform:scale(1);opacity:1} }
.cam-header{ display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #e5e7eb; flex-shrink:0; }
.cam-title{ font-size:16px; font-weight:800; display:flex; align-items:center; gap:9px; color:#1c1e21; }
.cam-title i{ color:#d4650f; }
.cam-close-btn{ width:32px; height:32px; border:none; background:#f3f4f6; border-radius:50%; cursor:pointer; font-size:16px; color:#6b7280; display:flex; align-items:center; justify-content:center; transition:background .15s; }
.cam-close-btn:hover{ background:#e5e7eb; }
.cam-body{ padding:20px; display:flex; flex-direction:column; gap:14px; }
.cam-viewport{ position:relative; width:100%; border-radius:12px; overflow:hidden; background:#000; aspect-ratio:4/3; }
#camVideo{ width:100%; height:100%; object-fit:cover; display:block; transform:scaleX(-1); }
#camCanvas{ display:none; }
#camPreviewImg{ display:none; width:100%; height:100%; object-fit:cover; border-radius:12px; }
.cam-face-guide{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; }
.cam-face-guide svg{ opacity:.35; }
.cam-status{ text-align:center; font-size:13px; color:#6b7280; min-height:20px; }
.cam-status.error{ color:#dc2626; font-weight:600; }
.cam-actions{ display:flex; gap:10px; }
.btn-cam-action{ flex:1; padding:11px; border:none; border-radius:10px; font-size:14px; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity .15s,transform .1s; font-family:inherit; }
.btn-cam-action:active{ transform:scale(.96); }
.btn-cam-snap{ background:#d4650f; color:#fff; }
.btn-cam-snap:hover{ opacity:.88; }
.btn-cam-snap:disabled{ background:#ccc; cursor:not-allowed; }
.btn-cam-retake{ background:#f3f4f6; color:#1c1e21; border:1.5px solid #e5e7eb; display:none; }
.btn-cam-retake:hover{ background:#e5e7eb; }
.btn-cam-use{ background:#16a34a; color:#fff; display:none; }
.btn-cam-use:hover{ opacity:.88; }
</style>
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">My <span>Profile</span></div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></div>
        </div>
    </div>

    <div class="content">

        <?php if ($success_msg): ?>
        <div class="pr-alert pr-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="pr-alert pr-alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="profile-grid">

            <!-- LEFT -->
            <div style="display:flex;flex-direction:column;gap:18px">

                <div class="pr-card">
                    <div class="avatar-card-body">
                        <div class="av-wrap">
                            <?php if ($avatar_src): ?>
                                <img src="<?= $avatar_src ?>" alt="Profile" class="av-img" id="avatarDisplay"
                                     onclick="viewProfilePic('<?= $avatar_src ?>')" title="View photo" style="cursor:zoom-in">
                            <?php else: ?>
                                <div class="av-placeholder" id="avatarDisplay"><?= $user_initial ?></div>
                            <?php endif; ?>
                            <button class="av-cam" onclick="openCameraModal()" title="Take selfie">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <div class="av-name"><?= htmlspecialchars($user['username']) ?></div>
                        <div class="av-role"><i class="fas fa-shield-halved"></i> Administrator</div>
                        <div class="av-id"><?= htmlspecialchars($user['userid']) ?></div>
                    </div>
                </div>

                <div class="pr-card">
                    <div class="pr-card-header">
                        <div class="pr-card-icon"><i class="fas fa-image"></i></div>
                        <div>
                            <div class="pr-card-title">Profile Picture</div>
                            <div class="pr-card-sub">PNG or JPEG · Max 2MB</div>
                        </div>
                    </div>
                    <div class="pr-card-body">
                        <div style="display:flex;gap:10px;margin-bottom:14px">
                            <button type="button" class="btn-source" onclick="openCameraModal()">
                                <i class="fas fa-camera"></i> Take Selfie
                            </button>
                            <button type="button" class="btn-source" onclick="triggerPicUpload()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                        </div>
                        <form method="POST" enctype="multipart/form-data" id="picForm">
                            <input type="hidden" name="action" value="upload_pic">
                            <input type="file" name="profile_picture" id="pic-file-input"
                                   accept="image/png,image/jpeg" onchange="previewPic(this)">
                            <div class="upload-drop-area" id="dropArea"
                                 onclick="triggerPicUpload()"
                                 ondragover="handleDragOver(event)"
                                 ondragleave="handleDragLeave(event)"
                                 ondrop="handleDrop(event)">
                                <img src="" alt="preview" class="upload-preview" id="uploadPreview">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or <strong>drag & drop</strong> your photo here<br>
                                   <span style="font-size:11px">PNG or JPEG only</span></p>
                            </div>
                            <button type="submit" class="btn-upload" id="btnUpload" disabled>
                                <i class="fas fa-upload"></i> Upload Photo
                            </button>
                            <div class="upload-hint"><i class="fas fa-info-circle"></i> Your face should be clearly visible</div>
                        </form>
                    </div>
                </div>

            </div><!-- /left -->

            <!-- RIGHT -->
            <div style="display:flex;flex-direction:column;gap:18px">

                <!-- Account Info (locked) -->
                <div class="pr-card">
                    <div class="pr-card-header">
                        <div class="pr-card-icon"><i class="fas fa-id-card"></i></div>
                        <div>
                            <div class="pr-card-title">Account Information</div>
                            <div class="pr-card-sub">Username and email are managed by the system</div>
                        </div>
                    </div>
                    <div class="pr-card-body">
                        <div class="pr-form-grid">
                            <div class="pr-field">
                                <label class="pr-label"><i class="fas fa-user"></i> Username</label>
                                <div class="pr-readonly">
                                    <i class="fas fa-lock"></i>
                                    <?= htmlspecialchars($user['username']) ?>
                                </div>
                                <div class="pr-readonly-hint"><i class="fas fa-info-circle"></i> Cannot be changed</div>
                            </div>
                            <div class="pr-field">
                                <label class="pr-label"><i class="fas fa-envelope"></i> Email Address</label>
                                <div class="pr-readonly">
                                    <i class="fas fa-lock"></i>
                                    <?= htmlspecialchars($user['email'] ?? '—') ?>
                                </div>
                                <div class="pr-readonly-hint"><i class="fas fa-info-circle"></i> Cannot be changed</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info (editable) -->
                <div class="pr-card">
                    <div class="pr-card-header">
                        <div class="pr-card-icon"><i class="fas fa-address-book"></i></div>
                        <div>
                            <div class="pr-card-title">Contact Information</div>
                            <div class="pr-card-sub">Update your phone number and address</div>
                        </div>
                    </div>
                    <div class="pr-card-body">
                        <form method="POST" id="infoForm">
                            <input type="hidden" name="action" value="update_info">
                            <div class="pr-form-grid">
                                <div class="pr-field">
                                    <label class="pr-label" for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                                    <input type="tel" name="phone" id="phone" class="pr-input"
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                           maxlength="20" placeholder="e.g. 012-3456789">
                                </div>
                                <div class="pr-field" style="grid-column:1/-1">
                                    <label class="pr-label" for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                                    <textarea name="address" id="address" class="pr-input"
                                              maxlength="300" rows="3"
                                              placeholder="Enter your address…"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="pr-card">
                    <div class="pr-card-header">
                        <div class="pr-card-icon"><i class="fas fa-lock"></i></div>
                        <div>
                            <div class="pr-card-title">Change Password</div>
                            <div class="pr-card-sub">Keep your admin account secure</div>
                        </div>
                    </div>
                    <div class="pr-card-body">
                        <form method="POST" id="pwForm" onsubmit="return validatePwForm()">
                            <input type="hidden" name="action" value="change_password">
                            <div style="display:flex;flex-direction:column;gap:14px">

                                <div class="pr-field">
                                    <label class="pr-label" for="old_password"><i class="fas fa-key"></i> Current Password</label>
                                    <div class="pw-wrap">
                                        <input type="password" name="old_password" id="old_password"
                                               class="pr-input" placeholder="Enter current password"
                                               autocomplete="current-password">
                                        <button type="button" class="pw-toggle" onclick="togglePw('old_password',this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="pr-field">
                                    <label class="pr-label" for="new_password"><i class="fas fa-lock"></i> New Password</label>
                                    <div class="pw-wrap">
                                        <input type="password" name="new_password" id="new_password"
                                               class="pr-input" placeholder="Enter new password"
                                               autocomplete="new-password" oninput="checkStrength(this.value)">
                                        <button type="button" class="pw-toggle" onclick="togglePw('new_password',this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                                    <div class="pw-strength-label" id="pwLabel"></div>
                                </div>

                                <div class="pr-field">
                                    <label class="pr-label" for="confirm_password"><i class="fas fa-check-double"></i> Confirm New Password</label>
                                    <div class="pw-wrap">
                                        <input type="password" name="confirm_password" id="confirm_password"
                                               class="pr-input" placeholder="Repeat new password"
                                               autocomplete="new-password">
                                        <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                            </div>
                            <button type="submit" class="btn-save" style="margin-top:16px">
                                <i class="fas fa-shield-alt"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

            </div><!-- /right -->
        </div><!-- /profile-grid -->
    </div><!-- /content -->
</main>

<!-- LIGHTBOX -->
<div id="picLightbox" onclick="closeLightbox()">
    <div class="lb-inner" onclick="event.stopPropagation()">
        <button class="lb-close" onclick="closeLightbox()" title="Close"><i class="fas fa-times"></i></button>
        <div class="lb-img-wrap"><img id="lbImg" src="" alt="Profile picture"></div>
        <div class="lb-toolbar">
            <button class="lb-btn" onclick="lbRotate(-90)" title="Rotate left"><i class="fas fa-rotate-left"></i></button>
            <button class="lb-btn" onclick="lbRotate(90)"  title="Rotate right"><i class="fas fa-rotate-right"></i></button>
            <div class="lb-divider"></div>
            <button class="lb-btn" onclick="lbFlip('h')" title="Flip horizontal"><i class="fas fa-arrows-left-right"></i></button>
            <button class="lb-btn" onclick="lbFlip('v')" title="Flip vertical"><i class="fas fa-arrows-up-down"></i></button>
            <div class="lb-divider"></div>
            <button class="lb-btn" id="btnSaveProfile" onclick="lbSaveAsProfile()"
                    title="Save as profile picture"
                    style="background:rgba(34,197,94,.2);border-color:rgba(34,197,94,.4)">
                <i class="fas fa-save"></i>
            </button>
        </div>
        <div class="lb-caption">Profile Picture &nbsp;·&nbsp; Click outside or Esc to close</div>
    </div>
</div>

<!-- CAMERA MODAL -->
<div id="cameraModal" role="dialog" aria-modal="true">
    <div class="cam-box">
        <div class="cam-header">
            <div class="cam-title"><i class="fas fa-camera"></i> Take a Selfie</div>
            <button class="cam-close-btn" onclick="closeCameraModal()" title="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="cam-body">
            <div class="cam-viewport">
                <video id="camVideo" autoplay playsinline muted></video>
                <canvas id="camCanvas"></canvas>
                <img id="camPreviewImg" alt="Selfie preview">
                <div class="cam-face-guide">
                    <svg width="160" height="200" viewBox="0 0 160 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="80" cy="100" rx="68" ry="88" stroke="white" stroke-width="2.5" stroke-dasharray="8 5"/>
                    </svg>
                </div>
            </div>
            <div class="cam-status" id="camStatus">Starting camera…</div>
            <div class="cam-actions">
                <button class="btn-cam-action btn-cam-snap" id="btnSnap" onclick="snapPhoto()" disabled>
                    <i class="fas fa-circle"></i> Snap Photo
                </button>
                <button class="btn-cam-action btn-cam-retake" id="btnRetake" onclick="retakePhoto()">
                    <i class="fas fa-redo"></i> Retake
                </button>
                <button class="btn-cam-action btn-cam-use" id="btnUsePhoto" onclick="useSelfie()">
                    <i class="fas fa-check"></i> Use This Photo
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/* ── LIGHTBOX ── */
let _lbRotate=0,_lbFlipH=1,_lbFlipV=1;
function viewProfilePic(src){document.getElementById('lbImg').src=src;lbReset();document.getElementById('picLightbox').classList.add('show');document.addEventListener('keydown',_lbKeyClose);}
function closeLightbox(){document.getElementById('picLightbox').classList.remove('show');document.removeEventListener('keydown',_lbKeyClose);}
function _lbKeyClose(e){if(e.key==='Escape')closeLightbox();if(e.key==='ArrowLeft')lbRotate(-90);if(e.key==='ArrowRight')lbRotate(90);}
function lbRotate(deg){_lbRotate+=deg;_lbApply();}
function lbFlip(dir){if(dir==='h')_lbFlipH*=-1;else _lbFlipV*=-1;_lbApply();}
function lbReset(){const img=document.getElementById('lbImg');img.style.transition='none';_lbRotate=0;_lbFlipH=1;_lbFlipV=1;_lbApply();requestAnimationFrame(()=>img.style.transition='transform .3s ease');}
function _lbApply(){document.getElementById('lbImg').style.transform=`rotate(${_lbRotate}deg) scaleX(${_lbFlipH}) scaleY(${_lbFlipV})`;}
function lbSaveAsProfile(){
    const btn=document.getElementById('btnSaveProfile'),img=document.getElementById('lbImg'),canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
    const rad=((_lbRotate%360)+360)%360,swap=rad===90||rad===270;
    canvas.width=swap?img.naturalHeight:img.naturalWidth;canvas.height=swap?img.naturalWidth:img.naturalHeight;
    ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(_lbRotate*Math.PI/180);ctx.scale(_lbFlipH,_lbFlipV);ctx.drawImage(img,-img.naturalWidth/2,-img.naturalHeight/2);
    const origHTML=btn.innerHTML;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';btn.disabled=true;
    canvas.toBlob(function(blob){
        const file=new File([blob],'profile_edited.jpg',{type:'image/jpeg'}),fd=new FormData();
        fd.append('action','upload_pic');fd.append('profile_picture',file,file.name);
        fetch('admin_profile.php',{method:'POST',body:fd}).then(r=>r.text()).then(()=>{closeLightbox();window.location.reload();}).catch(()=>{btn.innerHTML=origHTML;btn.disabled=false;alert('Failed to save.');});
    },'image/jpeg',0.95);
}

/* ── CAMERA ── */
let _picFile=null,_camStream=null;
function openCameraModal(){document.getElementById('cameraModal').classList.add('show');document.getElementById('camVideo').style.display='block';document.getElementById('camPreviewImg').style.display='none';document.getElementById('btnSnap').style.display='flex';document.getElementById('btnRetake').style.display='none';document.getElementById('btnUsePhoto').style.display='none';document.getElementById('btnSnap').disabled=true;_startCamera();}
function closeCameraModal(){_stopCamera();document.getElementById('cameraModal').classList.remove('show');}
function _startCamera(){
    _setCamStatus('Requesting camera access…','');
    navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280},height:{ideal:960}},audio:false})
    .then(function(stream){_camStream=stream;const v=document.getElementById('camVideo');v.srcObject=stream;v.onloadedmetadata=function(){v.play();document.getElementById('btnSnap').disabled=false;_setCamStatus('Position your face inside the oval, then snap!','');};})
    .catch(function(err){let msg='Camera access denied.';if(err.name==='NotFoundError')msg='No camera found.';if(err.name==='NotAllowedError')msg='Camera permission denied.';if(err.name==='NotReadableError')msg='Camera in use by another app.';_setCamStatus(msg,'error');});
}
function _stopCamera(){if(_camStream){_camStream.getTracks().forEach(t=>t.stop());_camStream=null;}document.getElementById('camVideo').srcObject=null;}
function snapPhoto(){const v=document.getElementById('camVideo'),c=document.getElementById('camCanvas'),p=document.getElementById('camPreviewImg');c.width=v.videoWidth||640;c.height=v.videoHeight||480;const ctx=c.getContext('2d');ctx.translate(c.width,0);ctx.scale(-1,1);ctx.drawImage(v,0,0,c.width,c.height);p.src=c.toDataURL('image/jpeg',.92);p.style.display='block';v.style.display='none';document.getElementById('btnSnap').style.display='none';document.getElementById('btnRetake').style.display='flex';document.getElementById('btnUsePhoto').style.display='flex';_setCamStatus('Looking good! Use it or retake.','');}
function retakePhoto(){document.getElementById('camVideo').style.display='block';document.getElementById('camPreviewImg').style.display='none';document.getElementById('btnSnap').style.display='flex';document.getElementById('btnRetake').style.display='none';document.getElementById('btnUsePhoto').style.display='none';_setCamStatus('Position your face inside the oval, then snap!','');}
function useSelfie(){document.getElementById('camCanvas').toBlob(function(blob){const file=new File([blob],'selfie_'+Date.now()+'.jpg',{type:'image/jpeg'});_stopCamera();document.getElementById('cameraModal').classList.remove('show');_handleFile(file);},'image/jpeg',.92);}
function _setCamStatus(msg,type){const el=document.getElementById('camStatus');el.textContent=msg;el.className='cam-status'+(type==='error'?' error':'');}
document.getElementById('cameraModal').addEventListener('click',function(e){if(e.target===this)closeCameraModal();});

/* ── FILE UPLOAD ── */
function triggerPicUpload(){document.getElementById('pic-file-input').click();}
function previewPic(input){if(input.files&&input.files[0])_handleFile(input.files[0]);}
function _handleFile(file){
    const allowed=['image/jpeg','image/jpg','image/png'];
    if(!allowed.includes(file.type)){_showUploadAlert('Only PNG and JPEG images are allowed.','error');return;}
    if(file.size>2*1024*1024){_showUploadAlert('File too large — maximum 2 MB.','error');return;}
    _picFile=file;
    const reader=new FileReader();
    reader.onload=function(e){const prev=document.getElementById('uploadPreview'),drop=document.getElementById('dropArea');prev.src=e.target.result;prev.classList.add('show');drop.querySelector('i').style.display='none';drop.querySelector('p').style.display='none';document.getElementById('btnUpload').disabled=false;_showUploadAlert('');};
    reader.readAsDataURL(file);
}
function handleDragOver(e){e.preventDefault();document.getElementById('dropArea').classList.add('dragover');}
function handleDragLeave(e){document.getElementById('dropArea').classList.remove('dragover');}
function handleDrop(e){e.preventDefault();document.getElementById('dropArea').classList.remove('dragover');const f=e.dataTransfer.files[0];if(f)_handleFile(f);}
document.getElementById('picForm').addEventListener('submit',function(e){
    e.preventDefault();
    if(!_picFile){_showUploadAlert('Please select, drop, or take a photo first.','error');return;}
    const btn=document.getElementById('btnUpload');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Uploading…';
    const fd=new FormData();fd.append('action','upload_pic');fd.append('profile_picture',_picFile,_picFile.name);
    fetch('admin_profile.php',{method:'POST',body:fd}).then(r=>r.text()).then(()=>window.location.reload()).catch(()=>{_showUploadAlert('Upload failed. Please try again.','error');btn.disabled=false;btn.innerHTML='<i class="fas fa-upload"></i> Upload Photo';});
});
function _showUploadAlert(msg,type){
    let el=document.getElementById('uploadAlert');
    if(!el){el=document.createElement('div');el.id='uploadAlert';el.style.cssText='font-size:12px;font-weight:600;margin-top:7px;padding:7px 11px;border-radius:7px;display:none';document.getElementById('picForm').appendChild(el);}
    if(!msg){el.style.display='none';return;}
    el.style.display='block';el.style.background=type==='error'?'#fee2e2':'#dcfce7';el.style.color=type==='error'?'#991b1b':'#166634';el.style.border='1px solid '+(type==='error'?'#fca5a5':'#86efac');el.textContent=msg;
}

/* ── PASSWORD ── */
function togglePw(fieldId,btn){const f=document.getElementById(fieldId),i=btn.querySelector('i');if(f.type==='password'){f.type='text';i.className='fas fa-eye-slash';}else{f.type='password';i.className='fas fa-eye';}}
function checkStrength(pw){const bar=document.getElementById('pwBar'),label=document.getElementById('pwLabel');let s=0;if(pw.length>=6)s++;if(pw.length>=10)s++;if(/[A-Z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;const lv=[{w:'0%',bg:'transparent',t:''},{w:'25%',bg:'#ef4444',t:'Weak'},{w:'50%',bg:'#f97316',t:'Fair'},{w:'75%',bg:'#eab308',t:'Good'},{w:'100%',bg:'#22c55e',t:'Strong'}][Math.min(s,4)];bar.style.width=pw.length?lv.w:'0%';bar.style.background=lv.bg;label.textContent=pw.length?lv.t:'';}
function validatePwForm(){const np=document.getElementById('new_password').value,cp=document.getElementById('confirm_password').value;if(np!==cp){alert('Passwords do not match.');return false;}if(np.length<6){alert('Minimum 6 characters.');return false;}return true;}

document.querySelectorAll('.pr-alert').forEach(function(el){setTimeout(function(){el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);},5000);});
</script>
</body>
</html>
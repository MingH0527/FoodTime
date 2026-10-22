<?php
/* ============================================================
   profile.php — FOODTIME User Profile
   Editable: phone, address, password, profile picture
   Locked:   username, email (display only)
   ============================================================ */
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['position'] ?? '') !== 'customer') {
    header('Location: login.php');
    exit;
}

$user_id     = $_SESSION['user_id'];
$success_msg = '';
$error_msg   = '';

// ── Fetch current user data ──
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
            $file      = $_FILES['profile_picture'];
            $allowed   = ['image/jpeg', 'image/jpg', 'image/png'];
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $mime      = finfo_file($finfo, $file['tmp_name']);
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
                    $s2 = mysqli_prepare($connect,
                        "UPDATE users SET profile_picture = ? WHERE userid = ?");
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
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --orange:#d4650f;--orange-dk:#b3540c;--orange-lt:#fff0e6;--orange-md:#f0874a;
  --bg:#f0f2f5;--white:#fff;--border:#e4e6eb;--text:#1c1e21;--sub:#65676b;--muted:#8a8d91;
  --green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;
  --radius:14px;--shadow:0 2px 16px rgba(0,0,0,.09);
}
[data-theme="dark"]{
  --bg:#18191a;--white:#242526;--border:#3a3b3c;--text:#e4e6eb;
  --sub:#b0b3b8;--muted:#6b6f76;--orange-lt:rgba(212,101,15,.15);
}
html,body{min-height:100%;font-family:'Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);color:var(--text);transition:background .25s,color .25s}

.top-nav{height:58px;background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:14px;position:sticky;top:0;z-index:200;box-shadow:0 1px 8px rgba(0,0,0,.07)}
.nav-brand{font-size:19px;font-weight:900;color:var(--orange);text-decoration:none;display:flex;align-items:center;gap:9px;letter-spacing:-.3px}
.nav-title{font-size:14px;font-weight:600;color:var(--sub);display:flex;align-items:center;gap:7px}
.nav-title i{color:var(--orange)}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.nav-back{color:var(--orange);text-decoration:none;font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:50px;border:1.5px solid var(--orange);transition:background .15s,color .15s}
.nav-back:hover{background:var(--orange);color:#fff}
.theme-btn{width:36px;height:36px;border:none;background:var(--bg);border-radius:50%;cursor:pointer;font-size:16px;color:var(--sub);display:flex;align-items:center;justify-content:center;transition:background .15s}
.theme-btn:hover{background:var(--border)}

.page-wrap{max-width:900px;margin:0 auto;padding:32px 20px 60px}
.page-title{font-size:26px;font-weight:900;letter-spacing:-.5px;margin-bottom:6px}
.page-sub{font-size:14px;color:var(--sub);margin-bottom:28px}

.alert{display:flex;align-items:center;gap:11px;padding:13px 18px;border-radius:var(--radius);font-size:14px;font-weight:600;margin-bottom:22px;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert-success{background:var(--green-lt);color:var(--green);border:1px solid #86efac}
.alert-error{background:var(--red-lt);color:var(--red);border:1px solid #fca5a5}
.alert i{font-size:16px;flex-shrink:0}

.profile-grid{display:grid;grid-template-columns:300px 1fr;gap:22px;align-items:start}
@media(max-width:720px){.profile-grid{grid-template-columns:1fr}}

.card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid var(--border);overflow:hidden}
.card-header{padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-header-icon{width:34px;height:34px;border-radius:10px;background:var(--orange-lt);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.card-title{font-size:15px;font-weight:800}
.card-sub{font-size:12px;color:var(--sub);margin-top:1px}
.card-body{padding:22px}

.avatar-card .card-body{padding:28px 22px 24px;display:flex;flex-direction:column;align-items:center;gap:16px;text-align:center}
.avatar-wrap{position:relative;width:120px;height:120px;flex-shrink:0}
.avatar-img,.avatar-placeholder{width:120px;height:120px;border-radius:50%;border:4px solid var(--orange);object-fit:cover;display:block;box-shadow:0 4px 18px rgba(212,101,15,.30)}
.avatar-placeholder{background:linear-gradient(135deg,var(--orange),var(--orange-md));color:#fff;font-size:46px;font-weight:900;display:flex;align-items:center;justify-content:center;user-select:none}
.avatar-edit-btn{position:absolute;bottom:4px;right:4px;width:32px;height:32px;border-radius:50%;background:var(--orange);color:#fff;border:2.5px solid var(--white);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:transform .2s,background .15s;box-shadow:0 2px 8px rgba(0,0,0,.18)}
.avatar-edit-btn:hover{transform:scale(1.15);background:var(--orange-dk)}
.avatar-name{font-size:18px;font-weight:800}
.avatar-id{font-size:12px;color:var(--muted);font-family:monospace;padding:3px 10px;background:var(--bg);border-radius:20px;margin-top:-6px}

#pic-file-input{display:none}
.upload-drop-area{width:100%;border:2px dashed var(--border);border-radius:10px;padding:16px 14px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;position:relative}
.upload-drop-area:hover,.upload-drop-area.dragover{border-color:var(--orange);background:var(--orange-lt)}
.upload-drop-area i{font-size:22px;color:var(--muted);display:block;margin-bottom:6px}
.upload-drop-area p{font-size:12px;color:var(--sub);line-height:1.5;margin:0}
.upload-drop-area strong{color:var(--orange)}
.upload-preview{display:none;width:64px;height:64px;border-radius:50%;object-fit:cover;margin:0 auto 8px;border:2.5px solid var(--orange);box-shadow:0 2px 8px rgba(212,101,15,.2)}
.upload-preview.show{display:block}
.btn-upload{width:100%;margin-top:10px;padding:10px;background:var(--orange);color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:background .15s}
.btn-upload:hover{background:var(--orange-dk)}
.btn-upload:disabled{background:#ccc;cursor:not-allowed}
.upload-hint{font-size:11px;color:var(--muted);margin-top:7px;text-align:center}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:520px){.form-grid{grid-template-columns:1fr}}
.form-field{display:flex;flex-direction:column;gap:5px}
.form-field.full{grid-column:1/-1}
.form-label{font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:6px}
.form-label i{color:var(--orange);font-size:12px}

/* Editable */
.form-input{padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;color:var(--text);background:var(--bg);outline:none;transition:border-color .15s,box-shadow .15s}
.form-input:focus{border-color:var(--orange);box-shadow:0 0 0 3px rgba(212,101,15,.12)}
textarea.form-input{resize:vertical;min-height:80px}

/* Locked / read-only display */
.form-readonly{padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;color:var(--muted);background:var(--bg);display:flex;align-items:center;gap:8px;cursor:not-allowed;opacity:.85}
.form-readonly i{color:var(--muted);font-size:13px}
.form-readonly-hint{font-size:11px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:4px}

.pw-wrap{position:relative}
.pw-wrap .form-input{padding-right:42px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:4px;display:flex;align-items:center}
.pw-toggle:hover{color:var(--orange)}
.pw-strength{height:4px;border-radius:4px;margin-top:5px;background:var(--border);overflow:hidden}
.pw-strength-bar{height:100%;border-radius:4px;transition:width .3s,background .3s;width:0}
.pw-strength-label{font-size:11px;color:var(--muted);margin-top:3px}

.btn-save{padding:11px 28px;background:var(--orange);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .15s,transform .1s;margin-top:6px}
.btn-save:hover{background:var(--orange-dk)}
.btn-save:active{transform:scale(.97)}

.btn-source{flex:1;padding:9px 10px;border:1.5px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:border-color .15s,background .15s,color .15s;font-family:inherit}
.btn-source:hover{border-color:var(--orange);background:var(--orange-lt);color:var(--orange)}

/* Camera modal */
#cameraModal{display:none;position:fixed;inset:0;z-index:8000;background:rgba(0,0,0,.72);align-items:center;justify-content:center;padding:20px}
#cameraModal.show{display:flex}
.cam-box{background:var(--white);border-radius:18px;width:100%;max-width:480px;box-shadow:0 12px 48px rgba(0,0,0,.35);overflow:hidden;animation:camIn .22s ease;display:flex;flex-direction:column}
@keyframes camIn{from{transform:scale(.93);opacity:0}to{transform:scale(1);opacity:1}}
.cam-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0}
.cam-title{font-size:16px;font-weight:800;display:flex;align-items:center;gap:9px}
.cam-title i{color:var(--orange)}
.cam-close-btn{width:32px;height:32px;border:none;background:var(--bg);border-radius:50%;cursor:pointer;font-size:16px;color:var(--sub);display:flex;align-items:center;justify-content:center;transition:background .15s}
.cam-close-btn:hover{background:var(--border)}
.cam-body{padding:20px;display:flex;flex-direction:column;gap:14px}
.cam-viewport{position:relative;width:100%;border-radius:12px;overflow:hidden;background:#000;aspect-ratio:4/3}
#camVideo{width:100%;height:100%;object-fit:cover;display:block;transform:scaleX(-1)}
#camCanvas{display:none}
#camPreviewImg{display:none;width:100%;height:100%;object-fit:cover;border-radius:12px}
.cam-face-guide{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
.cam-face-guide svg{opacity:.35}
.cam-status{text-align:center;font-size:13px;color:var(--sub);min-height:20px}
.cam-status.error{color:#dc2626;font-weight:600}
.cam-actions{display:flex;gap:10px}
.btn-cam-action{flex:1;padding:11px;border:none;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s,transform .1s;font-family:inherit}
.btn-cam-action:active{transform:scale(.96)}
.btn-cam-snap{background:var(--orange);color:#fff}
.btn-cam-snap:hover{opacity:.88}
.btn-cam-snap:disabled{background:#ccc;cursor:not-allowed}
.btn-cam-retake{background:var(--bg);color:var(--text);border:1.5px solid var(--border);display:none}
.btn-cam-retake:hover{background:var(--border)}
.btn-cam-use{background:#16a34a;color:#fff;display:none}
.btn-cam-use:hover{opacity:.88}

/* Lightbox */
#picLightbox{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.88);align-items:center;justify-content:center;padding:20px;cursor:zoom-out}
#picLightbox.show{display:flex}
.lb-inner{position:relative;display:flex;flex-direction:column;align-items:center;gap:14px;cursor:default}
.lb-img-wrap{width:88vmin;height:88vmin;max-width:85vh;max-height:85vh;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border-radius:16px;box-shadow:0 12px 60px rgba(0,0,0,.6);background:#111}
#lbImg{max-width:100%;max-height:100%;object-fit:contain;transition:transform .3s ease;border-radius:12px}
.lb-close{position:absolute;top:-14px;right:-14px;width:36px;height:36px;border-radius:50%;background:#fff;color:#1c1e21;border:none;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,.3);transition:transform .15s,background .15s;z-index:1}
.lb-close:hover{transform:scale(1.12);background:#fee2e2;color:#dc2626}
.lb-caption{color:rgba(255,255,255,.75);font-size:13px;font-weight:600;letter-spacing:.3px}
.lb-toolbar{display:flex;align-items:center;gap:8px;flex-shrink:0}
.lb-btn{width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.2);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:background .15s,transform .15s}
.lb-btn:hover{background:rgba(255,255,255,.25);transform:scale(1.08)}
.lb-btn:active{transform:scale(.94)}
.lb-divider{width:1px;height:24px;background:rgba(255,255,255,.2);margin:0 2px}

@media(max-width:480px){.nav-title{display:none}.page-wrap{padding:20px 14px 50px}.page-title{font-size:20px}}
</style>
</head>
<body>

<nav class="top-nav">
  <a href="index.php" class="nav-brand"><i class="fas fa-burger"></i> FOODTIME</a>
  <span class="nav-title"><i class="fas fa-user-circle"></i> My Profile</span>
  <div class="nav-right">
    <button class="theme-btn" id="themeBtn" title="Toggle dark mode"><i class="fas fa-moon"></i></button>
    <a href="index.php" class="nav-back"><i class="fas fa-arrow-left"></i> Home</a>
  </div>
</nav>

<div class="page-wrap">

  <div class="page-title">👤 My Profile</div>
  <div class="page-sub">Manage your contact details, profile picture and security settings.</div>

  <?php if ($success_msg): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
  <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>

  <div class="profile-grid">

    <!-- LEFT -->
    <div style="display:flex;flex-direction:column;gap:18px">

      <div class="card avatar-card">
        <div class="card-body">
          <div class="avatar-wrap">
            <?php if ($avatar_src): ?>
              <img src="<?= $avatar_src ?>" alt="Profile" class="avatar-img" id="avatarDisplay"
                   onclick="viewProfilePic('<?= $avatar_src ?>')" title="View photo" style="cursor:zoom-in">
            <?php else: ?>
              <div class="avatar-placeholder" id="avatarDisplay"><?= $user_initial ?></div>
            <?php endif; ?>
            <button class="avatar-edit-btn" onclick="openCameraModal()" title="Take selfie">
              <i class="fas fa-camera"></i>
            </button>
          </div>
          <div class="avatar-name"><?= htmlspecialchars($user['username']) ?></div>
          <div class="avatar-id"><?= htmlspecialchars($user['userid']) ?></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-header-icon"><i class="fas fa-image"></i></div>
          <div>
            <div class="card-title">Profile Picture</div>
            <div class="card-sub">PNG or JPEG · Max 2MB</div>
          </div>
        </div>
        <div class="card-body">
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

      <!-- Account info (locked) -->
      <div class="card">
        <div class="card-header">
          <div class="card-header-icon"><i class="fas fa-id-card"></i></div>
          <div>
            <div class="card-title">Account Information</div>
            <div class="card-sub">Username and email are fixed and cannot be changed</div>
          </div>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label"><i class="fas fa-user"></i> Username</label>
              <div class="form-readonly">
                <i class="fas fa-lock"></i>
                <?= htmlspecialchars($user['username']) ?>
              </div>
              <div class="form-readonly-hint"><i class="fas fa-info-circle"></i> Cannot be changed</div>
            </div>
            <div class="form-field">
              <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
              <div class="form-readonly">
                <i class="fas fa-lock"></i>
                <?= htmlspecialchars($user['email'] ?? '—') ?>
              </div>
              <div class="form-readonly-hint"><i class="fas fa-info-circle"></i> Cannot be changed</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact info (editable) -->
      <div class="card">
        <div class="card-header">
          <div class="card-header-icon"><i class="fas fa-address-book"></i></div>
          <div>
            <div class="card-title">Contact Information</div>
            <div class="card-sub">Update your phone number and delivery address</div>
          </div>
        </div>
        <div class="card-body">
          <form method="POST" id="infoForm">
            <input type="hidden" name="action" value="update_info">
            <div class="form-grid">
              <div class="form-field">
                <label class="form-label" for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" name="phone" id="phone" class="form-input"
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                       maxlength="20" placeholder="e.g. 012-3456789">
              </div>
              <div class="form-field full">
                <label class="form-label" for="address"><i class="fas fa-map-marker-alt"></i> Delivery Address</label>
                <textarea name="address" id="address" class="form-input"
                          maxlength="300" placeholder="Enter your full delivery address…"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
              </div>
            </div>
            <button type="submit" class="btn-save">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-header">
          <div class="card-header-icon"><i class="fas fa-lock"></i></div>
          <div>
            <div class="card-title">Change Password</div>
            <div class="card-sub">Keep your account secure</div>
          </div>
        </div>
        <div class="card-body">
          <form method="POST" id="pwForm" onsubmit="return validatePwForm()">
            <input type="hidden" name="action" value="change_password">
            <div style="display:flex;flex-direction:column;gap:14px">
              <div class="form-field">
                <label class="form-label" for="old_password"><i class="fas fa-key"></i> Current Password</label>
                <div class="pw-wrap">
                  <input type="password" name="old_password" id="old_password" class="form-input"
                         placeholder="Enter current password" autocomplete="current-password">
                  <button type="button" class="pw-toggle" onclick="togglePw('old_password',this)"><i class="fas fa-eye"></i></button>
                </div>
              </div>
              <div class="form-field">
                <label class="form-label" for="new_password"><i class="fas fa-lock"></i> New Password</label>
                <div class="pw-wrap">
                  <input type="password" name="new_password" id="new_password" class="form-input"
                         placeholder="Min. 6 characters" autocomplete="new-password" oninput="checkStrength(this.value)">
                  <button type="button" class="pw-toggle" onclick="togglePw('new_password',this)"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                <div class="pw-strength-label" id="pwLabel"></div>
              </div>
              <div class="form-field">
                <label class="form-label" for="confirm_password"><i class="fas fa-check-double"></i> Confirm New Password</label>
                <div class="pw-wrap">
                  <input type="password" name="confirm_password" id="confirm_password" class="form-input"
                         placeholder="Repeat new password" autocomplete="new-password">
                  <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)"><i class="fas fa-eye"></i></button>
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
</div><!-- /page-wrap -->

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
/* ── Theme ── */
(function(){const s=localStorage.getItem('ft_theme')||'light';document.documentElement.setAttribute('data-theme',s);updateThemeIcon(s);})();
document.getElementById('themeBtn').addEventListener('click',function(){const c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('ft_theme',n);updateThemeIcon(n);});
function updateThemeIcon(t){const i=document.querySelector('#themeBtn i');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';}

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
        fetch('profile.php',{method:'POST',body:fd}).then(r=>r.text()).then(()=>{closeLightbox();window.location.reload();}).catch(()=>{btn.innerHTML=origHTML;btn.disabled=false;alert('Failed to save.');});
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
function previewPic(input){if(!input.files||!input.files[0])return;_handleFile(input.files[0]);}
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
    fetch('profile.php',{method:'POST',body:fd}).then(r=>r.text()).then(()=>window.location.reload()).catch(()=>{_showUploadAlert('Upload failed. Please try again.','error');btn.disabled=false;btn.innerHTML='<i class="fas fa-upload"></i> Upload Photo';});
});
function _showUploadAlert(msg,type){
    let el=document.getElementById('uploadAlert');
    if(!el){el=document.createElement('div');el.id='uploadAlert';el.style.cssText='font-size:12px;font-weight:600;margin-top:7px;padding:7px 11px;border-radius:7px;display:none';document.getElementById('picForm').appendChild(el);}
    if(!msg){el.style.display='none';return;}
    el.style.display='block';el.style.background=type==='error'?'#fee2e2':'#dcfce7';el.style.color=type==='error'?'#991b1b':'#166534';el.style.border='1px solid '+(type==='error'?'#fca5a5':'#86efac');el.textContent=msg;
}

/* ── PASSWORD ── */
function togglePw(fieldId,btn){const f=document.getElementById(fieldId),i=btn.querySelector('i');if(f.type==='password'){f.type='text';i.className='fas fa-eye-slash';}else{f.type='password';i.className='fas fa-eye';}}
function checkStrength(pw){const bar=document.getElementById('pwBar'),label=document.getElementById('pwLabel');let s=0;if(pw.length>=6)s++;if(pw.length>=10)s++;if(/[A-Z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;const lv=[{w:'0%',bg:'transparent',t:''},{w:'25%',bg:'#ef4444',t:'Weak'},{w:'50%',bg:'#f97316',t:'Fair'},{w:'75%',bg:'#eab308',t:'Good'},{w:'100%',bg:'#22c55e',t:'Strong'}][Math.min(s,4)];bar.style.width=pw.length?lv.w:'0%';bar.style.background=lv.bg;label.textContent=pw.length?lv.t:'';}
function validatePwForm(){const np=document.getElementById('new_password').value,cp=document.getElementById('confirm_password').value;if(np!==cp){alert('Passwords do not match.');return false;}if(np.length<6){alert('Minimum 6 characters.');return false;}return true;}

document.querySelectorAll('.alert').forEach(function(el){setTimeout(function(){el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);},5000);});
</script>
</body>
</html>
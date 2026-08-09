<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION['systemio_authenticated'])) {
    header('Location: /');
    exit;
}

$dirPath = '/home/work/html/files';
if (!is_dir($dirPath)) {
    mkdir($dirPath, 0775, true);
}

function cleanName(string $name): string {
    return basename(trim($name));
}

function formatSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' Б';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, '.', '') . ' КБ';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1, '.', '') . ' МБ';
    return number_format($bytes / 1073741824, 1, '.', '') . ' ГБ';
}

function fileKind(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) return 'image';
    if (in_array($ext, ['apk', 'aab'], true)) return 'android';
    if (in_array($ext, ['exe', 'msi'], true)) return 'windows';
    if (in_array($ext, ['py', 'js', 'java', 'html', 'css'], true)) return 'code';
    if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true)) return 'archive';
    if (in_array($ext, ['ico', 'svg'], true)) return 'icon';
    return 'file';
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'upload' && isset($_FILES['uploaded_file'])) {
        $file = $_FILES['uploaded_file'];
        $name = cleanName((string)$file['name']);
        if ($file['error'] === UPLOAD_ERR_OK && $name !== '') {
            move_uploaded_file($file['tmp_name'], $dirPath . '/' . $name);
        }
    } elseif ($action === 'mkdir') {
        $name = cleanName((string)($_POST['name'] ?? ''));
        if ($name !== '') mkdir($dirPath . '/' . $name, 0775, true);
    } elseif ($action === 'delete') {
        $name = cleanName((string)($_POST['name'] ?? ''));
        $target = $dirPath . '/' . $name;
        if (is_file($target)) unlink($target);
        elseif (is_dir($target) && count(scandir($target)) === 2) rmdir($target);
    }
    header('Location: /files.php?sort=' . urlencode((string)($_GET['sort'] ?? 'name')));
    exit;
}

if (isset($_GET['download'])) {
    $name = cleanName((string)$_GET['download']);
    $path = $dirPath . '/' . $name;
    if (is_file($path)) {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $imageTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        if (isset($imageTypes[$extension])) {
            header('Content-Type: ' . $imageTypes[$extension]);
            header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        }
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
    exit;
}

$items = [];
foreach (scandir($dirPath) ?: [] as $name) {
    if ($name === '.' || $name === '..') continue;
    $path = $dirPath . '/' . $name;
    if (!is_file($path) && !is_dir($path)) continue;
    $isDir = is_dir($path);
    $items[] = [
        'name' => $name,
        'is_dir' => $isDir,
        'size' => $isDir ? 'каталог' : formatSize((int)filesize($path)),
        'bytes' => $isDir ? -1 : (int)filesize($path),
        'time' => (int)filemtime($path),
        'kind' => $isDir ? 'folder' : fileKind($name),
    ];
}
$sort = $_GET['sort'] ?? 'name';
usort($items, static function (array $a, array $b) use ($sort): int {
    if ($sort === 'size') return $b['bytes'] <=> $a['bytes'];
    if ($sort === 'date') return $b['time'] <=> $a['time'];
    return strcasecmp($a['name'], $b['name']);
});
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>File Server</title>
    <style>
        :root { font-family: Inter, system-ui, -apple-system, sans-serif; color: #e8f1ff; background: #0b1729; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 18px; background: #0b1729; }
        main { max-width: 1180px; margin: 0 auto; }
        header { margin-bottom: 22px; padding: 20px 22px; border: 1px solid #28466d; border-radius: 22px; background: linear-gradient(120deg, #0a1930, #163d6d 62%, #078eac); box-shadow: 0 18px 50px #02081777; }
        .head-row { display:flex; align-items:center; justify-content:space-between; gap:18px; }
        .eyebrow { margin: 0 0 6px; color: #7dd3fc; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 0; color: white; font-size: clamp(24px, 5vw, 34px); letter-spacing: -.03em; }
        .logout { color: #d6f8ff; font-size: 13px; font-weight: 700; text-decoration: none; }
        .toolbar { display: flex; align-items: center; justify-content: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
        .actions { display:flex; flex-wrap:wrap; align-items:center; gap:9px; }
        .upload-label, .folder-form button, .sort a { display:inline-block; padding:10px 14px; border:1px solid #31557f; border-radius:12px; background:#152b49; color:#e8f1ff; cursor:pointer; font:inherit; font-size:13px; font-weight:700; text-decoration:none; }
        .upload-label { background:#078eac; border-color:#65d9eb; }
        .upload input { display:none; }
        .folder-form { display:flex; gap:7px; }
        .folder-form input { width:130px; padding:10px 12px; border:1px solid #31557f; border-radius:12px; outline:none; background:#152b49; color:#e8f1ff; }
        .sort { display:flex; flex-wrap:wrap; gap:7px; }
        .sort a { padding:8px 10px; color:#9bb6d6; font-size:12px; }
        .sort a.active, .sort a:hover, .logout:hover, .folder-form button:hover { color:white; border-color:#42e8c5; }
        .sort a.active { background:#1b5470; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:16px; }
        .card { min-height:190px; padding:14px; display:flex; flex-direction:column; justify-content:space-between; border:1px solid #29476b; border-radius:17px; background:#10223a; box-shadow:0 12px 30px #02081766; }
        .preview { height:100px; display:grid; place-items:center; overflow:hidden; border-radius:11px; background:#0a1930; color:#7dd3fc; font-size:42px; text-decoration:none; }
        .preview img { width:100%; height:100%; object-fit:cover; }
        .filename { display:block; overflow:hidden; margin-top:12px; color:#e8f1ff; font-size:14px; font-weight:600; text-overflow:ellipsis; text-decoration:none; white-space:nowrap; }
        .card-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:9px; }
        .meta { display:flex; justify-content:space-between; gap:8px; color:#7f9abb; font-size:12px; }
        .delete { width:34px; height:34px; padding:0; border:1px solid #31557f; border-radius:10px; background:#152b49; color:#ff9baf; cursor:pointer; font-size:16px; }
        .delete:hover { border-color:#ff6f91; }
        @media(max-width:620px){body{padding:10px}.head-row{align-items:flex-start;flex-direction:column}.toolbar{align-items:flex-start;flex-direction:column;gap:10px}.actions{flex-direction:row}.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}}
    </style>
</head>
<body>
<main>
    <header><div class="head-row"><div><p class="eyebrow">MYAPP · VPS STORAGE</p><h1>File Server</h1></div><a class="logout" href="/?logout=1">Выйти</a></div></header>
    <div class="toolbar">
        <div class="actions">
            <form class="upload" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload"><label class="upload-label">＋ Загрузить файл<input type="file" name="uploaded_file" onchange="this.form.submit()"></label></form>
            <form class="folder-form" method="post"><input type="hidden" name="action" value="mkdir"><input name="name" maxlength="80" placeholder="Имя каталога" required><button type="submit">＋ Каталог</button></form>
        </div>
        <nav class="sort"><a class="<?= $sort === 'name' ? 'active' : '' ?>" href="?sort=name">Имя</a><a class="<?= $sort === 'size' ? 'active' : '' ?>" href="?sort=size">Размер</a><a class="<?= $sort === 'date' ? 'active' : '' ?>" href="?sort=date">Дата</a></nav>
    </div>
    <section class="grid">
    <?php foreach ($items as $item): ?>
        <article class="card">
            <?php if ($item['is_dir']): ?>
                <div class="preview">📁</div>
            <?php else: ?>
                <a class="preview" href="?download=<?= urlencode($item['name']) ?>">
                    <?php if ($item['kind'] === 'image'): ?><img src="?download=<?= urlencode($item['name']) ?>" alt="<?= h($item['name']) ?>">
                    <?php elseif ($item['kind'] === 'android'): ?>🤖
                    <?php elseif ($item['kind'] === 'windows'): ?>▣
                    <?php elseif ($item['kind'] === 'code'): ?>‹/›
                    <?php elseif ($item['kind'] === 'archive'): ?>▤
                    <?php elseif ($item['kind'] === 'icon'): ?>✦
                    <?php else: ?>📄<?php endif; ?>
                </a>
            <?php endif; ?>
            <span class="filename" title="<?= h($item['name']) ?>"><?= h($item['name']) ?></span>
            <div class="card-footer"><div class="meta"><span><?= h($item['size']) ?></span><span><?= h($item['kind']) ?></span></div><form method="post" onsubmit="return confirm('Удалить <?= h($item['name']) ?>?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= h($item['name']) ?>"><button class="delete" title="Удалить <?= h($item['name']) ?>" aria-label="Удалить <?= h($item['name']) ?>">🗑</button></form></div>
        </article>
    <?php endforeach; ?>
    </section>
</main>
</body>
</html>

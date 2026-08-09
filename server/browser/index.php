<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', true, true);
    }
    session_destroy();
    header('Location: /');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = (string)($_POST['pin'] ?? '');
    if (preg_match('/^\d{4}$/', $pin) && hash_equals('1111', $pin)) {
        session_regenerate_id(true);
        $_SESSION['systemio_authenticated'] = true;
        header('Location: /');
        exit;
    }
    $error = 'Неверный PIN-код';
}

if (empty($_SESSION['systemio_authenticated'])):
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SystemIO · Вход</title>
    <style>
        :root { font-family: Inter, system-ui, sans-serif; color: #e8f1ff; background: #0b1729; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: #0b1729; }
        main { width: min(100%, 390px); padding: 30px 26px; border: 1px solid #28466d; border-radius: 24px; background: #101f35; box-shadow: 0 24px 70px #02081799; text-align: center; }
        .logo { width: 62px; height: 62px; display: grid; place-items: center; margin: 0 auto 18px; border-radius: 18px; background: linear-gradient(135deg, #163d6d, #078eac); color: #7dd3fc; font-size: 28px; font-weight: 800; }
        h1 { margin: 0 0 8px; color: white; font-size: 28px; }
        p { margin: 0 0 22px; color: #8fa8c7; }
        input { width: 100%; padding: 14px; border: 1px solid #31557f; border-radius: 13px; outline: none; background: #0a1930; color: white; font-size: 24px; letter-spacing: .45em; text-align: center; }
        input:focus { border-color: #42e8c5; box-shadow: 0 0 0 3px #42e8c522; }
        button { width: 100%; margin-top: 14px; padding: 14px; border: 0; border-radius: 13px; background: #078eac; color: white; cursor: pointer; font-size: 16px; font-weight: 700; }
        button:hover { background: #11abc5; }
        .error { margin: 12px 0 0; color: #ff9baf; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <div class="logo">IO</div>
    <h1>SystemIO</h1>
    <p>Введите PIN-код для входа</p>
    <form method="post" autocomplete="off">
        <input name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" minlength="4" aria-label="PIN-код" autofocus required>
        <button type="submit">Войти</button>
    </form>
    <?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
</main>
</body>
</html>
<?php
exit;
endif;

require '/home/work/index-systemio-original.php';

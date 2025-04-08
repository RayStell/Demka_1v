<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    header("Location: login.php");
    exit();
}

// Подключение к БД
$db = new PDO('mysql:host=localhost; dbname=module; charset=utf8', 
'root', 
null, 
[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Получаем информацию о пользователе по токену
$token = $_SESSION['token'];
$admin = $db->query("SELECT id, login, type, name, surname FROM users WHERE token = '$token'")->fetch();

// Если пользователь не найден или не админ
if (!$admin || $admin['type'] !== 'admin') {
    $_SESSION['token'] = '';
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Обработка разблокировки пользователя
if (isset($_POST['unblock_user']) && !empty($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET blocked = 0, amountAttempt = 0 WHERE id = ?");
    if ($stmt->execute([$userId])) {
        $success = 'Пользователь разблокирован';
    } else {
        $error = 'Ошибка при разблокировке пользователя';
    }
}

// Обработка блокировки пользователя
if (isset($_POST['block_user']) && !empty($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET blocked = 1 WHERE id = ?");
    if ($stmt->execute([$userId])) {
        $success = 'Пользователь заблокирован';
    } else {
        $error = 'Ошибка при блокировке пользователя';
    }
}

// Получаем список всех пользователей
$users = $db->query("SELECT id, login, name, surname, blocked, amountAttempt, latest FROM users WHERE type != 'admin' ORDER BY latest DESC")->fetchAll();

// Проверяем и блокируем неактивных пользователей (более месяца)
$monthAgo = date('Y-m-d H:i:s', strtotime('-1 month'));
$db->query("UPDATE users SET blocked = 1 WHERE latest < '$monthAgo' AND type != 'admin'");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>Панель администратора</title>
</head>
<body>
    <div class="admin-panel">
        <h1>Панель администратора</h1>
        <div class="admin-info">
            <p>Администратор: <?php echo htmlspecialchars($admin['name'] . ' ' . $admin['surname']); ?></p>
        </div>
        
        <?php if(!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        
        <h2>Список пользователей</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Логин</th>
                        <th>Имя</th>
                        <th>Фамилия</th>
                        <th>Статус</th>
                        <th>Попыток входа</th>
                        <th>Последняя активность</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['login']); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['surname']); ?></td>
                        <td class="status-<?php echo $user['blocked'] ? 'blocked' : 'active'; ?>">
                            <?php echo $user['blocked'] ? 'Заблокирован' : 'Активен'; ?>
                        </td>
                        <td><?php echo $user['amountAttempt']; ?></td>
                        <td><?php echo $user['latest'] ? date('d.m.Y H:i', strtotime($user['latest'])) : 'Нет данных'; ?></td>
                        <td>
                            <?php if($user['blocked']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="unblock_user" class="btn-unblock">Разблокировать</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="block_user" class="btn-block">Заблокировать</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <p><a href="login.php?logout=1" class="logout-link">Выйти</a></p>
    </div>
</body>
</html> 
<?php
session_start();

// Функция для сохранения учетных данных пользователей
function saveUser($username, $password) {
    $usersFile = 'users.txt';
    $users = file_exists($usersFile) ? file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $users[] = "$username:$password";
    file_put_contents($usersFile, implode("\n", $users));
}

// Функция для проверки учетных данных пользователей
function checkUser($username, $password) {
    $usersFile = 'users.txt';
    $users = file_exists($usersFile) ? file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    foreach ($users as $user) {
        list($user, $pass) = explode(':', $user);
        if ($user === $username && $pass === $password) {
            return true;
        }
    }
    return false;
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Проверка авторизации
if (!isset($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (isset($_POST['register'])) {
            // Регистрация нового пользователя
            saveUser($username, $password);
            $_SESSION['username'] = $username;
        } elseif (checkUser($username, $password)) {
            // Авторизация существующего пользователя
            $_SESSION['username'] = $username;
        } else {
            $error = "Неверное имя пользователя или пароль";
        }
    }

    if (!isset($_SESSION['username'])) {
        // Форма авторизации и регистрации
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Авторизация и Регистрация</title>
            <link rel="stylesheet" href="css/style.css">
        </head>
        <body>
            <div class="container">
                <h2>Авторизация и Регистрация</h2>
                <?php if (isset($error)) echo "<p>$error</p>"; ?>
                <form action="" method="post">
                    <input type="text" name="username" placeholder="Имя пользователя" required>
                    <input type="password" name="password" placeholder="Пароль" required>
                    <button type="submit" name="register" value="1">Зарегистрироваться</button>
                    <button type="submit" name="login" value="1">Войти</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Обработка отправки сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = htmlspecialchars($_POST['message']);
    $to = htmlspecialchars($_POST['to']);
    $from = $_SESSION['username'];
    $timestamp = date('Y-m-d H:i:s');

    // Сохранение сообщения в файл
    $logFile = 'chat_log.txt';
    file_put_contents($logFile, "$timestamp | $from -> $to: $message\n", FILE_APPEND);
}

// Загрузка истории сообщений
$logFile = 'chat_log.txt';
$messages = file_exists($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Убираем фильтрацию сообщений
$filteredMessages = $messages;

// Обработка запроса на загрузку новых сообщений
if (isset($_GET['loadMessages']) && isset($_GET['lastTimestamp'])) {
    $lastTimestamp = $_GET['lastTimestamp'];
    $newMessages = array_filter($filteredMessages, function($msg) use ($lastTimestamp) {
        $parts = explode(' | ', $msg);
        return $parts[0] > $lastTimestamp;
    });
    foreach ($newMessages as $message) {
        echo "<p>$message</p>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Чат</h2>
        <div id="chat-box">
            <?php foreach ($filteredMessages as $message): ?>
                <p><?= $message ?></p>
            <?php endforeach; ?>
        </div>
        <form action="" method="post" id="chat-form">
            <input type="text" name="to" placeholder="Кому" required>
            <input type="text" name="message" placeholder="Сообщение" required>
            <button type="submit">Отправить</button>
        </form>
        <a href="?logout">Выйти</a>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatBox = document.getElementById('chat-box');
            const chatForm = document.getElementById('chat-form');
            let lastTimestamp = '<?php echo isset($filteredMessages[count($filteredMessages)-1]) ? explode(' | ', $filteredMessages[count($filteredMessages)-1])[0] : date('Y-m-d H:i:s'); ?>';

            function loadMessages() {
                fetch(`index.php?loadMessages=1&lastTimestamp=${lastTimestamp}`)
                    .then(response => response.text())
                    .then(data => {
                        if (data) {
                            chatBox.insertAdjacentHTML('beforeend', data);
                            const newMessages = chatBox.querySelectorAll('p:last-of-type');
                            newMessages.forEach(msg => {
                                const msgTimestamp = msg.textContent.split(' | ')[0];
                                if (msgTimestamp > lastTimestamp) {
                                    lastTimestamp = msgTimestamp;
                                }
                            });
                            chatBox.scrollTop = chatBox.scrollHeight;
                        }
                    });
            }

            chatForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(chatForm);
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    loadMessages();
                    chatForm.reset();
                });
            });

            // Обновление чата каждую секунду
            setInterval(loadMessages, 1000);
        });
    </script>
</body>
</html>
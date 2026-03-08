<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

$appEnv = $_ENV['APP_ENV'] ?? 'production';
if ($appEnv === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'tiny_tales';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed.');
}

define('MAX_WORD_COUNT', 1000);
define('MIN_WORD_COUNT', 50);
define('DEFAULT_WORD_COUNT', 100);
define('EXPORT_PATH', __DIR__ . '/exports/');

if (!file_exists(EXPORT_PATH)) {
    mkdir(EXPORT_PATH, 0755, true);
}

session_start();

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['prefs'] = $stmt->fetch() ?: [
        'dark_mode' => false,
        'default_word_count' => DEFAULT_WORD_COUNT,
        'default_genre' => null
    ];
}
?>

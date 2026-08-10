<?php
// Production credentials can come from environment variables or from the
// ignored includes/db.local.php file used by hosts without env-var settings.
$localConfig = [];
$localConfigPath = __DIR__ . '/db.local.php';
if (is_file($localConfigPath)) {
    $loadedConfig = require $localConfigPath;
    if (is_array($loadedConfig)) {
        $localConfig = $loadedConfig;
    }
}

$host = getenv('DB_HOST') ?: ($localConfig['host'] ?? 'localhost');
$port = getenv('DB_PORT') ?: ($localConfig['port'] ?? '3306');
$user = getenv('DB_USER') ?: ($localConfig['user'] ?? 'root');
$pass = getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? '');
$dbname = getenv('DB_NAME') ?: ($localConfig['name'] ?? 'Fit_Track');

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set connection timeout and other settings
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    $pdo->exec("SET SESSION wait_timeout = 28800"); // 8 hours
    $pdo->exec("SET SESSION interactive_timeout = 28800"); // 8 hours
} catch (PDOException $e) {
    error_log('PDO database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Check the server configuration.');
}

// Also create MySQLi connection for backward compatibility
$conn = new mysqli($host, $user, $pass, $dbname, (int) $port);

if ($conn->connect_error) {
    error_log('MySQLi database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Unable to connect to the database. Check the server configuration.');
}

// Set connection timeout and other settings to prevent "MySQL server has gone away" errors
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 60);
$conn->options(MYSQLI_OPT_READ_TIMEOUT, 60);
$conn->set_charset("utf8mb4");

// Optimize connection settings (without query cache since it's globally disabled)
$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
$conn->query("SET SESSION wait_timeout = 28800"); // 8 hours
$conn->query("SET SESSION interactive_timeout = 28800"); // 8 hours

// Optimized connection check function
if (!function_exists('checkConnection')) {
    function checkConnection() {
        global $conn;
        
        if (!$conn->ping()) {
            $conn->close();
            global $host, $port, $user, $pass, $dbname;
            $conn = new mysqli($host, $user, $pass, $dbname, (int) $port);
            if ($conn->connect_error) {
                error_log("Database reconnection failed: " . $conn->connect_error);
                return false;
            }
            $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            $conn->options(MYSQLI_OPT_READ_TIMEOUT, 30);
            $conn->set_charset("utf8mb4");
        }
        return $conn;
    }
}
?>

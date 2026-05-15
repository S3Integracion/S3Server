<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    /**
     * @var array<string, PDO>
     */
    private static array $connections = [];

    public static function getConnection(string $name = 'primary'): PDO
    {
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::int('DB_PORT', 3306);
        $user = Env::get('DB_USER', '');
        $password = Env::get('DB_PASSWORD', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $dbName = self::resolveDatabaseName($name);

        if ($dbName === '' || $user === '') {
            throw new RuntimeException(sprintf('Database settings are incomplete for connection "%s".', $name));
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connections[$name] = new PDO($dsn, $user, $password, $options);
            return self::$connections[$name];
        } catch (PDOException $exception) {
            throw new RuntimeException(sprintf('Database connection "%s" failed.', $name), 0, $exception);
        }
    }

    private static function resolveDatabaseName(string $name): string
    {
        if ($name === 'primary') {
            return (string) Env::get('DB_NAME', '');
        }

        if ($name === 'orders') {
            return (string) Env::get('ORDERS_DB_NAME', 'OrderDetails');
        }

        throw new RuntimeException(sprintf('Unknown database connection "%s".', $name));
    }
}

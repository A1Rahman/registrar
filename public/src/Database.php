<?php
declare(strict_types=1);

/**
 * Gives the rest of the app its PDO connection.
 * The connection itself is created by db() in db.php (the only place with
 * database-specific code), so the other classes never depend on it directly.
 */
class Database
{
    public static function connection(): PDO
    {
        return db();
    }
}

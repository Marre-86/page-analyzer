<?php

namespace Hexlet\Code\Misc;

function tableExists($pdo, $table)
{

    // Try a select statement against the table
    // Run it in try-catch in case PDO is in ERRMODE_EXCEPTION.
    try {
        $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
    } catch (\PDOException $e) {
        // We got an exception (table not found)
        return false;
    }

    // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
    return $result !== false;
}

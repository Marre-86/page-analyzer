<?php

namespace Hexlet\Code;

class Query
{
    private \PDO $pdo;
    private string $table;

    public function __construct(\PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }
    public function insertValues(string $name, string $created_at)
    {
        // подготовка запроса для добавления данных
        $sql = "INSERT INTO {$this->table}(name, created_at) VALUES(:name, :created_at)";
            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':created_at', $created_at);
            $stmt->execute();

        // возврат полученного значения id
        return $this->pdo->lastInsertId();
    }
    public function insertValuesChecks(array $check)
    {
        // подготовка запроса для добавления данных
        $sql = "INSERT INTO {$this->table}(url_id, status_code, h1, title, description, created_at)
                VALUES(:url_id, :status_code, :h1, :title, :description, :created_at)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':url_id', $check['url_id']);
            $stmt->bindValue(':status_code', $check['status_code'] ?? null);
            $stmt->bindValue(':h1', $check['h1'] ?? null);
            $stmt->bindValue(':title', $check['title'] ?? null);
            $stmt->bindValue(':description', $check['description'] ?? null);
            $stmt->bindValue(':created_at', $check['date']);
            $stmt->execute();

        // возврат полученного значения id
        return $this->pdo->lastInsertId();
    }
}

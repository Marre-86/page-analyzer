<?php

namespace Hexlet\Code;

class Query
{
    private $pdo;
    private $table;
    private $data = [
        'select' => '*',
        'where' => []
    ];

    public function __construct($pdo, $table, $data = null)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        if ($data) {
            $this->data = $data;
        }
    }
    public function insertValues($name, $created_at)
    {
        // подготовка запроса для добавления данных
        $sql = 'INSERT INTO urls(name, created_at) VALUES(:name, :created_at)';
            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':created_at', $created_at);
            $stmt->execute();

        // возврат полученного значения id
        return $this->pdo->lastInsertId();
    }
    public function insertValuesChecks($url_id, $created_at, $status_code)
    {
        // подготовка запроса для добавления данных
        $sql = 'INSERT INTO url_checks(url_id, status_code, created_at) VALUES(:url_id, :status_code, :created_at)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':url_id', $url_id);
            $stmt->bindValue(':status_code', $status_code ?? null);
            $stmt->bindValue(':created_at', $created_at);
            $stmt->execute();

        // возврат полученного значения id
        return $this->pdo->lastInsertId();
    }
}

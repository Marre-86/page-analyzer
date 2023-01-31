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
}

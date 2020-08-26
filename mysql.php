<?php

require_once __DIR__ . '/config.php';

class PDOWrapper {
    public $instance = null;

    public function __construct() {
        $dsn = 'mysql:host='.MYSQL_HOST.';dbname='.MYSQL_DATABASE.';port='.MYSQL_PORT;
        try {
            $this->instance = new PDO($dsn, MYSQL_USER, MYSQL_PASSWORD);
        } catch (Exception $e) {
            die("Unable to connect to database : ".$e->getMessage());
        }
    }
    public function query($sql, $data = null) {
        $sth = $this->instance->prepare($sql);
        $sth->execute($data);
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 1) {
            return $result[0];
        } else {
            return $result;
        }
    }

    public function lastInsertId() {
        return $this->instance->lastInsertId();
    }
}

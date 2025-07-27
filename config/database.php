<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'lims_hospital';
    private $username = 'root';
    private $password = '';
    private $connection;
    
    public function connect() {
        $this->connection = null;
        try {
            $this->connection = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
        return $this->connection;
    }
}
?>
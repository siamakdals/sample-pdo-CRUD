<?php


class DB
{

    private $conn = '';
    private $log_errors = false;

    public function __construct($db = "", $username = "", $password = "", $servername = "localhost", $log_errors = true)
    {
        $this->log_errors = $log_errors;

        try {
            $this->conn = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
            $this->conn->exec("set names utf8mb4");
        } catch
        (PDOException $e) {
            //echo "Connection failed: " . $e->getMessage();
            $this->logError($e->getMessage());
        }

    }

    public function query($sql)
    {
        $stm = $this->conn->prepare($sql);
        $res = $stm->execute();
        $this->logError($sql);
        return $res;
    }

    public function insertData($table, $fields, $data)
    {

        if (is_array($fields))
            $fields = implode(',', $fields);

        if (is_array($data))
            $data = implode("','", $data);

        $data = "'" . $data . "'";

        $sql = "INSERT INTO $table ($fields) VALUES ($data)";
        $res = $this->conn->exec($sql);
        $this->logError($sql);

        if ($res)
            return $this->conn->lastInsertId();
        else
            return null;
    }

    public function multiInsert(array $data, array $table_columns, $table)
    {
        $fields = implode(',', $table_columns);
        $sql = [];

        foreach ((array)$data as $row) {
            $sql[] = "('" . implode("','", $row) . "')";
        }

        $values = implode(',', $sql);

        return $this->query("INSERT INTO {$table} ({$fields}) VALUES {$values}");
    }

    public function getData($table, $fields = '*', array $where_key_data, $order = 'ASC', $order_by = 'id',
                            $limit = 0, $fetchAll = false, $offset = 0, $search = null, $group_by = null)
    {
        $where = '';
        foreach ($where_key_data as $key => $data) {
            $last_key = $key;
        }
        foreach ($where_key_data as $key => $data) {
            $virgol = $key == $last_key ? '' : ' AND ';
            $where .= $key . "='" . $data . "'$virgol";
        }

        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }

        $sql = "SELECT $fields FROM $table";
        if (!empty($where))
            $sql = "SELECT $fields FROM $table WHERE $where";

        if (!empty($search)) {
            $sql = "SELECT $fields FROM $table WHERE " . "`persian_title` LIKE '%$search%' OR `title` LIKE '%$search%'";
        }

        if (!empty($group_by))
            $sql .= " GROUP BY {$group_by}";
        if ($order_by != 'id')
            $sql .= " ORDER BY {$order_by} {$order}";
        if ($order_by == 'id' && $order != 'ASC')
            $sql .= " ORDER BY {$order_by} {$order}";
        if (!empty($limit))
            $sql .= " LIMIT {$limit}";
        if (!empty($offset))
            $sql .= " OFFSET {$offset}";
        $sql_smt = $sql;

        $sql = $this->conn->prepare($sql);
        $sql->execute();
        if ($fetchAll)
            $res = $sql->fetchAll(PDO::FETCH_ASSOC);
        else
            $res = $sql->fetch(PDO::FETCH_ASSOC);

        $this->logError($sql_smt);
        if ($res)
            return $res;
        else
            return null;
    }

    public function getRow($sql)
    {
        $stm = $this->conn->prepare($sql);
        $stm->execute();
        $res = $stm->fetch(PDO::FETCH_ASSOC);
        $this->logError($sql);
        return $res;
    }

    public function getResult($sql)
    {
        $stm = $this->conn->prepare($sql);
        $stm->execute();
        $res = $stm->fetchAll(PDO::FETCH_ASSOC);
        $this->logError($sql);
        return $res;
    }

    public function updateData($table, array $key_data, array $where_key_data)
    {
        $sql = '';
        $last_key = '';
        foreach ($key_data as $key => $data) {
            $last_key = $key;
        }
        foreach ($key_data as $key => $data) {
            $virgol = $key == $last_key ? '' : ',';
            $sql .= $key . "='" . $data . "'$virgol";
        }

        $where = '';
        foreach ($where_key_data as $key => $data) {
            $last_key = $key;
        }
        foreach ($where_key_data as $key => $data) {
            $virgol = $key == $last_key ? '' : ' AND ';
            $where .= $key . "='" . $data . "'$virgol";
        }

        $sql = "UPDATE $table SET $sql WHERE $where";
        $res = $this->conn->exec($sql);

        $this->logError($sql);
        if ($res)
            return true;
        else
            return null;
    }

    public function deleteData($table, array $where_key_data)
    {
        $where = '';
        $last_key = end(array_keys($where_key_data));

        foreach ($where_key_data as $key => $data) {
            $virgol = $key == $last_key ? '' : ' AND ';
            $where .= $key . "='" . $data . "'$virgol";
        }

        $sql = "DELETE FROM $table";
        if (!empty($where))
            $sql .= " WHERE $where";

        $res = $this->conn->exec($sql);

        $this->logError($sql);

        if ($res)
            return true;
        else
            return null;
    }

    private function logError($sql = null)
    {
        if (!$this->log_errors)
            return;
        if (!empty($this->conn) && ($this->conn->errorCode() == 0000))
            return;

        $txt = "\n-------| " . date('Y-m-d H:i:s') . " |-------\n";

        if (!empty($this->conn)) {
            $txt .= var_export($this->conn->errorInfo(), true) . "\n";
            $txt .= "SQL:\n$sql\n";
        } else {
            $txt .= "$sql\n";
        }
        $txt .= '-------------------------------------' . "\n";
        $file = 'db_err.log';
        //log
        $handel = fopen($file, 'a+');
        fwrite($handel, $txt);
        fclose($handel);
    }

}

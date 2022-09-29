<?php

class DB
{

    private $conn = '';
    private $log_errors = false;
    private $bound = [];

    public function __construct($db = "", $username = "", $password = "", $servername = "localhost", $log_errors = true)
    {
        $this->log_errors = $log_errors;

        try {
            $this->conn = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->conn->exec("set names utf8mb4");
        } catch (PDOException $e) {
            //Connection failed
            $this->logError($e->getMessage());
        }

    }

    public function query($sql, $params = [])
    {
        try {
            $stm = $this->conn->prepare($sql);
            $stm->execute($params);
            return $stm->rowCount();
        } catch (Exception $e) {
            $this->logErrorWrapper($stm, $e);
            return null;
        }
    }

    public function insertData($table, $fields, $data)
    {
        try {

            $count = 1;
            if (is_array($fields)) {
                $count = count($fields);
                $fields = implode(',', $fields);
            }

            $question_mark = implode(',', array_fill(0, $count, '?'));

            $sql = "INSERT INTO `$table` ($fields) VALUES ($question_mark)";

            $smt = $this->conn->prepare($sql);

            $data = is_array($data) ? $data : [$data];
            $smt->execute($data);
            $smt->closeCursor();

            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            $this->logErrorWrapper($smt, $e);
            return 0;
        }
    }

    public function multiInsert(array $data, array $table_columns, $table)
    {
        $fields = implode(',', $table_columns);

        $values = [];
        $params = [];

        foreach ($data as $row) {
            $params = array_merge($params, array_values($row));
            $values[] = "(" . implode(',', array_fill(0, count($row), '?')) . ")";
        }

        $values = implode(',', $values);

        return $this->query("INSERT INTO {$table} ({$fields}) VALUES {$values}",$params);
    }

    public function getData($table, $fields = '*', array $where_key_data, $order = 'ASC', $order_by = 'id',
                            $limit = 0, $fetchAll = false, $offset = 0, $search = null, $group_by = null)
    {

        $where = $search ? $this->likeStatement($where_key_data) : $this->whereStatement($where_key_data);

        $fields = is_array($fields) ? implode(',', $fields) : $fields;

        $sql = "SELECT $fields FROM $table";
        if (!empty($where))
            $sql = "SELECT $fields FROM $table $where";

        if (!empty($group_by))
            $sql .= " GROUP BY {$group_by}";
        if ($order_by != 'id')
            $sql .= " ORDER BY {$order_by} {$order}";
        if ($order_by == 'id' && $order != 'ASC')
            $sql .= " ORDER BY {$order_by} {$order}";

        $limit = (int)$limit;
        $offset = (int)$offset;
        if (!empty($limit))
            $sql .= " LIMIT {$limit}";
        if (!empty($offset))
            $sql .= " OFFSET {$offset}";

        try {
            $smt = $this->conn->prepare($sql);
            $smt->execute($this->getBound());

            if ($fetchAll)
                $res = $smt->fetchAll(PDO::FETCH_ASSOC);
            else
                $res = $smt->fetch(PDO::FETCH_ASSOC);

            $smt->closeCursor();

            return $res;
        } catch (Exception $e) {
            $this->logErrorWrapper($smt, $e);
            return null;
        }
    }

    public function getRow($sql, $params = [])
    {
        try {
            $stm = $this->conn->prepare($sql);
            $stm->execute($params);
            $res = $stm->fetch(PDO::FETCH_ASSOC);
            $stm->closeCursor();
            return $res;
        } catch (Exception $e) {
            $this->logErrorWrapper($stm, $e);
            return null;
        }
    }

    public function getResult($sql, $params = [])
    {
        try {
            $stm = $this->conn->prepare($sql);
            $stm->execute($params);
            return $stm->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logErrorWrapper($stm, $e);
            return null;
        }
    }

    public function updateData($table, array $key_data, array $where_key_data)
    {
        $data_bound = [];
        array_walk($key_data, function (&$value, $key) use (&$data_bound) {
            $cursor_key = ":data_$key";
            $data_bound[$cursor_key] = $value;
            $value = "`$key`=$cursor_key";
        });

        $sql = implode(',', $key_data);
        $where = $this->whereStatement($where_key_data);

        $sql = "UPDATE $table SET $sql";
        $sql = !empty($where) ? $sql . " $where" : $sql;

        $params = array_merge($data_bound, $this->getBound());
        return $this->query($sql, $params);
    }

    public function upsert($table, array $key_data)
    {

        $fields = implode(',', array_keys($key_data));
        $question_mark_insert = implode(',', array_fill(0, count($key_data), '?'));

        $params = array_values($key_data);
        $params = array_merge($params, $params);

        //
        array_walk($key_data, function (&$value, $key) {
            $value = "`$key`=?";
        });
        $question_mark_update = implode(',', $key_data);

        $sql = "INSERT INTO {$table} ($fields) VALUES ($question_mark_insert)
            ON DUPLICATE KEY UPDATE $question_mark_update;";

        return $this->query($sql, $params);
    }

    public function deleteData($table, array $where_key_data)
    {

        $where = $this->whereStatement($where_key_data);

        $sql = "DELETE FROM $table";
        $sql = !empty($where) ? $sql . " $where" : $sql;

        return $this->query($sql, $this->getBound());
    }

    public function whereStatement(array $where_key_data, $operator = 'AND')
    {
        $bound = [];
        array_walk($where_key_data, function (&$value, $key) use (&$bound) {
            $bound[":$key"] = $value;
            $value = "`$key`=:$key";
        });

        $operator = ' ' . $operator . ' ';
        $where = implode($operator, $where_key_data);

        if (empty($where)) {
            return '';
        }

        $this->bound = $bound;

        return 'WHERE ' . $where;
    }

    public function likeStatement(array $column_data, $operator = 'OR')
    {
        $bound = [];
        array_walk($column_data, function (&$value, $key) use (&$bound) {
            $bound[":$key"] = '%' . $value . '%';
            $value = "`$key` LIKE :$key";
        });

        $operator = ' ' . $operator . ' ';
        $where = implode($operator, $column_data);

        if (empty($where)) {
            return '';
        }

        $this->bound = $bound;

        return 'WHERE ' . $where;
    }

    public function getBound()
    {
        $tmp = $this->bound;
        $this->bound = [];
        return $tmp;
    }

    private function logError($string = null)
    {
        $txt = "-------| " . date('Y-m-d H:i:s') . " |-------\n";
        $txt .= $string . "\n";
        $txt .= '-------------------------------------' . "\n";
        $file = 'db_err.log';
        //log
        $handel = fopen($file, 'a+');
        fwrite($handel, $txt);
        fclose($handel);
    }

    private function logErrorWrapper($smt, $exception)
    {
        if (!$this->log_errors)
            return;

        $is_pdo_object = method_exists($smt, 'errorCode');
        if ($is_pdo_object && $smt->errorCode() === 0000)
            return;

        $txt = "Message:\n" . $exception->getMessage() . "\n";
        $sql = $smt->queryString;

        if ($is_pdo_object) {
            $txt .= "Error info:\n" . var_export($smt->errorInfo(), true) . "\n";
            $txt .= "SQL:\n$sql\n";
            $txt .= "Trace:\n" . $exception->getTraceAsString();
        } else {
            $txt .= "$sql";
        }


        $this->logError($txt);
    }

}

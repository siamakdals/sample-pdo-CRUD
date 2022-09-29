<?php

include_once 'DB.php';

#create object
$DB = new DB('test', 'root', '', '127.0.0.1');


//insert
$id = $DB->insertData('users',
    [
        'username',
        'first_name',
        'last_name',
    ], [
        'john_d',
        'john',
        'draw',
    ]);

echo 'id is: ' . $id . '<br>';

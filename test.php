<?php

include_once 'DB.php';

//create object
$DB = new DB('taskmanager', 'root', '');


//insert
$id = $DB->insertData('tasks', ['title', 'text'], ['test', 'hello world']);
echo 'id is: ' . $id . '<br>';

<?php
require_once 'class.php';
require_once 'token.php';
use sancho2804\yandex_disk;

$yd=new yandex_disk\init($token);

$result=$yd->get_space('gb');
var_dump($result);
var_dump($yd->error);
// var_dump($yd->get_dir_info()['_embedded']['items']);
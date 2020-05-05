<?php
require_once 'class.php';
require_once 'token.php';
use sancho2804\yandex_disk;

$yd=new yandex_disk\init($token);

$result=$yd->get_space('gb');
$yd->add_skip_dirs(['/.git/','/.vscode/','/vendor/']);
$yd->add_skip_files(['/.gitignore']);
$yd->add_skip_files_by_name(['composer.lock']);
var_dump($result);
var_dump($yd->scan());
// var_dump($yd->get_dir_info()['_embedded']['items']);
<?php
include 'vendor/autoload.php';
include 'token.php';
use sancho2804\yandex_disk\main as yandex_disk;

//Создаем объект класса. Первый аргумент - токен для подключения к янднес диску. 
//Второй задает директорию откуда начинать сканирование (по умолчанию = папка запуска скрипта).
$yd=new yandex_disk($token,__DIR__);
$yd->start_path='/files/www/yandex_disk';//Таким образом мы можем менять начальную директория сканирования.

//Получение информации о месте на диске.
$result=$yd->get_space('gb'); 
var_dump($result);
echo '<hr>';
exit;

//Добавление папок, которые стоит пропускать. 
$yd->add_skip_dirs(['.vscode','vendor']);//Принимает массив из относительных путей от начальной директории сканирования. В примере будут пропущены папки /files/www/rest_client/.vscode и /files/www/rest_client/vendor
$yd->add_skip_dirs_by_name(['.git']);//Пропуск производится сравнивая имена файлов. В данном примере папка .git будет пропущена во всех папках и подпапках.

//Добавление файлов, которые стоит пропускать. 
$yd->add_skip_files(['.gitignore']);//Принимает массив из относительных путей до файла от начальной директории сканирования. В примере будет пропущен файл /files/www/rest_client/.gitignore
$yd->add_skip_files_by_name(['.ds_store']);//Пропуск производится сравнивая имена файлов. В данном примере файл .ds_store будет пропущен во всех папках и подпапках.

//Запуск синхронизации с яндекс диском.
$result=$yd->sync(null,'/test');//Первый аргумент - относительный путь от начальной директории сканирования. Второй - путь на яндекс диске куда закачивать файлы.
var_dump($result);
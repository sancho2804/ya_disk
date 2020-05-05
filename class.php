<?php
namespace sancho2804\yandex_disk;
require_once 'vendor/autoload.php';
use sancho2804\rest_client;

class init{
	private $rest=null;
	private $default_space_unit='gb';
	private $default_path='/!yd_sync.json';
	private $scan_path=null;
	private $start_path=null;
	private $skip_dirs=[];
	private $skip_files=[];
	private $skip_files_by_name=[];
	private $files=[];
	private $need_update=[];
	private $error='';

	public function __construct($token,$scan_path='/'){
		if (!$token) throw new \Error("Не указан токен");
		if (!$scan_path) throw new \Error("Не верно указан путь до сканируемой папки");
		$this->scan_path=$scan_path;
		$this->start_path=pathinfo(__FILE__,PATHINFO_DIRNAME);
		$this->init_rest($token);
		$this->read_complete_array();
		chdir($this->start_path);
	}

	public function __get($var){
		if ($var=='error') return $this->error;
	}

	private function init_rest($token){
		$this->rest=new rest_client\init('https://cloud-api.yandex.net:443/v1/','OAuth',$token);
		$this->rest->eat_json('vendor/sancho2804/rest_client/json/yandex_disk.json');
	}

	public function get_space($unit='mb'):array{
		$allow_units=[
			'b'=>1,
			'kb'=>1024,
			'mb'=>1024*1024,
			'gb'=>1024*1024*1024,
			'tb'=>1024*1024*1024*1024,
		];
		if (!$allow_units[$unit]) $unit=$this->default_space_unit;
		$info=$this->rest->exec('disk',null,true);
		var_dump($info);
		return [
			'total'=>$info['total_space']/$allow_units[$unit],
			'used'=>$info['used_space']/$allow_units[$unit],
			'free'=>($info['total_space']-$info['used_space'])/$allow_units[$unit],
			'trash'=>$info['trash_size']/$allow_units[$unit],
		];
	}

	public function set_path($path){
		if (!$path) throw new Error("Не верно указан путь для сканирования");
		$this->path=$path;
	}

	public function add_skip_dirs(array $dirs):bool{
		if (!$dirs) return false;
		$count=count($this->skip_dirs);
		$this->skip_dirs=array_merge($this->skip_dirs,$dirs);
		if ($count<count($this->skip_dirs)) return true;
		return false;
	}

	public function add_skip_files(array $files):bool{
		if (!$files) return false;
		$count=count($this->skip_files);
		$this->skip_files=array_merge($this->skip_files,$files);
		if ($count<count($this->skip_files)) return true;
		return false;
	}

	public function add_skip_files_by_name(array $names):bool{
		if (!$names) return false;
		$count=count($this->skip_files_by_name);
		$this->skip_files_by_name=array_merge($this->skip_files_by_name,$names);
		if ($count<count($this->skip_files_by_name)) return true;
		return false;
	}

	public function read_complete_array(string $path=null):bool{
		if (!$path) $path=$this->default_path;
		if (!file_exists($path)){
			$this->error='Файл с массивом найденных файлов не существует';
			return false;
		}
		$data=file_get_contents($path);
		if (!$data){
			$this->error='Пустой файл либо не удалось считать';
			return false;
		}
		$data=json_decode($data,true);
		if (json_last_error()!=JSON_ERROR_NONE){
			$this->error='Файл имеет невалидный JSON формат';
			return false;
		}
		$this->files=$data;
		return true;
	}

	public function save_complete_array(string $path=null):bool{
		if (!$path) $path=$this->default_path;
		$data=json_encode($this->files);
		if (json_last_error()!=JSON_ERROR_NONE){
			$this->error='Ошибка преобразования в JSON формат';
			return false;
		}
		if (!file_put_contents($path,$data)){
			$this->error='Ошибка сохранения массива найденных файлов в файл';
			return false;
		}
		return true;
	}

	public function scan($scan_path=null){
		if (!$scan_path) $scan_path=$this->scan_path;
		if (in_array($scan_path,$this->skip_dirs)) return;

		$test=$this->start_path.$scan_path;
		$files=scandir($this->start_path.$scan_path);
		for ($i=2; $i < count($files); $i++) { 
			$item=$this->start_path.$scan_path.$files[$i];
			if (is_file($item)){
				if (in_array($scan_path.$files[$i],$this->skip_files)) continue;
				if (in_array($files[$i],$this->skip_files_by_name)) continue;

				$last_modify=(int)filemtime($item);
				$md5_file=md5_file($item);
				if (!$this->files[$item]){
					$this->files[$item]=[
						'lastmod'=>$last_modify,
						'md5'=>$md5_file
					];
					$this->need_update[$item]=$this->files[$item];
				}else{
					if ($this->files[$item]['lastmod']<=$last_modify || $this->files[$item]['md5']!=$md5_file){
						$this->need_update[$item]=[
							'lastmod'=>$last_modify,
							'md5'=>$md5_file
						];
					}
				}
			}else{
				$this->scan($scan_path.$files[$i].'/');
			}
		}
		return $this->need_update;
	}

	public function sync(){

	}
}
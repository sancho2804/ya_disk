<?php
namespace sancho2804\yandex_disk;
require_once 'vendor/autoload.php';
use sancho2804\rest_client;

class init{
	private $rest=null;
	private $default_space_unit='gb';
	private $default_path='/!yd_sync.json';
	private $start_path=null;
	private $skip_dirs=[];
	private $skip_dirs_by_name=['.','..'];
	private $skip_files=['!yd_sync.json'];
	private $skip_files_by_name=[];
	private $files=[];
	private $need_update=[];
	private $exists_paths=[];
	private $save_complete_after=10;
	private $error='';

	public function __construct($token,$start_path=__DIR__.'/'){
		if (!$token) throw new \Error("Не указан токен");
		if (!$start_path) throw new \Error("Не верно указан путь до сканируемой папки");
		$this->set_start_path($start_path);
		$this->init_rest($token);
		$this->read_complete_array();
	}

	private function set_start_path($start_path){
		$this->start_path=rtrim($start_path,'/').'/';
		chdir($this->start_path);
	}

	public function __get($var){
		if ($var=='error') return $this->error;
	}

	public function __set($var,$val){
		if ($var=='start_path') $this->set_start_path($val);
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
		return [
			'total'=>$info['total_space']/$allow_units[$unit],
			'used'=>$info['used_space']/$allow_units[$unit],
			'free'=>($info['total_space']-$info['used_space'])/$allow_units[$unit],
			'trash'=>$info['trash_size']/$allow_units[$unit],
		];
	}

	public function add_skip_dirs(array $dirs):bool{
		if (!$dirs) return false;
		$count=count($this->skip_dirs);
		$dirs=array_map(function($item){
			return rtrim($item,'/').'/';
		},$dirs);
		$this->skip_dirs=array_merge($this->skip_dirs,$dirs);
		if ($count<count($this->skip_dirs)) return true;
		return false;
	}

	public function add_skip_dirs_by_name(array $names):bool{
		if (!$names) return false;
		$count=count($this->skip_dirs_by_name);
		$this->skip_dirs_by_name=array_merge($this->skip_dirs_by_name,$names);
		if ($count<count($this->skip_dirs_by_name)) return true;
		return false;
	}

	public function add_skip_files(array $files):bool{
		if (!$files) return false;
		$count=count($this->skip_files);
		$files=array_map(function($item){
			return rtrim($item,'/');
		},$files);
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

	private function read_complete_array(string $path=null):bool{
		if (!$path) $path=__DIR__.$this->default_path;
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

	private function save_complete_array(string $path=null):bool{
		if (!$path) $path=__DIR__.$this->default_path;
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

	public function scan($path=null){
		if (!$path) $path='';
		if (in_array($path,$this->skip_dirs)) return;

		$files=scandir($this->start_path.$path);
		for ($i=0; $i < count($files); $i++) { 
			$item=$this->start_path.$path.$files[$i];
			if (is_file($item)){
				if (in_array($files[$i],$this->skip_files_by_name)) continue;
				if (in_array($path.$files[$i],$this->skip_files)) continue;

				$last_modify=(int)filemtime($item);
				$md5_file=md5_file($item);

				if (!$this->files[$item] || $this->files[$item]['lastmod']<$last_modify || $this->files[$item]['md5']!=$md5_file){
					$this->need_update[$item]=[
						'lastmod'=>$last_modify,
						'relative_path'=>$path.$files[$i],
						'md5'=>$md5_file
					];
				}
			}else{
				if (in_array($files[$i],$this->skip_dirs_by_name)) continue;
				$this->scan($path.$files[$i].'/');
			}
		}
		return $this->need_update;
	}

	private function is_exist_path($path):bool{
		if (!$path) throw new \Error('Передан пустой путь');
		if ($this->exists_paths[$path]) return true;
		$this->rest->exec('dir_info',null,true,$path);
		if ($this->rest->last_request_info['http_code']==404) return false;
		$this->exists_paths[$path]='';
		return true;
	}

	private function mk_dir_tree($path):bool{
		if (!$path) throw new \Error('Передан пустой путь');
		if ($this->is_exist_path($path)) return true;
		$parts=explode('/',$path);
		$path_tmp='';
		$not_exist=false;
		for ($i=0; $i < count($parts); $i++) {
			if (!$parts[$i]) continue;
			$path_tmp.='/'.$parts[$i];
			if (!$not_exist && $this->is_exist_path($path_tmp) & $not_exist=true) continue;
			$this->rest->exec('mkdir',null,true,$path_tmp);
			if (in_array($this->rest->last_request_info['http_code'],[201,409])){
				if (!$this->exists_paths[$path_tmp]) $this->exists_paths[$path_tmp]='';
			}else{
				$this->error='Невозможно создать директория '.$path_tmp;
				return false;
			}
		}
		return true;
	}

	private function get_upload_link($relative_path){
		$link=$this->rest->exec('get_upload',null,true,$relative_path);
		if ($this->rest->last_request_info['http_code']!=200) return false;
		return $link['href'];
	}

	private function upload_file($full_path, $relative_path):bool{
		if (!file_exists($full_path)) return false;
		$link=$this->get_upload_link($relative_path);
		if (!$link){
			$this->error='Не удается закачать файл '.$full_path;
			return false;
		}
		$data=fopen($full_path,'r');
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_PUT, true);
		curl_setopt($curl, CURLOPT_UPLOAD, true);
		curl_setopt($curl, CURLOPT_INFILESIZE, filesize($full_path));
		curl_setopt($curl, CURLOPT_INFILE, $data);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		fclose($data);
		if ($http_code == 201) return true;
	}

	public function sync($path=null,$start_path_on_disk='/'){
		$start_path_on_disk=rtrim($start_path_on_disk,'/').'/';
		$this->scan($path);
		if (count($this->need_update)==0) return true;
		$uploaded=0;
		foreach($this->need_update as $full_path=>&$params){
			$params['path_on_disk']=$start_path_on_disk.$params['relative_path'];
			$path_on_disk=pathinfo($params['path_on_disk'],PATHINFO_DIRNAME);
			$this->mk_dir_tree($path_on_disk);
			$result=$this->upload_file($full_path,$params['path_on_disk']);
			if ($result){
				$this->files[$full_path]=$params;
				$uploaded++;
				if ($uploaded%$this->save_complete_after==0) $this->save_complete_array();
			}
		}
		$this->save_complete_array();
		$return=[
			'files'=>count($this->files),
			'need_update'=>count($this->need_update),
			'updated'=>$uploaded
		];
		$this->need_update=[];
		return $return;
	}
}
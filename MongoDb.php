<?php

namespace Rain;

class Db{

	var $grid;
	
	function __construct(){
		try {	
		
			$this->grid = (new \MongoClient('localhost:27017'))->selectDB("templates")->getGridFS();
			
		} catch (\MongoConnectionException $e) {
			die('Error connecting to DB server');
		} catch (\MongoException $e) {
			die('MongoDB: ' . $e->getMessage());
		}
	}
	
	public function getTemplate($filePath){
		
		$return = array("", 0);

		$tpl = $this->grid->findOne(array("meta.name" => $filePath));
		if (!is_null($tpl)){
			$return[1] = $tpl->file['meta']['md5'];
			$return[0] = $tpl->getBytes();	
		} else {
			$return[1] = 1;
		}
	
		return $return;
		
	}
	
	public function clearTemplates(){
		$this->grid->drop();
	}
	
	public function countTemplates(){
		
		$tpls = array();
		$tpls['count'] = 0;
		$tpls['names'] = array();
		
		$cursor = iterator_to_array($this->grid->find());
		
		foreach($cursor as $c){
			$tpls['count']++;
			$tpls['names'][] = $c->file['meta']['name'];
		}
		
		return $tpls;
	}
	
	public function storeTemplate($templateCode, $filePath, $md5_current){
				
		try {
			
			$this->grid->remove(array("meta.name" => $filePath));
		
			$this->grid->storeBytes($templateCode, array("meta" => array("md5" => $md5_current, "name" => $filePath)));
			
		} catch (\MongoException $e) {
			die('MongoDB: ' . $e->getMessage());
		}
		
	}
		
}

?>
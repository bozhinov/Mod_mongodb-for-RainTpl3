<?php 

namespace Rain;

require_once("Tpl.php");

function reducePath(&$path){
	$path = preg_replace(array("#(/{2,})#", "#(/\./+)#"), array("/", "/"), $path);				
	$path = str_replace("/", DIRECTORY_SEPARATOR, $path);
	$path = str_replace("\\\\", DIRECTORY_SEPARATOR, $path);
}

$fs = (new Tpl)->countTemplates();
$grid = (new Db)->countTemplates();

echo "Count templates filesystem: ".$fs['count']."<br />";
echo "Count templates in cache: ".$grid['count']."<br /><br />";

// TODO: this should not be necessary 
array_walk($fs['names'], 'Rain\reducePath');
array_walk($grid['names'], 'Rain\reducePath');

if ($fs['count'] != $grid['count']){
	echo "Not ready for production yet!<br />";
	echo "Missing templates in cache:<br />";
	foreach($fs['names'] as $f){
		if(!in_array($f, $grid['names'])){
			echo $f."<br />";
		}
	}
} else {
	echo "Ready for production!";
}

#echo "<br />";
#print_r($fs['names']);
#echo "<br />";
#print_r($grid['names']);

?>
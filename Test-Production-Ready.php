<?php 

namespace Rain;

require_once("Tpl.php");

function fixPath(&$path){
	$path = preg_replace(array("#(/{2,})#", "#(/\./+)#"), array("/", "/"), $path);				
	$path = str_replace("/", "\\", $path);
}

$tpl = new Tpl;
$tpl->configure(array("tpl_dir" => "../templates/"));
$fs = $tpl->countTemplates();
$grid = (new Db)->countTemplates();

echo "Count templates filesystem: ".$fs['count']."<br />";
echo "Count templates in cache: ".$grid['count']."<br /><br />";

array_walk($fs['names'], 'Rain\fixPath');
array_walk($grid['names'], 'Rain\fixPath');

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
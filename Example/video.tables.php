<?php

require_once("config.php");

$cat = (isset($_REQUEST["cat"])) ? $_REQUEST["cat"] : "Category_1";
$folder = "\\VIDEOS\\".$cat."\\";

foreach ((new DirectoryIterator(dirname(__FILE__).$folder)) as $fileInfo) {
	if($fileInfo->isDot()) continue;
	$f = $fileInfo->getFilename();
	$_VIDEOS[] = array(
		'<a href="play.php?video='.base64_encode($folder.$f).'" target="_blank">'.$f.'</a>'
	); 
}

echo json_encode(array(
						"draw" => 1,
						"recordsTotal" => 0,
						"recordsFiltered" => 0,
						"data" => $_VIDEOS
					));

 
?>
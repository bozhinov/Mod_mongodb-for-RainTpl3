<?php

require_once("config.php");
require_once("Rain/tpl.php");

$link = base64_decode($_REQUEST["video"]);

$tpl = new Rain\Tpl;
$tpl->configure(array('production' => PRODUCTION));
$tpl->assign("title", $link);
$tpl->draw("play");
		
unset($tpl);

?>
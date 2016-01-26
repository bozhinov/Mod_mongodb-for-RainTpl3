<?php

namespace Rain;

include("MongoDb.php");

list($html,$md5) = (new Db)->getTemplate("templates/view_all.html");

echo $md5;

?>
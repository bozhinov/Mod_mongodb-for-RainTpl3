<?php

namespace Rain;

include("MongoDb.php");

list($html,$md5) = (new Db)->getTemplate("view_all");

echo $md5;

?>
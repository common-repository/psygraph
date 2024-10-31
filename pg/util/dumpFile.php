<?php

// only allowed to reference http, since we will be dispatching all of our
// commands to the server via post.

require_once("./http.php");
require_once("./testParams.php");

$params = getParams($argv);

// make sure that worked.
$fn = test_output($params, $params['format']);

print(file_get_contents($fn));

?>
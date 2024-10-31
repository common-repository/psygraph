<?php

include_once("pg.php");

$FORM = getHttpParams();

if(!isset($FORM['username'])) {
    exit(1);
}
$uid = getIDFromUsername( $FORM["username"] );

$data['publicAccess'] = getUserValue($uid, "publicAccess");
$jdata = json_encode($data, true);
printResult($jdata);

?>

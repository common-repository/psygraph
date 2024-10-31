<?php

require_once("pg.php");
require_once("out.php");

$FORM = getHttpParams();

$username = $FORM['username'];
$reason = checkUserPermission($username, "read");
if($reason) {
    printResult( $reason );
}

if(!isset($FORM['cert'])) {
    $FORM = handlePublicLogin($FORM);
    if($FORM['uid'] < 0) {
        printLoginFail($FORM, "This user does not allow data to be shared publically");
        exit(1);
    }
}
else {
    $FORM = handleLogin($FORM);
    if($FORM['uid'] < 0) {
        printLoginFail($FORM);
        exit(2);
    }
}


$types = array("rss", "csv", "ics", "kml", "html", "json", "psygraph");
if( ! in_array($FORM['format'], $types) ) {
    print("INCORRECT FORMAT");
    exit(3);
}

$FORM["headers"] = 1;
$fp = fopen("php://output", "w");
out_write($FORM, $fp);

?>

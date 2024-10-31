<?php

include_once("in.php");
include_once("pg.php");

$FORM = getHttpParams();
$FORM = handleLogin($FORM);

if($FORM['uid'] < 0) {
    printLoginFail($FORM);
}

$username = $FORM['username'];
$reason = checkUserPermission($username, "write");
if($reason) {
    printResult( $reason );
}

//$rslt = print_r($_FILES);
//    [pgFile] => Array
//        (
//            [name] => test.csv
//            [type] => text/plain
//            [tmp_name] => /tmp/phpsZ25Sw
//            [error] => 0
//            [size] => 6817
//        )


// Open a file for writing
$filename = $_FILES['pgFile']['tmp_name'];
$fp = fopen($filename, "r");

if(!$fp) {
    printHTMLResult("Could not open file: " . $filename);
}

$rslt = in_read($FORM, $fp);

// Close the streams
fclose($fp);

$resp = "";
if( isset( $FORM['respond']) && $FORM['respond']) {
    $resp  = "<h2>File processed.</h2>\n";
    $resp .= "$rslt\n";
    $resp .= "<p><a href='javascript:history.back()'>Go back</a></p>\n";
    printHTMLResult($resp);
}

printResult($resp);


?>

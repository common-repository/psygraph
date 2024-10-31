<?php

include_once("in.php");
include_once("pg.php");

$FORM = getHttpParams();
$FORM = handleLogin($FORM);

if($FORM['uid'] < 0) {
    printLoginFail($FORM);
}

$username = $FORM['username'];
$cert = $FORM['cert'];
$reason = checkUserPermission($username, "write");
if($reason) {
    printResult( $reason );
}

$response = "";
//$publicAccess = getUserValue($FORM['uid'], "publicAccess");
//if(! $publicAccess) {
//    $response .= "Public access set to '".$paString."'\n";
//    $response .= "<p><a href='javascript:history.back()'>Go back</a></p>\n";
//}
//else {

    $pgjs   = $FORM['server'] . "/page.js";
    $output = $FORM['server'] . "/output.php";
    $canvas = makeID();
    $field  = $FORM['field'];
    $ND     = $FORM['ND'];
    $height = $FORM['height'];
    $width  = $FORM['width'];
    $data   = post_request($output, $FORM);
    if($data!="") {       
        $response .= "<script src=". $pgjs ."></script>";
        $response .= "    <div>";
        $response .= "    <div id=".$canvas."></div>";
        $response .= "    </div>";
        $response .= "    <script>";
        $response .= "      var data = '$data';";
        $response .= "      drawJSON(data, $ND, '$field', '$canvas', '$height', '$width');";
        $response .= "      initializeCanvas('$canvas', '$height', '$width')";
        $response .= "    </script>";
    }
//}

printJSResult($response);

//////////////////////////////////

function makeID() {
    return bin2hex(openssl_random_pseudo_bytes(4));
}

function post_request($url, $data, $optional_headers = null, $getresponse = true) {
    $data = http_build_query($data);
    $params  = array('http' => array(
                         'method' => 'POST',
                         'content' => $data
                         ));
    if ($optional_headers !== null) {
        $params['http']['header'] = $optional_headers;
    }
    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        return "";
    }
    if ($getresponse){
        $response = stream_get_contents($fp);
        return $response;
    }
    return "";
}

?>

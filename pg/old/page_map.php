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

$pgjs   = $FORM['server'] . "/page.js";
$canvas = makeID();
$field  = $FORM['field'];
$ND     = $FORM['ND'];
$height = $FORM['height'];
$width  = $FORM['width'];

$url = $FORM['server'] . "/output.php";
$url .= "?username=".$FORM['username'];
foreach ($FORM as $key => $value) {
    switch($key) {
    case 'height':
    case 'width':
    case 'cert':
    case 'max':
    case 'start':
    case 'end':
    case 'id':
    case 'page':
    case 'category':
    case 'type':
    case 'format':
        $url .= "&" .$key ."=" .urlencode($value);
        break;
    default:
    }
}

$center = getMapCenter($url);

$response .= "      <div><div id=".$canvas."></div></div>";
$response .= "      <script src='https://maps.googleapis.com/maps/api/js?v=3.exp'></script>";
$response .= "      <script src='". $pgjs ."'></script>";
$response .= "      <script>";
$response .= "      function initialize() {";
$response .= "          initializeCanvas('$canvas', '$height', '$width');";
$response .= "          var chicago = new google.maps.LatLng($center);";
$response .= "          var mapOptions = { ";
$response .= "            'zoom': 11, ";
$response .= "            'center': chicago ";
$response .= "          };";
$response .= "          var map = new google.maps.Map(document.getElementById('$canvas'), mapOptions);";
$response .= "          var ctaLayer = new google.maps.KmlLayer({url: '$url' });";
$response .= "          ctaLayer.setMap(map);";
$response .= "      }";
$response .= "      google.maps.event.addDomListener(window, 'load', initialize);";
$response .= "      </script>";


printJSResult($response);

//////////////////////////////////

function makeID() {
    return bin2hex(openssl_random_pseudo_bytes(4));
}

function getMapCenter($url) {
    // return the first point of a line.  Or perhaps Chicago
    $center = "41.875696, -87.624207";
    //$fp = fopen($url, 'r');
    //if($fp) {
    //    while($line = fgets($fp)){
    //        if(trim($line) == "<coordinates>") {
    //            $ll = explode(",", trim(fgets($fp)));
    //            $center = $ll[0] . ", " . $ll[1];
    //            break;
    //        }
    //    }
    //    fclose($fp);
    //}
    return $center;
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

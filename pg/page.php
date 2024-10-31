<?php

include_once("pg.php");
require_once("out.php");

$FORM = getHttpParams();

if(!isset($FORM['cert'])) {
    $FORM = handlePublicLogin($FORM);
    if($FORM['uid'] < 0) {
        printLoginFail($FORM, "This user (".$FORM['username'].") does not allow data to be shared publically");
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

$response = "";
$displayType = $FORM['display'];

if(!strcmp("graph", $displayType) || !strcmp("bar", $displayType)) {
    $pgjs   = $FORM['server'] . "/page.js";
    $output = $FORM['server'] . "/output.php";
    $canvas = makeID();

    // get the data in a JSON string for JS embedding
    $FORM['format'] = "json";

    // we used to do this when we were on a separate server.
    //$data = post_request($output, $FORM);
    // we can no call to the method directly
    $fp = fopen("php://temp", "rw");
    out_write($FORM, $fp);
    rewind($fp);
    $data = stream_get_contents($fp);

    $display = $FORM['display'];
    $height  = $FORM['height'];
    $width   = $FORM['width'];

    if($data!="") {       
        $response .= "<script src=". $pgjs ."></script>";
        $response .= "    <div>";
        $response .= "    <div id=".$canvas."></div>";
        $response .= "    </div>";
        $response .= "    <script>";
        $response .= "      var data = '".$data."';";
        $response .= "      drawJSON(data, '$display', '$canvas', '$height', '$width');";
        $response .= "      initializeCanvas('$canvas', '$height', '$width')";
        $response .= "    </script>";
    }
    else {
        $response .= "No response to post: " . $output;
    }
    printJSResult($response);
}
else if(!strcmp("map", $displayType)) {
    $pgjs   = $FORM['server'] . "/page.js";
    $canvas = makeID();
    $FORM['format'] = "kml";
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
}
else if(!strcmp("list", $displayType)) {
    $FORM['format']   = "html";
    $FORM['embedded'] = false;
    $FORM["headers"]  = 1;
    $fp = fopen("php://output", "w");
    out_write($FORM, $fp);
}
else {
    $response = "<p>You must supply a valid display type</p>";
    printJSResult($response);
}

exit(0);

//////////////////////////////////

function makeID() {
    return bin2hex(openssl_random_pseudo_bytes(4));
}

function post_request($url, $data, $optional_headers = null, $getresponse = true) {
    $data = http_build_query($data);
    // this should be HTTPS, maybe
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
        DBG("Could not call FOPEN on $url to create POST: " . print_r($ctx, true) );
        return "";
    }
    if ($getresponse){
        $response = stream_get_contents($fp);
        return $response;
    }
    return "";
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

?>

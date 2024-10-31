<?php

include_once("pg.php");
include_once("out.php");

$FORM = getHttpParams();

if(!isset($FORM['cert'])) {
    $FORM = handlePublicLogin($FORM);
    if($FORM['uid'] < 0) {
    //    printLoginFail($FORM, "This user does not allow data to be shared publically");
    //    exit(1);
    }
}
else {
    $FORM = handleLogin($FORM);
    if($FORM['uid'] < 0) {
    //    printLoginFail($FORM);
    //    exit(2);
    }
}

$resp = "";
if(isset( $FORM['respond']) && $FORM['respond']) {
    //$publicAccess = strcmp($FORM['publicAccess'], "on") ? 0 : 1;
    //setUserValue($FORM['uid'], "publicAccess", $publicAccess);
    //$response  = "<h2>Changes processed.</h2>\n";
    //$publicAccess = getUserValue($FORM['uid'], "publicAccess");
    //$paString = $publicAccess ? "on" : "off";
    //$response .= "Public access set to '".$paString."'\n";
    //$response .= "<hr/>";
    
    $response .= "<p>Link to dynamically-generated data:</p>";
    $url = "";
    if($FORM['linkType'] == "file") { 
        $url  = $FORM['server'] . "/output.php";
        // add the format, which should be: html, ics, rss, csv, kml
        $url .= "?format="      . $FORM['format'];
    }
    else if($FORM['linkType'] == "display") {
        $url  = $FORM['server'] . "/page.php";
        // add the display, which should be: list, bar, map, graph
        $url .= "?display="     . $FORM['display'];
        $url .= "&signal="      . $FORM['signal'];
        $url .= "&interval="    . $FORM['interval'];
    }
    if($url != "") { // common fields
        $url .= "&server="      . urlencode($FORM['server']);
        $url .= "&username="    . $FORM['username'];
        $url .= "&max="         . $FORM['max'];
        $url .= "&start="       . $FORM['start'];
        $url .= "&end="         . $FORM['end'];
        $url .= "&id="          . $FORM['id'];
        $url .= "&category="    . $FORM['category'];
        $url .= "&page="        . $FORM['page'];
        $url .= "&type="        . $FORM['type'];
        $url .= "&height="      . $FORM['height'];
        $url .= "&width="       . $FORM['width'];
    }

    $response .= "<p><a href='".$url."'>".$url."</a></p>";
    $response .= "<hr/>";
    $response .= "<p><a href='javascript:history.back()'>Go back</a></p>\n";
    printHTMLResult($response);
}
else {
    $url = $FORM['server'] ."/generateLink.php";
    $response  = '<div id="pgUpload">';
    $response .= '<form action="'.$url.'" method="post" enctype="multipart/form-data">';

    // Progress link fields
    $response .= '<p><em>Filter the set of events that determine the data.</em></p>';
    // max, start, end, id, page, category, type
    //$response .= '<label for="format">Visualization Format:</label>';
    //$response .= '<select name="format" id="format">';
    //$response .= '  <option value="html">html</option>'; 
    //$response .= '</select><br/>';
    $response .= '<input type="hidden" name="format"   value="html"   id="format"/>';
    $response .= '<label for="max">Maximum number of events:</label>';
    $response .= '<input name="max" id="max"  type="number" min="0" max="1000"/><br/>';
    $response .= '<label for="start">Starting date:</label>';
    $response .= '<input name="start" id="start"  type="date"/><br/>';
    $response .= '<label for="end">Ending date:</label>';
    $response .= '<input name="end" id="end"  type="date"/><br/>';
    $response .= '<label for="id">Event ID number (for a specific event):</label>';
    $response .= '<input name="id" id="id"  type="number" min="0" /><br/>';
    $response .= '<label for="category">Category to display:</label>';
    $response .= '<input name="category" id="category" type="text"/><br/>';
    $response .= '<label for="page">Page to display:</label>';
    $response .= '<select name="page" id="page">';
    $response .= '  <option value="">all</option>'; 
    $response .= '  <option value="stopwatch">stopwatch</option>'; 
    $response .= '  <option value="timer">timer</option>'; 
    $response .= '  <option value="counter">counter</option>'; 
    $response .= '  <option value="note">note</option>'; 
    $response .= '</select><br/>';
    $response .= '<label for="type">Event type:</label>';
    $response .= '<input name="type" id="type" type="text"/><br/>';
    $response .= "<hr/>";

    $response .= '<p><em>Specify the properties of the output.</em></p>';
    $response .= '<label for="linkType">Link type (file or graphical display):</label>';
    $response .= '<select name="linkType" id="linkType">';
    $response .= '  <option value="file">file</option>'; 
    $response .= '  <option value="display">display</option>'; 
    $response .= '</select><br/>';
    $response .= "<hr/>";

    $response .= '<p><em>For file links, specify the file type:</em></p>';
    $response .= '<label for="format">File format:</label>';
    $response .= '<select name="format" id="format">';
    $response .= '  <option value="html">html</option>'; 
    $response .= '  <option value="ics">ics</option>'; 
    $response .= '  <option value="rss">rss</option>'; 
    $response .= '  <option value="kml">kml</option>'; 
    $response .= '  <option value="csv">csv</option>'; 
    $response .= '</select><br/>';
    $response .= "<hr/>";

    $response .= '<p><em>For display links, specify the type of display and the signal to be displayed:</em></p>';
    $response .= '<label for="display">Display type:</label>';
    $response .= '<select name="display" id="display">';
    $response .= '  <option value="list">list</option>'; 
    $response .= '  <option value="bar">bar</option>'; 
    $response .= '  <option value="map">map</option>'; 
    $response .= '  <option value="graph">graph</option>'; 
    $response .= '</select><br/>';
    $response .= '<label for="height">Height:</label>';
    $response .= '<input name="height" id="height" type="text" value="640px"/><br/>';
    $response .= '<label for="width">Width:</label>';
    $response .= '<input name="width" id="width" type="text" value="640px"/><br/>';
    $response .= '<label for="signal">Signal to analyse:</label>';
    $response .= '<select name="signal" id="signal">';
    $response .= '  <option value="events">events</option>'; 
    $response .= '  <option value="accelerationNorm">norm of acceleration</option>'; 
    $response .= '  <option value="acceleration">acceleration</option>'; 
    $response .= '  <option value="count">count</option>'; 
    $response .= '  <option value="correctCount">correct/incorrect count</option>'; 
    $response .= '  <option value="totalTime">total time</option>'; 
    $response .= '  <option value="eventCount">number of events</option>'; 
    $response .= '</select><br/>';
    $response .= '<label for="interval">Data aggregation interval:</label>';
    $response .= '<select name="interval" id="interval">';
    $response .= '  <option value="none">none</option>'; 
    $response .= '  <option value="day">day</option>'; 
    $response .= '  <option value="week">week</option>'; 
    $response .= '  <option value="month">month</option>'; 
    $response .= '</select><br/>';
    $response .= "<hr/>";

    $response .= '<input type="hidden" name="respond"  value="true"  id="respond"/>';
    $response .= '<input type="hidden" name="server"   value="'. $FORM['server']. '"   id="server"/>';
    $response .= '<input type="hidden" name="username" value="'. $FORM['username']. '" id="username"/>';
    $response .= '<input type="hidden" name="cert"     value="'. $FORM['cert']. '"     id="cert"/>';
    $response .= '<input type="submit" name="submit" value="Submit"/>';
    $response .= '<br/>';
    $response .= '</form>';
    $response .= '</div>';
    printHTMLResult($response);
}

?>
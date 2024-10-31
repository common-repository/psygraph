<?php

require_once("util.php");
require_once('pg.php');

function out_write($FORM, $file) {
    $FORM['file'] = $file;
    if($FORM["format"] == "rss") {
        out_writeRSS($FORM, $file);
    }
    else if($FORM["format"] == "csv") {
        out_writeCSV($FORM, $file);
    }
    else if($FORM["format"] == "ics") {
        out_writeICS($FORM, $file);
    }
    else if($FORM["format"] == "html") {
        out_writeHTML($FORM, $file);
    }
    else if($FORM["format"] == "kml") {
        out_writeKML($FORM, $file);
    }
    else if($FORM["format"] == "json") {
        out_writeJSON($FORM, $file);
    }
    else if($FORM["format"] == "psygraph") {
        out_writePsygraph($FORM, $file);
    }
    else {
        fwrite($file,  "ERROR: unknown format \n");
    }
}


function printHeader($FORM) {
    if($FORM["headers"]) {
        $mime = "";
        if(isset($FORM['embedded']) && $FORM['embedded']) {
            $mime = "text/plain";
        }
        else if($FORM["fileExt"] == "csv") {
            $mime = "text/csv";
        }
        else if($FORM["fileExt"] == "kml") {
            $mime = "application/vnd.google-earth.kml+xml";
        }        
        else if($FORM["fileExt"] == "html") {
            $mime = "text/html";
        }
        else if($FORM["fileExt"] == "ics") {
            $mime = "text/calendar";
        }
        else if($FORM["fileExt"] == "rss") {
            $mime = "application/rss+xml";
        }
        else if($FORM["fileExt"] == "json") {
            $mime = "application/json";
        }
        else if($FORM["fileExt"] == "pg") {
            $mime = "application/psygraph";
        }
        else {
            //fwrite($file, "UNRECOGNIZED MIME TYPE");
            exit(3);
        }
        header("Content-type: $mime");
        $date = strftime("%Y-%m-%d", time()+getLocalTZOffset());
        $username = "";
        if(isset($FORM["username"]))
            $username = $FORM["username"] . "_";
        $filename = "psygraph_" . $username . $date . "." . $FORM["fileExt"];
        // consider "attachment" instead of "inline"
        header("Content-Disposition: inline;filename=$filename");
    }
}

function getSelectedEvents($FORM, $file) {
    $uid  = $FORM["uid"];
    $opts = array();

    if(isset($FORM["start"]) && $FORM["start"] != "") {
        $opts['start'] = unformatTime($FORM['start']);
    }
    if(isset($FORM["end"]) && $FORM["end"] != "") {
        $opts['end'] = unformatTime($FORM['end']);
    }
    if(isset($FORM["id"]) && $FORM["id"] != "") {
        $opts['eid'] = $FORM["id"];
    }
    if(isset($FORM["category"]) && $FORM["category"] != "") {
        $opts['cid'] = getCategoryIDFromName($uid, $FORM["category"], true);
    }
    if(isset($FORM["page"]) && $FORM["page"] != "") {
        $opts['pid'] = getPageIDFromName($uid, $FORM["page"], true);
    }
    if(isset($FORM["type"]) && $FORM["type"] != "") {
        $opts['type'] = $FORM["type"];
    }
    
    // handle "max"
    if(isset($FORM["max"]) && $FORM["max"] != "") {
        $opts['max'] = $FORM["max"];
    }

    //fwrite($file, $query);

    $e = queryEventsForUser($uid, $opts);
    return $e;
}

//##############################################
// Write CSV
//##############################################
function out_writeCSV($FORM, $file) {
    $FORM["fileExt"] = "csv";
    printHeader($FORM);

    $e = getSelectedEvents($FORM, $file);
    $maxE = count($e);
    
    // the number of events to emit that carry the specified signal
    if(!isset($FORM['maxSignals']))
        $FORM['maxSignals']="1";
    $maxSignals = intval($FORM['maxSignals']);

    if(isset($FORM['signal'])) {
        $signal = $FORM['signal'];
        if($signal == "acceleration") {
            fputcsv($file, array("time","x","y","z"));
        }
        else if($signal == "analog1" || $signal == "analog2") {
            fputcsv($file, array("time","x"));
        }
        else if($signal == "temperature") {
            fputcsv($file, array("time","degrees"));
        }
        else if($signal == "heartRate") {
            fputcsv($file, array("time","rate"));
        }
        else if($signal == "orientation") {
            fputcsv($file, array("time","degrees"));
        }
        else if($signal == "location") {
            fputcsv($file, array("time","latitude","longitude","elevation"));
        }
        else {
            fwrite($file, "Error: unknown form signal" .$signal );
            return;
        }
        for($i=0; $i<$maxE; $i++) {
            $json = $e[$i][E_DATA];
            $event = json_decode($json, true);
            //var_dump($data);
            if(isset($event[$signal])) {
                $data = $event[$signal];
                for($j=0; $j<count($data); $j++) {
                    $data[$j][0] = sprintf('%d', $data[$j][0]); // make sure the time string is not scientific format
                    fputcsv($file, $data[$j]);
                }
                if(! --$maxSignals)
                    break;
            }
        }
    }
    else {
        fputcsv($file,  array("id","start","duration","category","page","type","data"));
        for($i=0; $i<$maxE; $i++) {
            $event    = parseEvent($e[$i]);
            $start    = formatTime($event["start"]);
            $duration = $event["duration"];
            $arr = array(
                $event["eid"]      ,
                $start             ,
                $duration          ,
                $event["category"] ,
                $event["page"]     ,
                $event["type"]     ,
                json_encode($event["data"], true));
            fputcsv($file, $arr);
        }
    }
}

// ###############################################
// # Write JSON
// ###############################################
function out_writeJSON($FORM, $file) {
    $FORM["fileExt"] = "json";
    printHeader($FORM);

    $e = getSelectedEvents($FORM, $file);
    $maxE = count($e);
    $output = array();

    if(!isset($FORM['signal']))
        $FORM['signal']="events";
    $signal = $FORM['signal'];

    if(!isset($FORM['interval']))
        $FORM['interval']="none";
    $interval = $FORM['interval'];

    if(!isset($FORM['maxSignals']))
        $FORM['maxSignals']="1";
    $maxSignals = intval($FORM['maxSignals']);

    if($signal=="events") {
        $output[] = ["id","start","duration","category","page","type","data"];
        for($i=0; $i<$maxE; $i++) {
            $event    = parseEvent($e[$i]);        
            $output[] = array($event["eid"],
            $event["start"],
            $event["duration"],
            $event["category"],
            $event["page"],
            $event["type"],
            $event["data"]);
        }
    }
    else if( ($signal == "acceleration") ||
             ($signal == "heartRate")    ||
             ($signal == "analog1")      ||
             ($signal == "analog2")      ||
             ($signal == "temperature")  ||
             ($signal == "rotation")     ||
             ($signal == "orientation")  ||
             ($signal == "location") ) {
        $field = $signal;
        if($signal == "acceleration") {
            $output[] = array("time","x","y","z");
        }
        else if($signal == "analog1" || $signal == "analog2") {
            $output[] = array("time","value");
        }
        else if($signal == "heartRate") {
            $output[] = array("time","rate");
        }
        else if($signal == "temperature") {
            $output[] = array("time","degrees");
        }
        else if($signal == "orientation") {
            $output[] = array("time","orientation");
        }
        else if($signal == "location") {
            $output[] = array("time","latitude","longitude","elevation");
        }
        for($i=0; $i<$maxE; $i++) {
            $json = $e[$i][E_DATA];
            $eventData = json_decode($json, true);
            if(isset($eventData[$field])) {
                $data = $eventData[$field];
                for($j=0; $j<count($data); $j++) {
                    if($signal == "orientation") {
                        $output[] = array($data[$j][0], $data[$j][1]);
                    }
                    else {
                        $output[] = array($data[$j][0], $data[$j][1], $data[$j][2], $data[$j][3]);
                    }
                }
                if(! --$maxSignals)
                    break;
            }
        }
    }    
    else if($signal == "distance") {
        $output[] = array("time","distance");
    }
    else if(($signal=="count") || ($signal=="correctCount")) {
        if($signal == "correctCount") {
            $output[] = array("time","correctCount");
            for($i=0; $i<$maxE; $i++) {
                $type = $e[$i][E_TYPE];
                if($type == "reset") {
                    $time = $e[$i][E_START];
                    $json = $e[$i][E_DATA];
                    $eventData = json_decode($json, true);
                    if( isset($eventData['count']) && isset($eventData['target']) ) {
                        if( $eventData['count'] == $eventData['target'] )
                            $output[] = array($time,1);
                        else
                            $output[] = array($time,0);
                    }
                }
            }
        }
        else {
            $output[] = array("time","count");
            for($i=0; $i<$maxE; $i++) {
                //$type = $e[$i][E_TYPE];
                //if($type == "count") {
                $time = $e[$i][E_START];
                $output[] = array($time, 1);
                //}
            }
        }
    }
    else if($signal == "eventCount") {
        $output[] = array("time","eventCount");
        for($i=0; $i<$maxE; $i++) {
            $time = $e[$i][E_START];
            $data = 1;
            $output[] = array($time, $data);
        }
    }
    else if($signal == "accelerationNorm") {
        $output[] = array("time","accelerationNorm");
        for($i=0; $i<$maxE; $i++) {
            $json = $e[$i][E_DATA];
            $eventData = json_decode($json, true);
            if(isset($eventData["acceleration"])) {
                $data = $eventData["acceleration"];
                for($j=0; $j<count($data); $j++) {
                    $norm  = $data[$j][1] * $data[$j][1];
                    $norm += $data[$j][2] * $data[$j][2];
                    $norm += $data[$j][3] * $data[$j][3];
                    $norm = sqrt($norm);
                    $output[] = array($data[$j][0], $norm);
                }
                if(! --$maxSignals)
                    break;
            }
        }
    }
    else if($signal == "heartRate" ||
            $signal == "analog1"   ||
            $signal == "analog2"   ||
            $signal == "temperature")
    {
        $output[] = array("time",$signal);
        for($i=0; $i<$maxE; $i++) {
            $json = $e[$i][E_DATA];
            $eventData = json_decode($json, true);
            if(isset($eventData[$signal])) {
                $data = $eventData[$signal];
                for($j=0; $j<count($data); $j++) {
                    $output[] = $data[$j];
                }
                if(! --$maxSignals)
                    break;
            }
        }
    }
    else {
        fwrite($file, "Error: unknown form signal" .$signal );
        return;
    }

    // accumulate over the interval, if required
    if($interval == "none") {
        $intOutput = $output;
    }
    else {
        $intOutput = array();
        $intOutput[0] = $output[0];
        $int = 0;
        if($interval == "day")
            $int = 24*60*60;
        else if($interval == "week")
            $int = 7*24*60*60;
        else if($interval == "month")
            $int = 30*24*60*60;

        if(count($output) > 1) {                                
            $interval = array();
            $time = $output[count($output)-1][0];
            $interval[] = $output[1];
            for($i=count($output)-1; $i>0; ) {
                $eventTime = $output[$i][0];
                if( ($time + $int) > $eventTime) {
                    $interval[] = $output[$i];
                    $i--;
                }
                else {
                    $intLen = count($interval);
                    if($intLen) {
                        // do summation or ratio
                        for($j=1; $j<$intLen; $j++) {
                            // take the latest time
                            $interval[0][0] = $interval[$j][0];
                            // sum over the array
                            for($k=1; $k < count($interval[0]); $k++) {
                                $interval[0][$k] += $interval[$j][$k];
                            }
                        }
                        // take the ratio of the summation
                        if($signal=="correctCount") {
                            for($k=1; $k < count($interval[0]); $k++) {
                                $interval[0][$k] = $interval[0][$k] *1.0 / $intLen;
                            }
                        }
                        $intOutput[] = $interval[0];
                    }
                    $time += $int;
                    $interval = array();
                }
            }
        }
    }

    $data = json_encode($intOutput, true);
    fwrite($file,  $data);
}

//###############################################
// Write Psygraph
//###############################################
function out_writePsygraph($FORM, $file) {
    $FORM["fileExt"] = "pg";
    printHeader($FORM);

    $uid = $FORM['uid'];

    $pg['user']       = getUser($uid);
    $pg['pages']      = getPages($uid, true);
    $pg['categories'] = getCategories($uid, true);
    $pg['events']     = getSelectedEvents($FORM, $file);
    
    $data = json_encode($pg, true);
    fwrite($file, $data);
}

//###############################################
//# Write HTML
//###############################################
function out_writeHTML($FORM, $file) {
    $FORM["fileExt"] = "html";
    printHeader($FORM);

    if(!isset($FORM['embedded']) || $FORM['embedded']==false) {
        fwrite($file, "<html><head></head><body>\n");
    }

    $print = <<<ENDOFHERE
  <table id='eventlist' class=data>
    <thead>
      <tr>
        <th>id</th>
        <th>category</th>
        <th>page</th>
        <th>start</th>
        <th>duration</th>
        <th>type</th>
        <th>data</th>
      </tr>
    </thead>
  <tbody>
ENDOFHERE;
fwrite($file, $print);

    $e = getSelectedEvents($FORM, $file);
    $maxE = count($e);

    $tzsec = getTZOffsetFromParams($FORM);
    
    for($i=0; $i<$maxE; $i++) {
        $event      = parseEvent($e[$i]);
        $start      = formatTime($event["start"], $tzsec);
        $duration   = formatDuration($event["duration"]);
        $dataString = getHTMLforData($FORM, $event["eid"], $event["page"], $event["type"], $event["data"]);
        fwrite($file,  "      <tr>\n");
        fwrite($file,  "        <td>" . $event["eid"] . "</td>\n");
        fwrite($file,  "        <td>" . $event["category"] . "</td>\n");
        fwrite($file,  "        <td>" . $event["page"] . "</td>\n");
        fwrite($file,  "        <td>" . $start . "</td>\n");
        fwrite($file,  "        <td>" . $duration . "</td>\n");
        fwrite($file,  "        <td>" . $event["type"] . "</td>\n");
        fwrite($file,  "        <td>" . $dataString . "</td>\n");
        fwrite($file,  "      </tr>\n");
    }

    fwrite($file, "</tbody></table>\n");
    
    if(!isset($FORM['embedded']) || $FORM['embedded']==false) {
        fwrite($file, "</body></html>\n");
    }
}

function getHTMLforData($FORM, $eid, $page, $type, $data) {
    $txt = "";
    $targetLink = " <a target=\"_blank\" ";
    $link = " <a ";
    if(isset($FORM["server"])) {
        $fileURL  = $FORM["server"] ."/output.php?username=". $FORM["username"];
        if(isset($FORM['exposeCert']) && $FORM['exposeCert'])
            $fileURL .= "&cert=". $FORM["cert"];
        $mediaURL = $FORM["server"] ."/mediaServer.php?username=". $FORM["username"];
        if(isset($FORM['exposeCert']) && $FORM['exposeCert'])
            $fileURL .= "&cert=". $FORM["cert"];
        if($data == "") {
        }
        else if($page=="stopwatch") {
            //$txt .= json_encode($data, true);
            if(isset($data['location'])) {
                $time = $data['location'][0][0];
                $lat  = $data['location'][0][1];
                $lng  = $data['location'][0][2];
                $alt  = $data['location'][0][3];
                $txt .= $targetLink . 'href="http://maps.google.com/maps?q='. $lat .','. $lng .'">map</a>';
                if(count($data['location'])>1) {
                    if(isset($data['distance']) && $data['distance'])
                        $txt .= " ". round($data['distance'],2) . " miles <br/>";
                    //$txt .= $link. $fileURL ."&format=kml&id=". $eid ."\">map</a>";
                    //$txt .= $link. $fileURL ."&format=csv&id=". $eid ."&signal=location\">location</a>";
                    $txt .= $targetLink. 'href="' . $fileURL ."&format=kml&id=". $eid ."\">kml</a>";
                    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=location\">csv</a>";
                    //$txt .= $targetLink . 'href="http://maps.google.com/maps?q='. $lat .','. $lng .'">map</a>';
                }
            }
            if(isset($data['acceleration'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=acceleration\">acceleration</a>";
            }
            if(isset($data['heartRate'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=heartRate\">Heart Rate</a>";
            }
            if(isset($data['analog1'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=analog1\">analog1</a>";
            }
            if(isset($data['analog2'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=analog2\">analog2</a>";
            }
            if(isset($data['temperature'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=temperature\">temperature</a>";
            }
            if(isset($data['rotation'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=rotation\">rotation</a>";
            }
            if(isset($data['orientation'])) {
    		    $txt .= $targetLink. 'href="' . $fileURL ."&format=csv&id=". $eid ."&signal=orientation\">orientation</a>";
            }
        }
        else if($page=="note") {
    	    $txt .=  $data['title'] . "<br/>";
            if(isset($data['text'])) {
                $t = html_encode($data['text']);
                $txt .= $link .' href="" onclick="alert(\''. $t .'\');">text</a>';
            }
            if(isset($data['location'])) {
                $time = $data['location'][0][0];
                $lat  = $data['location'][0][1];
                $lng  = $data['location'][0][2];
                $alt  = $data['location'][0][3];
                $txt .= $targetLink . 'href="http://maps.google.com/maps?q='. $lat .','. $lng .'">location</a>';
            }
            if(isset($data['audio'])) {
                $thisURL = $mediaURL . "&action=downloadFile&id=" . $eid;
                $txt .= $targetLink. 'href="' . $thisURL ."\">audio</a>";
            }
        }
        else if($page == "timer") {
            // nothing to display
        }
        else if($page == "counter") {
    	    $txt .= $data['count'];
        }
        else if($page == "home") {
            if(isset($data['text'])) {
                $text = str_replace('"',"'",$data['text'] );
                $text = str_replace("'","",$text );
                $txt .= $link.'href="#" onclick="alert(\''. $text .'\');">text</a>';
            }
        }
        else {
            $txt .= json_encode($data, true);
        }
    }
    return $txt;
}

// ###############################################
// # Write KML
// ###############################################

function out_writeKML($FORM, $file) {
    $FORM["fileExt"] = "kml";
    printHeader($FORM);

    $username = $FORM["username"];

    $s  =       '<?xml version="1.0" encoding="UTF-8"?>';
    $s .= "\n". '<kml xmlns="http://www.opengis.net/kml/2.2">';
    $s .= "\n". ' <Document>';
    $s .= "\n". '  <name>Tracks</name>';
    $s .= "\n". '  <description>Tracks recorded by ' .$username .'.</description>';
    $s .= "\n". '  <Style id="black">';
    $s .= "\n". '    <LineStyle>';
    $s .= "\n". '       <color>ff000000</color>';
    $s .= "\n". '       <width>4</width>';
    $s .= "\n". '    </LineStyle>';
    $s .= "\n". '  </Style>';
    $s .= "\n". '  <Style id="red">';
    $s .= "\n". '    <LineStyle>';
    $s .= "\n". '       <color>ffff0000</color>';
    $s .= "\n". '       <width>4</width>';
    $s .= "\n". '    </LineStyle>';
    $s .= "\n". '  </Style>';
    $s .= "\n". '  <Style id="green">';
    $s .= "\n". '    <LineStyle>';
    $s .= "\n". '       <color>ff00ff00</color>';
    $s .= "\n". '       <width>4</width>';
    $s .= "\n". '    </LineStyle>';
    $s .= "\n". '  </Style>';
    $s .= "\n". '  <Style id="blue">';
    $s .= "\n". '    <LineStyle>';
    $s .= "\n". '       <color>ff0000ff</color>';
    $s .= "\n". '       <width>4</width>';
    $s .= "\n". '    </LineStyle>';
    $s .= "\n". '  </Style>';
    fwrite($file, $s . "\n");

    $e = getSelectedEvents($FORM, $file);

    $placeID = 0;
    $colors = array("black", "red", "green", "blue");
    $color = 3;
    for($i=count($e)-1; $i>=0 ; $i--) {
        $event    = parseEvent($e[$i]);
        $start    = formatZTime($event['start']);
        $end      = formatZTime($event['start']+$event['duration']);
        $data     = $event['data'];
        $category = $event['category'];
        switch($event['type']) {
        case "interval": {
            if(! (isset($event['data']['location']) || isset($event['data']['position'])) )
                continue;
            if( isset($event['data']['location']) )
                $loc = $event['data']['location'];
            else
                $loc = $event['data']['position'];
            $numPoints = count($loc);
            $totalAltitude = 0;
            for($j=0; $j<$numPoints; $j++)
                $totalAltitude += abs($loc[$j][3]);
            $color = ($color+1) % 4; 
            $placeID ++;
            $path  = "    <Placemark id=\"$placeID\">\n";
            $path .= "      <name>Tracks: $category</name>\n";
            $path .= "      <description>This track was generated with Psygraph.</description>\n";
            $path .="       <TimeSpan><begin>".$start."</begin><end>".$end."</end></TimeSpan>\n";
            $path .= "      <styleUrl>#" . $colors[$color] . "</styleUrl>\n";
            $path .= "      <LineString>\n";
            if($totalAltitude)
                $path .= "        <altitudeMode>absolute</altitudeMode>\n";
            else
                $path .= "        <altitudeMode>relativeToGround</altitudeMode>\n";
            $path .= "        <coordinates>\n";
            if($numPoints) {
                for($j=0; $j<$numPoints; $j++) {
                    // longitude, latitude for KML
                    $path .= $loc[$j][2]. ",";
                    $path .= $loc[$j][1]. ",";
                    $path .= $loc[$j][3]. "\n";
                }
            }
            $path .="        </coordinates>\n";
            $path .="      </LineString>\n";
            $path .="    </Placemark>\n";
            fwrite($file, $path);
            break;
        }
        case "marker":
        case "text": {
            if(! (isset($event['data']['location']) || isset($event['data']['position'])) )
                continue;
            if( isset($event['data']['location']) )
                $loc = $event['data']['location'];
            else
                $loc = $event['data']['position'];
            $title = $event['data']['title'];
            $text = $event['data']['text'];
            $placeID ++;
            $path  = "    <Placemark id=\"$placeID\">\n";
            $path .="      <name>".$title."</name>\n";
            $path .="      <description>".$text."</description>\n";
            $path .="      <TimeStamp><when>".$start."</when></TimeStamp>\n";
            $path .="      <Point><coordinates>\n";
            $path .= $loc[0][2]. ",";
            $path .= $loc[0][1]. ",";
            $path .= $loc[0][3]. "\n";
            $path .="      </coordinates></Point>\n";
            $path .="    </Placemark>\n";
            fwrite($file, $path);
            break;
        }
        default: {
            break;
        }
        }
    }
    fwrite($file, "  </Document>\n</kml>");
}


// ###############################################
// # Write RSS
// ###############################################

function out_writeRSS($FORM, $file) {
    $FORM["fileExt"] = "rss";
    printHeader($FORM);

    fwrite($file,  "<?xml version=\"1.0\"?>\n");
    fwrite($file,  "<rss>\n");
    writeRSSChannel($FORM, $file);
    fwrite($file,  "</rss>\n");
}

// Write a channel.  For us, this corresponds to a user's data.
function writeRSSChannel($FORM, $file) {

    //<image> 
    //<title>RSS2.0 Example</title> 
    //<url>http://www.exampleurl.com/example/images/logo.gif</url> 
    //<link>http://www.exampleurl.com/example/index.html</link>
    //<width>88</width> 
    //<height>31</height> 
    //<description>The World's Leading Technical Publisher</description> 
    //</image>
    //
    //<textInput> 
    //<title>Search</title> 
    //<description>Search the Archives</description> 
    //<name>query</name> 
    //<link>http://www.exampleurl.com/example/search.cgi</link> 
    //</textInput>


    $username = $FORM["username"];
    $server   = $FORM["server"];
    $uid      = $FORM["uid"];
    $date = strftime("%Y-%m-%d", time()+GetTZOffset()*60);

    fwrite($file,  "  <channel>\n");
    fwrite($file,  "    <title>Track for $username</title>\n");
    fwrite($file,  "    <managingEditor>$username</managingEditor>\n");
    //fwrite($file,  "    <copyright>Copyright 2010 $username</copyright>\n");
    fwrite($file,  "    <ttl>0</ttl>\n");
    fwrite($file,  "    <lastBuildDate>$date</lastBuildDate>\n");
    //fwrite($file,  "    <link>$url/client/index.cgi?user=$username</link>\n");
    fwrite($file,  "    <description>The RSS Track feed of $username.</description>\n");

    //$FORM["max"] = 10;
    
    $e = getSelectedEvents($FORM, $file);
    $maxE = count($e);

    for($i=0; $i < $maxE; $i++) {
        $item = parseEvent($e[$i]);
        writeRSSItem($item, $file, $server, $username);
    }
    fwrite($file,  "  </channel>\n");
}

// Write a single item
function writeRSSItem($item, $file, $server, $username) {
    $category    = $item['category'];
    $data        = $item['data'];
    $page        = $item['page'];

    if($page=="note") {
        $mediaURL = $server ."/mediaServer.php?username=". $username;

        $title       = getEventTitle($item);
        $description = getEventDescription($item);
        $location    = getEventLocation($item);
        $media       = getEventMedia($item, $mediaURL);
        $url         = "https://psygraph.com";

        fwrite($file,  "    <item>\n");
        fwrite($file,  "      <title>" . $title . "</title>\n");
        fwrite($file,  "      <link>$url/client/rss.cgi?user=$username&guid=" . $item["eid"] . "</link>\n");
        fwrite($file,  "      <guid>" . $item["eid"] . "</guid>\n");
        fwrite($file,  "      <pubDate>" . $item["start"] . "</pubDate>\n");
        fwrite($file,  "      <category domain='psygraph'>" . $category . "</category>\n");
        
        if($description != "") {
            fwrite($file, "      <description>$description</description>\n");
        }
        if($media != "") {
            $length = "12345";
            fwrite($file, "      <enclosure url='".$media."' length='".$length."' type='audio/mpeg'/>");
        }
        fwrite($file,  "    </item>\n");
    }
}

// ###############################################
// # Write ICS
// ###############################################
function out_writeICS($FORM, $file) {
    $FORM["fileExt"] = "ics";
    printHeader($FORM);

    $uid       = $FORM["uid"];

    fwrite($file,  "BEGIN:VCALENDAR\r\n");
    fwrite($file,  "VERSION:2.0\r\n");
    fwrite($file,  "PRODID:-//Psygraph.com//v1.0//EN\r\n");
    fwrite($file,  "CALSCALE:GREGORIAN\r\n");
    fwrite($file,  "METHOD:PUBLISH\r\n");

    $e = getSelectedEvents($FORM, $file);
    $maxE = count($e);

    for($i=0; $i<$maxE; $i++) {
        $item = parseEvent($e[$i]);
        if($item['page'] == "stopwatch" ||
           $item['page'] == "timer"     ||
           $item['page'] == "counter"   ||
           $item['page'] == "note"      ) {
                writeICSEvent($FORM, $item, $file);
        }
        else {
        }
    }
    fwrite($file,  "END:VCALENDAR\r\n");
}

// Write a single event
function writeICSEvent( $FORM, $event, $file) {

    $username    = $FORM['username'];
    $guid        = "pg" . $event['eid'];
    $summary     = getEventTitle($event);
    $description = getEventDescription($event);
    $location    = getEventLocation($event);
    $dateNow     = createICSDatestamp(time()*1000);
    $dateStart   = createICSDatestamp($event['start']);
    $dateEnd     = createICSDatestamp($event['start'] + $event['duration']);
    $category  = "";
    if(isset($FORM["category"]))
        $category = $FORM["category"];

    $end = "";
    if ($event['page'] == "note") {
        fwrite($file,  "BEGIN:VJOURNAL\r\n");
        $end = "END:VJOURNAL\r\n";
    }
    else {
        fwrite($file,  "BEGIN:VEVENT\r\n");
        $end = "END:VEVENT\r\n";
    }
    
    fwrite($file,  "DTSTART:$dateStart\r\n");
    fwrite($file,  "DTEND:$dateEnd\r\n");
    fwrite($file,  "DTSTAMP:$dateNow\r\n");
    fwrite($file,  "UID:$guid\r\n");
    fwrite($file,  "DESCRIPTION:" . $description . "\r\n");
    //fwrite($file,  "LOCATION:West Wing\r\n");
    if($location != "") {
        fwrite($file, "GEO:" . $location);
    }
    fwrite($file,  "SEQUENCE:0\r\n");
    fwrite($file,  "STATUS:CONFIRMED\r\n");
    if($category != "")
        fwrite($file,  "CATEGORIES:". $category ."\r\n");
    fwrite($file,  "SUMMARY:$summary\r\n");
    fwrite($file,  "TRANSP:OPAQUE\r\n");

    fwrite($file,  $end);
}


function createICSDatestamp($time) {
    // 19970715T035959
    $date = formatTime($time);
    $date = str_replace(" ", "T", $date);
    $date = str_replace("-", "", $date);
    $date = str_replace(":", "", $date);
    $date = preg_replace("/\..*/", "", $date);
    return $date;// . "Z";
}
    
?>
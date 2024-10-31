<?php

// =================================================

function getEventTitle($event) {
    $title = $event['type'];
    if ($event['page']=="note") {
        $title = $event['data']['title'];
    }
    return $title;
}
function getEventDescription($event) {
    $description = $event['page'];
    if ($event['page']=="note" && isset($event['data']['text'])) {
        $description = $event['data']['text'];
    }
    return $description;
}
function getEventLocation($event) {
    return getLocationString($event['data'], true);
}
function getLocationString($data, $short) {
    $location = "";
    if( isset($data['location']) ) {
    	if($short) {
            $location = $data['location'][0][1] .";". $data['location'][0][2] . "\r\n";
        }
	else {
	     $location = "Lat: ".$data['location'][0][1] .", Lng: ". $data['location'][0][2];
	     //xxx compute alt.
        }
    }
    return $location;
}
function getEventMedia($event, $mediaURL) {
    $media = "";
    if ($event['page']=="note" && isset($event['data']['audio'])) {
        $media = $mediaURL . "&action=downloadFile&id=" . $event['eid'];
    }
    return $media;
}

// =================================================

// convert a local string to a GMT string, or from a GMT string
function convertGMT($str, $alreadyGMT = false) {
    $t = unformatTime($str);
    $date = new DateTime();
    $tzsec = getTZOffset($date->format('P'));
    if($alreadyGMT) {
        $t += $tzsec * 1000;
    }
    else {
        $t -= $tzsec * 1000;
    }
    $s = formatTime($t);
    return $s;
}

function getTZOffsetFromParams($FORM) {
    $tzsec = 0;
    if(isset($FORM['tz']))
        $tzsec = getTZOffset($FORM['tz']);
    return $tzsec;
}

function getTZOffset($tz) {
    $sec = 0;
    if($tz) {
        $tz = explode(":", $tz);
        if(count($tz)==1) {
            $tz = $tz[0];
            if(strlen($tz) >= 4) {
                $tz = array( substr($tz,0,-2), substr($tz,-2));
            }
        }
        $hours = 0;
        $minutes = 0;
        if(count($tz) > 0)
            $hours = intval($tz[0]);
        if(count($tz) > 1) {
            $minutes = intval($tz[1]);
            if($hours < 0)
                $minutes = -$minutes;
        }
        $sec = (($hours*60) + $minutes)*60;
    }
    return $sec;
}

function getLocalTZOffset() {
    $Offset = date("O", 0);

    $Parity = $Offset < 0 ? -1 : 1;
    $Offset = $Parity * $Offset;
    $Offset = ($Offset - ($Offset % 100))/100*60 + $Offset % 100;

    return $Parity * $Offset * 60;
}

// create a string from a number
function formatZTime($ms, $tzsec=0) {
    $time = formatTime($ms, $gmt);
    // remove milliseconds
    $time = explode(".", $time);
    $time = $time[0];
    // change format
    $time = str_replace(" ", "T", $time) . "Z";
    return $time;
}

function formatTime($ms, $tzsec = 0) {
    $sec     = $ms / 1000;
    $millis  = $ms % 1000;
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('GMT'));
    $date->setTimestamp($sec + $tzsec);
    $time = $date->format('Y-m-d H:i:s');
    $time = $time . "." . sprintf("%03d", $millis);
    return $time;
}

// create a number from a string
function unformatTime($str, $tzsec=0) {
    $str = str_replace("\"", "", $str);
    $arr = explode(".", $str);
    $time = 0;
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $arr[0], new DateTimeZone('GMT'));
    if($date) {
        $time = $date->getTimestamp() + $tzsec;//format('U');
        //$time   += Time::Piece->localtime->tzoffset;
        $time = $time * 1000;
        if($arr > 1) {
            $ms = str_pad($arr[1],3,"0", STR_PAD_RIGHT); // make sure it is at least three digits
            $ms = substr($ms,0,3);
            $time += intval( $ms );
        }
    }
    return $time;
}

function formatDuration($t) {
    $fmt  = "";
    $ms   = $t % 1000;
    $t    = ($t - $ms) / 1000; 
    if($ms)
        $fmt = sprintf(".%03d", $ms);
    $sec  = $t % 60;
    $t    = ($t - $sec) / 60; 
    if($t)
        $fmt = sprintf(":%02d", $sec) . $fmt;
    else
        $fmt = sprintf("%d", $sec) . $fmt;
    $min  = $t % 60;
    $t    = ($t - $min) / 60;
    if($t)
        $fmt = sprintf(":%02d", $min) . $fmt;
    else if($min)
        $fmt = sprintf("%d", $min) . $fmt;
    $hrs  = $t % 24;
    $t    = ($t - $hrs) / 24;
    $days = $t;
    if($days)
        $fmt  = sprintf("%d", $days) . $fmt;
    return $fmt;
}

// =====================================================================
// ET CETERA
// =====================================================================

function DBG($txt) {
    if(!is_string($txt))
        $txt = print_r($txt,true);
    $tempDir = sys_get_temp_dir();
    $filename = $tempDir . "/psygraph_errorLog.txt";
    try {
        $out = fopen($filename, "a");
        fputs($out,"$txt\n");
        fclose($out);
    }
    catch( Exception $e ) {
    }
}

//the following is necessary since dates are passed in from web queries
/*
function parseDateGMT($date) {
    if($date=="")
        return 0;
    list($d, $msec) = explode('.',$date);
    $t=strtotime($d); // ."GMT" (or "Z") may be necessary
    $ms = 0;
    if($msec != "") {
        // extend to MS
        while(strlen($msec) < 3)
            $msec .= "0";
        // clip to MS
        $msec = substr($msec, 0, 3);
        $ms = intval($msec);
    }
    //DBG($d ." ". $ms ." ". $t);
    return $t*1000 + $ms; // we need time in milliseconds
}
*/

function html_encode($txt) {
    $txt = htmlentities($txt);
    $txt = nl2br($txt);
    $txt = str_replace(array("\r", "\n"), '', $txt);
    $txt = "<p>" . $txt . "</p>";
    return $txt;
}

?>
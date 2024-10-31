<?php

require_once("util.php");

// we only read CSV or JSON
function in_read($FORM, $file) {
    $msg = "";
    if($FORM["format"] == "csv") {
        $msg .= in_readCSV($FORM, $file);
    }
    else if($FORM["format"] == "json") {
        $msg .= in_readJSON($FORM, $file);
    }
    else {
        $msg .= "Error: unknown file type: " . $FORM['format'];
    }
    return $msg;
}

function in_readCSV($FORM, $file) {
    $nLines = 0;
    try {
        // read the header line
        $event = fgetcsv($file);
        while(! feof($file) ) {
            $event = fgetcsv($file);
            if(count($event)!=7)
                break;
            $nLines++;

            $e["eid"]      = 0+$event[0];
            $e["uid"]      = $FORM["uid"];
            $e["start"]    = unformatTime($event[1]);
            $e["duration"] = 0+$event[2];
            $e["category"] = $event[3];
            $e["page"]     = $event[4];
            $e["type"]     = $event[5];
            $e["data"]     = json_decode($event[6], true);
            $ans           = createValidEvent($e);
        }
        return "Added " . $nLines . " events.";
    }
    catch( Exception $e ) {
        DBG($e);
        return "Error: " . print_r($e, true);
    }
}

function in_readJSON($FORM, $file) {
    $string = "";
    while(! feof($file) ) {
        $string .= fgets($file);
    }
    $events = json_decode($string, true);

    $lineNo = 0;
    for($i=1; $i<count($events); $i++) {
        $lineNo ++;
                
        // eid, uid, cid, pid, start, duration, type, data
        $e["eid"]      = 0+$events[$i][0];
        $e["uid"]      = $FORM['uid'];
        $e["start"]    = $events[$i][1];
        $e["duration"] = $events[$i][2];
        $e["category"] = $events[$i][3];
        $e["page"]     = $events[$i][4];
        $e["type"]     = $events[$i][5];
        $e["data"]     = $events[$i][6];
        $ans           = createValidEvent($e);
    }
    return "Added " . $lineNo . " events.";
}

function in_readPsygraph($FORM, $file) {
    $string = "";
    while(! feof($file) ) {
        $string .= fgets($file);
    }
    $pg = json_decode($string, true);

    $txt = "";
    $username = $pg['user']['username'];
    setUser($pg['user']);
    $uid = getIDFromUsername($username);

    setPages($uid, $pg['pages']);
    setCategories($uid, $pg['categories']);

    $events = $pg['events'];
    $lineNo = 0;
    for($i=0; $i<count($events); $i++) {
        $lineNo ++;
                
        // eid, uid, cid, pid, start, duration, type, data
        $e["eid"]      = 0+$events[i][E_EID];
        $e["uid"]      = $uid;
        $e["category"] = nameForCategoryID($pg['categories'], $events[$i][E_CID]);
        $e["page"]     = nameForPageID($pg['pages'], $events[$i][E_PID]);
        $e["start"]    = $events[$i][E_START];
        $e["duration"] = $events[$i][E_DURATION];
        $e["type"]     = $events[$i][E_TYPE];
        $e["data"]     = json_decode($events[$i][E_DATA], true); // xxx wasteful, since we encode it again soon.
        $ans           = createValidEvent($e);
    }
    return "Added " . $lineNo . " events.";
}

function nameForCategoryID($categories, $cid) {
    for($i=0; $i<count($categories); $i++) {
        if($categories[$i]['cid'] == $cid)
            return $categories[$i]['name'];
    }
    return "error";
}

function nameForPageID($pages, $pid) {
    for($i=0; $i<count($pages); $i++) {
        if($pages[$i]['pid'] == $pid)
            return $pages[$i]['name'];
    }
    return "error";
}

?>
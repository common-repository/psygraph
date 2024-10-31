<?php

require_once("pg.php");
require_once("util.php");

$FORM = json_decode(file_get_contents("php://input", TRUE), true);

if(! $FORM) {
    $msg = array("error" => "Invalid JSON input passed to server" );
    printArray( $msg );
}
else {
    $FORM = handleLogin($FORM);
    doAction($FORM["action"], $FORM);
}
exit(0);

function doAction($action, $FORM) {
    
    $username = $FORM['username'];
    // prevent certain actions
    switch ($action) {
    case "checkUser":
    case "login":
    case "settings":
    case "getPageData":
    case "getEventArray":
        $reason = checkUserPermission($username, "read");
        if($reason)
            printArray( array("error"=>$reason) );
        break;
    case "settingsData":
    case "changeEventCategory":
    case "deleteEventIDs":
    case "deleteAllEvents":
    case "deleteUser":
    case "deleteEventArray":
    case "setEventArray":
    default:
        $reason = checkUserPermission($username, "write");
        if($reason)
            printArray( array("error"=>$reason) );
        break;
    }

    if($action == "checkUser") {
        // just see if they exist in wordpress
        $msg = array();
        $wpid = WPCheckUser($FORM['username']);
        if($wpid==0) {
            $msg = array("error" => "No such username: " . $FORM["username"]);
        }
        printArray( $msg );
    }
    else if($FORM["uid"] < 0) {
        $msg = "Invalid login for action ". $action .": ". $FORM["username"];
        printArray( array("error" => $msg) );
    }

    switch ($action) {
    case "login":
        $data['username']       = $FORM['username'];
        $data['cert']           = $FORM['cert'];
        $data['certExpiration'] = $FORM['certExpiration'];
        $data['categories']     = $FORM['categories'];
        $data['pages']          = $FORM['pages'];
        $data['pageData']       = $FORM['pageData'];
        $data['mtime']          = $FORM['mtime'];
        printArray( $data );
        break;
    case "settings":
        $pg                 = json_decode($FORM["pg"], true);
        $user["uid"]        = $FORM["uid"];
        $user["username"]   = $FORM["username"];
        $user["categories"] = $pg["categories"];
        $user["pages"]      = $pg["pages"];
        $pd                 = $pg["pageData"];
        //$ud                 = $pg["userData"];
        $mtime              = $pg['mtime'];
        $dbMtime            = getUserMtime($user["uid"]);
        if($mtime > $dbMtime) {
            $data = updateUser($user, $pd, $mtime);
            printArray($data);
        }
        else {
            printArray( array("mtime", $mtime, $dbMtime) );
        }
        break;
    case "settingsData":
        $uid  = $FORM["uid"];
        $data = $FORM["data"];
        $pd   = $data["pageData"];
        updateUserData($uid, $pd);
        printArray( array() );
        break;
    case "getPageData":
        $uid  = $FORM["uid"];
        $data = $FORM["data"];
        $ans = array();
        foreach ($data as $page => $val) {
            $d = getPageData($uid, $page);
            $ans[$page]['mtime'] = $d[0];
            $ans[$page]['data']  = $d[1];
        }
        printArray( $ans );
        break;
    case "changeEventCategory":
        $ids = $FORM["ids"];
        $cat = $FORM["category"];
        $ans = changeEventCategory($ids, $cat);
        printArray( array() );
        break;
    case "deleteEventIDs":
        $ids = $FORM["ids"];
        deleteEventMedia($FORM["username"], $FORM["cert"], $ids);
        deleteEvents($ids);
        printArray( array() );
        break;
    case "deleteAllEvents":
        $events = getEventsForUser($FORM["uid"]);
        $ids = array();
        for($i=0; $i<count($events); $i++) {
            $id = 0+$events[$i][E_EID];
            array_push($ids, $id);
        }
        deleteEventMedia($FORM["username"], $FORM["cert"], $ids);
        deleteAllEventsForUID($FORM["uid"]);
        printArray( array() );
        break;
    case "deleteUser":
        deleteUser($FORM["uid"]);
        printArray( array() );
        break;
    case "deleteEventArray":
        $data   = $FORM["data"];
        $events = $data["events"];
        $ids = array();
        for($i=0; $i<count($events); $i++) {
            $id = 0+$events[$i][E_EID];
            array_push($ids, $id);
        }
        deleteEventMedia($FORM["username"], $FORM["cert"], $ids);
        deleteEvents($ids);
        $out["ids"] = $ids;
        printArray( $out );
        break;
    case "getEventArray":
        $e        = getEventsForUser($FORM["uid"]);
        $readable = parseReadableArray($e);
        printArray( $readable );
        break;
    case "setEventArray":
        $out = array();
        $data = $FORM["data"];
        $out["startTime"] = $data["startTime"];
        $out["endTime"]   = $data["endTime"];
        $events = $data["events"];
        $output = array();

        for($i=0; $i<count($events); $i++) {

            $e = array();
            $e["uid"]      = $FORM["uid"];
            $e["eid"]      = $events[$i][0];
            $e["start"]    = $events[$i][1];
            $e["duration"] = $events[$i][2];
            $e["category"] = $events[$i][3];
            $e["page"]     = $events[$i][4];
            $e["type"]     = $events[$i][5];
            $e["data"]     = $events[$i][6];
            if( hasEventID( $e["eid"] ) ) {
                //printArray( [error => "event already exists."] );
                DBG("Error: event $e[eid] exists");
            }
            else {
                $eid = createEvent($e);
                $output[count($output)] = array($e["eid"], $eid);

                // If the event was a note without a corresponding audio file,
                // create a WordPress post.
                if(!strcmp($e["page"], "note")) {
                    $edata = $e['data']; //json_decode($e["data"], true);
                    if(! isset($edata["audio"]) && isset($edata["text"])) {
                        $username = $FORM['username'];
                        $cert     = $FORM['cert'];
                        //$eid      = "" . $e['eid'];  //dont use the old value
                        $filename = "";
                        $fileSrc  = "";
                        $title    = $edata['title'];
                        $text     = $edata['text'];
                        $loc      = getLocationString($edata, false);
                        $category = $e['category'];
                        $rslt = WPUploadMedia($username, $cert, $eid, $filename, $fileSrc, $title, $text, $loc, $category);
                        if($rslt != "")
                            DBG($rslt);
                    }
                }
            }
        }
        $out["idlist"] = $output;
        printArray( $out );
        break;
    default:
        printArray( array( "error" => "unrecognized action: ". $action ) );
    }
    printArray( array( "error" => "incorrect response" ) );
}

?>
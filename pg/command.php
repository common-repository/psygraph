<?php

require_once("pg.php");
require_once("out.php");
require_once("in.php");

// parse the input, do the action
$FORM = $_POST;
$FORM = handleLogin($FORM);
doAction($FORM["action"], $FORM);
exit(0);


function doAction($action, $FORM) {
    
    if($FORM["uid"] < 0) {
        $msg = $FORM["username"] .",". $FORM["cert"];
        printResult( "Invalid login: $msg");
    }

    $username = $FORM['username'];
    $reason = checkUserPermission($username, "write");
    if($reason) {
        printResult( $reason );
    }

    if($action == "deleteEvents")
        deleteEventsCmd($FORM);
    else if($action == "deleteUser")
        deleteUserCmd($FORM);
    else if($action == "getCert")
        getCertCmd($FORM);
    else if($action == "printForm")
        printFormCmd($FORM);
    else if($action == "printCategories")
        printCategoriesCmd($FORM);
    else {
        printResult("unrecognized action: ". $action);
    }
}

function deleteEventsCmd($FORM) {
    print "Deleting events for " . $FORM['username'] . "...\n";
    if (isset($FORM['destroy']) && $FORM['destroy'] == "destroy") {
        deleteAllEventsForUID($FORM['uid']);
        print "Destroyed.\n";
    }
    else {
        deleteAllEventsForUser($FORM['username']);
        print "Deleted.\n";
    }
}    

function deleteUserCmd($FORM) {
    print "Deleting user: " . $FORM['username'] . "...\n";
    deleteUser($FORM['uid']);
    print "Deleted.\n";
}

function getCertCmd($FORM) {
    WPAuthenticate($FORM['username'], $FORM['password']);
    $uid  = getIDFromUsername($FORM['username']);
    $cert = getCert($uid);

    if(! verifyCert($uid, $cert[0]))
        print("Cert is not valid: ". $cert[1] ." ". time() ."\n");

    if(WPCheckUser($FORM['username']) == 0)
        print("User didn't check out\n");

    print $cert[0];
}

function printFormCmd($FORM) {
    $info = print_r($FORM, true);
    echo $info;
}

function printCategoriesCmd($FORM) {
    $categories = getCategories($FORM['uid'], false);
    print_r($categories, true);
}
?>


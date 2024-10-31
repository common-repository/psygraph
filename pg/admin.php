<?php

include_once("pg.php");
include_once("in.php");

$FORM = getHttpParams();

if(isset($FORM['query']) && isset($FORM['username']) ) {
    $query = $FORM['query'];
    if($query == "publicAccess" || $query == "createPosts") {
        $uid  = getIDFromUsername( $FORM["username"] );
        $data = array();
        $data[$query] = getUserDataValue($uid, $query);
        $jdata = json_encode($data, true);
        printResult($jdata);
        exit(0);
    }
    exit(1);
}

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

$resp = "";
if(isset( $FORM['respond']) && $FORM['respond']) {
    $publicAccess = strcmp($FORM['publicAccess'], "on") ? 0 : 1;
    setUserDataValue($FORM['uid'], "publicAccess", $publicAccess);
    $createPosts = strcmp($FORM['createPosts'], "on") ? 0 : 1;
    setUserDataValue($FORM['uid'], "createPosts", $createPosts);
    $response  = "<h2>Changes processed.</h2>\n";

    $publicAccess = getUserDataValue($FORM['uid'], "publicAccess");
    $string = $publicAccess ? "on" : "off";
    $response .= "Public access: ".$string."<br/>\n";
    $createPosts = getUserDataValue($FORM['uid'], "createPosts");
    $string = $createPosts ? "on" : "off";
    $response .= "Create posts: ".$string."<br/>\n";

    $response .= "<hr/>";
    $response .= "<p><a href='javascript:history.back()'>Go back</a></p>\n";
    printHTMLResult($response);
}
else {
    $url = $FORM['server'] ."/admin.php";
    $response  = '<div id="pgUpload">';
    $response .= '<form action="'.$url.'" method="post" enctype="multipart/form-data">';

    // publicAccess
    $string    = getUserDataValue($FORM['uid'], "publicAccess");
    $checked   = $string ? "checked" : "";
    $response .= '<p><em>The following checkbox allows public web access to your data on this server.</em></p>';
    $response .= '<label for="publicAccess">Public access:</label>';
    $response .= '<input name="publicAccess" id="publicAccess"  type="checkbox" '.$checked.' /><br/><br/>';

    $string    = getUserDataValue($FORM['uid'], "createPosts");
    $checked   = $string ? "checked" : "";
    $response .= '<p><em>The following checkbox creates public WordPress posts on this server.</em></p>';
    $response .= '<label for="createPosts">Create posts:</label>';
    $response .= '<input name="createPosts" id="createPosts"  type="checkbox" '.$checked.' /><br/><br/>';

    $response .= '<input type="hidden" name="respond"  value="true"  id="respond"/>';
    $response .= '<input type="hidden" name="server"   value="'. $FORM['server']. '"   id="format"/>';
    $response .= '<input type="hidden" name="username" value="'. $FORM['username']. '" id="user"/>';
    $response .= '<input type="hidden" name="cert"     value="'. $FORM['cert']. '"     id="cert"/>';
    $response .= '<input type="submit" name="submit" value="Submit"/>';
    $response .= '<br/>';
    $response .= '</form>';
    $response .= '</div>';
    printHTMLResult($response);
}

?>

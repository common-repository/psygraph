<?php

// ini_set('display_errors', 'On');
// error_reporting(E_ALL);

require_once("./util.php");

// constant values for the database
const U_TABLE       = 'users';
const U_UID         = 0;
const U_MTIME       = 1;
const U_USERNAME    = 2;
const U_CERT        = 3;
const U_CERT_EXPIRE = 4;
const U_DATA        = 5;

const P_TABLE    = 'pages';
const P_PID      = 0;
const P_MTIME    = 1;
const P_UID      = 2;
const P_NDX      = 3;
const P_NAME     = 4;
const P_DATA     = 5;
//const P_ACTIVE   = 6;
const MAX_ACTIVE_PAGES = 100;

const C_TABLE    = 'categories';
const C_CID      = 0;
const C_UID      = 1;
const C_NDX      = 2;
const C_NAME     = 3;
//const C_ACTIVE   = 6;
const MAX_ACTIVE_CATEGORIES = 100;

const E_TABLE    = 'events';
const E_EID      = 0;
const E_UID      = 1;
const E_CID      = 2;
const E_PID      = 3;
const E_START    = 4;
const E_DURATION = 5;
const E_TYPE     = 6;
const E_DATA     = 7;
const E_ACTIVE   = 8;

$DBhost = "";
$DBport = "";
$DB     = "";
$DBuser = "";
$DBpass = "";
$WPurl  = "";

// Load essential DB configuration
if(!$DBhost)
    readConfig();

function readConfig() {
    global $DBhost, $DBport, $DB, $DBuser, $DBpass, $WPurl;
    // First try to read from a file.
    $params = null;
    $ans    = null;
    $fromWP = false;
    // See if we can get the variables from a wordpress installation on localhost.
    if(func_num_args() >= 2) {
        $args = func_get_args();
        $WPurl = "http://localhost";
        if(func_num_args() == 3)
            $WPurl = $args[2];
        $WPurl .= "/xmlrpc.php";
        $params = WPGetVars($args[0], $args[1]);
        $fromWP = true;
    }
    else {
        $fn = array(__DIR__."/pgConfig.xml", dirname(__DIR__)."/pgConfig.xml",  dirname(dirname(__DIR__))."/pgConfig.xml");
        for($i=0; $i<count($fn); $i++) {
            if(file_exists($fn[$i])) {
                $params = file_get_contents($fn[$i]);
                break;
            }
        }
    }
    if($params != "") {
        $ans = pgXmlRpcRead($params);
        if($ans && $fromWP) 
            file_put_contents(__DIR__."/pgConfig.xml", $params);
        $DBhost = $ans['DBhost'];
        $DBport = $ans['DBport'];
        $DB     = $ans['DB'];
        $DBuser = $ans['DBuser'];
        $DBpass = $ans['DBpass'];
        $WPurl  = $ans['WPurl'];
    }
    return $ans;
}


// Set a few other variables
$DBconnect = "mysql:host=" .$DBhost. ";port=" .$DBport. ";dbname=" .$DB;
$DBopts = array(PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);


// =====================================================================
// HANDLE LOGIN, HTTP PARAMETERS, ETC
// =====================================================================
function getHttpParams() {
    $FORM = array();
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $FORM = array_merge($_POST,$_GET);
    }
    else {
        $FORM = $_GET;
    }

    // If magic quotes is enabled, strip the extra slashes.
    if(get_magic_quotes_gpc()) {
        //array_map("strip_array", $FORM):stripslashes($FORM); 
        array_walk_recursive($FORM, create_function('&$v,$k','$v = stripslashes($v);'));
    }
    return $FORM;
}

function ensureHTTPS() {
    if ( empty($_SERVER['HTTPS']) ||
         $_SERVER['HTTPS'] == "off" )
    {
        DBG("Insecure (HTTP) access.");
        //print($CGI_Obj->redirect("$hrefRoot/index.cgi"));
    }
}

function getPGCookie() {
    $mCookie = "";
    if(isset($_COOKIE['Psygraph'])) {
        $mCookie = $_COOKIE['Psygraph'];
    }
    return $mCookie;
}
function setPGCookie ( $FORM ) {
    setcookie("Psygraph", "", time() + 24*60*60, "/", "psygraph.com", 1);
}

function sanitizeFORM($INFORM) {
    // type checking for all form parameters
    foreach ($INFORM as $name => $value) {
        switch($name) {
        case "url":
        case "format":
        case "action":
        case "start":
        case "end":
        case "page":
        case "username":
        case "password":
        case "cert":
        case "version":
        // the following are parameters from wordpress
        case "max":
        case "id":
        case "type":
        case "server":
        case "wp_url":
        // file uploads
        case "fileKey":
        case "filename":
        // admin
        case "respond":
        case "submit":
        case "publicAccess":
            $OUTFORM[$name] = strval($value);
            break;
        case "embedded":
        case "postID":
            $OUTFORM[$name] = intval($value);
            break;
        case "data":
            if(!is_array($value)) {
                DBG("FORM param $name is not an array");
            }
            $OUTFORM[$name] = $value;
            break;
        case "pg":
            if(!is_string($value)) {
                DBG("FORM param $name is not an object");
            }
            $OUTFORM[$name] = $value;
            break;
        default:
            //DBG("FORM param: $name not recognized");
            $OUTFORM[$name] = $value;
        }
    }
    return $OUTFORM;
}
function handleLogin($FORM) {
    //ensureHTTPS();
    $FORM = sanitizeFORM($FORM);
    $username = $FORM["username"];
    $cert = ["", 0];

    createDB();
    $uid = -1;
    if(isset($FORM["password"]) && 
       $FORM["password"] != "") {
        $password = $FORM["password"];
        // See if the user exists in WP.
        if(WPAuthenticate($username, $password)) {
            $uid = getIDFromUsername( $username );
            if($uid >= 0) {
                $cert = getCert($uid);
            }
        }
        else {
            // Who is this?
        }
        $FORM['password'] = "";
    }
    else {
        $cert = ["", 0];
        $uid = getIDFromUsername( $username );
        if(! isset($FORM['cert'])) {
            $uid=-1;
        }
        // check the local certificate
        else if($uid > 0 && verifyCert($uid, $FORM["cert"]) )  {
            $cert = getCert($uid);
        }
        else {
            // check the remote certificate
            if(WPCertify($username, $FORM['cert'], "read")) {
                $cert = getCert($uid);
            }
            else {
                DBG("Login failure for " . $username);
                $uid=-1;
            }
        }
    }
    if($uid>0)
        $FORM = augmentForm($uid, $FORM);
    $FORM['cert']           = $cert[0];
    $FORM['certExpiration'] = $cert[1];
    $FORM['uid']            = $uid;
    return $FORM;
}
function checkUserPermission($username, $perm) {
    $filename = "blacklist_".$perm.".txt";
    if(file_exists($filename)) {
        $lines = file($filename);
        foreach ($lines as $line) {
            if($username == trim($line))
                return "user '".$username."' cannot modify data.";
        }
    }
    return "";
}
function handlePublicLogin($FORM) {
    $username = $FORM["username"];
    $cert = array("", 0);

    $uid = getIDFromUsername( $username );
    if($uid > 0) {
        if(getUserDataValue($uid, "publicAccess")) {
            $cert = getCert($uid);
            $FORM = augmentForm($uid, $FORM);
        }
        else {
            $uid = -1;
        }
    }
    $FORM['uid']  = $uid;
    $FORM['cert'] = $cert[0];
    $FORM['certExpiration'] = $cert[1];
    return $FORM;
}

function augmentForm($uid, $FORM) {
    $cat = getCategories($uid, false);
    $FORM["categories"] = $cat;

    $pages = getPages($uid, false);
    $FORM["pages"] = array();
    $pageData = array();
    if(count($pages)) {
        for($i=0; $i<count($pages)-1; $i++) {
            $page               = $pages[$i][P_NAME];
            $FORM["pages"][$i]  = $page;
            $pageData[$page]['mtime'] = 0 +$pages[$i][P_MTIME];
            $pageData[$page]['data']  = "";
        }
        $page               = $pages[count($pages)-1][P_NAME];
        $FORM["pages"][$i]  = $page;
        $pageData[$page]['mtime'] = 0 +$pages[count($pages)-1][P_MTIME];
        $pageData[$page]['data']  = "";
    }
    $FORM["pageData"] = $pageData;    
    
    $FORM["mtime"] = getUserMtime($uid);
    return $FORM;
}


// =====================================================================
// hand-rolled, simple, XML-RPC
// =====================================================================

function pgXmlRpcReadE($e) {
    $ans = "";
    if(isset($e->boolean)) {
        $ans = 0+ $e->boolean[0];
    }
    else if(isset($e->string)) {
        $ans = "". $e->string[0];
    }
    else if(isset($e->int)) {
        $ans = 0+ $e->int[0];
    }
    else if(isset($e->struct)) {
        $ans = array();
        $members = $e->struct->member;
        for($i=0; $i<count($members); $i++) {
            $m = $members[$i];
            $ans["". $m->name] = pgXmlRpcReadE($m->value);
        }
    }
    else {
        DBG("pgXmlRpcReadE: " . print_r($e));
    }
    return $ans;
}
function pgXmlRpcRead($message) {
    //return xmlrpc_decode($message);
    $ans = array();
    $err = true;
    try {
        $err = libxml_use_internal_errors(true);
        $msg = new SimpleXMLElement($message);
        $param = $msg->params->param[0];
        foreach ($param->value as $p) {
            $ans[] = pgXmlRpcReadE($p);
        }
        if(count($ans)==1)
            $ans = $ans[0];
    }
    catch( Exception $e ) {
        $ans = null;
    }
    libxml_use_internal_errors($err);
    return $ans;
}
function pgXmlRpcWrite() {
    $args = func_get_args();
    //$method = array_shift($args);
    //return xmlrpc_encode_request($method, $args);
    $message = '<?xml version="1.0" encoding="UTF-8"?>';
    $message .= "<methodCall><methodName>".$args[0]."</methodName><params>\n";
    for ($i=1; $i<count($args); $i++) {
        $message .= "  <param><value><string>".$args[$i]."</string></value></param>\n";
    }
    $message .= "</params></methodCall>\n";
    return $message;
}
function pgXmlRpcSend($message) {
    global $WPurl;

    $req = curl_init();
    $headers = array();
    array_push($headers,"Content-Type: text/xml");
    array_push($headers,"Content-Length: ".strlen($message));

    //curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
    //curl_setopt($req, CURLOPT_SSL_VERIFYHOST, false);
    //curl_setopt($req, CURLOPT_VERBOSE,1);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($req, CURLOPT_HEADER, true );
    curl_setopt($req, CURLOPT_URL, $WPurl ."/xmlrpc.php");
    curl_setopt($req, CURLOPT_POST, true );
    curl_setopt($req, CURLOPT_HTTPHEADER, $headers );
    curl_setopt($req, CURLOPT_POSTFIELDS, $message );
    curl_setopt($req, CURLOPT_TIMEOUT, 16);
    curl_setopt($req, CURLOPT_ENCODING ,"UTF-8");
    curl_setopt($req, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5) Gecko/20041107 Firefox/1.0'); 
    
    $response = curl_exec($req);
    if($errno = curl_errno($req)) {
        $error_message = "";
        if(function_exists("curl_strerror"))
            $error_message = curl_strerror($errno);
        DBG("cURL error ({$errno}):\n {$error_message}");
    }
    curl_close($req);
    return $response;
}


// =====================================================================
// WORDPRESS CONNECTIVITY
// =====================================================================

function WPGetVars($username, $cert) {
    //return pg_wp_getVars();
    $message = pgXmlrpcWrite("pg.getVars", $username, $cert);
    return pgXmlRpcSend($message);
    // unparsed.
}
function WPCheckUser($username) {
    //return pg_wp_checkUser($username);
    $message = pgXmlrpcWrite("pg.checkUser", $username, "foobar"); // passing one parameter is a bug somewhere...
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);
    return $ans;
}
function WPAuthenticate($username, $password) {
    //$ans = pg_wp_authenticate($username, $password);
    $message = pgXmlrpcWrite("pg.authenticate", $username, $password);
    $resp = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($resp);

    if($ans['success'] == 1) {
        $uid = getIDFromUsername($username);
        if($uid == -1) { // create the user.
            $uid = createUser($username);
        }
        setCert($uid, $ans['cert'], $ans['time']*1000);
        return true;
    }
    return false;
}
function WPCertify($username, $cert, $cap) {
    //return pg_wp_getValue($username, $cert, $cap);
    $message = pgXmlrpcWrite("pg.certify", $username, $cert, $cap);
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);

    if($ans==true) {
        $uid = getIDFromUsername($username);
        if($uid == -1) { // create the user.
            $uid = createUser($username);
        }
        setCert($uid, $cert, $ans['time']*1000);
        return true;
    }
    return $ans;
}
function WPValue($username, $cert, $value) {
    //return pg_wp_getValue($username, $cert, $value);
    $message = pgXmlrpcWrite("pg.getValue", $username, $cert, $value);
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);
    return $ans;
}
function WPGetMediaURL($username, $cert, $id) {
    //return pg_wp_getMediaURL($id);
    $message = pgXmlrpcWrite("pg.getMediaURL", $username, $cert, $id);
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);
    return $ans;
}
function WPGetMediaIDs($username, $cert) {
    //return pg_wp_getMediaIDs($id);
    $message = pgXmlrpcWrite("pg.getMediaIDs", $username, $cert);
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);
    return $ans;
}
function WPUploadMedia($username, $cert, $eid, $filename, $fileSrc, $title, $text, $loc, $category) {
    $uid = getIDFromUsername($username);
    if(! getUserDataValue($uid,"createPosts"))
        return "";
    // convert the title and text
    if($filename != "") {
        $text .= "<p class='audio'>Audio: <a href=\"PG_MEDIA_URL\">PG_MEDIA_URL</a></p>\n";
    }
    if($loc != "") {
        $text .= "<p class='location'>Location: [" .$loc . "]</p>\n";
    }
    $text  = htmlspecialchars($text);
    $title = htmlspecialchars($title);
    $message = pgXmlrpcWrite("pg.uploadMedia", $username, $cert, $eid, $filename, $fileSrc, $title, $text, $category);
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);
    return $ans;
}
function WPDeleteMedia($username, $cert, $ids) {
    //return pg_wp_deleteMedia($id);
    $message = pgXmlrpcWrite("pg.deleteMedia", $username, $cert, $ids);
    $ans = pgXmlRpcSend($message);
    $ans = pgXmlRpcRead($ans);
    return $ans;
}


// =====================================================================
// CERTIFICATES
// =====================================================================

//function createCert($uid) {
//    // create a new certificate valid for 48 hours
//    $cert = bin2hex(openssl_random_pseudo_bytes(10));
//    $time = time() + (48 * 60 * 60);
//    $stmt = "UPDATE ".U_TABLE." SET password='$cert', time='$time' WHERE uid='$uid'";
//    $ans = setDB($stmt);
//    DBG("Creating new certificate (".$cert.") for " . $uid);
//    return $cert;
//}
//function getValidCert($uid) {
//    $stmt = "SELECT password,time FROM ".U_TABLE." WHERE uid='$uid'";
//    $ans = getDB($stmt);
//    $dbPass = $ans[0][0];
//    $dbTime = $ans[0][1];
//    if( $dbTime > time() ) {
//        return $dbPass;
//    }
//    else {
//        return createCert($uid);
//    }
//}
function getCert($uid) {
    $stmt = "SELECT cert,certExpiration FROM ".U_TABLE." WHERE uid=?";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    $ans[0][1] += 0; // numeric cast
    return $ans[0];
}
function setCert($uid, $cert, $time) {
    $stmt = "UPDATE ".U_TABLE." SET cert=?,certExpiration=? WHERE uid=?";
    $ans = setDB($stmt, array($cert, PDO::PARAM_STR, $time, PDO::PARAM_INT, $uid, PDO::PARAM_INT));
    return $ans;
}
function verifyCert($uid, $cert) {
    $stmt = "SELECT cert,certExpiration FROM ".U_TABLE." WHERE uid=?";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    $dbPass = $ans[0][0];
    $dbTime = $ans[0][1];
    if( $cert == $dbPass && $dbTime > time()*1000 ) {
        return true;
    }
    return false;
}

// =====================================================================
// USER MANAGEMENT
// =====================================================================

function getUser ($uid) {
    $stmt = "SELECT uid,username,cert,certExpiration,m_time FROM ".U_TABLE." WHERE uid=?";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    return $ans[0];
}
function setUser($row) {
    // failure is OK if the entry exists
    $stmt = "INSERT IGNORE INTO ".U_TABLE." (m_time,username,cert,certExpiration) VALUES (?,?,?,?)";
    $ans = setDB($stmt, array($row['m_time'],         PDO::PARAM_INT, 
                              $row['username'],       PDO::PARAM_STR,
                              $row['cert'],           PDO::PARAM_STR,
                              $row['certExpiration'], PDO::PARAM_INT ));
    return $ans;
}

function getUsernameFromID($uid) {
    $stmt = "SELECT username FROM ".U_TABLE." WHERE uid=?";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    //if(count($ans) > 0) {
        return $ans[0][0];
        //}
        //return "";
}
function getIDFromUsername($username) {
    $stmt = "SELECT uid FROM ".U_TABLE." WHERE username=?";
    $ans = getDB($stmt, array($username, PDO::PARAM_STR));
    if(count($ans) > 0) {
        return 0+ $ans[0][0];
    }
    return -1;
}
function createUser($username) {
    // make sure the username is not in existence.
    $uid = getIDFromUsername($username);
    if($uid != -1) {
        DBG("ERROR: Found ID for username");
        return -1;
    }
    $cert   = "00DEADBEEF0123456789";
    $mtime  = time()*1000;
    $expire = 0; //this is not a valid cert, ever.
    $stmt   = "INSERT INTO ".U_TABLE." (username, cert, certExpiration, m_time) VALUES (?,?,?,?)";
    $ans    = setDB($stmt, array($username, PDO::PARAM_STR,
                                 $cert,     PDO::PARAM_STR,
                                 $expire,   PDO::PARAM_INT,
                                 $mtime,    PDO::PARAM_INT));
    if($ans != 1) {
        DBG("Error creating user");
        return -1;
    }
    $uid = getIDFromUsername($username);
    if($uid == -1) {
        DBG("Error creating user $username");
        return -1;
    }
    setUserData($uid, array());

    $cats = array('Uncategorized');
    for($i=0; $i<count($cats); $i++) {
        createCategory($uid, $cats[$i], $i, $mtime);
    }

    $pages = array('home', 'stopwatch', 'timer', 'counter', 'note', 'list', 'map', 'chart', 'graph');
    for($i=0; $i<count($pages); $i++) {
        createPage($uid, $pages[$i], $i, $mtime);
    }
    DBG("User $username created with uid: $uid\n");
    return $uid;
}
function deleteUser($uid) {
    $stmt = "DELETE FROM ".U_TABLE." WHERE uid=?";
    $ans = setDB($stmt, array($uid, PDO::PARAM_INT));
    $stmt = "DELETE FROM ".C_TABLE." WHERE uid=?";
    $ans = setDB($stmt, array($uid, PDO::PARAM_INT));
    $stmt = "DELETE FROM ".P_TABLE." WHERE uid=?";
    $ans = setDB($stmt, array($uid, PDO::PARAM_INT));
    $stmt = "DELETE FROM ".E_TABLE." WHERE uid=?";
    $ans = setDB($stmt, array($uid, PDO::PARAM_INT));
}
function updateUserData($uid, $pageData) {
    $lastMtime = getUserMtime($uid);
    /*
    foreach ($categoryData as $category => $value) {
        $mtime = $value['mtime'];
        $lastMtime = max($lastMtime, $mtime);
        $jdata  = $value['data'];
        $stmt  = "UPDATE ".C_TABLE." SET m_time=?, data=? WHERE uid=? AND name=?";
        $ans   = setDB($stmt, array($mtime,    PDO::PARAM_INT,
                                    $jdata,    PDO::PARAM_STR,
                                    $uid,      PDO::PARAM_INT,
                                    $category, PDO::PARAM_STR));
    }
    */
    foreach ($pageData as $page => $value) {
        $mtime = $value['mtime'];
        $lastMtime = max($lastMtime, $mtime);
        $jdata  = $value['data'];
        $stmt = "UPDATE ".P_TABLE." SET m_time=?, data=? WHERE uid=? AND name=?";
        $ans   = setDB($stmt, array($mtime, PDO::PARAM_INT,
                                    $jdata, PDO::PARAM_STR,
                                    $uid,   PDO::PARAM_INT,
                                    $page,  PDO::PARAM_STR));
    }
    setUserMtime($uid, $lastMtime);
}
function updateUser($user, $pageData, $mtime) {
    $i=1;
    $uid = $user['uid'];
    //setUserData($uid, $userData);

    //$mtime = getUserMtime($uid);
    //$mtime = time()*1000;
    setUserMtime($uid, $mtime);

    $request = array('pageData');

    $allCategories = getCategories($uid, true);
    $cats = $user["categories"];
    if($cats[0] != "Uncategorized") {
        DBG("Incorrect cat update");
        exit(33);
    }
    foreach ($cats as $category) {
        $cid = getCategoryIDFromName($uid, $category, true);
        if($cid == -1) {
            $stmt = "INSERT INTO ".C_TABLE." (name,uid,ndx) VALUES (?,?,?); ";
            $ans  = setDB($stmt, array($category, PDO::PARAM_STR,
                                       $uid,      PDO::PARAM_INT,
                                       $i,        PDO::PARAM_INT));
        }
        else {
            for($j=0; $j<count($allCategories); $j++) {
                if($cid == $allCategories[$j][0]) {
                    array_splice($allCategories, $j, 1);
                }
            }
            $stmt = "UPDATE ".C_TABLE." SET ndx=? WHERE cid=?";
            $ans  = setDB($stmt, array($i,     PDO::PARAM_INT,
                                       $cid,   PDO::PARAM_INT));
        }
        $i++;
    }
    for($i=0; $i<count($allCategories); $i++) {
        $cid  = $allCategories[$i][0];
        $idx  = MAX_ACTIVE_CATEGORIES+$i;
        $stmt = "UPDATE ".C_TABLE." SET ndx=? WHERE cid=?";
        $ans  = setDB($stmt, array($idx, PDO::PARAM_INT, $cid, PDO::PARAM_INT));
    }

    $i = 1;
    $allPages = getPages($uid, true);
    $pages = $user["pages"];

    if($pages[0] != "home") {
        DBG("Incorrect page update");
        exit(33);
    }
    foreach ($pages as $page) {
        $pid = getPageIDFromName($uid, $page, true);
        if($pid == -1) {
            $stmt = "INSERT INTO ".P_TABLE." (name,uid,ndx,m_time) VALUES (?,?,?,?)";
            $ans  = setDB($stmt, array($page,     PDO::PARAM_STR,
                                       $uid,      PDO::PARAM_INT,
                                       $i,        PDO::PARAM_INT,
                                       $mtime,    PDO::PARAM_INT));
        }
        else {
            for($j=0; $j<count($allPages); $j++) {
                if($pid == $allPages[$j][0]) {
                    array_splice($allPages, $j, 1);
                }
            }
            $stmt = "UPDATE ".P_TABLE." SET ndx=?, m_time=? WHERE pid=?";
            $ans  = setDB($stmt, array($i,     PDO::PARAM_INT,
                                       $mtime, PDO::PARAM_INT,
                                       $pid,   PDO::PARAM_INT));
        }
        // See if we need to update the page data
        $request['pageData'][$page] = 0;
        if(isset($pageData[$page])) {
            $value = $pageData[$page];
            $mtime = $value['mtime'];
            $data  = $value['data'];
            $localMtime = getPageMtime($uid, $page);
            if($mtime > $localMtime) {
                $request['pageData'][$page] = 1;
            }
        }
        else
            $request['pageData'][$page] = 1; // new page?  that cant happen...
        $i++;
    }
    for($i=0; $i<count($allPages); $i++) {
        $pid  = $allPages[$i][0];
        $idx  = MAX_ACTIVE_PAGES+$i;
        $stmt = "UPDATE ".P_TABLE." SET ndx=? WHERE pid=?";
        $ans  = setDB($stmt, array($idx, PDO::PARAM_INT, $pid, PDO::PARAM_INT));
    }
    return $request;
}
function getUserMtime($uid) {
    $stmt = "SELECT m_time FROM ".U_TABLE." WHERE uid=? ;";
    $ans  = getDB($stmt, array($uid, PDO::PARAM_INT));
    return 0 + $ans[0][0];
}
function setUserMtime($uid, $mtime) {
    $stmt  = "UPDATE ".U_TABLE." SET m_time=? WHERE uid=?";
    $ans = setDB($stmt, array($mtime, PDO::PARAM_INT, 
                              $uid,   PDO::PARAM_INT));
    if($ans != 1) {
        DBG("Error setting user mtime");
    }
}
function getUserData($uid) {
    $stmt  = "SELECT data FROM ".U_TABLE." WHERE uid=?;";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    if(!is_string($ans[0][0]))
        return array();
    return json_decode($ans[0][0], true);
}
function setUserData($uid, $data, $local=false) {
    // Since the publicAccess field is set independently, modify data with the current setting.
    if(! $local) {
        $data['publicAccess'] = getUserDataValue($uid, 'publicAccess');
        $data['createPosts'] = getUserDataValue($uid, 'createPosts');
    }
    $stmt  = "UPDATE ".U_TABLE." SET data=? WHERE uid=?";
    $jdata = json_encode($data, true);
    $ans = setDB($stmt, array($jdata, PDO::PARAM_STR, 
                              $uid, PDO::PARAM_INT));
    return $ans;
}
function getUserDataValue($uid, $name) {
    $ans = "";
    switch($name) {
        case "createPosts":
        case "publicAccess": {
            $data = getUserData($uid);
            if (array_key_exists($name, $data))
                $ans = $data[$name];
            else
                $ans = false;
            break;
        }
        default: {
            $username = getUsernameFromID($uid);
            $cert = getCert($uid);
            $ans = WPValue($username, $cert[0], $name);
            break;
        }
    }
    return $ans;
}
function setUserDataValue($uid, $name, $value) {
    $ans = "";
    switch($name) {
        case "createPosts":
        case "publicAccess": {
            $data = getUserData($uid);
            $data[$name] = $value;
            setUserData($uid, $data, true);
            break;
        }
        default: {
            DBG("No such user value: " . $value);
            break;
        }
    }
    return $ans;
}

// =====================================================================
// CATEGORIES
// =====================================================================

function getCategories($uid, $all=false) {
    $ndx = "";
    if(! $all)
        $ndx = "AND ndx<" . MAX_ACTIVE_CATEGORIES;
    $stmt = "SELECT cid,uid,ndx,name FROM ".C_TABLE." WHERE uid=? $ndx ORDER BY ndx";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    return $ans;
}
function setCategories($uid, $rows) {
    $ans = null;
    for($i=0; $i<count($rows); $i++) {
        $name = $rows[$i]['name'];
        if(getCategoryIDFromName($uid, $name, true) == -1) {
            $stmt = "INSERT INTO ".C_TABLE." (uid,name,ndx) VALUES (?,?,?)";
            $ans = setDB($stmt, array($uid,   PDO::PARAM_INT,
                                      $name,  PDO::PARAM_STR,
                                      $i,     PDO::PARAM_INT));
        }
        else {
            $stmt = "UPDATE ".C_TABLE." SET ndx=? WHERE name=?";
            $ans = setDB($stmt, array($i,     PDO::PARAM_INT,
                                      $name,  PDO::PARAM_STR));
        }
    }
    return $ans;
}
function getCategoryNameFromID($cid) {
    $stmt = "SELECT name FROM ".C_TABLE." WHERE cid=?";
    $ans  = getDB($stmt, array($cid, PDO::PARAM_INT));
    return $ans[0][0];
}
function getCategoryIDFromName($uid, $category, $all=false) {
    $ans = getCategories($uid, $all);
    for($i=0; $i<count($ans); $i++) {
        if($ans[$i][C_NAME] == $category) {
            return $ans[$i][C_CID];
        }
    }
    return -1;
}
function createCategory($uid, $name, $i, $mtime) {
    $data = array("modified" => false);
    $jdata = json_encode($data, true);
    $stmt = "INSERT INTO ".C_TABLE." (name,uid,ndx) VALUES (?,?,?)";
    $ans = setDB($stmt, array($name,  PDO::PARAM_STR,
                              $uid,   PDO::PARAM_INT,
                              $i,     PDO::PARAM_INT));
}

// =====================================================================
// PAGES
// =====================================================================

function getPages($uid, $all=false, $data=false) {
    if($all)
        $ndx = "";
    else
        $ndx = "AND ndx<" . MAX_ACTIVE_PAGES;
    if($data)
        $col = ",data";
    else
        $col = "";
    $stmt = "SELECT pid,m_time,uid,ndx,name$col FROM ".P_TABLE." WHERE uid=? $ndx ORDER BY ndx";
    $ans  = getDB($stmt, array($uid, PDO::PARAM_INT));
    return $ans;
}
function setPages($uid, $rows) {
    $ans = null;
    for($i=0; $i<count($rows); $i++) {
        $name = $rows[$i]['name'];
        $mtime = $rows[$i]['m_time'];
        $data = $rows[$i]['data'];
        if(getPageIDFromName($uid, $name, true) == -1) {
            $stmt = "INSERT INTO ".P_TABLE." (uid,name,ndx,m_time,data) VALUES (?,?,?,?,?)";
            $ans = setDB($stmt, array($uid,   PDO::PARAM_INT,
                                      $name,  PDO::PARAM_STR, 
                                      $i,     PDO::PARAM_INT,
                                      $mtime, PDO::PARAM_INT,
                                      $data,  PDO::PARAM_STR));
        }
        else {
            $stmt = "UPDATE ".P_TABLE." SET data=?, ndx=?, m_time=? WHERE name=?";
            $ans = setDB($stmt, array($data,  PDO::PARAM_STR,
                                      $i,     PDO::PARAM_INT,
                                      $mtime, PDO::PARAM_INT,
                                      $name,  PDO::PARAM_STR));
        }
    }
    return $ans;
}
function getPageNameFromID($pid) {
    $stmt = "SELECT name FROM ".P_TABLE." WHERE pid=?";
    $ans = getDB($stmt, array($pid, PDO::PARAM_INT));
    if(count($ans) > 0 && count($ans[0]) > 0)
        return $ans[0][0];
    return "Error";
}
function getPageIDFromName($uid, $page, $all=false) {
    $ans = getPages($uid, $all);
    for($i=0; $i<count($ans); $i++) {
        if($ans[$i][P_NAME] == $page) {
            return $ans[$i][P_PID];
        }
    }
    return -1;
}
function createPage($uid, $name, $i, $mtime) {
    $data = array("modified" => false);
    $jdata = json_encode($data, true);
    $stmt = "INSERT INTO ".P_TABLE." (name,uid,ndx,m_time,data) VALUES (?,?,?,?,?)";
    $ans = setDB($stmt, array($name,  PDO::PARAM_STR, 
                              $uid,   PDO::PARAM_INT,
                              $i,     PDO::PARAM_INT, 
                              $mtime, PDO::PARAM_INT, 
                              $jdata, PDO::PARAM_STR));
}
function getPageMtime($uid, $name) {
    $stmt = "SELECT m_time FROM ".P_TABLE." WHERE uid=? AND name=? ;";
    $ans  = getDB($stmt, array($uid, PDO::PARAM_INT, $name, PDO::PARAM_STR));
    return $ans[0][0];
}
function getPageData($uid, $name) {
    $stmt = "SELECT m_time,data FROM ".P_TABLE." WHERE uid=? AND name=? ;";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT, $name, PDO::PARAM_STR));
    $ans[0][0] += 0; //cast
    return $ans[0];
}
// =====================================================================
//  EVENTS
// =====================================================================

function getEvent($eid) {
    $stmt = "SELECT eid,uid,cid,pid,start,duration,type,data,active FROM ".E_TABLE." WHERE eid=? ;";
    $ans = getDB($stmt, array($eid, PDO::PARAM_INT));
    // cast the ID and TIME values to integers
    if(isset($ans[0][0])) {
        $ans[0][E_EID]      = 0+ $ans[0][E_EID];
        $ans[0][E_START]    = 0+ $ans[0][E_START];
        $ans[0][E_DURATION] = 0+ $ans[0][E_DURATION];
        return $ans[0];
    }
    return null;
}

function getEventsForUser($uid) {
    $stmt = "SELECT eid,uid,cid,pid,start,duration,type,data FROM ".E_TABLE." WHERE uid=? AND active!=FALSE ORDER BY start DESC";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT));
    return $ans;
}
function updateEventsForUser($uid) {
    // get event IDS for all WP media
    $username = getUsernameFromID($uid);
    $cert = getCert($uid);
    $wpids = WPGetMediaIDs($username, $cert[0]);
    $wpids = explode(",", $wpids);

    $pid = getPageIDFromName($uid, "note", true);
    $stmt = "SELECT eid,data FROM ".E_TABLE." WHERE uid=? AND pid=? ORDER BY start DESC";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT, $pid, PDO::PARAM_INT));
    for($i=0; $i<count($ans); $i++) {
        $eid = $ans[$i][0];
        $data = json_decode($ans[$i][1], true);
        $shouldHaveAudio = in_array($ans[$i][0], $wpids);
        // remove the audio tag from all events which no longer have an existing audio file.
        if(isset($data['audio']) && !$shouldHaveAudio) {
            unset($data['audio']);
            $jdata = json_encode($data, true);
            $stmt = "UPDATE ".E_TABLE." set data=? WHERE eid=?";
            $rslt = setDB($stmt, array($jdata, PDO::PARAM_STR, $eid, PDO::PARAM_INT));
            DBG("Removing audio data for event: " . $eid);
        }
        // add the audio tag to all events which have an existing audio file.
        else if(!isset($data['audio']) && $shouldHaveAudio) {
            $url = WPGetMediaURL($username, $cert, $eid);
            $ext = pathinfo($url, PATHINFO_EXTENSION);
            $data['audio'] = $ext;
            $jdata = json_encode($data, true);
            $stmt = "UPDATE ".E_TABLE." set data=? WHERE eid=?";
            $rslt = setDB($stmt, array($jdata, PDO::PARAM_STR, $eid, PDO::PARAM_INT));
            DBG("Adding audio data for event: " . $eid);
        }
    }
}

function queryEventsForUser($uid, $opt) {
    $stmt = "SELECT eid, uid, cid, pid, start, duration, type, data FROM ".E_TABLE." WHERE uid=?";
    $stmt .= " AND active!=FALSE";
    $arr = array($uid, PDO::PARAM_INT);

    if(isset($opt['start'])) {
        $stmt .= " AND start>=?";
        array_push($arr, $opt['start'], PDO::PARAM_INT);
    }
    if(isset($opt['end'])) {
        $stmt .= " AND start<?";
        array_push($arr, $opt['end'], PDO::PARAM_INT);
    }
    if(isset($opt['eid'])) {
        $stmt .= " AND eid=?";
        array_push($arr, $opt['eid'], PDO::PARAM_INT);
    }
    if(isset($opt['cid'])) {
        $stmt .= " AND cid=?";
        array_push($arr, $opt['cid'], PDO::PARAM_INT);
    }
    if(isset($opt['pid'])) {
        $stmt .= " AND pid=?";
        array_push($arr, $opt['pid'], PDO::PARAM_INT);
    }
    if(isset($opt['type'])) {
        $stmt .= " AND type=?";
        array_push($arr, $opt['type'], PDO::PARAM_INT);
    }
    $stmt .= " ORDER BY start DESC";

    // handle "max"
    if(isset($opt['max'])) {
        // We want the most recent events, so apply the limit to the acending series and swap.
        $max = intval($opt['max']);
	    $stmt .= " LIMIT $max";
        //$stmt = "SELECT * FROM (" . $stmt . ") a  ORDER BY a.start DESC LIMIT $max" ;
    }

    $ans = getDBEvents($stmt, $arr);
    //if(isset($opt['max'])) {
    //    $ans = array_reverse($ans);
    //}
    return $ans;
}
function getEventsInCategory($uid, $cid) {
    $stmt = "SELECT eid,uid,cid,pid,start,duration,type,data FROM ".E_TABLE." WHERE uid=? AND cid=? AND active!=FALSE ORDER BY start DESC";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT, $cid, PDO::PARAM_INT));
    return $ans;
}
function getEventsInPage($uid, $pid) {
    $stmt = "SELECT eid,uid,cid,pid,start,duration,type,data FROM ".E_TABLE." WHERE uid=? AND pid=? AND active!=FALSE ORDER BY start DESC";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT, $pid, PDO::PARAM_INT));
    return $ans;
}
function getEventIDsInRange($uid, $start, $end) {
    $stmt = "SELECT eid FROM ".E_TABLE." WHERE uid=? AND start >= ? AND start < ? AND active!=FALSE ORDER BY start DESC";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT, $start, PDO::PARAM_INT, $end, PDO::PARAM_INT));
    return $ans;
}
function getEventsNotInList($uid,$start,$end,$eids) {
    $stmt = "SELECT eid,uid,cid,pid,start,duration,type,data FROM ".E_TABLE." WHERE uid=? AND start >= ? AND start < ? AND active!=FALSE AND (eid NOT IN (?))";
    $ans = getDB($stmt, array($uid, PDO::PARAM_INT, $start, PDO::PARAM_INT, $end, PDO::PARAM_INT, $eids, PDO::PARAM_STR));
    return $ans;
}
function hasEventID($eid) {
    $stmt = "SELECT count(1) FROM ".E_TABLE." WHERE eid=?";
    $ans = getDB($stmt, array($eid, PDO::PARAM_INT));
    return $ans[0][0];
}
function deleteEvent($eid) {
    $stmt = "UPDATE ".E_TABLE." SET active=FALSE WHERE eid=?";
    $ans = setDB($stmt, array($eid, PDO::PARAM_INT));
    return $ans;
}
function deleteEventMedia($username, $cert, $eids) {
    $note_ids = array();
    for($i=0; $i<count($eids); $i++) {
        $e = getEvent($eids[$i]);
        if($e) {
            $event = parseReadableEvent($e);
            if( !strcmp($event['page'],"note") ) {
                array_push($note_ids, $eids[$i]);
            }
        }
    }
    $err = WPDeleteMedia($username, $cert, $note_ids);
    DBG("Deleting event media (" . $note_ids ."): " . $err);
}
function makeIDString($ids) {
    $idString = strval(intval($ids[0]));
    for($i=1; $i<count($ids); $i++)
        $idString .=  "," . strval(intval($ids[$i]));
    return $idString;
}
function changeEventCategory($eids,$cat) {
    $eidString = makeIDString($eids);
    $stmt = "UPDATE ".E_TABLE." SET category=? WHERE eid IN ($eidString)";
    $ans = setDB($stmt, array($cat, PDO::PARAM_STR));
    return $ans;
}
function deleteEvents($eids) {
    $eidString = makeIDString($eids);
    $stmt = "UPDATE ".E_TABLE." SET active=FALSE WHERE eid IN ($eidString)";
    $ans = setDB($stmt, array());
    return $ans;
}
function deleteAllEventsInCategory($uid, $cid) {
    $stmt = "UPDATE ".E_TABLE." SET active=FALSE WHERE uid=? AND cid=?";
    $ans  = setDB($stmt, array($uid, PDO::PARAM_INT, $cid, PDO::PARAM_INT) );
    return $ans;
}
function deleteAllEventsForUser($user) {
    $uid = getIDFromUsername($user);
    $stmt = "UPDATE ".E_TABLE." SET active=FALSE WHERE uid=?";
    $ans  = setDB($stmt, array($uid, PDO::PARAM_INT));
    return $ans;
}
function deleteAllEventsForUID($uid) {
    $stmt = "DELETE FROM ".E_TABLE." WHERE uid=?";
    $ans  = setDB($stmt, array($uid, PDO::PARAM_INT));
    return $ans;
}
function createValidEvent($e) {
    $uid   = $e["uid"];
    $name  = $e["category"];
    $cid = getCategoryIDFromName($uid, $name, true);
    if($cid==-1) {
        $mtime = time()*1000;
        createCategory($uid, $name, 10, $mtime);
    }
    return createEvent($e);
}
function createEvent($e) {
    global $DBconnect, $DBuser, $DBpass, $DBopts;
    $eid      = isset($e["eid"]) ? $e["eid"] : 0; 
    $uid      = $e["uid"];
    $start    = $e["start"];
    $duration = $e["duration"];
    $type     = $e["type"];
    $data     = json_encode($e["data"], true);
    $cid      = getCategoryIDFromName($uid, $e["category"], true);
    if($cid==-1)
        $cid = getCategoryIDFromName($uid, "Uncategorized", true);
    $pid      = getPageIDFromName($uid, $e["page"], true);

    if($eid && $eid > 0)
        $statement = "INSERT INTO ".E_TABLE." (eid,uid,cid,pid,start,duration,type,data) VALUES " .
            "(:eid, :uid, :cid, :pid, :start, :duration, :type, :data) ON DUPLICATE KEY UPDATE " .
            "uid=:uid, cid=:cid, pid=:pid, start=:start, duration=:duration, type=:type, data=:data, active=1 ";
    else
        $statement = "INSERT INTO ".E_TABLE." (uid,cid,pid,start,duration,type,data) VALUES " .
            "(:uid, :cid, :pid, :start, :duration, :type, :data)";
    try {
        $dbh = new PDO($DBconnect, $DBuser, $DBpass, $DBopts);
        $stmt = $dbh->prepare($statement);
        if($eid && $eid > 0)
            $stmt->bindParam(':eid',  $eid,      PDO::PARAM_INT);
        $stmt->bindParam(':uid',      $uid,      PDO::PARAM_INT);
        $stmt->bindParam(':cid',      $cid,      PDO::PARAM_INT);
        $stmt->bindParam(':pid',      $pid,      PDO::PARAM_INT);
        $stmt->bindParam(':start',    $start,    PDO::PARAM_INT);
        $stmt->bindParam(':duration', $duration, PDO::PARAM_INT);
        $stmt->bindParam(':type',     $type,     PDO::PARAM_STR, 128);
        $stmt->bindParam(':data',     $data,     PDO::PARAM_STR, 128*1024);
        $stmt->execute();
        $eid = $dbh->lastInsertId();
        return 0+ $eid;

    }
    catch (PDOException $e) {
        DBG( "Error!: " . $e->getMessage() );
    }
    return -1;
}
function parseEvent($event) {
    $rslt["eid"]      = 0+ $event[E_EID];
    $rslt["uid"]      = 0+ $event[E_UID];
    $rslt["cid"]      = 0+ $event[E_CID];
    $rslt["pid"]      = 0+ $event[E_PID];
    $rslt["start"]    = 0+ $event[E_START];
    $rslt["duration"] = 0+ $event[E_DURATION];
    $rslt["type"]     = $event[E_TYPE];
    $rslt["data"]     = json_decode($event[E_DATA], true);
    $rslt["category"] = getCategoryNameFromID($rslt["cid"]);
    $rslt["page"]     = getPageNameFromID($rslt["pid"]);
    return $rslt;
}
function parseReadableEvent($event) {
    $rslt["id"]       = $event["eid"];
    $rslt["start"]    = $event["start"];
    $rslt["duration"] = $event["duration"];
    $rslt["type"]     = $event["type"];
    $rslt["data"]     = $event["data"];
    $rslt["category"] = getCategoryNameFromID($event["cid"]);
    $rslt["page"]     = getPageNameFromID($event["pid"]);
    return $rslt;
}
function parseReadableArray ($events) {
    $rslt = array();
    for($i=0; $i<count($events); $i++) {
        $rslt[$i][0] = 0+ $events[$i][E_EID];
        $rslt[$i][1] = 0+ $events[$i][E_START];
        $rslt[$i][2] = 0+ $events[$i][E_DURATION];
        $rslt[$i][3] = getCategoryNameFromID($events[$i][E_CID]);
        $rslt[$i][4] = getPageNameFromID($events[$i][E_PID]);
        $rslt[$i][5] = $events[$i][E_TYPE];
        $rslt[$i][6] = json_decode($events[$i][E_DATA], true);
    }
    return $rslt;
}


// =====================================================================
// DB (CREATION, DELETION, QUERIES)
// =====================================================================

function createDB() {
    $sql = "CREATE TABLE IF NOT EXISTS ".U_TABLE." (               ".
        "uid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,             ".
        "m_time BIGINT(16) DEFAULT NULL,                           ".
        "username VARCHAR(45) NOT NULL UNIQUE,                     ".
        "cert VARCHAR(45) DEFAULT NULL,                            ".
        "certExpiration BIGINT(16) DEFAULT NULL,                   ".
        "data LONGBLOB,                                            ".
        "PRIMARY KEY (uid)                                         ".
        "  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1   ";
    queryDB( $sql );                                               
                                                                   
    $sql = "CREATE TABLE IF NOT EXISTS ".P_TABLE." (               ".
        "pid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,             ".
        "uid INT(11) NOT NULL,                                     ".
        "ndx INT(11) NOT NULL,                                     ".
        "m_time BIGINT(16) DEFAULT NULL,                           ".
        "name VARCHAR(45) DEFAULT NULL,                            ".
        "data LONGBLOB,                                            ".
        "PRIMARY KEY (pid)                                         ".
        "  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1  ";
    queryDB( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".C_TABLE." (               ".
        "cid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,             ".
        "uid INT(11) NOT NULL,                                     ".
        "ndx INT(11) NOT NULL,                                     ".
        "name VARCHAR(45) DEFAULT NULL,                            ".
        "PRIMARY KEY (cid)                                         ".
        "  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1  ";
    queryDB( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".E_TABLE." (               ".
        "eid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,             ".
        "uid INT(11) NOT NULL,                                     ".
        "cid INT(11) NOT NULL,                                     ".
        "pid INT(11) NOT NULL,                                     ".
        "start BIGINT(16) DEFAULT NULL,                            ".
        "duration BIGINT(16) DEFAULT NULL,                         ".
        "type VARCHAR(45) DEFAULT NULL,                            ".
        "data LONGBLOB,                                            ".
        "active TINYINT(1) NOT NULL DEFAULT 1,                     ".
        "PRIMARY KEY (eid)                                         ".
        "  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1  ";
    queryDB( $sql );
}

function deleteDB() {
    // remove everything from the DB
    queryDB( "DROP TABLE IF EXISTS " .U_TABLE );
    queryDB( "DROP TABLE IF EXISTS " .C_TABLE );
    queryDB( "DROP TABLE IF EXISTS " .P_TABLE );
    queryDB( "DROP TABLE IF EXISTS " .E_TABLE );
}

// This method does not use bound variables, and should not be used
// for any user-supplied parameters.
function queryDB($statement) {
    $ans = array();
    global $DBconnect, $DBuser, $DBpass, $DBopts;
    try {
        $dbh = new PDO($DBconnect, $DBuser, $DBpass, $DBopts);
        $stmt = $dbh->prepare($statement);
        $ans = $stmt->execute();
    }
    catch (PDOException $e) {
        DBG( "Error 1: " . $e->getMessage() );
    }
    return $ans;
}


function getDB($statement, $arr) {
    $ans = array();
    global $DBconnect, $DBuser, $DBpass, $DBopts;
    //DBG($DBconnect . $DBuser . $DBpass );
    try {
        $dbh = new PDO($DBconnect, $DBuser, $DBpass, $DBopts);
        $stmt = $dbh->prepare($statement);
        // bind parameter values and types.
        for($i=0; $i < count($arr)/2; $i ++) {
            $stmt->bindParam($i+1, $arr[$i*2], $arr[$i*2+1]);
        }

        // call the stored procedure
        $stmt->execute();

        $ans = $stmt->fetchAll();
    }
    catch (PDOException $e) {
        DBG( "Error 2: " . $e->getMessage() );
    }
    return $ans;
}

function getDBEvents($statement, $arr) {
    $ans = array();
    global $DBconnect, $DBuser, $DBpass, $DBopts;
    //DBG($DBconnect . $DBuser . $DBpass );
    try {
        $dbh = new PDO($DBconnect, $DBuser, $DBpass, $DBopts);
        $stmt = $dbh->prepare($statement);

        // bind parameter values and types.
        for($i=0; $i < count($arr)/2; $i ++) {
            $stmt->bindParam($i+1, $arr[$i*2], $arr[$i*2+1]);
        }

        // call the stored procedure
        $stmt->execute();
        $eid = $uid = $cid = $pid = $start = $duration = $type = $data = null;
        $stmt->bindColumn('eid',      $eid,      PDO::PARAM_INT);
        $stmt->bindColumn('uid',      $uid,      PDO::PARAM_INT);
        $stmt->bindColumn('cid',      $cid,      PDO::PARAM_INT);
        $stmt->bindColumn('pid',      $pid,      PDO::PARAM_INT);
        $stmt->bindColumn('start',    $start,    PDO::PARAM_INT);
        $stmt->bindColumn('duration', $duration, PDO::PARAM_INT);
        $stmt->bindColumn('type',     $type,     PDO::PARAM_STR, 128);
        $stmt->bindColumn('data',     $data,     PDO::PARAM_STR, 128 * 1024);

        $index = 0;
        while ($row = $stmt->fetch(PDO::FETCH_BOUND)) {
            $ans[$index][E_EID]      = $eid;
            $ans[$index][E_UID]      = $uid;
            $ans[$index][E_CID]      = $cid;
            $ans[$index][E_PID]      = $pid;
            $ans[$index][E_START]    = $start;
            $ans[$index][E_DURATION] = $duration;
            $ans[$index][E_TYPE]     = $type;
            $ans[$index][E_DATA]     = $data;
            $index = $index + 1;
        }
    }
    catch (PDOException $e) {
        DBG( "Error 3: " . $e->getMessage() . $statement );
    }
    return $ans;
}


function setDB( $statement, $arr ) {
    $ans = array();
    global $DBconnect, $DBuser, $DBpass, $DBopts;
    try {
        $dbh = new PDO($DBconnect, $DBuser, $DBpass, $DBopts);
        $stmt = $dbh->prepare($statement);
        // bind parameter values and types.
        for($i=0; $i < count($arr)/2; $i ++) {
            $stmt->bindParam($i+1, $arr[$i*2], $arr[$i*2+1]);
        }
        // call the stored procedure
        $ans = $stmt->execute();
    } catch (PDOException $e) {
        DBG( "Error 4: " . $e->getMessage() . $statement );
    }
    return $ans;
}

// =====================================================================
// Printing routines
// =====================================================================

function printHTMLHeader($FORM) {
    setPGCookie($FORM);
    header("Access-Control-Allow-Origin: *");
    //header('Access-Control-Allow-Headers', 'Content-type, Accept, X-Requested-With');
    header("Content-type: text/html");
}

function printResult($data) {
    header("Access-Control-Allow-Origin: *");
    //header('Access-Control-Allow-Headers', 'Content-type, Accept, X-Requested-With');
    header("Content-type: text/html");
    print($data);
    exit(0);
}

function printHTMLResult($data) {
    $html  = "<html><head></head><body><p>";
    $html .= $data;
    $html .= "</p></body></html>";
    printResult($html);
}
function printJSResult($data) {
    $url = "https://psygraph.com/webclient";
    $html  = "<html><head>";
    $html .= "<script src='".$url."/js/jquery-2.1.4.min.js'></script>";
    $html .= "<script src='".$url."/js/vis.min.js'></script>";
    $html .= "<link rel='stylesheet' type='text/css' href='".$url."/css/vis.min.css'>";
    $html .= "<link rel='stylesheet' type='text/css' href='page.css'>";
    $html .= "</head><body><p>";
    $html .= $data;
    $html .= "</p></body></html>";
    printResult($html);
}

function printArray($data) {
    global $FORM;
    setPGCookie($FORM);
    header("Access-Control-Allow-Origin: *");
    header("Content-type: application/json");
    $jdata = json_encode($data, true);
    print($jdata);
    exit(0);
}

function printLoginFail($FORM, $text = "Either your username or password was incorrect...") {
    setPGCookie($FORM);
    header("Content-type: text/html");

    print <<<ENDOFHERE
    <html>
	<head><title>Authentication Fail</title>
        <link rel="stylesheet" type="text/css" id="base_style" href="css/psygraph.css">
        <noscript><h2>Error: Javascript must be enabled.</h2></noscript>
	</head>
	<body>
	<h2>Login Failure</h2>
	<p>$text</p>
    <p><a href='javascript:history.back()'>Go back</a></p>
	</body>
    </html>
ENDOFHERE;
    exit(0);
}

function printError($FORM) {
    $page = $FORM["page"];
    print <<<ENDOFHERE

    You have requested an invalid page: $page 

ENDOFHERE;
}

function header_status($statusCode) {
    static $status_codes = null;
    if ($status_codes === null) {
        $status_codes = array (
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            301 => 'Moved Permanently',
            303 => 'See Other',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            415 => 'Unsupported Media Type',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            507 => 'Insufficient Storage'
            );
    }
    if ($status_codes[$statusCode] !== null) {
        $status_string = $statusCode . ' ' . $status_codes[$statusCode];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status_string, true, $statusCode);
    }
}


?>
<?php

// only allowed to reference http, since we will be dispatching all of our
// commands to the server via post.
require_once("../util.php");
require_once("./http.php");
require_once("./testParams.php");

$params = getParams($argv);
$tempDir = $params['tempDir'];

print("Testing date\n");
$d  = "2015-07-08 22:14:48.9";
//$d2 = "2015-07-08 22:14:48.9";
$t  = unformatTime($d);
//$t2 = unformatTime($d2);
$d3 = formatTime($t);
//check($t, $t2);
check(1436393688900, $t);
check("2015-07-08 22:14:48.900", $d3);

sdiff("2015-07-08 22:14:48.900", "2015-07-08 22:14:48.901", 0);

print("Deleting events\n");
$out = test_command($params, "deleteEvents");
check("Deleting events for test...\nDeleted.\n", $out);

print("Testing deletion\n");
$out = test_output($params, "csv");
check("id,start,duration,category,page,type,data\n", $out);

print("Upload the test.csv dataset to the server\n");
$good_file = "good_test.csv";
$out = test_input($params, "csv", $good_file);
check("", $out);

print("Testing CSV round trip\n");
$test_file = test_output($params, "csv");
checkFile($good_file, $test_file, $params); // we don't write over good_test.csv

print("Now we have verified the round trip for CSV, check all file types.\n");
testFormat($params, 'html');
testFormat($params, 'csv');
testFormat($params, 'kml');
testFormat($params, 'json');
testFormat($params, 'ics');
//testFormat('rss');

print("Delete the test user entirely\n");
$out = test_command($params, "deleteUser");
check("Deleting user: ". $params['username'] ."...\nDeleted.\n", $out);

// login again, so get a new certificate.
$params = getParams($argv);

print("Testing new user creation in JSON\n");
$test_file = test_output($params, "json");
checkFile("good_newUser.json", $test_file, $params);

/*
print("Upload the 'good_test.csv' dataset to the server\n");
$filename = "good_test.csv";
$out = test_input($params, "csv", $filename);
check("", $out);
*/

print("Upload the 'good_test.json' dataset to the server\n");
$filename = "good_test.json";
$out = test_input($params, "json", $filename);
check("", $out);

print("Double check with CSV.\n");
testFormat($params, 'csv');

print("Double check with JSON.\n");
$out = test_output($params, "json");
checkFile($filename, $out, $params);

// check event selection

print("Testing date queries\n");
//print( convertGMT("2015-07-08 22:14:40.90") );
// Start and end times
$tp = $params;
// We have to convert this time to GMT
//$tp['start'] = "2015-07-08 22:14:40.90";
//$tp['end']   = "2015-07-08 22:14:48.9";
$tp['start'] = convertGMT("2015-07-08 22:14:40.90");
$tp['end']   = convertGMT("2015-07-08 22:14:48.9");
$out = test_output($tp, "csv");
checkFile("good_startEnd.csv", $out, $params, true);

$tp['page']  = "note";
$tp['start'] = "2015-07-01 22:14:40.90";
$tp['end']   = "2015-07-09 22:14:48.9";
$out = test_output($tp, "csv");
checkFile("good_pageStartEnd.csv", $out, $params, true);

print("SUCCESS\n");
exit(0);


// test queries and manipulations on the dataset

$filea  = "files/alec.csv";
$filea2 = "files/alec2.csv";
$fileb  = "files/admin.csv";
$fileb2 = "files/admin2.csv";
system("rm --force $filea $filea2 $fileb $fileb2");

$params["format"] = "csv";
$out = test_output($params, "csv");
//print($out);


//system("./writeEvents url $urlroot username alec cert sregor csv > $filea");
//system("./deleteEvents url $urlroot admin DBpass#1 destroy");
//system("./writeEvents url $urlroot admin DBpass#1 csv > $fileb");
//
//
//// verify that $fileb is empty
//print("=================== file b =============\n");
//system("cat $fileb");
//system("rm $fileb");
//
//system("cat $filea | ./readEvents admin DBpass#1");
//system("./writeEvents admin DBpass#1 csv > $fileb");
//
//
//// verify that $filea is equal to $fileb
//print("=================== diff file a,b =============\n");
//system("cat $filea | cut -d, -f 2-7 > $filea2");
//system("cat $fileb | cut -d, -f 2-7 > $fileb2");
//system("diff $filea2 $fileb2");



function checkFile($old, $new, $params, $skipIds=false, $exit=false) {
    print("Comparing old: $old to new: $new\n");
    if(check($old, $new, $skipIds, $exit)) { // if there was an error...
        if( $params['force'] ) {
            $cmd = "cp $new $old";
            print("Executing cmd: $cmd\n");
            exec($cmd);
            exit(0);
        }
        else
            exit(1);
    }
}

function check($desired, $actual, $skipIds=false, $exit=true) {
    if(file_exists($desired))
        $old = file_get_contents($desired);
    else
        $old = $desired;
    if(file_exists($actual))
        $new = file_get_contents($actual);
    else
        $new = $actual;
    if($old != $new) {
        $s = sdiff($old, $new, $skipIds);
        if(count($s)) {
            print("+++++++++++++++++++++ ERROR ++++++++++++++++++++++++\n");
            print("OLD: \n" . $s['old'] . "\n");
            print("NEW: \n" . $s['new'] . "\n");
            print("\n");
            if($exit)
                exit(1);
            else
                return 1;
        }
    }
    return 0;
}

function sdiff($old, $new, $skipIds=false) {
    $from_start = strspn($old ^ $new, "\0");        
    $from_end = strspn(strrev($old) ^ strrev($new), "\0");

    $old_end = strlen($old) - $from_end;
    $new_end = strlen($new) - $from_end;

    $start = substr($new, 0, $from_start);
    $end = substr($new, $new_end);
    $new_diff = substr($new, $from_start, $new_end - $from_start);  
    $old_diff = substr($old, $from_start, $old_end - $from_start);

    $i=0; 
    $j=0;
    while($new_diff && count($new_diff) > $i && numeric($new_diff[$i]))
        $i++;
    while($old_diff && count($old_diff) > $j && numeric($old_diff[$j]))
        $j++;
    if(($i || $j) && $skipIds) { // skip this numerical difference, hopefully its an ID.
        return sdiff( substr($old_diff,$j), substr($new_diff,$i), $skipIds);
    }
    
    $new = "$start<INS>$new_diff</INS>$end";
    $old = "$start<DEL>$old_diff</DEL>$end";

    if(!$new_diff && !$old_diff)
        return array();
    else
        return array("old"=>$old, "new"=>$new);
}

function numeric($c) {
    if( is_numeric($c) || 
        ($c >= "a" && $c <= 'f') )
        return true;
    else
        return false;
}

function testFormat($params, $ext) {
    $user = $params['username'];
    $out = test_output($params, $ext);
    $userFile = "good_" .$user ."." .$ext;
    print("Testing format $ext\n");
    checkFile($userFile, $out, $params, true);
}

?>
<?php

require_once("../pg.php");


if($argc ==1) {
    $params = readConfig();
}
else if($argc == 3) {
    $username = $argv[1];
    $password = $argv[2];
    $params = readConfig($username, $password);
}
else if($argc == 4) {
    $username = $argv[1];
    $password = $argv[2];
    $server   = $argv[3];
    $params = readConfig($username, $password, $server);
}
else {
    print_r($argv);
    printUsage();
    exit(0);
}


$fn1 = "../pgConfig.xml";
$fn2 = "../../pgConfig.xml";
if(file_exists($fn1)) {
    print("\nSettings file: ".$fn1."\n");
    print("Settings:\n");
    print_r($params);
}
elseif(file_exists($fn2)) {
    print("\nSettings file: ".$fn2."\n");
    print("Settings:\n");
    print_r($params);
}
else {
    print("\nCould not access file: ".$fn2."\n");
    printUsage();
}
exit(0);

function printUsage() {
    print("\nUsage: php pgConfig [USERNAME PASSWORD [SERVER]]\n");
    print("e.g. php pgConfig admin mypass https://psygraph.com\n");
    print("where the WP user 'admin' has administrative privileges.\n\n");
}

?>
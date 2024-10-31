<?php

$tempDir = sys_get_temp_dir();
$filename = $tempDir . "/psygraph_errorLog.txt";

system("more " . $filename);

$file = fopen($filename, 'w');
if($file) {
    fprintf($file, "\n");
    fclose($file);
}

?>

<?php


function do_post($params, $url, $use_json) {
    if($use_json) {
        $content = json_encode($params);
    }
    else {
        $content = http_build_query($params);
    }
    
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => $content
        ),
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result;
}

function test_command($params, $cmd) {
    $params['action'] = $cmd;
    $urlroot = $params['url'];
    $url = $urlroot . "/command.php";
    $result = do_post($params, $url, 0);
    return $result;
}

function test_output($params, $format) {
    $urlroot = $params['url'];
    $url = $urlroot . "/output.php";
    $params['format'] = $format;
    $result = do_post($params, $url, 0);
    $filename = tempnam($params['tempDir'], 'pg_');
    file_put_contents($filename, $result);
    return $filename;
}

function test_input($params, $format, $filename) {
    $urlroot = $params["url"];
    $url = $urlroot . "/input.php";
    $params['format'] = $format;
    //$params['respond'] = 1;
    $cfile = curl_file_create(realpath($filename), 'text/plain', $filename);
    $fileParams = array('pgFile'=>$cfile);
    $post = array_merge($params, $fileParams);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $result = curl_exec($ch);
    curl_close ($ch);
    return $result;
}

function test_wp_page($params) {
    $urlroot = $params['url'];
    $url = $urlroot . "/wp.php";
    $result = do_post($params, $url, 0);
    return $result;
}

function test_server($params) {
    $urlroot = $params['url'];
    $url = $urlroot . "/server.php";
    $result = do_post($params, $url, 1);
    return $result;
}

?>

<?php

require_once( "pg_db.php" );

function pg_getCurrentUsername() {
    $current_user     = wp_get_current_user();
    $current_username = $current_user->user_login;
    return $current_username;
}

function pg_getPageUsername() { // return the user's page
    $username = "foobar";
    if(get_query_var('pg_username')) {
        $username = get_query_var('pg_username');
        if($username == "current")
            $username = pg_getCurrentUsername();
    }
    #else {
    #    $post_id = get_the_ID();
    #    $author_id = get_post_field( 'post_author', $post_id );
    #    $user = get_user_by('id', $author_id);
    #    $username = $user->user_login;
    #}
    return $username;
}
function pg_isOwner() {
    return pg_getPageUsername() == pg_getCurrentUsername();
}
function pgUser_publicAccess() {
    return pg_query( pg_getPageUsername(), "publicAccess");
}
function pgUser_createPosts() {
    return pg_query( pg_getPageUsername(), "createPosts" );
}

// [pg_link] return a link
function pg_linkShortcode( $atts ) {
    $username = pg_getPageUsername();
    $cert     = pg_getCert($username);
    //return $cert;
    // set defaults
    $atts = shortcode_atts( array(
                                'username' => "$username",
                                'cert'     => "$cert",
                                'linktext' => "here",
                                'format'   => "",
                                'max'      => "",
                                'start'    => "",
                                'end'      => "",
                                'id'       => "",
                                'page'     => "",
                                'type'     => "",
                                'interval' => "none"
                                ), $atts );
    
    // generate a link to a file
    $url = "<a href=\"";
    $url .= pg_serverUrl() . "/output.php";
    $url .= "?server="   . urlencode(pg_serverUrl());
    $url .= "&format="   . $atts['format'];
    $url .= "&username=" . $atts['username'];
    if(pg_isOwner())
        $url .= "&cert="     . $atts['cert'];
    $url .=  "\">"       . $atts['linktext'] . "</a>";
    return $url;
}

// [pg_events] return an html-formatted list of events
function pg_eventsShortcode( $atts ) {
    $username = pg_getPageUsername();
    $cert     = pg_getCert($username);

    // use WP timezone
    $min    = 60 * get_option('gmt_offset');
    $sign   = $min < 0 ? "-" : "+";
    $absmin = abs($min);
    $tz     = sprintf("%s%02d:%02d", $sign, $absmin/60, $absmin%60);
    
    // set defaults
    $atts = shortcode_atts( array(
                                'username' => "$username",
                                'cert'     => "$cert",
                                'max'      => "",
                                'start'    => "",
                                'end'      => "",
                                'id'       => "",
                                'page'     => "",
                                'category' => "",
                                'type'     => "",
                                'display'  => "",
                                'tz'       => $tz,
                                'signal'   => "events",
                                'height'   => "640px",
                                'width'    => "100%"
                                ), $atts );

    $atts['server']   = pg_serverUrl();
    $atts['embedded'] = true;
    $height   = $atts['height'];
    $width    = $atts['width'];

    if($atts['display'] == "graph") {
        return generatePage("graph", $atts, $height, $width);
    }
    else if($atts['display'] == "map") {
        return generatePage("map", $atts, $height, $width);
    }
    else if($atts['display'] == "bar") {
        return generatePage("bar", $atts, $height, $width);
    }
    else if($atts['display'] == "list") {
        $response = generateEvents($atts);
        return embedResponse($response, $height, $width);
    }
    else {
        return "<p>Unknown display type (".$atts['display'].") requested.</p>";
    }
}

// [pg_page] return a page
function pg_pageShortcode( $atts ) {
    $username = pg_getPageUsername();
    $cert     = pg_getCert($username);

    // set defaults
    $atts = shortcode_atts( array(
                                'page'     => "client",
                                'format'   => "csv",
                                'username' => "$username",
                                'cert'     => "$cert",
                                'limit'    => 4,
                                'height'   => "640px",
                                'width'    => "100%"
                                ), $atts );

    $username = $atts['username'];
    $cert     = $atts['cert'];
    $page     = $atts['page'];
    $limit    = $atts['limit'];
    $height   = $atts['height'];
    $width    = $atts['width'];


    if($page == "input") {
        $format = $atts['format'];
        return generateInput($username, $cert, $format);
    }
    else if($page == "audio") {
        return generateAudio($username);
    }
    else if($page == "client") {
        $username = pg_getCurrentUsername();
        $cert     = pg_getCert($username);
        return generatePsygraph($username, $cert, $height, $width);
    }
    else if($page == "user") {
        return generateUser($username);
    }
    else if($page == "posts") {
        $response = generatePosts($username, $limit);
        return embedResponse($response, $height, $width);
    }
    else if($page == "admin") {
        $username = pg_getCurrentUsername();
        $cert     = pg_getCert($username);
        return generateAdmin($username, $cert, $height, $width);
    }
    else {
        return "<p>Unknown page requested: $page</p>";
    }
}

function generatePsygraph($username, $cert, $height, $width) {
    $server = pg_serverUrl();
    $url  = "https://psygraph.com/webclient/wp.php";
    $url .= "?username=" . urlencode($username);
    // OK to use cert here, since it is only for the current_user.
    $url .= "&cert="     . urlencode($cert);
    $url .= "&server="   . urlencode($server);
    
    // the iframe will get resized to 100% if its ID remains psygraph
    $response = '<iframe id="psygraph" src="'.$url.'" height="'.$height.'" width="'.$width.'" allowfullscreen="true"></iframe>';
    return $response;
}

function generateUser($username) {
    $fn = __DIR__ . "/user_page.html";
    $content = "<p>Could not load file: 'user_page.html'.</p>";
    if( file_exists($fn) ) {
        $content = file_get_contents($fn);
    }
    return $content;
}

function generateAudio($username) {
    $text = "";
    if(pg_isOwner() || pgUser_publicAccess()) {
        $ids = pg_getAllMediaIDs($username);
        $text = implode(",", $ids);
    }
    $content  = '<div style="div {margin-left: 40px;}" class="wpview-clipboard" contenteditable="true">';
    $content .= do_shortcode( '[playlist ids="' . $text . '"]');
    $content .= '</div>';
    return $content;
}

function generateEvents($atts) {
    $server = pg_serverUrl();
    $url    = $server . "/output.php";
    // write atts to the URL
    $atts['format']   = "html";
    if(!pg_isOwner()) {
        unset($atts['cert']);
    }
    $response = post_request($url, $atts);
    return $response;
}

function generatePage($displayType, $atts, $height, $width) {
    $response = "";
    $server   = pg_serverUrl();
    $url      = $server . "/output.php";
    if(!strcmp("graph", $displayType) || !strcmp("bar", $displayType)) {
        // get the data in a JSON string for JS embedding
        $atts['format'] = "json";
        $data = post_request($url, $atts);

        $canvas = makeID();
        $display = $atts['display'];
        if($data!="") {
            $response .= "  <div>";
            $response .= "    <div id=".$canvas."></div>";
            $response .= "  </div>";
            $response .= "  <script>";
            $response .= "      var data = '".$data."';";
            $response .= "      pg_init();";
            $response .= "      pg_drawJSON(data, '$display', '$canvas', '$height', '$width');";
            $response .= "      pg_initializeCanvas('$canvas', '$height', '$width')";
            $response .= "  </script>";
        }
        else {
            $response .= "No response to post: " . $url;
        }
    }
    else if(!strcmp("map", $displayType)) {
        $canvas = makeID();
        $atts['format'] = "kml";
        $url .= "?username=".$atts['username'];
        foreach ($atts as $key => $value) {
            switch($key) {
                case 'height':
                case 'width':
                case 'cert':
                case 'max':
                case 'start':
                case 'end':
                case 'id':
                case 'page':
                case 'category':
                case 'type':
                case 'format':
                    $url .= "&" .$key ."=" .urlencode($value);
                    break;
                default:
            }
        }

        $center = getMapCenter($url);

        $response .= "      <div><div id=".$canvas."></div></div>";
        $response .= "      <script src='https://maps.googleapis.com/maps/api/js?v=3.exp'></script>";
        $response .= "      <script>";
        $response .= "      function initialize() {";
        $response .= "          pg_initializeCanvas('$canvas', '$height', '$width');";
        $response .= "          var chicago = new google.maps.LatLng($center);";
        $response .= "          var mapOptions = { ";
        $response .= "            'zoom': 11, ";
        $response .= "            'center': chicago ";
        $response .= "          };";
        $response .= "          var map = new google.maps.Map(document.getElementById('$canvas'), mapOptions);";
        $response .= "          var ctaLayer = new google.maps.KmlLayer({url: '$url' });";
        $response .= "          ctaLayer.setMap(map);";
        $response .= "      }";
        $response .= "      google.maps.event.addDomListener(window, 'load', initialize);";
        $response .= "      </script>";
    }
    else {
        $response = "<p>You must supply a valid display type</p>";
    }
    return $response;

}

function makeID() {
    return bin2hex(openssl_random_pseudo_bytes(4));
}

function getMapCenter($url) {
    // return the first point of a line.  Or perhaps Chicago
    $center = "41.875696, -87.624207";
    //$fp = fopen($url, 'r');
    //if($fp) {
    //    while($line = fgets($fp)){
    //        if(trim($line) == "<coordinates>") {
    //            $ll = explode(",", trim(fgets($fp)));
    //            $center = $ll[0] . ", " . $ll[1];
    //            break;
    //        }
    //    }
    //    fclose($fp);
    //}
    return $center;
}

function embedResponse($response, $height, $width) {
    $resp  = "<div class='pg_embedded' style='height: $height; width: $width;'>";
    $resp .= $response;
    $resp .= "</div>";
    return $resp;
}

function generatePosts($username, $limit) {
    $posts = '';
    $user = get_user_by('login', $username);
    $author_id = $user->ID;
    $args = array(
        'author'        => $author_id,
        'post_type'     => 'post',
        'post_status'   => 'any',
        'category_name' => 'Psygraph'
    );
    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        return "<p>No posts for user: ".$username."</p>";
    }
    while ($query->have_posts() && $limit--) {
        $post = $query->the_post();
        $image = $title = $date = $excerpt = $content = $category = '';

        if (has_post_thumbnail()) {
            $image = '<span class="image">' . get_the_post_thumbnail(get_the_ID()) . '</span><br/>';
        }
        $title = '<span class="title"><b>Title:</b> ' . get_the_title() . '</span><br/>';
        $content = '<div class="content">' . apply_filters('the_content', get_the_content()) . '</div><br/>';
        $date = ' <span class="date"><b>Date:</b> ' . get_the_date() . '</span><br/>';
        /*
        $terms = get_the_terms(get_the_ID());
        $term_output = array();
        foreach ($terms as $term)
            $term_output[] = '<a href="' . get_term_link($term) . '">' . $term->name . '</a>';
        $category = ' <span class="category-display"><b>Category:</b> ' . implode(', ', $term_output) . '</span><br/>';
        */
        $posts .= "<div class='post'>" . $image . $title . $date . $category . $content . "</div>";
    }
    wp_reset_postdata();

    return $posts;
}

function generateInput($username, $cert, $format) {
    $url = pg_serverUrl() . "/input.php";
    $response  = '<div id="pgUpload">';
    $response .= '<form action="'.$url.'" method="post" enctype="multipart/form-data">';
    $response .= '<label for="file">Filename: </label>';
    $response .= '<input type="file"   name="pgFile"                     id="pgFile"/>';
    $response .= '<br/><br/>';
    $response .= '<input type="hidden" name="respond"  value="true"      id="respond"/>';
    $response .= '<input type="hidden" name="format"   value="'.$format.'"   id="format"/>';
    $response .= '<input type="hidden" name="username" value="'.$username.'" id="user"/>';
    if(pg_isOwner())
        $response .= '<input type="hidden" name="cert"     value="'.$cert.'"     id="cert"/>';
    $response .= '<input type="submit" name="submit" value="Submit"/>';
    $response .= '<br/>';
    $response .= '</form>';
    $response .= '</div>';
    return $response;
}

function generateAdmin($username, $cert, $height, $width) {
    $response = "";
    if(pg_isOwner()) {
        $server = pg_serverUrl();
        $url  = $server . "/admin.php?username=" . urlencode($username);
        $url .= "&cert="     . urlencode($cert);
        $url .= "&server="   . urlencode($server);
        
        $response .= '<iframe id="pg_admin" src="'.$url.'" height="'.$height.'" width="'.$width.'" allowfullscreen="true"></iframe>';
    }
    else {
        $response .= '<p>You must be the logged-in owner of this page in order to make administrative changes.</p>';
    }
    return $response;
}

function pg_query($username, $query) {
    $url = pg_serverUrl() . "/admin.php";    
    $atts['query']    = $query;
    $atts['username'] = $username;
    // PHP post requests were disabled by hostgator.  lame.
    //$resp = post_request($url, $atts);
    $resp = get_request($url, $atts);
    $s = json_decode($resp, true);
    return $s[$query];
}

function post_request($url, $data, $optional_headers = null, $getresponse = true) {
    $data = http_build_query($data);
    $proto = "http";
    //if(preg_match("/https/", $url))
    //    $proto = "https";
    $params = array($proto => array(
        'method' => 'POST',
        'content' => $data,
        'header'=> "Content-type: application/x-www-form-urlencoded\r\n"
        . "Content-Length: " . strlen($data) . "\r\n",
        //'header' => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL
    ));
    
    if ($optional_headers !== null) {
        $params[$proto]['header'] = $optional_headers;
    }
    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        return "";
    }
    if ($getresponse){
        $response = stream_get_contents($fp);
        return $response;
    }
    return "";
}
function get_request($url, $data, $optional_headers = null, $getresponse = true) {
    $data = http_build_query($data);
    $url .= "?".$data;
    $proto = "http";
    //if(preg_match("/https/", $url))
    //    $proto = "https";
    $params = array($proto => array(
        'method' => 'GET'
        //'header' => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL
    ));
    
    if ($optional_headers !== null) {
        $params[$proto]['header'] = $optional_headers;
    }
    $ctx = stream_context_create($params);
    $fp  = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        return "";
    }
    if ($getresponse){
        $response = stream_get_contents($fp);
        return $response;
    }
    return "";
}


?>
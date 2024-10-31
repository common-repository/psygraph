<?php 

//require_once("pg_db.php");

// Add a settings link for the plugin
function pg_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=Psygraph">Settings</a>'; 
    array_unshift($links, $settings_link); 
    return $links;
}

// add the admin options page
function pg_settings_add_page() {
    add_options_page('Psygraph', 'Psygraph', 'manage_options', 'Psygraph', 'pg_settings_overview');
}
// add the admin settings and such
function pg_settings_init() {
    // Check that the user is allowed to update options
    //if (!current_user_can('manage_options')) {
    //    wp_die('You do not have sufficient permissions to access this page.');
    //}
    register_setting('psygraph_options', 'psygraph_options', 'pg_settings_validate' );
    add_settings_section('psygraph_main', 'Admin Settings', 'pg_settings_mainText', 'Psygraph');
    //add_settings_field('psygraph_page', 'Psygraph page name:', 'pg_settings_page', 'Psygraph', 'psygraph_main');
    add_settings_field('psygraph_createPosts', 'Create posts:', 'pg_settings_createPosts', 'Psygraph', 'psygraph_main');    
    add_settings_field('psygraph_deletePosts', 'Delete posts:', 'pg_settings_deletePosts', 'Psygraph', 'psygraph_main');
    add_settings_field('psygraph_postStatus', 'Post status:', 'pg_settings_postStatus', 'Psygraph', 'psygraph_main');
    add_settings_field('psygraph_upload', 'Media upload limit:', 'pg_settings_upload', 'Psygraph', 'psygraph_main');
}

// display the admin options page
function pg_settings_overview() {
    $page = pg_settingsValue("page");
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $page_url      = get_site_url() ."/". $page ."/". $username;    
    $page_url_safe = get_site_url() ."/?pagename=psygraph_template&pg_username=". $username;    
    print '<div>';
    print '<h2>Psygraph</h2>';
    print "<p>The Psygraph plugin integrates with the Psygraph mobile app (an app that tracks your meditation, breathing, and mindfulness) to visualize your data in WordPress.</p>";
    print "<p>The plugin shortcodes do things like generate progress charts, show the history of meditation sessions, and allow playback of recorded audio notes.</p>";
    print "<p>The ability to download psygraph data is governed by the setting on the following page: <a href=\"$page_url_safe\">$page_url</a> .</p>";
    print '<form action="options.php" method="post">';
    settings_fields('psygraph_options');
    do_settings_sections('Psygraph');
    print '<hr/>';
    print '<input name="Submit" type="submit" value="Save Changes" />';
    print '</form>';
    print '<br/><br/><br/><hr/>';
    print '<p>For more information, visit <a href="http://psygraph.com">http://psygraph.com</a></p>';
    print '</div>';
}
// validate our options
function pg_settings_validate($input) {
    $options = get_option('psygraph_options');

    // disallow slashes or percents in the page name
    //if(!preg_match('/^[^\%\/]*$/', $input['page'])) {
    //    $options['page'] = 'pguser';
    //}
    //else {
    //    $options['page'] = trim($input['page']);
    //}
    // make sure the post status matches an option
    $options['postStatus'] = "draft";
    $allOpts = array("publish","private","pending","draft");
    foreach($allOpts as $opt) {
        if(! strcmp($opt, $input['postStatus'] ))
            $options['postStatus'] = $opt;
    }

    // nothing to check for the following checkboxes
    $options['upload'] = 0;
    $allOpts = array(0,1,2,4,8,16,32,64,128,256,512,1024);
    foreach($allOpts as $opt) {
        if(! strcmp($opt, $input['upload'] ))
            $options['upload'] = $opt;
    }
    $options['createPosts'] = isset($input['createPosts']);
    $options['deletePosts'] = isset($input['deletePosts']);
    return $options;
}

// main section
function pg_settings_mainText() {
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $feed = get_site_url() ."/?feed=rss2&pg_username=". $username;    

    print "<p>The 'Create posts' checkbox creates posts out of notes in the Psygraph app.  The 'Delete posts' checkbox will delete them from wordpress when they are deleted from the app.</p>";
    print "<p>The 'Post status' field allows you to set the initial status of all created posts.</p>";
    print "<p>The 'Allow media uploads' checkbox uploads Psygraph audio notes to WordPress.  The audio is associated with the post, and stored as MP4 audio in the WordPress media folder.  The RSS feed (podcast URL) corresponding to that feed is available as: <a href=\"$feed\">$feed</a> .</p>";
    print "<hr/>";
}
function pg_settings_page() {
    $page = pg_settingsValue("page");
    echo "<input id='psygraph_page' name='psygraph_options[page]' size='40' type='text' value='".$page."' />";
}
function pg_settings_createPosts() {
    $checked = pg_settingsValue("createPosts") ? "checked" : "";
    echo "<input id='psygraph_createPosts' name='psygraph_options[createPosts]' type='checkbox' $checked />";
}
function pg_settings_deletePosts() {
    $checked = pg_settingsValue("deletePosts") ? "checked" : "";
    echo "<input id='psygraph_deletePosts' name='psygraph_options[deletePosts]' type='checkbox' $checked />";
}
function pg_settings_postStatus() {
    $value = pg_settingsValue("postStatus");
    echo "<select id='psygraph_postStatus' name='psygraph_options[postStatus]' />";
    $options = array("publish","private","pending","draft");
    foreach($options as $opt) {
        if(! strcmp($opt, $value))
            echo "<option selected>" . $opt . "</option>";
        else
            echo "<option>" . $opt . "</option>";
    }
    echo "</select>";
}
function pg_settings_upload() {
    //$checked = pg_settingsValue("upload") ? "checked" : "";
    //echo "<input id='psygraph_upload' name='psygraph_options[upload]' type='checkbox' $checked />";
    $value = pg_settingsValue("upload");
    echo "<select id='psygraph_upload' name='psygraph_options[upload]' />";
    $options = array(0,1,2,4,8,16,32,64,128,256,512,1024);
    foreach($options as $opt) {
        if(! strcmp($opt, $value))
            echo "<option selected>" . $opt . "</option>";
        else
            echo "<option>" . $opt . "</option>";
    }
    echo "</select>";}

// =====================================================================
// Manage the options and user settings
// =====================================================================

function pg_settingsValue($name, $options = null) {
    if($options==null)
        $options = get_option('psygraph_options');
    switch($name) {
    case "page":
        $ans  = "pguser";//isset($options['page']) ? $options['page'] : "pguser";
    break;
    case "postStatus": 
        $ans  = isset($options['postStatus']) ? $options['postStatus'] : "draft";
    break;
    case "upload":
        $ans  = isset($options['upload']) ? $options['upload'] : 0;
        break;
    case "createPosts":
        $ans  = isset($options['createPosts']) && $options['createPosts'];
        break;
    case "deletePosts":
        $ans  = isset($options['deletePosts']) && $options['deletePosts'];
        break;
    default:
        $ans = "";
    }
    return $ans;
}

?>

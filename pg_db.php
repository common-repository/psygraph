<?php

// The methods in this file manipulate a few entires in a DB table.
// For each user, we store a certificate and some settings data.

function pg_getTableName() {
    global $wpdb;
    $table_name = $wpdb->prefix . "psygraph";
    return $table_name;
}
function pg_url() {
    return plugins_url() . "/psygraph";
}
function pg_serverUrl() {
    return pg_url() . "/pg";
}

// =====================================================================
// Manage the user certificate
// =====================================================================
function pg_getCert($username) {
    global $wpdb;
    $table_name = pg_getTableName();

    $cert = $wpdb->get_var( "SELECT cert FROM $table_name WHERE username='$username'" );
    if(! pg_verifyCert($username, $cert)) {
        $cert = pg_createCert($username);
    }
    return $cert;
}
function pg_getCertTime($username) {
    global $wpdb;
    $table_name = pg_getTableName();

    $time = $wpdb->get_var( "SELECT time FROM $table_name WHERE username='$username'" );
    return $time;
}
function pg_createCert($username) {
    global $wpdb;
    $table_name = pg_getTableName();

    // create a new certificate valid for 48 hours
    $cert = bin2hex(openssl_random_pseudo_bytes(10));
    $time = time() +(48 *60 *60);
    $sql = "INSERT INTO $table_name (username, cert, time) VALUES ('$username', '$cert', '$time') ON DUPLICATE KEY UPDATE cert='$cert', time='$time' ;";
    $wpdb->query($sql);
    return $cert;
}
function pg_verifyCert($username, $cert) {
    global $wpdb;
    $table_name = pg_getTableName();

    $dbCert = $wpdb->get_var( "SELECT cert FROM $table_name WHERE username='$username';" );
    $dbTime = $wpdb->get_var( "SELECT time FROM $table_name WHERE username='$username';" );
    
    if( $dbTime > time()) {
        if($dbCert == $cert)
            return true;
    }
    // We could generate a new certificate, there may be a cacheing issue.
    // instead, we take this value and overwrite the cert downstream if necessary.
    //if($cert)
    //    pg_createCert($username);
    return false;
}

// =====================================================================
// Create a single post (template) that exposes Psygraph data for each user
// =====================================================================

function pg_createTemplate() {
    // create the client page
    $user = wp_get_current_user();
    $fn = __DIR__ . "/psygraph_template.html";
    $content = "<p>Could not load file: 'psygraph_template.html'.</p>";
    if( file_exists($fn) ) {
        $content = file_get_contents($fn);
    }
    $post = array (
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'post_author'    => $user->ID,
        'post_name'      => "psygraph_template",
        'post_title'     => "Psygraph Data",
        'post_status'    => 'pending',
        'post_type'      => 'page',
        'post_content'   => $content
        );
    $page = pg_getTemplate();
    if($page) {
        $post['ID'] = $page; 
        wp_update_post( $post );
    }
    else {
        $postID = wp_insert_post($post);
    }
}
function pg_deleteTemplate() {
    $page = pg_getTemplate();
    if($page)
        wp_delete_post($page);
}
function pg_getTemplate() {
    $page = get_page_by_title("Psygraph Data", OBJECT, 'page');
    if($page)
        return $page->ID;
    return 0;
}
// =====================================================================
// Functions related to user media and posts
// =====================================================================
function pg_updateMediaID($username, $id) {
    //$filepath = get_attached_file($id);
    //$attach = get_post( $id );
    //$parent = get_post( $attach->post_parent );
    $user = get_user_by('login', $username);
    $author_id = $user->ID;
    $post = array();
    $post['ID'] = $id;
    $post['post_author'] = $author_id;//$parent->post_author;
    wp_update_post( $post );
}
// get event ID's for all media files stored for the user.
function pg_getAllEventIDs($username) {
    $ids = pg_getAllMediaIDs($username);
    $eids = array();
    foreach ($ids as $id) {
        $filename = get_attached_file($id);
        $info = pathinfo($filename);
        $filename = basename($filename, '.' . $info['extension']);
        $filename = preg_replace("/pg\D*/", "", $filename);
        $eids[] = intval($filename);
    }
    return $eids;
}
// get media ID's for all media files stored for the user.
function pg_getAllMediaIDs($username) {
    $ids = array();
    $user = get_user_by('login', $username);
    $author_id = $user->ID;
    $args = array(
        'author' => $author_id,
        'post_type' => 'attachment',
        'post_status' => 'inherit'
    );
    $query = new WP_Query( $args );
    while ( $query->have_posts() ) {
        $query->the_post();
        $ids[] = get_the_ID();
    }
    return $ids;
}
// get the number of existing uploaded media for a user
function pg_numUploadedMedia($username) {
    $ids = pg_getAllMediaIDs($username);
    return count($ids);
}
// get media ID's for all media files stored for the user.
function pg_getAllPostIDs($username) {
    $ids = array();
    $user = get_user_by('login', $username);
    $author_id = $user->ID;
    $args = array(
        'author' => $author_id,
        'post_type' => 'post',
        'post_status' => 'any'
    );
    $query = new WP_Query( $args );
    while ( $query->have_posts() ) {
        $query->the_post();
        $ids[] = get_the_ID();
    }
    return $ids;
}
// get the media ID corresponding to a particular event ID. 
function pg_getMediaID($username, $eid) {
    $ids = pg_getAllMediaIDs($username);
    foreach ($ids as $id) {
        $filename = basename(get_attached_file($id));
        if( $filename == "pg_" . $username ."_". $eid . ".wav" ||
            $filename == "pg_" . $username ."_". $eid . ".m4a")
            return $id;
    }
    return 0;
}
// get the post ID corresponding to a particular event ID. 
function pg_getPostID($username, $eid) {
    $ids = pg_getAllPostIDs($username);
    foreach ($ids as $id) {
        $event = get_post_meta($id, "event_id", true);
        if(!strcmp($eid, $event))
            return $id;
    }
    return 0;
}

function pg_getCategory($cat_name) {
    $cat_id = get_cat_ID($cat_name);
    if(!$cat_id) {
        $cat_id = wp_create_category($cat_name);
    }
    return $cat_id;
}

function pg_createPost($user_id, $eid, $attachment_id, $title, $text, $category) {
    $cat_id = pg_getCategory($category);
    $pg_id  = pg_getCategory("Psygraph");
    $post_status = pg_settingsValue("postStatus");
    $post = array (
        //'comment_status' => "closed",
        //'ping_status'    => "closed",
        'post_author'    => $user_id,
        'post_name'      => $title,
        'post_title'     => $title,
        'post_category'  => array($cat_id, $pg_id),
        'post_status'    => $post_status,
        'post_type'      => "post",
        'post_content'   => $text
    );
    $enc_value = "";
    if($attachment_id) {
        $url  = wp_get_attachment_url($attachment_id);
        $filename = get_attached_file($attachment_id);
        $size = filesize($filename);
        $filetype = wp_check_filetype(basename($filename));
        $type = $filetype['type'];
        $enc_value .= $url;
        $enc_value .= "\n";
        $enc_value .= $size;
        $enc_value .= "\n";
        $enc_value .= $type;
    }
    $post_id = wp_insert_post($post);
    if(!$post_id) {
        return "Could not create post";
    }
    else {
        add_post_meta($post_id, "event_id", $eid, true);
        if($enc_value != "")
            add_post_meta($post_id, "enclosure", $enc_value, true);
        // attach the media to the post.
        if($attachment_id) {
            $attachment = array();
            $attachment['ID'] = $attachment_id;
            $attachment['post_parent'] = $post_id;
            wp_update_post( $attachment );
        }
    }
    return "";
}

// =====================================================================
// Handle plugin installation and removal
// =====================================================================
function pg_activate() {
    global $wpdb;
    $table_name = pg_getTableName();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      username varchar(45) NOT NULL PRIMARY KEY,
      cert varchar(45) DEFAULT NULL,
      time int(11) DEFAULT NULL
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
    $wpdb->query( $sql );

    pg_createTemplate();

    // Create a settings file for local installs of the pg server
    // Make sure it is chmod 0600
    $params = getPGPrefs(pg_wp_getVars());
    $creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());
    if ( ! WP_Filesystem($creds) ) {
        return;
    }
    global $wp_filesystem;
    $wp_filesystem->put_contents(__DIR__."/pgConfig.xml", $params, (0600 & ~ umask()) ); //WP_PLUGIN_DIR."/psygraph/pgConfig.xml"
}

function pg_deactivate() {
}
function pg_uninstall() {
    global $wpdb;
    $table_name = pg_getTableName();

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $wpdb->query( "DROP TABLE IF EXISTS ".$table_name .";");

    pg_deleteTemplate();
}

function getPGPrefs($arr) {
    $str = "<methodResponse><params>".
        "<param><value><struct>".
        "<member><name>DBhost</name><value><string>".$arr['DBhost']."</string></value></member> ".
        "<member><name>DBport</name><value><int>".$arr['DBport']."</int></value></member>".
        "<member><name>DB</name><value><string>".$arr['DB']."</string></value></member>".
        "<member><name>DBuser</name><value><string>".$arr['DBuser']."</string></value></member>".
        "<member><name>DBpass</name><value><string>".$arr['DBpass']."</string></value></member>".
        "<member><name>WPurl</name><value><string>".$arr['WPurl']."</string></value></member>".
        "</struct></value></param>".
        "</params></methodResponse>";
    return $str;
}

// =====================================================================
// If a user deletes their WP account, delete all media files they uploaded using Psygraph.
// =====================================================================

function pg_deleteUserCB($userID) {
    global $wpdb;
    $table_name = pg_getTableName();
    
    // delete the user from the database
    $user = get_userdata($userID);
    $username = $user->user_login;
    $sql = "DELETE FROM $table_name WHERE username='$username'";
    $wpdb->query($sql);
    
    // delete all media for the user
    $ids = pg_getAllEventIDs($username);
    foreach ($ids as $id) {
        if ( false === wp_delete_attachment( $id ) ) {
            // Log failure to delete attachment.
        }
    }
    /*
    $postID = pg_getUserPostID($username);
    if(!$postID) 
        return;
    $attachments = get_posts( array(
                                  'post_type'      => 'attachment',
                                  'posts_per_page' => -1,
                                  'post_status'    => 'any',
                                  'post_parent'    => $postID
                                  ) );
    foreach ( $attachments as $file ) {
        if ( false === wp_delete_attachment( $file->ID ) ) {
            // Log failure to delete attachment.
        }
    }
    wp_delete_post($postID);
    */
}


?>
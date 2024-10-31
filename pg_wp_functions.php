<?php

require_once("pg_settings.php");

function pg_wp_getVars() {
    $host      = explode(":", DB_HOST);
    $DBhost    = $host[0];
    $DBport    = count($host)>1 ? $host[1] : 3306;
    $DB        = DB_NAME;
    $DBuser    = DB_USER;
    $DBpass    = DB_PASSWORD;
    $WPurl     = get_site_url();
    return array( 'DBhost' => $DBhost, 
                  'DBport' => $DBport, 
                  'DB'     => $DB, 
                  'DBuser' => $DBuser, 
                  'DBpass' => $DBpass, 
                  'WPurl'  => $WPurl);
}

function pg_wp_authenticate($username, $password, $cap) {
    $user = wp_authenticate( $username, $password);
    if( is_wp_error($user) ) {
        return array("success" => false,
                     "reason"  => "bad password");
    }
    if(! user_can($user, $cap) ) {
        // admin privleges required.
        return array("success" => false,
                     "reason"  => "User cannot " . $cap);
    }
    return array("success" => true,
                 "cert"    => pg_getCert($username),
                 "time"    => pg_getCertTime($username));
}
function pg_wp_certify($username, $cert, $cap) {
    if(!pg_verifyCert($username, $cert)) {
        return false;
    }
    $user = get_user_by('login', $username);
    if( is_wp_error($user) ) {
        return false;
    }
    if(! user_can($user, $cap) ) {
        // more privleges required.
        return false;
    }
    return true;
}
function pg_wp_checkUser($username) {
    $id = username_exists($username);
    if($id)
        return $id;
    else
        return 0;
}
function pg_wp_getValue($username, $cert, $name) {
    $value = "";
    $user = get_user_by('login', $username);
    // user is already verified by this time
    //if( is_wp_error( $user ) ) {
    //    return "Error: no such user\n";
    //}
    $userdata = get_userdata($user->ID);
    if( $name == "email") {
        $value = $userdata->user_email;
    }
    elseif( $name == "firstName") {
        $value = $userdata->user_firstname;
    }
    elseif( $name == "lastName") {
        $value = $userdata->user_lastname;
    }
    elseif( $name == "displayName") {
        $value = $userdata->display_name;
    }
    else {
        $value = "Error: unknown value";
    }
    return $value;
}

function pg_wp_getMediaURL($username, $id) {
    $url = "File Not Found";
    $media_id = pg_getMediaID($username, $id);
    if($media_id)
        $url = wp_get_attachment_url($media_id);
    return $url;
}

function pg_wp_getMediaIDs($username) {
    $media_ids = pg_getAllEventIDs($username);
    return implode(",", $media_ids);
}

function pg_wp_uploadMedia($username, $eid, $filename, $fileSrc, $title, $text, $category) {
    $user = get_user_by('login', $username);
    // The user must exist
    if(!$user) {
        return "Could not find WordPress account with username: ". $user;
    }
    // The user must be able to publish content
    if(! user_can($user->ID, "publish_posts") ) {
        return "User ".$username." is not permitted to publish posts.";
    }
    
    $attachment_id = pg_getMediaID($username, $eid);
    $post_id       = pg_getPostID($username, $eid);
    $num_uploads   = pg_numUploadedMedia($username);

    // try uploading the media attachment
    if(!$attachment_id && $filename!="")
    {
        if($num_uploads >= pg_settingsValue("upload")) {

            return "User ".$username." has reached the file upload limit.";
        }
        if(user_can($user->ID, "upload_files")) {
            return "User ".$username." is not permitted to upload files.";
        }
        $filetype = wp_check_filetype($filename);
        $type = $filetype['type'];
        //if($type=="audio/mpeg")
        //    $type = "audio/m4a";
        $array = array( //array to mimic $_FILES
            'name'     => $filename,
            'type'     => $type,
            'tmp_name' => $fileSrc,
            'error'    => 0,
            'size'     => filesize($filename)
        );
        $postID = 0;//pg_getUserPostID($username, true);
        $desc = "";
        $post_data = array();
        $post_data['post_author'] = $user->ID;
        //$overrides = array('mimes' => array('m4a' => 'audio/mp4') );
        $attachment_id = media_handle_sideload($array, $postID);//, $desc, $post_data);
        
        if( is_wp_error($attachment_id) ) {
            // There was an error uploading the media.
            return "Error uploading media (".$filename."): ". $attachment_id->get_error_message();
        } else {
            pg_updateMediaID($username, $attachment_id);
        }
    }

    if(! $post_id && 
        $text != "" && 
        pg_settingsValue("createPosts")
    ) {
        if($attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            $text = str_replace("PG_MEDIA_URL", $url, $text);
        }
        else {
            $text = str_replace("PG_MEDIA_URL", "(File not found: check permissions)", $text);
        }
        $err = pg_createPost($user->ID, $eid, $attachment_id, $title, $text, $category);
        if($err != "")
            return $err;
    }
    return "OK";
}

function pg_wp_deleteMedia($username, $eid_string) {
    if(! pg_settingsValue("deletePosts"))
        return "post deletion is disabled.";
    $success = "";
    $eids = explode(',', $eid_string);
    for($i=0; $i<count($eids); $i++) {
        $eid = $eids[$i];
        $media_id = pg_getMediaID($username, $eid);
        if($media_id) {
            $success .= wp_delete_attachment($media_id, false) ? "Deleted audio ".$media_id." " : "";
        }
        $post_id = pg_getPostID($username, $eid);
        if($post_id) {
            $success .= wp_delete_post($post_id, false) ? "Deleted post ".$post_id." " : "";
        }
    }
    return $success;
}

?>
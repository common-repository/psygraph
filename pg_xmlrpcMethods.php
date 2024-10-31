<?php

/**
 * Filters the XMLRPC methods to allow just checking the login/pass of
 * a given users
 */


function pg_xmlrpcMethods($methods)
{
    $methods['pg.getServerVars'] = 'pg_getServerVars';
    $methods['pg.serverURL']     = 'pg_serverUrl';
    $methods['pg.checkUser']     = 'pg_checkUser';
    $methods['pg.authenticate']  = 'pg_authenticate';
    $methods['pg.certify']       = 'pg_certify';
    $methods['pg.getValue']      = 'pg_getValue';
    $methods['pg.getMediaURL']   = 'pg_getMediaURL';
    $methods['pg.getMediaIDs']   = 'pg_getMediaIDs';
    $methods['pg.uploadMedia']   = 'pg_uploadMedia';
    $methods['pg.deleteMedia']   = 'pg_deleteMedia';
    return $methods;
}

function pg_getServerVars($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $password = sanitize_text_field( $args[1] );
    // only allow this method when connecting through ssl.
    if(!is_ssl())
        return false;
    // authenticate at the administrator level, since we export DB info.
    $auth = pg_wp_authenticate($username, $password, "create_users");
    if(!$auth['success'])
        return false;
    return pg_wp_getVars();
}

function pg_checkUser($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    return pg_wp_checkUser($username);
}
function pg_authenticate($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $password = sanitize_text_field( $args[1] );
    return pg_wp_authenticate($username, $password, "read");
}
function pg_certify($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $cert     = sanitize_text_field( $args[1] );
    $cap      = sanitize_text_field( $args[2] );
    return pg_wp_certify($username, $cert, $cap);
}
function pg_getValue( $args ) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $cert     = sanitize_text_field( $args[1] );
    $name     = sanitize_text_field( $args[2] );
    if(!pg_verifyCert($username, $cert)) {
        return "Error: invalid certificate\n";
    }
    return pg_wp_getValue( $username, $password, $name );
}
function pg_getMediaURL($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $cert     = sanitize_text_field( $args[1] );
    $id       = intval($args[2]);
    if(!pg_verifyCert($username, $cert))
        return "Invalid credentials for media URL";
    return pg_wp_getMediaURL($username, $id);
}
function pg_getMediaIDs($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $cert     = sanitize_text_field( $args[1] );
    if(!pg_verifyCert($username, $cert))
        return "Invalid credentials for media ID";
    return pg_wp_getMediaIDs($username);
}
function pg_uploadMedia($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username    = sanitize_user( $args[0] );
    $cert        = sanitize_text_field( $args[1] );
    $eid         = sanitize_text_field( $args[2] );
    $filename    = sanitize_text_field( $args[3] );
    $fileSrc     = sanitize_text_field( $args[4] );
    $title       = sanitize_text_field( $args[5] );
    $text        = $args[6]; //htmlspecialchars_decode($args[6]); // decoding done by XMLRPC
    $category    = sanitize_text_field( $args[7] );
    if(!pg_verifyCert($username, $cert))
        return "Invalid credentials for uploading media";
    return pg_wp_uploadMedia($username, $eid, $filename, $fileSrc, $title, $text, $category);
}
function pg_deleteMedia($args) {
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );
    $username = sanitize_user( $args[0] );
    $cert     = sanitize_text_field( $args[1] );
    $id       = intval($args[2]);
    if(!pg_verifyCert($username, $cert))
        return "Invalid credentials for uploading media";
    return pg_wp_deleteMedia($username, $id);
}

?>
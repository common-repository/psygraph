<?php


require_once("pg.php");

$FORM = getHttpParams();
if(!isset($FORM['cert'])) {
    $FORM = handlePublicLogin($FORM);
    if($FORM['uid'] < 0) {
        printLoginFail($FORM, "This user does not allow data to be shared publically");
        exit(1);
    }
}
else {
    $FORM = handleLogin($FORM);
    if($FORM['uid'] < 0) {
        printLoginFail($FORM);
        exit(2);
    }
}

doAction($FORM["action"], $FORM);

exit(0);



function doAction($action, $FORM) {
    
    if($FORM["uid"] < 0) {
        $msg = $FORM["username"];
        printResult("Invalid login: $msg");
    }

    if($action == "uploadFile")
    {
        $username = $FORM['username'];
        $cert     = $FORM['cert'];
        $eid      = $FORM['eid'];
        $filename = $FORM['filename'];
        $title    = $FORM['title'];
        $text     = $FORM['text'];
        $loc      = $FORM['location'];
        $category = $FORM['category'];
        $fileSrc  = $_FILES['mediaFile']['tmp_name'];
        //$fileDest = $fileSrc . ".m4a";
        //move_uploaded_file($fileSrc, $fileDest);
        $rslt = WPUploadMedia($username, $cert, $eid, $filename, $fileSrc, $title, $text, $loc, $category);
        printResult( $rslt );        
    }
    else if($action == "downloadFile")
    {
        $username = $FORM['username'];
        $cert     = $FORM['cert'];
        $id       = $FORM['id'];
        $url      = WPGetMediaURL($username, $cert, $id);
        if($url) {
            httpRedirect($url);
        }
        else {
            updateEventsForUser($FORM["uid"]);
            printResult( "Could not locate file for event: ". $id );
        }
    }
    else if($action == "updateEvents") {
        // remove the audio tag from all events
        // which no longer have an existing audio file.
        updateEventsForUser($FORM["uid"]);
    }
    /* files are deleted by deleting the corresponding event.
    else if($action == "deleteFile")
    { 
        $username = $FORM['username'];
        $cert     = $FORM['cert'];
        $id       = $FORM['id'];
        $output = WPDeleteMedia($username, $cert, array($id) );
        $msg = "";
        if($output)
            $msg = "Successfully deleted file for event: " . $id;
        else
            $msg = "Failed to delete file for event: " . $id;
        printResult( $msg );
    }
    */
    else {
        printResult("Unrecognized action: ". $action);
    }
}

function httpRedirect($url, $permanent = false)
{
    header('Location: ' . $url, true, $permanent ? 301 : 302);
    exit(0);
}

?>

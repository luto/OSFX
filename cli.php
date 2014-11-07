<?php

// if we're invoked by the browser, just end the execution.
// http://stackoverflow.com/a/933375/2486196
if ( php_sapi_name() !== 'cli' ) {
    die();
}

// turn off all warnings for this file, as we always need clean output
error_reporting(0);

require('model/shownotes.php');
require('model/shownote.php');

$source = file_get_contents('php://stdin');

$shownotes = new OSFX\Model\Shownotes();
$shownotes->source = $source;
$shownotes->parse(FALSE);
$shownotes->validate();

// remove the source to save some bytes
$shownotes->source = null;

print(json_encode($shownotes));

<?php
/**
 * VuGen Local Replay tool.
 * This program enables replay of VuGen scripts against localhost during script development.
 * Instructions at: https://github.com/MyLoadTest/VuGenLocalReplay
 */

spl_autoload_register(function ($class) {
    include "classes/".$class.".class.php";
});

// Check local environment setup
VuGenLocalReplayHelper::checkModRewriteEnabled();

// Map all requests to responses
$scriptPath = VuGenLocalReplayHelper::getScriptFolder();
$map = new HttpRequestResponseMap($scriptPath);

// Check that hostnames have been added to hosts file and all web server ports are open/listening
$hosts = $map->hosts();
VuGenLocalReplayHelper::checkHostnamesInHostsFile($hosts);
$ports = $map->ports();
VuGenLocalReplayHelper::checkLocalPorts($ports);

// Lookup the HTTP request in HttpRequestResponseMap, and send the matching response
$request = HttpRequest::createFromSuperglobal();
try {
    $response = $map->lookup($request); // should this throw an exception if it cannot find the corresponding response?
} catch (Exception $e) { // If the request is not found in the map, return an error page.

    // Send the error page
    VuGenLocalReplayHelper::displayErrorPage("Invalid Request",
        "Invalid request. Could not find matching response for HTTP request.".
        (string)$request.
        "");
    // TODO: get first request object, and allow user to submit the valid request.
}
HttpSender::send($response);
exit();

?>
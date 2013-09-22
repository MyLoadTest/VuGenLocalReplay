<?php

spl_autoload_register(function ($class) {
    include "classes/".$class.".class.php";
});

/*
 * The HttpSender class 
 */
class HttpSender
{
    /**
     * Constructor declared private so class can't be instantiated (this is intended to be a 
     * static class.
     */
    private function __construct() {}
    
    /*
     *
     */
    public static function send($httpResponse) {
        if (!($httpResponse instanceof HttpResponse)) {
            throw new InvalidArgumentException("The HttpSender can only send HttpResponse objects.");
        }
        
        // Check that the response file exists
        if (!file_exists($httpResponse->file)) {
            throw new RuntimeException("Could not find response file: {$httpResponse->file}.");
        }

        // Set the HTTP response code
        http_response_code($httpResponse->code);
        
        // Add the HTTP headers
        foreach($httpResponse->headers as $key => $value) {
            header($key.": ".$value);
        }
        
        // Add the Set-Cookie headers
        // Note: should probably use setcookie() for this. Not sure what behaviour for expiring cookies will be.
        foreach($httpResponse->cookies as $value) {
            header("Set-Cookie: ".$value);
        }
        
        // Send the response file (if it exists).
        if ($httpResponse->file == "") {
            flush();
        } else {
            readfile($httpResponse->file); // Reads a file and writes it to the output buffer.
        }

        //VuGenLocalReplayHelper::displayErrorPage("Successful Response",
        //    "HttpResponse object:".
        //    (string)$httpResponse);
    }
}

?>
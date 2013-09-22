<?php

spl_autoload_register(function ($class) {
    include "classes/".$class.".class.php";
});

/*
 * The VuGenLocalReplayHelper class contains functions tath 
 */
class VuGenLocalReplayHelper
{
    /**
     * Constructor declared private so class can't be instantiated (this is intended to be a 
     * static class.
     */
    private function __construct() {}
    
    /**
     * Checks that Apache mod_rewrite is enabled. If it is not, then the program is terminated.
     */
    public static function checkModRewriteEnabled() {
        if (!in_array('mod_rewrite', apache_get_modules())) {
            self::displayErrorPage("mod_rewrite is not enabled",
                "mod_rewrite is not enabled. Find the Apache httdp.conf file, ".
                "and un-comment the \"LoadModule rewrite_module modules/mod_rewrite.so\" line.");
        }
    }

    /**
     * Checks that the list of host names all resolve to localhost (127.0.01.1)
     *
     * @param an array of hostnames/domain names to check
     */
    public static function checkHostnamesInHostsFile($hosts) {
        $numHosts = count($hosts);
        if ($numHosts == 0) {
            throw new InvalidArgumentException("There must be at least one host to check.");
        }
        
        // Check that all hostnames have been added to the hosts file (C:\WINDOWS\system32\drivers\etc\hosts)
        foreach($hosts as $host) {
            if (gethostbyname($host) != '127.0.0.1') {
                self::displayErrorPage("Entries missing from hosts file", 
                    "You must add the following lines to your Windows hosts file<br/>\n".
                    "<a href=\"file://C:/WINDOWS/system32/drivers/etc\">(C:\\WINDOWS\\system32\\drivers\\etc\\hosts</a>)<br/>\n".
                    "<br/>\n".
                    "<textarea rows=\"".($numHosts+2)."\" cols=\"60\" wrap=\"off\">\n".
                    "# Added for VuGen Local Replay (remove after script development is complete)\n".
                    "127.0.0.1       ".implode("\n127.0.0.1       ", $hosts).
                    "</textarea><br/>\n".
                    "<br/>\n".
                    "Note that you must have Administrator rights to edit the hosts file.");
            }
        }
    }
    
    /**
     * Checks that the list of ports are all listening on localhost.
     * Note that this does not check that *Apache* is listening on those ports, just that they are open.
     *
     * @param an array of TCP ports to check
     */
    public static function checkLocalPorts($ports) { // TODO: may have to modify so there are two functions: checkLocalHttpPorts and checkLocalHttpsPorts
        $invalidPorts = array();
        foreach($ports as $port) {
            $con = @fsockopen("localhost", $port, $errno, $errstr, 1); // timeout is 1 second, as local connections should be fast
            if ($con == false) {
               $invalidPorts[] = $port;
            } else {
               fclose($con);
            }
        }
        
        // If any ports are not listening, print instructions on how to reconfigure Apache
        if (count($invalidPorts) != 0) {
            self::displayErrorPage("One or more ports are not listening", 
                "You must add the following lines to your Apache httpd.conf file<br/>\n".
                "<a href=\"file://C:/wamp/bin/apache/Apache2.4.4/conf/httpd.conf\">(C:\\wamp\\bin\\apache\\Apache2.4.4\\conf\\httpd.conf</a>)<br/>\n".
                "<br/>\n".
                "Find the line that says \"Listen 80\", and add the following lines:<br/>\n".
                "<br/>\n".
                "<textarea rows=\"".(count($invalidPorts)+1)."\" cols=\"60\" wrap=\"off\">\n".
                "Listen ".implode("\nListen ", $invalidPorts)."\n".
                "</textarea><br/>\n".
                "<br/>\n".
                "Note that you must restart the Apache server for the changes to take effect. ".
                "If any ports are already in use on localhost, then Apache will fail to start.");
        }
    }
    
    /**
     * Gets the path of the VuGen script containing the recording snapshots.
     *
     * @return a string containing the path to the ./vugen/{ScriptName} folder
     */
    public static function getScriptFolder() {
        $vuGenFolder = dirname(__DIR__)."\\vugen";
        if (!is_dir($vuGenFolder)) {
            throw new RuntimeException("Could not find VuGen script folder: {$vuGenFolder}.");
        }
        
        // Check that there is a VuGen script in the vugen folder
        // Note: there must be only *one* script in the folder.
        $blacklist = [".", "..", "copy_only_one_vugen_script_to_this_folder.php"];
        $folders = scandir($vuGenFolder);
        foreach ($folders as $key => $value) {
            if (in_array($value, $blacklist)) {
                unset($folders[$key]);
            }
        }
        $folders = array_values($folders); // re-index the array
        if (count($folders) != 1) {
            throw new RuntimeException("There must be one (and only one) script in the VuGen script folder: {$vuGenFolder}.");
        }
        $scriptName = $folders[0];
        
        // TODO: check that the script should has a *.usr file and at least one snapshot file.

        return $vuGenFolder."\\".$scriptName;
    }
    
    /**
     * Display an HTTP 500 error page with user-specified content, and then terminate the script.
     *
     * @param the title of the page
     * @param the content of the page
     */
    public static function displayErrorPage($title, $content) {
        http_response_code(500);
        
        require("ErrorPageTemplate.php"); // $html is declared in this file.
        print $html;
    
        exit();
    }
    
}

?>
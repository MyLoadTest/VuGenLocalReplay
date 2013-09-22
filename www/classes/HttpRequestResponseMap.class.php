<?php

spl_autoload_register(function ($class) {
    include "classes/".$class.".class.php";
});

/// Matches HTTP requests to the correct HTTP response
class HttpRequestResponseMap {

    private $requests = array(); // These arrays must have the same number of elements
    private $responses = array();

    /**
     * Constructor for HttpRequestResponseMap
     *
     * @param the path to the VuGen script folder
     */
    public function __construct($scriptFolder) {
        
        $this->readSnapshots($scriptFolder);
        
        /*
        print "requests: ".count($this->requests);
        print "<br/>responses: ".count($this->responses);
        print "<pre>";
        print "responses: ";
        var_dump($this->responses);
        var_dump($this->request);
        print "</pre>";
        */
    }

    /**
     * Get a list of the hostnames that are in the recording snapshots
     *
     * @return an array of hosts that are in the requests/responses
     */
    public function hosts() {
        // Query each HttpRequest object sequentially, and add to a master list.
        $hosts = array();
        foreach ($this->requests as $request) {
            $url = $request->url;
            $hostname = parse_url($url, PHP_URL_HOST);
            if (!in_array($hostname, $hosts)) {
                $hosts[] = $hostname;
            }
        }
        return $hosts;
    }
    
    /**
     * Get a list of the web server ports that are in the recording snapshots
     *
     * @return a list of ports that are in the requests/responses
     */
    public function ports() {
        // Query each HttpRequest object sequentially, and add to a master list.
        // If HTTP is used and no port is specified, then add 80
        // If HTTPS is used and no port is specified, then add 443
        $ports = array();
        foreach ($this->requests as $request) {
            $url = $request->url;
            $port = parse_url($url, PHP_URL_PORT);
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (($scheme == "http") && ($port == null)) {
                $port = 80;
            } else if (($scheme == "https") && ($port == null)) {
                $port = 443;
            }
            if (!in_array($port, $ports)) {
                $ports[] = $port;
            }
        }
        return $ports;
    }

    /**
     * Lookup the HTTP response that matches the HTTP request
     *
     * @param the HttpRequest object to get the expected response for
     * @return the HttpResponse object that corresponds to the HttpRequest object
     */
    public function lookup($request) {
        // Search for the matching HttpResponse object
        for ($i=0; $i<count($this->requests); $i++) {
            if ($this->requests[$i] == $request) {
                break;
            }
        }
        
        // If the matching HttpResponse object was found return it, otherwise throw an exception.
        if ($i == count($this->requests)) {
            //var_dump($this->requests[0]); // only show the first request in the map
            var_dump($this->requests); // show all requests in the map
            throw new RangeException("Matching HTTP response not found");
        } else {
            return $this->responses[$i];
        }
    }
    
    /**
     * Read a snapshot_x.xml file from ./{ScriptName}/data/
     * It would be better to read the *.inf files, as these have links to the snapshot files and the payload files.
     *
     * @param the path to the script folder
     */
    private function readSnapshots($scriptFolder) {
        $snapshotsFolder = $scriptFolder . '/data';
        $infFiles = glob("{$snapshotsFolder}/t*.inf");
        if (($infFiles == false) || (count($infFiles) == 0)) {
            throw new InvalidArgumentException("Could not find any snapshot files in: {$snapshotsFolder}");
        }
        foreach($infFiles as $file) {
            // array_merge(): Merges the elements of one or more arrays together. If the arrays 
            // contain numeric keys, the later value will not overwrite the original value, but will be appended.
            $this->requests = array_merge($this->requests, HttpRequest::createFromSnapshot($file));
            $this->responses = array_merge($this->responses, HttpResponse::createFromSnapshot($file));

            if (count($this->requests) != count($this->responses)) {
                throw new InvalidArgumentException("Number of HTTP requests does not match number of HTTP responses.");
            }

            // TODO: remove 407 HTTP code, re-index the array
        }
    }

}

?>
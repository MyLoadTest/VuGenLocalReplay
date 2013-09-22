<?php

spl_autoload_register(function ($class) {
    include 'classes/' . $class . '.class.php';
});

/*
 * Contains all the parts of the HTTP request
 */
class HttpRequest {
    public $url;
    public $headers = array(); // an associative array with each header in a separate element
    public $body; // a string containing the raw POST body, or an empty string for GET requests.

    /**
     * Constructor for HttpRequest
     *
     * @param an associative array containing the request headers
     * @param a string containing the raw POST body (or an empty string)
     */
    public function __construct($requestUrl, $requestHeaders, $requestBody) {
        $this->url = $requestUrl;
        $this->headers = $requestHeaders;
        $this->body = $requestBody;
        
        //print '<pre>';
        //var_dump($headers);
        //print '</pre>';  
    }

    /*
     * Create a new HttpRequest object from the $_SERVER superglobal.
     * Note that superglobals are set and in-scope as long as this is running on a web server.
     * Call it like:
     *     $request = HttpResponse::createFromSuperglobal();
     *
     * @return An HttpRequest object
     */
    public static function createFromSuperglobal() {
        /*
         * Mandatory $_SERVER elements
         *  - REQUEST_SCHEME    e.g. http, https
         *  - REQUEST_METHOD    e.g. POST, GET, HEAD, PUT
         *  - SERVER_NAME       e.g. www.example.com
         *  - SERVER_PORT       e.g. 80
         *  - REQUEST_URI       e.g. /foo/bar?q=baz
         *
         * Optional $_SERVER elements
         *  - HTTP_REFERER      e.g. www.example.com/foo?q=bar
         *  - HTTP_COOKIE       e.g. foo=bar
         *  - CONTENT_TYPE      e.g. application/x-www-form-urlencoded
         *
         * If the REQUEST_METHOD is POST, then $_POST contains POST values if they were sent as 
         * name/value pairs, but is empty if the browser sent XML or JSON. To get raw POST data, 
         * read the stream "php://input". Note: this stream is not available with 
         * enctype="multipart/form-data".
         */
        $superglobalHeaders = array(); 
        
        // Build URL from mandatory $_SERVER elements
        if (($_SERVER["SERVER_PORT"] == "80") || ($_SERVER["SERVER_PORT"] == "443")) {
            $url = $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        } else {
            $url = $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        }
        
        // Other mandatory $_SERVER elements
        //$requestHeaders["URL"] = $url;
        $requestHeaders["Method"] = $_SERVER["REQUEST_METHOD"];
        $requestHeaders["Host"] = $_SERVER["SERVER_NAME"];
        
        // Optional $_SERVER elements
        if (isset($_SERVER["HTTP_REFERER"])) {
            $requestHeaders["Referer"] = $_SERVER["HTTP_REFERER"];
        } else {
            $requestHeaders["Referer"] = "";
        }
        if (isset($_SERVER["HTTP_COOKIE"])) {
            $requestHeaders["Cookie"] = $_SERVER["HTTP_COOKIE"];
        } else {
            $requestHeaders["Cookie"] = "";
        }
        if (isset($_SERVER["CONTENT_TYPE"])) {
            $requestHeaders["Content-Type"] = $_SERVER["CONTENT_TYPE"];
        } else {
            $requestHeaders["Content-Type"] = "";
        }
        
        // POST values (if applicable)
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $requestBody = file_get_contents("php://input");
        } else {
            $requestBody = "";
        }
        
        // Return a new instance of HttpRequest
        $instance = new self($url, $requestHeaders, $requestBody);
        return $instance;
    }

    /*
     * Create new HttpRequest objects from a snapshot file
     * Call it like:
     *     $requests = HttpResponse::createFromSnapshot($infFile);
     *
     * @param The path/filename of the snapshot tx.inf file
     * @return An array containing one or more HttpRequest objects
     */
    public static function createFromSnapshot($infFile) {
        if (!file_exists($infFile)) {
            throw new InvalidArgumentException("Could not find snapshot file: {$infFile}");
        }
        
        /*
         * Snapshots look like this:
         * [t1] <- matches the name of the file. There will be a separate file for each non-resource
         * FileName1=t1.htm <- non-resource filename for snapshot
         * URL1=http://www.jds.net.au/ <- requested URL
         * Total=42 <- number of resources linked to by this non-resource
         * RequestHeaderFile=t1_RequestHeader.txt <- request headers are saved for all non-resources
         * RequestBodyFile=NONE <- only POSTs will have a request body. But get the POST body from the snapshot.xml file.
         * ResponseHeaderFile=t1_ResponseHeader.txt <-
         * ContentType=text/html <- non-resource MIME type
         * SnapshotXmlFile=snapshot_1.xml <- contains detailed header information
         * FileName2=t1_clientmon.html <- resource filename for snapshot
         * URL2=http://www.jds.net.au/wp-content/themes/jds2012/js/clientmon.js <- URL for resource
         * ...
         * FileName42=t1_style.css <- index is 42. This is the last resource in the inf file
         * URL42=http://www.jds.net.au/wp-content/themes/jds2012/style.css
         */
        $iniSectionName = basename($infFile, ".inf");
        $iniArray = parse_ini_file($infFile, true, INI_SCANNER_RAW); // Note that this will cause an error if any values contain ";". Equals sign is fine due to use of INI_SCANNER_RAW.
        $numRequests = $iniArray[$iniSectionName]["Total"];
        $snapshotXmlFile = dirname($infFile)."/".$iniArray[$iniSectionName]["SnapshotXmlFile"];
        
        /*
         * The Snapshot.xml file may contain multiple reqests/responses in HTTPTask elements if
         * the script was recorded in HTML mode, or a single request/response if URL mode was used.
         * Note that the HTTPTask element for the parent non-resource will contain nested HTTPTask
         * elements for all of its non-resources.
         * 
         * Example XML structure:
         *   HTTPSnapshot (id matches snapshot number, )
         *   +-HTTPTask (hostname, URL, id matches transaction in Generation Log)
         *     +-HTTPRequest (method)
         *     | +-HTTPHeaders
         *     | | +-HTTPHeaderEntity (name of header. This element is repeated for each header.)
         *     | |   +-HTTPDataSet
         *     | |     +-HTTPData
         *     | |       +-ActualData (header value, base64 encoded)
         *     | +-HTTPBody (this section only exists for POSTs)
         *     |   +-HTTPDataSet
         *     |     +-HTTPData
         *     |       +-ActualData (raw POST body, base64 encoded)
         *     +-HTTPResponse
         *     +-HTTPTask (there can be multiple nested HTTPTask nodes. Assume there is ony a single level of nesting.)
         *     | +-HTTPRequest
         *     | +-HTTPResponse
         *     +-HTTPTask
         *         +- etc. etc.
         */
        $instances = array(); // this will hold the HttpRequest objects 
        $httpSnapshot = simplexml_load_file($snapshotXmlFile); // may contain multiple HTTPTasks
        $httpTasks = $httpSnapshot->xpath("//HTTPTask");
        foreach($httpTasks as $httpTask) {
            // Get mandatory HTTP request header elements
            //$requestHeaders["URL"] = (string)$httpTask["url"];
            $url = (string)$httpTask["url"];
            $requestHeaders["Method"] = (string)$httpTask->HTTPRequest["method"];
            $requestHeaders["Host"] = (string)$httpTask["hostname"]; // Could also get this from headers section

            // Get optional HTTP request header elements
            $httpHeaders = $httpTask->HTTPRequest->HTTPHeaders;
            $val = $httpHeaders->xpath("HTTPHeaderEntity[@name='Referer']/HTTPDataSet/HTTPData/ActualData");
            if (!empty($val)) {
                $requestHeaders["Referer"] = base64_decode((string)$val[0]);
            } else {
                $requestHeaders["Referer"] = "";
            }
            $val = $httpHeaders->xpath("HTTPHeaderEntity[@name='Content-Type']/HTTPDataSet/HTTPData/ActualData");
            if (!empty($val)) {
                $requestHeaders["Content-Type"] = base64_decode((string)$val[0]);
            } else {
                $requestHeaders["Content-Type"] = "";
            }
            $val = $httpHeaders->xpath("HTTPHeaderEntity[@name='Referer']/HTTPDataSet/HTTPData/ActualData");
            if (!empty($val)) {
                $requestHeaders["Referer"] = base64_decode((string)$val[0]);
            } else {
                $requestHeaders["Referer"] = "";
            }
            $val = $httpHeaders->xpath("HTTPHeaderEntity[@name='Cookie']/HTTPDataSet/HTTPData/ActualData");
                if (!empty($val)) {
                    $requestHeaders["Cookie"] = base64_decode((string)$val[0]);
                } else {
                    $requestHeaders["Cookie"] = "";
            }
            $val = $httpHeaders->xpath("HTTPHeaderEntity[@name='Content-Length']/HTTPDataSet/HTTPData/ActualData");
            if (!empty($val)) {
                $contentLength = base64_decode((string)$val[0]); // Used on next step. No need to store it in the request object.
            }

            // POST values (if applicable)
            if ( ($requestHeaders["Method"] == "POST") && ($contentLength > 0) ) {
                $requestBody = base64_decode($httpTask->HTTPRequest->HTTPBody->HTTPDataSet->HTTPData->ActualData);
            } else {
                $requestBody = "";
            }
        
            // Create a new instance of HttpRequest
            $instances[] = new self($url, $requestHeaders, $requestBody);
        }
        return $instances;
    }

    /*
     * For HttpRequest objects that have dynamic values, the dynamic value can be replaced with a 
     * regex, and a "fuzzy compare" can be performed.
     * 
     * @param The httpRequest object containing fields that have regex strings in them.
     * @return true if the object matches, otherwise false.
     */
    public function compareRegex($httpRequest) {
        // TODO
    }
    
    /**
     * Gets a string version of the HttpRequest object.
     *
     * @return a printable string representation of the HttpRequest object
     */
    public function __toString() {
        // This is a neat way to get save the output of var_dump to a string 
        // instead of printing it immediately
        ob_start();
        var_dump($this);
        $value = ob_get_contents();
        ob_end_clean();
        
        return $value;
    }
}

?>
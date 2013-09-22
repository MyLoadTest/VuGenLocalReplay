<?php

spl_autoload_register(function ($class) {
    include "classes/".$class.".class.php";
});

/*
 * Contains all the parts of the HTTP response
 */
class HttpResponse {
    public $url; // obviously an HTTP response does not include a URL, but including the request URL is useful for debugging.
    public $code;
    public $headers = array(); // an associative array with each header in a separate element
    public $cookies = array(); // a normal array with each Set-Cookie value in a separate element
    public $file; // a string containing the filename of the requested file (i.e. the response body)

    /**
     * Constructor for HttpResponse
     *
     * @param the URL of the original request
     * @param the numeric HTTP response code to send
     * @param an associative array containing the response headers
     * @param an array containing zero or more Set-Cookie headers
     * @param the path and filename of the file to send back
     */
    public function __construct($requestUrl, $responseCode, $responseHeaders, $responseCookies, $responseFile) {
        $this->url = $requestUrl;
        $this->code = $responseCode;
        $this->headers = $responseHeaders;
        $this->cookies = $responseCookies;
        $this->file = $responseFile;
        
        //print "<pre>";
        //var_dump($this);
        //print "</pre>";  
    }

    /*
     * Create new HttpResponse objects from a snapshot file
     * Call it like:
     *     $response = HttpResponse::createFromSnapshot($snapshotFile);
     *
     * @param The path/filename of the snapshot tx.inf file
     * @return An array containing one or more HttpResponse objects
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
        $iniArray = parse_ini_file($infFile, true, INI_SCANNER_RAW)[$iniSectionName]; // Note that this will cause an error if any values contain ";". Equals sign is fine due to use of INI_SCANNER_RAW.
        $numRequests = $iniArray["Total"];
        $snapshotXmlFile = dirname($infFile)."/".$iniArray["SnapshotXmlFile"];
        
        /*
         * The Snapshot.xml file may contain multiple reqests/responses in HTTPTask elements if
         * the script was recorded in HTML mode, or a single request/response if URL mode was used.
         * Note that the HTTPTask element for the parent non-resource will contain nested HTTPTask
         * elements for all of its non-resources.
         * 
         * Example XML structure:
         *   HTTPSnapshot (id matches snapshot number, )
         *   +-HTTPTask (hostname, URL, id matches transaction in Generation Log)
         *     +-HTTPRequest
         *     +-HTTPResponse (for some strange reason the response does not include the HTTP response code or HTTP version.)
         *     | +-HTTPHeaders
         *     | | +-HTTPHeaderEntity (name of header. This element is repeated for each header.)
         *     | |   +-HTTPDataSet
         *     | |     +-HTTPData
         *     | |       +-ActualData (header value, base64 encoded)
         *     | +-HTTPBody
         *     |   +-HTTPDataSet
         *     |     +-HTTPData
         *     |       +-ActualData (contains nothing useful. Must get file name from tx.inf file)
         *     +-HTTPTask (there can be multiple nested HTTPTask nodes. Assume there is ony a single level of nesting.)
         *     | +-HTTPRequest
         *     | +-HTTPResponse
         *     +-HTTPTask
         *         +- etc. etc.
         */
        $instances = array(); // this will hold the HttpResponse objects 
        $httpSnapshot = simplexml_load_file($snapshotXmlFile); // may contain multiple HTTPTasks
        $httpTasks = $httpSnapshot->xpath("//HTTPTask");
        // Note: The number of HTTPTask elements may not equal the number of requests in the inf file,
        // as the inf fie only contains successful (200) requests, but there will be an HTTPTask element
        // created for *all* HTTP request/response pairs.
        foreach($httpTasks as $httpTask) {
            $requestUrl = (string)$httpTask["url"];
            
            // Get HTTP response code (must get from raw header, as there is no field in the XML for this)
            $actualData = base64_decode($httpTask->HTTPResponse->HTTPHeaders->HTTPAllHeaders->HTTPDataSet->HTTPData->ActualData);
            $match = array();
            preg_match("/^HTTP\/1.1 ([0-9]{3})/", $actualData, $match);
            if (!isset($match[1])) {
                throw new InvalidArgumentException("Could not determine HTTP response code for HTTP response header: {$actualData}");
            }
            $responseCode = $match[1];
            
            // Get HTTP request header elements (skipping the ones that are unnecessary for replay)
            // Note special handling of Set-Cookie headers, which can appear more than once, so 
            // can't be put in the $responseHeaders associative array.
            $responseCookies = array();
            $headersToSkip = [
                "Via", // added when VuGen recording was done via a proxy.
                "Proxy-Connection", // added when VuGen recording was done via a proxy.
                "Date", // better to add the current date/time to the response. 
                "Transfer-Encoding" // Chrome will give "Error code: ERR_INVALID_CHUNKED_ENCODING" if "Transfer-Encoding: chunked" is used.
            ];
            $httpHeaderEntities = $httpTask->xpath("HTTPResponse/HTTPHeaders/HTTPHeaderEntity");
            foreach($httpHeaderEntities as $httpHeaderEntity) {
                $name = (string)$httpHeaderEntity["name"];
                if (in_array($name, $headersToSkip)) {
                    continue;
                }
                $value = base64_decode((string)$httpHeaderEntity->HTTPDataSet->HTTPData->ActualData);
                if ($name == "Set-Cookie") {
                    $responseCookies[] = $value;
                } else {
                    $responseHeaders[$name] = $value;
                }
            }
            
            // For successful requests, lookup the URL/filename in the inf file and get the name of the response file.
            // I am 99% sure that snapshot files are not saved for non-200 HTTP response codes.
            if ($responseCode == 200) {
                $urlKey = array_search($requestUrl, $iniArray); // The URL should only appear once in each inf file
                if ($urlKey != false) {
                    // Get the index from URL{index}, and create FileName{index} key.
                    $match = array();
                    preg_match("/(\d+)$/", $urlKey, $matches);
                    $index = $matches[1];
                    $fileKey = "FileName".$index;
                    $responseFile = dirname($infFile)."/".$iniArray[$fileKey];
                } else {
                    $responseFile = ""; // URL not found in inf file.
                }
            } else {
                $responseFile = ""; // non-200 response code
            }
            
            // Create a new instance of HttpResponse
            $instances[] = new self($requestUrl, $responseCode, $responseHeaders, $responseCookies, $responseFile);
        }
        return $instances;
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
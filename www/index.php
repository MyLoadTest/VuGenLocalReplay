<?php
/**
 * VuGen Local Replay tool.
 * This program enables replay of VuGen scripts against localhost during script development.
 * Instructions at: https://github.com/MyLoadTest/VuGenLocalReplay
 */

// Check that mod_rewrite is enabled
if (!in_array('mod_rewrite', apache_get_modules())) {
    error_page(500, 'mod_rewrite is not enabled. Find the Apache httdp.conf file, and un-comment the "LoadModule rewrite_module modules/mod_rewrite.so" line.');
    exit();
}

// Check that the script folder exists
$vugen_dir = __DIR__ . '/vugen';
if (!is_dir($vugen_dir)) {
    error_page(500, "Could not find directory {$vugen_dir}. Creating directory now. Please try again.");
    mkdir($vugen_dir);
    exit();
}

// Check that there is a VuGen script in the vugen folder
// Note: there must be only one script.
$folders = scandir($vugen_dir); // scandir argument is reletive to index.php, not the requested folder.
if (count($folders) == 2) { // i.e. "." and ".."
    error_page(500, "Please copy a VuGen script to {$vugen_dir}.");
    exit();
} else if (count($folders) > 4) {
    error_page(500, "Only one script may be present at a time in {$vugen_dir}.");
    exit();
} else if (count($folders) == 3) {
    // Script appears to be in the $script_dir. Continue.
    $script_dir = $vugen_dir . '/' . $folders[2];
} else {
    error_page(500, "Something unexpected has happened while checking for scripts in {$vugen_dir}.");
    exit();
}

// Read in the recording snapshots (t1.inf, t2.inf, etc.)
/* 
 * Snapshots look like this:
 * [t1] <- matches the name of the file. There will be a separate file for each non-resource
 * FileName1=t1.htm <- non-resource filename for snapshot
 * URL1=http://www.jds.net.au/ <- requested URL
 * Total=42 <- number of resources linked to by this non-resource
 * RequestHeaderFile=t1_RequestHeader.txt <- request headers are saved for all non-resources
 * RequestBodyFile=NONE <- only POSTs will have a request body
 * ResponseHeaderFile=t1_ResponseHeader.txt <-
 * ContentType=text/html <- non-resource MIME type
 * SnapshotXmlFile=snapshot_1.xml
 * FileName2=t1_clientmon.html <- resource filename for snapshot
 * URL2=http://www.jds.net.au/wp-content/themes/jds2012/js/clientmon.js <- URL for resource
 * FileName3=t1_style.css
 * URL3=http://www.jds.net.au/wp-content/themes/jds2012/style.css
 */
$request_response_mapping = array();
$snapshots_dir = $script_dir . '/data';
$inf_files = glob("{$snapshots_dir}/t*.inf");
if (($inf_files == false) || (count($inf_files) == 0)) {
    error_page(500, "Could not find a recording snapshot in the VuGen script folder: {$snapshots_dir}.");
    exit();
}
foreach($inf_files as $file) {
    $section_name = basename($file, '.inf');
    $ini_array = parse_ini_file($file, true, INI_SCANNER_RAW); // Note that this will cause an error if any values contain ";". Equals sign is fine due to use of INI_SCANNER_RAW.
    foreach($ini_array[$section_name] as $key => $val) {
        if (strncmp($key, 'FileName', 8) == 0) {
            $file_name = $val;
        } else if (strncmp($key, 'URL', 3) == 0) {
            $url = $val;
            $request_response_map[$url] = $file_name;
        }
    }
}

// Figure out which file to retun for the request (may return a 404 if not found)
$request_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
if (array_key_exists($request_url, $request_response_map)) {
    $response_file = $snapshots_dir . '/' . $request_response_map[$request_url];
} else {
    // Note 404 page must be above a certain size () or IE will display a default 404 page instead.
    // TODO: look for the nearest match. The 404 may be due to a dynamic client-side value
    //       Ask if they want to add an exception and return an http 500
    $keys = '';
    foreach (array_keys($request_response_map) as $key) {
        $keys .= $key . '<br />';
    }
    error_page(404, "404 File Not Found Error. Could not find {$request_url}. URLs in map: <br/>{$keys}");
    exit();
}

// Create a lookup table (map) of MIME types
// Note that the Apache mime.types file is included with this project.
$mime_type_map = array();
$mime_file_contents = file_get_contents(__DIR__ . '/mime.types');
foreach(explode("\n", $mime_file_contents) as $line) {
    // Skip rows that are commented out
    if ((isset($line[0])) && ($line[0] == '#')) {
        continue;
    }
    // Split the MIME type column and the file extension column
    $parts = preg_split('/\s+/', $line);
    if (!isset($parts[1])) {
        // Skip empty rows
        continue;
    }
    for ($i=1; $i<count($parts); $i++) {
        // Note that most rows are like: "image/png					png"
        // ...but some are like: "image/jpeg					jpeg jpg jpe"
        if ($parts[$i] == '') {
            continue; // skip empty column for Array([0] => x-conference/x-cooltalk, [1] => ice, [2] => )
        }
        $mime_type_map[$parts[$i]] = $parts[0];   
    }
}

// Look up the correct MIME type (based on file extension).
$url_path = parse_url($request_url, PHP_URL_PATH);
$file_extension = pathinfo($url_path, PATHINFO_EXTENSION);
if ($file_extension == '') {
    //Assume text/html for URLS like http://www.example.com/
    $mime_type = 'text/html';
} else if (array_key_exists($file_extension, $mime_type_map)) {
    $mime_type = $mime_type_map[$file_extension];
} else {
    error_page(500, "Could not fine MIME type for file extension \"{$file_extension}\".");
    exit();
}

// Respond to HTTP request with file contents.
if (file_exists($response_file)) {
    header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
    header("Content-Type: {$mime_type}");
    header("Content-Length: " . filesize($response_file));
    readfile($response_file);
    exit();
} else {
    error_page(500, "Something has gone really wrong. Could not find snapshot file {$response_file} for {$request_url}.");
    exit();
}

/*
 * Returns nicely formatted HTML page containing the specified text
 *
 * Note that this returns a complete HTML page. Functions that print output should not be used
 * before or after this function is called.
 *
 * @param HTTP response code
 * @param the text to insert into the HTML page
 * @return a string containing an HTML page
 */
function error_page($code, $text) {
    http_response_code($code);

    $html = <<<HTML
<html>
  <head>
    <title>VuGen Local Replay: Error Page</title>
    <style>
    </style>
  </head>
  <body>
    <h1>Error: $text</h1>
  </body>
</html>
HTML;

    print($html);
}

?>
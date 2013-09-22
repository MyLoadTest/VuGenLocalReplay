<?php
/*
 * This is HTML template that is used for VuGenLocalReplay status/error pages. 
 * It should ony be referenced from VuGenLocalReplayHelper::displayErrorPage().
 */

$html = <<<HTML
<html>
  <head>
    <title>{$title}</title>
    <style type="text/css">
        body {
          font-family: Sans-Serif;
          background-color: #e1ddd9;
          font-size: 12px;
          color:#564b47;
          padding:20px;
          margin:0px;
          //text-align: center;
        }
        .content {
            text-align: left;
            //vertical-align: middle;
            margin: 0px auto;
            padding: 10px;
            width: 550px;
            min-height: 300px;
            background-color: #ffffff;
            border: 1px dashed #564b47;
            overflow: auto;
        }
        .logo {
            float: right;
            display: table-cell;
            vertical-align: bottom;
        }
    </style>
  </head>
  <body>
    <div class="content">
      <h1>{$title}</h1>
      <p>{$content}</p>
      <!-- Convert an image into a base64 string: http://webcodertools.com/imagetobase64converter/Create -->
      <!--<p><img class="logo" alt="MyLoadTest" src="data:image/png;base64,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" /></p>-->
    </div>
  </body>
</html>
HTML;

?>
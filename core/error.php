<?php
// try to send 503 error
if (!headers_sent()) {
    header("HTTP/1.1 503 Service Unavailable");
    header('Retry-After: 600');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8"/>
    <title>503 Service Temporarily Unavailable</title>
    <link rel="profile" href="http://gmpg.org/xfn/11"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="-1"/>
    <meta http-equiv="Cache-Control" content="no-cache"/>
    <style>
        body {
            color: #0F0F0F;
            font: 15px/20px Helvetica, sans-serif;
        }

        section {
            margin: 40px auto;
            padding: 10px 20px;
            width: 640px;
            border: 1px solid #ccc;
            border-radius: 4px;
            -moz-border-radius: 4px;
            -webkit-border-radius: 4px;
            box-shadow: #ccc 4px 4px 4px;
            -moz-box-shadow: #ccc 4px 4px 4px;
        }

        h1 {
            color: #999;
            font-size: 2em;
            font-weight: 600;
            text-shadow: #777 0 0 1px;
        }

        h2 {
            color: #777;
            font-size: 1.6em;
        }

        pre {
            border-left: 5px solid #aaa;
            color: #888;
            padding-left:5px;
            white-space: pre-wrap;       /* css-3 */
            white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
            white-space: -pre-wrap;      /* Opera 4-6 */
            white-space: -o-pre-wrap;    /* Opera 7 */
            word-wrap: break-word;       /* Internet Explorer 5.5+ */
        }
    </style>
</head>
<body>
<section>
    <h1>Error 503</h1>
    <h2>Server Temporarily Unavailable</h2>
    <?php if (defined('BOLMER_DEBUG') && BOLMER_DEBUG && isset($e)) : ?>
        <?php if (is_array($e)) : ?>
            <p><?=$e['message']?></p>
            <pre><?=$e['file'].'#'.$e['line']?></pre>
        <?php else : ?>
            <p><?=$e->getMessage()?></p>
            <pre><?=$e->getTraceAsString()?></pre>
        <?php endif;?>
    <?php endif;?>
</section>
</body>
</html>
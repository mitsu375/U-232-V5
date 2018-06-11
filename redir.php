<?php
function htmlsafecharsr($txt = '')
{
    $txt = preg_replace("/&(?!#[0-9]+;)(?:amp;)?/s", '&amp;', $txt);
    $txt = str_replace(array(
        "<",
        ">",
        '"',
        "'"
    ) , array(
        "&lt;",
        "&gt;",
        "&quot;",
        '&#38;#039;'
    ) , $txt);
    return $txt;
}
$url = htmlsafecharsr($_GET['url']);
if(strripos($url,$_SERVER['HTTP_HOST'])){
    header('Location:'.urldecode($url));
}
$HTMLOUT = '';
$HTMLOUT .="
<!DOCTYPE html>
<html>
    <head>
    <title>Redirecting to {$url}</title>

    <style>
    body {
        background:#19191B;
        font-family: arial,sans-serif;
        font-size: 12px;
        height: 100%;
        margin: 0;
        color:#CCC
    }
    h1 {
        color:#F00
    }
    .textBubble {
        background:#555;
        border-color: #E6DB55;
     
        font-size: 13px;
        margin-bottom: 3px;
        padding: 10px;
        position: relative;
     
        border-radius: 3px 3px 3px 3px;
        box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;
     
        margin-top: 10px;
        text-align: left;
    }
    .btn{
        background-color: #00A5F0;
        border: 1px solid rgba(0, 0, 0, 0.2);
        border-radius: 5px 5px 5px 5px;
        box-shadow: 0 0 0 1px rgba(20, 20, 20, 0.4) inset, 0 1px #111;
        color: #FFFFFF;
        font-size: 11px;
        font-weight: bold;
        line-height: 30px;
        min-width: 45px;
        padding:10px;
        text-align: center;
        text-shadow: 0 -1px rgba(0, 0, 0, 0.2);
        transition: background-color 0.2s linear 0s;
        text-decoration:none;
    }
    .btn:hover{
        background-color: #24BBFF;
    }
    </style>
    </head>
    <body>
    <div style='width:800px;position:absolute;top:30%;left:50%;margin-left:-400px;text-align:center'>
        <div class='textBubble' style='text-align:left;margin-top:10px'>
            <div>
                <h1>Please be Careful</h1>
                <div class='tx'>For the safety and privacy of your account, remember to never enter your password unless you\'re sure the website your visiting is genuine. Also be sure to only download software from sites you trust. Please also read the Wikipedia articles on <a target='_blank' href='http://en.wikipedia.org/wiki/Malware'>malware</a> and <a target='_blank' href='http://en.wikipedia.org/wiki/Phishing'>phishing</a>.
                <br />
                <br />
                If you still want to continue to this link, then click on button bellow. </div>
                <div style='margin:20px 0;color:orange'>
                    <b></b>
                </div>
                <div style='text-align:right';>
                    <a class='btn' href='/'>Return to Safety</a>
                    <a class='btn' title='{$url}' href='http://www.nullrefer.com/?{$url}'>Continue</a>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
";

echo $HTMLOUT;die;
?>

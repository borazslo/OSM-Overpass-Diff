<?php

function countApacheRunningInstances($pattern) {
    //LoadModule status_module modules/mod_status.so
    //ExtendedStatus On
    if (!@exec("apachectl fullstatus", $status)) {
        return false;
    }

    if (is_array($status) AND count($status) > 10) {
        $c = 0;
        foreach ($status as $line) {
            if (preg_match($pattern, $line))
                $c++;
        }
        return $c;
    }
}

function checkWikipage($title) {
    if ($json = file_get_contents("https://wiki.openstreetmap.org/w/api.php?action=query&titles=" . $title . "&format=json")) {
        $json = json_decode($json);
        foreach ((array) $json->query->pages as $result => $page) {
            if ($result > -1) {
                return "http://wiki.openstreetmap.org/wiki/" . $title;
            }
        }
    }
    return false;
}

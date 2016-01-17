<?php

define('PATH', dirname(__FILE__) . "/");
if (!@include __DIR__ . '/vendor/autoload.php') {
    die('You must set up the project dependencies, run the following commands:
        wget http://getcomposer.org/composer.phar
        php composer.phar install');
}
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem(PATH);
$twig = new Twig_Environment($loader);

include 'functions.php';

$vars['runningInstances'] = countApacheRunningInstances('/OverpassDiff/');



include 'OverpassDiff.php';
$overpass = new OverpassDiff();

if (isset($_REQUEST['timeout']))
    $overpass->timeout = (int) $_REQUEST['timeout'];
if (isset($_REQUEST['dateOld']))
    $overpass->dateOld = date("Y-m-d H:i:s", strtotime($_REQUEST['dateOld']));
if (isset($_REQUEST['dateNew']))
    $overpass->dateNew = date("Y-m-d H:i:s", strtotime($_REQUEST['dateNew']));
if (isset($_REQUEST['code']))
    $overpass->code = $_REQUEST['code'];
else {
    $codes = array(
        '{{geocodeArea:Hungary}}->.searchArea;
(
node["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
way["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
relation["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
);',
        '{{geocodeArea:Hungary}}->.searchArea;
(
node["wheelchair"](area.searchArea);
way["wheelchair"](area.searchArea);
relation["wheelchair"](area.searchArea);
);',
    );
    $overpass->code = $codes[rand(0, 1)];
    $overpass->code = $codes[0];
}


if ($overpass->buildQuery()) {
    if (count($_POST) > 0 OR count($_GET) OR 3 == 3) {
        if ($overpass->runQuery()) {
            $rows = $overpass->diff();
        } else {
            $vars['alert'] = ["We could not recieve good answer from overpass api. - <strong><pre>" . $overpass->lasterror . "</pre></strong>", 'danger'];
            $rows = array();
        }
    } else
        $rows = array();
} else {
    $vars['alert'] = ["We could not build the Query. Sorry. - <strong><pre>" . $overpass->lasterror . "</pre></strong>", 'danger'];
    $rows = array();
}

$vars['achaviUrl'] = "http://overpass-api.de/achavi/?url=".urlencode($overpass->fullUrl);
$vars['xmlFile'] = $overpass->file;
$vars['now'] = date('Y-m-d H:i:s');

$vars['input']['dateOld'] = $overpass->dateOld;
$vars['input']['dateNew'] = $overpass->dateNew;
$vars['input']['code'] = $overpass->code;
$vars['input']['timeout'] = $overpass->timeout;

$vars['query'] = $overpass->query;



if (count($rows) > 0) {
    if (!file_exists('wikipages.json'))
        fopen('wikipages.json', 'w');
    $tmp = file_get_contents('wikipages.json');
    if ($tmp != '')
        $wikipages = (array) json_decode($tmp);
    else
        $wikipages = array();

    $colors = array('create' => 'green', 'modify' => 'orange', 'delete' => 'red');
    $c = 0;
    foreach ($rows as &$row) {

        $row['html']['c'] = $c++;
        $row['html']['typeId'] = "<a href='http://www.openstreetmap.org/" . $row['type'] . "/" . $row['id'] . "'>" . $row['type'] . ":" . $row['id'] . "</a>";
        $row['html']['action'] = "<font color='" . $colors[$row['action']] . "'>" . $row['action'] . "</font>";

        $row['html']['details'] = '';
        foreach ($row['diff'] as $type => $diff) {
            foreach ($diff as $key => $value) {
                if ($type != 'attributes') {
                    if ($type == 'nd') {
                        $key = '<i>nd</i>';
                        for ($i = 1; $i <= 2; $i++)
                            if (isset($value[$i]))
                                $value[$i] = "<i><a href='http://www.openstreetmap.org/node/" . $value[$i] . "'>" . $value[$i] . "</a></i>";
                    }
                    elseif ($type == 'member') {
                        $key = '<i>member</i>';
                        for ($i = 1; $i <= 2; $i++) {
                            if (isset($value[$i])) {
                                $tmp = explode(':', $value[$i]);
                                $value[$i] = "<i><a href='http://www.openstreetmap.org/" . $tmp[0] . "/" . $tmp[1] . "'>" . trim($value[$i], ":") . "</a></i>";
                            }
                        }
                    } else {

                        if (array_key_exists("Key:" . $key, $wikipages)) {
                            if (isset($wikipages["Key:" . $key]) AND $wikipages["Key:" . $key] != false) {
                                $key = "<a href='http://wiki.openstreetmap.org/wiki/Key:" . $key . "' target='_blank'>" . $key . "</a>";
                            }
                        } else {
                            if (checkWikipage("Key:" . $key)) {
                                $wikipages["Key:" . $key] = true;
                                $key = "<a href='http://wiki.openstreetmap.org/wiki/Key:" . $key . "' target='_blank'>" . $key . "</a>";
                            } else
                                $wikipages["Key:" . $key] = false;
                        }
                    }
                    if ($value[0] == 'deleted') {
                        $row['html']['details'] .= "<font color='red'><strike>" . $key . "=" . $value[1] . "</strike></font><br/>";
                    } elseif ($value[0] == 'added') {
                        $row['html']['details'] .= "<font color='green'>" . $key.="=" . $value[1] . "</font><br/>";
                    } elseif ($value[0] == 'modified') {
                        $row['html']['details'] .= "<font color='orange'>" . $key . "</font>=<font color='red'><strike>" . $value[1] . "</strike></font> <font color='green'>" . $value[2] . "</font><br/>";
                    }
                }
            }
        }


        $row['html']['lastChange'] = '';
        if ($row['action'] == 'modify') {
            if (isset($row['diff']['attributes']['version'])) {
                if ($row['diff']['attributes']['version'][2] - $row['diff']['attributes']['version'][1] == 1) {
                    $row['html']['lastChange'] .= "<a href='http://www.openstreetmap.org/changeset/" . $row['changeset'] . "'>" . $row['timestamp'] . "</a> by <a href='http://www.openstreetmap.org/user/" . $row['user'] . "'>" . $row['user'] . "</a>";
                } elseif(isset($row['diff']['version'])) {
                    $row['html']['lastChange'] .= "There were <a href='http://www.openstreetmap.org/" . $row['type'] . "/" . $row['id'] . "/history'>" . ($row['diff']['version'][2] - $row['diff']['version'][1]) . " revisions</a>.";
                }
            }
        } elseif ($row['action'] == 'create') {
            if ($row['version'] == 1) {
                $row['html']['lastChange'] .= "<a href='http://www.openstreetmap.org/changeset/" . $row['changeset'] . "'>" . $row['timestamp'] . "</a> by <a href='http://www.openstreetmap.org/user/" . $row['user'] . "'>" . $row['user'] . "</a>";
            } else {
                $row['html']['lastChange'] .= "<a href='http://www.openstreetmap.org/changeset/" . $row['changeset'] . "'>" . $row['timestamp'] . "</a>";
            }
        } else {
            $row['html']['lastChange'] .= "<a href='http://www.openstreetmap.org/changeset/" . $row['changeset'] . "'>" . $row['timestamp'] . "</a> by <a href='http://www.openstreetmap.org/user/" . $row['user'] . "'>" . $row['user'] . "</a>";
        }
    }

    $vars['rows'] = $rows;

    file_put_contents('wikipages.json', json_encode($wikipages));


    if (isset($overpass->resultXML)) {
        $vars['footer'] = (string) $overpass->resultXML->note;
        $vars['footer'] .= "<br/>Generated with " . $overpass->resultXML['generator'] . " " . $overpass->resultXML['version'];
    }
}

echo $twig->render('index.twig', $vars);

<html>
    <head>
    	<meta charset="utf-8">
    	<title>OSM Diff</title>
    </head>
    <body>
<?
echo "<h1><a href='index.php'>OSM Diff</a></h1>";
echo "<strong>Source and info: <a href='https://github.com/borazslo/OSM-Overpass-Diff'>https://github.com/borazslo/OSM-Overpass-Diff</a></strong><br/>";

include 'OverpassDiff.php';

$overpass = new OverpassDiff();

if(isset($_REQUEST['timeout']))
	$overpass->timeout = (int) $_REQUEST['timeout'];
if(isset($_REQUEST['dateOld']))
	$overpass->dateOld = date("Y-m-d H:i:s",strtotime($_REQUEST['dateOld']));
if(isset($_REQUEST['dateNew']))
	$overpass->dateNew = date("Y-m-d H:i:s",strtotime($_REQUEST['dateNew']));
if(isset($_REQUEST['code'])) 
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
	$overpass->code = $codes[rand(0,1)];
	//$overpass->code = $codes[0];
}

echo "<pre>";
if($overpass->buildQuery()) {
	if(count($_POST) > 0 OR count($_GET) OR 3 == 3) {
		if($overpass->runQuery()) {
			$rows = $overpass->diff();
		} else {
			echo "We could not recieve good answer from overpass api.";
			echo "- <strong>".$overpass->lasterror."</strong>";
			$rows = array();
		}
	} else
		$rows = array();
} else {
	echo "We could not build the Query. Sorry.";
	echo "- <strong>".$overpass->lasterror."</strong>";
	$rows = array();
}
echo "</pre>";

echo "<div style='float:left;width:49%'>";
	echo "<form action='".$_SERVER['PHP_SELF']."' method='get'>";
	echo "dateOld: <input name='dateOld' type='text' size='20' value='".$overpass->dateOld."'>; ";
	echo "dateNew: <input name='dateNew' type='text' size='20' value='".$overpass->dateNew."'> (Now: ".date('Y-m-d H:i:s').")<br/>";
	echo "<textarea name='code' style='width:100%;height:150px;margin-top:4px;margin-bottom:4px;font-size:13px'>".$overpass->code."</textarea>";
	echo "timeout: <input name='timeout' type='text' size='4' value='".$overpass->timeout."'>; ";
	echo "<button style='float:right'>Run</button>";

	echo "</form>";
echo" </div>";

echo "<div style='float:right;width:49%;margin:5px;padding:5px;overflow:auto;background-color:rgba(0,0,0,0.1)'><pre>".$overpass->query."</pre></div>";


if(count($rows) > 0) {
	echo "<table width='100%' border='1'>";
	echo "<tr>
		<th>type:id</th>
		<th>action</th>
		<th>details</th>
		<th>last change</th>
		</tr>";

	if(!file_exists('wikipages.json')) fopen('wikipages.json','w');
	$tmp = file_get_contents('wikipages.json');
	if($tmp != '' ) $wikipages = (array) json_decode($tmp);
	else $wikipages = array();

	$colors = array('create'=>'green','modify'=>'orange','delete'=>'red');
	foreach($rows as $row) {
		echo "<tr  valign='top'>";
			echo "<td><a href='http://www.openstreetmap.org/".$row['type']."/".$row['id']."'>".$row['type'].":".$row['id']."</a></td>";
			
			echo "<td><font color='".$colors[$row['action']]."'>".$row['action']."</font></td>";
			
		
			echo "<td>";
			foreach($row['diff'] as $type => $diff) {
				foreach($diff as $key => $value) {
					if($type == 'nds') $key = 'node';
					else {

						if(array_key_exists("Key:".$key, $wikipages)) {						
							if(isset($wikipages["Key:".$key]) AND $wikipages["Key:".$key] != false) {
								$key = "<a href='http://wiki.openstreetmap.org/wiki/Key:".$key."' target='_blank'>".$key."</a>";	
							}
						} else {
							if(checkWikipage("Key:".$key)) {
								$wikipages["Key:".$key] = true;
								$key = "<a href='http://wiki.openstreetmap.org/wiki/Key:".$key."' target='_blank'>".$key."</a>";	
							} else 
								$wikipages["Key:".$key] = false;
						}
					}
					if($value[0] == 'deleted') {
						echo "<font color='red'><strike>".$key."=".$value[1]."</strike></font><br/>";
					} elseif($value[0] == 'added') {
						echo "<font color='green'>".$key.="=".$value[1]."</font><br/>";
					} elseif($value[0] == 'modified') {
						echo "<font color='orange'>".$key."</font>=<font color='red'><strike>".$value[1]."</strike></font> <font color='green'>".$value[2]."</font><br/>";
					}
				}
			}
			echo "</td>";
		
			echo "<td><a href='http://www.openstreetmap.org/changeset/".$row['changeset']."'>".$row['timestamp']."</a> by <a href='http://www.openstreetmap.org/user/".$row['user']."'>".$row['user']."</a></td>";

		echo "</tr>";
	}

	file_put_contents('wikipages.json', json_encode($wikipages));

	echo "</table>";
	if(isset($overpass->resultXML)) {
		echo "<br/>".(string) $overpass->resultXML->note;
		echo "<br/>Generated with ".$overpass->resultXML['generator']." ".$overpass->resultXML['version'];
	}

}
function checkWikipage($title) {
	if($json = file_get_contents("https://wiki.openstreetmap.org/w/api.php?action=query&titles=".$title."&format=json")) {
		$json =json_decode($json);
		foreach((array) $json->query->pages as $result => $page ) {
			if($result > -1) {
				return "http://wiki.openstreetmap.org/wiki/".$title;
			}
		}
	}
	return false;
}


?>
</body></html>
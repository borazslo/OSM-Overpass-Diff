<html>
    <head>
    	<meta charset="utf-8">
    	<title>OSM Diff</title>
    </head>
    <body>
<?
echo "<h1>OSM Diff</h1>";

include 'OverpassDiff.php';

$overpass = new OverpassDiff();
$overpass->areaid = 3600021335;

if(isset($_REQUEST['dateOld']))
	$overpass->dateOld = strtotime($_REQUEST['dateOld']);
if(isset($_REQUEST['dateNew']))
	$overpass->dateNew = strtotime($_REQUEST['dateNew']);

$query = '(
node["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
way["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
relation["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
);';

echo "<pre>";
$overpass->buildQuery($query);
$overpass->runQuery();
$rows = $overpass->diff();
echo "</pre>";

echo "<strong>Query:</strong><pre>".$overpass->query."</pre>";

echo "<table width='100%' border='1'>";
echo "<tr>
	<th>type:id</th>
	<th>action</th>
	<th>details</th>
	<th>last change</th>
	</tr>";

$colors = array('create'=>'green','modify'=>'orange','delete'=>'red');
foreach($rows as $row) {
	echo "<tr  valign='top'>";
		echo "<td><a href='http://www.openstreetmap.org/".$row['type']."/".$row['id']."'>".$row['type'].":".$row['id']."</a></td>";
		
		echo "<td><font color='".$colors[$row['action']]."'>".$row['action']."</font></td>";
		
	
		echo "<td>";
		foreach($row['diff'] as $type => $diff) {
			foreach($diff as $key => $value) {
				if($type == 'nds') $key = 'node';
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

echo "</table>";
echo "<br/>".(string) $overpass->resultXML->note;
echo "<br/>Generated with ".$overpass->resultXML['generator']." ".$overpass->resultXML['version'];


?>
</body></html>
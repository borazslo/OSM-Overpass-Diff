<?php
include 'OverpassDiff.php';

$overpass = new OverpassDiff();

$overpass->code = 
	 '{{geocodeArea:Hungary}}->.searchArea;
(
node["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
way["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
relation["amenity"="place_of_worship"]["religion"="christian"]["denomination"~"catholic"](area.searchArea);
);';
	
if($overpass->buildQuery()) {
	if($overpass->runQuery()) {
		$rows = $overpass->diff();
	} else {		
		$rows = array();
	}
} else {	
	$rows = array();
}

echo '<?xml version="1.0" encoding="UTF-8"?>';

?>

<rss version="2.0">
  <channel>
    <title>Catholic Churches - OverpassDiff</title>
    <description>Take a look at some of FeedForAll&apos;s favorite software and resources for learning more about RSS.</description>
    <link>https://github.com/borazslo/OSM-Overpass-Diff</link>
    <language>en-us</language>
    <lastBuildDate><?php echo date('D, j M Y H:i:s') ?></lastBuildDate>
    <pubDate><?php echo date('D, j M Y H:i:s') ?></pubDate>
    <generator>OSM Overpass Diff</generator>
<?php
	foreach($rows as $row) {
		if(isset($row['timestamp']) AND $row['timestamp'] != '' ) {
			
	   echo' <item>
	      <title>['.$row['action'].'] '.$row['type'].':'.$row['id'].'</title>
	      <description>';
	      echo htmlentities("<pre>".print_r($row['diff'],1)."</pre>");
	     echo '</description>
	      <link>http://openstreetmap.org/'.$row['type'].'/'.$row['id'].'</link>
	      <pubDate>'.date('D, j M Y H:i:s +0200',strtotime($row['timestamp'])).'</pubDate>
	    </item>'."\n";
    }
    }
?>

  </channel>
</rss>
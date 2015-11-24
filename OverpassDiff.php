<?php


/**
 * 
 */
 class OverpassDiff { 
 	
 	function __construct()
 	{
 		$this->interpreter = "http://overpass-api.de/api/interpreter";
 		$this->timeout = 25;
 		$this->format = 'xml'; /* xml, json, array */
 		//$this->areaid; http://wiki.openstreetmap.org/wiki/Overpass_turbo/Extended_Overpass_Queries
 		$this->dateOld = date("Y-m-d\TH:00:00\Z",strtotime("-1 week"));
 		$this->dateNew = date("Y-m-d\TH:00:00\Z");
 	}

 	function buildQuery($code = false) {
 		if($code == false)
 			$code = $this->code;

 		if(isset($this->date)) {
 			$this->dateOld = $this->date;
 			$this->dateNew = time();
 		}

 		$return  = '[timeout:'.$this->timeout.']'."\n";
 		$return .= '[adiff:"'.date("Y-m-d\TH:i:00\Z",strtotime($this->dateOld)).'","'.date("Y-m-d\TH:i:00\Z",strtotime($this->dateNew)).'"];'."\n";
		$return .= $code;
		$return .= "\nout body meta;\n>;\nout meta skel qt;";

		$return = $this->replaceShortcuts($return);

		$this->query = $return;
		return $return;

 	}
 	function runQuery($query = false) {
 		if($query == false) {
 			$query = $this->query;
 		} 
 		if($query == '') return false;

	    $obj = false;
	    $query = str_replace(PHP_EOL, '', $query);
	    $query = str_replace('/ /', '', $query);
	    $query = urlencode($query);
	    $url = $this->interpreter."?data=".$query;
	    $dir = 'tmp/';
		if(!file_exists($dir)) mkdir($dir);

	    if(file_exists($dir."osm_".md5($query))) {
	        $result = file_get_contents($dir."osm_".md5($query)); 
	    } else {        
	        $result = @file_get_contents($url);
	        if (strpos($http_response_header[0], "400")) {
	        	return false;
	        }
	        elseif (strpos($http_response_header[0], "200")) { 
   				
			} else { 
   				return false;
			}
	        file_put_contents($dir."osm_".md5($query), $result);         
	    }

	    $this->resultRaw = $result;
	    $this->resultXML = simplexml_load_string($result);
	    $this->resultJSON = json_encode($this->resultXML);
	    $this->resultArray = json_encode($this->resultJSON);
	    
	    switch ($this->format) {
	    	case 'xml':
	    		$this->result = $this->resultXML;
	    		break;
	    	case 'json':
	    		$this->result = $this->resultJSON;
	    		break;
	    	case 'array':
	    		$this->result = $this->resultArray;
	    		break;
	    	
	    	default:
	    		$this->result = $result->resultRaw;
	    		break;
	    }


	    return $this->result;
		
		}

		function diff() {

			$rows = array();
			
			foreach($this->resultXML->{'action'} as $element) {
				$row = array();

				$action = (string) $element['type'];

				if($action == 'create') $tmp = $element->children();
				else $tmp = $element->new->children();

				foreach($tmp as $type => $node) {
					$differents = array();

					$row['action'] = $action;
					$row['type'] = $type;
					foreach(array('id','lat','lon','version','timestamp','changeset','uid','user') as $key )
						$row[$key] = (string) $node[$key];

					if($row['timestamp'] == '') {
						//echo "<pre>"; print_r($element);
					}


					$THEkey = strtotime((string) $node['timestamp'])."-".$row['type'].":".$row['id'];

					if($action == 'delete') {
						$new = array();
						foreach( $element->old->children()[0]->{'tag'} as $tag) {
							$tag = (array) $tag->attributes();
							$tmp = $tag['@attributes'];
							$new[$tmp['k']] = array('deleted',$tmp['v']);
						}
						$row['diff']['tags'] = $new;					
					} else if($action == 'create') {
						$new = array();
						foreach( $element->children()[0]->{'tag'} as $tag) {
							$tag = (array) $tag->attributes();
							$tmp = $tag['@attributes'];
							$new[$tmp['k']] = array('added',$tmp['v']);
						}
						$row['diff']['tags'] = $new;

					} else if($action == 'modify') {
						
						//diff attributes
						$old = (array) $element->old->children()[0]->attributes();
						$old = $old['@attributes'];						
						$new = (array) $element->new->children()[0]->attributes();
						$new = $new['@attributes'];
						$diff = $this->changes($old,$new);
						foreach(array('version','timestamp','changeset','uid','user') as $key) {
							unset($diff[$key]);
						}
						if(count($diff) > 0) $differents['attributes'] = $diff;


						//diff tags
						$old = array();
						foreach( $element->old->children()[0]->{'tag'} as $tag) {
							$tag = (array) $tag->attributes();
							$tmp = $tag['@attributes'];
							$old[$tmp['k']] = $tmp['v'];
						}
						$new = array();
						foreach( $element->new->children()[0]->{'tag'} as $tag) {
							$tag = (array) $tag->attributes();
							$tmp = $tag['@attributes'];
							$new[$tmp['k']] = $tmp['v'];
						}
						$diff = $this->changes($old,$new);
						if(count($diff) > 0) $differents['tags'] = $diff;

						//diff nds
						$old = array();
						foreach( $element->old->children()[0]->{'nd'} as $tag) {
							$tag = (array) $tag->attributes();
							$tmp = $tag['@attributes'];
							if(isset($tmp['ref'])) $old[$tmp['ref']] = $tmp['ref'];
						}
						$new = array();
						foreach( $element->new->children()[0]->{'nd'} as $tag) {
							$tag = (array) $tag->attributes();
							$tmp = $tag['@attributes'];
							if(isset($tmp['ref'])) $new[$tmp['ref']] = $tmp['ref'];
						}
						$diff = $this->changes($old,$new);
						if(count($diff) > 0) $differents['nds'] = $diff;

						$row['diff'] = $differents;
						
					}

					$rows[$THEkey] = $row;

				}
			}
			krsort($rows);
			return $rows;
			

		}

		private function changes($old,$new) {
			$return = array();
			foreach($old as $key => $value) {
				if(!isset($new[$key])) {
					$return[$key] = array('deleted',$value);
				} elseif($new[$key] == $old[$key]) {
					unset($new[$key]);
				} else {
					$return[$key] = array('modified',$old[$key],$new[$key]);
					unset($new[$key]);
				}
			}
			foreach($new as $key => $value) {
				if(!isset($old[$key])) {
					$return[$key] = array('added',$value);
				}
			}
			ksort($return);
			return $return;
		}

		private function replaceShortcuts($query) {
			//http://wiki.openstreetmap.org/wiki/Overpass_turbo/Extended_Overpass_Queries

			preg_match_all('/\{\{(geocodeId|geocodeArea|geocodeBbox|geocodeCoords):(.*)\}\}/i', $query, $matches,PREG_SET_ORDER);
			foreach($matches as $match) {
				if(count($match) > 0) {
					switch ($match[1]) {
						case 'geocodeId':
							$url = "http://nominatim.openstreetmap.org/search?q=".$match[2]."&format=json&limit=1";
							$json = file_get_contents($url);
							$json = json_decode($json);
							$return = "relation(".($json[0]->osm_id).")";
							$query = str_replace($match[0], $return, $query);
							break;

						case 'geocodeArea':
							$url = "http://nominatim.openstreetmap.org/search?q=".$match[2]."&format=json&limit=1";
							$json = file_get_contents($url);
							$json = json_decode($json);
							$return = "area(".($json[0]->osm_id + 3600000000).")";
							$query = str_replace($match[0], $return, $query);
							break;

						case 'geocodeBbox':
							$url = "http://nominatim.openstreetmap.org/search?q=".$match[2]."&format=json&limit=1";
							$json = file_get_contents($url);
							$json = json_decode($json);
							$return = implode(',',$json[0]->boundingbox);
							$query = str_replace($match[0], $return, $query);
							break;

						case 'geocodeCoords':
							$url = "http://nominatim.openstreetmap.org/search?q=".$match[2]."&format=json&limit=1";
							$json = file_get_contents($url);
							$json = json_decode($json);
							$return = $json[0]->lat.",".$json[0]->lon;
							$query = str_replace($match[0], $return, $query);
							break;

						default:
							# code...
							break;
					}
				}
			}
			return $query;
		}
 } 


?>
<?php

date_default_timezone_set('UTC');

/**
 * 
 */
 class OverpassDiff { 
 	
 	function __construct()
 	{
 		$this->apiurl = "http://overpass-api.de/api/";
 		$this->interpreter = $this->apiurl."interpreter";
 		$this->timeout = 255;
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
 		if($query == '') {
 			$this->lasterror = 'There was no code to build the query upon that.';
 			return false;
 		}

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
	    	if(!file_exists($dir."tmp_".md5($query))) {
	    		file_put_contents($dir."tmp_".md5($query),time());
	    	} else {
	    		$time = file_get_contents($dir."tmp_".md5($query));

	    		if($time + $this->timeout < time() + 1 ) {
		    		file_put_contents($dir."tmp_".md5($query),time());
	    		} else {
	    			//echo date('H:i:s')."<br/>";
	    			sleep(1);
	    			return $this->runQuery(urldecode($query));
	    		}
	    	}

	        $curl_handle=curl_init();
			curl_setopt($curl_handle, CURLOPT_URL,$url);
			curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl_handle, CURLOPT_USERAGENT, 'OverpassDiff');
		    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
			$result = curl_exec($curl_handle);
		    $response = curl_getinfo( $curl_handle );
			curl_close($curl_handle);

        	unlink($dir."tmp_".md5($query));
			//print_r($response);
	        
	        //http://overpass-api.de/command_line.html
	        if ($response['http_code'] == "400" ) { 
	        	$this->lasterror = "Bad Request: Error in the query.";
	        	return false; 
	        }
	        elseif ($response['http_code'] == "429" ) { 
	        	//429 Too Many Requests
	        	file_get_contents($this->apiurl."kill_my_queries");
	        	$this->lasterror = "Too Many Requests."	;      	
	        	return $this->runQuery($query);	        	
	        }
	        elseif ($response['http_code'] == "504" ) { 
	        	$this->lasterror = "Gateway Timeout. ".$this->apiurl;
	        	return false; 
	        }
	        elseif ($response['http_code'] == "200" ) {

			} else { 
	        	$this->lasterror = "Error! HTTP Code: ".$response['http_code'];	        	

   				return false;
			}
	        if($result != '') {
	        	file_put_contents($dir."osm_".md5($query), $result);
	        }
	    }
	    $this->resultRaw = $result;
	    $this->resultXML = simplexml_load_string($result);
	    $this->resultJSON = json_encode($this->resultXML);
	    $this->resultArray = (array) json_decode($this->resultJSON);

	    if(isset($this->resultArray['remark'])) {
	    	$this->lasterror = $this->resultArray['remark'];
	    	return false;
	    }
	    
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
			$pois = array();
			$rows = array();
			
			foreach($this->resultXML->{'action'} as $element) {
				$row = array();

				$action = (string) $element['type'];
				if($action == 'create') $tmp = $element->children();
				else $tmp = $element->new->children();				

				$differents = array();
				$row['action'] = $action;
				$row['type'] = key($tmp);;
				foreach(array('id','lat','lon','version','timestamp','changeset','uid','user') as $key )
					$row[$key] = (string) $tmp[0][$key];

				$THEkey = strtotime((string) $row['timestamp'])."-".$row['type'].":".$row['id'];

				//attributes
				if($row['action'] != 'create') {
					$old = (array) $element->old->children()[0];
					$old = $old['@attributes'];
					$new = (array) $element->new->children()[0];
					$new = $new['@attributes'];
				} else {
					$old = array();			
					$new = (array) $element->children()[0];
					$new = $new['@attributes'];
				}
				$diff = $this->changes($old,$new);
				if(count($diff) > 0) $differents['attributes'] = $diff;

				//tags
				$old = array(); $new = array();
				if($row['action'] != 'create') {
					foreach( $element->old->children()[0]->{'tag'} as $tag) {
						$tag = (array) $tag->attributes();
						$tag = $tag['@attributes'];						
						$old[$tag['k']] = $tag['v'];						
					}
					foreach( $element->new->children()[0]->{'tag'} as $tag) {
						$tag = (array) $tag->attributes();
						$tag = $tag['@attributes'];						
						$new[$tag['k']] = $tag['v'];
					}
				} else {
					foreach( $element->children()[0]->{'tag'} as $tag) {
						$tag = (array) $tag->attributes();
						$tag = $tag['@attributes'];						
						$new[$tag['k']] = $tag['v'];
					}
				}
				$diff = $this->changes($old,$new);
				if(count($diff) > 0) $differents['tag'] = $diff;
		
				//nds
				$old = array(); $new = array();
				if($row['action'] != 'create') {
					foreach( $element->old->children()[0]->{'nd'} as $nd) {
						$nd = (array) $nd->attributes();
						$nd = $nd['@attributes'];						
						if(isset($nd['ref'])) {
							$old[$nd['ref']] = $nd['ref'];
							$pois[] = "node:".$nd['ref'];
						}
					}
					foreach( $element->new->children()[0]->{'nd'} as $nd) {
						$nd = (array) $nd->attributes();
						$nd = $nd['@attributes'];
						if(isset($nd['ref'])) {
							$new[$nd['ref']] = $nd['ref'];
							$pois[] = "node:".$nd['ref'];
						}
					}
				} else {
					foreach( $element->children()[0]->{'nd'} as $nd) {
						$nd = (array) $nd->attributes();
						$nd = $nd['@attributes'];	
						if(isset($nd['ref'])) {
							$new[$nd['ref']] = $nd['ref'];
							$pois[] = "node:".$nd['ref'];
						}
					}
				}
				$diff = $this->changes($old,$new);
				if(count($diff) > 0) $differents['nd'] = $diff;

				//members
				$old = array(); $new = array();
				if($row['action'] != 'create') {
					foreach( $element->old->children()[0]->{'member'} as $member) {
						$member = (array) $member->attributes();
						$member = $member['@attributes'];						
						if(isset($member['ref'])) {
							$old[$member['type'].":".$member['ref']] = implode(':',$member);
							$pois[] = $member['type'].":".$member['ref'];
						}
					}
					foreach( $element->new->children()[0]->{'member'} as $member) {
						$member = (array) $member->attributes();
						$member = $member['@attributes'];
						if(isset($member['ref'])) {
							$new[$member['type'].":".$member['ref']] = implode(':',$member);
							$pois[] = $member['type'].":".$member['ref'];
						}
					}
				} else {
					foreach( $element->children()[0]->{'member'} as $member) {
						$member = (array) $member->attributes();
						$member = $member['@attributes'];	
						if(isset($member['ref'])) {
							$new[$member['type'].":".$member['ref']] = implode(':',$member);
							$pois[] = $member['type'].":".$member['ref'];
						}
					}
				}
				$diff = $this->changes($old,$new);
				if(count($diff) > 0) $differents['member'] = $diff;

				$row['diff'] = $differents;
				$rows[$THEkey] = $row;				
			}
			krsort($rows);
			foreach($rows as $key => $row) {
				if(in_array($row['type'].":".$row['id'], $pois)) {
					unset($rows[$key]);
				}
			}
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
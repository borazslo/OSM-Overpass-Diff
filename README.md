# (OSM) Overpass-Diff
This simple php class helps to compare the results of the same Overpass API query requested in two different times. It shows the changed tags, attributes and child nodes. It also shows the deleted and newly created elements.

It uses the [adiff](http://wiki.openstreetmap.org/wiki/Overpass_API/Overpass_QL#Augmented_Delta_between_two_dates_.28.22adiff.22.29) of the [Overpass API](http://wiki.openstreetmap.org/wiki/Overpass_API).

##Requirements:
- Php

##Cache
It saves the result of every Overpass Query.

##Variables
####dateOld
- The date for the base of the comparsion.
- It can be in any format recognizeble by [`strtotime()`](http://php.net/manual/en/function.strtotime.php). For example: `2015-12-24 18:00:00` or `-1 month`.
- Can be set in the script: `$overpass->dateOld` or thorugh Post/Get method: `dateOld`
- Default: `-1 week`.

####dateNew
- The date for the end of the comparsion.
- It can be in any format recognizeble by [`strtotime()`](http://php.net/manual/en/function.strtotime.php). For example: `2015-12-24 18:00:00` or `-1 month`.
- Can be set in the script: `$overpass->dateNew` or thorugh Post/Get method: `dateNew` 
- Default: the beginning of the current hour = `date('Y-m-d H:00:00')`

####areid
- The OSM id of the area in that we make the search
- It is a numeric id.
- Can be set in the script: `$overpass->areid` 
- There is no default and it is obligatory.
- Later on this will be changed.

####timeout
- optional
- Can be set in the script: `$overpass->timeout` 

##Development
Please, come and help to make this a great tool.
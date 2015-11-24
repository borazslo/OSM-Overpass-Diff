# (OSM) Overpass-Diff
This simple php class helps to compare the results of the same Overpass API query requested in two different times. It shows the changed tags, attributes and child nodes. It also shows the deleted and newly created elements.

It uses the [adiff](http://wiki.openstreetmap.org/wiki/Overpass_API/Overpass_QL#Augmented_Delta_between_two_dates_.28.22adiff.22.29) of the [Overpass API](http://wiki.openstreetmap.org/wiki/Overpass_API).

Example site: [207.180.171.165/OverpassDiff](http://207.180.171.165/OverpassDiff) (Please do not overload my devserver.)

##Requirements:
- Php

##Cache
It saves the result of every Overpass Query.

##Variables
- __dateOld__ & __dateNew__
 - The dates for the base of the comparsion.
 - It can be in any format recognizeble by [`strtotime()`](http://php.net/manual/en/function.strtotime.php). For example: `2015-12-24 18:00:00` or `-1 month`.
 - Default: from `-1 week` to the beginning of the current hour = `date('Y-m-d H:00:00')`.
- __timeout__
 - Default: 25

##Shortcuts
You can use some of the Overpass Shortcuts from the [Extended Overpass Queries](http://wiki.openstreetmap.org/wiki/Overpass_turbo/Extended_Overpass_Queries): {{geocodeId:_name_}}, {{geocodeArea:_name_}}, {{geocodeArea:_name_}}, {{geocodeCoords:_name_}}

##Development
Please, come and help to make this a great tool.
<?php
	function SphericalMercatorToWGS84($lat, $lon) {
		$latWGS = $lat / ( ( 2 * M_PI * 6378137 / 2.0 ) / 180.0 );
		$latWGS = 180.0 / M_PI * ( 2 * atan( exp( $latWGS * M_PI / 180.0 ) ) - M_PI / 2 );
		$lonWGS = $lon / ( ( 2 * M_PI * 6378137 / 2.0 ) / 180.0 );
	
		return array($latWGS, $lonWGS);
	}
	function zoomToDegrees($zoom){
		$degrees = round(360.0000 / pow(2, $zoom), 4);
	
		return $degrees;
	} 
	/**
	* Lat/lon passed through GET:
	*/
	if(!is_numeric($_GET["lat"])||!is_numeric($_GET["lon"])||!is_numeric($_GET["zoom"])){
		print "<p style=\"text-align:center;\">Planet Earth does not support non-numerical latitude or longitude. <a href=\"index.html\">Go back.</a></p>";
		die;
	}
	$latMercator = $_GET["lat"];
	$lonMercator = $_GET["lon"];
	$zoomOSM = $_GET["zoom"];
	$merc = SphericalMercatorToWGS84($latMercator, $lonMercator);
	$zoomDegrees =  zoomToDegrees($zoomOSM);

	function getUrlDbpediaArticles($lat, $lon, $dVariation)	{
		$format = 'json';
  		$query =
  			"PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
			SELECT ?subject ?label ?lat ?long WHERE {
    			?subject geo:lat ?lat.
    			?subject geo:long ?long.
    			?subject rdfs:label ?label.
    			FILTER(xsd:float(?lat) - ".$lat." <= ".$dVariation." && ".$lat." - xsd:float(?lat) <= ".$dVariation."
        		&& xsd:float(?long) - ".$lon." <= ".$dVariation." && ".$lon." - xsd:float(?long) <= ".$dVariation."
        		&& lang(?label) = 'en'
			).
			OPTIONAL { ?subject foaf:depiction ?depic. FILTER(?subject2 = ?subject) }
			OPTIONAL { ?subject dbpprop:imageName ?imageNm. FILTER(?subject2 = ?subject) }
			FILTER(!bound(?subject2)).
			} LIMIT 30";
			$searchUrl = 'http://dbpedia.org/sparql?'
			.'query='.urlencode($query)
			.'&format='.$format;

			return $searchUrl;
		}


	function request($url){
		if (!function_exists('curl_init')){
		die('CURL is not installed!');
		}

		$ch= curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, "Checking geotagged pages missing image. stephen@thjnk.com");
		$response = curl_exec($ch);

   		curl_close($ch);
		
		return $response;
	}

	$requestURL = getUrlDbpediaArticles($merc[0], $merc[1], $zoomDegrees);
	$responseArray = json_decode(request($requestURL), true);
?>
<html>
<title>List of Wikipedia articles lacking images</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<link href="grid.css" type="text/css" rel="stylesheet" media="screen"/>
<script src="http://www.openlayers.org/api/OpenLayers.js"></script>
<script>
	function init(){
		map = new OpenLayers.Map("mapdiv", { 
    			controls: [
				new OpenLayers.Control.Navigation(),
				new OpenLayers.Control.LayerSwitcher({'ascending':false}),
				new OpenLayers.Control.ScaleLine(),
			],
		});
		map.addLayer(new OpenLayers.Layer.OSM());
		var markers = new OpenLayers.Layer.Markers( "Markers" );
		map.addLayer(markers);

		var size = new OpenLayers.Size(21,25);
		var offset = new OpenLayers.Pixel(-(size.w/2), -size.h);
		var icon = new OpenLayers.Icon('marker.png', size, offset);
		markers.addMarker(new OpenLayers.Marker(new OpenLayers.LonLat(-122.4172210693359,37.76083374023438).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject()),icon));
	<?php
		foreach($responseArray["results"]["bindings"] as $place){
	?>
			markers.addMarker(new OpenLayers.Marker(new OpenLayers.LonLat(<?php print $place["long"]["value"]; ?>,<?php print $place["lat"]["value"]; ?>).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject()),icon.clone()));
	<?php
		}
	?> 
	//Set start centrepoint and zoom    
		var lonLat = new OpenLayers.LonLat( <?php print $merc[1]; ?>, <?php print $merc[0]; ?> )
			.transform(
            			new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
            			map.getProjectionObject() // to Spherical Mercator Projection
         		 );
		var zoom=<?php print $zoomOSM; ?>;
		map.setCenter (lonLat, zoom);  
	}
</script>
</head>
<body onload="init()">
	<div class="row">
		<div class="column grid_10"><h1>Photo hunt</h1></div>
	</div>
	<div class="row">
		<div id="mapdiv" class="column grid_10" style="height:500px;"></div>
 	</div>
	<div class="row">
		<div class"column grid_10"><h2>List of articles</h2></div>
	</div>
	<?php 
		$divNumber = 0;
		foreach($responseArray["results"]["bindings"] as $place){
			$articleNumber = $divNumber+1;
			$articleName = $place["label"]["value"];
			$articleNameURL = str_replace(" ","_",$articleName);
			$articleNameURL = "http://en.wikipedia.org/wiki/".$articleNameURL;
			print "
	<div class=\"row\">
		<div class=\"column grid_6\">
			".$articleNumber.". <a href='".$articleNameURL."'>".$place["label"]["value"]."</a>
		</div>
		<div class=\"column grid_4\" id=\"mapdiv".$divNumber."\" style=\"height: 220px;\">
		<script>
			map = new OpenLayers.Map(\"mapdiv".$divNumber."\",{
			controls: [
				new OpenLayers.Control.Navigation(),
				new OpenLayers.Control.LayerSwitcher({'ascending':false}),
				new OpenLayers.Control.ScaleLine(),
			],});
			map.addLayer(new OpenLayers.Layer.OSM());
			var lonLat = new OpenLayers.LonLat( ".$place["long"]["value"]." ,".$place["lat"]["value"]." )
				.transform(
					new OpenLayers.Projection(\"EPSG:4326\"), // transform from WGS 1984
					map.getProjectionObject() // to Spherical Mercator Projection
				);
			var zoom=15;
			var markers = new OpenLayers.Layer.Markers( \"Markers\" );
			map.addLayer(markers);
 			markers.addMarker(new OpenLayers.Marker(lonLat));
			map.setCenter (lonLat, zoom);
		</script>
		</div>
	</div>
	<div class=\"row\">
		<div class=\"column grid_10\">
			<p>".$place["lat"]["value"]."/".$place["long"]["value"]."</p>
		</div>
	</div>";
	
		$divNumber = $divNumber + 1;
	} 

	?>
<div class="row">
	<div class="column grid_10">
	<h2>About</h2>
	<ul>    
		<li>Maps provided by <a href="http://wiki.openstreetmap.org/wiki/Main_Page">Open Street Maps</a> using <a href="http://www.openlayers.org/">OpenLayers</a></li>                 
		<li>Wikipedia articles parsed and provided by <a href="http://dbpedia.org/About">DBpedia</a></li>
	<li>Photo hunt generator copyright 2011  <a href="http://thjnk.com">Stephen LaPorte</a>, licensed under the <a rel="license" href="http:/www.gnu.org/licenses/gpl-2.0.html">GPLv2</a>.
	</ul>
</div>
</body>
</html>

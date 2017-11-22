<?php
# Connect to the database
$user="dfl";
$password="Dfl#123!";
$database="folkloredb";
$dblink = mysql_connect("localhost:3306",$user,$password);
if (!$dblink) {
    die('Could not connect: ' . mysql_error());
}
$db_selected = @mysql_select_db($database, $dblink) or die( "Unable to select database" );
if (!$db_selected) {
     die('Unable to select db: ' . mysql_error());
}

/* UTF8 encoding is necessary so that all place names get written to the
 * proper files (especially fieldtrips.xml, places.xml and the individual
 * informants' files). */
#$encoding_query = "SET names \"utf8\"";
$encoding_query = "SET names \"latin1\"";

$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed '. mysql_error());

$global_places_list = array();

function add_to_global_places($source_array) {
    global $global_places_list;
    foreach ($source_array as $place_id) {
        if ($place_id && (!(array_key_exists($place_id, $global_places_list)))) {
            $place_query = "SELECT name, name_alternate, latitude, longitude, topo_num FROM place WHERE place_id=$place_id";
            $place_result =  mysql_query($place_query) or die('Place query failed ' . mysql_error());
            $place_row = mysql_fetch_assoc($place_result);
            if (!($place_row)) {
		    echo "missing place: $place_id\n";
	    }

	    $global_places_list[$place_id] = $place_row;
	    mysql_free_result($place_result);
	}
    }
}

$outfile = fopen('person_to_bio_place.txt', 'w+');

$person_query = "SELECT person_id, last_name, first_name, birth_place_id, death_place_id, core_informant FROM person";
$person_result = mysql_query($person_query) or die('Person query failed ' . mysql_error());

while ($person_row = mysql_fetch_assoc($person_result)) {
	if ($person_row['birth_place_id']) {
		add_to_global_places(array($person_row['birth_place_id']));
		fwrite($outfile, $person_row['person_id'] . "\t" . $person_row['birth_place_id'] . "\t" . $person_row['first_name'] . " " . $person_row['last_name'] . "\t" . $global_places_list[$person_row['birth_place_id']]['name'] . "\t" . $global_places_list[$person_row['birth_place_id']]['latitude'] . "\t" . $global_places_list[$person_row['birth_place_id']]['longitude'] . "\t" . "birthplace" . "\t" . $person_row['core_informant'] . "\n");
	}
	if ($person_row['death_place_id']) {
		add_to_global_places(array($person_row['death_place_id']));
		fwrite($outfile, $person_row['person_id'] . "\t" . $person_row['death_place_id'] . "\t" . $person_row['first_name'] . " " . $person_row['last_name'] . "\t" . $global_places_list[$person_row['death_place_id']]['name'] . "\t" . $global_places_list[$person_row['death_place_id']]['latitude'] . "\t" . $global_places_list[$person_row['death_place_id']]['longitude'] . "\t" . "deathplace" . "\t" . $person_row['core_informant'] . "\n");
	}
	
	$residence_query = "SELECT place_recorded_id, COUNT(place_recorded_id) AS place_count FROM story, story_to_informant WHERE story_to_informant.informant_id=".$person_row['person_id']." AND story_to_informant.story_id=story.story_id GROUP BY place_recorded_id ORDER BY place_count DESC";
	$residence_result = mysql_query($residence_query) or die('Residence query failed ' . mysql_error());
	while ($residence_row = mysql_fetch_assoc($residence_result)) {
		if ($residence_row['place_recorded_id']) {
			add_to_global_places(array($residence_row['place_recorded_id']));
			fwrite($outfile, $person_row['person_id'] . "\t" . $residence_row['place_recorded_id'] . "\t" . $person_row['first_name'] . " " . $person_row['last_name'] . "\t" . $global_places_list[$residence_row['place_recorded_id']]['name'] . "\t" . $global_places_list[$residence_row['place_recorded_id']]['latitude'] . "\t" . $global_places_list[$residence_row['place_recorded_id']]['longitude'] . "\t" . "residence" . "\t" . $person_row['core_informant'] . "\n");
		}
	}	
}
mysql_free_result($residence_result);

fclose($outfile);

mysql_close($dblink);

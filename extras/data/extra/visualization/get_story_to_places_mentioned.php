<?php
include("bootstrap.php");

$edgefile = fopen('danishStoryToPlacesMentioned.wadj', 'w+');
$labelfile = fopen('danishStoryToPlacesMentioned.wadj.labels', 'w+');

$story_info = array();
$story_to_places_mentioned = array();

$place_ids = array();

$stories_query = "SELECT * FROM story";
$stories_result = mysql_query($stories_query) or die('Stories query failed ' . mysql_error());

while ($story_row = mysql_fetch_assoc($stories_result)) {

	$informant_id_query = "SELECT informant_id FROM story_to_informant WHERE story_id=".$story_row['story_id']." AND secondary=0";
	$informant_id_result = mysql_query($informant_id_query) or die('Informant ID query failed ' . mysql_error());
	$informant_info = mysql_fetch_assoc($informant_id_result);
	$story_row['informant_id'] = $informant_info['informant_id'];

	$story_info[$story_row['story_id']] = $story_row;

	$places_mentioned_query = "SELECT DISTINCT place_id, story_id FROM story_to_place_mentioned WHERE story_id=".$story_row['story_id'];
	$places_mentioned_result = mysql_query($places_mentioned_query) or die('Places mentioned query failed ' . mysql_error());

	while ($place_mentioned_row = mysql_fetch_assoc($places_mentioned_result)) {
		if (!array_key_exists($story_row['story_id'], $story_to_places_mentioned))
			$story_to_places_mentioned[$story_row['story_id']] = array($place_mentioned_row['place_id']);
		else
			$story_to_places_mentioned[$story_row['story_id']][] = $place_mentioned_row['place_id'];

	}
	mysql_free_result($places_mentioned_result);
}
mysql_free_result($stories_result);

foreach ($story_to_places_mentioned as $story_id=>$place_mentioned_ids) {

	if (array_key_exists($story_id, $etk_index_info))
		$etk_index = $etk_index_info[$story_id];
	else
		$etk_index = "";

	if (array_key_exists($story_id, $genre_info))
		$genre_id = $genre_info[$story_id];
	else
		$genre_id = "";

        $tango_indices = "";
	if (array_key_exists($story_id, $tango_index_info)) {
		sort($tango_index_info[$story_id]);
		foreach($tango_index_info[$story_id] as $t_index)
		        $tango_indices = $tango_indices . $t_index . " ";
	}

        $occupation = "";
        if (array_key_exists($story_info[$story_id]['informant_id'], $occupation_info))
	        $occupation = $occupation_info[$story_info[$story_id]['informant_id']];

	$gender = "N/A";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "m")
	        $gender = "male";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "f")
                $gender = "female";

	fwrite($labelfile, $story_id . ' [label="' . $story_info[$story_id]['publication_info'] . '" type="story" story_id="' . $story_id . '" informant_id="' . $story_info[$story_id]['informant_id'] . '" informant_occupation="' . $occupation . '" informant_gender="' . $gender . '" place_recorded_id="' . $story_info[$story_id]['place_recorded_id'] .'" fieldtrip_id="' . $story_info[$story_id]['fieldtrip_id'] . '" etk_index_id="'. $etk_index . '" genre_id="' . $genre_id . '" tango_indices="' . $tango_indices . '" ];'."\n");

	fwrite($edgefile, $story_id . ': ');

	foreach ($place_mentioned_ids as $place_mentioned_id) {

		if ($place_mentioned_id == 0) {
			echo "ALERT: null place mentioned ID for story $story_id\n";
			continue;
		}

		if (!in_array($place_mentioned_id, $place_ids)) {
			// ADD TO LABELS FILE
			$place_ids[] = $place_mentioned_id;
		}

		fwrite($edgefile, $place_mentioned_id + $max_story_id . '(1),');

	}

	fwrite($edgefile, ";\n");
}

sort($place_ids);

foreach ($place_ids as $place_mentioned_id) {
	fwrite($labelfile, $place_mentioned_id + $max_story_id . ' [label="' . $places_info[$place_mentioned_id]['name'] . '" type="place_mentioned" place_id="' . $place_mentioned_id . '" ];'."\n");
}

mysql_free_result($places_result);

fclose($edgefile);
fclose($labelfile);

mysql_close($dblink); 

?>

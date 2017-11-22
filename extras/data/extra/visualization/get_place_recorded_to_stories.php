<?php
include("bootstrap.php");

$edgefile = fopen('danishPlaceRecordedToStories.wadj', 'w+');
$labelfile = fopen('danishPlaceRecordedToStories.wadj.labels', 'w+');

$story_info = array();
$story_to_place_recorded = array();

$place_id_to_stories = array();

$stories_query = "SELECT * FROM story";
$stories_result = mysql_query($stories_query) or die('Stories query failed ' . mysql_error());

while ($story_row = mysql_fetch_assoc($stories_result)) {

	if (($story_row['place_recorded_id'] == 0) || ($story_row['place_recorded_id'] == "NULL") || ($story_row['place_recorded_id'] == ""))
		continue;

	if (!array_key_exists($story_row['place_recorded_id'], $place_id_to_stories))
		$place_id_to_stories[$story_row['place_recorded_id']] = array($story_row['story_id']);
	else
		$place_id_to_stories[$story_row['place_recorded_id']][] = $story_row['story_id'];

	$informant_id_query = "SELECT informant_id FROM story_to_informant WHERE story_id=".$story_row['story_id']." AND secondary=0";
	$informant_id_result = mysql_query($informant_id_query) or die('Informant ID query failed ' . mysql_error());
	$informant_info = mysql_fetch_assoc($informant_id_result);
	$story_row['informant_id'] = $informant_info['informant_id'];

	$story_info[$story_row['story_id']] = $story_row;

}
mysql_free_result($stories_result);

foreach ($story_info as $story_id=>$story_row) {

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

}

ksort($place_id_to_stories);

foreach ($place_id_to_stories as $place_id=>$story_ids) {

	fwrite($edgefile, $place_id + $max_story_id . ': ');

	foreach ($story_ids as $story_id)
		fwrite($edgefile, $story_id . '(1),');

	fwrite($edgefile, ";\n");

	fwrite($labelfile, $place_id + $max_story_id . ' [label="' . $places_info[$place_id]['name'] . '" type="place_recorded" place_id="' . $place_id . '" ];' ."\n");

}

fclose($edgefile);
fclose($labelfile);

mysql_close($dblink); 

?>

<?php
include("bootstrap.php");

$edgefile = fopen('danishInformantToStories.wadj', 'w+');
$labelfile = fopen('danishInformantToStories.wadj.labels', 'w+');

$story_info = array();

$informant_id_to_stories = array();

$stories_query = "SELECT * FROM story";
$stories_result = mysql_query($stories_query) or die('Stories query failed ' . mysql_error());
while ($story_row = mysql_fetch_assoc($stories_result)) {

	$story_info[$story_row['story_id']] = $story_row;

}

$informant_to_stories_query = "SELECT DISTINCT story_id, informant_id, secondary FROM story_to_informant WHERE secondary=0";
$informant_to_stories_result = mysql_query($informant_to_stories_query) or die('Informant to stories query failed ' . mysql_error());

while ($informant_to_stories_info = mysql_fetch_assoc($informant_to_stories_result)) {
	if (array_key_exists($informant_to_stories_info['informant_id'], $informant_id_to_stories))
		$informant_id_to_stories[$informant_to_stories_info['informant_id']][] = $informant_to_stories_info['story_id'];
	else	
		$informant_id_to_stories[$informant_to_stories_info['informant_id']] = array($informant_to_stories_info['story_id']);

	$story_info[$informant_to_stories_info['story_id']]['informant_id'] = $informant_to_stories_info['informant_id'];
}		

mysql_free_result($stories_result);

foreach ($story_info as $story_id=>$story_row) {

	if ($story_row['place_recorded_id'] == "")
		$story_row['place_recorded_id'] == "0";

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

ksort($informant_id_to_stories);

$null_informant_id = $max_story_id + 1;

foreach ($informant_id_to_stories as $informant_id=>$story_ids) {

	sort($story_ids);

	if ($informant_id != 0) {
	
		fwrite($edgefile, $informant_id + $null_informant_id . ': ');

		foreach ($story_ids as $story_id)
			fwrite($edgefile, $story_id . '(1),');

		fwrite($edgefile, ";\n");

		$occupation = "";
		if (array_key_exists($informant_id, $occupation_info))
			$occupation = $occupation_info[$informant_id];

		$gender = "N/A";
		if ($people_info[$informant_id]['gender'] == "m")
			$gender = "male";
		if ($people_info[$informant_id]['gender'] == "f")
			$gender = "female";

		fwrite($labelfile, $informant_id + $null_informant_id . ' [label="' . $people_info[$informant_id]['first_name'] . " " . $people_info[$informant_id]['last_name'] . '" type="informant" person_id="' . $informant_id . '" informant_occupation="' . $occupation . '" informant_gender="' . $gender . '" ];' ."\n");

	} else {

		fwrite($edgefile, $null_informant_id . ': ');

		foreach ($story_ids as $story_id)
			fwrite($edgefile, $story_id . '(1),');

		fwrite($edgefile, ";\n");

		fwrite($labelfile, $null_informant_id . ' [label="Unknown informant" type="informant" person_id="0" informant_occupation="" informant_gender="N/A" ];' ."\n");

	}

}

fclose($edgefile);
fclose($labelfile);

mysql_close($dblink); 

?>

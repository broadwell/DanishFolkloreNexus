<?php
include("bootstrap.php");

$edgefile = fopen('story_to_story2.txt', 'w+');
$labelfile = fopen('danishStoryToStory.wadj.labels', 'w+');

$story_info = array();
$story_to_stories = array();

$stories_query = "SELECT * FROM story";
$stories_result = mysql_query($stories_query) or die('Stories query failed ' . mysql_error());

/*while ($story_row = mysql_fetch_assoc($stories_result)) {

	$informant_id_query = "SELECT informant_id FROM story_to_informant WHERE story_id=".$story_row['story_id']." AND secondary=0";
	$informant_id_result = mysql_query($informant_id_query) or die('Informant ID query failed ' . mysql_error());
	$informant_info = mysql_fetch_assoc($informant_id_result);
	$story_row['informant_id'] = $informant_info['informant_id'];

	$story_info[$story_row['story_id']] = $story_row;
 */	
#	$related_stories_query = "SELECT * FROM story_to_story WHERE story_id_1=".$story_row['story_id']." OR story_id_2=".$story_row['story_id'];
	$related_stories_query = "SELECT * FROM story_to_story";
	$related_stories_result = mysql_query($related_stories_query) or die('Related stories query failed ' . mysql_error());

	while ($relation_row = mysql_fetch_assoc($related_stories_result)) {
/*
		if ($relation_row['story_id_1'] == $story_row['story_id'])
			$related_story_id = $relation_row['story_id_2'];
		if ($relation_row['story_id_2'] == $story_row['story_id'])
			$related_story_id = $relation_row['story_id_1'];

		if ($related_story_id == $story_row['story_id'])
			continue;
 */
		/*
		if (!array_key_exists($story_row['story_id_1'], $story_to_stories)) {
			$story_to_stories[$story_row['story_id']] = array($related_story_id);
		} else {
			if (!in_array($related_story_id, $story_to_stories[$story_row['story_id']]))
				$story_to_stories[$story_row['story_id']][] = $related_story_id;
		}
		 */
		fwrite($edgefile, $relation_row['story_id_1'] . ' ' . $relation_row['story_id_2'] . "\n");
	}
	mysql_free_result($related_stories_result);
/*}
mysql_free_result($stories_result);
 */

fclose($edgefile);
exit();

foreach ($story_to_stories as $story_id=>$related_stories) {

        if (array_key_exists($story_id, $etk_index_info))
                $etk_index = $etk_index_info[$story_id];
        else
                $etk_index = "0";

        if (array_key_exists($story_id, $genre_info))
                $genre_id = $genre_info[$story_id];
        else
                $genre_id = "0";

	/*
	$tango_indices = "";
	if (array_key_exists($story_id, $tango_index_info)) {
		foreach($tango_index_info[$story_id] as $t_index)
			$tango_indices = $tango_indices . $t_index . " ";
	}
	 */

	$occupation = "0";
	if (array_key_exists($story_info[$story_id]['informant_id'], $occupation_info))
		$occupation = $occupation_info[$story_info[$story_id]['informant_id']];

	$gender = "0";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "m")
	        $gender = "1";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "f")
                $gender = "2";

	if ($story_info[$story_id]['informant_id'] == "")
		$informant_id = "0";
	else
		$informant_id = $story_info[$story_id]['informant_id'];

	if ($story_info[$story_id]['fieldtrip_id'] == "")
		$fieldtrip_id = "0";
	else
		$fieldtrip_id = $story_info[$story_id]['fieldtrip_id'];

	if ($story_info[$story_id]['place_recorded_id'] == "")
		$place_recorded_id = "0";
	else
		$place_recorded_id = $story_info[$story_id]['place_recorded_id'];
/*
	fwrite($labelfile, $story_id . ' [label="' . $story_info[$story_id]['publication_info'] . '" type="0" story_id="' . $story_id . '" informant_id="' . $informant_id . '" informant_occupation="' . $occupation . '" informant_gender="' . $gender . '" place_recorded_id="' . $place_recorded_id .'" fieldtrip_id="' . $fieldtrip_id . '" etk_index_id="'. $etk_index . '" genre_id="' . $genre_id . '"'); 

	fwrite($labelfile, ' URL="http://localhost/danishCategories.php?sid=' . $story_id . '&tid=0&iid=' . $informant_id . '&occid=' . $occupation . '&gnid=' . $gender . '&prid=' . $place_recorded_id . '&fid=' . $fieldtrip_id . '&eid=' . $etk_index . '&grid=' . $genre_id . '" ];'."\n");
 */
#	fwrite($edgefile, $story_id . ': ');

	foreach ($related_stories as $related_story) {

		fwrite($edgefile, $story_id . ' ' . $related_story . "\n");

#		fwrite($edgefile, $related_story . '(1),');

	}

}

fclose($edgefile);
fclose($labelfile);

mysql_close($dblink);

?>

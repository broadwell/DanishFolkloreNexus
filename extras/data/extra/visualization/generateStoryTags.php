<?php
include("bootstrap.php");

$use_extra_info = false;

$tagfile = fopen('danishStoryTags.txt', 'w+');

$story_info = array();
$story_to_stories = array();

$stories_query = "SELECT * FROM story ORDER BY story_id";
$stories_result = mysql_query($stories_query) or die('Stories query failed ' . mysql_error());

$edges = array();
$edgeweights = array();
$story_to_keywords = array();

$informant_ids = array();
$story_to_informant = array();

$story_to_place_recorded = array();
$place_recorded_ids = array();

$story_to_places_mentioned = array();

while ($story_row = mysql_fetch_assoc($stories_result)) {

	$place_mentioned_ids = array();

	$story_id = $story_row['story_id'];

	fwrite($tagfile, $story_id . ":");

	$places_mentioned_query = "SELECT * from story_to_place_mentioned WHERE story_id = ".$story_id;
	$places_mentioned_result = mysql_query($places_mentioned_query) or die('Places mentioned query failed ' . mysql_error());
	while ($place_mentioned_row = mysql_fetch_assoc($places_mentioned_result)) {
		if (!array_key_exists($story_id, $story_to_places_mentioned))
			$story_to_places_mentioned[$story_id] = array($place_mentioned_row['place_id']);
		else
			$story_to_places_mentioned[$story_id][] = $place_mentioned_row['place_id'];

		if ((!in_array($place_mentioned_row['place_id'], $place_mentioned_ids)) && $place_mentioned_row['place_id'] != "0")
			$place_mentioned_ids[] = $place_mentioned_row['place_id'];
	}

	$informant_id_query = "SELECT informant_id FROM story_to_informant WHERE story_id=".$story_id." AND secondary=0";
	$informant_id_result = mysql_query($informant_id_query) or die('Informant ID query failed ' . mysql_error());
	$informant_info = mysql_fetch_assoc($informant_id_result);
	$story_row['informant_id'] = $informant_info['informant_id'];

	$story_info[$story_id] = $story_row;

	$related_stories_query = "SELECT * FROM story_to_story WHERE story_id_1=".$story_id." OR story_id_2=".$story_id;
	$related_stories_result = mysql_query($related_stories_query) or die('Related stories query failed ' . mysql_error());

	while ($relation_row = mysql_fetch_assoc($related_stories_result)) {

		if ($relation_row['story_id_1'] == $story_id)
			$related_story_id = $relation_row['story_id_2'];
		if ($relation_row['story_id_2'] == $story_id)
			$related_story_id = $relation_row['story_id_1'];

		if ($related_story_id == $story_id)
			continue;

		if (!array_key_exists($story_id, $story_to_stories)) {
			$story_to_stories[$story_id] = array($related_story_id);
		} else {
			if (in_array($related_story_id, $story_to_stories[$story_id])) {
				continue;
			} else {
				$story_to_stories[$story_id][] = $related_story_id;
			}
		}

		if ($story_id < $related_story_id)
			fwrite($tagfile, 's' . $story_id . '_to_s' . $related_story_id . ' ');
		else
			fwrite($tagfile, 's' . $related_story_id . '_to_s' . $story_id . ' ');


	}
	mysql_free_result($related_stories_result);

	$related_keywords_query = "SELECT * FROM story_to_keyword WHERE story_id=".$story_id;
	$related_keywords_result = mysql_query($related_keywords_query) or die('Keywords query failed ' . mysql_error());
	while ($related_keyword_row = mysql_fetch_assoc($related_keywords_result)) {

		if (!array_key_exists($story_id, $story_to_keywords)) {
			$story_to_keywords[$story_id] = array($related_keyword_row['keyword_id']);
		} else {
			if (in_array($related_keyword_row['keyword_id'], $story_to_keywords))
				continue;
			$story_to_keywords[$story_id][] = $related_keyword_row['keyword_id'];
		}

		fwrite($tagfile, $keywords[$related_keyword_row['keyword_id']]['keyword'] . ' ');

	}
	mysql_free_result($related_keywords_result);

	# Put info about all of the stories in the labels file

        if (array_key_exists($story_id, $etk_index_info)) {
		$etk_index_id = $etk_index_info[$story_id];
		fwrite($tagfile, 'etk_index_' . $etk_index_id . ' ');
	}

        if (array_key_exists($story_id, $genre_info)) {
		$genre_id = $genre_info[$story_id];
		$story_to_genre[$story_id] = $genre_id;
		fwrite($tagfile, 'genre_' . $genre_id . ' ');
	} else {
		$genre_id = "0";
	}

	$gender = "0";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "m")
	        $gender = "1";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "f")
		$gender = "2";

	if (($gender != "0") && $use_extra_info)
		fwrite($tagfile, 'gender_' . $gender . ' ');

	if ($story_info[$story_id]['core_or_variant'] == "variant")
		$core_or_variant = "1";
	else
		$core_or_variant = "0";

	if (($story_info[$story_id]['informant_id'] == "") || ($story_info[$story_id]['informant_id'] == "0")) {
		$informant_id = "0";
	} else {
		$informant_id = $story_info[$story_id]['informant_id'];
		if ($use_extra_info)
			fwrite($tagfile, 'informant_' . $informant_id . ' ');

		$story_to_informant[$story_id] = $people_info[$informant_id];
		if (!in_array($informant_id, $informant_ids))
			$informant_ids[] = $informant_id;
	}

	if (($informant_id != "0") && (array_key_exists($informant_id, $occupation_info))) {
		foreach ($occupation_info[$informant_id] as $occupation_row)
			if (($occupation_row['occupation_id'] != "0") && $use_extra_info)
				fwrite($tagfile, 'occupation_' . $occupation_row['occupation_id'] . ' ');
	}

	if ($story_info[$story_id]['fieldtrip_id'] == "")
		$fieldtrip_id = "0";
	else
		$fieldtrip_id = $story_info[$story_id]['fieldtrip_id'];

	if ($story_info[$story_id]['place_recorded_id'] == "") {
		$place_recorded_id = "0";
	} else {
		$place_recorded_id = $story_info[$story_id]['place_recorded_id'];
		if (($place_recorded_id != "0") && $use_extra_info)
			fwrite($tagfile, 'place_recorded_' . $place_recorded_id . ' ');
		$story_to_place_recorded[$story_id] = $place_recorded_id;
		if ((!in_array($place_recorded_id, $place_recorded_ids)) && ($place_recorded_id != "0"))
			$place_recorded_ids[] = $place_recorded_id;
	}

	foreach($place_mentioned_ids as $place_mentioned_id)
		fwrite($tagfile, 'place_mentioned_' . $place_mentioned_id . ' ');

	if (array_key_exists($story_id, $tango_index_info)) {
		foreach ($tango_index_info[$story_id] as $tango_index_id) {
			if ($tango_index_id != "130")
				fwrite($tagfile, 'tango_index_' . $tango_index_id . ' ');
		}
	}

	# Informant occupation?
	# Informant gender? (probably not)
	# Fieldtrip ID? (nope - captured by informant relation)

	fwrite($tagfile, "\n");
}
mysql_free_result($stories_result);

fclose($tagfile);

mysql_close($dblink);

?>

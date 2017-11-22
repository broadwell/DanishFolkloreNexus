<?php
include("bootstrap.php");

$matrixfile = fopen('ETK_Jaccard.txt', 'w+');

$informantIDtoInitials = array("150" => "BJK", "241" => "JPP", "235" => "KMP", "90" => "AMJ", "123" => "PJ");

$story_to_informant = array();

$story_to_informant_query = "SELECT story_id, informant_id FROM story_to_informant ORDER BY story_id";
$story_to_informant_result =  mysql_query($story_to_informant_query) or die('Story to informant query failed ' . mysql_error());
while ($story_to_informant_row = mysql_fetch_assoc($story_to_informant_result)) {
    $story_id = $story_to_informant_row['story_id'];
    $informant_id = $story_to_informant_row['informant_id'];

    if (array_key_exists($story_id, $story_to_informant)) {
        if (($story_to_informant[$story_id] == 0) && ($informant_id != 0)) {
            $story_to_informant[$story_id] = $informant_id;
        }
    } else {
        $story_to_informant[$story_id] = $informant_id;
    }
}

$story_ids = array();
$story_info = array();

$story_attribute_counts = array();

$story_label = array();

$keyword_ids = array();
$story_to_keywords = array();

$etk_index_ids = array();
$story_to_etk_indices = array();
$tango_index_ids = array();
$story_to_tango_indices = array();

$place_ids = array();
$story_to_places_mentioned = array();

$stories_query = "SELECT DISTINCT story_id, publication_info FROM story ORDER BY story_id";
$stories_result =  mysql_query($stories_query) or die('Story query failed ' . mysql_error());

$story_count = 0;

$total_relations = 0;

while ($story_row = mysql_fetch_assoc($stories_result)) {
    $story_id = $story_row['story_id'];
    $pub_info = $story_row['publication_info'];
    $informant_id = $story_to_informant[$story_id];

    $story_to_keywords[$story_id] = array();
    $story_to_etk_indices[$story_id] = array();
    $story_to_tango_indices[$story_id] = array();
    $story_to_places_mentioned[$story_id] = array();

    $story_ids[] = $story_id;

    $story_attribute_counts[$story_id] = 0;

    $formatted_story_id = str_pad($story_id, 3, "0", STR_PAD_LEFT);

    if (array_key_exists($informant_id, $informantIDtoInitials))
        $informant_initials = $informantIDtoInitials[$informant_id];
    else
        $informant_initials = "XXX";

    if (strpos($pub_info, "Unpub") === false)
        $english_pub_filename = $formatted_story_id."-".$informant_initials. "-".$pub_info;
    else
        $english_pub_filename = $formatted_story_id."-".$pub_info;

    $story_label[$story_id] = $english_pub_filename;
    
    $story_info[$story_id] = $story_row;

	$places_mentioned_query = "SELECT * from story_to_place_mentioned WHERE story_id = ".$story_id;
	$places_mentioned_result = mysql_query($places_mentioned_query) or die('Places mentioned query failed ' . mysql_error());
        while ($place_mentioned_row = mysql_fetch_assoc($places_mentioned_result)) {
            if ($place_mentioned_row['place_id'] == "0")
                continue;

                $total_relations++;
                $story_attribute_counts[$story_id]++;
		    $story_to_places_mentioned[$story_id][] = $place_mentioned_row['place_id'];

		if ((!in_array($place_mentioned_row['place_id'], $place_ids)) && $place_mentioned_row['place_id'] != "0")
			$place_ids[] = $place_mentioned_row['place_id'];
	}

	$related_keywords_query = "SELECT * FROM story_to_keyword WHERE story_id=".$story_id;
	$related_keywords_result = mysql_query($related_keywords_query) or die('Keywords query failed ' . mysql_error());
	while ($related_keyword_row = mysql_fetch_assoc($related_keywords_result)) {
            
           # $story_attribute_counts[$story_id]++;
            $story_attribute_counts[$story_id] += $related_keyword_row['frequency'];
            $total_relations = $total_relations + $related_keyword_row['frequency'];

            $story_to_keywords[$story_id][$related_keyword_row['keyword_id']] = $related_keyword_row['frequency'];
		if ((!in_array($related_keyword_row['keyword_id'], $keyword_ids)) && $related_keyword_row['keyword_id'] != "0")
			$keyword_ids[] = $related_keyword_row['keyword_id'];

	}
	mysql_free_result($related_keywords_result);

        $etk_indices_query = "SELECT * FROM story_to_etk_index WHERE story_id=".$story_id;
        $etk_indices_result = mysql_query($etk_indices_query) or die('ETK indices query failed ' . mysql_error());
	while ($etk_index_row = mysql_fetch_assoc($etk_indices_result)) {
            
            $total_relations++;
            $story_attribute_counts[$story_id]++;

                $story_to_etk_indices[$story_id][] = $etk_index_row['etk_index_id'];
		if ((!in_array($etk_index_row['etk_index_id'], $etk_index_ids)) && $etk_index_row['etk_index_id'] != "0")
			$etk_index_ids[] = $etk_index_row['etk_index_id'];
        }
        mysql_free_result($etk_indices_result);

        $tango_indices_query = "SELECT * FROM story_to_tango_index WHERE story_id=".$story_id;
        $tango_indices_result = mysql_query($tango_indices_query) or die('Tango indices query failed ' . mysql_error());
	while ($tango_index_row = mysql_fetch_assoc($tango_indices_result)) {
            $total_relations++;
            $story_attribute_counts[$story_id]++;
                $story_to_tango_indices[$story_id][] = $tango_index_row['tango_index_id'];
		if ((!in_array($tango_index_row['tango_index_id'], $tango_index_ids)) && $tango_index_row['tango_index_id'] != "0")
			$tango_index_ids[] = $tango_index_row['tango_index_id'];
        }
        mysql_free_result($tango_indices_result);

	# Put info about all of the stories in the labels file

#	if ($story_info[$story_id]['core_or_variant'] == "variant")
#		$core_or_variant = "1";
#	else
#		$core_or_variant = "0";
        
}
#mysql_free_result($stories_result);

echo "Total relations is " . $total_relations . "\n";

sort($story_ids);

// Compute the global weight for each attribute (keyword, place mentioned, etc.)

$keyword_gw = array();
$place_gw = array();
$etk_gw = array();
$tango_gw = array();

foreach ($keyword_ids as $attribID) {
    $gw_inverse = 0;
    foreach ($story_ids as $storyID) {
        if (array_key_exists($attribID, $story_to_keywords[$storyID])) {
            $Pij = $story_to_keywords[$storyID][$attribID] / $story_attribute_counts[$storyID];
            $gw_inverse += abs($Pij * log($Pij)) / $total_relations;
        }
    } 
    $keyword_gw[$attribID] = 1 - $gw_inverse;
    print "Global weight of keyword " . $attribID . " is " . $keyword_gw[$attribID] . "\n";
}

foreach ($place_ids as $attribID) {
    $gw_inverse = 0;
    foreach ($story_ids as $storyID) {
        if (in_array($attribID, $story_to_places_mentioned[$storyID])) {
            $Pij = 1 / $story_attribute_counts[$storyID];
            $gw_inverse += abs($Pij * log($Pij)) / $total_relations;
        }
    } 
    $place_gw[$attribID] = 1 - $gw_inverse;
    echo "Global weight of place " . $attribID . " is " . $place_gw[$attribID] . "\n";
}

foreach ($etk_index_ids as $attribID) {
    $gw_inverse = 0;
    foreach ($story_ids as $storyID) {
        if (in_array($attribID, $story_to_etk_indices[$storyID])) {
            $Pij = 1 / $story_attribute_counts[$storyID];
            $gw_inverse += abs($Pij * log($Pij)) / $total_relations;
        }
    } 
    $etk_gw[$attribID] = 1 - $gw_inverse;
}

foreach ($tango_index_ids as $attribID) {
    $gw_inverse = 0;
    foreach ($story_ids as $storyID) {
        if (in_array($attribID, $story_to_tango_indices[$storyID])) {
            $Pij = 1 / $story_attribute_counts[$storyID];
            $gw_inverse += abs($Pij * log($Pij)) / $total_relations;
        }
    } 
    $tango_gw[$attribID] = 1 - $gw_inverse;
}

foreach ($story_ids as $storyID) {

    fwrite($matrixfile, $story_label[$storyID] . " ");

    if ($story_label[$storyID] == "")
        echo "ERROR: nonexistent story label for ID " . $storyID . "\n";

    echo $story_label[$storyID] . ": " . $story_attribute_counts[$storyID] . "\n";

}
fwrite($matrixfile, "\n");

foreach ($story_ids as $storyID) {
    
    foreach ($story_ids as $relatedStoryID) {

        $jaccardSimilarity = ComputeJaccard($storyID, $relatedStoryID);

        fwrite($matrixfile, $jaccardSimilarity . " ");

    }
    
    fwrite($matrixfile, "\n");

}

function ComputeJaccard($story1, $story2) {

    global $keyword_ids, $place_ids, $etk_index_ids, $tango_index_ids, $story_to_keywords, $story_to_places_mentioned, $story_to_etk_indices, $story_to_tango_indices, $keyword_gw, $place_gw, $etk_gw, $tango_gw;

    $BASEVAL = log(2);

    $jaccardNumerator = 0;
    $jaccardDenominator = 0;

    foreach (array_unique(array_merge(array_keys($story_to_keywords[$story1]), array_keys($story_to_keywords[$story2]))) as $attribID) {
        if (array_key_exists($attribID, $story_to_keywords[$story1]))
            $local_weight1 = log(1 + $story_to_keywords[$story1][$attribID]);
        else
            $local_weight1 = 0;
        if (array_key_exists($attribID, $story_to_keywords[$story2]))
            $local_weight2 = log(1 + $story_to_keywords[$story2][$attribID]);
        else
            $local_weight2 = 0;

        if (($local_weight1 != 0) && ($local_weight2 != 0))
            $jaccardNumerator += min(($keyword_gw[$attribID] * $local_weight1), ($keyword_gw[$attribID] * $local_weight2));

        $jaccardDenominator += max(($keyword_gw[$attribID] * $local_weight1), ($keyword_gw[$attribID] * $local_weight2));
    }
    foreach (array_unique(array_merge($story_to_places_mentioned[$story1], $story_to_places_mentioned[$story2])) as $attribID) {
        if (in_array($attribID, $story_to_places_mentioned[$story1]))
            $local_weight1 = $BASEVAL;
        else
            $local_weight1 = 0;
        if (in_array($attribID, $story_to_places_mentioned[$story2]))
            $local_weight2 = $BASEVAL;
        else
            $local_weight2 = 0;

        if (($local_weight1 != 0) && ($local_weight2 != 0))
            $jaccardNumerator += min(($place_gw[$attribID] * $local_weight1), ($place_gw[$attribID] * $local_weight2));

        $jaccardDenominator += max(($place_gw[$attribID] * $local_weight1), ($place_gw[$attribID] * $local_weight2));
    }
    foreach (array_unique(array_merge($story_to_etk_indices[$story1], $story_to_etk_indices[$story2])) as $attribID) {
        if (in_array($attribID, $story_to_etk_indices[$story1]))
            $local_weight1 = $BASEVAL;
        else
            $local_weight1 = 0;
        if (in_array($attribID, $story_to_etk_indices[$story2]))
            $local_weight2 = $BASEVAL;
        else
            $local_weight2 = 0;

        if (($local_weight1 != 0) && ($local_weight2 != 0))
            $jaccardNumerator += min(($etk_gw[$attribID] * $local_weight1), ($etk_gw[$attribID] * $local_weight2));

        $jaccardDenominator += max(($etk_gw[$attribID] * $local_weight1), ($etk_gw[$attribID] * $local_weight2));
    }
    
    foreach (array_unique(array_merge($story_to_tango_indices[$story1], $story_to_tango_indices[$story2])) as $attribID) {
        if (in_array($attribID, $story_to_tango_indices[$story1]))
            $local_weight1 = $BASEVAL;
        else
            $local_weight1 = 0;
        if (in_array($attribID, $story_to_tango_indices[$story2]))
            $local_weight2 = $BASEVAL;
        else
            $local_weight2 = 0;

        if (($local_weight1 != 0) && ($local_weight2 != 0))
            $jaccardNumerator += min(($tango_gw[$attribID] * $local_weight1), ($tango_gw[$attribID] * $local_weight2));

        $jaccardDenominator += max(($tango_gw[$attribID] * $local_weight1), ($tango_gw[$attribID] * $local_weight2));
    }

    return $jaccardNumerator / $jaccardDenominator;

}
	
fclose($matrixfile);

mysql_close($dblink);

?>

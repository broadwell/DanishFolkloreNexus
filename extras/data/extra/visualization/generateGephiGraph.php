<?php
include("bootstrap.php");

# Function to add an XML <name>value</name> node as a child of another node.
# Returns an identifier for this node that can be used to add children
# to it, or set name=value attributes via add_attribute()
# To specify a valueless node (just <name></name>) pass in a '' for $value
function add_child($dom, $base, $name, $value) {
    if ($value) {
        $value = str_replace('&', '&amp;', $value);
        $child = $base->appendChild($dom->createElement($name, $value));
    } else {
        $child = $base->appendChild($dom->createElement($name));
    }
    return $child;
}

# Function to assign an XML <element name=value>BLAH</element> attribute
# to an existing node. Returns an identifier for this attribute
function add_attribute($dom, $element, $name, $value) {
    $attr = $element->appendChild($dom->createAttribute($name));
    $attr->appendChild($dom->createTextNode($value));
    return $attr;
}

$graphdom = new DOMDocument('1.0');
$graphinfo = $graphdom->appendChild($graphdom->createElement('gexf'));

add_attribute($graphdom, $graphinfo, 'xmlns', "http://www.gexf.net/1.2draft");
add_attribute($graphdom, $graphinfo, 'version', "1.2");

$graphmeta = $graphinfo->appendChild($graphdom->createElement('meta'));
add_attribute($graphdom, $graphmeta, 'lastmodifieddate', '2011-08-09');
add_child($graphdom, $graphmeta, 'creator', 'PMB');
add_child($graphdom, $graphmeta, 'description', 'Danish Folklore 1.0 story hypergraph');

$graphsection = $graphinfo->appendChild($graphdom->createElement('graph'));
add_attribute($graphdom, $graphsection, 'mode', 'static');
add_attribute($graphdom, $graphsection, 'defaultedgetype', 'directed');

$graphnodes = $graphsection->appendChild($graphdom->createElement('nodes'));




$story_info = array();
$story_to_stories = array();

$stories_query = "SELECT * FROM story ORDER BY story_id";
$stories_result = mysql_query($stories_query) or die('Stories query failed ' . mysql_error());

$edges = array();
$edgeweights = array();
$story_to_keywords = array();

$informant_ids = array();
$story_to_informant = array();

$informant_to_place = array();

$story_to_place_recorded = array();
$place_recorded_ids = array();

$story_to_places_mentioned = array();
$place_mentioned_ids = array();

while ($story_row = mysql_fetch_assoc($stories_result)) {

	$story_id = $story_row['story_id'];

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

		if (!array_key_exists($story_id, $story_to_stories))
			$story_to_stories[$story_id] = array($related_story_id);
		else
			$story_to_stories[$story_id][] = $related_story_id;

	}
	mysql_free_result($related_stories_result);

	$related_keywords_query = "SELECT * FROM story_to_keyword WHERE story_id=".$story_id;
	$related_keywords_result = mysql_query($related_keywords_query) or die('Keywords query failed ' . mysql_error());
	while ($related_keyword_row = mysql_fetch_assoc($related_keywords_result)) {

		if (!array_key_exists($story_id, $story_to_keywords))
			$story_to_keywords[$story_id] = array($related_keyword_row);
		else
			$story_to_keywords[$story_id][] = $related_keyword_row;

	}
	mysql_free_result($related_keywords_result);

	# Put info about all of the stories in the labels file

        if (array_key_exists($story_id, $etk_index_info))
                $etk_index = $etk_index_info[$story_id];
        else
                $etk_index = "0";

        if (array_key_exists($story_id, $genre_info)) {
		$genre_id = $genre_info[$story_id];
		$story_to_genre[$story_id] = $genre_id;
	} else {
		$genre_id = "0";
	}
/*
	$occupation = "0";
	if (array_key_exists($story_info[$story_id]['informant_id'], $occupation_info)) {
		$occupation = $occupation_info[$story_info[$story_id]['informant_id']]['occupation_id'];
		$informant_to_occupation[$story_info[$story_id]['informant_id']] = $occupations[$occupation];
	}
 */
	$gender = "0";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "m")
	        $gender = "1";
        if ($people_info[$story_info[$story_id]['informant_id']]['gender'] == "f")
                $gender = "2";

	if ($story_info[$story_id]['core_or_variant'] == "variant")
		$core_or_variant = "1";
	else
		$core_or_variant = "0";

	if (($story_info[$story_id]['informant_id'] == "") || ($story_info[$story_id]['informant_id'] == "0")) {
		$informant_id = "0";
	} else {
		$informant_id = $story_info[$story_id]['informant_id'];
		$story_to_informant[$story_id] = $people_info[$informant_id];
		if (!in_array($informant_id, $informant_ids))
			$informant_ids[] = $informant_id;
	}

	if ($story_info[$story_id]['fieldtrip_id'] == "")
		$fieldtrip_id = "0";
	else
		$fieldtrip_id = $story_info[$story_id]['fieldtrip_id'];

	if ($story_info[$story_id]['place_recorded_id'] == "") {
		$place_recorded_id = "0";
	} else {
		$place_recorded_id = $story_info[$story_id]['place_recorded_id'];
		$story_to_place_recorded[$story_id] = $place_recorded_id;
		if ((!in_array($place_recorded_id, $place_recorded_ids)) && ($place_recorded_id != "0"))
			$place_recorded_ids[] = $place_recorded_id;
	}

$storynode = $graphnodes->appendChild($graphdom->createElement('node'));
add_attribute($graphdom, $storynode, 'id', $story_id);
add_attribute($graphdom, $storynode, 'label', $story_info[$story_id]['publication_info'] . ' (story ID ' . $story_id . ')');

//        fwrite($labelfile, $story_id . ' [label="' . $story_info[$story_id]['publication_info'] . ' (story ID ' . $story_id . ')" type="1" story_id="' . $story_id . '" informant_id="' . $informant_id . '" informant_gender="' . $gender . '" place_recorded_id="' . $place_recorded_id .'" fieldtrip_id="' . $fieldtrip_id . '" etk_index_id="'. $etk_index . '" genre_id="' . $genre_id . '" core_or_variant="' . $core_or_variant . '"'); 

//	fwrite($labelfile, ' URL="http://localhost/danishCategories.php?sid=' . $story_id . '&tid=0&iid=' . $informant_id . '&gnid=' . $gender . '&prid=' . $place_recorded_id . '&fid=' . $fieldtrip_id . '&eid=' . $etk_index . '&grid=' . $genre_id . '" ];'."\n");
}
#mysql_free_result($stories_result);

/* Add the explicit story to story edges (undirectd) to the edgelist  */
/*foreach ($story_to_stories as $story_id=>$related_stories) {

	if (!array_key_exists($story_id, $edges))
		$edges[$story_id] = array();

	foreach ($related_stories as $related_story) {
		$edges[$story_id][] = $related_story;
	}

}
 */

$matrixFile = file_get_contents('DFLmatrix.txt');

$cosineMatrix = explode("\n", $matrixFile);

$labelsRow = $cosineMatrix[0];

$labels = explode(' ', $labelsRow);

$storyIDs = array();
$storyID = 0;
$relatedStoryID = 0;
$rowToStoryID = array();
$storyIDtoLabel = array();
$rowNumber = 1;

foreach ($labels as $label) {
    $labelParts = explode('-', $label);
    $storyID = $labelParts[0] + 0;

    $rowToStoryID[$rowNumber] = $storyID;

#    echo $rowNumber . " => " . $storyID . "\n";

    $storyIDtoLabel[$storyID] = $label;
    $rowNumber++;
}

for ($rowNumber=1; $rowNumber<(sizeof($cosineMatrix)-1); $rowNumber++) {

    $row = $cosineMatrix[$rowNumber];

    $storyID = $rowToStoryID[$rowNumber];

    $columns = explode(' ', $row);

    for ($colNumber=0; $colNumber<sizeof($columns); $colNumber++) {

        $relatedStoryID = $rowToStoryID[$colNumber+1];

        $weight = $columns[$colNumber];

        /* Add the edges to the story-to-story edges array. Note that the
         * relations in this array will automatically be two-way (undirected)
         * because the cosine similarity matrix duplicates each relation on
         * either side of the main diagonal. */
        if (($weight > .2) && ($weight != 1)) {

            /* Make sure the array key existsin the main edges array, since
               the story-to-story array info will be tacked onto the end of each
               of its rows in the edgelist file. */ 
            if (!array_key_exists($storyID, $edges))
		$edges[$storyID] = array();

            $edges[$storyID][] = $relatedStoryID;
            $edgeweights[$storyID . "," . $relatedStoryID] = $weight;
        }

    }
}

/* Write the Tango index info to the labels file */

foreach($tango_indices as $tango_index_id=>$tango_index_row) {
    $tangonode = $graphnodes->appendChild($graphdom->createElement('node'));
    add_attribute($graphdom, $tangonode, 'id', $tango_index_id + $max_story_id);
    add_attribute($graphdom, $tangonode, 'label', $tango_index_row['type'] . ": " . $tango_index_row['name'] . ' (Tango index)');
//	fwrite($labelfile, $tango_index_id + $max_story_id . ' [label="' . $tango_index_row['type'] . ': ' . $tango_index_row['name'] . ' (Tango index)" tango_index_id="' . $tango_index_id . '" type="2" ];'."\n");
}

/* Add the story to Tango index edges (undirected) to the edgelist */

foreach($tango_index_info as $story_id=>$tango_index_ids) {

	foreach ($tango_index_ids as $tango_index_id) {

		$tango_index_node = $tango_index_id + $max_story_id;

		if (!array_key_exists($story_id, $edges)) {
			echo 'MISSING STORY for story->tango index: ' . $story_id . "\n";
			if (array_key_exists($story_id, $story_info))
				$edges[$story_id] = array($tango_index_node);
			else
				continue;
		} else {
			$edges[$story_id][] = $tango_index_node;
		}

		if (array_key_exists($tango_index_node, $edges))
			$edges[$tango_index_node][] = $story_id;
		else
			$edges[$tango_index_node] = array($story_id);
	}

}

$max_tango_node = $max_story_id + max(array_keys($tango_indices));

/* Write the keyword info to the labels file */

foreach ($keywords as $keyword_id=>$keyword_row) {
    $keywordnode = $graphnodes->appendChild($graphdom->createElement('node'));
    add_attribute($graphdom, $keywordnode, 'id', $keyword_id + $max_tango_node);
    add_attribute($graphdom, $keywordnode, 'label', $keyword_row['display_string'] . ' (keyword)');
//	fwrite($labelfile, $keyword_id + $max_tango_node . ' [label="' . $keyword_row['display_string'] . ' (keyword)" keyword_id="' . $keyword_id . '" type="3" ];' . "\n");
}

/* Add the story to keyword edges (undirected) to the edgelist */

foreach ($story_to_keywords as $story_id=>$keyword_rows) {

	foreach ($keyword_rows as $keyword_row) {

		$keyword_node = $keyword_row['keyword_id'] + $max_tango_node;
	
		if (!array_key_exists($story_id, $edges)) {
			echo 'MISSING STORY for story->keyword: ' . $story_id . "\n";
			if (array_key_exists($story_id, $story_info))
				$edges[$story_id] = array($keyword_node);
			else
				continue;
		} else {
			$edges[$story_id][] = $keyword_node;
		}

		if (array_key_exists($keyword_node, $edges))
			$edges[$keyword_node][] = $story_id;
		else
			$edges[$keyword_node] = array($story_id);

		if (array_key_exists($story_id . "," . $keyword_node, $edgeweights))
			echo "DUPLICATE EDGEWEIGHT!: " . $story_id . ", " . $keyword_node . "\n";
		else
			$edgeweights[$story_id . "," . $keyword_node] = $keyword_row['frequency'];

		if (array_key_exists($keyword_node . "," . $story_id, $edgeweights))
			echo "DUPLICATE EDGEWEIGHT!: " . $keyword_node . ", " . $story_id . "\n";
		else
			$edgeweights[$keyword_node . "," . $story_id] = $keyword_row['frequency'];

	}

}

$max_keyword_node = $max_tango_node + max(array_keys($keywords));

/* Write the ETK index info to the labels file */

foreach ($etk_info as $etk_index_id=>$etk_index_row) {
    $etknode = $graphnodes->appendChild($graphdom->createElement('node'));
    add_attribute($graphdom, $etknode, 'id', $etk_index_id + $max_keyword_node);
    add_attribute($graphdom, $etknode, 'label', $etk_index_row['heading_english'] . ' (ETK index)');
//	fwrite($labelfile, $etk_index_id + $max_keyword_node . ' [label="' . $etk_index_row['heading_english'] . ' (ETK index)" etk_index_id="' . $etk_index_id . '" type="4" ];' . "\n");
}

/* Add the story to ETK index edges (undirected) to the edgelist */

foreach ($etk_index_info as $story_id=>$etk_index_id) {

	$etk_index_node = $etk_index_id + $max_keyword_node;

	if (!array_key_exists($story_id, $edges)) {
		echo 'MISSING STORY for story->ETK index: ' . $story_id . "\n";
		if (array_key_exists($story_id, $story_info))
			$edges[$story_id] = array($etk_index_node);
		else
			continue;
	} else {
		$edges[$story_id][] = $etk_index_node;
	}

	if (array_key_exists($etk_index_node, $edges))
		$edges[$etk_index_node][] = $story_id;
	else
		$edges[$etk_index_node] = array($story_id);
}

$max_etk_index_node = $max_keyword_node + max(array_keys($etk_info));

/* Add the genres info to the labels file */
/*
foreach($genres_info as $genre_id=>$genre_row) {
    $genrenode = $graphnodes->appendChild($graphdom->createElement('node'));
    add_attribute($graphdom, $genrenode, 'id', $genre_id + $max_etk_index_node);
    add_attribute($graphdom, $genrenode, 'label', $genre_row['name'] . ' (genre)');
//	fwrite($labelfile, $genre_id + $max_etk_index_node . ' [label="' . $genre_row['name'] . ' (genre)" genre_id="' . $genre_id . '" type="2" ];' . "\n");
}
 */
/* Add the story to genre edges (undirected) to the edgelist */
/*
foreach ($genre_info as $story_id=>$genre_id) {

	$genre_node = $genre_id + $max_etk_index_node;

	if (!array_key_exists($story_id, $edges)) {
		echo 'MISSING STORY for story->genre index: ' . $story_id . "\n";
		if (array_key_exists($story_id, $story_info))
			$edges[$story_id] = array($genre_node);
		else
			continue;
	} else {
		$edges[$story_id][] = $genre_node;
	}	


	if (array_key_exists($genre_node, $edges))
		$edges[$genre_node][] = $story_id;
	else
		$edges[$genre_node] = array($story_id);

}
 */
//$max_genre_node = $max_etk_index_node + max(array_keys($genres_info));
$max_genre_node = $max_etk_index_node;
echo "Max genre node is $max_genre_node\n";

/* Add the informants to the labels file */
/*
sort($informant_ids);

foreach ($informant_ids as $informant_id) {
	fwrite($labelfile, $informant_id + $max_genre_node . ' [label="' . $people_info[$informant_id]['first_name'] . ' ' . $people_info[$informant_id]['last_name'] . ' (informant ID ' . $informant_id . ')" person_id="' . $informant_id . '" type="5" ];' . "\n");
}
 */ 
/* Add the story to informant edges (undirected) to the edgelist */
/*
foreach ($story_to_informant as $story_id=>$informant_row) {
	if ($informant_row['person_id'] == "0")
		echo "PERSON ID IS 0 for story $story_id\n";
	
	$informant_node = $informant_row['person_id'] + $max_genre_node;

	if (!array_key_exists($story_id, $edges)) {
		echo 'MISSING STORY for story->informant: ' . $story_id . "\n";
		if (array_key_exists($story_id, $story_info))
			$edges[$story_id] = array($informant_node);
		else
			continue;
	} else {
		$edges[$story_id][] = $informant_node;
	}
#	echo "Added edge between story $story_id and informant $informant_node\n";

	if (!array_key_exists($informant_node, $edges))  {
		$edges[$informant_node] = array($story_id);
	} else {
		if (!in_array($story_id, $edges[$informant_node]))
			$edges[$informant_node][] = $story_id;
	}
	echo "Added edge between informant $informant_node and story $story_id\n";

}

$max_informant_node = $max_genre_node + max($informant_ids);
echo "Max informant node is $max_informant_node\n";
 */
/* Add the occupations to the labels file */
/*
foreach ($occupations as $occupation_id=>$occupation_row) {
	fwrite($labelfile, $occupation_id + $max_informant_node . ' [label="' . $occupation_row['name'] . ' (occupation ID ' . $occupation_id . ')" occupation_id="' . $occupation_id . '" type="6" ];' . "\n");
}

sort($informant_ids);
 */
/* Add the informant to occupations edges (undirected) to the edgelist */
/*
foreach ($informant_ids as $informant_id) {

	if (array_key_exists($informant_id, $occupation_info)) {
		foreach ($occupation_info[$informant_id] as $occupation_row) {
			$occupation_node = $max_informant_node + $occupation_row['occupation_id'];
			$edges[$informant_id + $max_genre_node][] = $occupation_node;

			if (!array_key_exists($occupation_node, $edges))
				$edges[$occupation_node] = array($informant_id + $max_genre_node);
			else
				$edges[$occupation_node][] = $informant_id + $max_genre_node;

		}
	}

}

$max_occupation_node = $max_informant_node + max(array_keys($occupations));
echo "Max occupation node is $max_occupation_node\n";
 */
/* Add the places recorded to the labels file */
/*
sort($place_recorded_ids);

foreach ($place_recorded_ids as $place_id) {
       
	$place_row = $places_info[$place_id];

	fwrite($labelfile, $place_id + $max_occupation_node . ' [label="' . $place_row['name'] . ' (place recorded ID ' . $place_id . ')" place_id="' . $place_id . '" type="7" longitude="' . $place_row['longitude'] . '" latitude="' . $place_row['latitude'] . '" ];' . "\n");

}
 */
/* Add the story to place recorded edges (undirected) to the edgelist */
/*
foreach ($story_to_place_recorded as $story_id=>$place_recorded_id) {
	$place_node = $max_occupation_node + $place_recorded_id;

	if (!array_key_exists($story_id, $edges)) {
		echo 'MISSING STORY for story->place recorded: ' . $story_id . "\n";
		if (array_key_exists($story_id, $story_info))
			$edges[$story_id] = array($place_node);
		else
			continue;
	} else {
		$edges[$story_id][] = $place_node;
	}
	echo "Added edge between story $story_id and place recorded $place_node\n";

	if (!array_key_exists($place_node, $edges)) {
		$edges[$place_node] = array($story_id);
	} else {
		if (!in_array($story_id, $edges[$place_node]))
			$edges[$place_node][] = $story_id;
	}

}

$max_place_recorded_node = $max_occupation_node + max($place_recorded_ids);
echo "Max place recorded node is $max_place_recorded_node\n";
 */

$max_place_recorded_node = $max_genre_node;
/* Add the places mentioned to the labels file */

sort($place_mentioned_ids);

foreach ($place_mentioned_ids as $place_id) {
       
	$place_row = $places_info[$place_id];
        
    $placenode = $graphnodes->appendChild($graphdom->createElement('node'));
    add_attribute($graphdom, $placenode, 'id', $place_id + $max_place_recorded_node);
    add_attribute($graphdom, $placenode, 'label', $place_row['name'] . ' (place mentioned ID ' . $place_id . ')');

//	fwrite($labelfile, $place_id + $max_place_recorded_node . ' [label="' . $place_row['name'] . ' (place mentioned ID ' . $place_id . ')" place_id="' . $place_id . '" type="5" longitude="' . $place_row['longitude'] . '" latitude="' . $place_row['latitude'] . '" ];' . "\n");

}

/* Add the story to place mentioned edges (undirected) to the edgelist */

foreach ($story_to_places_mentioned as $story_id=>$places_mentioned) {
	foreach ($places_mentioned as $place_mentioned_id) {
		$place_node = $max_place_recorded_node + $place_mentioned_id;

		if (!array_key_exists($story_id, $edges)) {
			echo 'MISSING STORY for story->place mentioned: ' . $story_id . "\n";
			if (array_key_exists($story_id, $story_info))
				$edges[$story_id] = array($place_node);
			else
				continue;
		} else {
			$edges[$story_id][] = $place_node;
		}
		echo "Added edge between story $story_id and place mentioned $place_node\n";

		if (!array_key_exists($place_node, $edges)) {
			$edges[$place_node] = array($story_id);
		} else {
			if (!in_array($story_id, $edges[$place_node]))
				$edges[$place_node][] = $story_id;
		}

	}
}

$max_place_mentioned_node = $max_place_recorded_node + max($place_mentioned_ids);
echo "Max place mentioned node is $max_place_mentioned_node\n";

# Now write ALL of the observed edges to the edgelist

$graphedges = $graphsection->appendChild($graphdom->createElement('edges'));

ksort($edges);

$edges_written = array();

$edge_id = 1;

foreach ($edges as $parent_node=>$children) {

//	fwrite($edgefile, $parent_node . ': ');

	foreach ($children as $child_node) {

		if (array_key_exists($parent_node, $edges_written) && (in_array($child_node, $edges_written[$parent_node])))
			echo "DUPLICATE EDGE: $parent_node -> $child_node\n";

		if (array_key_exists($parent_node . "," . $child_node, $edgeweights)) {
//			fwrite($edgefile, $child_node . '(' . $edgeweights[$parent_node . "," . $child_node] . '),');
                    $edge = $graphedges->appendChild($graphdom->createElement('edge'));
                    add_attribute($graphdom, $edge, 'id', $edge_id);
                    add_attribute($graphdom, $edge, 'source', $parent_node);
                    add_attribute($graphdom, $edge, 'target', $child_node);
                    add_attribute($graphdom, $edge, 'weight', $edgeweights[$parent_node . "," . $child_node]);
                    $edge_id++;
                } else if (array_key_exists($child_node . "," . $parent_node, $edgeweights)) {
                    add_attribute($graphdom, $edge, 'id', $edge_id);
                    add_attribute($graphdom, $edge, 'source', $parent_node);
                    add_attribute($graphdom, $edge, 'target', $child_node);
                    add_attribute($graphdom, $edge, 'weight', $edgeweights[$child_node . "," . $parent_node]);
                    $edge_id++;
//			fwrite($edgefile, $child_node . '(' . $edgeweights[$child_node . "," . $parent_node] . '),');
                } else {
//			fwrite($edgefile, $child_node . '(1),');
                    add_attribute($graphdom, $edge, 'id', $edge_id);
                    add_attribute($graphdom, $edge, 'source', $parent_node);
                    add_attribute($graphdom, $edge, 'target', $child_node);
                    add_attribute($graphdom, $edge, 'weight', '1');
                }

		if (array_key_exists($parent_node, $edges_written))
			$edges_written[$parent_node][] = $child_node;
		else
			$edges_written = array($child_node);
	}
//	fwrite($edgefile, ";\n");
	
}

//fclose($edgefile);
//fclose($labelfile);

$graphdom->formatOutput = true;
$graphdom->save('danishHyperGraph.gexf');

mysql_close($dblink);

?>

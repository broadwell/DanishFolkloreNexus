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
$encoding_query = "SET names \"utf8\"";
#$encoding_query = "SET names \"latin1\"";
$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed: ' . mysql_error());
/*
$matches_count = 0;
$stories_matched = array();
 */

function decode_heading($tango_index_name) {
	if ($tango_index_name == "Skt_Hans") {
		$tango_index_name = "Skt. Hans";
	} else {
		# String manipulation: replace underscores with
		# spaces, and SLASH with a /
		
		$tango_index_name = str_replace('_', ' ', $tango_index_name);
		$tango_index_name = str_replace('SLASH', '/', $tango_index_name);
	}
	return $tango_index_name;
}

function encode_heading($tango_index_name) {
	if ($tango_index_name == "Skt. Hans") {
		$tango_index_name = "Skt_Hans";
	} else {
		# String manipulation: replace underscores with
		# spaces, and SLASH with a /
		
		$tango_index_name = str_replace(' ', '_', $tango_index_name);
		$tango_index_name = str_replace('/', 'SLASH', $tango_index_name);
	}
	return $tango_index_name;
}


$info_query = "SELECT * FROM tango_variant_data ORDER BY story_id";
$info_result =  mysql_query($info_query) or die('Tango variant index data query failed: ' . mysql_error());
while ($info_row = mysql_fetch_assoc($info_result)) {

/*	echo "Info row keys: ";
	foreach ($info_row as $key=>$value) {
		echo $key . ", ";
	}
	break;
 */
	#$story_query = "SELECT DISTINCT story_id, publication_info FROM story WHERE publication_info REGEXP '^".$range_row['range_prefix'].".*'";
/*	$story_query = "SELECT DISTINCT story_id, publication_info FROM story WHERE publication_info LIKE '".$info_row['publication_info']."'";
	$story_result = mysql_query($story_query) or die('Tango index story query failed: ' . mysql_error());

	$matches_count = 0;
	while ($story_row = mysql_fetch_assoc($story_result)) {
		$matches_count++;
		$story_id = $story_row['story_id'];
	}

	if ($matches_count != 1) {
		echo "pub_info " . $info_row['publication_info'] . " matched " . $matches_count . " stories, skipping\n";
		mysql_free_result($story_result);
		continue;
	}
 */

	$story_id = $info_row['story_id'];

	echo "Working with story ID " . $story_id . ", pub_info " . $info_row['publication_info'] . "\n";

/*
	$story_assoc_query = 'UPDATE tango_variant_data SET story_id='.$story_id.' WHERE publication_info="'.$info_row['publication_info'].'" LIMIT 1';
	$story_assoc_result = mysql_query($story_assoc_query) or die ('Story association query failed: ' . mysql_error());

	continue;
 */
	$existing_genre = "";

	$existing_genre_query = "SELECT * FROM story_to_genre WHERE story_id='".$story_id."'";
	$existing_genre_result = mysql_query($existing_genre_query) or die('Existing genre query failed: ' . mysql_error());
	$existing_genre = mysql_fetch_assoc($existing_genre_result);
	mysql_free_result($existing_genre_result);

	$existing_indices = array();

	$existing_indices_query = "SELECT * FROM story_to_tango_index WHERE story_id='".$story_id."'";
	$existing_indices_result = mysql_query($existing_indices_query) or die('Existing indices query failed: ' . mysql_error());

	while ($existing_index_row = mysql_fetch_assoc($existing_indices_result))
		$existing_indices[] = $existing_index_row['tango_index_id'];

	foreach($info_row as $key => $value) {

		if (($key == "publication_info") || ($key == "tango_variant_data_id") || ($key == "Valborg") || ($key == "story_id"))
			continue;
		if ($key == "Genre") {
			# Skip if it's blank

			if ($value == "") {
				if ($existing_genre)
					echo "CONFLICT: Story " . $story_id . " (" . $info_row['publication_info'] . "): New genre is blank, but existing genre is genre #" . $existing_genre['genre_id'] . "\n";
				continue;
			}
			
			$genre_query = 'SELECT * FROM genre WHERE name="'.$value.'"';
			$genre_result = mysql_query($genre_query) or die('Genre query failed: ' . mysql_error());
			if ($genre_row = mysql_fetch_assoc($genre_result)) {
				$genre_id = $genre_row['genre_id'];

				if (!$existing_genre) {
					$new_story_genre_query = 'INSERT INTO story_to_genre (story_id, genre_id) VALUES ("'.$story_id.'", "'.$genre_id.'" )';
					echo "CHANGE: Story " . $story_id . " (" . $info_row['publication_info'] . "): Adding genre association: " . $value . ", ID " . $genre_id . "\n";
					mysql_query($new_story_genre_query) or die('Failed to add new story to genre association: ' . mysql_error());

				} else if ($genre_id != $existing_genre['genre_id']) {
					echo "CONFLICT: Story " . $story_id . " (" . $info_row['publication_info'] . "): New genre " . $genre_id . " (". $genre_row['name'] . ") different from old genre: " . $existing_genre['genre_id']. "\n";
					continue;
				} else if ($genre_id == $existing_genre['genre_id']) {
					echo 'New genre ID matches existing genre ID: ' . $genre_id . ", skipping\n";
					continue;
				}

			} else {
				echo "ERROR: Story " . $story_id . " (" . $info_row['publication_info'] . "): Unable to find genre match for " . $value . "\n";
				continue;
			}

		} else if ($key == "Resolution") {
			# If value is blank or weird, make it "None / Unspecified" 

			if (($value == "") || ($value == "None") || ($value == "No"))
				$value = "None / Unspecified";	

			$resolution_index_query = 'SELECT tango_index_id FROM tango_index WHERE type="Resolution" AND name="'.$value.'"';
			$resolution_index_result = mysql_query($resolution_index_query) or die('Resolution index query failed: ' . mysql_error());
			if ($resolution_index_row = mysql_fetch_assoc($resolution_index_result)) {

				$resolution_index_id = $resolution_index_row['tango_index_id'];

				# Make sure this association doesn't already exist (a story can only have one resolution type)

				$existing_resolution_query = 'SELECT DISTINCT tango_index_id, story_to_tango_index_id FROM story_to_tango_index WHERE story_id="'.$story_id.'" AND (tango_index_id="127" OR tango_index_id="128" OR tango_index_id="129" OR tango_index_id="130")';
				$existing_resolution_result = mysql_query($existing_resolution_query) or die('Existing resolution query failed: ' . mysql_error());

				// XXX PMB If there's a different resolution value and
				// the old one is 130, replace it!
				if (!($existing_resolution_row = mysql_fetch_assoc($existing_resolution_result))) {
					$resolution_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$resolution_index_id.'" )';
					echo "CHANGE: Story " . $story_id . " (" . $info_row['publication_info'] . "): Adding resolution: " . $value . ", Tango index " . $resolution_index_id . "\n";
					mysql_query($resolution_query) or die('Failed to add new story to Tango resolution association: ' . mysql_error());
				} else {
					if (($existing_resolution_row['tango_index_id'] != $resolution_index_id) && ($existing_resolution_row['tango_index_id'] != "130"))
						echo "CONFLICT: Story " . $story_id . " (". $info_row['publication_info'] . "): Resolution ID " . $existing_resolution_row['tango_index_id'] . " already exists for story, want new one to be: " . $resolution_index_id . "\n";
					else if ($existing_resolution_row['tango_index_id'] == $resolution_index_id)
						echo "New resolution matches existing ID: " . $resolution_index_id . ", skipping.\n";
					else if (($existing_resolution_row['tango_index_id'] != $resolution_index_id) && ($existing_resolution_row['tango_index_id'] == "130")) {
						echo "CHANGE: Story " . $story_id . " (" . $info_row['publication_info'] . "): Removing null resolution, adding resolution: " . $value . ", Tango index " . $resolution_index_id . "\n";
						$delete_query = 'DELETE FROM story_to_tango_index WHERE story_id="'.$story_id.'" AND tango_index_id="130"';
						mysql_query($delete_query) or die('Delete resolution unspecified query failed: ' . mysql_error());
						$resolution_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$resolution_index_id.'" )';
						mysql_query($resolution_query) or die('Failed to add new story to Tango resolution association: ' . mysql_error());
					}
				}

			}

		/* All other index types */

		} else {

			$tango_index_string = decode_heading($key);

			$tango_type_query = "SELECT tango_index_id, name FROM tango_index WHERE type='". $tango_index_string."'";
			$tango_type_result = mysql_query($tango_type_query) or die('Tango type query failed: ' . mysql_error());
			$parent_matched = false;
			$child_matched = false;
			$none_unspecified_index_id = 0;
			while ($index_row = mysql_fetch_assoc($tango_type_result)) {
				
				if ($value == "TRUE")
					$parent_matched = true;

				# An index type (parent) is True for this story.
				# See if any of its children are too
				if (array_key_exists(encode_heading($index_row['name']), $info_row) && ($info_row[encode_heading($index_row["name"])] == "TRUE")) {
					$child_matched = true;

#					echo "Matched parent and child index: " . $tango_index_string . "=>" . $index_row['name'] . ", Tango index " . $index_row["tango_index_id"] . "\n";

					if (in_array($index_row["tango_index_id"], $existing_indices)) {
						echo "Existing value for Tango index ". $index_row['tango_index_id'] ." (" . $tango_index_string . "=>" . $index_row['name'] .") is TRUE, new one is also TRUE, skipping\n";
						continue;
					}

					# Otherwise, add this entry to the DB
					
					echo "CHANGE: Story " . $story_id . " (" . $info_row['publication_info'] . "): Adding association for " . $tango_index_string . "=>" . $index_row['name'] . ", Tango index " . $index_row["tango_index_id"] . "\n";
					$name_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$index_row["tango_index_id"].'" )';
					mysql_query($name_query) or die('Failed to add new story to Tango name association: ' . mysql_error());
				
				} else {

					if (array_key_exists(encode_heading($index_row['name']), $info_row) && ($info_row[encode_heading($index_row["name"])] == "FALSE") && in_array($index_row["tango_index_id"], $existing_indices))
						echo "CONFLICT: Story " . $story_id . " (" . $info_row['publication_info'] . "): Existing value for Tango index ". $index_row['tango_index_id'] ." (" . $tango_index_string . "=>" . $index_row['name'] .") is TRUE, new one is FALSE!\n";
				}

				# Make a note of the None / Unspecified index
				# value if we see it
				if ($index_row["name"] == 'None / Unspecified')
					$none_unspecified_index_id = $index_row["tango_index_id"];
			}
			# If the type index is true but none of its children
			# (name) is, add an entry for type + unspecified
			if ($parent_matched && !$child_matched) {
#				echo "Story " . $story_id . ": Matched parent but not child: " . $tango_index_string . "=>None / Unspecified, Tango index " . $none_unspecified_index_id . "\n";
				if ($none_unspecified_index_id > 0) {
					# add an entry for this type + None/unsp

					if (in_array($none_unspecified_index_id, $existing_indices)) {
						if ($value != "TRUE")
							echo "CONFLICT: Story " . $story_id . " (" . $info_row['publication_info'] . "): None/unspecified index " . $none_unspecified_index_id . " is already TRUE, new value is " . $value . "\n";
						else
							echo "None/unspecified index " . $none_unspecified_index_id . " is already TRUE, new value is TRUE, skipping\n";

						continue;
					}
					
					echo "CHANGE: Story " . $story_id . " (" . $info_row['publication_info'] . "): Adding none/unspecified association for " . $tango_index_string . "\n";
					$type_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$none_unspecified_index_id.'")';
					mysql_query($type_query) or die('Failed to add new story to Tango type association with name = None / Unspecified: ' . mysql_error());
				} else {
					echo "ERROR: Story " . $story_id . " (" . $info_row['publication_info'] . "): Unable to find none/unspecified index!\n";
				}
			}
			
			mysql_free_result($tango_type_result);
			
			$tango_name_query = "SELECT tango_index_id, type, name FROM tango_index WHERE name='". $tango_index_string."'";
			$tango_name_result = mysql_query($tango_name_query) or die('Tango name query failed: ' . mysql_error());
			if ($name_row = mysql_fetch_assoc($tango_name_result)) {

				# If this entry is a name (child) whose parent
				# is not True, then add an entry for this
				# index. If the parent is True, then an entry
				# was already added in the loop above while
				# processing the parent (type)
				if (!array_key_exists(encode_heading($name_row["type"]), $info_row) || (array_key_exists(encode_heading($name_row["type"]), $info_row) && ($info_row[encode_heading($name_row["type"])] == "FALSE"))) {
#					echo "Matched child but not parent: " . $name_row['type'] . "=>" . $tango_index_string . ", Tango index " . $name_row["tango_index_id"] . "\n";
					if (in_array($name_row['tango_index_id'], $existing_indices)) {
						if ($info_row[encode_heading($name_row["name"])] != "TRUE")
							echo "CONFLICT: Story " . $story_id . " (" . $info_row['publication_info'] . "): Child index " . $name_row['name'] . " is already TRUE, new value is " . $info_row[encode_heading($name_row["name"])] . "\n";	
						else
							echo "Child index " . $name_row['name'] . " is already TRUE, new value is TRUE, skipping\n";

						continue;

					}
					if ($info_row[encode_heading($name_row["name"])] == "TRUE") {
						$name_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$name_row["tango_index_id"].'" )';
						echo "CHANGE: Story " . $story_id . " (" . $info_row['publication_info'] . "): adding matched child, unmatched parent " . $name_row['type'] . "=>" . $tango_index_string . ", Tango index " . $name_row["tango_index_id"] . "\n";
						mysql_query($name_query) or die('Failed to add new story to Tango type association based on child name: ' . mysql_error());
					}
				}

			}
			mysql_free_result($tango_name_result);
		}

	}

}
mysql_free_result($info_result);

mysql_close($dblink);
?>

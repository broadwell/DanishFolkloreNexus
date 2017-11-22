<?php

# Connect to the database
$user="dfl";
$password="Dfl#123!";
$database="dflfulldb";
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

/* The tables we're trying to create:
 * ms_image: ms_image_id, image_filename, type
 * ms_page: ms_page_id, page_number
 * ms_page_to_image: ms_page_to_image_id, ms_page_id, ms_image_id
 * story_to_ms_page: story_to_ms_page_id, story_id, ms_page_id, sequence_no, order_on_page
 */

/* Remember that manuscript images start with side "b" of the given page (as 
 * numbered in the manscript_images table) on the left, and then provide side "a"
 * of the next page on the right side */

/* This can (and should in most cases) be two-to-one or one-to-one */
$ms_page_to_image = array();

/* This should only be one-to-one or one-to-two */
$ms_image_to_page = array();

$story_ids = array();

$story_to_ms_image = array();

$story_start_position = array();

$story_page_range = array();

$ms_image_id = array();

/* Fill in the manuscript image filenames data first */

$dir_path = '../manuscript_images';
$handle = opendir($dir_path);
$image_index = 0;

while ($file = readdir($handle)) {
	if ($file != "." && $file != "..") {
		if (is_dir("$dir_path/$file")) {
	                 // found a directory
                } else {
			// found an ordinary file
			$image_index++;
			$ms_image_id[$file] = $image_index;
			$image_update_query = 'INSERT INTO ms_image (image_filename, type) VALUES ("' . $file . '", "field_diary")';
			#mysql_query($image_update_query) or die('Image insert query failed: ' . mysql_error());
		}
	}
}
closedir($handle);

$ms_filenames_query = "SELECT manuscript_image_id, story_id, image_filename FROM manuscript_image ORDER BY manuscript_image_id";
$ms_filename_result = mysql_query($ms_filenames_query) or die('MS filenames query failed: ' . mysql_error());

while ($ms_filename_row = mysql_fetch_assoc($ms_filename_result)) {

	if (!in_array($ms_filename_row['image_filename'], array_keys($ms_image_id)))
		echo "MISSING IMAGE FILE: " . $ms_filename_row['image_filename'] . "\n";

	if (!array_key_exists($ms_filename_row['story_id'], $story_to_ms_image)) {
		$story_to_ms_image[$ms_filename_row['story_id']] = array($ms_filename_row['image_filename']);
	} else {
		if (!in_array($ms_filename_row['image_filename'], $story_to_ms_image[$ms_filename_row['story_id']]))
			$story_to_ms_image[$ms_filename_row['story_id']][] = $ms_filename_row['image_filename'];

	}
		
}
mysql_free_result($ms_filename_result);

$all_pages = array();

$page_query = "(SELECT DISTINCT field_diary_start_page FROM manuscript_image) UNION DISTINCT (SELECT DISTINCT field_diary_end_page FROM manuscript_image)";
$page_result = mysql_query($page_query) or die('Page query failed: ' . mysql_error());
while ($page_row = mysql_fetch_assoc($page_result)) {

	if (!preg_match('/^([0-9]+[a|b])\s?\(?(.*?)\)?$/', $page_row['field_diary_start_page'], $matches)) {
		echo "NO match for " . $page_row['field_diary_start_page'] . "\n";
	} else {
		if (!in_array($matches[1], $all_pages)) {
			$all_pages[] = $matches[1];
		}
	}

}
mysql_free_result($page_result);

$manuscript_query = "SELECT * FROM manuscript_image ORDER BY field_diary_start_page";
$manuscript_result = mysql_query($manuscript_query) or die('Manuscript images query failed: ' . mysql_error());

$single_page_count = 0;
$weirdness_count = 0;

while ($manuscript_row = mysql_fetch_assoc($manuscript_result)) {

	$story_id = $manuscript_row['story_id'];
	if (!in_array($story_id, $story_ids))
		$story_ids[] = $story_id;

	/* The goal here is to build up the ms_page_to_image table.
	 * Once this is built, the ms_page_to_story table should be straightforward because 
	 * there's a direct connection in the existing table between ms pages and stories
	 * (even if it's not one-to-one)
	 */

	$start_page_string = $manuscript_row['field_diary_start_page'];
	$end_page_string = $manuscript_row['field_diary_end_page'];
	
	if (!preg_match('/^([0-9]+[a|b])\s?\(?(.*?)\)?$/', $start_page_string, $matches)) {
		echo "NO match for " . $start_page_string . "\n";
		$start_page = $start_page_string;
	} else {
		$start_page = $matches[1];
		$start_position = $matches[2];
		$story_start_position[$story_id] = $start_position; 
		#echo "Extra stuff for " . $start_page_string . " is " . $matches[2] . "\n";
	}

	if (!preg_match('/^([0-9]+[a|b])\s?(.*?)$/', $end_page_string, $matches)) {
		echo "NO match for " . $end_page_string . "\n";
		$end_page = $end_page_string;
	} else {
		$end_page = $matches[1];
		$end_position = $matches[2]; // This makes no sense
	}

	// If they're the same, then we know this specific image has that page in it
	// But check to see how many there are, because in some cases (like story 127)
	// there are 3 separate images all given the same field diary start & end pages
	// (2 of them are the front and back covers of the field diary book)
	if ($start_page == $end_page) {
		$single_page_count++;
		
		// DBUPDATE add to story_to_ms_page
		if (!array_key_exists($story_id, $story_page_range))
			$story_page_range[$story_id] = array($start_page);
//		echo 'NEW story->ms page: ' . $story_id . ' => ' . $start_page . "\n";

	} else {

		/* DEAL WITH RANGES HERE */
		preg_match('/^([0-9]+)([a|b])$/', $start_page, $matches);
		$start_page_no = $matches[1];
		$start_page_side = $matches[2];

		preg_match('/^([0-9]+)([a|b])$/', $end_page, $matches);
		$end_page_no = $matches[1];
		$end_page_side = $matches[2];

		$range = array();
		$current_page_no = $start_page_no;
		$current_side = $start_page_side;

		while (($current_page_no != $end_page_no) || ($current_side != $end_page_side)) {
			$page = sprintf("%d%s", $current_page_no, $current_side);

			if (!in_array($page, $all_pages)) {
		//		echo "PAGE in range only: " . $page . "\n";
				$all_pages[] = $page;
			}

			$range[] = $page;

			if ($current_side == 'a') {
				$current_side = 'b';
			} else {
				$current_page_no++;
				$current_side = 'a';
			}
		}
		$page = sprintf("%d%s", $current_page_no, $current_side);
		$range[] = $page;

		if (!array_key_exists($story_id, $story_page_range))
			$story_page_range[$story_id] = $range;
/*
		echo "RANGE for story " . $story_id . ": " . $start_page . " to " . $end_page . ": ";
		print_r($range);
		echo "\n";
 */

	}

}

mysql_free_result($manuscript_result);

$ms_page_id = array();
$ms_page_index = 0;

sort($all_pages);
foreach ($all_pages as $page_number) {
	/* Can also create a new pages array here with the same id #s as in the DB */
	$ms_page_index++;
	$ms_page_id[$page_number] = $ms_page_index;
	$page_update_query = 'INSERT INTO ms_page (page_number) VALUE ("' . $page_number . '")';
	#mysql_query($page_update_query) or die('Page insert query failed: ' . mysql_error());
}


/* How to deal with a range of ms images for a story: use story_to_ms_image
 * Start with the lowest image number for that story.
 * (NOTE: This should only be a problem (I think) for story 175, in which
 * the manuscript filenames may not be in ascending order -- due to
 * B25M7804.gif breaking the pattern.)
 * Otherwise, the lowest image number should correspond to the first page in
 * the range. <MAKE THE ASSOCIATION>
 * If that page ends with an "a", then we need to go to the next image
 * when going to "b".
 * If that page ends with a "b", we don't increment the image.
 */	

foreach ($story_page_range as $story_id=>$range) {

//	echo "WORKING WITH STORY " . $story_id . "\n";

	$story_images = $story_to_ms_image[$story_id];

	$seqno = 1;

	if (array_key_exists($story_id, $story_start_position))
		$start_pos = $story_start_position[$story_id];
	else
		$start_pos = '0';

	// DBUPDATE: story_to_ms_page (shouldn't already exist, but check?)
	$story_to_page_query = 'INSERT INTO story_to_ms_page (story_id, ms_page_id, seqno, order_on_page) VALUES ("' . $story_id . '", "' . $ms_page_id[$range[0]] . '", "' . $seqno . '", "' . $start_pos . '")';
	mysql_query($story_to_page_query) or die('story to page query failed: ' . mysql_error());

	if (!array_key_exists($range[0], $ms_page_to_image)) {
		$page_to_image_query = 'INSERT INTO ms_page_to_image (ms_page_id, ms_image_id) VALUES ("' . $ms_page_id[$range[0]] . '", "' . $ms_image_id[$story_images[0]] . '")';
		mysql_query($page_to_image_query) or die('page to image query failed: ' . mysql_error());
		$ms_page_to_image[$range[0]] = $story_images[0];
	}

	if (sizeof($range) == 1) {
		// This is the case if the start and end page are the same
		/*
		} else {
			if ($ms_page_to_image[$start_page] != $manuscript_row['image_filename']) {
				$ms_page_to_image[$start_page] = $manuscript_row['image_filename'];
				echo "WEIRDNESS: story " . $story_id . ": Different images assigned to MS page " . $start_page_string . "\n";
				$weirdness_count++;
			}
		}
		 */

		continue;
	}
	/*
		if (!array_key_exists($manuscript_row['image_filename'], $ms_image_to_page))
			$ms_image_to_page[$manuscript_row['image_filename']] = array($start_page);
		else {
			if (!in_array($start_page, $ms_image_to_page[$manuscript_row['image_filename']])) {
				$ms_image_to_page[$manuscript_row['image_filename']][] = $start_page;
				if (sizeof($ms_image_to_page[$manuscript_row['image_filename']]) > 2) {
					echo "WEIRDNESS: story " . $story_id . ": More than two MS pages assigned to image " . $manuscript_row['image_filename'] . "\n";
					$weirdness_count++;
				}
			}
		}
	 */

	// The first is a gimme: story_image[0] => ms_page[0]
	$page = $range[0];
//	echo 'NEW story->ms page: ' . $story_id . ' => ' . $page . "\n";
//	echo 'NEW ms page->image: ' . $page . ' => ' . $story_images[0] . "\n";
	// DBUPDATE: ms_page_to_image (def check that it doesn't already exist) 
/*	if (array_key_exists($page, $ms_page_to_image)) {
		if ($ms_page_to_image[$page] == $story_images[0]) {
			// Don't add it to the DB
		} else {
			echo "ERROR: story " . $story_id . ": range starts on page " . $page . ", with wrong image=" . $story_images[0] . "\n";
			continue;
		}
	}
 */
	// Then, loop: if the last letter was a, then the next is b
	$story_image_index = 0;
	$range_index = 0;

	preg_match('/^([0-9]+)([a|b])$/', $page, $matches);
	$last_pageno = $matches[1];
	$last_suffix = $matches[2];

	while ($page != $range[sizeof($range)-1]) {

		$seqno++;

		$range_index++;
		$page = $range[$range_index];

		if ($last_suffix == "a")
			$story_image_index++;

		// DBUPDATE: story_to_ms_page (shouldn't already exist, but check?)
		$story_to_page_query = 'INSERT INTO story_to_ms_page (story_id, ms_page_id, seqno, order_on_page) VALUES ("' . $story_id . '", "' . $ms_page_id[$page] . '", "' . $seqno . '", "0")';
		mysql_query($story_to_page_query) or die('story to page query failed: ' . mysql_error());
		//echo 'NEW story->ms page: ' . $story_id . ' => ' . $page . "\n";
		if ($story_image_index >= sizeof($story_images))
			echo "ERROR: Exceeded story images for story " . $story_id . ", page " . $page . "\n";

	// DBUPDATE: ms_page_to_image (def check that it doesn't already exist) 
//			echo 'NEW ms page->image: ' . $page . ' => ' . $story_images[$story_image_index] . "\n";
		if (!array_key_exists($page, $ms_page_to_image)) {
			$page_to_image_query = 'INSERT INTO ms_page_to_image (ms_page_id, ms_image_id) VALUES ("' . $ms_page_id[$page] . '", "' . $ms_image_id[$story_images[$story_image_index]] . '")';
			mysql_query($page_to_image_query) or die('page to image query failed: ' . mysql_error());
			$ms_page_to_image[$page] = $story_images[$story_image_index];
		}
		
		preg_match('/^([0-9]+)([a|b])$/', $page, $matches);
		$last_pageno = $matches[1];
		$last_suffix = $matches[2];

	}

}


echo("Total images: " . sizeof(array_keys($ms_image_id)) . "\n");
echo("Total pages: " . sizeof(array_keys($all_pages)) . "\n");
echo("Single pages: " . $single_page_count . "\n");
echo("Weirdness: " . $weirdness_count . "\n");

mysql_close($dblink);
?>

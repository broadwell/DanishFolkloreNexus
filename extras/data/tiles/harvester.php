<?php

# Round-robin through the three servers: a, b and c
# EX: http://a.tile.openstreetmap.org/LEVEL/COLUMN/ROW.png
$server = array('a', 'b', 'c');
$current_server = 0;

$base_dir = "OSM";

$level_extent = array();
$level_extent[3] = 7;
$level_extent[4] = 15;
$level_extent[5] = 31;
$level_extent[6] = 63;
$level_extent[7] = 127;
$level_extent[8] = array();
$level_extent[8]['col_min'] = 120;
$level_extent[8]['col_max'] = 149;
$level_extent[8]['row_min'] = 54;
$level_extent[8]['row_max'] = 102;
$level_extent[9] = array();
$level_extent[9]['col_min'] = 263;
$level_extent[9]['col_max'] = 279;
$level_extent[9]['row_min'] = 153;
$level_extent[9]['row_max'] = 167;
$level_extent[10] = array();
$level_extent[10]['col_min'] = 552;
$level_extent[10]['col_max'] = 558;
$level_extent[10]['row_min'] = 307;
$level_extent[10]['row_max'] = 326;
$level_extent[11] = array();
$level_extent[11]['col_min'] = 1066;
$level_extent[11]['col_max'] = 1115;
$level_extent[11]['row_min'] = 616;
$level_extent[11]['row_max'] = 657;
$level_extent[12] = array();
$level_extent[12]['col_min'] = 2142;
$level_extent[12]['col_max'] = 2224;
$level_extent[12]['row_min'] = 1238;
$level_extent[12]['row_max'] = 1316;
$level_extent[13] = array();
$level_extent[13]['col_min'] = 4275;
$level_extent[13]['col_max'] = 4444;
$level_extent[13]['row_min'] = 2447;
$level_extent[13]['row_max'] = 2630;
$min_level = 12;
$max_level = 13;

for ($level=$min_level; $level<=$max_level; $level++) {

	$levelpath = sprintf($base_dir . '/L%02d', $level);
	if (!(is_dir($levelpath))) {
		mkdir($levelpath, 0755);
	}

	for ($column=$level_extent[$level]['col_min']; $column<=$level_extent[$level]['col_max']; $column++) {

		for ($row=$level_extent[$level]['row_min']; $row<=$level_extent[$level]['row_max']; $row++) {

			$rowpath = sprintf($levelpath . '/R%08x', $row);
			if (!(is_dir($rowpath))) {
				mkdir($rowpath, 0755);
			}

			$format = $base_dir . '/L%02d/R%08x/C%08x.png';
			$outpath = sprintf($format, $level, $row, $column);

			if (is_file($outpath))
				continue;

			$tile_url = 'http://' . $server[$current_server] . '.tile.openstreetmap.org/' . $level . '/' . $column . '/' . $row . '.png';

			echo "Fetching $tile_url \n";

			$curl_conn = curl_init($tile_url);
			$fp = fopen($outpath, "w");

			curl_setopt($curl_conn, CURLOPT_FILE, $fp);
			curl_setopt($curl_conn, CURLOPT_HEADER, 0);

			curl_exec($curl_conn);
			curl_close($curl_conn);
			fclose($fp);

			$current_server = ($current_server + 1) % 3;

		}

	}

}


?>

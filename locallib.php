<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Booktool_validator module local lib functions
 *
 * @package    booktool_validator
 * @copyright  2014 Ivana Skelic, Hrvoje Golcic 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/book/edit_form.php');


/**
 * Function checks if all book chapters are present in booktool_validation table and marked as
 * valid (all images have alt attribute and all tables have summary attribute)
 * 
 * @param  	int field id from $book stdObject
 * @return 	bool
 */
 function is_validated($bookid) {

 	global $DB;

 	if (!$DB->record_exists('booktool_validator', array('bookid' => $bookid), IGNORE_MULTIPLE)) {
 		return false;
 		exit;
 	}

 	$isvalidated = false;

 	$cnttrue = 0;
 	$cntfalse = 0;

 	$sql = "SELECT bc.id 
 		FROM {book_chapters} bc
 		JOIN {book} b ON b.id = bc.bookid
 		WHERE b.id = ?";

 	$chapterids = $DB->get_record_sql($sql, $bookid);

 	foreach ($chapterids as $chapterid) {

 		if ($DB->record_exists('booktool_validator', array('bookid' => $bookid, 'chapterid' => $chapterid))) {
 			
 			$validated = $DB->get_field(
 				'booktool_validator', 
 				'validated', 
 				array('bookid' => $bookid, 'chapterid' => $chapterid), 
 				MUST_EXIST
 			);
 			
 			if ($validated == 1) {
 				$cnttrue ++;
 			} else {
 				$cntfalse ++;
 			}

 		} else {
 			return false;
 		}

 		if ($cntfalse > 0) {
 			return false;
 		} else {
 			return true;
 		}	
 	}
 }

/**
 * Function checks if all images in book chapter have alt attribute
 * 
 * @param  	int field id from $book stdObject
 * @param 	int field id from $chapter stdObject
 * @return 	bool
 */
 function check_images($bookid, $chapterid) {
 	global $DB;

 	$query = $DB->get_field(
 		'book_chapters', 
 		'content', 
 		array('id' => $chapterid, 'bookid' => $bookid),
 		MUST_EXIST
 	);

 	$chapter = new DOMDocument('ISO-8859-1');
 	$chapter->loadHTML($query);

 	$truecnt = 0;
 	$falsecnt = 0;

 	foreach( $chapter->getElementsByTagName('img') as $imgnode ) {
		
		$alt = $imgnode->getAttribute('alt');

		if (!empty($alt)) {
			$falsecnt ++;
		} else {
			$truecnt ++;
		}
	}

	if ($falsecnt > 0) {
		return false;
	} else {
		return true;
	}
 }

/**
* Function checks if all tables in book chapter have summary attribute
* 
* @param  	int field id from $book stdObject
* @param 	int field id from $chapter stdObject
* @return 	bool
*/
function check_tables($bookid, $chapterid) {
	global $DB;

	$query = $DB->get_field(
		'book_chapters', 
		'content', 
		array('id' => $chapterid, 'bookid' => $bookid),
		MUST_EXIST
	);

	$chapter = new DOMDocument('ISO-8859-1');
	$chapter->loadHTML($query);

	$truecnt = 0;
	$falsecnt = 0;

	foreach( $chapter->getElementsByTagName('table') as $tablenode ) {
	
		$summ = $tablenode->getAttribute('summary');

		if (!empty($summ)) {
			$falsecnt ++;
		} else {
			$truecnt ++;
		}
	}

	if ($falsecnt > 0) {
		return false;
	} else {
		return true;
	}
}

/**
 * Function counts number of images that lack alt attribute and number of tables that lack
 * summarry attribute. Returns number of faults.
 *
 * @param  	int $book->id
 * @param 	int $chapterid
 * @return 	int
 */
function count_faults($bookid, $chapterid) {
	global $DB;

	$query = $DB->get_field(
		'book_chapters', 
		'content', 
		array('id' => $chapterid, 'bookid' => $bookid),
		MUST_EXIST
	);

	$chapter = new DOMDocument('ISO-8859-1');
	$chapter->loadHTML($query);

	$noalt = 0; 	//no alt attribute counter
	$nosumm = 0; 	//no summary attribute counter

	// Count how many tables doesn't have summary attribute
	foreach( $chapter->getElementsByTagName('table') as $tablenode ) {
	
		$summ = $tablenode->getAttribute('summary');

		if (!empty($summ)) {
			$nosumm ++;
		} 
	}

	// Count how many images doesn't have alt attribute
	foreach( $chapter->getElementsByTagName('img') as $imgnode ) {
		
		$alt = $imgnode->getAttribute('alt');

		if (!empty($alt)) {
			$noalt ++;
		}
	}

	return ($nosumm + $noalt);
}

/**
 * Get chapter name.
 *
 * Finds and returns chapter name for given parameters.
 *
 * @param  	$book->id
 * @param 	$chapterid
 * @return 	array
 */
function chapter_getname($book, $chapterid) {

	global $DB;

	$query = 'SELECT title FROM {book_chapters} WHERE id = ? AND bookid = ?';
	$params = array($chapterid, $book->id);
	$query_result = $DB->get_record_sql($query, $params);
	
	if (!$query_result) {
		return false;
	}
	return $query_result->title;
}

/**
 * Finds and prints images that lack alt attribute for given arguments
 *
 * @param  	stdClass $book->id
 * @param 	$chapterid
 * @return 	
 */
function print_images($bookid, $chapterid) {

	global $DB;

	$query = $DB->get_field('book_chapters', 'content', array('id'=>$chapterid, 'bookid'=>$bookid));
	$content = serialize($query);

	$alt_pat = '/<img(\s*(?!alt)([\w\-])+=([\"])[^\"]+\3)*\s*\/?>/i';;
	preg_match_all($alt_pat, $content, $img_alt_pregmatch);

	if (count($img_alt_pregmatch[0]) != 0) {
		
		echo get_string('image','booktool_validator');
		echo "<br> <br>";

		foreach($img_alt_pregmatch[0] as $print) {
   		echo $print . "<br>";
		}
	}		
}

/**
 * Function find and show tables that have no summary attribute
 *
 * @param  	int $book->id
 * @param 	int $book->id
 * @return 	
 */
function show_tables($bookid, $chapterid) {
	global $DB;

	$query = $DB->get_field(
		'book_chapters', 
		'content', 
		array('id' => $chapterid, 'bookid' => $bookid)
	);
	
	$chapter = new DOMDocument('ISO-8859-1');
	$chapter->loadHTML($query);

	foreach( $chapter->getElementsByTagName('table') as $tablenode ) {
	
		$summ = $tablenode->getAttribute('summary');

		if (!empty($summ) ) {
			$output = get_string('table', 'booktool_validator');
			$output .= '<br>'. $summ->saveXML().'<br>';

			echo $output;
		}
	}
}

/**
 * Function finds and shows all images that have empty alt attribute
 *
 * @param  	int $book->id
 * @param 	int $chapterid
 * @return 	
 */
function show_images($bookid, $chapterid) {
	global $DB;

	$query = $DB->get_field(
		'book_chapters', 
		'content', 
		array('id' => $chapterid, 'bookid' => $bookid)
	);
	
	$chapter = new DOMDocument('ISO-8859-1');
	$chapter->loadHTML($query);

	foreach( $chapter->getElementsByTagName('img') as $imgnode ) {
	
		$alt = $imgnode->getAttribute('alt');

		if (!empty($alt) ) {
			$output = get_string('image', 'booktool_validator');
			$output .= '<br>'. $alt->saveXML().'<br>';

			echo $output;
		}
	}
}

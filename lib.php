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
 * Library of functions for the booktool_validator module.
 *
 * @package    booktool_validator
 * @copyright  2014 Ivana Skelic, Hrvoje Golcic 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to add module settings to
 */
function booktool_validator_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
	global $PAGE, $DB;

	$params = $PAGE->url->params();

	//if chapter is in editing mode there is no 'id' field in params
	if (array_key_exists('cmid', $params)) {
		$cm = get_coursemodule_from_id('book', $params['cmid'], 0, false, MUST_EXIST);
	} elseif (array_key_exists('update', $params)) {
		$cm = get_coursemodule_from_id('book', $params['update'], 0, false, MUST_EXIST);
	} elseif(array_key_exists('id', $params) && !array_key_exists('cmid', $params)) {
		$cm = get_coursemodule_from_id('book', $params['id'], 0, false, MUST_EXIST);
	}
		
	if (empty($params['id']) || empty($params['chapterid'])) {
		return;
	}

	if (has_capability('booktool/validator:validate', $PAGE->cm->context)) {

		//check images and tables
		$url_img = new moodle_url(
 			'/mod/book/tool/validator/images.php', 
 			array('id'=>$params['id'])
 		);
 		$node->add(
 			get_string('check_img', 'booktool_validator'), 
 			$url_img, 
 			navigation_node::TYPE_SETTING, 
 			null, 
 			null, 
 			new pix_icon('image', '', 'booktool_validator', array('class'=>'icon'))
 		);
 		
 		$url_table = new moodle_url(
 			'/mod/book/tool/validator/tables.php', 
 			array('id'=>$params['id'])
 		);
 		$node->add(
 			get_string('check_table', 'booktool_validator'), 
 			$url_table, 
 			navigation_node::TYPE_SETTING, 
 			null, 
 			null, 
 			new pix_icon('table', '', 'booktool_validator', array('class'=>'icon'))
 		);

		//validator		
		$string1 = get_string('validatebook', 'booktool_validator');
		$string2 = get_string('validatechapter', 'booktool_validator');

		$navigation_node = navigation_node::TYPE_SETTING;

		$url1 = new moodle_url(
			'/mod/book/tool/validator/book.php', 
			array('id' => $params['id'])
			);
		$url2 = new moodle_url(
			'/mod/book/tool/validator/chapter.php', 
			array('id' => $params['id'], 'chapterid' => $params['chapterid'])
		);

		//to avoid else case
		$isvalid = 0;
		$timevalidated = 0;
		$timemodified = 1;

		if ($DB->record_exists('book_validator', array('bookid' => $cm->instance)) ) {
			
			$isvalid = $DB->get_field(
				'book_validator', 
				'isvalid', 
				array('bookid' => $cm->instance), 
				MUST_EXIST
			);

			$sql = 'SELECT MAX(timevalidated) FROM {book_chapters_validator} where bookid = ?';
			$timevalidated = $DB->get_field_sql(
				$sql, 
				array('bookid' => $cm->instance), 
				MUST_EXIST
			);

			$sql = 'SELECT MAX(timemodified) FROM {book_chapters} where bookid = ?';

			$timemodified = $DB->get_field_sql(
				$sql,
				array('bookid' => $cm->instance), 
				MUST_EXIST
			);

		}

		if ($isvalid == 1 && ($timevalidated >= $timemodified)) {
			// if time of validation is greather than time of modification that means that book wasn't
			// altered after validation

			$pixicon = new pix_icon('valid', '', 'booktool_validator', array('class'=>'icon'));

			$node->add(
				get_string('validchapter', 'booktool_validator'), 
				null, 
				$navigation_node, 
				null, 
				null, 
				$pixicon
			);

			$node->add(
				get_string('validbook', 'booktool_validator'), 
				null, 
				$navigation_node, 
				null, 
				null, 
				$pixicon
			);

		} else {

			$pixicon = new pix_icon('validate', '', 'booktool_validator', array('class'=>'icon'));

			$node->add(
				$string1, 
				$url1, 
				$navigation_node, 
				null, 
				null, 
				$pixicon
			);

			$node->add(
				$string2, 
				$url2, 
				$navigation_node, 
				null, 
				null, 
				$pixicon
			);
		}
	}
}
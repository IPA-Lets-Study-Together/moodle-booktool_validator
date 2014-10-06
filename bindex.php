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
 * Book validation
 *
 * @package    booktool_validator
 * @copyright  2014 Ivana Skelic, Hrvoje Golcic 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/validation_form.php');

$id = required_param('id', PARAM_INT);  // Course Module ID

var_dump($id);

$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$book = $DB->get_record('book', array('id'=>$cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/book:read', $context);
require_capability('booktool/validator:validate', $context);

$PAGE->set_url('/mod/book/tool/validator/bindex.html', array('id'=>$id));

//Fill and print the form
$pagetitle = $book->name . ": " . 

$PAGE->set_title($book->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($book->name);

//get all chapter ids
$chapterids = $DB->get_records_sql(
    'SELECT id FROM {book_chapters} WHERE bookid = ?', 
    array($book->id)
);

//check if every chapter exists in booktool_validator table
foreach ($chapterids as $chapter) {

    // Check if chapter exists in booktool_validator table
    $chapterexists = $DB->record_exists(
        'booktool_validator', 
        array('bookid' => $book->id, 'chapterid' => $chapter->id)
    );

    // If chapter doesn't exist create and check for validation problems
    if (!$chapterexists) {

        $record = new stdClass();

        $record->bookid = $book->id;
        $record->chapterid = $chapter->id;

        if (!check_images($book->id, $chapter->id) && !check_tables($book->id, $chapter->id)) {

            $params = $PAGE->url->params();

            $record->validated = 0;
            $record->faults = count_faults($book->id, $chapter->id);
            $DB->insert_record('booktool_validator', $record, false);

        } else {

            $record->validated = 1;
            $record->faults = 0;
            $DB->insert_record('booktool_validator', $record, false);
        }
    }

    // If chapter exists in plugin table check for validation
    $validated = $DB->get_field(
        'booktool_validator', 
        'validated', 
        array('bookid' => $book->id, 'chapterid' => $chapter->id), 
        MUST_EXIST)
    );
    
    if ($validated == 0) {
        //if validated value is 0 that means that chapter is not validated
        $params = $PAGE->url->params();

        $title = $DB->get_field(
            'book_chapters', 
            'title', 
            array('id' => $chapter->id, 'bookid' => $book->id)
        );
            
        echo "<hr />";

        echo "<h4>" . $title . "</h4>\t" . get_string('event_chapter_notvalidated', 'booktool_validator');
        echo get_string('nof', 'booktool_validator') . $DB->get_field('booktool_validator', 'faults', array('bookid'=>$book->id, 'chapterid'=>$chapter->id));

        echo "<br>";

        show_images($book->id, $chapter->id);
        echo "<br>";
        show_tables($book->id, $chapter->id);

        $url = new moodle_url(
            '/mod/book/tool/validator/bcindex.php', 
            array('cmid' => $params['id'], 'chapterid'=>$chapter->id)
        );

        $str = get_string('validate', 'booktool_validator');
        $actionlink = new action_link(
            $url, 
            $str, 
            null
        );

        $outputstring = get_string('click', 'booktool_validator').
            '<strong>'.
            $OUTPUT->render($actionlink).
            '</strong><br>';

        echo $outputstring;
    } 
}

$chapterscnt = $DB->count_records(
    'book_chapters', 
    array('bookid' => $book->id)
);

$chaptersvalidated = $DB->count_records(
    'booktool_validator', 
    array('bookid' => $book->id, 'validated' => 1)
);

if ($chapterscnt == $chaptersvalidated) {
    
    echo get_string('event_book_validated', 'booktool_validator');
}

echo $OUTPUT->footer();

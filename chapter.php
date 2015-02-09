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
 * Book validator
 *
 * @package    booktool_validation
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/validation_form.php');

global $DB;
$DB->set_debug(true);

// if user canceled form
if (isset($_REQUEST['cancel'])) redirect($CFG->wwwroot . "/mod/book/view.php?id=".$_REQUEST['cmid']);

// if user saved form
if (isset($_REQUEST['submitbutton'])) $id = $_REQUEST['cmid'];
//else 
else $id = required_param('id', PARAM_INT);            // Course Module ID
$chapterid  = optional_param('chapterid', 0, PARAM_INT);    // Chapter ID

if ($id) {
    $cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $book = $DB->get_record('book', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $book = $DB->get_record('book', array('id' => $id), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

if (!$chapterid || $chapterid == 0) {
    //only gets first chapter id
    $chapterid = $DB->get_field(
        'book_chapters', 
        'id', 
        array('bookid' => $book->id), 
        IGNORE_MULTIPLE
    );
}

var_dump($book);

$chapter = $DB->get_record(
    'book_chapters', 
    array('id' => $chapterid, 'bookid' => $book->id), 
    '*', 
    MUST_EXIST
);

$chapter->cmid = $cm->id;

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/book:edit', $context);

//set page url
$PAGE->set_url(
    '/mod/book/tool/validator/chapter.html', 
    array('id' => $id, 'chapterid' => $chapterid)
    );

// set admin layout - This is a bloody hack!
$PAGE->set_pagelayout('admin'); 

//check if data exists

if (!$DB->record_exists('book_validator', array('bookid' => $book->id))) {

    $sql = 'SELECT id FROM {book_chapters} WHERE bookid = ?';
    $params = array('bookid' => $book->id);
    $chapterids = $DB->get_records_sql($sql, $params);

    $chaptersnumber = count($chapterids); //count the number of chapters in the book
    $validchapters = 0; //count the number of valid chapters in this book
    
    foreach ($chapterids as $id) { 

        $record = new stdClass();
        $record->bookid = $book->id;
        $record->chapterid = $id->id;

        $record->faults = count_faults($book->id, $id->id);
        $record->timevalidated = time();

        var_dump($record);

        $DB->insert_record('book_chapters_validator', $record, false);

        if ($record->faults == 0) {
            $validchapters ++;
        }
    }

    unset($record);

    $record = new stdClass();
    $record->bookid = $book->id;
    
    if ($validchapters == $chaptersnumber) {
        $record->is_valid = 1;
    } else {
        $record->is_valid = 0;
    }
    $record->timevalidated = time();

    var_dump($record);
    $DB->insert_record('book_validator', $record, false);
}

$PAGE->set_title($book->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($book->name);

if (count_faults($book->id, $chapterid) != 0) {

    $options = array(
        'noclean'=>true, 
        'subdirs'=>true, 
        'maxfiles'=>-1, 
        'maxbytes'=>0, 
        'context'=>$context
        );
    $chapter = file_prepare_standard_editor(
        $chapter, 
        'content', 
        $options, 
        $context, 
        'mod_book', 
        'chapter', 
        $chapterid
        );

    $mform = new book_validation_edit_form(
        null, 
        array('chapter'=>$chapter, 'options'=>$options)
        );

    //If data submitted, process and store
    
    if ($mform->is_cancelled()) {

        redirect($CFG->wwwroot . "/mod/book/view.php?id=$id");
        
    } else if ($data = $mform->get_data()) {
        //store the files
        $data->timemodified = time();
        $data = file_postupdate_standard_editor(
            $data, 
            'content', 
            $options, 
            $context, 
            'mod_book', 
            'chapter', 
            $data->id
            );

        $DB->update_record('book_chapters', $data);

        $DB->set_field(
            'book', 
            'revision', 
            $book->revision+1, 
            array('id' => $bookid)
            );

        //check again after submission
        if (count_faults($book->id, $chapterid) == 0) {

            $record = $DB->get_record(
                'book_chapters_validator', 
                array('id' => $data->id),
                '*',
                MUST_EXIST
                );

            $record->faults = 0;
            $record->timevalidated = time();

            $DB->update_record('book_chapters_validator', $record, false);

            unset($record);

            $record = $DB->get_record(
                'book_validator', 
                array('bookid' => $book->id).
                '*',
                MUST_EXIST
                );

            $validchapters = $DB->count_records(
                'book_chapters_validator', 
                array('bookid' => $book->id, 'faults' => 0)
                );

            var_dump($validchapters);

            if ($validchapters == $chaptersnumber)
                $record->is_valid = 1;

            $record->timevalidated = time();

            $DB->update_record('book_validator', $record, false);

        }

        add_to_log(
            $course->id, 
            'course', 
            'update mod', 
            '../mod/book/view.php?id='.$cm->id, 
            'book '.$book->id
            );

        $params = array(
            'context' => $context,
            'objectid' => $data->id
            );
        $event = \mod_book\event\chapter_updated::create($params);
        $event->add_record_snapshot('book_chapters', $data);
        $event->trigger();  

        book_preload_chapters($book); // fix structure
        redirect($CFG->wwwroot . "/mod/book/view.php?id=$id");
    }

echo get_string('event_chapter_notvalidated', 'booktool_validator');
echo get_string('nof', 'booktool_validator') 
    . $DB->get_field(
        'book_chapters_validator', 
        'faults', 
        array('bookid' => $book->id, 'chapterid' => $chapterid)
    );
echo "<br>";

show_images($book->id, $chapterid, $context->id);
echo "<br>";
show_tables($book->id, $chapterid);

$mform->display();
} else {
    echo get_string('event_chapter_validated', 'booktool_validator');
}

echo $OUTPUT->footer();
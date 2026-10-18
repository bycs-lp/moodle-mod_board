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

namespace mod_board\phpunit\privacy;

use core_privacy\local\request\approved_userlist;
use mod_board\board;
use mod_board\privacy\provider;

/**
 * Privacy provider deletion tests.
 *
 * @package    mod_board
 * @copyright  2020 onward: Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_board\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Deleting all users in a context removes notes, ratings, comments, history and note images.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);
        $context = \context_module::instance($board->cmid);
        $column = $DB->get_record('board_columns', ['boardid' => $board->id, 'sortorder' => 1], '*', MUST_EXIST);

        $note = $generator->create_note(['columnid' => $column->id, 'userid' => $user1->id]);
        $comment = $generator->create_comment(['noteid' => $note->id, 'userid' => $user2->id]);
        $DB->insert_record('board_note_ratings', ['noteid' => $note->id, 'userid' => $user2->id, 'timecreated' => time()]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_board',
            'filearea' => 'images',
            'itemid' => $note->id,
            'filepath' => '/',
            'filename' => 'image.png',
        ], 'image');

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('board_notes', ['id' => $note->id]));
        $this->assertFalse($DB->record_exists('board_comments', ['id' => $comment->id]));
        $this->assertFalse($DB->record_exists('board_note_ratings', ['noteid' => $note->id]));
        $this->assertFalse($DB->record_exists('board_history', ['boardid' => $board->id]));
        $this->assertTrue(get_file_storage()->is_area_empty($context->id, 'mod_board', 'images', $note->id));
    }

    /**
     * Deleting approved users in a context removes their data and keeps data of other users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        /** @var \mod_board_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_board');
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $board = $this->getDataGenerator()->create_module('board', [
            'course' => $course->id,
            'singleusermode' => board::SINGLEUSER_DISABLED,
        ]);
        $context = \context_module::instance($board->cmid);
        $column = $DB->get_record('board_columns', ['boardid' => $board->id, 'sortorder' => 1], '*', MUST_EXIST);

        $note1 = $generator->create_note(['columnid' => $column->id, 'userid' => $user1->id]);
        $note2 = $generator->create_note(['columnid' => $column->id, 'userid' => $user2->id]);
        $comment = $generator->create_comment(['noteid' => $note2->id, 'userid' => $user1->id]);
        $DB->insert_record('board_note_ratings', ['noteid' => $note2->id, 'userid' => $user1->id, 'timecreated' => time()]);

        provider::delete_data_for_users(new approved_userlist($context, 'mod_board', [$user1->id]));

        $this->assertFalse($DB->record_exists('board_notes', ['id' => $note1->id]));
        $this->assertFalse($DB->record_exists('board_comments', ['id' => $comment->id]));
        $this->assertFalse($DB->record_exists('board_note_ratings', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('board_history', ['boardid' => $board->id, 'userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('board_notes', ['id' => $note2->id]));
        $this->assertTrue($DB->record_exists('board_history', ['boardid' => $board->id, 'userid' => $user2->id]));
    }
}

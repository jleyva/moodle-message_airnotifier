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
 * Provide interface for AJAX device actions
 *
 * @copyright 2012 Jerome Mouneyrac
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package message_airnotifier
 */


define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');

// Initialise ALL the incoming parameters here, up front.
$id         = required_param('id', PARAM_INT);
$enable     = required_param('enable', PARAM_BOOL);

require_login();
require_sesskey();

$usercontext = context_user::instance($USER->id);

$PAGE->set_url('/message/output/airnotifier/rest.php');
$PAGE->set_context($usercontext);

require_capability('message/airnotifier:managedevice', $usercontext);

echo $OUTPUT->header();

$device = $DB->get_record('message_airnotifier_devices', array('id' => $id), '*', MUST_EXIST);

$device->enable = required_param('enable', PARAM_BOOL);
$DB->update_record('message_airnotifier_devices', $device);


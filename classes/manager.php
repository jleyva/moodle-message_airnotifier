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
 * Airnotifier manager class
 *
 * @package    message_airnotifier
 * @category   external
 * @copyright  2012/2014 Jerome Mouneyrac / Juan Leyva <jerome@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */


/**
 * Airnotifier helper manager class
 */
class message_airnotifier_manager {

    /**
     * Include the relevant javascript and language strings for the device
     * toolbox YUI module
     *
     * @return bool
     */
    public function include_device_ajax() {
        global $PAGE, $CFG;

        if (!$CFG->enableajax) {
            return false;
        }

        $config = new stdClass();

        // The URL to use for resource changes.
        if (!isset($config->resturl)) {
            $config->resturl = '/message/output/airnotifier/rest.php';
        }

        // Any additional parameters which need to be included on page submission.
        if (!isset($config->pageparams)) {
            $config->pageparams = array();
        }

        // Include toolboxes.
        $PAGE->requires->yui_module('moodle-message_airnotifier-toolboxes', 'M.message.init_device_toolbox', array(array(
                'ajaxurl' => $config->resturl,
                'config' => $config,
                ))
        );

        // Required strings for the javascript.
        $PAGE->requires->strings_for_js(array('deletecheckdevicename'), 'message_airnotifier');
        $PAGE->requires->strings_for_js(array('show', 'hide'), 'moodle');

        return true;
    }

    /**
     * Return the user devices for a specific app.
     *
     * @param string $appname the app name .
     * @param int $userid if empty take the current user.
     * @return array all the devices
     */
    public function get_user_devices($appname, $userid = null) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $devices = array();

        $params = array('appid' => $appname, 'userid' => $userid);

        // First, we look all the devices registered for this user in the Moodle core.
        // We are going to allow only ios devices (since these are the ones that supports PUSH notifications).
        if ($userdevices = $DB->get_records('user_devices', $params)) {
            foreach ($userdevices as $device) {
                if (strtolower($device->platform) == 'ios') {
                    // Check if the device is known by airnotifier.
                    if (!$airnotifierdev = $DB->get_record('message_airnotifier_devices', array('userdeviceid' => $device->id))) {
                        // We have to create the device token in airnotifier.

                        if (! $this->create_token($device->pushid)) {
                            continue;
                        }

                        $airnotifierdev = new stdClass;
                        $airnotifierdev->userdeviceid = $device->id;
                        $airnotifierdev->enable = 1;
                        $airnotifierdev->id = $DB->insert_record('message_airnotifier_devices', $airnotifierdev);
                    }
                    $device->id = $airnotifierdev->id;
                    $device->enable = $airnotifierdev->enable;
                    $devices[] = $device;
                }
            }
        }
        return $devices;
    }

    /**
     * Request and access key to Airnotifier
     *
     * @return mixed The access key or false in case of error
     */
    public function request_accesskey() {
        global $CFG, $USER;

        require_once($CFG->libdir . '/filelib.php');

        // Sending the request access key request to Airnotifier.
        $serverurl = $CFG->airnotifierurl . ':' . $CFG->airnotifierport . '/accesskeys/';
        // We use an APP Key "none", it can be anything.
        $header = array('Accept: application/json', 'X-AN-APP-NAME: ' . $CFG->airnotifierappname,
            'X-AN-APP-KEY: none');
        $curl = new curl();
        $curl->setHeader($header);

        // Site ids are stored as secrets in md5 in the Moodle public hub.
        $params = array(
            'url' => $CFG->wwwroot,
            'siteid' => md5($CFG->siteidentifier),
            'contact' => $USER->email,
            'description' => $CFG->wwwroot
            );
        $resp = $curl->post($serverurl, $params);

        if ($key = json_decode($resp, true)) {
            if (!empty($key['accesskey'])) {
                return $key['accesskey'];
            }
        }
        return false;
    }

    /**
     * Create a device token in the Airnotifier instance
     *
     * @return bool True if all was right
     */
    private function create_token($token) {
        global $CFG;

        if (!$this->is_system_configured()) {
            return false;
        }

        require_once($CFG->libdir . '/filelib.php');

        $serverurl = $CFG->airnotifierurl . ':' . $CFG->airnotifierport . '/tokens/' . $token;
        $header = array('Accept: application/json', 'X-AN-APP-NAME: ' . $CFG->airnotifierappname,
            'X-AN-APP-KEY: ' . $CFG->airnotifieraccesskey);
        $curl = new curl;
        $curl->setHeader($header);
        $params = array();
        $resp = $curl->post($serverurl, $params);

        if ($resp = json_decode($resp, true)) {
            if (!empty($resp['status'])) {
                return $resp['status'] == 'ok' || $resp['status'] == 'token exists';
            }
        }
        return false;
    }

    /**
     * Tests whether the airnotifier settings have been configured
     * @return boolean true if airnotifier is configured
     */
    public function is_system_configured() {
        global $CFG;

        return (!empty($CFG->airnotifierurl) && !empty($CFG->airnotifierport) &&
                !empty($CFG->airnotifieraccesskey)  && !empty($CFG->airnotifierappname) &&
                !empty($CFG->airnotifiermobileappname));
    }

}

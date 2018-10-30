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
 * A page displaying the user's contacts and messages
 *
 * @package    core_message
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');

require_login(null, false);

if (isguestuser()) {
    redirect($CFG->wwwroot);
}

if (empty($CFG->messaging)) {
    print_error('disabled', 'message');
}

// The id of the user we want to view messages from.
$id = optional_param('id', 0, PARAM_INT);

// It's possible for someone with the right capabilities to view a conversation between two other users. For BC
// we are going to accept other URL parameters to figure this out.
$user1id = optional_param('user1', $USER->id, PARAM_INT);
$user2id = optional_param('user2', $id, PARAM_INT);
$contactsfirst = optional_param('contactsfirst', 0, PARAM_INT);

$url = new moodle_url('/message/index.php');
if ($id) {
    $url->param('id', $id);
} else {
    if ($user1id) {
        $url->param('user1', $user1id);
    }
    if ($user2id) {
        $url->param('user2', $user2id);
    }
    if ($contactsfirst) {
        $url->param('contactsfirst', $contactsfirst);
    }
}
$PAGE->set_url($url);

$user1 = null;
$currentuser = true;
if ($user1id != $USER->id) {
    $user1 = core_user::get_user($user1id, '*', MUST_EXIST);
    $currentuser = false;
} else {
    $user1 = $USER;
}

$user2 = null;
if (!empty($user2id)) {
    $user2 = core_user::get_user($user2id, '*', MUST_EXIST);
}

$user2realuser = !empty($user2) && core_user::is_real_user($user2->id);
$systemcontext = context_system::instance();
if ($currentuser === false && !has_capability('moodle/site:readallmessages', $systemcontext)) {
    print_error('accessdenied', 'admin');
}

$PAGE->set_context(context_user::instance($user1->id));
$PAGE->set_pagelayout('standard');
$strmessages = get_string('messages', 'message');
if ($user2realuser) {
    $user2fullname = fullname($user2);

    $PAGE->set_title("$strmessages: $user2fullname");
    $PAGE->set_heading("$strmessages: $user2fullname");
} else {
    $PAGE->set_title("{$SITE->shortname}: $strmessages");
    $PAGE->set_heading("{$SITE->shortname}: $strmessages");
}

// Remove the user node from the main navigation for this page.
$usernode = $PAGE->navigation->find('users', null);
$usernode->remove();

$settings = $PAGE->settingsnav->find('messages', null);
$settings->make_active();

// Get the renderer and the information we are going to be use.
$renderer = $PAGE->get_renderer('core_message');
$requestedconversation = false;
if ($contactsfirst) {
    $conversations = \core_message\api::get_contacts($user1->id, 0, 20);
} else {
    $conversations = \core_message\api::get_conversations($user1->id, 0, 20);
    // Transform new data format back into the old format, just for BC during the deprecation life cycle.
    $tmp = [];
    foreach ($conversations as $id => $conv) {
        $data = new \stdClass();
        // The logic for the 'other user' is a follows:
        // If a conversation is of type 'individual', the other user is always the member who is not the current user, unless
        // the current user is the only conversation member.
        // If the conversation is of type 'group', the other user is always the sender of the most recent message.
        if ($conv->type == \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL) {
            $otheruserid = $user1->id;
            foreach ($conv->members as $member) {
                if ($member->id != $otheruserid) {
                    $otheruserid = $member->id;
                }
            }
        } else if ($conv->type == \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP) {
            $otheruserid = $conv->messages[0]->useridfrom;
        }
        $data->userid = $otheruserid;
        $data->useridfrom = $conv->messages[0]->useridfrom ?? null;
        $data->fullname = $conv->members[$otheruserid]->fullname;
        $data->profileimageurl = $conv->members[$otheruserid]->profileimageurl;
        $data->profileimageurlsmall = $conv->members[$otheruserid]->profileimageurlsmall;
        $data->ismessaging = isset($conv->messages[0]->text) ? true : false;
        $data->lastmessage = $conv->messages[0]->text ?? null;
        $data->messageid = $conv->messages[0]->id ?? null;
        $data->isonline = $conv->members[$otheruserid]->isonline ?? null;
        $data->isblocked = $conv->members[$otheruserid]->isblocked ?? null;
        $data->isread = $conv->isread;
        $data->unreadcount = $conv->unreadcount;
        $tmp[$data->userid] = $data;
    }
    $conversations = $tmp;
}
$messages = [];
if (!$user2realuser) {
    // If there are conversations, but the user has not chosen a particular one, then render the most recent one.
    $user2 = new stdClass();
    $user2->id = null;
    if (!empty($conversations)) {
        $contact = reset($conversations);
        $user2->id = $contact->userid;
    }
} else {
    // The user has specifically requested to see a conversation. Add the flag to
    // the context so that we can render the messaging app appropriately - this is
    // used for smaller screens as it allows the UI to be responsive.
    $requestedconversation = true;
}

// Mark the conversation as read.
if (!empty($user2->id)) {
    if ($currentuser && isset($conversations[$user2->id])) {
        // Mark the conversation we are loading as read.
        if ($conversationid = \core_message\api::get_conversation_between_users([$user1->id, $user2->id])) {
            \core_message\api::mark_all_messages_as_read($user1->id, $conversationid);
        }

        // Ensure the UI knows it's read as well.
        $conversations[$user2->id]->isread = 1;
    }

    // Get the conversationid.
    if (!isset($conversationid)) {
        if (!$conversationid = self::get_conversation_between_users($userids)) {
            // If the conversationid doesn't exist, throw an exception.
            throw new moodle_exception('conversationdoesntexist', 'core_message');
        }
    }

    $convmessages = \core_message\api::get_conversation_messages($user1->id, $conversationid, 0, 20, 'timecreated DESC');
    $messages = $convmessages['messages'];

    // Keeps track of the last day, month and year combo we were viewing.
    $day = '';
    $month = '';
    $year = '';

    // Parse the messages to add missing fields for backward compatibility.
    $messages = array_map(function($message) use ($user1, $user2, $USER, $day, $month, $year) {
        // Add useridto.
        if (empty($message->useridto)) {
            if ($message->useridfrom == $user1->id) {
                $message->useridto = $user2->id;
            } else {
                $message->useridto = $user1->id;
            }
        }

        // Add currentuserid.
        $message->currentuserid = $USER->id;

        // Add displayblocktime.
        $date = usergetdate($message->timecreated);
        if ($day != $date['mday'] || $month != $date['month'] || $year != $date['year']) {
            $day = $date['mday'];
            $month = $date['month'];
            $year = $date['year'];
            $message->displayblocktime = true;
        } else {
            $message->displayblocktime = false;
        }
        // We don't have this information here so, for now, we leave an empty value.
        // This is a temporary solution because a new UI is being built in MDL-63303.
        $message->timeread = 0;

        return $message;
    }, $messages);
}

$pollmin = !empty($CFG->messagingminpoll) ? $CFG->messagingminpoll : MESSAGE_DEFAULT_MIN_POLL_IN_SECONDS;
$pollmax = !empty($CFG->messagingmaxpoll) ? $CFG->messagingmaxpoll : MESSAGE_DEFAULT_MAX_POLL_IN_SECONDS;
$polltimeout = !empty($CFG->messagingtimeoutpoll) ? $CFG->messagingtimeoutpoll : MESSAGE_DEFAULT_TIMEOUT_POLL_IN_SECONDS;
$messagearea = new \core_message\output\messagearea\message_area($user1->id, $user2->id, $conversations, $messages,
        $requestedconversation, $contactsfirst, $pollmin, $pollmax, $polltimeout);

// Now the page contents.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('messages', 'message'));

// Display a message if the messages have not been migrated yet.
if (!get_user_preferences('core_message_migrate_data', false, $user1id)) {
    $notify = new \core\output\notification(get_string('messagingdatahasnotbeenmigrated', 'message'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->render($notify);
}

// Display a message that the user is viewing someone else's messages.
if (!$currentuser) {
    $notify = new \core\output\notification(get_string('viewinganotherusersmessagearea', 'message'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->render($notify);
}
echo $renderer->render($messagearea);
echo $OUTPUT->footer();

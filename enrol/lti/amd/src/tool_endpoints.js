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
 * Module supporting the dynamic and manual registration tabs in the tool registration admin setting.
 *
 * @module     enrol_lti/tool_endpoints
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {add as toastNotice} from 'core/toast';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';
import {get_strings as getStrings} from 'core/str';
import {prefetchStrings} from 'core/prefetch';
import 'core/copy_to_clipboard';

const SELECTORS = {
    GENERATE_REGISTRATION_URL_BUTTON: '[id^="lti_generate_registration_url"]',
    REGISTRATION_URL_TABLE: '[id^="lti_registration_url_table_"]',
    URL_VALUE: '[id^="lti_tool_endpoint_url_"]',
    URL_INFO: '[id^="lti_tool_endpoint_url_info_"]',
    URL_COPY_TO_CLIPBOARD: '[data-action="copytoclipboard"]',
    URL_DELETE: '[data-action="delete"]'
};

const removeRegistrationURL = () => {
    getStrings([
        {key: 'registrationurldeleted', component: 'enrol_lti'}
    ])
    .then((deletedStr) => {
        document.querySelector(SELECTORS.REGISTRATION_URL_TABLE).classList.add('hidden');
        let createURLButton = document.querySelector(SELECTORS.GENERATE_REGISTRATION_URL_BUTTON);
        createURLButton.setAttribute('aria-disabled', 'false');
        createURLButton.removeAttribute('aria-label');
        createURLButton.classList.remove('disabled');
        createURLButton.title = '';
        document.querySelector(SELECTORS.URL_VALUE).value = '';
        document.querySelector(SELECTORS.URL_INFO).innerHTML = '';
        createURLButton.focus();

        // Let the user know the URL was deleted.
        toastNotice(deletedStr);
    });
};

const displayRegistrationURL = (generateButton) => {
    let requests = [
        {methodname: 'enrol_lti_get_lti_advantage_registration_url', args: {'createifmissing': true}},
    ];
    Promise.all([
        getStrings([
            {key: 'registrationurlgeneratesuccess', component: 'enrol_lti'},
            {key: 'registrationurlcannotgenerate', component: 'enrol_lti'}
        ]),
        Ajax.call(requests)[0]
    ])
    .then(([[generateSuccessStr, cannotGenerateStr], urlObject]) => {
        document.querySelector(SELECTORS.URL_VALUE).value = urlObject.url;
        document.querySelector(SELECTORS.URL_INFO).innerHTML = urlObject.expirystring;
        document.querySelector(SELECTORS.REGISTRATION_URL_TABLE).classList.remove('hidden');
        generateButton.setAttribute('aria-disabled', 'true');
        generateButton.classList.add('disabled');
        generateButton.title = cannotGenerateStr;
        generateButton.setAttribute('aria-label', cannotGenerateStr);

        toastNotice(generateSuccessStr);
    })
    .catch(Notification.exception);
};

const showDeleteModal = () => {

    return ModalFactory.create({
        type: ModalFactory.types.SAVE_CANCEL,
        large: false,
        title: getString('registrationurldeletetitle', 'enrol_lti'),
        body: getString('registrationurldeletebody', 'enrol_lti')
    })
    .then(modal => {
        modal.setSaveButtonText(getString('registrationurldeleteconfirm', 'enrol_lti'));

        modal.getRoot().on(ModalEvents.save, () => {
            let requests = [
                {methodname: 'enrol_lti_delete_lti_advantage_registration_url', args: []},
            ];
            let promises = Ajax.call(requests);

            promises[0].then((result) => {
                if (result.status === true) {
                    removeRegistrationURL();
                }
            }).catch(Notification.exception);
        });

        modal.getRoot().on(ModalEvents.hidden, function() {
            modal.destroy();
        });

        return modal.show();
    })
    .catch(Notification.exception);
};

const generateRegistrationURLHandler = (event) => {
    const triggerElement = event.target.closest(SELECTORS.GENERATE_REGISTRATION_URL_BUTTON);
    if (triggerElement === null) {
        return;
    }
    event.preventDefault();

    if (event.target.getAttribute('aria-disabled') == "true") {
        return;
    }

    displayRegistrationURL(event.target);
};

const focusURLHandler = (event) => {
    const triggerElement = event.target.closest(SELECTORS.URL_VALUE);
    if (triggerElement === null) {
        return;
    }
    event.preventDefault();

    triggerElement.select();
};

const deleteClickHandler = (event) => {
    const triggerElement = event.target.closest(SELECTORS.URL_DELETE);
    if (triggerElement === null) {
        return;
    }
    event.preventDefault();

    showDeleteModal();
};

export const init = () => {
    prefetchStrings('enrol_lti', [
        'registrationurldeletetitle',
        'registrationurldeletebody',
        'registrationurldeleteconfirm',
        'registrationurlgenerate',
        'registrationurldeleted',
        'registrationurlgeneratesuccess',
        'registrationurlcannotgenerate'
    ]);

    // Event capturing supporting the select on focus behaviour (with text selection permitted on subsequent clicks).
    document.addEventListener('focus', focusURLHandler, true);

    // And delegation for deleting the URL.
    document.addEventListener('click', deleteClickHandler);

    // And delegation for creating a new registration URL.
    document.addEventListener('click', generateRegistrationURLHandler);
};

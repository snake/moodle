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
 * A type of dialogue used as for choosing options.
 *
 * @module     core_course/local/chooser/dialogue
 * @package    core
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import * as ModalEvents from 'core/modal_events';
import selectors from 'core_course/local/activitychooser/selectors';
import * as Templates from 'core/templates';
import {end, arrowLeft, arrowRight, home, enter, space} from 'core/key_codes';
import {addIconToContainer} from 'core/loadingicon';

/**
 * Given an event from the main module 'page' navigate to it's help section via a carousel.
 *
 * @method showModuleHelp
 * @param {jQuery} carousel Our initialized carousel to manipulate
 * @param {Object} moduleData Data of the module to carousel to
 */
const showModuleHelp = (carousel, moduleData) => {
    const help = carousel.find(selectors.regions.help)[0];
    help.innerHTML = '';

    // Add a spinner.
    const spinnerPromise = addIconToContainer(help);

    // Used later...
    let transitionPromiseResolver = null;
    const transitionPromise = new Promise(resolve => {
        transitionPromiseResolver = resolve;
    });

    // Build up the html & js ready to place into the help section.
    const contentPromise = Templates.renderForPromise('core_course/chooser_help', moduleData);

    // Wait for the content to be ready, and for the transition to be complet.
    Promise.all([contentPromise, spinnerPromise, transitionPromise])
        .then(([{html, js}]) => Templates.replaceNodeContents(help, html, js))
        .then(() => {
            help.querySelector(selectors.regions.chooserSummary.description).focus();
            return help;
        })
        .catch(Notification.exception);

    // Move to the next slide, and resolve the transition promise when it's done.
    carousel.one('slid.bs.carousel', () => {
        transitionPromiseResolver();
    });
    // Trigger the transition between 'pages'.
    carousel.carousel('next');
};

/**
 * Register chooser related event listeners.
 *
 * @method registerListenerEvents
 * @param {Promise} modal Our modal that we are working with
 * @param {Map} mappedModules A map of all of the modules we are working with with K: mod_name V: {Object}
 */
const registerListenerEvents = (modal, mappedModules) => {
    const bodyClickListener = e => {
        if (e.target.closest(selectors.actions.optionActions.showSummary)) {
            const carousel = $(modal.getBody()[0].querySelector(selectors.regions.carousel));

            const module = e.target.closest(selectors.regions.chooserOption.container);
            const moduleName = module.dataset.modname;
            const moduleData = mappedModules.get(moduleName);
            showModuleHelp(carousel, moduleData);
        }

        // From the help screen go back to the module overview.
        if (e.target.matches(selectors.actions.closeOption)) {
            const carousel = $(modal.getBody()[0].querySelector(selectors.regions.carousel));

            // Trigger the transition between 'pages'.
            carousel.carousel('prev');
            carousel.on('slid.bs.carousel', () => {
                const allModules = modal.getBody()[0].querySelector(selectors.regions.modules);
                const caller = allModules.querySelector(selectors.regions.getModuleSelector(e.target.dataset.modname));
                caller.focus();
            });
        }
    };

    modal.getBodyPromise()

    // The return value of getBodyPromise is a jquery object containing the body NodeElement.
    .then(body => body[0])

    // Set up the carousel.
    .then(body => {
        $(body.querySelector(selectors.regions.carousel))
            .carousel({
                interval: false,
                pause: true,
                keyboard: false
            });

        return body;
    })

    // Add the listener for clicks on the body.
    .then(body => {
        body.addEventListener('click', bodyClickListener);
        return body;
    })

    // Register event listeners related to the keyboard navigation controls.
    .then(body => {
        initKeyboardNavigation(body, mappedModules);
        return body;
    })
    .catch();

};

/**
 * Initialise the keyboard navigation controls for the chooser.
 *
 * @method initKeyboardNavigation
 * @param {NodeElement} body Our modal that we are working with
 * @param {Map} mappedModules A map of all of the modules we are working with with K: mod_name V: {Object}
 */
const initKeyboardNavigation = (body, mappedModules) => {

    const chooserOptions = body.querySelectorAll(selectors.regions.chooserOption.container);

    Array.from(chooserOptions).forEach((element) => {
        return element.addEventListener('keyup', (e) => {
            const chooserOptions = document.querySelector(selectors.regions.chooserOptions);

            // Check for enter/ space triggers for showing the help.
            if (e.keyCode === enter || e.keyCode === space) {
                if (e.target.matches(selectors.actions.optionActions.showSummary)) {
                    e.preventDefault();
                    const module = e.target.closest(selectors.regions.chooserOption.container);
                    const moduleName = module.dataset.modname;
                    const moduleData = mappedModules.get(moduleName);
                    const carousel = $(body.querySelector(selectors.regions.carousel));
                    carousel.carousel({
                        interval: false,
                        pause: true,
                        keyboard: false
                    });
                    showModuleHelp(carousel, moduleData);
                }
            }

            // Next.
            if (e.keyCode === arrowRight) {
                e.preventDefault();
                const currentOption = e.target.closest(selectors.regions.chooserOption.container);
                const nextOption = currentOption.nextElementSibling;
                const firstOption = chooserOptions.firstElementChild;
                const toFocusOption = clickErrorHandler(nextOption, firstOption);
                focusChooserOption(toFocusOption, currentOption);
            }

            // Previous.
            if (e.keyCode === arrowLeft) {
                e.preventDefault();
                const currentOption = e.target.closest(selectors.regions.chooserOption.container);
                const previousOption = currentOption.previousElementSibling;
                const lastOption = chooserOptions.lastElementChild;
                const toFocusOption = clickErrorHandler(previousOption, lastOption);
                focusChooserOption(toFocusOption, currentOption);
            }

            if (e.keyCode === home) {
                e.preventDefault();
                const currentOption = e.target.closest(selectors.regions.chooserOption.container);
                const firstOption = chooserOptions.firstElementChild;
                focusChooserOption(firstOption, currentOption);
            }

            if (e.keyCode === end) {
                e.preventDefault();
                const currentOption = e.target.closest(selectors.regions.chooserOption.container);
                const lastOption = chooserOptions.lastElementChild;
                focusChooserOption(lastOption, currentOption);
            }
        });
    });
};

/**
 * Focus on a chooser option element and remove the previous chooser element from the focus order
 *
 * @method focusChooserOption
 * @param {HTMLElement} currentChooserOption The current chooser option element that we want to focus
 * @param {HTMLElement} previousChooserOption The previous focused option element
 */
const focusChooserOption = (currentChooserOption, previousChooserOption = false) => {
    if (previousChooserOption !== false) {
        const previousChooserOptionLink = previousChooserOption.querySelector(selectors.actions.addChooser);
        const previousChooserOptionHelp = previousChooserOption.querySelector(selectors.actions.optionActions.showSummary);
        // Set tabindex to -1 to remove the previous chooser option element from the focus order.
        previousChooserOption.tabIndex = -1;
        previousChooserOptionLink.tabIndex = -1;
        previousChooserOptionHelp.tabIndex = -1;
    }

    const currentChooserOptionLink = currentChooserOption.querySelector(selectors.actions.addChooser);
    const currentChooserOptionHelp = currentChooserOption.querySelector(selectors.actions.optionActions.showSummary);
    // Set tabindex to 0 to add current chooser option element to the focus order.
    currentChooserOption.tabIndex = 0;
    currentChooserOptionLink.tabIndex = 0;
    currentChooserOptionHelp.tabIndex = 0;
    // Focus the current chooser option element.
    currentChooserOption.focus();
};

/**
 * Small error handling function to make sure the navigated to object exists
 *
 * @method clickErrorHandler
 * @param {HTMLElement} item What we want to check exists
 * @param {HTMLElement} fallback If we dont match anything fallback the focus
 * @return {String}
 */
const clickErrorHandler = (item, fallback) => {
    if (item !== null) {
        return item;
    } else {
        return fallback;
    }
};

/**
 * Display the module chooser.
 *
 * @method displayChooser
 * @param {HTMLElement} origin The calling button
 * @param {Object} modal Our created modal for the section
 * @param {Array} sectionModules An array of all of the built module information
 */
export const displayChooser = (origin, modal, sectionModules) => {

    // Make a map so we can quickly fetch a specific module's object for either rendering or searching.
    const mappedModules = new Map();
    sectionModules.forEach((module) => {
        mappedModules.set(module.componentname + '_' + module.link, module);
    });

    // Register event listeners.
    registerListenerEvents(modal, mappedModules);

    // We want to focus on the action select when the dialog is closed.
    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });

    // We want to focus on the first chooser option element as soon as the modal is opened.
    modal.getRoot().on(ModalEvents.shown, () => {
        modal.getModal()[0].tabIndex = -1;

        modal.getBodyPromise()
        .then(body => {
            const firstChooserOption = body[0].querySelector(selectors.regions.chooserOption.container);
            focusChooserOption(firstChooserOption);

            return;
        })
        .catch(Notification.exception);
    });

    modal.show();
};

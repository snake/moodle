<?php

require_once(__DIR__ . '/config.php');
require_once($CFG->libdir . '/formslib.php');

class test_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'myheader', "Tool Placements");
        $mform->setExpanded('myheader');
        $mform->addElement(
            'autocomplete',
            'myautocomplete',
            "Placement type",
            [
                1 => 'Activity Chooser',
                2 => 'Course Navigation',
                3 => 'Third placement type',
                4 => 'Fourth placement type',
            ],
            ['multiple' => true]
        );

        // For rule demonstration purposes, only a single element will be added.
        // This should be shown when "Third placement type" is selected (or included in the selection).
        $mform->addElement('text', 'hiddenplacementtext', "Hidden field for the third placement type");

        // Now that the 'in' rule for multi-select has been fixed, supporting the desired behaviour is as simple as:
        $mform->hideIf('hiddenplacementtext', 'myautocomplete[]', 'in', ['1', '2', '4', '']);
        // 1 rule per element!

        $this->add_action_buttons();
    }
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/testform.php');
echo $OUTPUT->header();

$multiselectformurl = new moodle_url('lib/form/tests/fixtures/multiselect_hideif_disabledif_form.php');

echo $OUTPUT->notification("<h3>Autocomplete-multiselect/hideIf discovery</h3>
    <ol>
    <li>The objective is to have each option in the 'autocomplete' form element control visibility for one or more form elements. Including a given option in the selection should show 1 or more elements corresponding to that option.</li>
    <li>Note: Only \"Third placement type\" will result in an element being shown in this form. The other options are included for rule testing purposes only (see backend code). </li>
    <li>The form element used here is 'autocomplete' with 'multiple' set to true. This element supports hideIf/disabledIf rules <b> and the 'in' rule has been patched, now supporting in via a subset check</b>.</li>
    <li>This form doesn't demonstrate section hideif which is unsupported. See <a href=\"https://tracker.moodle.org/browse/MDL-82996\">MDL-82996 which deals with that.</a></li>
    </ol>", 'info');

$form = new test_form();
if ($data = $form->get_data()) {
    echo $OUTPUT->notification("Submit does nothing in this demo form.", 'success');
    print_object($data);
}

$form->display();
echo $OUTPUT->footer();

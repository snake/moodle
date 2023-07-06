@mod @mod_lti
Feature: Manage course tools
  In order to provide richer experiences for learners
  As a teacher
  I need to be able to add external tools to a course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

  Scenario: Create a course tool from the zero state
    Given I am on the "Course 1" course page logged in as teacher1
    And I navigate to "LTI External tools" in current page administration
    And I should see "LTI External tools are add-on apps"
    And I should see "There are no LTI external tools yet"
    When I click on "Add tool" "link"
    And I press "Cancel"
    Then I should see "LTI External tools are add-on apps"
    And I should see "There are no LTI external tools yet"
    And I click on "Add tool" "link"
    And I set the following fields to these values:
      | Tool name | Teaching Tool 1 |
      | Tool URL | http://example.com |
      | Tool description | A short description of the tool |
    And I press "Save changes"
    And I should see "Successfully added course tool"
    And I should see "A short description of the tool" in the "Teaching Tool 1" "table_row"

  Scenario: Viewing a site level tool in the course tools table
    Given the following "mod_lti > tool types" exist:
      | name      | description         | baseurl                   |
      | Test tool | Example description | https://example.com/tool |
    And I am on the "Course 1" course page logged in as teacher1
    When I navigate to "LTI External tools" in current page administration
    Then "This is a site level tool which cannot be edited" "icon" should exist in the "Test tool" "table_row"

  Scenario: Viewing course tools without the capability to add/edit but having the capability to use
    Given the following "role capability" exists:
      | role                                | editingteacher |
      | mod/lti:addcoursetool               | prohibit       |
      | mod/lti:addpreconfiguredinstance    | allow          |
    And the following "mod_lti > course tools" exist:
      | name      | description         | baseurl                   | course |
      | Test tool | Example description | https://example.com/tool | C1     |
    And I am on the "Course 1" course page logged in as teacher1
    When I navigate to "LTI External tools" in current page administration
    Then "You don't have permission to add or edit course tools" "icon" should exist in the "Test tool" "table_row"

  @javascript
  Scenario: Edit a course tool
    Given the following "mod_lti > course tools" exist:
      | name      | description         | baseurl                   | course |
      | Test tool | Example description | https://example.com/tool | C1     |
    And I am on the "Course 1" course page logged in as teacher1
    And I navigate to "LTI External tools" in current page administration
    And the "Edit" item should exist in the "Actions" action menu of the "Test tool" "table_row"
    And the "Delete" item should exist in the "Actions" action menu of the "Test tool" "table_row"
    When I open the action menu in "Test tool" "table_row"
    And I choose "Edit" in the open action menu
    And I press "Cancel"
    Then I should see "Test tool" in the "course-tools" "table"
    And I open the action menu in "Test tool" "table_row"
    And I choose "Edit" in the open action menu
    And I set the following fields to these values:
      | Tool name | Test tool (edited) |
      | Tool URL | http://example.com |
      | Tool description | A short description of the tool (edited) |
    And I press "Save changes"
    And I should see "The changes to the course tool 'Test tool (edited)' were saved"
    And I should see "A short description of the tool (edited)" in the "Test tool (edited)" "table_row"

  @javascript
  Scenario: Navigate through the listing of course tools
    Given 20 "mod_lti > course tools" exist with the following data:
    |name        |Test tool [count]                  |
    |description |Example description [count]        |
    |baseurl     |https://www.example.com/tool[count]|
    |course      |C1                                 |
    And I am on the "Course 1" course page logged in as teacher1
    When I navigate to "LTI External tools" in current page administration
    Then I should see "Test tool 1" in the "course-tools" "table"
    And I click on "Name" "link"
    And I should see "Test tool 20" in the "course-tools" "table"
    And I click on "2" "link" in the "page" "region"
    And I should see "Test tool 1" in the "course-tools" "table"

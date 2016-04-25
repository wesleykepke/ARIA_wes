<?php

/**
 * The file that defines the functionality that should take place after
 * particular forms are submitted by students and teachers.
 *
 * @link       http://wesleykepke.github.io/ARIA/
 * @since      1.0.0
 *
 * @package    ARIA
 * @subpackage ARIA/includes
 */

// Required files
require_once("class-aria-api.php");
require_once("class-aria-registration-handler.php");
require_once("class-aria-create-competition.php");

/**
 * The aria form hooks class.
 *
 * @since      1.0.0
 * @package    ARIA
 * @subpackage ARIA/includes
 * @author     KREW
 */
class ARIA_Form_Hooks {

  /**
   * This function will prepopulate the teacher drop down menu.
   *
   * This function is responsible for taking all of the entries that exist
   * in the associated competition's teacher master form and use those entries
   * to pre-populate the drop-down menu.
   *
   * @param $form 	Form Object 	The current form object.
   * @param $is_ajax 	Bool 	Specifies if the form is submitted via AJAX
   *
   * @since 1.0.0
   * @author KREW
   */
  public static function aria_before_student_submission($form, $is_ajax) {
    // Only perform processing if it's a student form
    if (!array_key_exists('isStudentPublicForm', $form)
        || !$form['isStudentPublicForm']) {
          return;
    }

    // get all of the teacher entries in the form's teacher master form
    $teacher_master_field_mapping = ARIA_API::aria_master_teacher_field_id_array();
    $related_forms = $form['aria_relations'];

    // sort by teacher last name
    $search = array();
    $sorting = array( 'key' => $teacher_master_field_mapping['last_name'], 'direction' => 'ASC', 'is_numeric' => false );
    $paging = array('offset' => 0, 'page_size' => 2000);
    $total_count = 0;
    $teacher_entries = GFAPI::get_entries($related_forms['teacher_master_form_id'], $search, $sorting, $paging, $total_count);
    $formatted_teacher_names = array();


    foreach ($teacher_entries as $entry) {
      $single_teacher = array(
        'text' => $entry[strval($teacher_master_field_mapping['first_name'])] . " " .  $entry[strval($teacher_master_field_mapping['last_name'])],
        'value' => $entry[strval($teacher_master_field_mapping['first_name'])] . " " .  $entry[strval($teacher_master_field_mapping['last_name'])],
        'isSelected' => false
      );
      $formatted_teacher_names[] = $single_teacher;
      unset($single_teacher);
    }

    // update the current form with the previously obtained teachers
    $student_field_mapping = ARIA_API::aria_student_field_id_array();
    $search_field = $student_field_mapping['teacher_name'];
    $name_field = ARIA_API::aria_find_field_by_id($form['fields'], $search_field);
    $form['fields'][$name_field]->choices = $formatted_teacher_names;
  }

  /**
   * This function will be the hook that is called after a student submits their
   * information for a new music competition. This function will take all of the
   * information that the student submitted and update corresponding data in the
   * student form, the student master form, and the teacher master form.
   *
   * @param		$form		GF Forms Object		The form this function is attached to.
   * @param		$entry	GF Entry Object		The entry that is returned after form submission.
   *
   * @since 1.0.0
   * @author KREW
   */
  public static function aria_after_student_submission($entry, $feed, $transaction_id, $amount) {

    $form_id = $entry['form_id'];
    $form = GFAPI::get_form($form_id);

    // Only perform processing if it's a student form
    if (!array_key_exists('isStudentPublicForm', $form)
        || !$form['isStudentPublicForm']) {
          return;
    }

    // Find the 4 related forms that pertain to $form
    $related_forms = $form['aria_relations'];

    // Find out the information associated with the $entry variable
    $student_fields = ARIA_API::aria_student_field_id_array();

    /* teacher master has not been fully checked because form doesn't look complete? */
    $teacher_master_fields = ARIA_API::aria_master_teacher_field_id_array();
    $student_master_fields = ARIA_API::aria_master_student_field_id_array();

    // Hash for teacher (just has the teacher name)
    if (!empty($entry[$student_fields['not_listed_teacher_name']])) {
      // student entered a name that didn't appear in the drop-down menu
      $teacher_name = $entry[$student_fields['not_listed_teacher_name']];

    }
    else {
      $teacher_name = $entry[(string)$student_fields["teacher_name"]];
    }
    $teacher_hash = hash("md5", $teacher_name);

    // Hash for student (student name and entry date)
    $student_name =
        $entry[(string)$student_fields["student_first_name"]];
    $student_name .= ' ' .
        $entry[(string)$student_fields["student_last_name"]];
    $student_name_and_entry = $student_name;
    $student_name_and_entry .= ' ' . $entry["date_created"];

    $student_hash = hash("md5", $student_name_and_entry);


    // Search through the teacher master form to see if the teacher has an entry made
    $teacher_entry =
      ARIA_Registration_Handler::aria_find_teacher_entry($related_forms['teacher_master_form_id'],
        $teacher_hash);

    if ($teacher_entry) {
      // Determine whether a student has been added or not (if it's an array)
      $students = $teacher_entry[strval($teacher_master_fields["students"])];
      $students = unserialize($students);

      if (!is_array($students)) {
        $students = array();
      }

      // Add the newly registered student to the teacher's list of student hashes
      $students[] = $student_hash;
      $teacher_entry[strval($teacher_master_fields["students"])] = serialize($students);

      // Update the teacher entry with the new student edition
      $result = GFAPI::update_entry($teacher_entry);
      if (is_wp_error($result)) {
        wp_die(__LINE__.$result->get_error_message());
      }
    }
    else {
      // Create a new teacher and apply some default values
      $teacher_name = explode(" ", $teacher_name);
      $new_teacher_entry = array();
      $new_teacher_entry[] = array(
        strval($teacher_master_fields["students"]) => serialize(array($student_hash)),
        strval($teacher_master_fields["first_name"]) => $teacher_name[0],
        strval($teacher_master_fields["last_name"]) => sizeof($teacher_name) > 1 ? $teacher_name[1] : '',
        strval($teacher_master_fields["email"]) => null,
        strval($teacher_master_fields["phone"]) => null,
        strval($teacher_master_fields["volunteer_preference"]) => null,
        strval($teacher_master_fields["volunteer_time"]) => null,
        strval($teacher_master_fields["is_judging"]) => null,
        strval($teacher_master_fields["teacher_hash"]) => $teacher_hash
      );

      $result = GFAPI::add_entries($new_teacher_entry, $related_forms['teacher_master_form_id']);
      if (is_wp_error($result)) {
        wp_die(__LINE__.$result->get_error_message());
      }
    }

    // Make a new student master entry with the student hash
    $new_student_master_entry = array();
    $new_student_master_entry[] = array(
      strval($student_master_fields["parent_name"]) => null,
      strval($student_master_fields["parent_first_name"]) => $entry[strval($student_fields["parent_first_name"])],
      strval($student_master_fields["parent_last_name"]) => $entry[strval($student_fields["parent_last_name"])],
      strval($student_master_fields["parent_email"]) => $entry[strval($student_fields["parent_email"])],
      strval($student_master_fields["student_name"]) => null,
      strval($student_master_fields["student_first_name"]) => $entry[strval($student_fields["student_first_name"])],
      strval($student_master_fields["student_last_name"]) => $entry[strval($student_fields["student_last_name"])],
      strval($student_master_fields["student_level"]) => $entry[strval($student_fields["student_level"])],
      strval($student_master_fields["student_birthday"]) => $entry[strval($student_fields["student_birthday"])],
      strval($student_master_fields["teacher_name"]) => $entry[strval($student_fields["teacher_name"])],
      strval($student_master_fields["available_festival_days"]) => $entry[strval($student_fields["available_festival_days"])],
      strval($student_master_fields["preferred_command_performance"]) => $entry[strval($student_fields["preferred_command_performance"])],
      strval($student_master_fields["song_1_period"]) => null,
      strval($student_master_fields["song_1_composer"]) => null,
      strval($student_master_fields["song_1_selection"]) => null,
      strval($student_master_fields["song_2_period"]) => null,
      strval($student_master_fields["song_2_composer"]) => null,
      strval($student_master_fields["song_2_selection"]) => null,
      strval($student_master_fields["theory_score"]) => null,
      strval($student_master_fields["alternate_theory"]) => null,
      strval($student_master_fields["competition_format"]) => null,
      strval($student_master_fields["timing_of_pieces"]) => null,
      strval($student_master_fields["hash"]) => $student_hash
    );

    // add the newly created student to the competition master form
    $student_result = GFAPI::add_entries($new_student_master_entry, $related_forms['student_master_form_id']);
    if (is_wp_error($student_result)) {
      wp_die(__LINE__.$student_result->get_error_message());
    }

    // determine how many students have registered so far
    $search_criteria = array();
    $sorting = null;
    $paging = array('offset' => 0, 'page_size' => 2000);
    $total_count = 0;
    $entries = GFAPI::get_entries($related_forms['student_master_form_id'], $search_criteria,
                                  $sorting, $paging, $total_count);

    // consolidate information for emails
    $email_info = array();
    $email_info['teacher_hash'] = $teacher_hash;
    $email_info['teacher_name'] = $teacher_name;
    $email_info['teacher_email'] = $teacher_entry[strval($teacher_master_fields["email"])];
    $email_info['festival_chairman_email'] = $related_forms["festival_chairman_email"];
    $email_info['notification_email'] = $related_forms["notification_email"];
    $email_info['festival_chairman_email'] = $related_forms["festival_chairman_email"];
    $email_info['parent_email'] = $entry[strval($student_fields["parent_email"])];
    $email_info['teacher_url'] = $related_forms["teacher_public_form_url"];
    $email_info['student_hash'] = $student_hash;
    $email_info['student_name'] = $student_name;
    $email_info['parent_name'] = $entry[strval($student_fields["parent_first_name"])] .
    " " . $entry[strval($student_fields["parent_last_name"])];
    $comp_name = strpos($form['title'], 'Student Registration');
    $comp_name = substr($form['title'], 0, $comp_name - 1);
    $email_info['competition_name'] = $comp_name;
    $email_info['num_participants'] = count($entries);

    // send emails to various parties (parents, teachers, festival chairman)
    ARIA_Registration_Handler::aria_send_registration_emails($email_info);
  }

  public static function aria_before_teacher_render($form, $is_ajax) {
    // Only perform processing if it's a teacher form
    if (!array_key_exists('isTeacherPublicForm', $form)
        || !$form['isTeacherPublicForm']) {
        return $form;
    }

    // Get the query variables from the link
    $student_hash = get_query_var("student_hash", false);
    $teacher_hash = get_query_var("teacher_hash", false);

    $error = "You cannot access this form. Check your email to get the correct link to access this form correctly.";

    // If they dont exist redirect to home
    if (!$student_hash || !$teacher_hash) {
      wp_die($error);
    }

    // Get the related forms of the form
    $related_forms = $form['aria_relations'];

    // Check if the variables exist as a teacher-student combination
    // If they dont exist redirect home.
    if (!ARIA_Registration_Handler::aria_check_student_teacher_relationship($related_forms, $student_hash, $teacher_hash)) {
      wp_die($error);
    }

    // Do form prepopulation
    $teacher_prepopulation_values = ARIA_Registration_Handler::aria_get_teacher_pre_populate($related_forms, $teacher_hash);
    $student_prepopulation_values = ARIA_Registration_Handler::aria_get_student_pre_populate($related_forms, $student_hash);

    $prepopulated_form = ARIA_Registration_Handler::aria_prepopulate_form(
                  $form, $teacher_prepopulation_values, $student_prepopulation_values);


    return $prepopulated_form;
  }

  /**
   * This function will be the hook that is called after a teacher submits
   * information for a particular student. This function will take all of the
   * information that the teacher submitted and update corresponding data in the
   * teacher form, the student master form, and the teacher master form.
   *
   * @param		$form		GF Forms Object		The form this function is attached to.
   * @param		$entry	GF Entry Object		The entry that is returned after form submission.
   *
   * @since 1.0.0
   * @author KREW
   */
  public static function aria_after_teacher_submission($entry, $form) {
    // Only perform processing if it's a teacher form
    if (!array_key_exists('isTeacherPublicForm', $form)
        || !$form['isTeacherPublicForm']) {
          return;
    }

    // Find the 4 related forms that pertain to $form
    $related_forms = $form['aria_relations'];

    /*
    Get the query variables from the link.
    Link must be in a format like the following:
    wesley-bruh-teacher-registration-4/?teacher_hash=fredharris&student_hash=weskepke
    Note the & in the url above.
    */
    $student_hash = get_query_var("student_hash", false);
    $teacher_hash = get_query_var("teacher_hash", false);

    // Get field id arrays
    $student_master_field_ids = ARIA_API::aria_master_student_field_id_array();
    $teacher_master_field_ids = ARIA_API::aria_master_teacher_field_id_array();
    $teacher_public_field_ids = ARIA_API::aria_teacher_field_id_array();

    // Locate the teacher entry in the teacher master.
    $teacher_master_entry =
      ARIA_Registration_Handler::aria_find_teacher_entry($related_forms['teacher_master_form_id'],
      $teacher_hash);

    // If the teacher doesn't exist, throw an error message
    if (!$teacher_master_entry) {
      wp_die("Error: aria_after_teacher_submission() could not locate the specified teacher.");
    }

    // If the teacher does exist, update the teacher master with the new information
/*    $teacher_master_entry[strval($teacher_master_field_ids['first_name'])] =
      $entry[strval($teacher_public_field_ids['first_name'])];
    $teacher_master_entry[strval($teacher_master_field_ids['last_name'])] =
      $entry[strval($teacher_public_field_ids['last_name'])];*/
    $teacher_master_entry[strval($teacher_master_field_ids['email'])] =
      $entry[strval($teacher_public_field_ids['email'])];
    $teacher_master_entry[strval($teacher_master_field_ids['phone'])] =
      $entry[strval($teacher_public_field_ids['phone'])];

    $volunteer_pref_field = ARIA_Registration_Handler::aria_find_field_by_id($form['fields'],
        $teacher_public_field_ids['volunteer_preference']);
    for($i=1; $i <= count($form['fields'][$volunteer_pref_field]['choices']); $i++){
      if(isset($entry[strval($teacher_public_field_ids['volunteer_preference']) . '.' . strval($i)])){
        $teacher_master_entry[strval($teacher_master_field_ids['volunteer_preference']) . '.' . strval($i)] =
          $entry[strval($teacher_public_field_ids['volunteer_preference']) . '.' . strval($i)];
      }
      else{
        $teacher_master_entry[strval($teacher_master_field_ids['volunteer_preference']) . '.' . strval($i)] =
          null;
      }
    }

    $volunteer_time_field = ARIA_Registration_Handler::aria_find_field_by_id($form['fields'],
        $teacher_public_field_ids['volunteer_time']);
    for($i=1; $i <= count($form['fields'][$volunteer_time_field]['choices']); $i++){
      if(isset($entry[strval($teacher_public_field_ids['volunteer_time']) . '.' . strval($i)])){
        $teacher_master_entry[strval($teacher_master_field_ids['volunteer_time']) . '.' . strval($i)] =
          $entry[strval($teacher_public_field_ids['volunteer_time']) . '.' . strval($i)];
      }
      else{
        $teacher_master_entry[strval($teacher_master_field_ids['volunteer_time']) . '.' . strval($i)] =
          null;
      }
    }


    $teacher_master_entry[strval($teacher_master_field_ids['is_judging'])] =
      $entry[strval($teacher_public_field_ids['is_judging'])];
    $teacher_master_entry[strval($teacher_master_field_ids['schedule_with_students'])] =
      $entry[strval($teacher_public_field_ids['schedule_with_students'])];

    // Update the teacher master form with the new information
    $result = GFAPI::update_entry($teacher_master_entry);
		if (is_wp_error($result)) {
			wp_die($result->get_error_message());
		}

    // Locate the student entry in the student master.
    $student_master_entry = ARIA_Registration_Handler::aria_find_student_entry($form["title"], $student_hash);

    // If the student doesn't exist, throw an error message
    if (!$student_master_entry) {
      wp_die("Error: aria_after_teacher_submission() could not locate the specified student.");
    }

    // If the student does exist, update the student master with the new information

    $student_master_entry[strval($student_master_field_ids['song_1_period'])] =
      $entry[strval($teacher_public_field_ids['song_1_period'])];
    $student_master_entry[strval($student_master_field_ids['song_1_composer'])] =
      $entry[strval($teacher_public_field_ids['song_1_composer'])];
    $student_master_entry[strval($student_master_field_ids['song_1_selection'])] =
      $entry[strval($teacher_public_field_ids['song_1_selection'])];

    // if student level != 11
    if($student_master_entry[strval($student_master_field_ids['student_level'])] != '11')
    {
      $student_master_entry[strval($student_master_field_ids['song_2_period'])] =
        $entry[strval($teacher_public_field_ids['song_2_period'])];
      $student_master_entry[strval($student_master_field_ids['song_2_composer'])] =
        $entry[strval($teacher_public_field_ids['song_2_composer'])];
      $student_master_entry[strval($student_master_field_ids['song_2_selection'])] =
        $entry[strval($teacher_public_field_ids['song_2_selection'])];
    }
    else
    {
      // if student level == 11
      $student_master_entry[strval($student_master_field_ids['song_2_composer'])] =
        $entry[strval($teacher_public_field_ids['alt_song_2_composer'])];
      $student_master_entry[strval($student_master_field_ids['song_2_selection'])] =
        $entry[strval($teacher_public_field_ids['alt_song_2_selection'])];
    }



    $student_master_entry[strval($student_master_field_ids['theory_score'])] =
      $entry[strval($teacher_public_field_ids['theory_score'])];

    $alt_theory_field = ARIA_Registration_Handler::aria_find_field_by_id($form['fields'],
        $teacher_public_field_ids['alternate_theory']);
    for($i=1; $i <= count($form['fields'][$alt_theory_field]['choices']); $i++){
      if(isset($entry[strval($teacher_public_field_ids['alternate_theory']) . '.' . strval($i)])){
        $student_master_entry[strval($student_master_field_ids['alternate_theory']) . '.' . strval($i)] =
          $entry[strval($teacher_public_field_ids['alternate_theory']) . '.' . strval($i)];
      }
      else{
        $student_master_entry[strval($student_master_field_ids['alternate_theory']) . '.' . strval($i)] =
          null;
      }
    }

    $student_master_entry[strval($student_master_field_ids['competition_format'])] =
      $entry[strval($teacher_public_field_ids['competition_format'])];
    $student_master_entry[strval($student_master_field_ids['timing_of_pieces'])] =
      $entry[strval($teacher_public_field_ids['timing_of_pieces'])];

    // Update the student master form with the new information
    $result = GFAPI::update_entry($student_master_entry);
		if (is_wp_error($result)) {
			wp_die($result->get_error_message());
		}
  }

  /**
   * This function will expose the new, custom query variables to WP_Query.
   *
   * In order for ARIA's query hash method to work, specific query vars (the
   * query vars that will be added to URLs) need to be added to the public
   * query variables that are available to WP_Query. This function is
   * responsible for adding these query vars to the $query_vars property of
   * WP_Query.
   *
   * @param $vars   Array   The array of query vars passed via the filter
   *
   * @since 1.0.0
   * @author KREW
  */
  public static function aria_add_query_vars_filter($vars) {
    $vars[] = "teacher_hash";
    $vars[] = "student_hash";
    return $vars;
  }


}

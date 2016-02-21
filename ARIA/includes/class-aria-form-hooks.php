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

  /*
  The following two functions are intended only for testing purposes.
  */
  public static function after_student_master_submission($entry, $form) {
    wp_die(json_encode($entry));
  }

  public static function aria_teacher_master_submission($entry, $form) {
    wp_die(json_encode($entry));
  }

  /**
   * This function will be the hook that is called after a student public form
   * entry is made. This is to get all information from the student form and
   * make updates in the teacher master and the student master form.
   *
   * This can be used as such:
   * add_action(
   * 'gform_after_submission_x', 'aria_after_student_submission', 10, 2);
   *
   * @param		$form		GF Forms Object		The form this function is attached to.
   * @param		$entry	GF Entry Object		The entry that is returned after form submission.
   *
   * @since 1.0.0
   * @author KREW
   */
  public static function aria_after_student_submission($entry, $form) {
    // Only perform processing if it's a student form
    if (!array_key_exists('isStudentPublicForm', $form)
        || !$form['isStudentPublicForm']) {
          return;
    }

    // Find the 4 related forms that pertain to $form
    $related_forms = $form['aria_relations'];

    // Find out the information associated with the $entry variable
    $student_fields = ARIA_Create_Competition::aria_student_field_id_array();

    /* teacher master has not been fully checked because form doesn't look complete? */
    $teacher_master_fields = ARIA_Create_Master_Forms::aria_master_teacher_field_id_array();
    $student_master_fields = ARIA_Create_Master_Forms::aria_master_student_field_id_array();

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
    $student_name_and_entry =
        $entry[(string)$student_fields["student_first_name"]];
    $student_name_and_entry .=
        $entry[(string)$student_fields["student_last_name"]];
    $student_name_and_entry .= $entry["date_created"];
    $student_hash = hash("md5", $student_name_and_entry);

    // Search through the teacher master form to see if the teacher has an entry made
    $teacher_entry =
      ARIA_Registration_Handler::aria_find_teacher_entry($related_forms['teacher_master_form_id'],
      $teacher_hash);

    if ($teacher_entry) {
      // Add the newly registered student to the teacher's list of student hashes
      $teacher_entry[strval($teacher_master_fields["students"])][] = $student_hash;
    }
    else {
      // Create a new teacher and apply some default values
      $teacher_name = explode(" ", $teacher_name);
      $new_teacher_entry = array();
      $new_teacher_entry[] = array(
        strval($teacher_master_fields["students"]) => array($student_hash),
        strval($teacher_master_fields["first_name"]) => $teacher_name[0],
        strval($teacher_master_fields["last_name"]) => $teacher_name[1],
        strval($teacher_master_fields["email"]) => null,
        strval($teacher_master_fields["phone"]) => null,
        strval($teacher_master_fields["volunteer_preference"]) => null,
        strval($teacher_master_fields["volunteer_time"]) => null,
        strval($teacher_master_fields["is_judging"]) => null,
        strval($teacher_master_fields["hash"]) => $teacher_hash
      );

      $result = GFAPI::add_entries($new_teacher_entry, $related_forms['teacher_master_form_id']);
      if (is_wp_error()) {
        wp_die($result->get_error_message());
      }
    }

    // Make a new student master entry with the student hash
    $new_student_master_entry = array();
    $new_student_master_entry[] = array(
      strval($student_master_fields["parent_name"]) => $entry[strval($student_fields["parent_name"])],
      strval($student_master_fields["parent_first_name"]) => $entry[strval($student_fields["parent_first_name"])],
      strval($student_master_fields["parent_last_name"]) => $entry[strval($student_fields["parent_last_name"])],
      strval($student_master_fields["parent_email"]) => $entry[strval($student_fields["parent_email"])],
      strval($student_master_fields["student_name"]) => $entry[strval($student_fields["student_name"])],
      strval($student_master_fields["student_first_name"]) => $entry[strval($student_fields["student_first_name"])],
      strval($student_master_fields["student_last_name"]) => $entry[strval($student_fields["student_last_name"])],
      strval($student_master_fields["student_birthday"]) => $entry[strval($student_fields["student_birthday"])],
      strval($student_master_fields["teacher_name"]) => $entry[strval($student_fields["teacher_name"])],
      strval($student_master_fields["not_listed_teacher_name"]) => $entry[strval($student_fields["not_listed_teacher_name"])],
      strval($student_master_fields["available_festival_days"]) => $entry[strval($student_fields["available_festival_days"])],
      strval($student_master_fields["available_festival_days_saturday"]) => $entry[strval($student_fields["available_festival_days_saturday"])],
      strval($student_master_fields["available_festival_days_sunday"]) => $entry[strval($student_fields["available_festival_days_sunday"])],
      strval($student_master_fields["preferred_command_performance"]) => $entry[strval($student_fields["preferred_command_performance"])],
      strval($student_master_fields["preferred_command_performance_earlier"]) => $entry[strval($student_fields["preferred_command_performance_earlier"])],
      strval($student_master_fields["preferred_command_performance_later"]) => $entry[strval($student_fields["preferred_command_performance_later"])],
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

    $student_result = GFAPI::add_entries($new_student_master_entry, $related_forms['student_master_form_id']);
    if (is_wp_error($student_result)) {
      wp_die($student_result->get_error_message());
    }
  }

  public static function aria_before_teacher_render($form) {
    // Get the query variables from the link
    $student_hash = get_query_var("student_hash", false);
    $teacher_hash = get_query_var("teacher_hash", false);

    // If they dont exist redirect to home
    if (!$student_hash || !$teacher_hash) {
      wp_redirect( home_url() );
      exit();
    }

    // Check if the variables exist as a teacher-student combination
    // If they dont exist redirect home.
    if (!ARIA_Registration_Handler::aria_check_student_teacher_relationship($form["title"], $student_hash, $teacher_hash)) {
      wp_redirect( home_url() );
      exit();
    }

    // Do form prepopulation
    $teacher_prepopulation_values = ARIA_Registration_Handler::aria_get_teacher_pre_populate($form['title'], $teacher_hash);
    $student_prepopulation_values = ARIA_Registration_Handler::aria_get_student_pre_populate($form['title'], $student_hash);


	// !!!RENEE HERE
  }

  public static function aria_after_teacher_submission($form, $entry) {
    // Get the query variables from the link
    $student_hash = get_query_var("student_hash", false);
    $teacher_hash = get_query_var("teacher_hash", false);

    // Get field id arrays
    $student_master_field_ids = ARIA_Create_Master_Forms::aria_master_student_field_id_array();
    $teacher_master_field_ids = ARIA_Create_Master_Forms::aria_master_teacher_field_id_array();
    $teacher_public_field_ids = ARIA_Create_Competition::aria_master_teacher_field_id_array();

    // Update the teacher entry in the teacher master.
    $teacher_master_entry = ARIA_Registration_Handler::aria_find_teacher_entry($form["title"], $teacher_hash);
    if (!teacher_entry) {
      wp_die("Error");
    }
    $teacher_master_entry[(string) $teacher_master_field_ids['name']] = $entry[(string) $teacher_public_field_ids['name']];
    $teacher_master_entry[(string) $teacher_master_field_ids['email']] = $entry[(string) $teacher_public_field_ids['email']];
    $teacher_master_entry[(string) $teacher_master_field_ids['phone']] = $entry[(string) $teacher_public_field_ids['phone']];
    $teacher_master_entry[(string) $teacher_master_field_ids['volunteer_preference']] = $entry[(string) $teacher_public_field_ids['volunteer_preference']];
    $teacher_master_entry[(string) $teacher_master_field_ids['volunteer_time']] = $entry[(string) $teacher_public_field_ids['volunteer_time']];
    $teacher_master_entry[(string) $teacher_master_field_ids['is_judging']] = $entry[(string) $teacher_public_field_ids['is_judging']];

    // Update the student entry in the student master.
    $student_master_entry = ARIA_Registration_Handler::aria_find_student_entry($form["title"], $student_hash);
    if (!student_entry) {
      wp_die("Error");
    }

    $student_master_entry[(string) $student_master_field_ids['student_name']] = $entry[(string) $teacher_public_field_ids['student_name']];
    $student_master_entry[(string) $student_master_field_ids['song_1_period']] = $entry[(string) $teacher_public_field_ids['song_1_period']];
    $student_master_entry[(string) $student_master_field_ids['song_1_composer']] = $entry[(string) $teacher_public_field_ids['song_1_composer']];
    $student_master_entry[(string) $student_master_field_ids['song_1_selection']] = $entry[(string) $teacher_public_field_ids['song_1_selection']];
    $student_master_entry[(string) $student_master_field_ids['song_2_period']] = $entry[(string) $teacher_public_field_ids['song_2_period']];
    $student_master_entry[(string) $student_master_field_ids['song_2_composer']] = $entry[(string) $teacher_public_field_ids['song_2_composer']];
    $student_master_entry[(string) $student_master_field_ids['song_2_selection']] = $entry[(string) $teacher_public_field_ids['song_2_selection']];
    $student_master_entry[(string) $student_master_field_ids['theory_score']] = $entry[(string) $teacher_public_field_ids['theory_score']];
    $student_master_entry[(string) $student_master_field_ids['alternate_theory']] = $entry[(string) $teacher_public_field_ids['alternate_theory']];
    $student_master_entry[(string) $student_master_field_ids['competition_format']] = $entry[(string) $teacher_public_field_ids['competition_format']];
    $student_master_entry[(string) $student_master_field_ids['timing_of_pieces']] = $entry[(string) $teacher_public_field_ids['timing_of_pieces']];

    $teacher_result = GFAPI::update_entry( $teacher_master_entry );
    $student_result = GFAPI::update_entry( $student_master_entry );
  }
}

// In order to make use of query vals of these functions, this filter must be,
// added to the query vars.
function aria_add_query_vars_filter( $vars ){
  $vars[] = "teacher_hash";
  $vars[] .= "student_hash";
  return $vars;
}
add_filter( 'query_vars', 'aria_add_query_vars_filter' );

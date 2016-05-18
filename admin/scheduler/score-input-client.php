<?php

require_once("class-aria-scheduler.php");

function determine_function_call() {
  switch ($_POST['funcToCall']) {
    case "update_scores":
      update_scores();
    break;

    case "print_trophy_list":
      print_trophy_list();
    break;
  }
}

function update_scores() {
  // determine the file path of the associated competition
  $title = str_replace(' ', '_', $_POST['compName']);
  $file_path = dirname(__FILE__);
  $parsed_file_path = explode('/', $file_path);
  $file_path = "";
  $parsed_file_path_index = 0;
  while ($parsed_file_path[$parsed_file_path_index] != "plugins") {
    $file_path .= $parsed_file_path[$parsed_file_path_index] . "/";
    $parsed_file_path_index++;
  }
  $file_path .= "uploads/$title.txt";

  ////echo print_r($_POST);
  ////echo "File path: $file_path <br>";

  // read the serialized Scheduler object from file
  if (file_exists($file_path)) {
    $scheduler = file_get_contents($file_path);
    $scheduler = unserialize($scheduler);

    // update all of the scores in the scheduler
    $scheduler->update_student_scores($_POST['students']);

    // write the scheduler object back out to file
    $scheduler_data = serialize($scheduler);
    $fp = fopen($file_path, 'w+');
    if ($fp) {
      fwrite($fp, $scheduler_data);
      fclose($fp);
    }
    else {
      echo "Did not save correctly.";
    }
  }
  else {
    //echo "Documents have not been generated because file doesn't exist";
  }
}

function print_trophy_list() {
  // determine the file path of the associated competition
  $title = str_replace(' ', '_', $_POST['compName']);
  $file_path = dirname(__FILE__);
  $parsed_file_path = explode('/', $file_path);
  $file_path = "";
  $parsed_file_path_index = 0;
  while ($parsed_file_path[$parsed_file_path_index] != "plugins") {
    $file_path .= $parsed_file_path[$parsed_file_path_index] . "/";
    $parsed_file_path_index++;
  }
  $file_path .= "uploads/$title.txt";

  ////echo print_r($_POST);
  ////echo "File path: $file_path <br>";

  // read the serialized Scheduler object from file
  if (file_exists($file_path)) {
    $scheduler = file_get_contents($file_path);
    $scheduler = unserialize($scheduler);

    // update all of the scores in the scheduler
    $scheduler->print_trophy_list(); 

    // write the scheduler object back out to file
    $scheduler_data = serialize($scheduler);
    $fp = fopen($file_path, 'w+');
    if ($fp) {
      fwrite($fp, $scheduler_data);
      fclose($fp);
    }
    else {
      echo "Did not save correctly.";
    }
  }
  else {
    //echo "Documents have not been generated because file doesn't exist";
  }
}

determine_function_call();

<?php
/**
 * @package sensei-quiz-chances
 * @version 1.0
 */
/*
Plugin Name: Mikes sensei-quiz-chances
Plugin URI: http://michaelwing.co.uk/
Description: mike-sensei-quiz-chances
Author: Mike
Version: 1.0
Author URI: http://michaelwing.co.uk/
*/

//error_reporting(E_ALL & ~E_DEPRECATED);
//error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

define('SENSEI_QUIZ_CHANCES_PATH', plugin_dir_path( __FILE__ ));
define('SENSEI_QUIZ_CHANCES_URL', plugins_url().'/'.basename(dirname(__FILE__)).'/');

class Sensei_Quiz_Chances {
  
  const FIELD_PREFIX  = '_sensei_quiz_chances';
  
  public function __construct() {
    add_action ( 'add_meta_boxes_lesson', array ($this, 'add_quiz_meta_box') );
    add_action ( 'save_post', array ($this, 'save_post_meta') );
    add_action ( 'sensei_before_main_content', array ($this, 'sensei_before_main_content') );
    add_action ( 'sensei_user_quiz_submitted', array ($this, 'update_chances_taken') );
    
    add_action( 'wp_loaded', function() {
      add_action( 'show_user_profile', array ($this, 'add_chances_taken_fields') );
      add_action( 'edit_user_profile', array ($this, 'add_chances_taken_fields') );
    });
    //add_action( 'edit_user_profile_update', array ($this, 'add_chances_taken_fields') );
    //add_action( 'personal_options_update', array ($this, 'add_chances_taken_fields') );
    
    add_action( 'admin_enqueue_scripts',  array ($this, 'my_enqueue') );
    add_action( 'wp_ajax_reset_quiz_chances_taken', array ($this, 'reset_quiz_chances_taken') );
    
    add_action( 'sensei_lesson_single_title', array ($this, 'sensei_course_start') );
    add_filter( 'sensei_breadcrumb_output',  array ($this, 'sensei_breadcrumb_output'), 10);
    add_filter( 'sensei_user_quiz_status',  array ($this, 'sensei_user_quiz_status'), 10);
    add_filter( 'sensei_reset_quiz_text',  array ($this, 'sensei_reset_quiz_text'), 10);
    add_action( 'sensei_quiz_action_buttons', array ($this, 'sensei_quiz_action_buttons') );
    
    add_action( 'wp_loaded', function() {
      global $woothemes_sensei;
      remove_action( 'sensei_quiz_action_buttons', array( $woothemes_sensei->frontend, 'sensei_quiz_action_buttons' ) );
    });

    add_filter( 'wpmem_ul_profile_rows',  array ($this, 'wpmem_ul_profile_rows'), 10);
  }
  
  public function add_quiz_meta_box() {
    add_meta_box ( "quiz-allowed-chances-metabox", __ ( 'Quiz chances allowed', 'Sensei_Quiz_Chances' ), array (
        $this,
        'metabox' 
    ), 'lesson', "normal", "low" );
  }
  
  public function metabox($post) {
    $post_id  = $post->ID;
    $quiz_chances_allowed = get_post_meta ( $post_id, "_quiz_chances_allowed",true);
    ?>
<div class="inside">
  <div class="sensei-options-panel">
    <div class="options_group" id="quiz_chances_allowed_options_group">
      <p class="form-field quiz_chances_allowed ">
        <label for="quiz_chances_allowed">
          <span class="label"><?php _e('Chances allowed','Sensei_Quiz_Chances');?></span><input id="quiz_chances_allowed" name="quiz_chances_allowed" class="large" type="number" value="<?php echo $quiz_chances_allowed;?>" />
           <!--<span class="description"><?php _e('Allowed chances','Sensei_Quiz_Chances');?></span>-->
        </label>
      </p>
    </div>
  </div>
</div>
    <?php
  }

  function save_post_meta($post_id) {
    if (defined ( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE)
      return $post_id;
      
    if (!current_user_can('edit_posts'))
      return;

    if (!isset($id))
      $id = (int) $post_id;
    
    if ( isset( $_POST ['quiz_chances_allowed'] ) && $_POST ['quiz_chances_allowed'] != '' ) {
      $quiz_chances_allowed = (int) $_POST['quiz_chances_allowed'];
      update_post_meta ( $post_id, "_quiz_chances_allowed", $quiz_chances_allowed );
    } else {
      delete_post_meta ( $post_id, "_quiz_chances_allowed" );
    }
  }

  function sensei_before_main_content() {
    if (get_post_type() !== 'quiz') return;
    $quiz_id = get_the_ID();
    $user_id = get_current_user_id();
    $quiz_chances_allowed = (int) get_post_meta ( $quiz_id, "_quiz_chances_allowed", true );
    //$quiz_chances_taken   = (int) get_user_meta ( $user_id, "_quiz_chances_taken_{$quiz_id}", true );
    $quiz_chances_taken   = get_user_meta ( $user_id, "_quiz_chances_taken", true );
    //var_dump($quiz_chances_allowed, $quiz_chances_taken);
    if ( ! ($quiz_chances_allowed) ||  ! isset($quiz_chances_taken[$quiz_id]) || $quiz_chances_allowed > $quiz_chances_taken[$quiz_id]) return;
    
    $lesson_id = intval( get_post_meta( $quiz_id, '_quiz_lesson', true ) );
    $user_lesson_status = WooThemes_Sensei_Utils::user_lesson_status( $lesson_id, $user_id );
    // If Quiz is passed, show it even if the allowed no of chances have been exceeded
    if ($user_lesson_status->comment_approved == 'passed') return;
    // No of allowed chances has been exceeded
    /*
    if (wp_get_referer() && false === strpos(wp_get_referer(), '/quiz/')) {
      wp_safe_redirect(wp_get_referer());
    } else {
      wp_safe_redirect(get_home_url());
    }
    */
    //$user_id = get_current_user_id();
    $userinfo = get_userdata($user_id);
    $username = $userinfo->user_login;
    $quizname = get_the_title($quiz_id);
    $profileurl = get_site_url().'/profile/?uid='.$user_id;
    $to = 'michael.wing@resolutiontelevision.com';
    $subject = 'User failed quiz please reset their chances';
    $body = 'The user: '.$username.' has failed the quiz: '.$quizname.' Please reset their quiz chances for them to continue on here: '.$profileurl;
 
    wp_mail( $to, $subject, $body);

    wp_safe_redirect(get_home_url().'/locked-out');
    exit;
  }
  
  function update_chances_taken($user_id) {
    $quiz_id = get_the_ID();
    $user_id = get_current_user_id();
    //$quiz_chances_allowed = get_post_meta ( $quiz_id, "_quiz_chances_allowed",true);
    //$quiz_chances_taken = get_user_meta ( $user_id, "_quiz_chances_taken_{$quiz_id}", true ); 
    //update_user_meta( $user_id, "_quiz_chances_taken_{$quiz_id}", ($quiz_chances_taken+1) );
    $quiz_chances_taken = get_user_meta ( $user_id, "_quiz_chances_taken", true );
    $quiz_chances_taken[$quiz_id] = isset($quiz_chances_taken[$quiz_id]) ? $quiz_chances_taken[$quiz_id]+1 : 1;
    update_user_meta( $user_id, "_quiz_chances_taken", $quiz_chances_taken );
  }
  
  function add_chances_taken_fields($user, $return=false) {
    global $wpdb;

    $user_id  = is_object($user) ? $user->ID : $user;
    $quiz_chances_taken  = get_user_meta ( $user_id, "_quiz_chances_taken", true );
    //if ( ! is_array($quiz_chances_taken) || count($quiz_chances_taken) == 0) return;
    if ( ! is_array($quiz_chances_taken)) {
      $quiz_chances_taken = array();
    }
    if (count($quiz_chances_taken) == 0) {
      $quizes = array();
      if ($return) {
        return '';
      } else {
        echo '';
      }
    } else {
      $data = $wpdb->get_results( $wpdb->prepare("SELECT id, post_title FROM {$wpdb->posts} WHERE id IN (".implode(", ", array_fill(0, count($quiz_chances_taken), '%d')).")", array_keys($quiz_chances_taken) ) );
      //var_dump($data);
      foreach ($data as $obj) {
        $quizes[$obj->id] = $obj;
      }
    }
    //var_dump($quizes);
    $html   = '';
    $h_tag  = $return ? 'h5' : 'h3';
    $html   .= "<{$h_tag}>Induction Questions chances taken</{$h_tag}>";
    $html .= '
      <table class="form-table" id="quiz_chances_taken_table"><tbody>';
      foreach ($quiz_chances_taken as $quiz_id => $quiz_chance_taken) {
        $html .= '<tr class="user-chances_taken-wrap">
          <th><label for="chances_taken">'. esc_attr($quizes[$quiz_id]->post_title) .'</label></th>
          <td>
            <input type="hidden" value="'. esc_attr($quiz_id) .'" class="quiz_id" />
            <span>'. esc_attr($quiz_chance_taken) .'</span> &nbsp;&nbsp; 
            <input type="button" class="button button-primary quiz_chances_taken_reset_btn" style="display:inline;" value="Reset" />
          </td>
        </tr>';
      }
    $html .= '</tbody></table>';
    if ($return) {
      return $html;
    } else {
      echo $html;
    }
  }
  
  function my_enqueue($hook, $user_id=null) {
    if( 'profile.php' != $hook ) return;  // Only applies to profile page
    if (empty($user_id)) {
      global $user_id;
    }
    wp_enqueue_script( 'ajax-script', plugins_url( '/js/quiz_chances.js', __FILE__ ), array('jquery'));
    // in javascript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
    wp_localize_script( 'ajax-script', 'ajax_object',
    array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'user_id' => $user_id ) );
  }
  
  function reset_quiz_chances_taken() {
    //sleep(3);
    //var_dump($_POST);
    if ( ! isset($_POST['post_ids'])) {
      wp_die(0); // this is required to terminate immediately and return a proper response
    }
    $user_id  = (int) $_POST['user_id'];
    $quiz_chances_taken = get_user_meta ( $user_id, "_quiz_chances_taken", true );
    foreach ($_POST['post_ids'] as $post_id) {
      //$post_id  = sanitize_text_field($post_id);
      $post_id  = (int) $post_id;
      $quiz_chances_taken[$post_id] = 0;
    }
    update_user_meta( $user_id, "_quiz_chances_taken", $quiz_chances_taken );
    wp_die(0); // this is required to terminate immediately and return a proper response
  }
  
  function sensei_course_start() {
    //ini_set("memory_limit", "256M");
    global $post, $current_user;
    $course_id  = get_post_meta ( $post->ID, "_lesson_course", true );
    $course = get_post($course_id);
    
		// Check if the user is taking the course
		$is_user_taking_course = WooThemes_Sensei_Utils::user_started_course( $course->ID, $current_user->ID );
		// Handle user starting the course
		if ( ! $is_user_taking_course ) {
			// Start the course
			// action 'sensei_user_course_start' is done within user_start_course()
			$activity_logged = WooThemes_Sensei_Utils::user_start_course( $current_user->ID, $course->ID );
		} // End If Statement
  }
  
  function sensei_breadcrumb_output($html) {
    global $post;
    $id = $post->ID;
    $lesson_id = intval( get_post_meta( $id, '_quiz_lesson', true ) );
    if( ! $lesson_id ) {
      return;
    }
    
    $course_id = intval( get_post_meta( $lesson_id, '_lesson_course', true ) );
    if( ! $course_id ) {
      return $html;
    }
    $html .= ' &nbsp;&nbsp;|&nbsp;&nbsp; Course overview: <a href="' . esc_url( get_permalink( $course_id ) ) . '" title="' . esc_attr( apply_filters( 'sensei_back_to_course_text', __( 'Back to the course', 'woothemes-sensei' ) ) ) . '">' . get_the_title( $course_id ) . '</a>';
    return $html;
    
    /*
    $nav_id_array = sensei_get_prev_next_lessons( $lesson_id );
    $previous_lesson_id = absint( $nav_id_array['prev_lesson'] );
    $next_lesson_id = absint( $nav_id_array['next_lesson'] );
    */
  }
  
  function sensei_user_quiz_status($args) {
    //var_dump($args);
    if ($args['status'] != 'passed') {
      return $args;
    }
    $args['message']  = $this->sensei_breadcrumb_output($args['message']);
    return $args;
  }
  
  function sensei_reset_quiz_text($html) {
    return __( 'Take Quiz Again', 'woothemes-sensei' );
  }
  
  function sensei_quiz_action_buttons() {
		global $post, $current_user, $woothemes_sensei;
		$lesson_id = (int) get_post_meta( $post->ID, '_quiz_lesson', true );
		$lesson_course_id = (int) get_post_meta( $lesson_id, '_lesson_course', true );
		$lesson_prerequisite = (int) get_post_meta( $lesson_id, '_lesson_prerequisite', true );
		$show_actions = true;
    $user_lesson_status = WooThemes_Sensei_Utils::user_lesson_status( $lesson_id, $current_user->ID );
    $user_quiz_grade = get_comment_meta( $user_lesson_status->comment_ID, 'grade', true );
		if( intval( $lesson_prerequisite ) > 0 ) {
			// If the user hasn't completed the prereq then hide the current actions
			$show_actions = WooThemes_Sensei_Utils::user_completed_lesson( $lesson_prerequisite, $current_user->ID );
		}
		if ( $show_actions && is_user_logged_in() && WooThemes_Sensei_Utils::user_started_course( $lesson_course_id, $current_user->ID ) ) {

			// Get Reset Settings
			$reset_quiz_allowed = get_post_meta( $post->ID, '_enable_quiz_reset', true );
      // In Progress
      if ( '' == $user_quiz_grade) { 
      // Quiz in progress - then allow save or complete
      /*
        Removed save button as it is not needed.
        <input type="hidden" name="woothemes_sensei_save_quiz_nonce" id="woothemes_sensei_save_quiz_nonce" value="<?php echo esc_attr(  wp_create_nonce( 'woothemes_sensei_save_quiz_nonce' ) ); ?>" />
        <span><input type="submit" name="quiz_save" class="quiz-submit save" value="<?php echo apply_filters( 'sensei_save_quiz_text', __( 'Save Quiz', 'woothemes-sensei' ) ); ?>"/></span>
      */
      ?>
        <input type="hidden" name="woothemes_sensei_complete_quiz_nonce" id="woothemes_sensei_complete_quiz_nonce" value="<?php echo esc_attr(  wp_create_nonce( 'woothemes_sensei_complete_quiz_nonce' ) ); ?>" />
        <span><input type="submit" name="quiz_complete" class="quiz-submit complete" value="<?php echo apply_filters( 'sensei_complete_quiz_text', __( 'Complete Quiz', 'woothemes-sensei' ) ); ?>"/></span>
		  <?php } elseif ( isset( $reset_quiz_allowed ) && $reset_quiz_allowed && $user_lesson_status->comment_approved == 'failed' ) { 
      // Failed - then allow to take again
      ?>
        <input type="hidden" name="woothemes_sensei_reset_quiz_nonce" id="woothemes_sensei_reset_quiz_nonce" value="<?php echo esc_attr(  wp_create_nonce( 'woothemes_sensei_reset_quiz_nonce' ) ); ?>" />
        <span><input type="submit" name="quiz_reset" class="quiz-submit reset" value="<?php echo apply_filters( 'sensei_reset_quiz_text', __( 'Reset Quiz', 'woothemes-sensei' ) ); ?>"/></span>
		  <?php } elseif ($user_lesson_status->comment_approved == 'passed') { 
      // Passed - then forward to "Next Lesson"
        $nav_id_array = sensei_get_prev_next_lessons( $lesson_id );
        $previous_lesson_id = absint( $nav_id_array['prev_lesson'] );
        $next_lesson_id = absint( $nav_id_array['next_lesson'] );
      
        if ($next_lesson_id != 0) {
        // If next lesson exists in course
      ?>
        <span><input type="submit" name="quiz_next" class="quiz-submit reset" value="Next Lesson" onclick="window.location = '<?php echo esc_url( get_permalink( $next_lesson_id ) ); ?>'; return false;"/></span>
        <?php } else {
        // If no next lesson in course, show course
        $course_id = intval( get_post_meta( $lesson_id, '_lesson_course', true ) );
        ?>
        <span><input type="submit" name="quiz_next" class="quiz-submit reset" value="Complete Induction" onclick="window.location = '<?php echo esc_url( get_permalink( $course_id ) ); ?>'; return false;"/></span>
        <?php
        }
      }
    }
  }

  function wpmem_ul_profile_rows($rows) {
    $field  = 'chances_taken';
    $rows[$field]['div_before']  = '<div class="field-name" style="width: 50%;">';
    $rows[$field]['span_before'] = '';
    $rows[$field]['label']       = '';
    $rows[$field]['field']       = '<br /><br />';
    $rows[$field]['span_after']  = '';
    $rows[$field]['div_after']   = '</div>';

    $user_id  = ( isset( $_GET['uid'] ) ) ? (int) $_GET['uid'] : '';
    $rows[$field]['field']  .= $this->add_chances_taken_fields($user_id, true);
    $this->my_enqueue('profile.php', $user_id);

    return $rows;
  }
  
}

if (class_exists('Sensei_Quiz_Chances')) {
  $Sensei_Quiz_Chances  = new Sensei_Quiz_Chances;
}

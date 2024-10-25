<?php
/**
 * Plugin Name: LearnDash Test Data Generator
 * Description: Generates test data for LearnDash, including users, courses, progress data, and transactions.
 * Version: 2.4
 * Author: Jack Kitterhing
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LearnDash_Test_Data_Generator {
    private $log_messages = array();
    private $payment_gateways = array(
        'stripe',
        'paypal',
        'razorpay'
    );

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'LearnDash Test Data Generator',
            'LD Test Data',
            'manage_options',
            'learndash-test-data-generator',
            array($this, 'admin_page'),
            'dashicons-database-add',
            100
        );
    }

    public function register_settings() {
        register_setting('learndash_test_data_generator', 'ldtdg_num_users');
        register_setting('learndash_test_data_generator', 'ldtdg_num_courses');
        register_setting('learndash_test_data_generator', 'ldtdg_completion_percentage');
        register_setting('learndash_test_data_generator', 'ldtdg_generate_transactions');
        register_setting('learndash_test_data_generator', 'ldtdg_debug_mode');
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>LearnDash Test Data Generator</h1>
            <form method="post" action="options.php">
                <?php settings_fields('learndash_test_data_generator'); ?>
                <?php do_settings_sections('learndash_test_data_generator'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Number of Users</th>
                        <td><input type="number" name="ldtdg_num_users" value="<?php echo esc_attr(get_option('ldtdg_num_users')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Number of Courses</th>
                        <td><input type="number" name="ldtdg_num_courses" value="<?php echo esc_attr(get_option('ldtdg_num_courses')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Completion Percentage</th>
                        <td><input type="number" name="ldtdg_completion_percentage" value="<?php echo esc_attr(get_option('ldtdg_completion_percentage')); ?>" min="0" max="100" />%</td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Generate Transactions</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ldtdg_generate_transactions" value="1" <?php checked(get_option('ldtdg_generate_transactions'), 1); ?> />
                                Also generate test transactions for enrollments
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Debug Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ldtdg_debug_mode" value="1" <?php checked(get_option('ldtdg_debug_mode'), 1); ?> />
                                Show detailed debug information during generation
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
            <form method="post" action="">
                <?php wp_nonce_field('generate_test_data', 'ldtdg_nonce'); ?>
                <input type="submit" name="generate_test_data" class="button button-primary" value="Generate Test Data" />
            </form>
        </div>
        <?php

        if (isset($_POST['generate_test_data']) && check_admin_referer('generate_test_data', 'ldtdg_nonce')) {
            $this->generate_test_data();
            $this->display_log();
        }
    }

    private function log($message, $debug = false) {
        if ($debug && !get_option('ldtdg_debug_mode')) {
            return;
        }
        $this->log_messages[] = $debug ? '🔍 ' . $message : $message;
    }

    private function display_log() {
        echo '<div class="notice notice-info"><p>Generation Log:</p><ul>';
        foreach ($this->log_messages as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }

    public function generate_test_data() {
        $num_users = intval(get_option('ldtdg_num_users', 10));
        $num_courses = intval(get_option('ldtdg_num_courses', 5));
        $completion_percentage = intval(get_option('ldtdg_completion_percentage', 50));
        $generate_transactions = get_option('ldtdg_generate_transactions') == '1';
        $debug_mode = get_option('ldtdg_debug_mode') == '1';

        $this->log("Starting data generation with: $num_users users and $num_courses courses");
        if ($generate_transactions) {
            $this->log("Transaction generation is enabled - will create payment records");
        }
        if ($debug_mode) {
            $this->log("Debug mode is enabled - showing detailed information", true);
        }

        // Generate users
        $users = $this->generate_users($num_users);

        // Generate courses
        $courses = $this->generate_courses($num_courses);

        // Enroll users in courses and generate progress data
        $this->generate_enrollment_and_progress($users, $courses, $completion_percentage, $generate_transactions);

        echo '<div class="notice notice-success"><p>Test data generated successfully!</p></div>';
    }

    private function generate_users($num_users) {
        $users = array();
        $existing_user_count = count_users();
        $start_index = $existing_user_count['total_users'] + 1;

        for ($i = $start_index; $i < $start_index + $num_users; $i++) {
            $unique_string = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $username = 'testuser' . $i . '_' . $unique_string;
            $email = $username . '@example.com';

            $user_id = wp_create_user($username, wp_generate_password(), $email);
            
            if (!is_wp_error($user_id)) {
                $users[] = get_user_by('ID', $user_id);
                $this->log("Created user: $username (ID: $user_id)");
            } else {
                $this->log("Failed to create user: $username. Error: " . $user_id->get_error_message());
            }
        }
        return $users;
    }

    private function generate_courses($num_courses) {
        $courses = array();
        for ($i = 0; $i < $num_courses; $i++) {
            $course_id = wp_insert_post(array(
                'post_title' => 'Test Course ' . ($i + 1) . ' ' . substr(md5(uniqid(mt_rand(), true)), 0, 8),
                'post_type' => 'sfwd-courses',
                'post_status' => 'publish',
            ));

            if ($course_id) {
                // Set a random price between $10 and $100
                $price = rand(10, 100);
                
                // Set price type (70% chance of one-time payment, 30% chance of subscription)
                $price_type = (rand(1, 100) <= 70) ? LEARNDASH_PRICE_TYPE_PAYNOW : LEARNDASH_PRICE_TYPE_SUBSCRIBE;
                
                if ($price <= 0) {
                    $this->log("ERROR: Generated invalid price of $price for course $course_id");
                    continue;
                }

                $this->log("Setting up course $course_id with price type: $price_type and price: $price", true);

                // Course settings array
                $course_settings = array(
                    'course_price_type' => $price_type,
                    'course_price' => $price,
                    'course_access_list' => array(),
                    'course_points_enabled' => '',
                    'course_points' => 0,
                    'course_prerequisite_enabled' => '',
                    'course_prerequisite' => array(),
                    'course_materials_enabled' => '',
                    'course_materials' => '',
                );

                // If subscription, add subscription settings
                if ($price_type === LEARNDASH_PRICE_TYPE_SUBSCRIBE) {
                    $course_settings['course_price_billing_p3'] = 30;
                    $course_settings['course_price_billing_t3'] = 'D';
                    $course_settings['course_no_of_cycles'] = rand(3, 12);
                }

                // Update course settings
                foreach ($course_settings as $key => $value) {
                    learndash_update_setting($course_id, $key, $value);
                }

                // Update post meta for course
                update_post_meta($course_id, '_sfwd-courses', $course_settings);

                // Verify settings were saved
                $saved_price = learndash_get_setting($course_id, 'course_price');
                $saved_type = learndash_get_setting($course_id, 'course_price_type');
                
                if ($saved_price != $price || $saved_type != $price_type) {
                    $this->log("WARNING: Course settings mismatch for course $course_id. Price: $price vs $saved_price, Type: $price_type vs $saved_type", true);
                }

                $courses[] = get_post($course_id);
                $this->log("Created course: Test Course " . ($i + 1) . " (ID: $course_id) with price: $" . $price . " and type: " . $price_type);
                $this->generate_course_content($course_id);
            }
        }
        return $courses;
    }

    private function generate_course_content($course_id) {
        $course_steps = array();
        
        // Generate lessons
        $num_lessons = rand(3, 8);
        for ($i = 0; $i < $num_lessons; $i++) {
            $lesson_id = wp_insert_post(array(
                'post_title' => 'Lesson ' . ($i + 1),
                'post_type' => 'sfwd-lessons',
                'post_status' => 'publish',
                'menu_order' => $i,
            ));

            $this->log("Created lesson: Lesson " . ($i + 1) . " (ID: $lesson_id) for course $course_id");
            
            // Associate lesson with course
            learndash_update_setting($lesson_id, 'course', $course_id);
            
            $lesson_steps = array();

            // Generate topics
            $num_topics = rand(2, 5);
            for ($j = 0; $j < $num_topics; $j++) {
                $topic_id = wp_insert_post(array(
                    'post_title' => 'Topic ' . ($j + 1),
                    'post_type' => 'sfwd-topic',
                    'post_status' => 'publish',
                    'menu_order' => $j,
                ));
                $this->log("Created topic: Topic " . ($j + 1) . " (ID: $topic_id) for lesson $lesson_id");
                
                // Associate topic with course and lesson
                learndash_update_setting($topic_id, 'course', $course_id);
                learndash_update_setting($topic_id, 'lesson', $lesson_id);

                $lesson_steps['sfwd-topic'][$topic_id] = array();
            }

            // Generate quiz for lesson
            $quiz_id = $this->create_quiz('Quiz for Lesson ' . ($i + 1), $course_id, $lesson_id);

            $this->log("Created quiz: Quiz for Lesson " . ($i + 1) . " (ID: $quiz_id) for lesson $lesson_id");

            $lesson_steps['sfwd-quiz'][$quiz_id] = array();

            $course_steps['sfwd-lessons'][$lesson_id] = $lesson_steps;
        }

        // Generate a global quiz
        $global_quiz_id = $this->create_quiz('Global Course Quiz', $course_id);
        $this->log("Created global quiz: Global Course Quiz (ID: $global_quiz_id) for course $course_id");

        $course_steps['sfwd-quiz'][$global_quiz_id] = array();

        // Set course steps
        $ld_course_steps_object = new LDLMS_Course_Steps($course_id);
        $ld_course_steps_object->set_steps($course_steps);

        $this->log("Course steps set for course $course_id");
    }

    private function create_quiz($title, $course_id, $lesson_id = 0) {
        $quiz_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => 'sfwd-quiz',
            'post_status' => 'publish',
        ));

        // Associate quiz with course and lesson
        learndash_update_setting($quiz_id, 'course', $course_id);
        if ($lesson_id) {
            learndash_update_setting($quiz_id, 'lesson', $lesson_id);
        }

        // Create ProQuiz quiz
        $pro_quiz_id = $this->create_pro_quiz($title);

        // Associate WordPress quiz with ProQuiz quiz
        learndash_update_setting($quiz_id, 'quiz_pro', $pro_quiz_id);

        // Set other quiz settings
        $quiz_meta = array(
            'course' => $course_id,
            'lesson' => $lesson_id,
            'quiz_pro' => $pro_quiz_id,
            'quiz_mode' => 'single',
            'time_limit' => '',
            'certificate' => '',
            'threshold' => '80',
        );
        foreach ($quiz_meta as $key => $value) {
            learndash_update_setting($quiz_id, $key, $value);
        }

        return $quiz_id;
    }

    private function create_pro_quiz($title) {
        $quiz_mapper = new WpProQuiz_Model_QuizMapper();
        $quiz = new WpProQuiz_Model_Quiz();
        $quiz->setName($title);
        $quiz->setText('Quiz Description');
        $quiz->setResultText('Quiz Result Text');
        $quiz->setTitleHidden(false);

        $quiz_mapper->save($quiz);

        return $quiz->getId();
    }

    private function generate_enrollment_and_progress($users, $courses, $completion_percentage, $generate_transactions = false) {
        foreach ($users as $user) {
            $course_progress = array();
            $quizzes = array();

            foreach ($courses as $course) {
                // Enroll user in course
                $enrolled = ld_update_course_access($user->ID, $course->ID);
                if ($enrolled) {
                    $this->log("Enrolled user {$user->ID} in course {$course->ID}");

                    // Generate transaction if enabled
                    if ($generate_transactions) {
                        $this->generate_transaction($user, $course);
                    }
                } else {
                    $this->log("Failed to enroll user {$user->ID} in course {$course->ID}");
                    continue;
                }

                // Get course steps
                $course_steps = learndash_get_course_steps($course->ID);
                $total_steps = count($course_steps);

                $this->log("Course {$course->ID} has $total_steps steps");

                if ($total_steps === 0) {
                    $this->log("Skipping progress for course {$course->ID} as it has no steps");
                    continue;
                }

                // Determine if the user will complete the course
                $will_complete = (rand(1, 100) <= $completion_percentage);

                // Calculate the number of steps to complete
                $steps_to_complete = $will_complete ? $total_steps : rand(0, $total_steps - 1);

                $this->log("User {$user->ID} will complete $steps_to_complete out of $total_steps steps for course {$course->ID}");

                // Initialize course progress
                $course_progress[$course->ID] = array(
                    'lessons' => array(),
                    'topics' => array(),
                );

                // Mark steps as complete
                $completed_steps = 0;
                foreach ($course_steps as $step_id) {
                    if ($completed_steps < $steps_to_complete) {
                        $step_type = get_post_type($step_id);
                        $completed_steps++;

                        switch ($step_type) {
                            case 'sfwd-lessons':
                                $course_progress[$course->ID]['lessons'][$step_id] = 1;
                                break;
                            case 'sfwd-topic':
                                $lesson_id = learndash_get_setting($step_id, 'lesson');
                                $course_progress[$course->ID]['topics'][$lesson_id][$step_id] = 1;
                                break;
                            case 'sfwd-quiz':
                                $quiz_meta = array(
                                    'time' => time(),
                                    'score' => 100,
                                    'count' => 1,
                                    'pass' => 1,
                                    'points' => 10,
                                    'total_points' => 10,
                                    'percentage' => 100,
                                );
                                $quizzes[] = array(
                                    'quiz' => $step_id,
                                    'score' => 100,
                                    'count' => 1,
                                    'pass' => 1,
                                    'time' => time(),
                                    'pro_quizid' => learndash_get_setting($step_id, 'quiz_pro'),
                                    'course' => $course->ID,
                                    'lesson' => learndash_get_setting($step_id, 'lesson'),
                                    'topic' => 0,
                                    'points' => 10,
                                    'total_points' => 10,
                                    'percentage' => 100,
                                    'timespent' => 60,
                                    'has_graded' => false,
                                    'statistic_ref_id' => 0,
                                );
                                update_user_meta($user->ID, 'ld_quiz_' . $step_id, $quiz_meta);
                                break;
                        }

                        $this->log("Marked step $step_id as complete for user {$user->ID} in course {$course->ID}");
                    } else {
                        break;
                    }
                }

                // Update course progress
                $progress = ($total_steps > 0) ? ($completed_steps / $total_steps) * 100 : 0;
                update_user_meta($user->ID, 'course_' . $course->ID . '_progress', $progress);
                $this->log("Updated progress for user {$user->ID} in course {$course->ID}: $progress%");

                // If all steps are completed, mark the course as complete
                if ($completed_steps === $total_steps) {
                    $completion_time = time();
                    update_user_meta($user->ID, 'course_completed_' . $course->ID, $completion_time);
                    $this->log("Marked course {$course->ID} as complete for user {$user->ID}");
                }
            }

            // Update _sfwd-course_progress user meta
            update_user_meta($user->ID, '_sfwd-course_progress', $course_progress);

            // Update _sfwd-quizzes user meta
            update_user_meta($user->ID, '_sfwd-quizzes', $quizzes);
        }
    }

    private function generate_transaction($user, $course) {
        if (empty($user) || empty($course)) {
            return;
        }
    
        // Get course pricing
        $course_price = learndash_get_setting($course->ID, 'course_price');
        $price_type = learndash_get_setting($course->ID, 'course_price_type');
        
        if (empty($course_price) || $course_price <= 0) {
            $this->log("ERROR: Invalid course price $course_price for course {$course->ID}");
            return;
        }
    
        // Pick random gateway
        $gateway = $this->payment_gateways[array_rand($this->payment_gateways)];
    
        // Generate a random transaction ID
        $transaction_id = wp_insert_post(array(
            'post_type' => 'sfwd-transactions',
            'post_title' => 'Order #' . wp_generate_password(8, false),
            'post_status' => 'publish',
            'post_author' => $user->ID
        ));
    
        if (!$transaction_id) {
            $this->log("ERROR: Failed to create transaction for user {$user->ID} and course {$course->ID}");
            return;
        }
    
        // Add transaction date in the past (random date within last 90 days)
        $days_ago = rand(1, 90);
        $past_time = strtotime("-$days_ago days");
        
        wp_update_post(array(
            'ID' => $transaction_id,
            'post_date' => date('Y-m-d H:i:s', $past_time),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $past_time),
        ));
    
        // Prepare user data
        $user_data = array(
            'display_name' => $user->display_name,
            'user_email' => $user->user_email
        );
    
        // Prepare pricing info structure
        $pricing_info = array(
            'currency' => learndash_get_currency_code(),
            'price' => floatval($course_price),
            'discount' => 0,
            'discounted_price' => floatval($course_price)
        );
    
        // Add subscription fields if needed
        if ($price_type === LEARNDASH_PRICE_TYPE_SUBSCRIBE) {
            $pricing_info['recurring_times'] = learndash_get_setting($course->ID, 'course_no_of_cycles', 0);
            $pricing_info['duration_value'] = learndash_get_setting($course->ID, 'course_price_billing_p3', 0);
            $pricing_info['duration_length'] = $this->map_duration_length(
                learndash_get_setting($course->ID, 'course_price_billing_t3', '')
            );
            $trial_price = learndash_get_setting($course->ID, 'course_trial_price', 0);
            
            if (!empty($trial_price)) {
                $pricing_info['trial_price'] = floatval($trial_price);
                $pricing_info['trial_duration_value'] = learndash_get_setting($course->ID, 'course_trial_duration_p1', 0);
                $pricing_info['trial_duration_length'] = $this->map_duration_length(
                    learndash_get_setting($course->ID, 'course_trial_duration_t1', '')
                );
            }
        }
    
        // Required meta fields that match the Transaction model
        $transaction_meta = array(
            // Core identification fields
            'user_id' => $user->ID,
            'course_id' => $course->ID,
            'is_parent' => 1,
            
            // Gateway info
            'gateway_name' => $gateway,
            'ld_payment_processor' => $gateway,
            '_ld_payment_gateway' => $gateway,
            '_ld_payment_method' => ucfirst($gateway),
            
            // Price info
            'price_type' => $price_type,
            '_ld_payment_total' => $course_price,
            '_ld_currency' => learndash_get_currency_code(),
            'pricing_info' => $pricing_info,
            
            // Transaction details
            'gateway_transaction_id' => 'txn_' . wp_generate_password(16, false),
            'purchase_type' => $price_type,
            'purchase_price' => $course_price,
            'purchase_date' => $past_time,
            'transaction_type' => 'purchase',
            
            // User info
            'user' => $user_data,
            
            // Product info
            'post' => array(
                'post_title' => $course->post_title,
                'post_type' => $course->post_type,
            ),
            
            // Additional fields
            'has_trial' => isset($pricing_info['trial_price']),
            'has_subscription' => $price_type === LEARNDASH_PRICE_TYPE_SUBSCRIBE,
            'gateway_subscription_id' => $price_type === LEARNDASH_PRICE_TYPE_SUBSCRIBE 
                ? 'sub_' . wp_generate_password(14, false)
                : '',
        );
    
        // Update all transaction meta
        foreach ($transaction_meta as $meta_key => $meta_value) {
            update_post_meta($transaction_id, $meta_key, $meta_value);
        }
    
        $this->log("Generated transaction (ID: $transaction_id) for user {$user->ID} and course {$course->ID} with price: $course_price and type: $price_type");
        
        // Verify transaction meta was saved
        $saved_price = get_post_meta($transaction_id, '_ld_payment_total', true);
        $saved_type = get_post_meta($transaction_id, 'price_type', true);
        
        if (empty($saved_price) || $saved_price != $course_price) {
            $this->log("ERROR: Transaction price not saved correctly. Expected: $course_price, Got: $saved_price");
        }
        if (empty($saved_type) || $saved_type != $price_type) {
            $this->log("ERROR: Transaction type not saved correctly. Expected: $price_type, Got: $saved_type");
        }
    }

    private function map_duration_length($ld_duration) {
        $map = array(
            'D' => 'D', // Day
            'W' => 'W', // Week
            'M' => 'M', // Month
            'Y' => 'Y'  // Year
        );
    
        return isset($map[$ld_duration]) ? $map[$ld_duration] : '';
    }
}

new LearnDash_Test_Data_Generator();
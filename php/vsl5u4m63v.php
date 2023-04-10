<?php

/**
 * @package QuickAI - OpenAI Content & Image Generator
 * @author Bylancer
 * @version 2.3
 * @Updated Date: 09/Apr/2023
 * @Copyright 2015-23 Bylancer
 */
define("ROOTPATH", dirname(__DIR__));
define("APPPATH", ROOTPATH . "/php/");

require_once ROOTPATH . '/includes/autoload.php';
require_once ROOTPATH . '/includes/lang/lang_' . $config['lang'] . '.php';

sec_session_start();

if (isset($_GET['action'])) {
    if ($_GET['action'] == "submitBlogComment") {
        submitBlogComment();
    }
    if ($_GET['action'] == "generate_content") {
        generate_content();
    }
    if ($_GET['action'] == "generate_image") {
        generate_image();
    }
    if ($_GET['action'] == "save_document") {
        save_document();
    }
    if ($_GET['action'] == "delete_document") {
        delete_document();
    }
    if ($_GET['action'] == "delete_image") {
        delete_image();
    }

    // AI chat
    if ($_GET['action'] == "send_ai_message") {
        send_ai_message();
    }
    if ($_GET['action'] == "delete_ai_chats") {
        delete_ai_chats();
    }
    if ($_GET['action'] == "export_ai_chats") {
        export_ai_chats();
    }

    // speech to text
    if ($_GET['action'] == "speech_to_text") {
        speech_to_text();
    }

    // ai code
    if ($_GET['action'] == "ai_code") {
        ai_code();
    }
    die(0);
}

if (isset($_POST['action'])) {
    if ($_POST['action'] == "ajaxlogin") {
        ajaxlogin();
    }
    if ($_POST['action'] == "email_verify") {
        email_verify();
    }
    die(0);
}

function ajaxlogin()
{
    global $config, $lang, $link;
    $loggedin = userlogin($_POST['username'], $_POST['password']);
    $result['success'] = false;
    $result['message'] = __("Error: Please try again.");
    if (!is_array($loggedin)) {
        $result['message'] = __("Username or Password not found");
    } elseif ($loggedin['status'] == 2) {
        $result['message'] = __("This account has been banned");
    } else {
        create_user_session($loggedin['id'],$loggedin['username'],$loggedin['password'],$loggedin['user_type']);
        update_lastactive();

        $redirect_url = get_option('after_login_link');
        if(empty($redirect_url)){
            $redirect_url = $link['DASHBOARD'];
        }

        $result['success'] = true;
        $result['message'] = $redirect_url;
    }
    die(json_encode($result));
}

function email_verify()
{
    global $config, $lang;

    if (checkloggedin()) {
        /*SEND CONFIRMATION EMAIL*/
        email_template("signup_confirm", $_SESSION['user']['id']);

        $respond = __('Sent');
        echo '<a class="button gray" href="javascript:void(0);">' . $respond . '</a>';
        die();

    } else {
        exit;
    }
}

function submitBlogComment()
{
    global $config, $lang;
    $comment_error = $name = $email = $user_id = $comment = null;
    $result = array();
    $is_admin = '0';
    $is_login = false;
    if (checkloggedin()) {
        $is_login = true;
    }
    $avatar = $config['site_url'] . 'storage/profile/default_user.png';
    if (!($is_login || isset($_SESSION['admin']['id']))) {
        if (empty($_POST['user_name']) || empty($_POST['user_email'])) {
            $comment_error = __('All fields are required.');
        } else {
            $name = validate_input($_POST['user_name']);
            $email = validate_input($_POST['user_email']);

            $regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
            if (!preg_match($regex, $email)) {
                $comment_error = __('This is not a valid email address.');
            }
        }
    } else if ($is_login && isset($_SESSION['admin']['id'])) {
        $commenting_as = 'admin';
        if (!empty($_POST['commenting-as'])) {
            if (in_array($_POST['commenting-as'], array('admin', 'user'))) {
                $commenting_as = $_POST['commenting-as'];
            }
        }
        if ($commenting_as == 'admin') {
            $is_admin = '1';
            $info = ORM::for_table($config['db']['pre'] . 'admins')->find_one($_SESSION['admin']['id']);
            $user_id = $_SESSION['admin']['id'];
            $name = $info['name'];
            $email = $info['email'];
            if (!empty($info['image'])) {
                $avatar = $config['site_url'] . 'storage/profile/' . $info['image'];
            }
        } else {
            $user_id = $_SESSION['user']['id'];
            $user_data = get_user_data(null, $user_id);
            $name = $user_data['name'];
            $email = $user_data['email'];
            if (!empty($user_data['image'])) {
                $avatar = $config['site_url'] . 'storage/profile/' . $user_data['image'];
            }
        }
    } else if ($is_login) {
        $user_id = $_SESSION['user']['id'];
        $user_data = get_user_data(null, $user_id);
        $name = $user_data['name'];
        $email = $user_data['email'];
        if (!empty($user_data['image'])) {
            $avatar = $config['site_url'] . 'storage/profile/' . $user_data['image'];
        }
    } else if (isset($_SESSION['admin']['id'])) {
        $is_admin = '1';
        $info = ORM::for_table($config['db']['pre'] . 'admins')->find_one($_SESSION['admin']['id']);
        $user_id = $_SESSION['admin']['id'];
        $name = $info['name'];
        $email = $info['email'];
        if (!empty($info['image'])) {
            $avatar = $config['site_url'] . 'storage/profile/' . $info['image'];
        }
    } else {
        $comment_error = __('Please login to post a comment.');
    }

    if (empty($_POST['comment'])) {
        $comment_error = __('All fields are required.');
    } else {
        $comment = validate_input($_POST['comment']);
    }

    $duplicates = ORM::for_table($config['db']['pre'] . 'blog_comment')
        ->where('blog_id', $_POST['comment_post_ID'])
        ->where('name', $name)
        ->where('email', $email)
        ->where('comment', $comment)
        ->count();

    if ($duplicates > 0) {
        $comment_error = __('Duplicate Comment: This comment is already exists.');
    }

    if (!$comment_error) {
        if ($is_admin) {
            $approve = '1';
        } else {
            if ($config['blog_comment_approval'] == 1) {
                $approve = '0';
            } else if ($config['blog_comment_approval'] == 2) {
                if ($is_login) {
                    $approve = '1';
                } else {
                    $approve = '0';
                }
            } else {
                $approve = '1';
            }
        }

        $blog_cmnt = ORM::for_table($config['db']['pre'] . 'blog_comment')->create();
        $blog_cmnt->blog_id = $_POST['comment_post_ID'];
        $blog_cmnt->user_id = $user_id;
        $blog_cmnt->is_admin = $is_admin;
        $blog_cmnt->name = $name;
        $blog_cmnt->email = $email;
        $blog_cmnt->comment = $comment;
        $blog_cmnt->created_at = date('Y-m-d H:i:s');
        $blog_cmnt->active = $approve;
        $blog_cmnt->parent = $_POST['comment_parent'];
        $blog_cmnt->save();

        $id = $blog_cmnt->id();
        $date = date('d, M Y');
        $approve_txt = '';
        if ($approve == '0') {
            $approve_txt = '<em><small>' . __('Comment is posted, wait for the reviewer to approve.') . '</small></em>';
        }

        $html = '<li id="li-comment-' . $id . '"';
        if ($_POST['comment_parent'] != 0) {
            $html .= 'class="children-2"';
        }
        $html .= '>
                   <div class="comments-box" id="comment-' . $id . '">
                        <div class="comments-avatar">
                            <img src="' . $avatar . '" alt="' . $name . '">
                        </div>
                        <div class="comments-text">
                            <div class="avatar-name">
                                <h5>' . $name . '</h5>
                                <span>' . $date . '</span>
                            </div>
                            ' . $approve_txt . '
                            <p>' . nl2br(stripcslashes($comment)) . '</p>
                        </div>
                    </div>
                </li>';

        $result['success'] = true;
        $result['html'] = $html;
        $result['id'] = $id;
    } else {
        $result['success'] = false;
        $result['error'] = $comment_error;
    }
    die(json_encode($result));
}

function generate_content()
{
    $result = array();
    if (checkloggedin()) {
        global $config;

        if(!$config['non_active_allow']){
            $user_data = get_user_data(null,$_SESSION['user']['id']);
            if($user_data['status'] == 0){
                $result['success'] = false;
                $result['error'] = __('Verify your email address to use the AI.');
                die(json_encode($result));
            }
        }

        $_POST = validate_input($_POST);

        if (!empty($_POST['ai_template'])) {

            $prompt = '';
            $text = array();
            $max_tokens = (int)$_POST['max_results'];
            $max_results = (int)$_POST['no_of_results'];
            $temperature = (float)$_POST['quality'];

            $membership = get_user_membership_detail($_SESSION['user']['id']);
            $words_limit = $membership['settings']['ai_words_limit'];
            $plan_templates = $membership['settings']['ai_templates'];

            if (get_option('single_model_for_plans'))
                $model = get_option('open_ai_model', 'gpt-3.5-turbo');
            else
                $model = $membership['settings']['ai_model'];

            $start = date('Y-m-01');
            $end = date_create(date('Y-m-t'))->modify('+1 day')->format('Y-m-d');
            $total_words_used = ORM::for_table($config['db']['pre'] . 'word_used')
                ->where('user_id', $_SESSION['user']['id'])
                ->where_raw("(`date` BETWEEN '$start' AND '$end')")
                ->sum('words');

            $total_words_used = $total_words_used ?: 0;

            // check if user's membership have the template
            if (!in_array($_POST['ai_template'], $plan_templates)) {
                $result['success'] = false;
                $result['error'] = __('Upgrade your membership plan to use this template');
                die(json_encode($result));
            }

            // check user's word limit
            if ($words_limit != -1 && (($words_limit - $total_words_used) < $max_tokens)) {
                $result['success'] = false;
                $result['error'] = __('Words limit exceeded, Upgrade your membership plan.');
                die(json_encode($result));
            }

            switch ($_POST['ai_template']) {
                case 'blog-ideas':
                    if (!empty($_POST['description'])) {
                        $prompt = create_blog_idea_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'blog-intros':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_blog_intros_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'blog-titles':
                    if (!empty($_POST['description'])) {
                        $prompt = create_blog_titles_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'blog-section':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_blog_section_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'blog-conclusion':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_blog_conclusion_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'article-writer':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_article_writer_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'article-rewriter':
                    if (!empty($_POST['description']) && !empty($_POST['keywords'])) {
                        $prompt = create_article_rewriter_prompt($_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'article-outlines':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_article_outlines_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'talking-points':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_talking_points_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'paragraph-writer':
                    if (!empty($_POST['description']) && !empty($_POST['keywords'])) {
                        $prompt = create_paragraph_writer_prompt($_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'content-rephrase':
                    if (!empty($_POST['description']) && !empty($_POST['keywords'])) {
                        $prompt = create_content_rephrase_prompt($_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'facebook-ads':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_facebook_ads_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'facebook-ads-headlines':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_facebook_ads_headlines_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'google-ad-titles':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_google_ads_titles_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'google-ad-descriptions':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_google_ads_descriptions_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'linkedin-ad-headlines':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_linkedin_ads_headlines_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'linkedin-ad-descriptions':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_linkedin_ads_descriptions_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'app-and-sms-notifications':
                    if (!empty($_POST['description'])) {
                        $prompt = create_app_sms_notifications_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'text-extender':
                    if (!empty($_POST['description']) && !empty($_POST['keywords'])) {
                        $prompt = create_text_extender_prompt($_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'content-shorten':
                    if (!empty($_POST['description'])) {
                        $prompt = create_content_shorten_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'quora-answers':
                    if (!empty($_POST['title']) && !empty($_POST['description'])) {
                        $prompt = create_quora_answers_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'summarize-for-2nd-grader':
                    if (!empty($_POST['description'])) {
                        $prompt = create_summarize_2nd_grader_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'stories':
                    if (!empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_stories_prompt($_POST['audience'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'bullet-point-answers':
                    if (!empty($_POST['description'])) {
                        $prompt = create_bullet_point_answers_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'definition':
                    if (!empty($_POST['keyword'])) {
                        $prompt = create_definition_prompt($_POST['keyword'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'answers':
                    if (!empty($_POST['description'])) {
                        $prompt = create_answers_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'questions':
                    if (!empty($_POST['description'])) {
                        $prompt = create_questions_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'passive-active-voice':
                    if (!empty($_POST['description'])) {
                        $prompt = create_passive_active_voice_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'pros-cons':
                    if (!empty($_POST['description'])) {
                        $prompt = create_pros_cons_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'rewrite-with-keywords':
                    if (!empty($_POST['description']) && !empty($_POST['keywords'])) {
                        $prompt = create_rewrite_with_keywords_prompt($_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'emails':
                    if (!empty($_POST['recipient']) && !empty($_POST['recipient-position']) && !empty($_POST['description'])) {
                        $prompt = create_emails_prompt($_POST['recipient'], $_POST['recipient-position'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'emails-v2':
                    if (!empty($_POST['from']) && !empty($_POST['to']) && !empty($_POST['goal']) && !empty($_POST['description'])) {
                        $prompt = create_emails_v2_prompt($_POST['from'], $_POST['to'], $_POST['goal'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'email-subject-lines':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_email_subject_lines_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'startup-name-generator':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_startup_name_generator_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'company-bios':
                    if (!empty($_POST['description']) && !empty($_POST['title']) && !empty($_POST['platform'])) {
                        $prompt = create_company_bios_prompt($_POST['title'], $_POST['description'], $_POST['platform'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'company-mission':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_company_mission_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'company-vision':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_company_vision_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'product-name-generator':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_product_name_generator_prompt($_POST['description'], $_POST['title'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'product-descriptions':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_product_descriptions_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'amazon-product-titles':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_amazon_product_titles_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'amazon-product-descriptions':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_amazon_product_descriptions_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'amazon-product-features':
                    if (!empty($_POST['title']) && !empty($_POST['audience']) && !empty($_POST['description'])) {
                        $prompt = create_amazon_product_features_prompt($_POST['title'], $_POST['description'], $_POST['audience'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'social-post-personal':
                    if (!empty($_POST['description'])) {
                        $prompt = create_social_post_personal_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'social-post-business':
                    if (!empty($_POST['title']) && !empty($_POST['information']) && !empty($_POST['description'])) {
                        $prompt = create_social_post_business_prompt($_POST['title'], $_POST['information'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'instagram-captions':
                    if (!empty($_POST['description'])) {
                        $prompt = create_instagram_captions_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'instagram-hashtags':
                    if (!empty($_POST['description'])) {
                        $prompt = create_instagram_hashtags_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'twitter-tweets':
                    if (!empty($_POST['description'])) {
                        $prompt = create_twitter_tweets_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'youtube-titles':
                    if (!empty($_POST['description'])) {
                        $prompt = create_youtube_titles_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'youtube-descriptions':
                    if (!empty($_POST['description'])) {
                        $prompt = create_youtube_descriptions_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'youtube-outlines':
                    if (!empty($_POST['description'])) {
                        $prompt = create_youtube_outlines_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                case 'linkedin-posts':
                    if (!empty($_POST['description'])) {
                        $prompt = create_linkedin_posts_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'tiktok-video-scripts':
                    if (!empty($_POST['description'])) {
                        $prompt = create_tiktok_video_scripts_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'meta-tags-blog':
                    if (!empty($_POST['title']) && !empty($_POST['keywords']) && !empty($_POST['description'])) {
                        $prompt = create_meta_tags_blog_prompt($_POST['title'], $_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'meta-tags-homepage':
                    if (!empty($_POST['title']) && !empty($_POST['keywords']) && !empty($_POST['description'])) {
                        $prompt = create_meta_tags_homepage_prompt($_POST['title'], $_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'meta-tags-product':
                    if (!empty($_POST['title']) && !empty($_POST['keywords']) && !empty($_POST['description']) && !empty($_POST['company_name'])) {
                        $prompt = create_meta_tags_product_prompt($_POST['company_name'], $_POST['title'], $_POST['description'], $_POST['keywords'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'tone-changer':
                    if (!empty($_POST['description'])) {
                        $prompt = create_tone_changer_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'song-lyrics':
                    if (!empty($_POST['genre']) && !empty($_POST['title'])) {
                        $prompt = create_song_lyrics_prompt($_POST['title'], $_POST['genre'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'translate':
                    if (!empty($_POST['description'])) {
                        $prompt = create_translate_prompt($_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'faqs':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_faqs_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'faq-answers':
                    if (!empty($_POST['description']) && !empty($_POST['title']) && !empty($_POST['question'])) {
                        $prompt = create_faq_answers_prompt($_POST['title'], $_POST['description'], $_POST['question'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                case 'testimonials-reviews':
                    if (!empty($_POST['description']) && !empty($_POST['title'])) {
                        $prompt = create_testimonials_reviews_prompt($_POST['title'], $_POST['description'], $_POST['language'], $_POST['tone']);
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('All fields with (*) are required.');
                        die(json_encode($result));
                    }
                    break;
                default:
                    // check for custom template
                    $ai_template = ORM::for_table($config['db']['pre'] . 'ai_custom_templates')
                        ->where('active', '1')
                        ->where('slug', $_POST['ai_template'])
                        ->find_one();
                    if(!empty($ai_template)) {
                        $prompt = $ai_template['prompt'];

                        if ($_POST['language'] == 'en') {
                            $prompt = $ai_template['prompt'];
                        } else {
                            $languages = get_ai_languages();
                            $prompt = "Provide response in " . $languages[$_POST['language']] . ".\n\n ". $ai_template['prompt'];
                        }

                        if (!empty($ai_template['parameters'])) {
                            $parameters = json_decode($ai_template['parameters'], true);
                            foreach ($parameters as $key => $parameter) {
                                if(!empty($_POST['parameter'][$key])) {
                                    if(strpos($prompt, '{{' . $parameter['title'] . '}}') !== false) {
                                        $prompt = str_replace('{{' . $parameter['title'] . '}}', $_POST['parameter'][$key], $prompt);
                                    } else {
                                        $prompt .= "\n\n " . $parameter['title'] . ": " . $_POST['parameter'][$key];
                                    }
                                }
                            }
                        }

                        $prompt .= " \n\n Voice of tone of the response must be " . $_POST['tone'] . '.';
                    } else {
                        $result['success'] = false;
                        $result['error'] = __('Unexpected error, please try again.');
                        die(json_encode($result));
                    }

                    break;
            }

            // check bad words
            if($word = check_bad_words($prompt)){
                $result['success'] = false;
                $result['error'] = __('Your request contains a banned word:').' '.$word;
                die(json_encode($result));
            }

            require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/OpenAi.php';
            require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/Url.php';

            $open_ai = new Orhanerday\OpenAi\OpenAi(get_api_key());

            if ($model == 'gpt-3.5-turbo' || $model == 'gpt-4') {
                $complete = $open_ai->chat([
                    'model' => $model,
                    'messages' => [
                        [
                            "role" => "user",
                            "content" => $prompt
                        ],
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => $max_tokens,
                    'n' => $max_results,
                    'user' => $_SESSION['user']['id']
                ]);
            } else {
                $complete = $open_ai->completion([
                    'model' => $model,
                    'prompt' => $prompt,
                    'temperature' => $temperature,
                    'max_tokens' => $max_tokens,
                    'n' => $max_results,
                    'user' => $_SESSION['user']['id']
                ]);
            }

            $response = json_decode($complete, true);

            if (isset($response['choices'])) {
                if ($model == 'gpt-3.5-turbo' || $model == 'gpt-4') {
                    if (count($response['choices']) > 1) {
                        foreach ($response['choices'] as $value) {
                            $text[] = nl2br(trim($value['message']['content'])) . "<br><br><br><br>";
                        }
                    } else {
                        $text[] = nl2br(trim($response['choices'][0]['message']['content']));
                    }
                } else {
                    if (count($response['choices']) > 1) {
                        foreach ($response['choices'] as $value) {
                            $text[] = nl2br(trim($value['text'])) . "<br><br><br><br>";
                        }
                    } else {
                        $text[] = nl2br(trim($response['choices'][0]['text']));
                    }
                }

                $tokens = $response['usage']['completion_tokens'];

                $word_used = ORM::for_table($config['db']['pre'] . 'word_used')->create();
                $word_used->user_id = $_SESSION['user']['id'];
                $word_used->words = $tokens;
                $word_used->date = date('Y-m-d H:i:s');
                $word_used->save();

                $result['success'] = true;
                $result['text'] = implode("<br><br><hr><br><br>", $text);
                $result['old_used_words'] = $total_words_used;
                $result['current_used_words'] = $total_words_used + $tokens;
            } else {
                // error log default message
                if(!empty($response['error']['message']))
                    error_log('OpenAI: '. $response['error']['message']);

                $result['success'] = false;
                $result['error'] = get_api_error_message($open_ai->getCURLInfo()['http_code']);
                die(json_encode($result));
            }
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function generate_image()
{
    $result = array();
    if (checkloggedin()) {
        global $config;

        // if disabled by admin
        if(!$config['enable_ai_images']) {
            $result['success'] = false;
            $result['error'] = __('This feature is disabled by the admin.');
            die(json_encode($result));
        }

        if(!$config['non_active_allow']){
            $user_data = get_user_data(null,$_SESSION['user']['id']);
            if($user_data['status'] == 0){
                $result['success'] = false;
                $result['error'] = __('Verify your email address to use the AI.');
                die(json_encode($result));
            }
        }

        $_POST = validate_input($_POST);

        if (!empty($_POST['description'])) {

            $membership = get_user_membership_detail($_SESSION['user']['id']);
            $images_limit = $membership['settings']['ai_images_limit'];

            $start = date('Y-m-01');
            $end = date_create(date('Y-m-t'))->modify('+1 day')->format('Y-m-d');
            $total_images_used = ORM::for_table($config['db']['pre'] . 'image_used')
                ->where('user_id', $_SESSION['user']['id'])
                ->where_raw("(`date` BETWEEN '$start' AND '$end')")
                ->sum('images');

            $total_images_used = $total_images_used ?: 0;

            // check user's images limit
            if ($images_limit != -1 && (($images_limit - $total_images_used) < $_POST['no_of_images'])) {
                $result['success'] = false;
                $result['error'] = __('Images limit exceeded, Upgrade your membership plan.');
                die(json_encode($result));
            }

            $prompt = $_POST['description'];
            $prompt .= !empty($_POST['style']) ? ', ' . $_POST['style'] : '';
            $prompt .= !empty($_POST['lighting']) ? ', ' . $_POST['lighting'] : '';
            $prompt .= !empty($_POST['mood']) ? ', mood ' . $_POST['mood'] : '';

            // check bad words
            if($word = check_bad_words($prompt)){
                $result['success'] = false;
                $result['error'] = __('Your request contains a banned word:').' '.$word;
                die(json_encode($result));
            }

            // check image api
            $image_api = get_option('ai_image_api');
            if($image_api == 'any'){
                // check random
                $data = ['openai', 'stable-diffusion'];
                $image_api = $data[array_rand($data)];
            }

            if($image_api == 'stable-diffusion') {
                include ROOTPATH . '/includes/lib/StableDiffusion.php';

                $stableDiffusion = new StableDiffusion(get_image_api_key($image_api));

                if($_POST['resolution'] == '1024x1024'){
                    $width = 1024;
                    $height = 1024;
                } else {
                    $width = 512;
                    $height = 512;
                }

                $response = $stableDiffusion->image([
                    "text_prompts" => [
                        ["text" => $prompt]
                    ],
                    "height" => $height,
                    "width" => $width,
                    "samples" => (int)$_POST['no_of_images'],
                    "steps" => 50,
                    "cfg_scale" => 20,
                ]);
                $response = json_decode($response, true);
                if(isset($response['artifacts'])) {
                    foreach ($response['artifacts'] as $image) {

                        $name = uniqid() . '.png';
                        $target_dir = ROOTPATH . '/storage/ai_images/';
                        file_put_contents($target_dir . $name, base64_decode($image['base64']));
                        resizeImage(200, $target_dir . 'small_' . $name, $target_dir . $name);
                        $content = ORM::for_table($config['db']['pre'] . 'ai_images')->create();
                        $content->user_id = $_SESSION['user']['id'];
                        $content->title = $_POST['title'];
                        $content->description = $_POST['description'];
                        $content->resolution = $_POST['resolution'];
                        $content->image = $name;
                        $content->created_at = date('Y-m-d H:i:s');
                        $content->save();

                        $array = [
                            'small' => $config['site_url'] . 'storage/ai_images/small_' . $name,
                            'large' => $config['site_url'] . 'storage/ai_images/' . $name,
                        ];
                        $images[] = $array;
                    }

                    $image_used = ORM::for_table($config['db']['pre'] . 'image_used')->create();
                    $image_used->user_id = $_SESSION['user']['id'];
                    $image_used->images = (int)$_POST['no_of_images'];
                    $image_used->date = date('Y-m-d H:i:s');
                    $image_used->save();

                    $result['success'] = true;
                    $result['data'] = $images;
                    $result['description'] = $_POST['description'];
                    $result['old_used_images'] = $total_images_used;
                    $result['current_used_images'] = $total_images_used + $_POST['no_of_images'];
                } else {
                    // error log default message
                    if(!empty($response['error']['message']))
                        error_log('Stable Diffusion: '. $response['error']['message']);

                    $result['success'] = false;
                    $result['error'] = get_api_error_message($stableDiffusion->getCURLInfo()['http_code']);
                    die(json_encode($result));
                }
            } else {
                // openai
                require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/OpenAi.php';
                require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/Url.php';

                $open_ai = new Orhanerday\OpenAi\OpenAi(get_image_api_key($image_api));

                $complete = $open_ai->image([
                    'prompt' => $prompt,
                    'size' => $_POST['resolution'],
                    'n' => (int)$_POST['no_of_images'],
                    "response_format" => "url",
                    'user' => $_SESSION['user']['id']
                ]);

                $response = json_decode($complete, true);

                if (isset($response['data'])) {
                    $images = array();

                    foreach ($response['data'] as $key => $value) {
                        $url = $value['url'];

                        $name = uniqid() . '.png';

                        $image = file_get_contents($url);

                        $target_dir = ROOTPATH . '/storage/ai_images/';
                        file_put_contents($target_dir . $name, $image);

                        resizeImage(200, $target_dir . 'small_' . $name, $target_dir . $name);

                        $content = ORM::for_table($config['db']['pre'] . 'ai_images')->create();
                        $content->user_id = $_SESSION['user']['id'];
                        $content->title = $_POST['title'];
                        $content->description = $_POST['description'];
                        $content->resolution = $_POST['resolution'];
                        $content->image = $name;
                        $content->created_at = date('Y-m-d H:i:s');
                        $content->save();

                        $array = [
                            'small' => $config['site_url'] . 'storage/ai_images/small_' . $name,
                            'large' => $config['site_url'] . 'storage/ai_images/' . $name,
                        ];
                        $images[] = $array;
                    }

                    $image_used = ORM::for_table($config['db']['pre'] . 'image_used')->create();
                    $image_used->user_id = $_SESSION['user']['id'];
                    $image_used->images = (int)$_POST['no_of_images'];
                    $image_used->date = date('Y-m-d H:i:s');
                    $image_used->save();

                    $result['success'] = true;
                    $result['data'] = $images;
                    $result['description'] = $_POST['description'];
                    $result['old_used_images'] = $total_images_used;
                    $result['current_used_images'] = $total_images_used + $_POST['no_of_images'];
                } else {
                    // error log default message
                    if(!empty($response['error']['message']))
                        error_log('Stable Diffusion: '. $response['error']['message']);

                    $result['success'] = false;
                    $result['error'] = get_api_error_message($open_ai->getCURLInfo()['http_code']);
                    die(json_encode($result));
                }
            }
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function save_document()
{
    $result = array();
    if (checkloggedin()) {
        global $config;

        $content = validate_input($_POST['content'], true);
        $_POST = validate_input($_POST);
        $_POST['content'] = $content;

        if (!empty($_POST['id'])) {
            $content = ORM::for_table($config['db']['pre'] . 'ai_documents')->find_one($_POST['id']);
        } else {
            $content = ORM::for_table($config['db']['pre'] . 'ai_documents')->create();
        }

        $content->user_id = $_SESSION['user']['id'];
        $content->title = $_POST['title'];
        $content->content = $_POST['content'];
        $content->template = $_POST['ai_template'];
        $content->created_at = date('Y-m-d H:i:s');
        $content->save();

        $result['success'] = true;
        $result['id'] = $content->id();
        $result['message'] = __('Successfully Saved.');
        die(json_encode($result));
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function delete_document()
{
    $result = array();
    if (checkloggedin()) {
        global $config;

        $data = ORM::for_table($config['db']['pre'] . 'ai_documents')
            ->where(array(
                'id' => $_POST['id'],
                'user_id' => $_SESSION['user']['id'],
            ))
            ->delete_many();

        if ($data) {
            $result['success'] = true;
            $result['message'] = __('Deleted Successfully');
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function delete_image()
{
    $result = array();
    if (checkloggedin()) {
        global $config;

        $data = ORM::for_table($config['db']['pre'] . 'ai_images')
            ->where(array(
                'id' => $_POST['id'],
                'user_id' => $_SESSION['user']['id'],
            ))
            ->delete_many();

        if ($data) {
            $result['success'] = true;
            $result['message'] = __('Deleted Successfully');
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function send_ai_message() {
    $result = array();
    global $config;

    // if disabled by admin
    if(!$config['enable_ai_chat']) {
        $result['success'] = false;
        $result['error'] = __('This feature is disabled by the admin.');
        die(json_encode($result));
    }

    if (checkloggedin()) {

        if(!$config['non_active_allow']){
            $user_data = get_user_data(null,$_SESSION['user']['id']);
            if($user_data['status'] == 0){
                $result['success'] = false;
                $result['error'] = __('Verify your email address to use the AI.');
                die(json_encode($result));
            }
        }

        $membership = get_user_membership_detail($_SESSION['user']['id']);
        $words_limit = $membership['settings']['ai_words_limit'];
        $plan_ai_chat = $membership['settings']['ai_chat'];

        if(!$plan_ai_chat){
            $result['success'] = false;
            $result['error'] = __('Upgrade your membership plan to use this feature.');
            die(json_encode($result));
        }

        if (get_option('single_model_for_plans'))
            $model = get_option('open_ai_model', 'gpt-3.5-turbo');
        else
            $model = $membership['settings']['ai_model'];

        if($model != 'gpt-3.5-turbo' && $model != 'gpt-4') {
            $result['success'] = false;
            $result['error'] = __('You can not use the chat feature with your OpenAI model. ChatGPT model is required.');
            die(json_encode($result));
        }

        $start = date('Y-m-01');
        $end = date_create(date('Y-m-t'))->modify('+1 day')->format('Y-m-d');
        $total_words_used = ORM::for_table($config['db']['pre'] . 'word_used')
            ->where('user_id', $_SESSION['user']['id'])
            ->where_raw("(`date` BETWEEN '$start' AND '$end')")
            ->sum('words');

        $total_words_used = $total_words_used ?: 0;
        $total_available_words = $words_limit - $total_words_used;

        $max_tokens = (int) get_option("ai_chat_max_token", '-1');
        // check user's word limit
        $max_tokens_limit = $max_tokens == -1 ? 500 : $max_tokens;
        if ($words_limit != -1 && (($words_limit - $total_words_used) < $max_tokens_limit)) {
            $result['success'] = false;
            $result['error'] = __('Words limit exceeded, Upgrade your membership plan.');
            die(json_encode($result));
        }

        $_POST = validate_input($_POST);

        // check bad words
        if($word = check_bad_words($_POST['msg'])){
            $result['success'] = false;
            $result['error'] = __('Your request contains a banned word:').' '.$word;
            die(json_encode($result));
        }

        // create message history
        $ROLE = "role";
        $CONTENT = "content";
        $USER = "user";
        $SYS = "system";
        $ASSISTANT = "assistant";

        // get last 10 messages
        $sql = "SELECT * FROM
                (
                 SELECT * FROM ".$config['db']['pre'] . 'ai_chat'." WHERE `user_id` = {$_SESSION['user']['id']} ORDER BY id DESC LIMIT 8
                ) AS sub
                ORDER BY id ASC;";
        $chats = ORM::for_table($config['db']['pre'] . 'ai_chat')
            ->raw_query($sql)
            ->find_array();

        $history[] = [$ROLE => $SYS, $CONTENT => "You are a helpful assistant."];
        foreach ($chats as $chat) {
            $history[] = [$ROLE => $USER, $CONTENT => $chat['user_message']];
            if(!empty($chat['ai_message']))
                $history[] = [$ROLE => $ASSISTANT, $CONTENT => $chat['ai_message']];
        }
        $history[] = [$ROLE => $USER, $CONTENT => $_POST['msg']];

        require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/OpenAi.php';
        require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/Url.php';

        $open_ai = new Orhanerday\OpenAi\OpenAi(get_api_key());

        $opts = [
            'model' => $model,
            'messages' => $history,
            'temperature' => 1.0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'user' => $_SESSION['user']['id']
        ];
        if($max_tokens != -1) {
            $opts['max_tokens'] = $max_tokens;
        }

        $complete = $open_ai->chat($opts);
        $response = json_decode($complete, true);

        if(isset($response['choices'])) {
            $ai_message = (trim($response['choices'][0]['message']['content']));

            // save chat
            $chat = ORM::for_table($config['db']['pre'] . 'ai_chat')->create();
            $chat->user_id = $_SESSION['user']['id'];
            $chat->user_message = $_POST['msg'];
            $chat->ai_message = $ai_message;
            $chat->date = date('Y-m-d H:i:s');
            $chat->save();

            $tokens = $response['usage']['completion_tokens'];

            $word_used = ORM::for_table($config['db']['pre'] . 'word_used')->create();
            $word_used->user_id = $_SESSION['user']['id'];
            $word_used->words = $tokens;
            $word_used->date = date('Y-m-d H:i:s');
            $word_used->save();

            $result['success'] = true;
            $result['message'] = nl2br(escape($ai_message));
            $result['old_used_words'] = $total_words_used;
            $result['current_used_words'] = $total_words_used + $tokens;
            die(json_encode($result));
        }

        // error log default message
        if(!empty($response['error']['message']))
            error_log('OpenAI: '. $response['error']['message']);

        $result['success'] = false;
        $result['error'] = get_api_error_message($open_ai->getCURLInfo()['http_code']);
        die(json_encode($result));
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function delete_ai_chats () {
    $result = array();
    if (checkloggedin()) {
        global $config;

        $data = ORM::for_table($config['db']['pre'] . 'ai_chat')
            ->where(array(
                'user_id' => $_SESSION['user']['id'],
            ))
            ->delete_many();

        if ($data) {
            $result['success'] = true;
            $result['message'] = __('Deleted Successfully');
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function export_ai_chats () {
    $result = array();
    if (checkloggedin()) {
        global $config;

        $data = ORM::for_table($config['db']['pre'] . 'ai_chat')
            ->table_alias('c')
            ->select_many_expr('c.*','u.name full_name')
            ->where('c.user_id',$_SESSION['user']['id'])
            ->join($config['db']['pre'] . 'user', 'u.id = c.user_id', 'u')
            ->find_array();

        $text = '';
        $ai_name = get_option('ai_chat_bot_name', __('AI Chat Bot'));
        foreach ($data as $chat) {
            // user
            $text .= "[{$chat['date']}] ";
            $text .= $chat['full_name'].': ';
            $text .= $chat['user_message']."\n\n";

            // ai
            if(!empty($chat['ai_message'])) {
                $text .= "[{$chat['date']}] ";
                $text .= $ai_name . ': ';
                $text .= $chat['ai_message'] . "\n\n";
            }
        }
        $result['success'] = true;
        $result['text'] = $text;
        die(json_encode($result));
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function speech_to_text() {
    $result = array();
    global $config;

    // if disabled by admin
    if(!$config['enable_speech_to_text']) {
        $result['success'] = false;
        $result['error'] = __('This feature is disabled by the admin.');
        die(json_encode($result));
    }

    if (checkloggedin()) {
        if(!$config['non_active_allow']){
            $user_data = get_user_data(null,$_SESSION['user']['id']);
            if($user_data['status'] == 0){
                $result['success'] = false;
                $result['error'] = __('Verify your email address to use the AI.');
                die(json_encode($result));
            }
        }

        $_POST = validate_input($_POST);

        if (!empty($_FILES['file']['tmp_name'])) {

            $membership = get_user_membership_detail($_SESSION['user']['id']);
            $speech_to_text_limit = $membership['settings']['ai_speech_to_text_limit'];
            $speech_text_file_limit = $membership['settings']['ai_speech_to_text_file_limit'];

            $start = date('Y-m-01');
            $end = date_create(date('Y-m-t'))->modify('+1 day')->format('Y-m-d');
            $total_speech_used = ORM::for_table($config['db']['pre'] . 'speech_to_text_used')
                ->where('user_id', $_SESSION['user']['id'])
                ->where_raw("(`date` BETWEEN '$start' AND '$end')")
                ->count();

            $total_speech_used = $total_speech_used ?: 0;

            // check user's images limit
            if ($speech_to_text_limit != -1 && (($speech_to_text_limit - $total_speech_used) < 1)) {
                $result['success'] = false;
                $result['error'] = __('Audio transcription limit exceeded, Upgrade your membership plan.');
                die(json_encode($result));
            }

            if($speech_text_file_limit != -1 && ($_FILES['file']['size'] > $speech_text_file_limit * 1024 * 1024)) {
                $result['success'] = false;
                $result['error'] = __('File size limit exceeded, Upgrade your membership plan.');
                die(json_encode($result));
            }

            // check bad words
            if($word = check_bad_words($_POST['description'])){
                $result['success'] = false;
                $result['error'] = __('Your request contains a banned word:').' '.$word;
                die(json_encode($result));
            }

            require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/OpenAi.php';
            require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/Url.php';

            $open_ai = new Orhanerday\OpenAi\OpenAi(get_api_key());

            $tmp_file = $_FILES['file']['tmp_name'];
            $file_name = basename($_FILES['file']['name']);
            $c_file = curl_file_create($tmp_file, $_FILES['file']['type'], $file_name);
            $complete = $open_ai->transcribe([
                "model" => "whisper-1",
                "file" => $c_file,
                "prompt" => $_POST['description'],
                'user' => $_SESSION['user']['id']
            ]);

            $response = json_decode($complete, true);

            if (isset($response['text'])) {
                $response['text'] = nl2br(trim($response['text']));

                $content = ORM::for_table($config['db']['pre'] . 'ai_documents')->create();
                $content->user_id = $_SESSION['user']['id'];
                $content->title = !empty($_POST['title']) ? $_POST['title'] : __('Untitled Document');
                $content->content = $response['text'];
                $content->template = 'quickai-speech-to-text';
                $content->created_at = date('Y-m-d H:i:s');
                $content->save();

                $speech_used = ORM::for_table($config['db']['pre'] . 'speech_to_text_used')->create();
                $speech_used->user_id = $_SESSION['user']['id'];
                $speech_used->date = date('Y-m-d H:i:s');
                $speech_used->save();

                $result['success'] = true;
                $result['text'] = $response['text'];
                $result['old_used_speech'] = $speech_to_text_limit;
                $result['current_used_speech'] = $total_speech_used;
            } else {
                // error log default message
                if(!empty($response['error']['message']))
                    error_log('OpenAI: '. $response['error']['message']);

                $result['success'] = false;
                $result['error'] = get_api_error_message($open_ai->getCURLInfo()['http_code']);
                die(json_encode($result));
            }
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}

function ai_code()
{
    $result = array();
    if (checkloggedin()) {
        global $config;

        // if disabled by admin
        if(!$config['enable_ai_code']) {
            $result['success'] = false;
            $result['error'] = __('This feature is disabled by the admin.');
            die(json_encode($result));
        }

        if(!$config['non_active_allow']){
            $user_data = get_user_data(null,$_SESSION['user']['id']);
            if($user_data['status'] == 0){
                $result['success'] = false;
                $result['error'] = __('Verify your email address to use the AI.');
                die(json_encode($result));
            }
        }

        $_POST = validate_input($_POST);

        if (!empty($_POST['description'])) {

            $prompt = $_POST['description'];
            $max_tokens = (int) get_option("ai_code_max_token", '-1');

            $membership = get_user_membership_detail($_SESSION['user']['id']);
            $words_limit = $membership['settings']['ai_words_limit'];
            $plan_ai_code = $membership['settings']['ai_code'];

            if (get_option('single_model_for_plans'))
                $model = get_option('open_ai_model', 'gpt-3.5-turbo');
            else
                $model = $membership['settings']['ai_model'];

            $start = date('Y-m-01');
            $end = date_create(date('Y-m-t'))->modify('+1 day')->format('Y-m-d');
            $total_words_used = ORM::for_table($config['db']['pre'] . 'word_used')
                ->where('user_id', $_SESSION['user']['id'])
                ->where_raw("(`date` BETWEEN '$start' AND '$end')")
                ->sum('words');

            $total_words_used = $total_words_used ?: 0;

            // check if user's membership have the template
            if (!$plan_ai_code) {
                $result['success'] = false;
                $result['error'] = __('Upgrade your membership plan to use this feature');
                die(json_encode($result));
            }

            // check user's word limit
            $max_tokens_limit = $max_tokens == -1 ? 500 : $max_tokens;
            if ($words_limit != -1 && (($words_limit - $total_words_used) < $max_tokens_limit)) {
                $result['success'] = false;
                $result['error'] = __('Words limit exceeded, Upgrade your membership plan.');
                die(json_encode($result));
            }

            // check bad words
            if($word = check_bad_words($prompt)){
                $result['success'] = false;
                $result['error'] = __('Your request contains a banned word:').' '.$word;
                die(json_encode($result));
            }

            require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/OpenAi.php';
            require_once ROOTPATH . '/includes/lib/orhanerday/open-ai/src/Url.php';

            $open_ai = new Orhanerday\OpenAi\OpenAi(get_api_key());

            if ($model == 'gpt-3.5-turbo' || $model == 'gpt-4') {
                $opt = [
                    'model' => $model,
                    'messages' => [
                        [
                            "role" => "user",
                            "content" => $prompt
                        ],
                    ],
                    'temperature' => 1,
                    'n' => 1,
                    'user' => $_SESSION['user']['id']
                ];
                if($max_tokens != -1) {
                    $opt['max_tokens'] = $max_tokens;
                }
                $complete = $open_ai->chat($opt);
            } else {
                $opt = [
                    'model' => $model,
                    'prompt' => $prompt,
                    'temperature' => 1,
                    'n' => 1,
                ];
                if($max_tokens != -1) {
                    $opt['max_tokens'] = $max_tokens;
                }
                $complete = $open_ai->completion($opt);
            }

            $response = json_decode($complete, true);

            if (isset($response['choices'])) {
                if ($model == 'gpt-3.5-turbo' || $model == 'gpt-4') {
                    $text = trim($response['choices'][0]['message']['content']);
                } else {
                    $text = trim($response['choices'][0]['text']);
                }

                // replace the code
                if(preg_match_all('/```([\s\S]+?)```/', $text, $parts))
                {
                    foreach ($parts[1] as $key => $part){
                        $part = escape($part);
                        $text = str_replace($parts[0][$key], '</p><pre><code>'.$part.'</code></pre><p>', $text);
                    }
                } else {
                    $text =  escape($text);
                }
                $text =  nl2br($text);

                $tokens = $response['usage']['completion_tokens'];

                $content = ORM::for_table($config['db']['pre'] . 'ai_documents')->create();
                $content->user_id = $_SESSION['user']['id'];
                $content->title = !empty($_POST['title']) ? $_POST['title'] : __('Untitled Document');
                $content->content = $text;
                $content->template = 'quickai-ai-code';
                $content->created_at = date('Y-m-d H:i:s');
                $content->save();

                $word_used = ORM::for_table($config['db']['pre'] . 'word_used')->create();
                $word_used->user_id = $_SESSION['user']['id'];
                $word_used->words = $tokens;
                $word_used->date = date('Y-m-d H:i:s');
                $word_used->save();

                $result['success'] = true;
                $result['text'] = $text;
                $result['old_used_words'] = $total_words_used;
                $result['current_used_words'] = $total_words_used + $tokens;
            } else {
                // error log default message
                if(!empty($response['error']['message']))
                    error_log('OpenAI: '. $response['error']['message']);

                $result['success'] = false;
                $result['error'] = get_api_error_message($open_ai->getCURLInfo()['http_code']);
                die(json_encode($result));
            }
            die(json_encode($result));
        }
    }
    $result['success'] = false;
    $result['error'] = __('Unexpected error, please try again.');
    die(json_encode($result));
}
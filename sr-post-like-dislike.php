<?php

/**
 * Plugin Name: SR Post Like Dislike
 * Plugin URI: https://superrishi.com/plugin/sr-post-like-dislike/
 * Description: A plugin to add like and dislike functionality to posts,pages, and custom post types.
 * Version: 1.0.1
 * Author: superrishi
 * Author URI: https://superrishi.com
 * Requires at least: 5.0
 * Tested up to: 6.1.1
 * WC requires at least: 3.1
 * WC tested up to: 7.3.0
 * Requires PHP: 7.2
 * License: GPLv2 or later
 * Text Domain: sr-post-like-dislike
 */
if (!defined('ABSPATH')) {
    exit;
}

class PostLikeDislikePlugin {

    protected $post_id;
    public $api_connection_nonce_string;
    public $liked_by_user_meta_key = 'sr_posts_liked_by_me';
    public $disliked_by_user_meta_key = 'sr_posts_disliked_by_me';
    public $likeDislikeResponse = array(
        'like' => 'Post id successfully added to liked posts.',
        'already_like' => 'already liked',
        'like_removed' => 'Post id successfully removed from liked posts.',
        'like_not_found' => 'Post id not found in liked post ids.',
        'dislike' => 'Post id successfully added to disliked posts.',
        'already_dislike' => 'already disliked',
        'dislike_removed' => 'Post id successfully removed from disliked posts.',
        'dislike_not_found' => 'Post id not found in disliked post ids.'
    );

    public function __construct($post_id) {
        $this->post_id = $post_id;
        $this->api_connection_nonce_string = 'secured_api_connection';
    }

    public function likeDislike() {
        check_ajax_referer($this->api_connection_nonce_string, 'security');
        $post_id = sanitize_text_field($_POST['post_id']);
        $type = sanitize_text_field($_POST['type']);
        $dislike_count = get_post_meta($post_id, 'sr_post_dislike_count', true);
        if (!trim($dislike_count)) {
            $dislike_count = 0;
        }
        $like_count = get_post_meta($post_id, 'sr_post_like_count', true);
        if (!trim($like_count)) {
            $like_count = 0;
        }
        if (is_user_logged_in()) {
            $response = $this->likeDislikeForLoggedInUsers($post_id, $type);
        } else {
            $response = $this->likeDislikeForNonLoggedInUsers($post_id, $type);
        }

        $this->manageLikeDislikeCounter($post_id, $type, $like_count, $dislike_count, $response);
        wp_send_json_success($response);
        exit;
    }

    public function likeDislikeForLoggedInUsers($post_id, $type) {
        $user_id = get_current_user_id();
        switch ($type):
            case 'like':
                return $this->likeForLoggedInUsers($post_id, $user_id);
            case 'unlike':
                return $this->unlikeForLoggedInUsers($post_id, $user_id);
            case 'dislike':
                return $this->dislikeForLoggedInUsers($post_id, $user_id);
            case 'undislike':
                return $this->undislikeForLoggedInUsers($post_id, $user_id);
        endswitch;
    }

    public function likeForLoggedInUsers($post_id, $user_id) {
        $user_liked_posts_ids = $this->get_user_liked_posts_ids($user_id);
        if (in_array($post_id, $user_liked_posts_ids)) {
            return (array('message' => $this->likeDislikeResponse['already_like']));
        } else {
            $user_liked_posts_ids[] = $post_id;
            $user_liked_posts_ids_comma_sep = implode(',', $user_liked_posts_ids);
            update_user_meta($user_id, $this->liked_by_user_meta_key, $user_liked_posts_ids_comma_sep);
            return (array('message' => $this->likeDislikeResponse['like']));
        }
    }

    public function unlikeForLoggedInUsers($post_id, $user_id) {
        $user_liked_posts_ids = $this->get_user_liked_posts_ids($user_id);
        if (in_array($post_id, $user_liked_posts_ids)) {
            $this->removePostIdLikedByUser($post_id, $user_id, $user_liked_posts_ids);
            return (array('message' => $this->likeDislikeResponse['like_removed']));
        } else {
            return (array('message' => $this->likeDislikeResponse['like_not_found']));
        }
    }

    public function dislikeForLoggedInUsers($post_id, $user_id) {
        $user_disliked_posts_ids = $this->get_user_disliked_posts_ids($user_id);
        if (in_array($post_id, $user_disliked_posts_ids)) {
            return (array('message' => $this->likeDislikeResponse['already_dislike']));
        } else {
            $user_disliked_posts_ids[] = $post_id;
            $user_disliked_posts_ids_comma_sep = implode(',', $user_disliked_posts_ids);
            update_user_meta($user_id, $this->disliked_by_user_meta_key, $user_disliked_posts_ids_comma_sep);
            return (array('message' => $this->likeDislikeResponse['dislike']));
        }
    }

    public function undislikeForLoggedInUsers($post_id, $user_id) {
        $user_disliked_posts_ids = $this->get_user_disliked_posts_ids($user_id);
        if (in_array($post_id, $user_disliked_posts_ids)) {
            $this->removePostIdDislikedByUser($post_id, $user_id, $user_disliked_posts_ids);
            return (array('message' => $this->likeDislikeResponse['dislike_removed']));
        } else {
            return (array('message' => $this->likeDislikeResponse['dislike_not_found']));
        }
    }

    public function get_user_liked_posts_ids($user_id) {
        return explode(',', sanitize_option($this->liked_by_user_meta_key, get_user_meta($user_id, $this->liked_by_user_meta_key, true)));
    }

    public function likeDislikeForNonLoggedInUsers($post_id, $type) {
        switch ($type):
            case 'like':
                return $this->likeForNonLoggedInUsers($post_id);
            case 'unlike':
                return $this->unlikeForNonLoggedInUsers($post_id);
            case 'dislike':
                return $this->dislikeForNonLoggedInUsers($post_id);
            case 'undislike':
                return $this->undislikeForNonLoggedInUsers($post_id);
        endswitch;
    }

    public function likeForNonLoggedInUsers($post_id) {
        if (isset($_COOKIE['sr_posts_liked_by_me'])) {
            $user_liked_posts_ids = explode(',', sanitize_text_field($_COOKIE['sr_posts_liked_by_me']));
            if (in_array($post_id, $user_liked_posts_ids)) {
                return (array('message' => $this->likeDislikeResponse['already_like']));
            } else {
                $user_liked_posts_ids[] = $post_id;
                $user_liked_posts_ids_comma_sep = implode(',', $user_liked_posts_ids);
                setcookie('sr_posts_liked_by_me', $user_liked_posts_ids_comma_sep, time() + (86400 * 365 * 10), '/');
                return (array('message' => $this->likeDislikeResponse['like']));
            }
        } else {
            $user_liked_posts_ids[] = $post_id;
            $user_liked_posts_ids_comma_sep = implode(',', $user_liked_posts_ids);
            setcookie('sr_posts_liked_by_me', $user_liked_posts_ids_comma_sep, time() + (86400 * 365 * 10), '/');
            return (array('message' => $this->likeDislikeResponse['like']));
        }
    }

    public function unlikeForNonLoggedInUsers($post_id) {
        if (isset($_COOKIE['sr_posts_liked_by_me'])) {
            $user_liked_posts_ids = explode(',', sanitize_text_field($_COOKIE['sr_posts_liked_by_me']));
            if (in_array($post_id, $user_liked_posts_ids)) {
                $index_of_post_id = array_search($post_id, $user_liked_posts_ids);
                unset($user_liked_posts_ids[$index_of_post_id]);
                $user_liked_posts_ids_comma_sep = implode(',', $user_liked_posts_ids);
                setcookie('sr_posts_liked_by_me', $user_liked_posts_ids_comma_sep, time() + (86400 * 365 * 10), '/');
                return (array('message' => $this->likeDislikeResponse['like_removed']));
            } else {
                return (array('message' => $this->likeDislikeResponse['like_not_found']));
            }
        }
    }

    public function dislikeForNonLoggedInUsers($post_id) {
        if (isset($_COOKIE['sr_posts_disliked_by_me'])) {
            $user_disliked_posts_ids = explode(',', sanitize_text_field($_COOKIE['sr_posts_disliked_by_me']));
            if (in_array($post_id, $user_disliked_posts_ids)) {
                return (array('message' => $this->likeDislikeResponse['already_dislike']));
            } else {
                $user_disliked_posts_ids[] = $post_id;
                $user_disliked_posts_ids_comma_sep = implode(',', $user_disliked_posts_ids);
                setcookie('sr_posts_disliked_by_me', $user_disliked_posts_ids_comma_sep, time() + (86400 * 365 * 10), '/');
                return (array('message' => $this->likeDislikeResponse['dislike']));
            }
        } else {
            $user_disliked_posts_ids[] = $post_id;
            $user_disliked_posts_ids_comma_sep = implode(',', $user_disliked_posts_ids);
            setcookie('sr_posts_disliked_by_me', $user_disliked_posts_ids_comma_sep, time() + (86400 * 365 * 10), '/');
            return (array('message' => $this->likeDislikeResponse['dislike']));
        }
    }

    public function undislikeForNonLoggedInUsers($post_id) {
        if (isset($_COOKIE['sr_posts_disliked_by_me'])) {
            $user_disliked_posts_ids = explode(',', sanitize_text_field($_COOKIE['sr_posts_disliked_by_me']));
            if (in_array($post_id, $user_disliked_posts_ids)) {
                $index_of_post_id = array_search($post_id, $user_disliked_posts_ids);
                unset($user_disliked_posts_ids[$index_of_post_id]);
                $user_disliked_posts_ids_comma_sep = implode(',', $user_disliked_posts_ids);
                setcookie('sr_posts_disliked_by_me', $user_disliked_posts_ids_comma_sep, 0, '/');
                return (array('message' => $this->likeDislikeResponse['dislike_removed']));
            } else {
                return (array('message' => $this->likeDislikeResponse['dislike_not_found']));
            }
        }
    }

    public function get_user_disliked_posts_ids($user_id) {
        return explode(',', sanitize_option($this->disliked_by_user_meta_key, get_user_meta($user_id, $this->disliked_by_user_meta_key, true)));
    }

    public function removePostIdLikedByUser($post_id, $user_id, $liked_ids) {
        $index_of_post_id = array_search($post_id, $liked_ids);
        unset($liked_ids[$index_of_post_id]);
        $liked_ids_comma_sep = implode(',', $liked_ids);
        update_user_meta($user_id, $this->liked_by_user_meta_key, $liked_ids_comma_sep);
    }

    public function removePostIdDislikedByUser($post_id, $user_id, $disliked_ids) {
        $index_of_post_id = array_search($post_id, $disliked_ids);
        unset($disliked_ids[$index_of_post_id]);
        $disliked_ids_comma_sep = implode(',', $disliked_ids);
        update_user_meta($user_id, $this->disliked_by_user_meta_key, $disliked_ids_comma_sep);
    }

    public function manageLikeDislikeCounter($post_id, $type, $like_count, $dislike_count, $response) {
        switch ($type):
            case 'like':
                if ($response['message'] !== $this->likeDislikeResponse['already_like']) {
                    update_post_meta($post_id, 'sr_post_like_count', ++$like_count);
                }
                break;
            case 'unlike':
                if ($like_count > 0):
                    update_post_meta($post_id, 'sr_post_like_count', --$like_count);
                endif;
                break;
            case 'dislike':
                if ($response['message'] !== $this->likeDislikeResponse['already_dislike']) {
                    update_post_meta($post_id, 'sr_post_dislike_count', ++$dislike_count);
                }
                break;
            case 'undislike':
                if ($dislike_count > 0):
                    update_post_meta($post_id, 'sr_post_dislike_count', --$dislike_count);
                endif;
                break;
        endswitch;
    }

    public function getLikeCount() {
        return get_post_meta($this->post_id, 'sr_post_like_count', true);
    }

    public function getDislikeCount() {
        return get_post_meta($this->post_id, 'sr_post_dislike_count', true);
    }

}

class PostLikeDislikeIcons {

    protected $post_id;
    protected $like_dislike_plugin;

    public function __construct($post_id) {
        $this->post_id = $post_id;
        $this->like_dislike_plugin = new PostLikeDislikePlugin($post_id);
    }

    function enqueue_font_awesome() {
        wp_enqueue_style('like-dislike', plugin_dir_url(__FILE__) . 'assets/like-dislike.css', array(), '1.0.0', 'all');
        wp_enqueue_script('like-dislike', plugin_dir_url(__FILE__) . 'assets/like-dislike.js', array('jquery'), '1.0.0', true);
        wp_localize_script('like-dislike', 'likeDislikeVars', array(
            'url' => admin_url('admin-ajax.php'),
            'action' => 'likeDislike',
            'security' => wp_create_nonce($this->like_dislike_plugin->api_connection_nonce_string)
        ));
    }

    public function display() {
        // Get the current like and dislike counts for the post
        $like_count = $this->like_dislike_plugin->getLikeCount();
        $dislike_count = $this->like_dislike_plugin->getDislikeCount();
        if (is_user_logged_in()):
            $user_id = get_current_user_id();
            $user_liked_posts_ids = $this->like_dislike_plugin->get_user_liked_posts_ids($user_id);
            $user_disliked_posts_ids = $this->like_dislike_plugin->get_user_disliked_posts_ids($user_id);
        else:
            $user_liked_posts_ids = explode(',', sanitize_text_field($_COOKIE['sr_posts_liked_by_me']));
            $user_disliked_posts_ids = explode(',', sanitize_text_field($_COOKIE['sr_posts_disliked_by_me']));
        endif;
        $liked = (is_array($user_liked_posts_ids) && in_array($this->post_id, $user_liked_posts_ids)) ? 'liked' : '';
        $disliked = (is_array($user_disliked_posts_ids) && in_array($this->post_id, $user_disliked_posts_ids)) ? 'disliked' : '';
        // Build the HTML for the like and dislike icons
        $html = '<div class="like-dislike-icons">';
        $html .= '<span class="like-icon"><span class="like-post-id-' . $this->post_id . ' ' . $liked . '" onclick="return likeUnlikePost(' . $this->post_id . ');" ontouchstart="return likeUnlikePost(' . $this->post_id . ');"></span> <code class="like-count-post-id-' . $this->post_id . '">' . ($like_count ? $like_count : 0) . '</code></span>';
        $html .= '<span class="dislike-icon"><span class="dislike-post-id-' . $this->post_id . ' ' . $disliked . '" data-post-id="' . $this->post_id . '" onclick="return dislikeUndislikePost(' . $this->post_id . ');" ontouchstart="return dislikeUndislikePost(' . $this->post_id . ');"></span> <code class="dislike-count-post-id-' . $this->post_id . '">' . ($dislike_count ? $dislike_count : 0) . '</code></span>';
        $html .= '</div>';
        return $html;
    }

    function display_like_dislike_icons($title, $post_id) {
        if (in_the_loop()) {
            $like_dislike_icons = new PostLikeDislikeIcons($post_id);
            $html = $like_dislike_icons->display();
            $title = $title . $html;
        }
        return $title;
    }

    public function displayOnPostTitle() {
        if (!is_admin()) {
            add_filter('the_title', array($this, 'display_like_dislike_icons'), 10, 2);
        }
        add_action('wp_enqueue_scripts', array($this, 'enqueue_font_awesome'));
        add_action('wp_ajax_likeDislike', array($this->like_dislike_plugin, 'likeDislike'));
        add_action('wp_ajax_nopriv_likeDislike', array($this->like_dislike_plugin, 'likeDislike'));
    }

}

$post_id = get_the_ID();
$like_dislike_icons = new PostLikeDislikeIcons($post_id);
$like_dislike_icons->displayOnPostTitle();


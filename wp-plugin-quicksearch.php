<?php
/**
 * @package Quick Search for Wordpress
 * @version 1.02
 */
/*
Plugin Name: Quick Search for Wordpress
Plugin URI: http://www.quick-solution.com/quicksearch
Description: Automatically update the search index of QuickSearch.
Author: QUICK SOLUTION
Version: 1.01
Author URI: https://profiles.wordpress.org/morimasato/
*/

define( 'QUICKSEARCH_VERSION', '1.02' );
define( 'QUICKSEARCH_URI',  'https://www.quick-solution.com/');

/**
 * Add Quick Search Settings in plugin action links
 */
function quicksearch_plugin_action_links($links, $file) {
    if ($file == plugin_basename(dirname(__FILE__).'/wp-plugin-quicksearch.php')) {
        $links[] = '<a href="options-general.php?page=quicksearch-site-search/wp-plugin-quicksearch.php">'.__('Settings').'</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'quicksearch_plugin_action_links', 10, 2);

/**
 * Add Quick Search Settings in admin menu
 */
function quicksearch_plugin_admin_config_add_menu() {
    $hookname = add_submenu_page(
        'options-general.php',
        'Quick Search for Wordpress',
        'Quick Search for Wordpress',
        'manage_options',
        __FILE__,
        'quicksearch_admin_config'
    );
}
add_action('admin_menu', 'quicksearch_plugin_admin_config_add_menu', 99);

/**
 * Quick Search Settings
 */
function quicksearch_admin_config() {

    echo '<div class="wrap">';
    echo '<h1>Quick Search for Wordpress</h1>';
    $quicksearch_content_key = get_option('quicksearch_content_key');
    $quicksearch_api_key = get_option('quicksearch_api_key');
    if (!empty($_POST['posted']) && $_POST['posted'] === 'yes') {
        check_admin_referer('quicksearch_admin_config', 'quicksolution');
        $quicksearch_content_key = stripcslashes(strip_tags($_POST['quicksearch_content_key']));
        $quicksearch_api_key = stripcslashes(strip_tags($_POST['quicksearch_api_key']));
        $url = QUICKSEARCH_URI . 'content/' . $quicksearch_content_key;
        $options = array(
            'timeout' => 60,
            'user-agent' => 'WordPress/' . $GLOBALS['wp_version'] . '; QuickSearch/' . QUICKSEARCH_VERSION . '; ' . home_url(),
            'headers' => array('X-FTS-API-Key' => $quicksearch_api_key)
        );
        $response = wp_remote_get( $url, $options );
        if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
            echo '<div class="error"><p><strong>' . implode(' ', $response['response']) . '</strong></p></div>';
        }else{
            $json = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $json )) {
                if ($json['code'] == '0000') {
                    update_option('quicksearch_content_key', $quicksearch_content_key);
                    update_option('quicksearch_api_key', $quicksearch_api_key);
                    echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
                }else{
                    echo '<div class="error"><p><strong>' . $json['code'] . ' ' . $json['message'] . '</strong></p></div>';
                }
            }else{
                echo '<div class="error"><p><strong>' . implode(' ', $response['response']) . '</strong></p></div>';
            }
        }
    }
    echo '<form method="post" action="#">';
    echo '<input type="hidden" name="posted" value="yes">';
    echo wp_nonce_field('quicksearch_admin_config', 'quicksolution', true, false);
    echo '<table class="form-table">';
    echo '<tbody><tr>';
    echo '<th scope="row"><label for="quicksearch_content_key">'.__('Content Key').'</label></th>';
    echo '<td><input name="quicksearch_content_key" type="text" id="quicksearch_content_key" value="'.($quicksearch_content_key ? $quicksearch_content_key : '').'" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="quicksearch_api_key">'.__('API Key').'</label></th>';
    echo '<td><input name="quicksearch_api_key" type="text" id="quicksearch_api_key" value="'.($quicksearch_api_key ? $quicksearch_api_key : '').'" class="regular-text"></td>';
    echo '</tr>';
    echo '</tbody></table>';
    echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="'.__('Save Changes').'"></p></form>';
    echo '</div>';
}

/**
 * Quick Search All Status Transitions
 */
function quicksearch_all_status_transitions( $new_status, $old_status, $post ) {
    if( $new_status == 'publish' ){
        $method = 'create';
    }else{
        if ( $new_status != $old_status && $old_status == 'publish' ) {
            $method = 'delete';
        }else{
            return;
        }
    }
    $quicksearch_content_key = get_option('quicksearch_content_key');
    $quicksearch_api_key = get_option('quicksearch_api_key');
    if (empty($quicksearch_content_key) || empty($quicksearch_api_key)) {
        return;
    }
    $url = QUICKSEARCH_URI . 'content/' . $quicksearch_content_key . '/index/';
    $http_args = array(
        'method' => 'PUT',
        'timeout' => 60,
        'user-agent' => 'WordPress/' . $GLOBALS['wp_version'] . '; QuickSearch/' . QUICKSEARCH_VERSION . '; ' . home_url(),
        'headers' => array('X-FTS-API-Key' => $quicksearch_api_key),
        'body' => array(
            'json' => wp_json_encode(
                array(
                    array(
                        'method' => $method,
                        'url' => get_permalink( $post->ID ),
                        'delay' => 1
                    )
                )
            )
        )
    );
    $response = wp_remote_post( $url, $http_args );
}

/**
 * Quick Search Init
 */
function quicksearch_init() {
    add_action( 'transition_post_status', 'quicksearch_all_status_transitions', 10, 3 );
}
add_action('init', 'quicksearch_init');

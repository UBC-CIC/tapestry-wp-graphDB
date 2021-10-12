<?php

require_once __DIR__.'/classes/class.tapestry-analytics.php';

/**
 * Plugin Name: Tapestry
 * Plugin URI: https://www.tapestry-tool.com
 * Description: Custom post type - Tapestry
 * Version: 2.41.0-beta
 * Author: Tapestry Team, University of British Coloumbia.
 */

// Used to force-refresh assets
$TAPESTRY_VERSION_NUMBER = '2.44.0-beta';

// Set this to false if you want to use the Vue build instead of npm dev
$TAPESTRY_USE_DEV_MODE = true;

/**
 * Register endpoints.
 */
require_once dirname(__FILE__).'/endpoints.php';
require_once dirname(__FILE__).'/settings.php';
require_once dirname(__FILE__).'/plugin-updates.php';

/**
 * Register Tapestry type on initialization.
 */
function create_tapestry_type()
{
    $labels = [
        'name' => __('Tapestries'),
        'singular_name' => __('Tapestry'),
        'all_items' => __('All Tapestries'),
    ];
    $args = [
        'label' => __('tapestry'),
        'labels' => $labels,
        'description' => '',
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'delete_with_user' => false,
        'show_in_rest' => true,
        'rest_base' => '',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'has_archive' => false,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'exclude_from_search' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => false,
        'rewrite' => ['with_front' => true],
        'query_var' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'author'],
    ];
    register_post_type('tapestry', $args);
}
add_action('init', 'create_tapestry_type');

/**
 * Register Tapestry Node type on initialization.
 */
function create_tapestry_node_type()
{
    $labels = [
        'name' => __('Tapestry Nodes'),
        'singular_name' => __('Tapestry Node'),
        'all_items' => __('Tapestry Nodes'),
    ];
    $args = [
        'label' => __('tapestry-node'),
        'labels' => $labels,
        'description' => '',
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'delete_with_user' => false,
        'show_in_rest' => true,
        'rest_base' => '',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'has_archive' => false,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'exclude_from_search' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => false,
        'rewrite' => ['with_front' => true],
        'query_var' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'author'],
    ];
    register_post_type('tapestry_node', $args);
}
add_action('init', 'create_tapestry_node_type');

/**
 * Show posts of Tapestry type on the home page.
 */
function add_tapestry_post_types_to_query($query)
{
    if (is_home() && $query->is_main_query()) {
        $query->set('post_type', ['post', 'tapestry', 'tapestry-node']);
    }

    return $query;
}
add_action('pre_get_posts', 'add_tapestry_post_types_to_query');

/*
 * Add custom tapestry_thumb size
 */
add_action('after_setup_theme', 'tapestry_theme_setup');
function tapestry_theme_setup()
{
    add_image_size('tapestry_thumb', 420, 420, true);
}

/*
 * Enqueue scripts and styles for the tapestry
 */

add_action('wp_enqueue_scripts', 'tapestry_enqueue_libraries');
add_action('wp_enqueue_scripts', 'tapestry_enqueue_vue_app');
add_filter('style_loader_tag', 'tapestry_add_style_attributes', 10, 2);

function tapestry_enqueue_vue_app()
{
    global $post;
    if ('tapestry' == get_post_type($post) && !post_password_required($post)) {
        global $TAPESTRY_VERSION_NUMBER;
        global $TAPESTRY_USE_DEV_MODE;

        // register the Vue build script.
        $vueUrl = $TAPESTRY_USE_DEV_MODE ? 'http://localhost:8080/dist' : plugin_dir_url(__FILE__).'templates/vue/dist';

        wp_register_script( // the app build script generated by Webpack.
            'tapestry_d3_vue',
            $vueUrl.'/build.js?v='.$TAPESTRY_VERSION_NUMBER,
            [],
            null,
            true
        );

        // make custom data available to the Vue app with wp_localize_script.
        global $post;
        global $wp_roles;

        $kaltura_partner_id = null;
        $kaltura_unique_configuration = null;
        if(LOAD_KALTURA) {
            $kaltura_partner_id = KALTURA_PARTNER_ID;
            $kaltura_unique_configuration = KALTURA_UNIQUE_CONFIG;
        }
        
        wp_localize_script(
            'tapestry_d3_vue', // vue script handle defined in wp_register_script.
            'wpData', // javascript object that will made availabe to Vue.
            [ // wordpress data to be made available to the Vue app in 'wpData'
                'directory_uri' => plugin_dir_url(__FILE__).'templates/vue/dist', // child theme directory path.
                'vue_uri' => $vueUrl, // path to vue
                'rest_url' => untrailingslashit(esc_url_raw(rest_url())), // URL to the REST endpoint.
                'wpUrl' => get_bloginfo('url'),
                'app_path' => $post->post_name, // page where the custom page template is loaded.
                'post_categories' => get_terms([
                    'taxonomy' => 'category', // default post categories.
                    'hide_empty' => true,
                    'fields' => 'names',
                ]),
                'nonce' => wp_create_nonce('wp_rest'),
                'wpUserId' => apply_filters('determine_current_user', false),
                'adminAjaxUrl' => admin_url('admin-ajax.php'),
                'file_upload_nonce' => wp_create_nonce('media-form'),
                'upload_url' => admin_url('async-upload.php'),
                'roles' => $wp_roles->get_names(),
                'wpCanEditTapestry' => current_user_can('edit_post', get_the_ID()),
                'currentUser' => wp_get_current_user(),
                'uploadDirArray' => wp_upload_dir(),
                'kaltura' => array(
                    "partnerId" => $kaltura_partner_id,
                    "uniqueConfiguration" => $kaltura_unique_configuration,
                ),
            ]
        );

        // enqueue the Vue app script with localized data.
        wp_enqueue_script('tapestry_d3_vue');
    }
}

function tapestry_enqueue_libraries()
{
    global $post;
    global $TAPESTRY_VERSION_NUMBER;
    if ('tapestry' == get_post_type($post) && !post_password_required($post)) {
        $LIBS_FOLDER_URL = plugin_dir_url(__FILE__).'templates/libs/';

        // CSS
        wp_enqueue_style('font-awesome-5', 'https://use.fontawesome.com/releases/v5.5.0/css/all.css', [], null);
        wp_enqueue_style('tapestry-css', plugin_dir_url(__FILE__).'templates/tapestry.css', [], $TAPESTRY_VERSION_NUMBER);

        // JS
        wp_enqueue_script('heartbeat');
        wp_enqueue_script('es2015-test', $LIBS_FOLDER_URL.'es2015-test.js');
    }
}

function tapestry_add_style_attributes($html, $handle)
{
    global $post;
    if ('tapestry' == get_post_type($post) && !post_password_required($post)) {
        if ('font-awesome-5' === $handle) {
            return str_replace("media='all'", "media='all' integrity='sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU' crossorigin='anonymous'", $html);
        }
    }

    return $html;
}

/**
 * Filter the template for Tapestry post.
 */
function load_tapestry_template($singleTemplate)
{
    global $post;
    if ('tapestry' === $post->post_type) {
        $singleTemplate = dirname(__FILE__).'/templates/single-tapestry.php';
    }

    return $singleTemplate;
}
add_filter('single_template', 'load_tapestry_template');

function create_new_tapestry()
{
    if (!current_user_can('edit_posts')) {
        return "";
    }

    $prefix = get_rest_url(null, 'tapestry-tool/v1');

    return "
        <button onclick='promptAddNewTapestry()'>
            Add Tapestry
        </button>
        <script type='text/javascript'>
            function promptAddNewTapestry() {
                let name = prompt(`Enter a name`);
                if (name !== null) {
                    var apiUrl = '{$prefix}';
                    let payload = {};
                    payload[`nodes`] = [];
                    payload[`groups`] = [];
                    payload[`links`] = [];
                    payload[`title`] = name;
                    return new Promise((fulfill, reject) => {
                        let xhr = new XMLHttpRequest();
                        xhr.open('POST', apiUrl + '/tapestries');
                        xhr.setRequestHeader(`Content-Type`, `application/json;charset=UTF-8`);
                        xhr.setRequestHeader(`X-WP-Nonce`, `".wp_create_nonce('wp_rest')."`);
                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                fulfill(xhr.response);
                            } else {
                                reject({
                                    status: xhr.status,
                                    statusText: xhr.statusText
                                });
                            }
                        };
                        xhr.onerror = () => {
                            reject({
                                status: xhr.status,
                                statusText: xhr.statusText
                            });
                        };
                        xhr.send(JSON.stringify(payload));
                    }).then(data => {
                        let res = JSON.parse(data);
                        window.location.href = res.settings.permalink;
                    }).catch(err => {
                        console.log(err);
                        alert(`Error occured while creating tapestry, please try again`);
                    })
                }
            }
        </script>
    ";
}

add_shortcode('new_tapestry_button', 'create_new_tapestry');

function replace_special_apostrophe($str)
{
    return str_replace('’', "'", $str);
}

$quote_style = 'ENT_QUOTES';
add_filter('rest_prepare_post', 'prefix_title_entity_decode');
function prefix_title_entity_decode($response)
{
    $data = $response->get_data();
    $data['title']['rendered'] = wp_specialchars_decode(html_entity_decode($data['title']['rendered']), $quote_style);
    $data['title']['rendered'] = replace_special_apostrophe($data['title']['rendered']);
    $data['content']['rendered'] = wp_specialchars_decode(html_entity_decode($data['content']['rendered']), $quote_style);
    $data['content']['rendered'] = replace_special_apostrophe($data['content']['rendered']);
    $response->set_data($data);

    return $response;
}

// Analytics

register_activation_hook(__FILE__, 'create_tapestry_analytics_schema');
function create_tapestry_analytics_schema()
{
    $analytics = new TapestryAnalytics();
    $analytics->createSchema();
}

add_action('wp_ajax_nopriv_tapestry_tool_log_event', 'tapestry_tool_log_event');
add_action('wp_ajax_tapestry_tool_log_event', 'tapestry_tool_log_event');
function tapestry_tool_log_event()
{
    $analytics = new TapestryAnalytics();
    $analytics->log($_POST);

    wp_die();
}


// Kaltura
add_action('upload_videos_to_kalture', 'upload_videos_to_kalture');
function upload_videos_to_kaltura()
{
    if (LOAD_KALTURA) {
        $upload_folder = wp_upload_dir();
    
        $files_in_upload_folder = scandir($upload_folder);
        $current_date = date('Y/m/d');

        /*
            1. Uplaod files from server to kaltura
            2. Note local server urls to replace with kaltura urls
         */

        $videos_in_upload_folder = array_filter(
            $files_in_upload_folder,
            function ($file) {
            return strpos($file, ".mp4");
        }
        );

        $video_links = array();

        $kalturaApi = new KalturaApi();

        foreach ($videos_in_upload_folder as $key => $value) {
            $file_obj = new StdClass();
        
            $file_obj->file_path = $upload_folder."/".$value;
            $file_obj->name = $value;
            try {
                $result = $kalturaApi->uploadKalturaVideo($file_obj, $current_date);

                $video_links[$value] = $result;
            }
            catch(Exception $e){
                error_log("Unable to upload video - ".$file_obj->file_path." to kaltura, ".$e);
            }
            
        }

        // Replace all local server urls with kaltura urls
        $tapestries = get_posts(['post_type' => 'tapestry',]);
        $video_nodes = array();

        foreach ($tapestries as $value) {
            $tapestry = new Tapestry($value->ID);

            foreach ($tapestry->getNodeIds() as $node_id) {
                $node = new TapestryNode($value->ID, $node_id);
                $nodeMeta = $node->getMeta();

                if ($nodeMeta->mediaType == "video") {
                    if (strpos($node->getTypeData()->mediaURL, "/wp-content/uploads/")) {
                        array_push($video_nodes, $node);
                    }
                }
            }
        }

        foreach ($video_nodes as $node) {
            foreach ($video_links as $original_link => $kaltura_data) {
                $node_type_data = $node->getTypeData();

                if (strpos($node_type_data->mediaURL, $original_link)) {
                    $typeData = $node->getTypeData();
                    $typeData->mediaURL = $kaltura_data->dataUrl."?.mp4";
                    $typeData->kalturaData = array(
                        "id" => $kaltura_data->id,
                        "partnerId" => $kaltura_data->partnerId,
                    );

                    $node->set($typeData);
                    $node->save();

                    wp_delete_file($upload_folder."/".$original_link);
                    break;
                }
            }
        }

    }
}
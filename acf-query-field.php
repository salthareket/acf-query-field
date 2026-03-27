<?php
/**
 * Plugin Name: ACF Query Field
 * Description: Custom ACF field for creating dynamic queries.
 * Version: 1.2.1
 * Author: Tolga Koçak
 * Requires Plugins: advanced-custom-fields
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Bağımlılık kontrolü
add_action( 'admin_init', function() {
    $missing = [];
    if ( ! class_exists( 'ACF' ) && ! class_exists( 'acf' ) ) {
        $missing[] = 'Advanced Custom Fields (ACF)';
    }
    if ( ! class_exists( 'Timber' ) ) {
        $missing[] = 'Timber';
    }
    if ( ! empty( $missing ) ) {
        add_action( 'admin_notices', function() use ( $missing ) {
            printf(
                '<div class="notice notice-error"><p><strong>ACF Query Field:</strong> %s eklentileri yüklü ve aktif olmalıdır.</p></div>',
                implode( ', ', $missing )
            );
        });
    }
});

class ACF_Query_Field {

    public function __construct() {
        add_action('acf/include_field_types', array($this, 'include_field'));
        add_action('wp_ajax_acf_query_field_ajax', array($this, 'acf_query_field_ajax'));
        add_action('wp_ajax_nopriv_acf_query_field_ajax', array($this, 'acf_query_field_ajax'));

        add_action('wp_ajax_acf_query_field_author_ajax', array($this, 'acf_query_field_author_ajax'));
        add_action('wp_ajax_nopriv_acf_query_field_author_ajax', array($this, 'acf_query_field_author_ajax'));

        add_action('wp_ajax_acf_query_field_post_ajax', array($this, 'acf_query_field_post_ajax'));
        add_action('wp_ajax_nopriv_acf_query_field_post_ajax', array($this, 'acf_query_field_post_ajax'));

        add_action('wp_ajax_acf_query_field_fetch_meta_names', array($this, 'acf_query_field_fetch_meta_names'));
        add_action('wp_ajax_nopriv_acf_query_field_fetch_meta_names', array($this, 'acf_query_field_fetch_meta_names'));

        add_action('wp_ajax_acf_query_field_check_template_path', array($this, 'acf_query_field_check_template_path'));
        add_action('wp_ajax_nopriv_acf_query_field_check_template_path', array($this, 'acf_query_field_check_template_path'));

        add_action('wp_ajax_get_acf_query', array($this, 'get_acf_query'));
        add_action('wp_ajax_nopriv_get_acf_query', array($this, 'get_acf_query'));
    }

    public function include_field( $version = false ) {
        if ( ! $version ) $version = 4;
        include_once('acf-query-field-class.php');
    }

    public function acf_query_field_ajax() {
        $response = array(
            "error"   => false,
            "message" => "",
            "html"    => "",
            "data"    => ""
        );
        $count   = 0;
        $options = "";
        $ids     = array();
        $type    = isset($_POST["type"])     ? sanitize_text_field($_POST["type"])     : "";
        $value   = isset($_POST["value"])    ? sanitize_text_field($_POST["value"])    : "";
        $selected = isset($_POST["selected"]) ? $_POST["selected"] : ""; // array veya string olabilir

        switch ($type) {

            case 'post_type':
                if( empty($value)){
                    $taxonomies = get_taxonomies(array(), 'objects' );
                }else{
                    $taxonomies = get_object_taxonomies( array( 'post_type' => $value ), 'objects' );
                }
                if($taxonomies){
                    $taxonomies = array_filter($taxonomies, function($taxonomy) {
                        return $taxonomy->public;
                    });
                    if($taxonomies){
                        $options .= "<option value='0' ".(empty($selected)?"":"selected").">All Taxonomies</option>"; 
                        foreach( $taxonomies as $taxonomy ){
                            $ids[] = $taxonomy;
                            $options .= "<option value='".esc_attr($taxonomy->name)."' ".($selected == $taxonomy->name?"selected":"").">".esc_html($taxonomy->label)."</option>";        
                        }
                    }              
                }  
            break;

            case 'taxonomy':
                $selected = !is_array($selected) ? json_decode(stripslashes($selected), true) : $selected;
                $selected = empty($selected) ? array() : array_map('intval', (array)$selected);
                $terms = get_terms( array( 'taxonomy' => $value, 'hide_empty' => false ) );
                if($terms){
                    foreach( $terms as $term ){
                        $options .= "<option value='".esc_attr($term->term_id)."' ".(in_array($term->term_id, $selected)?"selected":"").">".esc_html($term->name)."</option>";
                        $ids[] = $term->term_id;          
                    }                
                }
            break;
        }

        $response["html"] = $options;
        $values = array();
        $values["selected"] = $selected;
        $values["ids"]      = $ids;
        $values["count"]    = $count;
        $response["data"]   = $values;
        echo json_encode($response);
        die;
    }

    public function acf_query_field_author_ajax() {
        global $wpdb;

        $search = sanitize_text_field($_GET['search']);
        $role = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : ''; // Role parametresi
        $selected = isset($_GET['selected']) ? intval($_GET['selected']) : 0; // Seçilen ID
        $page = intval($_GET['page']);
        $offset = ($page - 1) * 10; // Sayfalamayı desteklemek için

        // Role parametresi varsa usermeta ile join yap
        if (!empty($role)) {
            $query = "
                SELECT u.ID, u.display_name 
                FROM $wpdb->users u 
                INNER JOIN $wpdb->usermeta um ON u.ID = um.user_id
                WHERE u.display_name LIKE %s 
                AND um.meta_key = '{$wpdb->prefix}capabilities' 
                AND um.meta_value LIKE %s 
            ";

            $params = [
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($role) . '%'
            ];

            // Eğer selected doluysa sorguya ekle
            if ($selected) {
                $query .= " AND u.ID != %d";
                $params[] = $selected;
            }

            $query .= " ORDER BY u.display_name LIMIT %d, 10";
            $params[] = $offset;

            $results = $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            $query = "
                SELECT ID, display_name 
                FROM $wpdb->users 
                WHERE display_name LIKE %s 
            ";

            $params = [
                '%' . $wpdb->esc_like($search) . '%'
            ];

            // Eğer selected doluysa sorguya ekle
            if ($selected) {
                $query .= " AND ID != %d";
                $params[] = $selected;
            }

            $query .= " ORDER BY display_name LIMIT %d, 10";
            $params[] = $offset;

            $results = $wpdb->get_results($wpdb->prepare($query, ...$params));
        }

        $data = array();

        foreach ($results as $user) {
            $data[] = array(
                'id' => $user->ID,
                'text' => $user->display_name
            );
        }

        wp_send_json($data);
    }
    public function acf_query_field_post_ajax() {
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        $taxonomy  = isset($_GET['taxonomy'])  ? sanitize_text_field($_GET['taxonomy'])  : '';
        $terms     = isset($_GET['terms'])     ? array_map('intval', (array)$_GET['terms']) : [];
        $selected  = isset($_GET['selected'])  ? intval($_GET['selected']) : 0;
        $page      = isset($_GET['page'])      ? intval($_GET['page'])     : 1;
        $offset    = ($page - 1) * 10;

        // Polylang dil filtresi — sadece yüklüyse uygula
        $lang = (function_exists('pll_current_language')) ? pll_current_language() : '';

        $args = [
            'post_type'      => $post_type ?: 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'paged'          => $page,
            'offset'         => $offset,
        ];

        // Eğer taxonomy varsa
        if ($taxonomy) {
            $args['tax_query'] = [
                'relation' => 'AND', // Term koşullarının ilişkisi
            ];

            if (!empty($terms)) {
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $terms,
                    'operator' => 'IN', // Termlerin herhangi biri eşleşmeli
                ];
            } else {
                // Terms boşsa, taxonomy'nin tüm terimlerini içeren postları getir
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => get_terms(['taxonomy' => $taxonomy, 'fields' => 'slugs']), // Taxonomy'nin tüm term slug'larını al
                    'operator' => 'IN',
                ];
            }
        }

        // Eğer seçilen post varsa, onu hariç tut
        if ($selected) {
            $args['post__not_in'] = [$selected];
        }

        // WP_Query ile verileri çek
        $query = new WP_Query($args);

        $data = [];
        foreach ($query->posts as $post) {
            $data[] = [
                'id'   => $post->ID,       // ID döndür
                'text' => $post->post_title // post_title döndür
            ];
        }
        wp_send_json([
            'results' => $data,
            'total_count' => $query->found_posts // Toplam post sayısı
        ]);
    }

    public function acf_query_field_fetch_meta_names() {
        global $wpdb;

        $type = sanitize_text_field($_GET['type']);
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';
        $terms = isset($_GET['terms']) ? array_map('intval', $_GET['terms']) : [];
        $search = sanitize_text_field($_GET['q']);
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $results = array();
        $total_meta_names = 0;

        switch ($type) {
            case 'post':
                $meta_names = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE meta_key LIKE %s LIMIT %d, %d",
                    '%' . $wpdb->esc_like($search) . '%', $offset, $limit
                ));
                $total_meta_names = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT meta_key) FROM $wpdb->postmeta WHERE meta_key LIKE %s",
                    '%' . $wpdb->esc_like($search) . '%'
                ));
                break;

            case 'taxonomy':
                if (!empty($taxonomy)) {
                    $meta_names = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM $wpdb->termmeta WHERE term_id IN (
                            SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s
                        ) AND meta_key LIKE %s LIMIT %d, %d",
                        $taxonomy, '%' . $wpdb->esc_like($search) . '%', $offset, $limit
                    ));
                    $total_meta_names = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT meta_key) FROM $wpdb->termmeta WHERE term_id IN (
                            SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s
                        ) AND meta_key LIKE %s",
                        $taxonomy, '%' . $wpdb->esc_like($search) . '%'
                    ));
                } else {
                    $meta_names = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM $wpdb->termmeta WHERE meta_key LIKE %s LIMIT %d, %d",
                        '%' . $wpdb->esc_like($search) . '%', $offset, $limit
                    ));
                    $total_meta_names = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT meta_key) FROM $wpdb->termmeta WHERE meta_key LIKE %s",
                        '%' . $wpdb->esc_like($search) . '%'
                    ));
                }
                break;

            case 'user':
                $meta_names = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT meta_key FROM $wpdb->usermeta WHERE meta_key LIKE %s LIMIT %d, %d",
                    '%' . $wpdb->esc_like($search) . '%', $offset, $limit
                ));
                $total_meta_names = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT meta_key) FROM $wpdb->usermeta WHERE meta_key LIKE %s",
                    '%' . $wpdb->esc_like($search) . '%'
                ));
                break;

            case 'comment':
                $meta_names = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT meta_key FROM $wpdb->commentmeta WHERE meta_key LIKE %s LIMIT %d, %d",
                    '%' . $wpdb->esc_like($search) . '%', $offset, $limit
                ));
                $total_meta_names = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT meta_key) FROM $wpdb->commentmeta WHERE meta_key LIKE %s",
                    '%' . $wpdb->esc_like($search) . '%'
                ));
                break;

            default:
                wp_send_json_error('Invalid type specified');
                return;
        }

        foreach ($meta_names as $meta_name) {
            $results[] = array('id' => $meta_name->meta_key, 'text' => $meta_name->meta_key);
        }

        wp_send_json([
            'results' => $results,
            'total_count' => $total_meta_names // Toplam post sayısı
        ]);
    }

    public function acf_query_field_check_template_path() {
        $type = sanitize_text_field($_POST['type']);
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $post_type = $post_type == "0" ? "": $post_type;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
        $roles = isset($_POST['roles']) ? sanitize_text_field($_POST['roles']) : '';
        $comment_type = isset($_POST['comment_type']) ? sanitize_text_field($_POST['comment_type']) : '';

        $args = array(
            'path' => [],
            'type' => $type,
            'post_type' => $post_type,
            'taxonomy' => $taxonomy,
            'role' => $roles,
            'comment_type' => $comment_type
        );

        $default_template = [];//false;

        $dirs = Timber::$dirname;
        
        switch ($args['type']) {
            case 'post':
                if($args['post_type']){
                    $args['path'][] = "{$args['post_type']}/tease.twig";
                    $args['path'][] = "{$args['post_type']}/tease-*.twig";
                }
                $args['path'][] = "tease.twig";
                break;

            case 'taxonomy':
                if($args['taxonomy']){
                    $args['path'][] = "{$args['taxonomy']}/tease.twig";
                    $args['path'][] = "{$args['taxonomy']}/tease-*.twig";
                }
                $args['path'][] = "tease.twig";
                break;

            case 'user':
                if ($args['role']) {
                    $args['path'][] = "user/tease-*.twig";
                    $args['path'][] = "user/tease-{$args['role']}.twig";
                    $args['path'][] = "user/tease-{$args['role']}-*.twig";
                }
                $args['path'][] = "user/tease.twig";
                break;

            case 'comment':
                if ($args['comment_type']) {
                    $args['path'][] = "comment/tease-*.twig";
                    $args['path'][] = "comment/tease-{$args['comment_type']}.twig";
                    $args['path'][] = "comment/tease-{$args['comment_type']}-*.twig";
                }
                $args['path'][] = "comment/tease.twig";
                break;
        }
        /*foreach ($dirs as $dir) {
            //if(!$default_template){
                foreach ($args['path'] as $path) {
                    $full_path = get_template_directory() . "/$dir/{$path}";
                    if (file_exists($full_path)) {
                        $default_template[] = "$dir/{$path}";
                        //continue;
                    }
                }                
            //}
        }*/
        foreach ($dirs as $dir) {
            foreach ($args['path'] as $path) {
                if (strpos($path, '*') !== false) {
                    // Eğer path içerisinde '*' varsa glob ile dosya listesi al
                    $full_glob_path = get_template_directory() . "/$dir/{$path}";
                    $glob_files = glob($full_glob_path); // Bu pattern'e uyan tüm dosyalar
                    if ($glob_files) {
                        foreach ($glob_files as $file) {
                            $relative_path = str_replace(get_template_directory() . "/", '', $file);
                            $default_template[] = $relative_path;
                        }
                    }
                } else {
                    // Eğer '*' yoksa, doğrudan file_exists kontrolü yap
                    $full_path = get_template_directory() . "/$dir/{$path}";
                    if (file_exists($full_path)) {
                        $default_template[] = "$dir/{$path}";
                    }
                }
            }
        }
        wp_send_json([
            'template' => $default_template
        ]);
    }

}
new ACF_Query_Field();
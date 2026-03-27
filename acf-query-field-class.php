<?php

if( ! defined( 'ABSPATH' ))  exit;

class ACF_Field_Query_Field extends \acf_field {

    public $acf_query_field_id;
    public $settings;
    public $cache_enabled = false; // ✨ YENİ: Önbellek durumunu tutacak özellik

    const CACHE_PREFIX = 'acf_query_result_'; 
    const QUERY_PARAM_PREFIX = 'acf_query_params_';

    public function __construct( $settings ) {
        //add_filter('acf/validate_value', [$this, 'validate_value'], 10, 4);
        $this->name = 'query_field';
        $this->label = __('Query Field', 'acf-query-field');
        $this->category = 'relational';
        $this->defaults = array(
            'required' => 1 // Varsayılan olarak `required` özelliğini devre dışı bırak
        );
        $this->settings = $settings;

        $this->acf_query_field_id = 0;

        $default_dirnames = Timber::$dirname;
        if($default_dirnames){
            if(!is_array($default_dirnames)){
                $default_dirnames = [$default_dirnames];
            }
            //$template_dirnames = array_merge($default_dirnames, [plugin_dir_path(__FILE__) . '\templates']);
            $template_dirnames = array_merge($default_dirnames, ["../../plugins/acf-query-field/templates"]);
            //array_unshift( Timber::$dirname, "../../plugins/acf-query-field/templates");//plugin_dir_path(__FILE__) . 'templates' );            
        }else{
            $template_dirnames = [];
        }

        Timber::$dirname = $template_dirnames;

        //print_r($template_dirnames);

        parent::__construct();
    }
    
    private function get_default_lang_label($id, $type = 'post') {
        // Polylang yoksa hiç uğraşma
        if (!function_exists('pll_default_language') || !function_exists('pll_get_post')) return '';

        $default_lang = pll_default_language();
        $current_lang = function_exists('pll_current_language') ? pll_current_language() : $default_lang;

        // Eğer zaten varsayılan dildeysek paranteze gerek yok
        if ($current_lang === $default_lang) return '';

        global $wpdb;
        $label = '';
        $tr_id = 0;

        // 1. ADIM: Sadece ID eşleşmesini al (Bu güvenlidir, SQL tetiklemez)
        if ($type === 'post') {
            $tr_id = pll_get_post($id, $default_lang);
        } elseif ($type === 'term') {
            $tr_id = pll_get_term($id, $default_lang);
        }

        // 2. ADIM: Eğer TR karşılığı varsa, başlığı direkt SQL ile çek
        if ($tr_id && $tr_id != $id) {
            if ($type === 'post') {
                // wp_posts tablosundan direkt SELECT (Polylang filtreleri bypass edilir)
                $label = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", 
                    $tr_id
                ));
            } elseif ($type === 'term') {
                // wp_terms tablosundan direkt SELECT
                $label = $wpdb->get_var($wpdb->prepare(
                    "SELECT name FROM {$wpdb->terms} WHERE term_id = %d", 
                    $tr_id
                ));
            }
        }

        if (empty($label)){
            $label = $id;
        }

        // Görsel olarak ayırmak için gri parantez içinde döndür
        return ' <span style="color:#999 !important; font-weight:normal !important; font-size:11px !important; font-style: italic;">(' . esc_html($label) . ')</span>';
    }

    public function get_sticky_supported_post_types() {
        $post_types = get_post_types(array('public' => true), 'names');
        return array_filter($post_types, function($post_type) {
            return post_type_supports($post_type, 'sticky');
        });
    }

    public function get_vars($value, $query){
        $vars = [];
        if($query){
            $vars["type"] = $value["type"];
            switch($value["type"]){  
                case "post":
                   $vars["posts_per_page"] = $query["posts_per_page"];
                break;
                case "taxonomy":
                    $vars["number"] = $query["number"];
                break;
                case "user":
                    $vars["number"] = $query["number"];
                break;
                case "comment":
                    $vars["number"] = $query["number"];
                break;
            }
            $vars["orderby"] = $query["orderby"];
            $vars["order"] = $query["order"];
        }
        $vars["heading"] = $value["heading"];
        $vars["max_posts"] = $value["max_posts"];
        return $vars;
    }

    public function render_repeater_row($field, $row = array()) {
        ob_start();
        $selected_compare = isset($row['compare']) ? $row['compare'] : '';
        $selected_key = isset($row['key'])?$row['key']: "";
        ?>
        <div class="acf-repeater-row">
            <div class="acf-fields --left">
                <div class="acf-field acf-field-select" data-name="key">
                    <select name="<?php echo esc_attr($field['name']) ?>[meta][<?php echo $row['index']; ?>][key]" class="meta-name" data-val="<?php echo esc_attr($selected_key) ?>">
                        <?php if (!empty($selected_key)) : ?>
                            <option value="<?php echo esc_attr($selected_key); ?>" selected>
                                <?php echo esc_html($selected_key); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="acf-field acf-field-select" data-name="compare">
                    <select name="<?php echo esc_attr($field['name']) ?>[meta][<?php echo $row['index']; ?>][compare]" class="compare">
                        <option value="=" <?php selected($selected_compare, '='); ?>>Equals</option>
                        <option value="!=" <?php selected($selected_compare, '!='); ?>>Not Equals</option>
                        <option value=">" <?php selected($selected_compare, '>'); ?>>Greater Than</option>
                        <option value="<" <?php selected($selected_compare, '<'); ?>>Less Than</option>
                        <option value="LIKE" <?php selected($selected_compare, 'LIKE'); ?>>Like</option>
                        <option value="NOT LIKE" <?php selected($selected_compare, 'NOT LIKE'); ?>>Not Like</option>
                        <option value="IN" <?php selected($selected_compare, 'IN'); ?>>In</option>
                        <option value="NOT IN" <?php selected($selected_compare, 'NOT IN'); ?>>Not In</option>
                    </select>
                </div>
                <div class="acf-field acf-field-text" data-name="value">
                    <input type="text" class="meta_value" name="<?php echo esc_attr($field['name']) ?>[meta][<?php echo $row['index']; ?>][value]" value="<?php echo esc_attr($row['value'] ?? ''); ?>" />
                </div>
                <button type="button" class="button remove-row">Remove</button>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }

    public function acf_query_field_pagination_defaults(){
        $post_pagination = get_field("post_pagination", "options");//get_option("options_post_pagination");
        if(!empty($post_pagination) && is_array($post_pagination)){
            $post_pagination_tmp = [];
            foreach ($post_pagination as $item) {
                $post_type = $item["post_type"];
                $posts_per_page = -1;
                if($item["paged"]){
                    $posts_per_page = intval($item["catalog_rows"]) * intval($item["catalog_columns"]);
                }else{
                    $item["catalog_rows"] = $item["catalog_columns"] = 1;
                }
                $item["posts_per_page"] = $posts_per_page;
                unset($item["post_type"]);
                $post_pagination_tmp[$post_type] = $item;
            }
            $post_pagination = $post_pagination_tmp;
            unset($post_pagination_tmp);       
        }
        return $post_pagination;
    }

    function render_field_settings( $field ) {
        acf_render_field_setting($field, array(
            'label'         => __('Return Type'),
            'instructions'  => __('Choose the return type for this field.'),
            'type'          => 'radio',
            'name'          => 'return_type',
            'choices'       => array(
                'wp_query'   => __('WP Query'),
                'sql_query'   => __('SQL Query'),
                'result'   => __('Result'),
                'render'  => __('Render'),
            ),
            'layout'        => 'horizontal', // Opsiyonel, radio buttonları yatay şekilde dizmek için.
        ));
    }

    function render_field( $field ) {
        // Varsayılan değerler atama
        //error_log("render_field");
        //error_log(json_encode($field["value"]));
        $field['value'] = wp_parse_args($field['value'], array(
            'type' => 'post',
            'post_type' => '',
            'taxonomy' => '',
            'taxonomy_post_type' => '',
            'terms' => '',
            'comment_post_type' => '',
            'comment_taxonomy' => '',
            'comment_terms' => '',
            'roles' => '',
            'roles_comment' => '',
            'orderby' => '',
            'order' => 'asc',
            'meta' => [],
            'comment_type' => "",
            'rating' => [],
            "post" => "",
            "post_comment" => "",
            'paged' => false,
            'paged_url' => false,
            'posts_per_page' => 10,
            'max_posts' => "",
            'template' => "",
            'template_default' => false,
            'template_default_path' => "",
            "heading" => "h3",
            "load_type" => "button",
            "preload" => false,
            "slider" => 0,
            "acf_query_field_id" => empty($field['value']['acf_query_field_id'])?unique_code(16):$field['value']['acf_query_field_id'],
            "sticky" => 0,
            "sticky_ignore" => 0,
            "button_class" => "btn-primary",
            "button_size" => "md",
            "button_outline" => false,
            "button_full_width" => false,
            "button_position" => "center",
        ));

        if($field["return_type"] == "result"){
            $field['value']["paged"] = false;
        }

        //print_r($field['value']);

        if(!empty($field['value']['acf_query_field_id'])){
            $this->acf_query_field_id = $field['value']['acf_query_field_id'];
        }

        $pagination_defaults = $this->acf_query_field_pagination_defaults();
        ?>

            <input type="hidden" name="return_type" value="<?php echo $field["return_type"];?>"/>

            <h3 class="acf-field-header">Query - (<?php echo $field["return_type"];?>)</h3>
        
            <div class="acf-query-type-fields acf-fields" data-type="type">
                <div class="acf-field acf-field-select" data-width="50" data-name="type">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[type]"><?php _e('Type', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['type'])?$field['value']['type']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[type]" id="<?php echo esc_attr($field['name']) ?>_type" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="post" <?php selected($selected , 'post'); ?>>Post</option>
                            <option value="taxonomy" <?php selected($selected , 'taxonomy'); ?>>Taxonomy</option>
                            <option value="user" <?php selected($selected , 'user'); ?>>User</option>
                            <option value="comment" <?php selected($selected , 'comment'); ?>>Comment</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="acf-query-post-fields acf-fields <?php echo $field['value']['type'] == 'post' ? '' : 'd-none' ?>" data-type="post">

                <div class="acf-field acf-field-select" data-width="50" data-name="post_type">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[post_type]"><?php _e('Post Type', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['post_type'])?$field['value']['post_type']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[post_type]" id="<?php echo esc_attr($field['name']) ?>_post_type" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="0"><?php _e('All Posts', 'acf-query-field'); ?></option>
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            foreach ($post_types as $post_type) {
                                $default_label = $this->get_default_lang_label($post_type->name, 'post_type');
                                ?>
                                <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($selected, $post_type->name); ?>>
                                    <?php echo esc_html($post_type->label) . $default_label; ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="acf-field acf-field-select" data-width="50" data-name="taxonomy">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[taxonomy]"><?php _e('Taxonomy', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <select name="<?php echo esc_attr($field['name']) ?>[taxonomy_post_type]" id="<?php echo esc_attr($field['name']) ?>_taxonomy" data-val="<?php echo esc_attr($field['value']["taxonomy_post_type"]) ?>">
                            <option value="0"><?php _e('All Taxonomies', 'acf-query-field'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="acf-field acf-field-select" data-width="50" data-name="terms">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[terms_post_type]"><?php _e('Terms', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $terms_post_type = isset($field['value']['terms_post_type'])?$field['value']['terms_post_type']:[]; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[terms_post_type][]" id="<?php echo esc_attr($field['name']) ?>_terms_post_type" data-ui="1" data-multiple="1" data-placeholder="<?php echo esc_attr(_e('All Terms', 'acf-query-field')) ?>" data-allow_null="1" multiple data-val="<?php echo esc_attr(json_encode($terms_post_type)) ?>">
                        </select>
                    </div>
                </div>
            </div>

            <div class="acf-query-taxonomy-fields acf-fields <?php echo $field['value']['type'] == 'taxonomy' ? '' : 'd-none' ?>" data-type="taxonomy">

                <div class="acf-field acf-field-select" data-width="50" data-name="taxonomy">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[taxonomy]"><?php _e('Taxonomy', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['taxonomy'])?$field['value']['taxonomy']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[taxonomy]" id="<?php echo esc_attr($field['name']) ?>_taxonomy" data-val="<?php echo esc_attr($selected) ?>">
                            <?php
                            $taxonomies = get_taxonomies(array('public' => true), 'objects');
                            if($taxonomies){
                                ob_start();
                                ?>
                                <option value="0"><?php _e('All Taxonomies', 'acf-query-field'); ?></option>
                                <?php
                                foreach ($taxonomies as $taxonomy) {
                                    $default_label = $this->get_default_lang_label($taxonomy->name, 'taxonomy');
                                    ?>
                                    <option value="<?php echo esc_attr($taxonomy->name); ?>" <?php selected($selected, $taxonomy->name); ?>><?php echo esc_html($taxonomy->label) . $default_label; ?></option>
                                    <?php
                                }
                                $taxonomy_options_html = ob_get_clean();
                            } else {
                                $taxonomy_options_html = '';
                            }
                            ?>
                        </select>
                        <script>
                            var acf_query_field_taxonomies = <?php echo json_encode($taxonomy_options_html); ?>;
                        </script>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="terms">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[terms]"><?php _e('Terms', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <select name="<?php echo esc_attr($field['name']) ?>[terms][]" id="<?php echo esc_attr($field['name']) ?>_taxonomy_terms" data-ui="1" data-multiple="1" data-placeholder="<?php echo esc_attr(_e('All Terms', 'acf-query-field')) ?>" data-allow_null="1" multiple data-val="<?php echo esc_attr(json_encode($field['value']['terms'])) ?>">
                        </select>
                    </div>
                </div>
            </div>

            <div class="acf-query-user-fields acf-fields <?php echo $field['value']['type'] == 'user' ? '' : 'd-none' ?>" data-type="user">
                 <div class="acf-field acf-field-select" data-width="50" data-name="roles">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[roles]"><?php _e('Roles', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['roles'])?$field['value']['roles']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[roles]" id="<?php echo esc_attr($field['name']) ?>_roles" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="0"><?php _e('All Roles', 'acf-query-field'); ?></option>
                            <?php
                            global $wp_roles;
                            foreach ($wp_roles->roles as $role_name => $role_info) {
                                $default_label = $this->get_default_lang_label($role_name, 'role');
                                ?>
                                <option value="<?php echo esc_attr($role_name); ?>" <?php selected($selected, $role_name); ?>><?php echo esc_html($role_info['name']) . $default_label; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="acf-query-comment-fields acf-fields acf-fields-breakable <?php echo $field['value']['type'] == 'comment' ? '' : 'd-none' ?>" data-type="comment">

                <?php
                global $wpdb;
                $comment_types = $wpdb->get_col("SELECT DISTINCT comment_type FROM {$wpdb->comments} WHERE comment_type != ''");
                $default_comment_types = array('comment', 'pingback', 'trackback');
                $all_comment_types = array_unique(array_merge($comment_types, $default_comment_types));
                ?>
                <div class="acf-field acf-field-select" data-width="50" data-name="comment_type">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[comment_type]"><?php _e('Comment Type', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['comment_type'])?$field['value']['comment_type']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[comment_type]" id="<?php echo esc_attr($field['name']) ?>_comment_type" data-val="<?php echo esc_attr($selected) ?>">
                            <option value=""><?php _e('All Comment Types', 'acf-query-field'); ?></option>
                            <?php foreach ($all_comment_types as $type) : ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($selected, $type); ?>><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="comment_rating">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[rating]"><?php _e('Comment Rating', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['rating'])?$field['value']['rating']:[]; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[rating][]" id="<?php echo esc_attr($field['name']) ?>_comment_rating" data-ui="1" data-multiple="1" data-placeholder="<?php echo esc_attr(_e('All Ratings', 'acf-query-field')) ?>" data-allow_null="1" multiple data-val="<?php echo esc_attr(json_encode($selected)) ?>">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) : 
                            ?>
                                <option value="<?php echo esc_attr($i); ?>" <?php echo in_array($i, $selected) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($i); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="acf-fields-breaker"></div>

                <div class="acf-field acf-field-select" data-width="50" data-name="post_type">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[post_type_comment]"><?php _e('Post Type', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['post_type_comment'])?$field['value']['post_type_comment']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[post_type_comment]" id="<?php echo esc_attr($field['name']) ?>_post_type_comment" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="0"><?php _e('All Posts', 'acf-query-field'); ?></option>
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            foreach ($post_types as $post_type) {
                                $default_label = $this->get_default_lang_label($post_type->name, 'post_type'); 
                                ?>
                                <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($selected, $post_type->name); ?>><?php echo esc_html($post_type->label) . $default_label; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="acf-field acf-field-select" data-width="50" data-name="taxonomy">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[taxonomy_comment]"><?php _e('Taxonomy', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']["taxonomy_comment"])?$field['value']["taxonomy_comment"]:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[taxonomy_comment]" id="<?php echo esc_attr($field['name']) ?>_taxonomy_comment" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="0"><?php _e('All Taxonomies', 'acf-query-field'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="acf-field acf-field-select" data-width="50" data-name="terms">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[terms_comment]"><?php _e('Terms', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']["terms_comment"])?$field['value']["terms_comment"]:[]; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[terms_comment][]" id="<?php echo esc_attr($field['name']) ?>_terms_comment" data-ui="1" data-multiple="1" data-placeholder="<?php echo esc_attr(_e('All Terms', 'acf-query-field')) ?>" data-allow_null="1" multiple data-val="<?php echo esc_attr(json_encode($selected)) ?>">
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="post">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[post_comment]"><?php _e('Post', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['post_comment'])?$field['value']['post_comment']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[post_comment]" id="<?php echo esc_attr($field['name']) ?>_post_comment" data-placeholder="<?php _e('All Posts...', 'acf-query-field'); ?>" data-val="<?php echo esc_attr($selected) ?>" data-allow_null="1" data-ui="1">
                            <?php
                            if(!empty($selected)){
                                $post_info = get_post($selected);
                                if ($post_info && $post_info->post_type == $field['value']['post_type_comment']) {
                                    $post_title = $post_info->post_title;
                                    ?>
                                    <option value="<?php echo esc_attr($selected) ?>" selected><?php echo $post_title ?></option>
                                <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="acf-fields-breaker"></div>

                <div class="acf-field acf-field-select" data-width="50" data-name="roles_comment">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[roles_comment]"><?php _e('Roles', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['roles_comment'])?$field['value']['roles_comment']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[roles_comment]" id="<?php echo esc_attr($field['name']) ?>_roles_comment" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="0"><?php _e('All Roles', 'acf-query-field'); ?></option>
                            <?php
                            global $wp_roles;
                            foreach ($wp_roles->roles as $role_name => $role_info) {
                                $default_label = $this->get_default_lang_label($role_name, 'role'); 
                                ?>
                                <option value="<?php echo esc_attr($role_name); ?>" <?php selected($selected, $role_name); ?>><?php echo esc_html($role_info['name']) . $default_label; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-select <?php echo isset($field['value']['roles_comment']) && $field['value']['roles_comment']  ? '' : 'd-none' ?>" data-width="50" data-name="author_comment">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[author_comment]"><?php _e('Author', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['author_comment'])?$field['value']['author_comment']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[author_comment]" id="<?php echo esc_attr($field['name']) ?>_author_comment" data-placeholder="<?php _e('All authors...', 'acf-query-field'); ?>" data-val="<?php echo esc_attr($selected) ?>" data-allow_null="1" data-ui="1">
                            <?php
                            if(!empty($selected)){
                                $user_info = get_userdata($selected);
                                if ($user_info) {
                                    $username = $user_info->user_login; // Kullanıcının giriş adı
                                    $display_name = $user_info->display_name; // Kullanıcının görüntülenen adı
                                    ?>
                                    <option value="<?php echo esc_attr($selected) ?>" selected><?php echo $display_name ?></option>
                                <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="acf-query-order-fields acf-fields" data-type="order">
                
                <div class="acf-field acf-field-select" data-width="50" data-name="orderby">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[orderby]"><?php _e('Order By', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <select name="<?php echo esc_attr($field['name']) ?>[orderby]" id="<?php echo esc_attr($field['name']) ?>_orderby" data-val="<?php echo esc_attr($field['value']["orderby"]) ?>">
                            <!-- Orderby Seçenekleri Dinamik Olarak Doldurulacak -->
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="order" data-val="<?php echo esc_attr($field['value']["order"]) ?>">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[order]"><?php _e('Order', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <select name="<?php echo esc_attr($field['name']) ?>[order]" id="<?php echo esc_attr($field['name']) ?>_order">
                            <option value="asc" <?php selected($field['value']['order'], 'asc'); ?>>ASC</option>
                            <option value="desc" <?php selected($field['value']['order'], 'desc'); ?>>DESC</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="acf-query-meta-fields acf-fields" data-type="meta">

                <div class="acf-field acf-field-repeater" data-width="50" data-type="repeater" data-name="meta" data-val="" data-parent="<?php echo esc_attr($field['name']) ?>">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[meta]"><?php _e('Meta Query', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">

                        <div class="acf-repeater">
                            <div class="holder">
                            <?php
                            if (!empty($field['value']['meta'])) {
                                $index = 0;
                                foreach ($field['value']['meta'] as $row) {
                                    echo $this->render_repeater_row($field, array_merge($row, ['index' => $index]));
                                    $index++;
                                }
                            }
                            ?>
                            </div>
                            <div class="acfe-repeater-stylised-button">
                                <div class="acf-actions text-center">
                                    <a class="acf-button acf-repeater-add-row button" href="#" data-event="add-row">Add Meta</a>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Meta değerlerini JSON olarak hidden input'ta tut
                        // Flexible/repeater içinde nested name attribute'ları bozulabiliyor
                        // Bu hidden input her zaman doğru çalışır
                        $meta_json = !empty($field['value']['meta']) ? json_encode($field['value']['meta']) : '[]';
                        ?>
                        <input type="hidden" 
                               name="<?php echo esc_attr($field['name']); ?>[meta_json]" 
                               class="acf-query-meta-json" 
                               value="<?php echo esc_attr($meta_json); ?>" />

                    </div>
                </div>

                <div class="acf-query-post-meta-fields acf-fields  <?php echo $field['value']['type'] == 'post' ? '' : 'd-none' ?>" style="display:flex;margin-bottom:30px;" data-type="post_meta">

                    <div class="acf-field acf-field-true-false" data-name="has_thumbnail" data-type="true_false">
                        <div class="acf-label">
                            <label for="<?php echo esc_attr($field['name']) ?>[has_thumbnail]">Must have thumbnail image</label>
                        </div>
                        <div class="acf-input">
                            <div class="acf-true-false">
                                <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[has_thumbnail]" value="0"/>
                                <label>
                                    <?php $selected = isset($field['value']['has_thumbnail'])?$field['value']['has_thumbnail']:""; ?>
                                    <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[has_thumbnail]" name="<?php echo esc_attr($field['name']) ?>[has_thumbnail]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                    <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                        <span class="acf-switch-on">Yes</span>
                                        <span class="acf-switch-off">No</span>
                                        <div class="acf-switch-slider"></div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="acf-field acf-field-true-false" data-name="sticky" data-type="true_false">
                        <div class="acf-label">
                            <label for="<?php echo esc_attr($field['name']) ?>[sticky]">Only Sticky Posts</label>
                        </div>
                        <div class="acf-input">
                            <div class="acf-true-false">
                                <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[sticky]" value="0"/>
                                <label>
                                    <?php $selected = isset($field['value']['sticky'])?$field['value']['sticky']:""; ?>
                                    <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[sticky]" name="<?php echo esc_attr($field['name']) ?>[sticky]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                    <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                        <span class="acf-switch-on">Yes</span>
                                        <span class="acf-switch-off">No</span>
                                        <div class="acf-switch-slider"></div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="acf-field acf-field-true-false" data-name="sticky_ignore" data-type="true_false">
                        <div class="acf-label">
                            <label for="<?php echo esc_attr($field['name']) ?>[sticky_ignore]">Ignore Sticky Posts</label>
                        </div>
                        <div class="acf-input">
                            <div class="acf-true-false">
                                <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[sticky_ignore]" value="0"/>
                                <label>
                                    <?php $selected = isset($field['value']['sticky_ignore'])?$field['value']['sticky_ignore']:""; ?>
                                    <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[sticky_ignore]" name="<?php echo esc_attr($field['name']) ?>[sticky_ignore]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                    <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                        <span class="acf-switch-on">Yes</span>
                                        <span class="acf-switch-off">No</span>
                                        <div class="acf-switch-slider"></div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>


            </div>
            
            <?php 
            if($field["return_type"] != "result"){
            ?>
                <h3 class="acf-field-header">Pagination</h3>
            <?php
            }
            ?>
            

            <div class="acf-query-pagination-fields acf-fields" data-type="pagination">

                <div class="acf-field acf-field-true-false <?php echo $field["return_type"] == "result"?"d-none":"";?>" data-name="paged" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[paged]">Paged</label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[paged]" value="0"/>
                            <label>
                                <?php $selected = isset($field['value']['paged'])?$field['value']['paged']:""; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[paged]" name="<?php echo esc_attr($field['name']) ?>[paged]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-true-false <?php echo $field["return_type"] == "result"?"d-none":"";?> <?php echo $field["value"]["paged"]?"":"d-none";?>" data-name="paged_url" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[paged_url]">Paging in Url</label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[paged_url]" value="0"/>
                            <label>
                                <?php $selected = isset($field['value']['paged_url'])?$field['value']['paged_url']:""; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[paged_url]" name="<?php echo esc_attr($field['name']) ?>[paged_url]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <?php
                    $default_posts_per_page_exists = false;
                    $default_posts_per_page = 0;
                    if(isset($field["value"]["post_type"]) && isset($field["value"]["type"]) && isset($field["value"]["paged"])  && $pagination_defaults){
                        if(!empty($field["value"]["post_type"]) && !empty($field["value"]["type"]) && !empty($field["value"]["paged"])){
                           if($field["value"]["type"] == "post" && in_array($field["value"]["post_type"], array_keys($pagination_defaults))){
                               if($pagination_defaults[$field["value"]["post_type"]]["paged"] && $field["value"]["paged"]){
                                   $default_posts_per_page_exists = true;
                                   $default_posts_per_page = $pagination_defaults[$field["value"]["post_type"]]["posts_per_page"];
                               }
                           }
                        }
                    }
                ?>
                <div class="acf-field acf-field-true-false <?php echo $default_posts_per_page_exists?"":"d-none";?>" data-name="default_posts_per_page" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[default_posts_per_page]">Apply Defaults <div class="default_posts_per_page" style="display:block;color:blue;"><?php echo $field["value"]["post_type"]; ?> = <?php echo $default_posts_per_page; ?> items</div></label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[default_posts_per_page]" value="0"/>
                            <label>
                                <?php $selected = $default_posts_per_page; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[default_posts_per_page]" name="<?php echo esc_attr($field['name']) ?>[default_posts_per_page]" value="<?php echo esc_attr($default_posts_per_page) ?>" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected>0?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-select <?php echo $field["return_type"] == "result"?"d-none":"";?> <?php echo $field["value"]["paged"]?"":"d-none";?>" data-width="50" data-name="load_type">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[load_type]"><?php _e('Load Type', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['load_type'])?$field['value']['load_type']:""; ?>
                        <select class="data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[load_type]" id="<?php echo esc_attr($field['name']) ?>_load_type" data-val="<?php echo esc_attr($selected) ?>" <?php echo $default_posts_per_page_exists?"readonly":"";?>>
                            <option value="default" <?php selected($selected, "default"); ?>><?php _e('Default pagination', 'acf-query-field'); ?></option>
                            <option value="button"  <?php selected($selected, "button"); ?>><?php _e('Load by button', 'acf-query-field'); ?></option>
                            <option value="scroll"  <?php selected($selected, "scroll"); ?>><?php _e('Load on scroll', 'acf-query-field'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-text <?php echo $field["value"]["paged"]?"":"d-none";?>" data-name="posts_per_page">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[posts_per_page]">Posts per page</label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-input-wrap">
                            <input type="text" class="posts_per_page data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[posts_per_page]" value="<?php echo esc_attr($field['value']['posts_per_page'] ?? ''); ?>" data-val="<?php echo esc_attr($field['value']['posts_per_page'] ?? ''); ?>" <?php echo $default_posts_per_page_exists?"readonly":"";?>/>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-text" data-name="max_posts">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[max_posts]">Max posts</label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-input-wrap">
                            <?php $selected = isset($field['value']['max_posts'])?$field['value']['max_posts']:($field['value']['slider']?10:""); ?>
                            <input type="text" class="posts_per_page data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[max_posts]" value="<?php echo esc_attr($selected); ?>" data-val="<?php echo esc_attr($selected); ?>" placeholder="Leave blank for all"/>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-true-false <?php echo $field["return_type"] == "result"?"d-none":"";?>" data-name="preload" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[preload]" data-label="Start without ajax">
                           Start <?php echo $field['value']['preload']?"first page":"" ?> without ajax
                       </label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[preload]" value="0"/>
                            <label>
                                <?php $selected = isset($field['value']['preload'])?$field['value']['preload']:""; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[preload]" name="<?php echo esc_attr($field['name']) ?>[preload]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

            </div>

            <div class="acf-query-button-fields acf-fields <?php echo $field["return_type"] == "result"?"d-none":"";?> <?php echo $field["value"]["paged"] && $field['value']['load_type'] == 'button' ? '' : 'd-none' ?>" data-type="button">

                <div class="acf-field acf-field-select" data-width="50" data-name="button_class">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[button_class]"><?php _e('Button Type', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['button_class'])?$field['value']['button_class']:""; ?>
                        <?php 
                        $colors_list_file = get_template_directory() . '/theme/static/data/colors.json';
                        $colors = file_get_contents($colors_list_file);
                        $options = json_decode($colors, true);
                        ?>
                        <select class="data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[button_class]" id="<?php echo esc_attr($field['name']) ?>_button_class" data-val="<?php echo esc_attr($selected) ?>">
                            <?php 
                            foreach ($options as $option) {?>
                                <option value="<?php echo esc_attr($option) ?>" <?php selected($selected, $option); ?>><?php _e($option, 'acf-query-field'); ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="button_size">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[button_size]"><?php _e('Button Size', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['button_size'])?$field['value']['button_size']:""; ?>
                        <select class="data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[button_size]" id="<?php echo esc_attr($field['name']) ?>_button_size" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="sm" <?php selected($selected, "sm"); ?>><?php _e('Small', 'acf-query-field'); ?></option>
                            <option value="md"  <?php selected($selected, "md"); ?>><?php _e('Medium', 'acf-query-field'); ?></option>
                            <option value="lg"  <?php selected($selected, "lg"); ?>><?php _e('Large', 'acf-query-field'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-true-false" data-name="button_outline" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[button_outline]">
                           Button Outline
                       </label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[button_outline]" value="0"/>
                            <label>
                                <?php $selected = isset($field['value']['button_outline'])?$field['value']['button_outline']:""; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[button_outline]" name="<?php echo esc_attr($field['name']) ?>[button_outline]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-true-false" data-name="button_full_width" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[button_full_width]">
                           Button Full Width
                       </label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[button_full_width]" value="0"/>
                            <label>
                                <?php $selected = isset($field['value']['button_full_width'])?$field['value']['button_full_width']:""; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[button_full_width]" name="<?php echo esc_attr($field['name']) ?>[button_full_width]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="button_position">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[button_position]"><?php _e('Button Position', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['button_position'])?$field['value']['button_position']:""; ?>
                        <select class="data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[button_position]" id="<?php echo esc_attr($field['name']) ?>_button_position" data-val="<?php echo esc_attr($selected) ?>">
                            <option value="start" <?php selected($selected, "start"); ?>><?php _e('Left', 'acf-query-field'); ?></option>
                            <option value="center"  <?php selected($selected, "center"); ?>><?php _e('Center', 'acf-query-field'); ?></option>
                            <option value="end"  <?php selected($selected, "end"); ?>><?php _e('Right', 'acf-query-field'); ?></option>
                        </select>
                    </div>
                </div>

            </div>

            <?php 
            if($field["return_type"] == "render"){
            ?>
            <h3 class="acf-field-header">Templating</h3>
            <div class="acf-query-view-fields acf-fields" data-type="view">

                <div class="acf-field acf-field-select" data-width="50" data-name="template_default">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[template_default]">Use Default Templates</label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['template_default'])?$field['value']['template_default']:""; ?>
                        <input type="hidden" class="template_default_path" name="<?php echo esc_attr($field['name']) ?>[template_default_path]" value="<?php echo esc_attr($selected) ?>"/>
                        <select name="<?php echo esc_attr($field['name']) ?>[template_default]" id="<?php echo esc_attr($field['name']) ?>_template_default" data-val="<?php echo esc_attr($field['value']["template_default"]) ?>">
                            <option value="0"><?php _e('Choose a default template', 'acf-query-field'); ?></option>
                            <option value="<?php echo esc_attr($selected); ?>"><?php echo esc_html($selected); ?></option>
                        </select>
                    </div>
                </div>
     
                <?php
                $handle = get_stylesheet_directory() . '/theme/templates/_custom/';
                $templates = array();// scandir($handle);
                if ($handle = opendir($handle)) {
                    while (false !== ($entry = readdir($handle))) {
                        if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) === 'twig') {
                            $templates['theme/templates/_custom/' . $entry] = $entry;
                        }
                    }
                    closedir($handle);
                }
                ?>
                <?php $template_default_selected = isset($field['value']['template_default'])?$field['value']['template_default']:""; ?>
                <div class="acf-field acf-field-select <?php echo $template_default_selected || !$templates?"d-none":"" ?>" data-width="50" data-name="template">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[template]"><?php _e('Template', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['template'])?$field['value']['template']:""; ?>
                        <select name="<?php echo esc_attr($field['name']) ?>[template]" id="<?php echo esc_attr($field['name']) ?>_template" data-val="<?php echo esc_attr($field['value']["template"]) ?>">
                            <option value="0"><?php _e('Choose a custom template', 'acf-query-field'); ?></option>
                            <?php
                            if( is_array($templates) ) {
                                foreach( $templates as $key => $template ) {
                            ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected, $key); ?>><?php echo esc_html($template); ?></option>
                            <?php 
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="acf-field acf-field-true-false" data-name="slider" data-type="true_false">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[slider]">Slider View</label>
                    </div>
                    <div class="acf-input">
                        <div class="acf-true-false">
                            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[slider]" value="0"/>
                            <label>
                                <?php $selected = isset($field['value']['slider'])?$field['value']['slider']:""; ?>
                                <input type="checkbox" id="<?php echo esc_attr($field['name']) ?>[slider]" name="<?php echo esc_attr($field['name']) ?>[slider]" value="1" class="acf-switch-input" autocomplete="off" <?php checked($selected, 1); ?>/>
                                <div class="acf-switch <?php echo $selected?"-on":"" ?>">
                                    <span class="acf-switch-on">Yes</span>
                                    <span class="acf-switch-off">No</span>
                                    <div class="acf-switch-slider"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="acf-field acf-field-select" data-width="50" data-name="heading">
                    <div class="acf-label">
                        <label for="<?php echo esc_attr($field['name']) ?>[heading]"><?php _e('Tease Heading', 'acf-query-field'); ?></label>
                    </div>
                    <div class="acf-input">
                        <?php $selected = isset($field['value']['heading'])?$field['value']['heading']:""; ?>
                        <select class="data-val-inherit" name="<?php echo esc_attr($field['name']) ?>[heading]" id="<?php echo esc_attr($field['name']) ?>_heading" data-val="<?php echo esc_attr($selected) ?>">
                            <?php foreach(range(1, 6) as $number) { ?>
                            <option value="h<?php echo $number;?>" <?php selected($selected, "h".$number); ?>><?php _e("h".$number, 'acf-query-field'); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>
            <?php 
            }
            ?>

            <input type="hidden" name="<?php echo esc_attr($field['name']) ?>[acf_query_field_id]" value="<?php echo esc_attr($field['value']['acf_query_field_id'] ?? ''); ?>"/>

            <script>
                var acf_query_field_pagination_defaults = <?php echo json_encode($pagination_defaults); ?>;
            </script>
        
        <?php
    }

    function load_field($field){
        //error_log("load_field");
        return $field;
    }

    public function get_method(){
        echo "get_acf_query";
    }

    public function generate_wp_query($value){
        global $wpdb;
        $query = [];
        $meta_query = [];
        $tax_query = [];
        $vars = [];

        // Polylang aktifse mevcut dili query'ye ekle — 3x sonuç döndürmeyi engelle
        if ( function_exists('pll_current_language') ) {
            $lang = isset($_REQUEST['lang']) ? sanitize_text_field($_REQUEST['lang']) : pll_current_language();
            if ( $lang ) {
                $query['lang'] = $lang;
            }
        }
        switch($value["type"]){
            case "post" :
                if($value["post_type"] != "0"){
                    $query["post_type"] = $value["post_type"];
                }else{
                    $query["post_type"] = "any";
                }
                
                if(isset($value["has_thumbnail"]) && $value["has_thumbnail"]){
                    $meta_query[] = array(
                        'key' => '_thumbnail_id',
                        'compare' => 'EXISTS', // _thumbnail_id anahtarının varlığını kontrol eder
                    );
                    $meta_query[] = array(
                        'key' => '_thumbnail_id',
                        "value" => "",
                        'compare' => '!='
                    );
                }

                if(isset($value["terms_post_type"]) && $value["terms_post_type"]){
                    $tax_query[] = [
                            'taxonomy' => $value["taxonomy_post_type"],
                            'field'    => 'term_id',
                            'terms'    => $value["terms_post_type"],
                            'compare' => 'IN'
                    ];
                }else{
                    if(isset($value["taxonomy_post_type"])){
                        if($value["taxonomy_post_type"] != 0){
                            $terms = get_terms([
                                'taxonomy' => $value["taxonomy_post_type"],
                                'hide_empty' => false,
                            ]);
                            if (!is_wp_error($terms) && !empty($terms)) {
                                $term_ids = wp_list_pluck($terms, 'term_id');
                                $tax_query[] = [
                                    'taxonomy' => $value["taxonomy_post_type"],
                                    'field'    => 'term_id',
                                    'terms'    => $term_ids,
                                    'compare' => 'IN'
                                ];
                            }                            
                        }else{
                            /*$taxonomies = get_object_taxonomies( array( 'post_type' => $query["post_type"], "public" => true ), 'objects' );
                            if($taxonomies){
                                $taxonomies = array_filter($taxonomies, function($taxonomy) {
                                    return $taxonomy->public;
                                });
                                if($taxonomies){
                                    $taxonomies = array_keys($taxonomies);
                                    foreach ($taxonomies as $key => $taxonomy) {
                                        $term_ids = array();
                                        $terms = get_terms(array(
                                            'taxonomy' => $taxonomy,
                                            'hide_empty' => false, // Boş terimleri de al
                                        ));
                                        if (!is_wp_error($terms)) {
                                            foreach ($terms as $term) {
                                                $term_ids[] = $term->term_id;
                                            }
                                            $tax_query[] = [
                                                'taxonomy' => $taxonomy,//array_keys(get_taxonomies(['public' => true], 'names')),
                                                'field'    => 'term_id',
                                                'terms'    => $term_ids,
                                                'compare' => 'IN'
                                            ];
                                            if(!isset($tax_query["relation"]) && $key > 0){
                                                $tax_query["relation"] = "OR";
                                            } 
                                        }
                                    }
                                }
                            }*/
                        }
                    }
                }
            break;

            case "taxonomy" :
                if(isset($value["taxonomy"])){
                    if($value["taxonomy"] != "0"){
                        $query["taxonomy"] = $value["taxonomy"];
                    }else{
                        $query["taxonomy"] = array_keys(get_taxonomies(['public' => true], 'names'));
                    }
                    if(isset($value["terms"])){
                        if($value["terms"]){
                            $query['include'] = $value["terms"];
                        }
                    }
                    $query['hide_empty'] = false;    
                }
            break;

            case "user" :
                if(isset($value["roles"])){
                    if(!empty($value["roles"])){
                        $query["role"] = $value["roles"];
                    }
                    if(!empty($value["user"])){
                        $query["include"] = [$value["user"]];
                    }
                }
            break;

            case "comment" :

                $query["status"] = "approve";

                $query['no_found_rows'] = false;

                if(!empty($value["comment_type"])){
                    $query["comment_type"] = $value["comment_type"];
                }

                if(isset($value["rating"])){
                    $meta_query[] = [
                        'key'     => 'rating', // Meta anahtarı, yorumun rating değerini sakladığımız yer
                        'value'   => $value["rating"], // Rating değerleri dizisi
                        'compare' => 'IN',     // Dizideki değerlere eşit olanları al
                        'type'    => 'NUMERIC' // Rating değerlerinin sayısal olduğunu belirtir
                    ];
                }

                $post_query = "";
                if(isset($value["post_type_comment"])){
                    if(!empty($value["post_type_comment"])){
                        $post_query = "post_type";
                    }
                }
                if(isset($value["taxonomy_comment"])){
                    if(!empty($value["taxonomy_comment"])){
                        $post_query = "taxonomy";
                    }
                }
                if(isset($value["terms_comment"])){
                    if(!empty($value["terms_comment"])){
                        $post_query = "terms";
                    }
                }
                if(isset($value["post_comment"])){
                    if(!empty($value["post_comment"])){
                        $post_query = "post";
                    }
                }
                switch($post_query){

                    case "post_type":
                        $post_ids = $wpdb->get_col($wpdb->prepare("
                            SELECT ID 
                            FROM {$wpdb->posts}
                            WHERE post_type = %s 
                            AND post_status = 'publish'
                        ", $value["post_type_comment"]));
                         $query["post__in"] = $post_ids;
                    break;

                    case "taxonomy":
                        $post_ids = $wpdb->get_col($wpdb->prepare("
                            SELECT p.ID 
                            FROM {$wpdb->posts} p
                            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                            WHERE p.post_type = %s
                            AND p.post_status = 'publish'
                            AND tt.taxonomy = %s
                        ", $value["post_type_comment"], $value["taxonomy_comment"]));
                        $query["post__in"] = $post_ids;
                    break;

                    case "terms":
                        $placeholders = implode(',', array_fill(0, count($value["terms_comment"]), '%d')); // Term ID'ler için placeholder oluştur
                        $post_ids = $wpdb->get_col($wpdb->prepare("
                            SELECT p.ID
                            FROM {$wpdb->posts} p
                            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                            WHERE p.post_type = %s
                            AND p.post_status = 'publish'
                            AND tt.taxonomy = %s
                            AND tr.term_taxonomy_id IN ($placeholders)
                        ", $value["post_type_comment"], $value["taxonomy_comment"], ...$value["terms_comment"]));
                        $query["post__in"] = $post_ids;
                    break;

                    case "post" :
                        $query["post_id"] = $value["post_comment"];
                    break;
                }

                if(isset($value["author_comment"])){
                    if($value["author_comment"]){
                        $query["user_id"] = $value["author_comment"];
                    }
                }else{
                    if(isset($value["roles_comment"])){
                        if($value["roles_comment"]){
                            $meta_query[] =  [
                                'key'     => 'user_role', // Kullanıcı rolü meta anahtarı
                                'value'   => $value["roles_comment"], // Yalnızca administrator rolüne sahip kullanıcıların yorumlarını al
                                'compare' => '=', // Tam eşleşme
                                'type'    => 'CHAR' // Meta değerinin karakter türünde olduğunu belirtir
                            ];
                        }
                    }                    
                }

                if($value["orderby"] == "rating"){
                    $query["meta_key"] = "rating";
                    $value["orderby"] = "value_num";
                }
            break;
        }

        if (isset($value["meta"]) && is_array($value["meta"])) {
            foreach ($value["meta"] as $meta) {
                if (
                    isset($meta["key"]) && $meta["key"] !== '' && $meta["key"] !== false && 
                    isset($meta["compare"]) && $meta["compare"] !== '' && $meta["compare"] !== false && 
                    isset($meta["value"])
                ) {
                    if (is_numeric($meta["value"])) {
                        $meta["type"] = 'NUMERIC';
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $meta["value"])) {
                        $meta["type"] = 'DATE';
                    } else {
                        $meta["type"] = 'CHAR';
                    }
                    $meta_query[] = $meta;
                }
            }
        }

        if($meta_query){
            if(count($meta_query) > 1 && !isset($meta_query["relation"]) ){
                $meta_query["relation"] = "AND";
            }
            $query["meta_query"] = $meta_query;
        }
        if($tax_query){
            if(count($tax_query) > 1 && !isset($tax_query["relation"])){
                $tax_query["relation"] = "AND";
            }
            $query["tax_query"] = $tax_query;
        }

        $post_count_query = $value["type"]=="post"?"posts_per_page":"number";
        //$post_count_all   = $value["type"]=="post"?"-1":"0";
        $post_count_all   = $value["type"]=="post"?"9999":"0";//-1 yapınca tek post donebiliyo bazen

        if($value["paged"]){
            if(!empty($value["max_posts"])){
                $query[$post_count_query] = $value["posts_per_page"] > $value["max_posts"] ? $value["max_posts"] : $value["posts_per_page"];
            }else{
                $query[$post_count_query] = $value["posts_per_page"];
            }
            if($query[$post_count_query] == ""){
               $query[$post_count_query] = 10;
            }
        }else{
            $query[$post_count_query] = empty($value["max_posts"])?$post_count_all:$value["max_posts"];
            if($query[$post_count_query] == ""){
               $query[$post_count_query] = $post_count_all;
            }
        }
        
        $query["orderby"] = $value["orderby"];
        $query["order"] = $value["order"];

        return $query;
    }

    public function get_result($type = "post", $query=[], $post_id = 0) {
    
        // 1. Anahtar Oluşturma
        $query_hash = substr(md5(serialize($query)), 0, 10);  
        $cache_key = ACF_Field_Query_Field::CACHE_PREFIX . $this->acf_query_field_id . '_' . $query_hash; // ✨ DÜZELTME
        $cache_key = sanitize_key($cache_key);

        $posts = false; // Sorgu sonucu için başlangıç değeri

        // ----------------------------------------------------
        // 1. Önbelleği Kontrol Et
        // Önbellekleme açıksa (cache_enabled) VE admin panelinde değilsek kontrol et
        // ----------------------------------------------------
        if ($this->cache_enabled && !is_admin()) { // ✨ DÜZELTME: cache_enabled eklendi
            $posts = get_transient($cache_key);
            //echo "cached---";
        }

        // Cache'de sonuç yoksa VEYA admin alanında isek VEYA cache kapalı ise sorguyu çalıştır.
        if ($posts === false) {
            // 2. Önbellekte yok, sorguyu çalıştır.
            $paginate = new Paginate($query, []); // Mevcut kodunuzdaki sınıfı kullanın
            $result = $paginate->get_results($type);
            $posts = $result["posts"];

            if ( !is_wp_error($posts) && !empty($posts) ) {
                
                // ----------------------------------------------------
                // 3. Önbelleğe Kaydet
                // Önbellekleme açıksa (cache_enabled) VE admin panelinde değilsek kaydet
                // ----------------------------------------------------
                if ($this->cache_enabled && !is_admin()) { // ✨ DÜZELTME: cache_enabled eklendi
                    // 30 dakikalık süre ile önbelleğe kaydet.
                    set_transient($cache_key, $posts, 30 * MINUTE_IN_SECONDS);
                }
            } else {
                $posts = array(); // Hata durumunda boş dizi döndür
            }
        }
        
        return $posts;
    }

    public function get_block_data($value, $post_id, $field){
        global $post;
        $block = [];
        $block_meta = [];
        $block_settings     = false;
        $column_breakpoints = false;
        $slider_settings    = false;
        $slider             = $value["slider"] ?? false;
        $blocks = parse_blocks( get_the_content( '', false, $post->ID ) );
        if($blocks){
            foreach($blocks as $block_item){
                if(isset($block_item["attrs"]["data"])){
                    if(isset($block_item["attrs"]["data"]["_".$field["name"]])){
                        if($block_item["attrs"]["data"]["_".$field["name"]] == $field["key"]){
                            $block = $block_item;
                            continue;
                        }
                    }                 
                }
            }
            if($block){
                
                $is_column = false;
                $index = 0;
                $prefix = "";
                if($block["attrs"]["name"] == "acf/bootstrap-columns"){
                    if(isset($block["attrs"]["data"]["acf_block_columns"])){
                        $block_columns = get_field("_acf_block_columns", $post_id);
                        foreach($block["attrs"]["data"]["acf_block_columns"] as $key => $column){
                            if($column == "block-archive"){
                                $index = $key;
                                $is_column = true;
                                $prefix = "acf_block_columns_".$index."_";
                                continue;
                            }
                        }     
                    }
                }

                $block_data = $block["attrs"]["data"];

                if(!$is_column){

                    if(isset($block_data["block_settings"])){
                        $block_settings = get_field($block_data["_block_settings"]);
                    }
                    if(isset($block_data["column_breakpoints"])){
                        $column_breakpoints = get_field($block_data["_column_breakpoints"]);
                    }
                    if(isset($block_data["slider_settings"]) && $slider){
                        $slider_settings = get_field($block_data["_slider_settings"]);
                    }   

                }
            }
            if($block_settings && ($column_breakpoints || ($slider_settings && $slider))){
                $block_columns = [
                    "block_settings"     => $block_settings,
                    "column_breakpoints" => $column_breakpoints,
                ];
                if($slider_settings && $slider){
                    $block_columns["slider"] = $slider;
                    $block_columns["slider_settings"] = $slider_settings;
                    $block_meta["container_slider"] = block_container($slider_settings["container"]);
                }
                $block_meta["row"] = block_columns($block_columns, $block);
            }
        }

        return $block_meta;
    }

    public function get_render_preload($query, $vars, $value){
        $context = Timber::context();

        $query["paged"] = get_query_var("paged");

        $templates = $value["templates"];
        $paginate_preload = new Paginate($query, $vars);
        $result = $paginate_preload->get_results($value["type"]);
        
        $posts = $result["posts"];
        if(is_wp_error($posts)){
            $posts = array();
        }
        $context["slider"] = $value["slider"];
        $context["heading"] = $value["heading"];
        $context["posts"] = $posts;
        $context["templates"] = $templates;
        $context["is_preview"] = is_admin();

        $preload = $result["data"];
        $preload["posts"] = Timber::compile(["acf-query-field/loop.twig"], $context);
        return $preload;
    }

    public function get_render($type = "post", $query = [], $value = [], $post_id = "", $field = []){

            $context = Timber::context();
            $template = 'acf-query-field/archive.twig';
            $templates = $value["templates"];

            $block_meta = $this->get_block_data($value, $post_id, $field);//apply_filters( 'acf_query_field_block_meta', []);//
 
            $vars = $this->get_vars($value, $query);

            if(get_query_var("paged") > 0){
                //$query["paged"] = get_query_var("paged");
            }

            if($value["preload"]){
                $context["data"] = $this->get_render_preload($query, $vars, $value);
            }
            
            if($value["paged"] && $value["load_type"] == "default"){
                $query["paged"] = get_query_var("paged");
                $paginate = new Paginate($query, $vars);
                $result = $paginate->get_results($value["type"]);
            }else{
                $paginate = new Paginate($query, $vars);
                $result = $paginate->get_results($value["type"]);
            }

            $context["posts"] = $result["posts"];

            $context["acf_query_field"] = $this;
            $context["block_meta"] = $block_meta;
            $context["fields"] = $value;
            $context["templates"] = $templates;
            $context["is_preview"] = is_admin();
            $response["data"] = isset($context["data"])?$context["data"]:[];
            $response["html"] = Timber::compile($template, $context);

            //print_r($response["html"]);
            //Timber::$dirname = $default_dirnames;
            //print_r($response);

        return $response;
    }

    function format_value( $value, $post_id, $field ) {

        $templates = [];
        $templates[] = "tease.twig";
        if(isset($value["template_default"]) && $value["template_default"]){
            array_unshift( $templates, $value["template_default_path"]);
        }else{
            if(isset($value["template"]) && $value["template"]){
                array_unshift( $templates, $value["template"]);
            }
        }
        $value["templates"] = $templates;
        //error_log(json_encode($templates));
        unset($value["template"]);
        unset($value["template_default"]);
        unset($value["template_default_path"]);

        $post_count_query = "posts_per_page";//$value["type"]=="post"?"posts_per_page":"number";

        if(isset($value["slider"]) && $value["slider"]){
           $value[$post_count_query] = empty($value["max_posts"])?-1:$value["max_posts"];
        }else{
            if(isset($value["paged"]) && $value["paged"]){
                $value[$post_count_query] = $value["posts_per_page"];
                if(!empty($value["max_posts"]) && is_numeric($value["max_posts"])){
                    $max_posts = $value["max_posts"] < $value["posts_per_page"]?$value["posts_per_page"]:$value["max_posts"];
                    $page = !empty(get_query_var('paged'))?get_query_var('paged'):1;
                    $value[$post_count_query] = min($value[$post_count_query], $value["max_posts"] - ( $page - 1 ) * $value[$post_count_query]);
                }            
            }else{
                $value[$post_count_query] = empty($value["max_posts"])?-1:$value["max_posts"];
            }
        }

        if(isset($value["type"]) && $value["type"] != "post" && $value["posts_per_page"] == -1){
            $value["posts_per_page"] = 0;
        }

        $query = [];
        
        if(isset($value["acf_query_field_id"])){
            $wp_query_name = ACF_Field_Query_Field::QUERY_PARAM_PREFIX . $value["acf_query_field_id"];
            $query = get_option($wp_query_name);            
        }

        if(empty($query) && isset($value["type"])){
            $query = $this->generate_wp_query($value);
        }

        if((isset($value["paged"]) && $value["paged"] && $value["load_type"] == "default") || (isset($value["slider"]) && $value["slider"])){
           $value["preload"] = true;
        }

        //print_r($this->get_vars($value, $query));

        //error_log(print_r($query, true));

        if(empty($query)){
            return [];
        }

        switch($field["return_type"]){// query, result, render
            case "wp_query" :
                $value = $query;
            break;

            case "sql_query" :
                $sql_query_name = "acf_query_sql_".$post_id;
                $sql_query = get_option($sql_query_name);
                if(empty($sql_query)){
                    $value = wp_query_to_sql($value["type"], $query, $value);
                }else{
                    $value = $sql_query;
                }
            break;

            case "result":
                $value = $this->get_result($value["type"], $query);
                //error_log(print_r($value, true));

            break;

            case "render":
                $render = $this->get_render($value["type"], $query, $value, $post_id, $field);
                $value = array(
                    "html" => $render["html"],
                    "values" => $value
                );
            break;
        }
        
        return $value;
    }

    function update_value( $value, $post_id, $field ) {
        
        // 1. acf_query_field_id kontrolü ve ataması
        if(!isset($value["acf_query_field_id"]) || empty($value["acf_query_field_id"])){
            $acf_query_field_id = unique_code(16);
        }else{
            $acf_query_field_id = $value["acf_query_field_id"];
        }
        
        $this->acf_query_field_id = $acf_query_field_id;
        $value["acf_query_field_id"] = $acf_query_field_id;

        // 1b. Meta query — hidden JSON input'tan oku (flexible/repeater içinde güvenilir)
        if (!empty($value['meta_json'])) {
            $decoded = json_decode(stripslashes($value['meta_json']), true);
            if (is_array($decoded)) {
                $value['meta'] = array_values(array_filter($decoded, function($row) {
                    return !empty($row['key']);
                }));
            }
            unset($value['meta_json']); // DB'ye kaydetme, sadece meta'yı kullan
        } elseif (isset($value['meta']) && is_array($value['meta'])) {
            // Fallback: eski yöntem — repeater'dan gelen değerleri filtrele
            $value['meta'] = array_values(array_filter($value['meta'], function($row) {
                return !empty($row['key']);
            }));
        }
        
        // 2. Önbellek temizliği için hedef post tipini kaydet
        $target_post_type = isset($value['post_type']) ? $value['post_type'] : null;

        if ( !empty( $target_post_type ) ) {
            update_post_meta( $post_id, '_acf_query_field_target_type_' . $field['name'], $target_post_type );
        } else {
            delete_post_meta( $post_id, '_acf_query_field_target_type_' . $field['name'] );
        }

        // 3. KALICI SORGULARI KAYDETME KISMINI KALDIRDIK VEYA DÜZELTTİK
        // NOT: Bu kısım, performans ve stale data sorununa neden olduğu için revize edildi.
        // Artık sadece sorgu ayarlarını (value) kaydedip, sonuçları get_result içinde Transient olarak alacağız.
        
        // Eğer illa ki sorgu array'ini/SQL'i bir yerde tutmak istiyorsanız,
        // Transient veya Post Meta kullanabilirsiniz. Ancak sonuçları değil, sadece sorgunun kendisini (array) tutun.
        
        // Örnek: Query array'ini kaydetme (opsiyonel)
        $wp_query = $this->generate_wp_query($value);
        $sql_query = wp_query_to_sql($value["type"], $wp_query, $value);
        
        $queries = [
            "wp_query_args" => $wp_query, // Sadece sorgu argümanları
            "sql_query_string" => $sql_query // Sadece SQL string'i
        ];
        
        foreach($queries as $key => $query_data){
            // Burada, sorgu tanımını (sonucunu değil) kaydetmeye devam edilebilir, ancak option yerine
            // alanın kendi meta verisine (yani $value array'i içinde geri döndürerek) kaydetmek daha temiz olabilir.
            
            // Önemli: Eğer bu sorgu tanımını da Transient olarak kaydetmezseniz, 
            // her sayfada sorgu oluşturma maliyetine katlanırsınız.
            // Ancak current kodunuz option'a kaydettiği için, şimdilik bu kısmı olduğu gibi bıraktım,
            // ancak buradaki `option` kayıtları da sonuçları değil, **sorgu tanımlarını** tutmalıdır.
            
            $option_name = "acf_query_".$key."_".$value["acf_query_field_id"];
            if(!empty($query_data)){
                // Option'a kaydetmeye devam et (Sadece sorgu argümanları ve SQL string'i)
                //error_log("guncelleniyo :".$option_name); 
                update_option($option_name, $query_data);
                
                // Query ID'sini $value'a eklemeye devam et (Mevcut yapınızı korumak için)
                global $wpdb;
                $option_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s",
                    $option_name
                ));
                $value[$key."_id"] = $option_id; // İsimlendirmeyi değiştirdim: query_id yerine sadece _id
            }
        }

        // YENİ EKLEME (2. Senaryoyu Temizlemek İçin):
        // Sorgu ayarları (value) değiştiğinde, önceki sonuç Transient'ını temizle.
        if(isset($value["acf_query_field_id"]) && !empty($value["acf_query_field_id"])) {
            
            $current_id = $value["acf_query_field_id"];

            // Transient'ları field ID'si ile siliyoruz.
            // ÖNEMLİ: get_result metodunuzdaki anahtarın (key) bu ID'yi içerdiğinden emin olun.
            $transient_key_prefix = 'acf_query_' . $current_id; // Örneğin: acf_query_1a2b3c4d5e
            
            global $wpdb;
            
            // Bu field'a ait tüm Transient'ları bul ve sil.
            $transients_to_delete = $wpdb->get_col( $wpdb->prepare( "
                SELECT option_name 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s
            ", '_transient_' . $transient_key_prefix . '%', '_transient_timeout_' . $transient_key_prefix . '%' ) );

            foreach ( $transients_to_delete as $option_name ) {
                // Transient adını öneklerden temizle
                if ( strpos( $option_name, '_transient_timeout_' ) === 0 ) {
                    $transient_name = substr( $option_name, 19 ); 
                } else if ( strpos( $option_name, '_transient_' ) === 0 ) {
                    $transient_name = substr( $option_name, 11 );
                } else {
                    continue; 
                }
                
                delete_transient( $transient_name );
            }
        }

        // SONUÇLARI KAYDETME KODUNU BURADAN SİLDİK.
        
        return $value;
    }

    function validate_value($valid, $value, $field, $input) {

        //error_log("validate_value");
        //return false;

         //error_log("validate_value: ");
         //error_log(print_r($value, true));
        
        if ($field['name'] == "max_posts" && empty($value)) {
            $valid = 'Bu alan gerekli.';
        }
        //error_log(json_encode($valid));
        //error_log(json_encode($value));
        //error_log(json_encode($field));
        //error_log(json_encode($input));

        return $valid;
    }

    public function input_admin_enqueue_scripts() {
        wp_enqueue_script('acf-query-field', plugin_dir_url(__FILE__) . 'assets/script.js', array('acf-input'), '1.0.0', true);
        wp_enqueue_style('acf-query-field', plugin_dir_url(__FILE__) . 'assets/style.css', array('acf-input'), '1.0.0');
    }

}

/*new ACF_Field_Query_Field( array(
    'version' => '1.0.0',
    'url' => plugin_dir_url(__FILE__),
    'path' => plugin_dir_path(__FILE__)
));*/
if (class_exists("Timber")) {
    acf_register_field_type(new ACF_Field_Query_Field( array(
        'version' => '1.0.0',
        'url' => plugin_dir_url(__FILE__),
        'path' => plugin_dir_path(__FILE__)
    )));
}

// acf-query-field-class.php dosyasının en altına veya plugin ana dosyasına ekleyin

add_action( 'save_post', 'acf_query_field_clear_cache', 10, 2 );

/**
 * Bir gönderi güncellendiğinde, o gönderinin tipine bağlı olan tüm ACF Query Field önbelleklerini temizler.
 * Bu sayede get_result metodu bir sonraki çağrıda güncel veriyi çeker.
 */
function acf_query_field_clear_cache( $post_id, $post ) {
    
    // Revizyon, oto-kayıt ve yeni post işlemlerini atla
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || $post->post_status === 'auto-draft' ) {
        return;
    }
    
    $updated_post_type = $post->post_type;

    // 1. Güncellenen post_type'ı hedefleyen tüm ACF Query Field'larının listesini bul
    global $wpdb;
    
    // 'query_field' tipindeki tüm alanların hedef post_type'ı için kaydettiği meta key'leri arıyoruz.
    $meta_key_pattern = '_acf_query_field_target_type_%';
    
    $query_posts = $wpdb->get_col( $wpdb->prepare( "
        SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key LIKE %s 
        AND meta_value = %s
    ", $meta_key_pattern, $updated_post_type ) );

    if ( !empty( $query_posts ) ) {
        // 2. Transients'ları temizle
        // get_result metodunda kullandığımız 'acf_query_' önekine sahip tüm Transient'ları sil.
        
        $transients_to_delete = $wpdb->get_col( $wpdb->prepare( "
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s
        ", '_transient_' . ACF_Field_Query_Field::CACHE_PREFIX . '%', '_transient_timeout_' . ACF_Field_Query_Field::CACHE_PREFIX . '%' ) );
        
        foreach ( $transients_to_delete as $option_name ) {
            
            // Transient adı, option_name'deki ön ekten sonraki kısımdır.
            if ( strpos( $option_name, '_transient_timeout_' ) === 0 ) {
                $transient_name = substr( $option_name, 19 ); 
            } else if ( strpos( $option_name, '_transient_' ) === 0 ) {
                $transient_name = substr( $option_name, 11 );
            } else {
                continue; 
            }
            
            delete_transient( $transient_name );
        }
    }
}
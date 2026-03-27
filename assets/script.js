(function($) {
    if (typeof acf !== 'undefined') {
        acf.add_action('ready_field/type=query_field', function($field) {
            console.log($field)
            initialize_query_field($field);
        });

        acf.add_action('append_field/type=query_field', function($field) {
            console.log($field)
            initialize_query_field($field);
        });

        function initialize_query_field($field) {

            $field.addClass("init");

            var $return_type = $field.find('[name="return_type"]').val();

            var $type = $field.find('[data-name="type"]');

            var $post_type_comment      = $field.find('[data-type="comment"]').find('[data-name="post_type"]');
            var $taxonomy_comment       = $field.find('[data-type="comment"]').find('[data-name="taxonomy"]');
            var $terms_comment          = $field.find('[data-type="comment"]').find('[data-name="terms"]');
            var $post_comment           = $field.find('[data-type="comment"]').find('[data-name="post"]');
            var $button                 = $field.find('[data-type="button"]');

            var $has_thumbnail           = $field.find('[data-name="has_thumbnail"]');

            var $roles_comment          = $field.find('[data-name="roles_comment"]');
            var $author_comment         = $field.find('[data-name="author_comment"]');

            var $template               = $field.find('[data-name="template"]');
            var $template_default       = $field.find('[data-name="template_default"]');
            var $template_default_path  = $template_default.find('input.template_default_path');

            var $paged                  = $field.find('[data-name="paged"]');
            var $paged_url              = $field.find('[data-name="paged_url"]');
            var $default_posts_per_page = $field.find('[data-name="default_posts_per_page"]');
            var $posts_per_page         = $field.find('[data-name="posts_per_page"]');
            var $max_posts              = $field.find('[data-name="max_posts"]');
            var $load_type              = $field.find('[data-name="load_type"]');
            var $preload                = $field.find('[data-name="preload"]');
            var $slider                 = $field.find('[data-name="slider"]');

            var $post_meta              = $field.find('[data-type="post_meta"]');
            var $sticky                 = $post_meta.find('[data-name="sticky"]');
            var $sticky_ignore          = $post_meta.find('[data-name="sticky_ignore"]');

            initialize_meta($field);

            $field.find("select[multiple]").each(function(){
                $(this).select2({
                    allowClear: $(this).data('allow_null') == 1 ? true : false,
                    placeholder: $(this).data('placeholder'),
                    multiple: $(this).data('multiple') == 1 ? true : false,
                });
            });

            $terms_comment
            .on('select2:select', function(e) {
                $post_comment.find("select").attr("data-val", "");
                $post_comment.find("select").data("val", "");
                $post_comment.find("select").val(null).trigger("change");
            })
            .on('select2:unselect', function(e) {
                $post_comment.find("select").attr("data-val", "");
                $post_comment.find("select").data("val", "");
                $post_comment.find("select").val(null).trigger("change");
            });

            $post_comment
            .on('select2:unselect', function(e) {
                $post_comment.find("select").attr("data-val", "");
                $post_comment.find("select").data("val", "");
                $post_comment.find("select").val(null).trigger("change");
            });

            $author_comment
            .on('select2:unselect', function(e) {
                $author_comment.find("select").attr("data-val", "");
                $author_comment.find("select").data("val", "");
                $author_comment.find("select").val(null).trigger("change");
            });

            function formatUser(user) {
                if (!user.id) {
                    return user.text;
                }
                var $user = $('<span>' + user.text + '</span>');
                return $user;
            }

            $author_comment.find("select").select2({
                allowClear: 1,
                placeholder: $(this).data('placeholder'),
                multiple: 0,
                ajax: {
                    url: ajaxurl, // WordPress'in AJAX URL'si
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        var selectedRole = $roles_comment.find("select").val(); // role_select, role seçimi için kullanılan select field'ın ID'si
                        var selectedId   = $author_comment.find("select").data("val") || "";
                        return {
                            action: 'acf_query_field_author_ajax',
                            search: params.term,
                            role: selectedRole,  // Role parametresini AJAX isteğine ekliyoruz
                            selected: selectedId, 
                            page: params.page || 1
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1,
                templateResult: formatUser,
                templateSelection: formatUser
            }).trigger("change");

            $post_comment.find("select").select2({
                allowClear: 1,
                placeholder: $(this).data('placeholder'),
                multiple: 0,
                ajax: {
                    url: ajaxurl, // WordPress'in AJAX URL'si
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        var post_type = $post_type_comment.find("select").val();
                        var taxonomy = $taxonomy_comment.find("select").val();
                        var terms = $terms_comment.find("select").val();
                        var selectedId   = $post_comment.find("select").data("val") || "";
                        return {
                            action: 'acf_query_field_post_ajax',
                            search: params.term,
                            post_type : post_type,
                            taxonomy : taxonomy,
                            terms: terms,  // Role parametresini AJAX isteğine ekliyoruz
                            selected: selectedId, 
                            page: params.page || 1
                        };
                    },
                    processResults: function(data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results,
                            pagination: {
                                more: (params.page * 10) < data.total_count
                            }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1,
                templateResult: formatUser,
                templateSelection: formatUser
            }).trigger("change");

            $template_default.find("select").on("change", function(){
                if($(this).val() != 0) {
                    $template_default_path.val($(this).val());
                    $template.find("select").val(0);
                    $template.addClass("d-none");
                }else{
                    $template_default_path.val("");
                    $template.removeClass("d-none");
                }
            });

            $paged.find("input[type='checkbox']").on("change", function(){
                if($(this).is(':checked')) {
                    if($load_type.find("select").val() == "button" && $return_type != "result"){
                        $button.removeClass("d-none");                        
                    }
                    $paged_url.removeClass("d-none");
                    $posts_per_page.removeClass("d-none");
                    $default_posts_per_page.addClass("d-none");
                    $default_posts_per_page.find(".default_posts_per_page").html("")
                    if($type.find("select").val() == "post"){
                        $post_type = $field.find("[data-name='post_type']").find("select").val();        
                        if(acf_query_field_pagination_defaults.hasOwnProperty($post_type)){
                            if(acf_query_field_pagination_defaults[$post_type]["paged"]){
                               $default_posts_per_page.removeClass("d-none");
                               $default_posts_per_page.find(".default_posts_per_page").html($post_type+" "+acf_query_field_pagination_defaults[$post_type]["posts_per_page"]+" items");
                           }
                        }
                    }
                    $load_type.removeClass("d-none");
                    //$preload.removeClass("d-none");
                    $preload.find(".acf-label label").html($preload.find(".acf-label label").attr("data-label"));
                    $slider.find("input").prop("checked", false).trigger("change");
                    $max_posts.find("input").val("");
                }else{
                    $button.addClass("d-none");
                    $paged_url.addClass("d-none");
                    $posts_per_page_val = $posts_per_page.find("input").attr("data-val");
                    $posts_per_page.find("input").val($posts_per_page_val);
                    $posts_per_page.addClass("d-none");
                    //$default_posts_per_page.find("input").val("");
                    $default_posts_per_page.addClass("d-none");
                    $load_type.addClass("d-none");
                    $preload.find(".acf-label label").html("Load without ajax");
                    //$preload.find("input").prop("checked", false).trigger("change");
                    //$preload.addClass("d-none");
                }
                $max_posts.find("input").val("").prop("required", false);
            });

            $paged_url.find("input[type='checkbox']").on("change", function(){
                if($(this).is(':checked')) {
                    $load_type.find("select").find("option[value='default']").removeClass("d-none");
                }else{
                    $load_type.find("select").find("option[value='default']").addClass("d-none");
                    if($load_type.find("select").val() == "default"){
                        $load_type.find("select option:not(.d-none)").first().prop('selected', true);
                    }
                }
            });

            $default_posts_per_page.find("input[type='checkbox']").on("change", function(){
                $post_type = $field.find("[data-name='post_type']").find("select").val();  
                if($(this).is(':checked')) {
                    console.log(acf_query_field_pagination_defaults[$post_type])
                    $load_type.find('select option[value="'+acf_query_field_pagination_defaults[$post_type]["type"]+'"]').prop('selected', true);
                    $load_type.find('select').attr("readonly", true).trigger("change");
                    $posts_per_page.find("input").val(acf_query_field_pagination_defaults[$post_type]["posts_per_page"]).attr("readonly", true);
                }else{
                    $posts_per_page_val = $posts_per_page.find("input").attr("data-val");
                    $posts_per_page.find("input").val($posts_per_page_val).attr("readonly", false);
                    $load_type_val = $load_type.find("select").attr("data-val");
                    $load_type.find('select option[value="'+$load_type_val+'"]').prop('selected', true);
                    $load_type.find('select').attr("readonly", false).trigger("change");
                    $(this).val(0);
                }
            });

            $load_type.find("select").on("change", function(){
                if($(this).val() == "button" && $return_type != "result"){
                    $button.removeClass("d-none");
                }else{
                    $button.addClass("d-none");
                }
            });

            $slider.find("input[type='checkbox']").on("change", function(){
                if($(this).is(':checked')) {
                    $paged.find("input").prop("checked", false).trigger("change");
                    $max_posts.find("input").val("10");
                    //$max_posts.find("input").prop("required", true);
                }else{
                    $max_posts.find("input").val("");
                    //$max_posts.find("input").prop("required", false);
                }
            });

            $sticky.find("input[type='checkbox']").on("change", function(){
                if($(this).is(':checked')) {
                    $sticky_ignore.addClass("d-none");
                    $sticky_ignore.find("input").prop("checked", false).trigger("change");
                }else{
                    $sticky_ignore.removeClass("d-none");
                }
            });

            $sticky_ignore.find("input[type='checkbox']").on("change", function(){
                if($(this).is(':checked')) {
                    $sticky.addClass("d-none");
                    $sticky.find("input").prop("checked", false).trigger("change");
                }else{
                    $sticky.removeClass("d-none");
                }
            });

            $field.find("select").each(function(){
                $(this).on('change', function(e) {
                    var type  = $(this).closest(".acf-fields").data("type");
                    var name  = $(this).closest(".acf-field").data("name");
                    var value = $(this).val();

                    switch(name){
                        case "type" :
                            reset_fields($field);
                            toggle_fields($field, value);
                            update_orderby_options($field);
                            check_template_paths($field);
                            if(value != "post"){
                                $default_posts_per_page.addClass("d-none");
                                $default_posts_per_page.find(".default_posts_per_page").html("");
                                $default_posts_per_page.find("input").val(0).prop("checked", false).trigger("change");
                            }
                            if(!e.isTrigger){
                                $field.find(".acf-query-meta-fields .acf-field[data-name='meta']").find(".holder").empty();
                            }
                        break;
                        case "post_type" :
                        case "taxonomy" :
                        case "terms" :
                            get_options($field, type, name, value, e);
                            $default_posts_per_page.addClass("d-none");
                            if(name == "post_type"){
                                $default_posts_per_page.addClass("d-none");
                                $default_posts_per_page.find(".default_posts_per_page").html("");
                                $default_posts_per_page.find("input").val(0).prop("checked", false);
                                if(!e.isTrigger){
                                    $default_posts_per_page.find("input").trigger("change");
                                }
                                if(acf_query_field_pagination_defaults.hasOwnProperty(value)){
                                   if(acf_query_field_pagination_defaults[value]["paged"]){
                                       $default_posts_per_page.find("input").val(acf_query_field_pagination_defaults[value]["posts_per_page"]);
                                       $default_posts_per_page.removeClass("d-none");
                                       $default_posts_per_page.find(".default_posts_per_page").html(value+" "+acf_query_field_pagination_defaults[value]["posts_per_page"]+" items");
                                   }
                                }
                            }
                            if( type == "comment" && (name != "terms" && value != "0" && e.originalEvent)){
                                if(name == "post_type"){
                                    $taxonomy_comment.find("select").val("0").data("val", "");
                                    $terms_comment.find("select").val("0").data("val", "");
                                }
                                if(name == "taxonomy"){
                                    $terms_comment.find("select").val("0").data("val", "");
                                }
                                $post_comment.find("select").attr("data-val", "");
                                $post_comment.find("select").data("val", "");
                                $post_comment.find("select").find("select").val(null).trigger("change");
                            }
                        break;
                        case "roles_comment" :
                            $author_comment.addClass("d-none");
                            if(value != "0"){
                                $author_comment.removeClass("d-none");
                                if(!e.isTrigger){
                                    $author_comment.find("select").val(null).trigger("change");
                                }
                            }
                        break;
                        case "roles" :
                        case "comment_type" :
                            check_template_paths($field);
                        break;

                    }
                });
            });

            function reset_fields($field){
                console.log("reset_fields")
                if($field.hasClass("init")){
                    return false;
                }
     
                var types = $field.find('.acf-fields').not("[data-type='type']").map(function() {
                    return $(this).data('type');
                }).get();
                console.log(types)
                
                types.forEach(function(type, index) {
                    var container = $field.find('.acf-query-'+type+'-fields');
                    var selects = container.find("select");
                    selects.each(function(){
                        if(!$(this).hasClass("data-val-inherit")){
                            if($(this).find("option[value='0']").length > 0){
                                $(this).attr("data-val", "");
                                $(this).data("val", "");
                                $(this).val("0");
                            }else{
                               $(this).attr("data-val", "");
                               $(this).data("val", "");
                               var firstOption = $(this).find('option:first');
                               var firstValue = firstOption.val();
                               firstOption.prop('selected', true);
                               $(this).val(firstValue);
                            }
                            if($(this).next(".select2").length > 0){
                                console.log($(this))
                                $(this).val(null).trigger('change');
                            }
                        }else{
                            $val = $(this).attr("data-val");
                            if($val != ""){
                                $(this).find('select option[value="'+$val+'"]').prop('selected', true);
                            }
                        }
                        if(!$(this).hasClass("data-val-inherit")){
                            
                        }
                    });
                });
            }

            function toggle_fields($field, type) {

                var container = $field.find('.acf-query-'+type+'-fields');

                var $post_type_obj = container.find('[data-name="post_type"]');
                var $taxonomy_obj = container.find('[data-name="taxonomy"]');
                var $terms_obj    = container.find('[data-name="terms"]');
                //var $post_obj    = container.find('[data-name="post"]');

                //var $taxonomy_obj = type == "post" ? $taxonomy_post_type : $taxonomy;
                //var $terms_obj    = type == "post" ? $terms_post_type : $terms;

                console.log("toggle_fields : "+type)
                $field.find('.acf-query-post-fields, .acf-query-taxonomy-fields, .acf-query-user-fields, .acf-query-comment-fields').addClass("d-none");
                $field.find('.acf-query-' + type + '-fields').removeClass("d-none");

                $post_meta.find("input[type='checkbox']").prop("checked", false).trigger("change");
                $post_meta.addClass("d-none");

                /*$has_thumbnail.find("input[type='checkbox']").prop("checked", false).trigger("change");
                $has_thumbnail.addClass("d-none");

                $sticky.find("input[type='checkbox']").prop("checked", false).trigger("change");
                $sticky.addClass("d-none");

                $sticky_ignore.find("input[type='checkbox']").prop("checked", false).trigger("change");
                $sticky_ignore.addClass("d-none");*/

                if (type === 'taxonomy') {

                    // init sirasinda terms data-val'i koru — get_options chain'i restore edecek
                    if (!$field.hasClass("init")) {
                        $terms_obj.addClass("d-none");
                        $terms_obj.find("select").val(null).trigger('change');
                    }

                    $taxonomy_obj.find("select").html(acf_query_field_taxonomies);
                    if($taxonomy_obj.find("select").data("val") == "" || !$field.hasClass("init")){
                        $taxonomy_obj.removeClass("d-none").find("select").val(0);
                    }else{
                        $taxonomy_obj.removeClass("d-none")
                        $taxonomy_obj.find("select").trigger('change'); 
                    }
                    // data-val'i ancak init bittikten sonra sifirla
                    // init sirasinda chain devam edecegi icin korunmali
                    if (!$field.hasClass("init")) {
                        $taxonomy_obj.find("select").attr("data-val", "");
                    }
                    
                } else if(type === 'post' || type === 'comment'){

                    $taxonomy_obj.addClass("d-none");
                    $terms_obj.addClass("d-none")

                    $post_type_obj.removeClass("d-none");
                    if($post_type_obj.find("select").data("val") == "" || !$field.hasClass("init")){
                        $post_type_obj.find("select").prop("selected", 0);
                        $post_type_obj.find("select").val(0);
                    }else{
                        $post_type_obj.find("select").trigger("change");
                    }
                    // data-val'i ancak init bittikten sonra sifirla
                    if (!$field.hasClass("init")) {
                        $post_type_obj.find("select").attr("data-val", "");
                    }

                    if(type == "post"){
                        //$has_thumbnail.removeClass("d-none");
                        $post_meta.removeClass("d-none");
                        //$sticky_ignore.removeClass("d-none");
                    }

                }
                $field.removeClass("init");
            }

            function get_options($field, type, name, value, e){
                console.log(type, name, value);
                var container = $field.find('.acf-query-'+type+'-fields');

                var $post_type_obj = container.find('[data-name="post_type"]');
                var $taxonomy_obj = container.find('[data-name="taxonomy"]');
                var $terms_obj    = container.find('[data-name="terms"]');

                var data = {
                    action: 'acf_query_field_ajax',
                };
                
                switch(name){
                    case "post_type" :
                        if (value === '0') {
                            $taxonomy_obj.addClass("d-none");
                            $terms_obj.addClass("d-none");
                            return;
                        }
                        var $obj_main = $taxonomy_obj;
                        var $obj_chained = $terms_obj; 
                        $obj_main.removeClass("d-none");
                        $obj_main.addClass("loading-process loading-xs");
                        $obj_chained.addClass("d-none");
                        data["value"]    = value;
                        data["type"]     = "post_type"
                        data["selected"] = $obj_main.find("select").data("val");
                    break;
                    case "taxonomy" :
                        if (value === '0') {
                            $terms_obj.addClass("d-none");
                            return;
                        }
                        var $obj_main = $terms_obj;
                        $obj_main.find("select").val(null).trigger('change');
                        $obj_main.removeClass("d-none");
                        $obj_main.addClass("loading-process loading-xs");
                        data["value"]    = value;
                        data["type"]     = "taxonomy"
                        data["selected"] = $obj_main.find("select").data("val");
                    break;
                    default:
                        return false;
                    break;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'post',
                    dataType: 'json',
                    data: data,
                    success: function(response) {
                        if (!response.error) {
                            if (response.html) {
                                var $select       = $obj_main.find("select");
                                var isMultiple    = $select.prop("multiple");
                                // data-val'i AJAX dönmeden önce oku — success içinde zaten silinmiş olabilir
                                var savedVal      = $select.data("val");

                                $select.html(response.html);

                                // Kaydedilmiş değeri restore et (programatik trigger ise)
                                if (e && e.isTrigger && savedVal !== undefined && savedVal !== "" && savedVal !== "0") {
                                    if (isMultiple) {
                                        // Terms gibi multiple select'ler için JSON array parse et
                                        var parsedVal = savedVal;
                                        if (typeof parsedVal === "string") {
                                            try { parsedVal = JSON.parse(parsedVal); } catch(err) { parsedVal = [parsedVal]; }
                                        }
                                        if (Array.isArray(parsedVal) && parsedVal.length > 0) {
                                            // Her option'ı kontrol et, parsedVal içindeyse seç
                                            $select.find("option").each(function() {
                                                if (parsedVal.indexOf($(this).val()) !== -1 || parsedVal.indexOf(parseInt($(this).val())) !== -1) {
                                                    $(this).prop("selected", true);
                                                }
                                            });
                                        }
                                    } else {
                                        $select.val(savedVal);
                                    }
                                }

                                // select2 varsa update et
                                if ($select.hasClass("select2-hidden-accessible")) {
                                    $select.trigger("change.select2");
                                }

                                if ($obj_chained) {
                                    // Chained select için mevcut değeri geç (restore edilmiş olabilir)
                                    get_options($field, type, $obj_main.closest(".acf-field").data("name"), $select.val(), e);
                                }
                            } else {
                                $obj_main.addClass("d-none");
                                if ($obj_chained) {
                                    $obj_chained.addClass("d-none");
                                }
                            }
                        } else {
                            console.error(response.message);
                            $obj_main.addClass("d-none");
                        }

                        // data-val'i SADECE kullanıcı elle değiştirdiyse sıfırla
                        // Programatik trigger (isTrigger) ise koru — chain henüz bitmemiş olabilir
                        if (!e || !e.isTrigger) {
                            $obj_main.find("select").attr("data-val", "");
                            $obj_main.find("select").data("val", "");
                        }

                        $obj_main.removeClass("loading-process loading-xs");
                        check_template_paths($field);
                    },
                    error: function(xhr, status, error) {
                        $obj_main.addClass("d-none");
                        $obj_main.removeClass("loading-process loading-xs");
                        console.error('AJAX Error: ' + status + ' - ' + error);
                    }
                });
            }

            function update_orderby_options($field) {
                var type = $field.find('[data-name="type"] select').val();
                var $orderby = $field.find('[data-name="orderby"] select');
                var options = [];

                switch (type) {
                    case 'post':
                        options = [
                            'ID', 'author', 'title', 'name', 'type', 'date', 'modified',
                            'parent', 'rand', 'comment_count', 'relevance', 'menu_order',
                            'meta_value', 'meta_value_num', 'post__in'
                        ];
                        break;
                    case 'taxonomy':
                        options = ['name', 'slug', 'term_group', 'term_id', 'term_order', 'count'];
                        break;
                    case 'user':
                        options = ['ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name'];
                        break;
                    case 'comment':
                        options = ['comment_ID', 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id', 'rating'];
                        break;
                }

                var selectedValue = $orderby.data("val");
                var optionsHtml = options.map(function(option, index) {
                    var isSelected = (option === selectedValue || (selectedValue === undefined && index === 0)) ? ' selected' : '';
                    return '<option value="' + option + '"' + isSelected + '>' + option + '</option>';
                }).join('');
                $orderby.html(optionsHtml);
                // data-val sadece kullanici type degistirince sifirlanir
                // init sirasinda (programatik trigger) korunur
                if (!$field.hasClass("init")) {
                    $orderby.attr("data-val", "");
                }
            }

            function check_template_paths($field){
                var $type                 = $field.find('[data-name="type"] select').val();
                var $post_type            = $field.find('[data-name="post_type"] select').val();
                var $taxonomy             = $field.find('[data-name="taxonomy"] select').val();
                var $roles                = $field.find('[data-name="roles"] select').val();
                var $comment_type         = $field.find('[data-name="comment_type"] select').val();
                var template_default_path = $field.find('input.template_default_path');
                var template_default      = $field.find('[data-name="template_default"]');
                    template_default.removeClass("d-none");
                    template_default.addClass("loading-process loading-xs");
                $.ajax({
                    url: ajaxurl,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        action: 'acf_query_field_check_template_path',
                        type: $type,
                        post_type: $post_type,
                        taxonomy: $taxonomy,
                        roles: $roles,
                        comment_type: $comment_type
                    },
                    success: function(response) {
                        if (response.template) {
                            //template_default.find(".template_default_path").html(response.template);
                            //template_default_path.val(response.template);
                            template_default.find("select").empty()
                            let selected = template_default_path.val();
                            template_default.find("select").find('option').each(function() {
                                if ($(this).val() != "0") {
                                    $(this).remove();
                                }
                            });
                            response.template.forEach(function(item) {
                                console.log("bu abi:"+item)
                                if(item != 0 && item != "0"){
                                    let option = $('<option></option>').attr('value', item).text(item);
                                    if (item == selected) {
                                        option.prop('selected', true);
                                    }
                                    template_default.find("select").append(option);                                    
                                }
                            });
                        }else{
                            template_default_path.val("");
                            //template_default.find(".template_default_path").html("");
                            template_default.addClass("d-none");
                            //template_default.find("input[type='checkbox']").prop("checked", false).trigger("change");
                            template_default.find("select").find('option').each(function() {
                                if ($(this).val() != "0") {
                                    $(this).remove();
                                }
                            });
                            template_default.find("select").trigger("change");
                        }
                        template_default.removeClass("loading-process loading-xs");
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error: ' + status + ' - ' + error);
                    }
                });
            }

            $type.find("select").trigger("change");   
        }


        function initialize_meta($field){
            var $metaRepeater = $field.find('.acf-repeater');
            $metaRepeater.find('.meta-name').each(function() {
                initialize_meta_name($(this));
            });
            $field.find('.acf-repeater-add-row').on('click', function() {
                var index = $metaRepeater.find('.acf-repeater-row').length;
                var name = $field.find('.acf-field-repeater').attr("data-parent")+'[meta]['+index+']';
                var newRow = `
                    <div class="acf-repeater-row">
                        <div class="acf-fields --left">
                            <div class="acf-field acf-field-select" data-name="key">
                                <select name="${name}[key]" class="meta-name"></select>
                            </div>
                            <div class="acf-field acf-field-select" data-name='compare'>
                                <select name="${name}[compare]" class="operator">
                                    <option value="=">Equals</option>
                                    <option value="!=">Not Equals</option>
                                    <option value=">">Greater Than</option>
                                    <option value="<">Less Than</option>
                                    <option value="LIKE">Like</option>
                                    <option value="NOT LIKE">Not Like</option>
                                    <option value="IN">In</option>
                                    <option value="NOT IN">Not In</option>
                                </select>
                            </div>
                            <div class="acf-field acf-field-text" data-name='value'>
                                <input type="text" name="${name}[value]" value="" class="meta_value"/>
                            </div>
                            <button type="button" class="button remove-row">Remove</button>
                        </div>
                        
                    </div>`;
                
                $metaRepeater.find(".holder").append(newRow);

                var $newMetaName = $metaRepeater.find('.acf-repeater-row').last().find("select.meta-name");
                initialize_meta_name($newMetaName);
            });
            $field.on('click', '.remove-row', function() {
                $(this).closest('.acf-repeater-row').remove();
            });
        }

        function initialize_meta_name($element) {
            $element.select2({
                ajax: {
                    url: ajaxurl, // WordPress'in AJAX URL'si
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        var $field = $element.closest(".acf-field-query-field");
                        var $type = $field.find('[data-name="type"] select').val();
                        var $post_type = $field.find('[data-name="post_type"] select').val();
                        var $taxonomy = $field.find('[data-name="taxonomy"] select').val();
                        var $terms = $field.find('[data-name="terms"] select').val();
                        return {
                            action: 'acf_query_field_fetch_meta_names',
                            type: $type,
                            post_type: $post_type,
                            taxonomy: $taxonomy,
                            terms: $terms,
                            q: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function(data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results,
                            pagination: {
                                more: (params.page * 10) < data.total_count
                            }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1,
                placeholder: 'Select Meta Name',
                allowClear: true
            }).trigger("change");
        }

    }
})(jQuery);
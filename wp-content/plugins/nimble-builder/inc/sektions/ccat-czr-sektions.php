<?php
namespace Nimble;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Google Fonts => save the list of most used fonts in the site
// so that they are appended in first position when building gfont collection for input in customizer control js
// implemented for https://github.com/presscustomizr/nimble-builder/issues/418
add_action('customize_save_after', '\Nimble\sek_update_most_used_gfonts');
function sek_update_most_used_gfonts( $manager ) {
    $skope_id = skp_get_skope_id();
    $all_gfonts = sek_get_all_gfonts( $skope_id );
    if ( is_array($all_gfonts) && !empty($all_gfonts) ) {
        update_option( NIMBLE_OPT_NAME_FOR_MOST_USED_FONTS, $all_gfonts );
    }
}


add_action('customize_save_after', '\Nimble\sek_maybe_write_global_stylesheet');
function sek_maybe_write_global_stylesheet( $manager ) {
    // Try to write the CSS
    new Sek_Dyn_CSS_Handler( array(
        'id'             => NIMBLE_GLOBAL_SKOPE_ID,
        'skope_id'       => NIMBLE_GLOBAL_SKOPE_ID,
        'mode'           => Sek_Dyn_CSS_Handler::MODE_FILE,
        'customizer_save' => true,//<= indicating that we are in a customizer_save scenario will tell the dyn css class to only write the css file + save the google fonts, not schedule the enqueuing
        'force_rewrite'  => true, //<- write even if the file exists
        'is_global_stylesheet' => true
    ) );
}

// @return array of all gfonts used in the site
// the duplicates are not removed, because we order the fonts by number of occurences in javascript.
// @see js control::font_picker in api.czrInputMap
// implemented for https://github.com/presscustomizr/nimble-builder/issues/418
function sek_get_all_gfonts() {
    // First check if we have font defined globally. Implemented since https://github.com/presscustomizr/nimble-builder/issues/292
    $global_options = get_option( NIMBLE_OPT_NAME_FOR_GLOBAL_OPTIONS );
    $ffamilies = array();
    if ( is_array( $global_options ) && !empty( $global_options['fonts'] ) && is_array( $global_options['fonts'] ) ) {
        $ffamilies = array_merge( $ffamilies, $global_options['fonts'] );
    }

    // Do a query on all NIMBLE_CPT and walk all skope ids, included the global skope ( for global sections )
    $sek_post_query_vars = array(
        'post_type'              => NIMBLE_CPT,
        'post_status'            => get_post_stati(),
        //'name'                   => sanitize_title( NIMBLE_OPT_PREFIX_FOR_SEKTION_COLLECTION . $skope_id ),
        'posts_per_page'         => -1,
        'no_found_rows'          => true,
        'cache_results'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'lazy_load_term_meta'    => false,
    );
    $query = new \WP_Query( $sek_post_query_vars );
    if ( ! is_array( $query->posts ) || empty( $query->posts ) )
      return;

    foreach ($query->posts as $post_object ) {
        if ( $post_object ) {
            $seks_data = maybe_unserialize( $post_object->post_content );
        }
        $seks_data = is_array( $seks_data ) ? $seks_data : array();
        if ( empty( $seks_data ) )
          continue;
        if ( is_array( $seks_data ) && !empty( $seks_data['fonts'] ) && is_array( $seks_data['fonts'] ) ) {
            $ffamilies = array_merge( $ffamilies, $seks_data['fonts'] );
        }
    }//foreach

    // duplicates are kept for ordering
    //$ffamilies = array_unique( $ffamilies );
    return $ffamilies;
}



// ENQUEUE CUSTOMIZER JAVASCRIPT + PRINT LOCALIZED DATA
add_action ( 'customize_controls_enqueue_scripts', '\Nimble\sek_enqueue_controls_js_css', 20 );
function sek_enqueue_controls_js_css() {
    wp_enqueue_style(
        'sek-control',
        sprintf(
            '%1$s/assets/czr/sek/css/%2$s' ,
            NIMBLE_BASE_URL,
            sek_is_dev_mode() ? 'sek-control.css' : 'sek-control.min.css'
        ),
        array(),
        NIMBLE_ASSETS_VERSION,
        'all'
    );


    wp_enqueue_script(
        'czr-sektions',
        //dev / debug mode mode?
        sprintf(
            '%1$s/assets/czr/sek/js/%2$s' ,
            NIMBLE_BASE_URL,
            sek_is_dev_mode() ? 'ccat-sek-control.js' : 'ccat-sek-control.min.js'
        ),
        array( 'czr-skope-base' , 'jquery', 'underscore' ),
        NIMBLE_ASSETS_VERSION,
        $in_footer = true
    );


    wp_localize_script(
        'czr-sektions',
        'sektionsLocalizedData',
        apply_filters( 'nimble-sek-localized-customizer-control-params',
            array(
                'nimbleVersion' => NIMBLE_VERSION,
                'isDevMode' => sek_is_dev_mode(),
                'baseUrl' => NIMBLE_BASE_URL,
                //ajaxURL is not mandatory because is normally available in the customizer window.ajaxurl
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'customizerURL'   => admin_url( 'customize.php' ),
                'sektionsPanelId' => '__sektions__',
                'addNewSektionId' => 'sek_add_new_sektion',
                'addNewColumnId' => 'sek_add_new_column',
                'addNewModuleId' => 'sek_add_new_module',

                'optPrefixForSektionSetting' => NIMBLE_OPT_PREFIX_FOR_SEKTION_COLLECTION,//'nimble___'
                'optNameForGlobalOptions' => NIMBLE_OPT_NAME_FOR_GLOBAL_OPTIONS,//'nimble___'
                'optPrefixForSektionsNotSaved' => NIMBLE_OPT_PREFIX_FOR_LEVEL_UI,//"__nimble__"

                'globalOptionDBValues' => get_option( NIMBLE_OPT_NAME_FOR_GLOBAL_OPTIONS ),// '__nimble_options__'

                'defaultLocalSektionSettingValue' => sek_get_default_location_model(),
                'defaultGlobalSektionSettingValue' => sek_get_default_location_model( NIMBLE_GLOBAL_SKOPE_ID ),

                'settingIdForGlobalSections' => sek_get_seks_setting_id( NIMBLE_GLOBAL_SKOPE_ID ),
                'globalSkopeId' => NIMBLE_GLOBAL_SKOPE_ID,

                //'userSavedSektions' => get_option(NIMBLE_OPT_NAME_FOR_SAVED_SEKTIONS),

                //'presetSections' => sek_get_preset_sections_api_data(), <= fetched on demand in ajax

                'registeredModules' => CZR_Fmk_Base()->registered_modules,

                // Dnd
                'preDropElementClass' => 'sortable-placeholder',
                'dropSelectors' => implode(',', [
                    // 'module' type
                    //'.sek-module-drop-zone-for-first-module',//the drop zone when there's no module or nested sektion in the column
                    //'[data-sek-level="location"]',
                    //'.sek-not-empty-col',// the drop zone when there is at least one module
                    //'.sek-column > .sek-column-inner sek-section',// the drop zone when there is at least one nested section
                    //'.sek-content-module-drop-zone',//between sections
                    '.sek-drop-zone', //This is the selector for all eligible drop zones printed statically or dynamically on dragstart
                    'body',// body will not be eligible for drop, but setting the body as drop zone allows us to fire dragenter / dragover actions, like toggling the "approaching" or "close" css class to real drop zone

                    // 'preset_section' type
                    '.sek-content-preset_section-drop-zone'//between sections
                ]),

                'isSavedSectionEnabled' => defined( 'NIMBLE_SAVED_SECTIONS_ENABLED' ) ? NIMBLE_SAVED_SECTIONS_ENABLED : true,
                'areBetaFeaturesEnabled' => sek_are_beta_features_enabled(),

                'registeredWidgetZones' => array_merge( array( '_none_' => __('Select a widget area', 'nimble-builder') ), sek_get_registered_widget_areas() ),

                'globalOptionsMap' => SEK_Front_Construct::$global_options_map,
                'localOptionsMap' => SEK_Front_Construct::$local_options_map,

                'registeredLocations' => sek_get_locations(),
                // added for the module tree #359
                'moduleCollection' => sek_get_module_collection(),
                'moduleIconPath' => NIMBLE_MODULE_ICON_PATH,

                'hasActiveCachePlugin' => sek_has_active_cache_plugin(),

                // Tiny MCE
                'idOfDetachedTinyMceTextArea' => NIMBLE_DETACHED_TINYMCE_TEXTAREA_ID,
                'tinyMceNimbleEditorStylesheetUrl' => sprintf( '%1$s/assets/czr/sek/css/sek-tinymce-content.css', NIMBLE_BASE_URL ),
                // defaultToolbarBtns is used for the detached tinymce editor
                'defaultToolbarBtns' => "formatselect,fontsizeselect,forecolor,bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,spellchecker,hr,pastetext,removeformat,charmap,outdent,indent,undo,redo",
                // basic btns are used for the heading, the quote content and quote cite
                'basic_btns' => array('forecolor','bold','italic','underline','strikethrough','link','unlink'),
                'basic_btns_nolink' => array('forecolor','bold','italic','underline','strikethrough'),
                // with list introduced for the accordion module https://github.com/presscustomizr/nimble-builder/issues/482
                'basic_btns_with_lists' => array('forecolor','bold','italic','underline','strikethrough','link','unlink', 'bullist', 'numlist'),

                'eligibleForFeedbackNotification' => sek_get_feedback_notif_status(),

                // May 21st, v1.7.5 => back to the local data
                // after problem was reported when fetching data remotely : https://github.com/presscustomizr/nimble-builder/issues/445
                //'presetSectionsModules' => array_keys( sek_get_sections_registration_params_api_data() )
                'presetSectionsModules' => array_keys( sek_get_sections_registration_params() ),

                // array(
                //     '[gfont]Trochut:700',
                //     '[gfont]Sansita:900',
                //     '[gfont]Josefin+Sans:100',
                //     '[gfont]Poppins:regular',
                //     '[cfont]Comic Sans MS,Comic Sans MS,cursive',
                //     '[gfont]Covered+By+Your+Grace:regular'
                // ),
                'alreadyUsedFonts' => get_option( NIMBLE_OPT_NAME_FOR_MOST_USED_FONTS )
            )
        )
    );//wp_localize_script()

    nimble_enqueue_code_editor();
}//sek_enqueue_controls_js_css()





/**
 * Enqueue all code editor assets
 */
function nimble_enqueue_code_editor() {
    wp_enqueue_script( 'code-editor' );
    wp_enqueue_style( 'code-editor' );

    wp_enqueue_script( 'csslint' );
    wp_enqueue_script( 'htmlhint' );
    wp_enqueue_script( 'csslint' );
    wp_enqueue_script( 'jshint' );
    wp_enqueue_script( 'htmlhint-kses' );
    wp_enqueue_script( 'jshint' );
    wp_enqueue_script( 'jsonlint' );
}



/**
 * Enqueue assets needed by the code editor for the given settings.
 *
 * @param array $args {
 *     Args.
 *
 *     @type string   $type       The MIME type of the file to be edited.
 *     @type array    $codemirror Additional CodeMirror setting overrides.
 *     @type array    $csslint    CSSLint rule overrides.
 *     @type array    $jshint     JSHint rule overrides.
 *     @type array    $htmlhint   JSHint rule overrides.
 *     @returns array Settings for the enqueued code editor.
 * }
 */
function nimble_get_code_editor_settings( $args ) {
    $settings = array(
        'codemirror' => array(
            'indentUnit' => 2,
            'tabSize' => 2,
            'indentWithTabs' => true,
            'inputStyle' => 'contenteditable',
            'lineNumbers' => true,
            'lineWrapping' => true,
            'styleActiveLine' => true,
            'continueComments' => true,
            'extraKeys' => array(
                'Ctrl-Space' => 'autocomplete',
                'Ctrl-/' => 'toggleComment',
                'Cmd-/' => 'toggleComment',
                'Alt-F' => 'findPersistent',
                'Ctrl-F'     => 'findPersistent',
                'Cmd-F'      => 'findPersistent',
            ),
            'direction' => 'ltr', // Code is shown in LTR even in RTL languages.
            'gutters' => array(),
        ),
        'csslint' => array(
            'errors' => true, // Parsing errors.
            'box-model' => true,
            'display-property-grouping' => true,
            'duplicate-properties' => true,
            'known-properties' => true,
            'outline-none' => true,
        ),
        'jshint' => array(
            // The following are copied from <https://github.com/WordPress/wordpress-develop/blob/4.8.1/.jshintrc>.
            'boss' => true,
            'curly' => true,
            'eqeqeq' => true,
            'eqnull' => true,
            'es3' => true,
            'expr' => true,
            'immed' => true,
            'noarg' => true,
            'nonbsp' => true,
            'onevar' => true,
            'quotmark' => 'single',
            'trailing' => true,
            'undef' => true,
            'unused' => true,

            'browser' => true,

            'globals' => array(
                '_' => false,
                'Backbone' => false,
                'jQuery' => false,
                'JSON' => false,
                'wp' => false,
            ),
        ),
        'htmlhint' => array(
            'tagname-lowercase' => true,
            'attr-lowercase' => true,
            'attr-value-double-quotes' => false,
            'doctype-first' => false,
            'tag-pair' => true,
            'spec-char-escape' => true,
            'id-unique' => true,
            'src-not-empty' => true,
            'attr-no-duplication' => true,
            'alt-require' => true,
            'space-tab-mixed-disabled' => 'tab',
            'attr-unsafe-chars' => true,
        ),
    );

    $type = '';

    if ( isset( $args['type'] ) ) {
        $type = $args['type'];

        // Remap MIME types to ones that CodeMirror modes will recognize.
        if ( 'application/x-patch' === $type || 'text/x-patch' === $type ) {
            $type = 'text/x-diff';
        }
    } //we do not treat the "file" case


    if ( 'text/css' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'css',
            'lint' => true,
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
        ) );
    } elseif ( 'text/x-scss' === $type || 'text/x-less' === $type || 'text/x-sass' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => $type,
            'lint' => false,
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
        ) );
    } elseif ( 'text/x-diff' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'diff',
        ) );
    } elseif ( 'text/html' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'htmlmixed',
            'lint' => true,
            'autoCloseBrackets' => true,
            'autoCloseTags' => true,
            'matchTags' => array(
                'bothTags' => true,
            ),
        ) );

        if ( ! current_user_can( 'unfiltered_html' ) ) {
            $settings['htmlhint']['kses'] = wp_kses_allowed_html( 'post' );
        }
    } elseif ( 'text/x-gfm' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'gfm',
            'highlightFormatting' => true,
        ) );
    } elseif ( 'application/javascript' === $type || 'text/javascript' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'javascript',
            'lint' => true,
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
        ) );
    } elseif ( false !== strpos( $type, 'json' ) ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => array(
                'name' => 'javascript',
            ),
            'lint' => true,
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
        ) );
        if ( 'application/ld+json' === $type ) {
            $settings['codemirror']['mode']['jsonld'] = true;
        } else {
            $settings['codemirror']['mode']['json'] = true;
        }
    } elseif ( false !== strpos( $type, 'jsx' ) ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'jsx',
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
        ) );
    } elseif ( 'text/x-markdown' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'markdown',
            'highlightFormatting' => true,
        ) );
    } elseif ( 'text/nginx' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'nginx',
        ) );
    } elseif ( 'application/x-httpd-php' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'php',
            'autoCloseBrackets' => true,
            'autoCloseTags' => true,
            'matchBrackets' => true,
            'matchTags' => array(
                'bothTags' => true,
            ),
        ) );
    } elseif ( 'text/x-sql' === $type || 'text/x-mysql' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'sql',
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
        ) );
    } elseif ( false !== strpos( $type, 'xml' ) ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'xml',
            'autoCloseBrackets' => true,
            'autoCloseTags' => true,
            'matchTags' => array(
                'bothTags' => true,
            ),
        ) );
    } elseif ( 'text/x-yaml' === $type ) {
        $settings['codemirror'] = array_merge( $settings['codemirror'], array(
            'mode' => 'yaml',
        ) );
    } else {
        $settings['codemirror']['mode'] = $type;
    }

    if ( ! empty( $settings['codemirror']['lint'] ) ) {
        $settings['codemirror']['gutters'][] = 'CodeMirror-lint-markers';
    }

    // Let settings supplied via args override any defaults.
    foreach ( wp_array_slice_assoc( $args, array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ) ) as $key => $value ) {
        $settings[ $key ] = array_merge(
            $settings[ $key ],
            $value
        );
    }

    $settings = apply_filters( 'nimble_code_editor_settings', $settings, $args );

    if ( empty( $settings ) || empty( $settings['codemirror'] ) ) {
        return false;
    }

    if ( isset( $settings['codemirror']['mode'] ) ) {
        $mode = $settings['codemirror']['mode'];
        if ( is_string( $mode ) ) {
            $mode = array(
                'name' => $mode,
            );
        }
    }

    return $settings;
}




/* ------------------------------------------------------------------------- *
 *  LOCALIZED PARAMS I18N
/* ------------------------------------------------------------------------- */
add_filter( 'nimble-sek-localized-customizer-control-params', '\Nimble\nimble_add_i18n_localized_control_params' );
function nimble_add_i18n_localized_control_params( $params ) {
    return array_merge( $params, array(
        'i18n' => array(
            'Sections' => __( 'Sections', 'nimble-builder'),

            'Nimble Builder' => __('Nimble Builder', 'nimble-builder'),

            "You've reached the maximum number of allowed nested sections." => __("You've reached the maximum number of allowed nested sections.", 'nimble-builder'),
            "You've reached the maximum number of columns allowed in this section." => __( "You've reached the maximum number of columns allowed in this section.", 'nimble-builder'),
            "A section must have at least one column." => __( "A section must have at least one column.", 'nimble-builder'),

            'If this problem locks Nimble Builder, you can try resetting the sections of this page.' => __('If this problem locks Nimble Builder, you can try resetting the sections of this page.', 'nimble-builder'),
            'Reset' => __('Reset', 'nimble-builder'),
            'Reset complete' => __('Reset complete', 'nimble-builder'),
            'Reset failed' => __('Reset failed', 'nimble-builder'),

            // Header button title text
            'Drag and drop content' => __('Drag and drop content', 'nimble-builder'),

            // Generated UI
            'Content Picker' => __('Content Picker', 'nimble-builder'),
            'Pick a module' => __('Pick a module', 'nimble-builder'),
            'Pick a pre-designed section' => __('Pick a pre-designed section', 'nimble-builder'),
            'Select a content type' => __('Select a content type', 'nimble-builder'),

            'Header location only accepts modules and pre-built header sections' => __('Header location only accepts modules and pre-built header sections', 'nimble-builder'),
            'Footer location only accepts modules and pre-built footer sections' => __('Footer location only accepts modules and pre-built footer sections', 'nimble-builder'),
            'You can\'t drop a header section in the footer location' => __('You can\'t drop a header section in the footer location', 'nimble-builder'),
            'You can\'t drop a footer section in the header location' => __('You can\'t drop a footer section in the header location', 'nimble-builder'),

            'Sections for an introduction' => __('Sections for an introduction', 'nimble-builder'),
            'Sections for services and features' => __('Sections for services and features', 'nimble-builder'),
            'About us sections' => __('About us sections', 'nimble-builder'),
            'Contact-us sections' => __('Contact-us sections', 'nimble-builder'),
            'Empty sections with columns layout' => __('Empty sections with columns layout', 'nimble-builder'),
            'Header sections' => __('Header sections', 'nimble-builder'),
            'Footer sections' => __('Footer sections', 'nimble-builder'),

            'Module' => __('Module', 'nimble-builder'),
            'Content for' => __('Content for', 'nimble-builder'),
            'Customize the options for module :' => __('Customize the options for module :', 'nimble-builder'),

            'Layout settings for the' => __('Layout settings for the', 'nimble-builder'),
            'Background settings for the' => __('Background settings for the', 'nimble-builder'),
            'Text settings for the' => __('Text settings for the', 'nimble-builder'),
            'Borders settings for the' => __('Borders settings for the', 'nimble-builder'),
            'Padding and margin settings for the' => __('Padding and margin settings for the', 'nimble-builder'),
            'Height and vertical alignment for the' => __('Height and vertical alignment for the', 'nimble-builder'),
            'Width settings for the' => __('Width settings for the', 'nimble-builder'),
            'Custom anchor ( CSS ID ) and CSS classes for the' => __('Custom anchor ( CSS ID ) and CSS classes for the', 'nimble-builder'),
            'Device visibility settings for the' => __('Device visibility settings for the', 'nimble-builder'),
            'Responsive settings : breakpoint, column direction' => __('Responsive settings : breakpoint, column direction', 'nimble-builder'),

            'Settings for the' => __('Settings for the', 'nimble-builder'),//section / column / module

            'The section cannot be moved higher.' => __('The section cannot be moved higher.', 'nimble-builder'),
            'The section cannot be moved lower.' => __('The section cannot be moved lower.', 'nimble-builder'),

            // UI global and local options
            'Current page options' => __( 'Current page options', 'nimble-builder'),
            'Page template' => __( 'Page template', 'nimble-builder'),
            'This page uses a custom template.' => __( 'This page uses a custom template.', 'nimble-builder'),
            'Page header and footer' => __( 'Page header and footer', 'nimble-builder'),
            'Inner and outer widths' => __( 'Inner and outer widths', 'nimble-builder'),
            'Custom CSS' => __( 'Custom CSS', 'nimble-builder'),
            'Reset the sections in this page' => __( 'Reset the sections in this page', 'nimble-builder'),
            'Reset the sections displayed in global locations' => __( 'Reset the sections displayed in global locations', 'nimble-builder'),
            'Page speed optimizations' => __( 'Page speed optimizations', 'nimble-builder'),

            'Global text options for Nimble sections' => __('Global text options for Nimble sections', 'nimble-builder'),
            'Site wide header and footer' => __( 'Site wide header and footer', 'nimble-builder'),
            'Site wide breakpoint for Nimble sections' => __( 'Site wide breakpoint for Nimble sections', 'nimble-builder'),
            'Site wide inner and outer sections widths' => __( 'Site wide inner and outer sections widths', 'nimble-builder'),

            'Site wide page speed optimizations' => __( 'Site wide page speed optimizations', 'nimble-builder'),
            'Beta features' => __( 'Beta features', 'nimble-builder'),
            'Protect your contact forms with Google reCAPTCHA' => __( 'Protect your contact forms with Google reCAPTCHA', 'nimble-builder'),

            // DEPRECATED
            'Options for the sections of the current page' => __( 'Options for the sections of the current page', 'nimble-builder'),
            'General options applied for the sections site wide' => __( 'General options applied for the sections site wide', 'nimble-builder'),
            //

            'Site wide options' => __( 'Site wide options', 'nimble-builder'),


            // Levels
            'location' => __('location', 'nimble-builder'),
            'section' => __('section', 'nimble-builder'),
            'nested section' => __('nested section', 'nimble-builder'),
            'column' => __('column', 'nimble-builder'),
            'module' => __('module', 'nimble-builder'),

            // DRAG n DROP
            'This browser does not support drag and drop. You might need to update your browser or use another one.' => __('This browser does not support drag and drop. You might need to update your browser or use another one.', 'nimble-builder'),
            'You first need to click on a target ( with a + icon ) in the preview.' => __('You first need to click on a target ( with a + icon ) in the preview.', 'nimble-builder'),
            'Insert here' => __('Insert here', 'nimble-builder'),
            'Insert in a new section' => __('Insert in a new section', 'nimble-builder'),
            'Insert a new section here' => __('Insert a new section here', 'nimble-builder'),

            // DOUBLE CLICK INSERTION


            // MODULES
            'Select a font family' => __('Select a font family', 'nimble-builder'),
            'Web safe fonts' => __('Web safe fonts', 'nimble-builder'),
            'Google fonts' => __('Google fonts', 'nimble-builder'),
            'Already used fonts' => __( 'Already used fonts', 'nimble-builder'),

            'Set a custom url' => __('Set a custom url', 'nimble-builder'),

            'Something went wrong, please refresh this page.' => __('Something went wrong, please refresh this page.', 'nimble-builder'),

            'Select an icon' => __( 'Select an icon', 'nimble-builder' ),

            // Code Editor
            'codeEditorSingular' => __( 'There is %d error in your %s code which might break your site. Please fix it before saving.', 'nimble-builder' ),
            'codeEditorPlural' => __( 'There are %d errors in your %s code which might break your site. Please fix them before saving.', 'nimble-builder' ),

            // Various
            'Settings on desktops' => __('Settings on desktops', 'nimble-builder'),
            'Settings on tablets' => __('Settings on tablets', 'nimble-builder'),
            'Settings on mobiles' => __('Settings on mobiles', 'nimble-builder'),

            // Level Tree
            'No sections to navigate' => __('No sections to navigate', 'nimble-builder'),
            'Remove this element' => __('Remove this element', 'nimble-builder'),

            // Cache plugin warning
            // @see https://github.com/presscustomizr/nimble-builder/issues/395
            'You seem to be using a cache plugin.' => __('You seem to be using a cache plugin.', 'nimble-builder'),
            'It is recommended to disable your cache plugin when customizing your website.' => __('It is recommended to disable your cache plugin when customizing your website.', 'nimble-builder'),

            // Revision history
            // @see https://github.com/presscustomizr/nimble-builder/issues/392
            'Revision history of local sections' => __('Revision history of local sections', 'nimble-builder'),
            'Revision history of global sections' => __('Revision history of global sections', 'nimble-builder'),
            'The revision could not be restored.' => __('The revision could not be restored.', 'nimble-builder'),
            'The revision has been successfully restored.' => __('The revision has been successfully restored.', 'nimble-builder'),
            'Select' => __('Select', 'nimble-builder'),
            'No revision history available for the moment.' => __('No revision history available for the moment.', 'nimble-builder'),
            'This is the current version.' => __('This is the current version.', 'nimble-builder'),
            '(currently published version)' => __('(currently published version)','nimble-builder'),

            // Import / export
            'You need to publish before exporting.' => __( 'Nimble Builder : you need to publish before exporting.', 'nimble-builder'),
            'Export / Import' => __('Export / Import', 'nimble-builder'),
            'Export / Import global sections' => __('Export / Import global sections', 'nimble-builder'),
            'Export failed' => __('Export failed', 'nimble-builder'),
            'Nothing to export.' => __('Nimble Builder : you have nothing to export. Start adding sections to this page!', 'nimble-builder'),
            'Import failed' => __('Import failed', 'nimble-builder'),
            'The current page has no available locations to import Nimble Builder sections.' => __('The current page has no available locations to import Nimble Builder sections.', 'nimble-builder'),
            'Missing file' => __('Missing file', 'nimble-builder'),
            'File successfully imported' => __('File successfully imported', 'nimble-builder'),
            'Import failed, invalid file content' => __('Import failed, invalid file content', 'nimble-builder'),
            'Import failed, file problem' => __('Import failed, file problem', 'nimble-builder'),
            'Some image(s) could not be imported' => __('Some image(s) could not be imported', 'nimble-builder'),
            // 'Module' => __('Module', 'text_doma'),

            // Column width
            'This is a single-column section with a width of 100%. You can act on the internal width of the parent section, or adjust padding and margin.' => __('This is a single-column section with a width of 100%. You can act on the internal width of the parent section, or adjust padding and margin.', 'nimble-builder'),

            // Accordion module
            'Accordion title' => __('Accordion title', 'nimble-builder')
            //'Remove this element' => __('Remove this element', 'text_dom'),
            //'Remove this element' => __('Remove this element', 'text_dom'),
            //'Remove this element' => __('Remove this element', 'text_dom'),
            //'Remove this element' => __('Remove this element', 'text_dom'),
            //'Remove this element' => __('Remove this element', 'text_dom'),


        )//array()
    )//array()
    );//array_merge
}//'nimble_add_i18n_localized_control_params'







// ADD SEKTION VALUES TO EXPORTED DATA IN THE CUSTOMIZER PREVIEW
add_filter( 'skp_json_export_ready_skopes', '\Nimble\add_sektion_values_to_skope_export' );
function add_sektion_values_to_skope_export( $skopes ) {
    if ( ! is_array( $skopes ) ) {
        sek_error_log( __FUNCTION__ . ' error => skp_json_export_ready_skopes filter => the filtered skopes must be an array.' );
    }
    $new_skopes = array();
    foreach ( $skopes as $skp_data ) {
        if ( ! is_array( $skp_data ) || empty( $skp_data['skope'] ) ) {
            sek_error_log( __FUNCTION__ . ' error => missing skope informations' );
            continue;
        }
        if ( 'group' == $skp_data['skope'] ) {
            $new_skopes[] = $skp_data;
            continue;
        }
        if ( ! is_array( $skp_data ) ) {
            error_log( 'skp_json_export_ready_skopes filter => the skope data must be an array.' );
            continue;
        }
        $skope_id = 'global' === $skp_data['skope'] ? NIMBLE_GLOBAL_SKOPE_ID : skp_get_skope_id( $skp_data['skope'] );
        $skp_data[ 'sektions' ] = array(
            'db_values' => sek_get_skoped_seks( $skope_id ),
            'setting_id' => sek_get_seks_setting_id( $skope_id )//nimble___loop_start[skp__post_page_home], nimble___custom_location_id[skp__global]
        );
        // foreach( [
        //     'loop_start',
        //     'loop_end',
        //     'before_content',
        //     'after_content',
        //     'global'
        //     ] as $location ) {
        //     $skp_data[ 'sektions' ][ $location ] = array(
        //         'db_values' => sek_get_skoped_seks( $skope_id, $location ),
        //         'setting_id' => sek_get_seks_setting_id( $skope_id, $location )//nimble___loop_start[skp__post_page_home]
        //     );
        // }
        $new_skopes[] = $skp_data;
    }

    // sek_error_log( '//////////////////// => new_skopes', $new_skopes);

    return $new_skopes;
}



add_action( 'customize_controls_print_footer_scripts', '\Nimble\sek_print_nimble_customizer_tmpl' );
function sek_print_nimble_customizer_tmpl() {
    ?>
    <script type="text/html" id="tmpl-nimble-top-bar">
      <div id="nimble-top-bar" class="czr-preview-notification">
          <div class="sek-add-content">
            <button type="button" class="material-icons" title="<?php _e('Add content', 'nimble-builder'); ?>" data-nimble-state="enabled">
              add_circle_outline<span class="screen-reader-text"><?php _e('Add content', 'nimble-builder'); ?></span>
            </button>
          </div>
          <div class="sek-level-tree">
            <button type="button" class="fas fa-stream" title="<?php _e('Section navigation', 'nimble-builder'); ?>" data-nimble-state="enabled">
              <span class="screen-reader-text"><?php _e('Section navigation', 'nimble-builder'); ?></span>
            </button>
          </div>
          <div class="sek-do-undo">
            <button type="button" class="icon undo" title="<?php _e('Undo', 'nimble-builder'); ?>" data-nimble-history="undo" data-nimble-state="disabled">
              <span class="screen-reader-text"><?php _e('Undo', 'nimble-builder'); ?></span>
            </button>
            <button type="button" class="icon do" title="<?php _e('Redo', 'nimble-builder'); ?>" data-nimble-history="redo" data-nimble-state="disabled">
              <span class="screen-reader-text"><?php _e('Redo', 'nimble-builder'); ?></span>
            </button>
          </div>
          <div class="sek-settings">
            <button type="button" class="fas fa-sliders-h" title="<?php _e('Global settings', 'nimble-builder'); ?>" data-nimble-state="enabled">
              <span class="screen-reader-text"><?php _e('Global settings', 'nimble-builder'); ?></span>
            </button>
          </div>
          <div class="sek-notifications"></div>
          <div class="sek-nimble-doc" data-doc-href="https://docs.presscustomizr.com/collection/334-nimble-builder/?utm_source=usersite&utm_medium=link&utm_campaign=nimble-customizer-topbar">
            <div class="sek-nimble-icon"><img src="<?php echo NIMBLE_BASE_URL.'/assets/img/nimble/nimble_icon.svg?ver='.NIMBLE_VERSION; ?>" alt="<?php _e('Nimble Builder','nimble-builder'); ?>" title="<?php _e('Nimble online documentation', 'nimble-builder'); ?>"/></div>
            <span class="sek-pointer" title="<?php _e('Nimble online documentation', 'nimble-builder'); ?>"><?php _e('Nimble online documentation', 'nimble-builder'); ?></span>
            <button class="far fa-question-circle" type="button" title="<?php _e('Nimble online documentation', 'nimble-builder'); ?>" data-nimble-state="enabled">
              <span class="screen-reader-text"><?php _e('Nimble online documentation', 'nimble-builder'); ?></span>
            </button>
          </div>
      </div>
    </script>

    <script type="text/html" id="tmpl-nimble-top-save-ui">
      <div id="nimble-top-save-ui" class="czr-preview-notification">
          <input id="sek-saved-section-id" type="hidden" value="">
          <div class="sek-section-title">
              <label for="sek-saved-section-title" class="customize-control-title"><?php _e('Section title', 'nimble-builder'); ?></label>
              <input id="sek-saved-section-title" type="text" value="">
          </div>
          <div class="sek-section-description">
              <label for="sek-saved-section-description" class="customize-control-title"><?php _e('Section description', 'nimble-builder'); ?></label>
              <textarea id="sek-saved-section-description" type="text" value=""></textarea>
          </div>
          <div class="sek-section-save">
              <button class="button sek-do-save-section far fa-save" type="button" title="<?php _e('Save', 'nimble-builder'); ?>">
                <?php _e('Save', 'nimble-builder'); ?><span class="screen-reader-text"><?php _e('Save', 'nimble-builder'); ?></span>
              </button>
          </div>
          <button class="button sek-cancel-save far fa-times-circle" type="button" title="<?php _e('Cancel', 'nimble-builder'); ?>">
              <?php _e('Cancel', 'nimble-builder'); ?><span class="screen-reader-text"><?php _e('Cancel', 'nimble-builder'); ?></span>
          </button>
      </div>
    </script>

    <script type="text/html" id="tmpl-nimble-level-tree">
      <div id="nimble-level-tree">
          <div class="sek-tree-wrap"></div>
          <button class="button sek-close-level-tree far fa-times-circle" type="button" title="<?php _e('Close', 'nimble-builder'); ?>">
            <?php _e('Close', 'nimble-builder'); ?><span class="screen-reader-text"><?php _e('Close', 'nimble-builder'); ?></span>
          </button>
      </div>
    </script>

    <script type="text/html" id="tmpl-nimble-feedback-ui">
      <div id="nimble-feedback" data-sek-dismiss-pointer="<?php echo NIMBLE_FEEDBACK_NOTICE_ID; ?>">
          <div class="sek-feedback-step-one">
            <div class="sek-main-feedback-heading">
              <img class="sek-feedback-nimble-icon big" src="<?php echo NIMBLE_BASE_URL.'/assets/img/nimble/nimble_icon.svg?ver='.NIMBLE_VERSION; ?>" alt="<?php _e('Nimble Builder','nimble-builder'); ?>"/>
              <p>Congratulations! You have created several sections with Nimble Builder on your website.</p>
            </div>
            <p>Are you enjoying Nimble Builder ?</p>
            <button class="button sek-feedback-btn sek-neg" data-sek-feedback-action="not_enjoying" type="button">
              <?php _e('Not really', 'nimble-builder'); ?>
            </button>
            <button class="button sek-feedback-btn sek-pos" data-sek-feedback-action="enjoying" type="button">
              <?php _e('Yes !', 'nimble-builder'); ?>
            </button>
          </div>
          <div class="sek-feedback-step-two-not-enjoying">
            <p>Sorry to hear you are not enjoying designing with Nimble Builder. Your feedback would be very useful for us to improve.</p>
            <p>Could you take a minute and let us know what we can do better ?</p>
            <button class="button sek-feedback-btn sek-neg" data-sek-feedback-action="maybe_later" type="button">
              <?php _e('No thanks, maybe later', 'nimble-builder'); ?>
            </button>
            <button class="button sek-feedback-btn sek-pos" data-sek-feedback-action="reporting_problem" data-problem-href="https://wordpress.org/support/plugin/nimble-builder/#new-post" type="button" title="<?php _e('Report a problem', 'nimble-builder'); ?>">
              <?php _e('Report a problem', 'nimble-builder'); ?>
            </button>
            <button class="button sek-feedback-btn sek-already" data-sek-feedback-action="already_did" type="button">
              <?php _e('I already did', 'nimble-builder'); ?>
            </button>
          </div>
          <div class="sek-feedback-step-two-enjoying">
            <span class="sek-stars" data-sek-feedback-action="go_review">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
            <p>Awesome! Could you please leave a rating on WordPress.org ?<br/>
            This would encourage other users discovering Nimble Builder. A huge thanks in advance!</p>
            <p class="sek-signature">-Nicolas, Founder and Lead Developer of Nimble Builder</p>
            <?php
              // Hidden since July 2019
              // @see https://github.com/presscustomizr/nimble-builder/issues/481
              //<button class="button sek-feedback-btn sek-neg" data-sek-feedback-action="maybe_later" type="button">
              //
            ?>
                <?php //_e('No thanks, maybe later', 'text_domain'); ?>
              <?php //</button>
            ?>
            <button class="button sek-feedback-btn sek-pos" data-sek-feedback-action="go_review" type="button">
              <?php _e('OK, you deserve it', 'nimble-builder'); ?>
            </button>
             <button class="button sek-feedback-btn sek-already" data-sek-feedback-action="already_did" type="button">
              <?php _e('I already did', 'nimble-builder'); ?>
            </button>
          </div>
          <div class="sek-feedback-step-three-thanks">
            <img class="sek-feedback-nimble-icon big" src="<?php echo NIMBLE_BASE_URL.'/assets/img/nimble/nimble_icon.svg?ver='.NIMBLE_VERSION; ?>" alt="<?php _e('Nimble Builder','nimble-builder'); ?>"/>
            <p>&middot; Thank you! &middot;</p>
          </div>

          <button class="button sek-feedback-btn sek-close-feedback-ui far fa-times-circle" data-sek-feedback-action="dismiss" title="Dismiss" type="button"></button>
      </div>
    </script>

    <?php // Detached WP Editor => added when coding https://github.com/presscustomizr/nimble-builder/issues/403 ?>
    <div id="czr-customize-content_editor-pane">
      <div data-czr-action="close-tinymce-editor" class="czr-close-editor"><i class="fas fa-arrow-circle-down" title="<?php _e( 'Hide Editor', 'nimble-builder' ); ?>"></i>&nbsp;<span><?php _e( 'Hide Editor', 'nimble-builder');?></span></div>
      <div id="czr-customize-content_editor-dragbar" title="<?php _e('Resize the editor', 'nimble-builder'); ?>">
        <span class="screen-reader-text"><?php _e( 'Resize the editor', 'nimble-builder' ); ?></span>
        <i class="czr-resize-handle fas fa-arrows-alt-v"></i>
      </div>
      <!-- <textarea style="height:250px;width:100%" id="czr-customize-content_editor"></textarea> -->
      <?php
        // the textarea id for the detached editor is 'czr-customize-content_editor'
        // this function generates the <textarea> markup
        sek_setup_nimble_editor( '', NIMBLE_DETACHED_TINYMCE_TEXTAREA_ID , array(
            '_content_editor_dfw' => false,
            'drag_drop_upload' => true,
            'tabfocus_elements' => 'content-html,save-post',
            'editor_height' => 235,
            'default_editor' => 'tinymce',
            'tinymce' => array(
                'resize' => false,
                'wp_autoresize_on' => false,
                'add_unload_trigger' => false,
                'wpautop' => true
            ),
        ) );
      ?>
    </div>
    <?php
}




// Introduced for https://github.com/presscustomizr/nimble-builder/issues/395
function sek_has_active_cache_plugin() {
    if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
        return true;
    }

    $cache_plugins = array(
        'WP Fastest Cache' => 'wp-fastest-cache/wpFastestCache.php',
        'W3 Total Cache' => 'w3-total-cache/w3-total-cache.php',
        'LiteSpeed Cache' => 'litespeed-cache/litespeed-cache.php',
        'WP Super Cache' => 'wp-super-cache/wp-cache.php',
        'Cache Enabler' => 'cache-enabler/cache-enabler.php',
        'Autoptimize' => 'autoptimize/autoptimize.php',
        'CachePress' => 'sg-cachepress/sg-cachepress.php',
        'Comet Cache' => 'comet-cache/comet-cache.php'
    );
    $active = null;
    foreach ( $cache_plugins as $plug_name => $plug_file ) {
        if( !is_null($active) )
          break;
        if ( sek_is_plugin_active( $plug_file ) )
          $active = $plug_name;
    }
    return $active;
}

/**
* HELPER
* Check whether the plugin is active by checking the active_plugins list.
* copy of is_plugin_active declared in wp-admin/includes/plugin.php
*
*
* @param string $plugin Base plugin path from plugins directory.
* @return bool True, if in the active plugins list. False, not in the list.
*/
function sek_is_plugin_active( $plugin ) {
  return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || sek_is_plugin_active_for_network( $plugin );
}


/**
* HELPER
* Check whether the plugin is active for the entire network.
* copy of is_plugin_active_for_network declared in wp-admin/includes/plugin.php
*
* @param string $plugin Base plugin path from plugins directory.
* @return bool True, if active for the network, otherwise false.
*/
function sek_is_plugin_active_for_network( $plugin ) {
  if ( ! is_multisite() )
    return false;

  $plugins = get_site_option( 'active_sitewide_plugins');
  if ( isset($plugins[$plugin]) )
    return true;

  return false;
}



?><?php
namespace Nimble;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'customize_controls_print_footer_scripts', '\Nimble\sek_print_nimble_input_templates' );
function sek_print_nimble_input_templates() {


      // data structure :
      // {
      //     input_type : input_type,
      //     input_data : input_data,
      //     input_id : input_id,
      //     item_model : item_model,
      //     input_tmpl : wp.template( 'nimble-input___' + input_type )
      // }
      ?>
      <script type="text/html" id="tmpl-nimble-input-wrapper">
        <# var css_attr = serverControlParams.css_attr,
            input_data = data.input_data,
            input_type = input_data.input_type,
            is_width_100 = true === input_data['width-100'];


        // some inputs have a width of 100% even if not specified in the input_data
        if ( _.contains( ['color', 'radio', 'textarea'], input_type ) ) {
            is_width_100 = true;
        }
        var width_100_class = is_width_100 ? 'width-100' : '',
            hidden_class = 'hidden' === input_type ? 'hidden' : '',
            data_transport_attr = !_.isEmpty( input_data.transport ) ? 'data-transport="' + input_data.transport + '"' : '',
            input_width = !_.isEmpty( input_data.input_width ) ? input_data.input_width : '';
        #>

        <div class="{{css_attr.sub_set_wrapper}} {{width_100_class}} {{hidden_class}}" data-input-type="{{input_type}}" <# print(data_transport_attr); #>>
          <# if ( input_data.html_before ) { #>
            <div class="czr-html-before"><# print(input_data.html_before); #></div>
          <# } #>
          <# if ( input_data.notice_before_title ) { #>
            <span class="czr-notice"><# print(input_data.notice_before_title); #></span><br/>
          <# } #>
          <# if ( 'hidden' !== input_type ) { #>
            <# var title_width = ! _.isEmpty( input_data.title_width ) ? input_data.title_width : ''; #>
            <div class="customize-control-title {{title_width}}"><# print( input_data.title ); #></div>
          <# } #>
          <# if ( input_data.notice_before ) { #>
            <span class="czr-notice"><# print(input_data.notice_before); #></span>
          <# } #>

          <?php // nested template, see https://stackoverflow.com/questions/8938841/underscore-js-nested-templates#13649447 ?>
          <?php // about print(), see https://underscorejs.org/#template ?>
          <div class="czr-input {{input_width}}"><# if ( _.isFunction( data.input_tmpl ) ) { print(data.input_tmpl(data)); } #></div>

          <# if ( input_data.notice_after ) { #>
            <span class="czr-notice"><# print(input_data.notice_after); #></span>
          <# } #>
          <# if ( input_data.html_after ) { #>
            <div class="czr-html-after"><# print(input_data.html_after); #></div>
          <# } #>
        </div><?php //css_attr.sub_set_wrapper ?>
      </script>



      <?php
      /* ------------------------------------------------------------------------- *
       *  PARTS FOR MULTI-ITEMS MODULES
       *  fixes https://github.com/presscustomizr/nimble-builder/issues/473
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-crud-module-part">
        <# var css_attr = serverControlParams.css_attr; #>
        <button class="{{css_attr.open_pre_add_btn}}"><?php _e('Add New', 'nimble-builder'); ?> <span class="fas fa-plus-square"></span></button>
        <div class="{{css_attr.pre_add_wrapper}}">
          <div class="{{css_attr.pre_add_success}}"><p></p></div>
          <div class="{{css_attr.pre_add_item_content}}">

            <span class="{{css_attr.cancel_pre_add_btn}} button"><?php _e('Cancel', 'nimble-builder'); ?></span> <span class="{{css_attr.add_new_btn}} button"><?php _e('Add it', 'nimble-builder'); ?></span>
          </div>
        </div>
      </script>

      <script type="text/html" id="tmpl-nimble-rud-item-part">
        <# var css_attr = serverControlParams.css_attr, is_sortable_class ='';
          if ( data.is_sortable ) {
              is_sortable_class = css_attr.item_sort_handle;
          }
        #>
        <div class="{{css_attr.item_header}} {{is_sortable_class}} czr-custom-model">
          <# if ( ( true === data.is_sortable ) ) { #>
            <div class="{{css_attr.item_title}} "><h4>{{ data.title }}</h4></div>
          <# } else { #>
            <div class="{{css_attr.item_title}}"><h4>{{ data.title }}</h4></div>
          <# } #>
          <div class="{{css_attr.item_btns}}"><a title="<?php _e('Edit', 'nimble-builder'); ?>" href="javascript:void(0);" class="fas fa-pencil-alt {{css_attr.edit_view_btn}}"></a>&nbsp;<a title="<?php _e('Remove', 'nimble-builder'); ?>" href="javascript:void(0);" class="fas fa-trash {{css_attr.display_alert_btn}}"></a></div>
          <div class="{{css_attr.remove_alert_wrapper}}"></div>
        </div>
      </script>



      <?php
      /* ------------------------------------------------------------------------- *
       *  SUBTEMPLATES
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-subtemplate___range_number">
        <?php
          // we save the int value + unit
          // we want to keep only the numbers when printing the tmpl
          // dev note : value.replace(/\D+/g, '') : ''; not working because remove "." which we might use for em for example
        ?>
        <#
          var item_model = data.item_model,
              input_id = data.input_id,
              rawValue = _.has( item_model, input_id ) ? item_model[input_id] : null,
              value,
              unit;

          value = _.isString( rawValue ) ? rawValue.replace(/px|em|%/g,'') : rawValue;
          unit = _.isString( rawValue ) ? rawValue.replace(/[0-9]|\.|,/g, '') : 'px';
          unit = _.isEmpty( unit ) ? 'px' : unit;
          var _step = _.has( data.input_data, 'step' ) ? 'step="' + data.input_data.step + '"' : '',
              _saved_unit = _.has( item_model, 'unit' ) ? 'data-unit="' + data.input_data.unit + '"' : '',
              _min = _.has( data.input_data, 'min' ) ? 'min="' + data.input_data.min + '"': '',
              _max = _.has( data.input_data, 'max' ) ? 'max="' + data.input_data.max + '"': '';
        #>
        <div class="sek-range-wrapper">
          <input data-czrtype="{{input_id}}" type="hidden" data-sek-unit="{{unit}}"/>
          <input class="sek-range-input" type="range" <# print(_step); #> <# print(_saved_unit); #> <# print(_min); #> <# print(_max); #>/>
        </div>
        <div class="sek-number-wrapper">
            <input class="sek-pm-input" value="{{value}}" type="number" <# print(_step); #> <# print(_min); #> <# print(_max); #> >
        </div>
      </script>


      <script type="text/html" id="tmpl-nimble-subtemplate___unit_picker">
          <div class="sek-unit-wrapper">
            <div aria-label="<?php _e('unit', 'nimble-builder'); ?>" class="sek-ui-button-group" role="group"><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('pixels', 'nimble-builder'); ?>" data-sek-unit="px">px</button><button type="button" aria-pressed="false" class="sek-ui-button" title="em" data-sek-unit="em">em</button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('percents', 'nimble-builder'); ?>" data-sek-unit="%">%</button></div>
          </div>
      </script>

      <script type="text/html" id="tmpl-nimble-subtemplate___number">
        <div class="sek-simple-number-wrapper">
            <input data-czrtype="{{data.input_id}}" class="sek-pm-input" value="{{value}}" type="number"  >
        </div>
      </script>











      <?php
      /* ------------------------------------------------------------------------- *
       * CODE EDITOR
      /* ------------------------------------------------------------------------- */
      ?>
      <?php
      // data structure :
      // {
      //     input_type : input_type,
      //     input_data : input_data,
      //     input_id : input_id,
      //     item_model : item_model,
      //     input_tmpl : wp.template( 'nimble-input___' + input_type )
      // }
      ?>

      <script type="text/html" id="tmpl-nimble-input___code_editor">
        <#
          var item_model = data.item_model,
              input_id = data.input_id,
              value = _.has( item_model, input_id ) ? item_model[input_id] : null,
              code_type = data.input_data.code_type;
        #>
        <textarea data-czrtype="{{input_id}}" data-editor-code-type="{{code_type}}" class="width-100" name="textarea" rows="10" cols=""></textarea>
      </script>



      <script type="text/html" id="tmpl-nimble-input___detached_tinymce_editor">
        <#
          var input_data = data.input_data,
              item_model = data.item_model,
              input_id = data.input_id,
              value = _.has( item_model, input_id ) ? item_model[input_id] : null,
              code_type = data.input_data.code_type;
        #>
        <button type="button" class="button text_editor-button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{input_id}}" data-czr-action="open-tinymce-editor"><?php _e('Edit', 'nimble-builder'); ?></button>&nbsp;
        <button type="button" class="button text_editor-button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{input_id}}" data-czr-action="close-tinymce-editor"><?php _e('Hide editor', 'nimble-builder'); ?></button>
        <input data-czrtype="{{input_id}}" type="hidden" value=""/>
      </script>

      <script type="text/html" id="tmpl-nimble-input___nimble_tinymce_editor">
        <?php
        // Added an id attribute for https://github.com/presscustomizr/nimble-builder/issues/403
        // needed to instantiate wp.editor.initialize(...)
        ?>
        <#
          var item_model = data.item_model,
              input_id = data.input_id,
              value = _.has( item_model, input_id ) ? item_model[input_id] : null;
        #>
        <textarea id="textarea-{{input_id}}" data-czrtype="{{input_id}}" class="width-100" name="textarea" rows="10" cols=""></textarea>
      </script>



      <script type="text/html" id="tmpl-nimble-input___h_alignment">
        <#
          var input_id = data.input_id;
        #>
        <div class="sek-h-align-wrapper">
          <input data-czrtype="{{input_id}}" type="hidden"/>
          <div class="sek-align-icons">
            <div data-sek-align="left" title="<?php _e('Align left', 'nimble-builder'); ?>"><i class="material-icons">format_align_left</i></div>
            <div data-sek-align="center" title="<?php _e('Align center', 'nimble-builder'); ?>"><i class="material-icons">format_align_center</i></div>
            <div data-sek-align="right" title="<?php _e('Align right', 'nimble-builder'); ?>"><i class="material-icons">format_align_right</i></div>
          </div>
        </div><?php // sek-h-align-wrapper ?>
      </script>


      <script type="text/html" id="tmpl-nimble-input___h_text_alignment">
        <#
          var input_id = data.input_id;
        #>
        <div class="sek-h-align-wrapper">
          <input data-czrtype="{{input_id}}" type="hidden"/>
          <div class="sek-align-icons">
            <div data-sek-align="left" title="<?php _e('Align left', 'nimble-builder'); ?>"><i class="material-icons">format_align_left</i></div>
            <div data-sek-align="center" title="<?php _e('Align center', 'nimble-builder'); ?>"><i class="material-icons">format_align_center</i></div>
            <div data-sek-align="right" title="<?php _e('Align right', 'nimble-builder'); ?>"><i class="material-icons">format_align_right</i></div>
            <div data-sek-align="justify" title="<?php _e('Justified', 'nimble-builder'); ?>"><i class="material-icons">format_align_justify</i></div>
          </div>
        </div><?php // sek-h-align-wrapper ?>
      </script>


      <script type="text/html" id="tmpl-nimble-input___nimblecheck">
        <#
          var input_id = data.input_id,
          item_model = data.item_model,
          value = _.has( item_model, input_id ) ? item_model[input_id] : false,
          _checked = ( false != value ) ? "checked=checked" : '',
          _uniqueId = wp.customize.czr_sektions.guid();
        #>
        <div class="nimblecheck-wrap">
          <input id="nimblecheck-{{_uniqueId}}" data-czrtype="{{input_id}}" type="checkbox" <# print(_checked); #> class="nimblecheck-input">
          <label for="nimblecheck-{{_uniqueId}}" class="nimblecheck-label">{{sektionsLocalizedData.i18n['Switch']}}</label>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  ALPHA COLOR
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___wp_color_alpha">
        <#
          var item_model = data.item_model,
              input_id = data.input_id,
              value = _.has( item_model, input_id ) ? item_model[input_id] : null;
        #>
        <input data-czrtype="{{data.input_id}}" class="width-100"  data-alpha="true" type="text" value="{{value}}"></input>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  SIMPLE SELECT : USED FOR SELECT, FONT PICKER, ICON PICKER, ...
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___simpleselect">
        <select data-czrtype="{{data.input_id}}"></select>
      </script>

      <?php
      /* ------------------------------------------------------------------------- *
       *  SIMPLE SELECT WITH DEVICE SWITCHER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___simpleselect_deviceswitcher">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <select></select>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  NUMBER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___number_simple">
        <#
          var number_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'number' );
          if ( _.isFunction( number_tmpl ) ) { print( number_tmpl( data ) ); }
        #>
      </script>

      <?php
      /* ------------------------------------------------------------------------- *
       *  RANGE
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___range_simple">
        <div class="sek-range-with-unit-picker-wrapper sek-no-unit-picker">
          <#
            var range_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'range_number' );
            if ( _.isFunction( range_tmpl ) ) { print( range_tmpl( data ) ); }
          #>
        </div>
      </script>


      <script type="text/html" id="tmpl-nimble-input___range_with_unit_picker">
        <div class="sek-range-with-unit-picker-wrapper">
            <#
              var range_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'range_number' );
              if ( _.isFunction( range_tmpl ) ) { print( range_tmpl( data ) ); }
              var unit_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'unit_picker' );
              if ( _.isFunction( unit_tmpl ) ) { print( unit_tmpl( data ) ); }
            #>
        </div>
      </script>




      <?php
      /* ------------------------------------------------------------------------- *
       *  SPACING
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___spacing">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <div class="sek-spacing-wrapper">
            <div class="sek-pad-marg-inner">
              <div class="sek-pm-top-bottom-wrap sek-flex-justify-center">
                <div class="sek-flex-center-stretch" data-sek-spacing="margin-top" title="<?php _e('Margin top', 'nimble-builder'); ?>">
                  <div class="sek-pm-input-parent">
                    <input class="sek-pm-input" value="" type="number"  >
                  </div>
                </div>
              </div>
              <div class="sek-pm-middle-wrap sek-flex-justify-center">
                <div class="sek-flex-center-stretch sek-pm-margin-left" data-sek-spacing="margin-left" title="<?php _e('Margin left', 'nimble-builder'); ?>">
                  <div class="sek-pm-input-parent">
                    <input class="sek-pm-input" value="" type="number"  >
                  </div>
                </div>

                <div class="sek-pm-padding-wrapper">
                  <div class="sek-flex-justify-center">
                    <div class="sek-flex-center-stretch" data-sek-spacing="padding-top" title="<?php _e('Padding top', 'nimble-builder'); ?>">
                      <div class="sek-pm-input-parent">
                        <input class="sek-pm-input" value="" type="number"  >
                      </div>
                    </div>
                  </div>
                    <div class="sek-flex-justify-center sek-flex-space-between">
                      <div class="sek-flex-center-stretch" data-sek-spacing="padding-left" title="<?php _e('Padding left', 'nimble-builder'); ?>">
                        <div class="sek-pm-input-parent">
                          <input class="sek-pm-input" value="" type="number"  >
                        </div>
                      </div>
                      <div class="sek-flex-center-stretch" data-sek-spacing="padding-right" title="<?php _e('Padding right', 'nimble-builder'); ?>">
                        <div class="sek-pm-input-parent">
                          <input class="sek-pm-input" value="" type="number"  >
                        </div>
                      </div>
                    </div>
                  <div class="sek-flex-justify-center">
                    <div class="sek-flex-center-stretch" data-sek-spacing="padding-bottom" title="<?php _e('Padding bottom', 'nimble-builder'); ?>">
                      <div class="sek-pm-input-parent">
                        <input class="sek-pm-input" value="" type="number"  >
                      </div>
                    </div>
                  </div>
                </div>

                <div class="sek-flex-center-stretch sek-pm-margin-right" data-sek-spacing="margin-right" title="<?php _e('Margin right', 'nimble-builder'); ?>">
                  <div class="sek-pm-input-parent">
                    <input class="sek-pm-input" value="" type="number"  >
                  </div>
                </div>
              </div>

              <div class="sek-pm-top-bottom-wrap sek-flex-justify-center">
                <div class="sek-flex-center-stretch" data-sek-spacing="margin-bottom" title="<?php _e('Margin bottom', 'nimble-builder'); ?>">
                  <div class="sek-pm-input-parent">
                    <input class="sek-pm-input" value="" type="number"  >
                  </div>
                </div>
              </div>
            </div><?php //sek-pad-marg-inner ?>

            <#
              var unit_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'unit_picker' );
              if ( _.isFunction( unit_tmpl ) ) { print( unit_tmpl( data ) ); }
            #>
            <div class="reset-spacing-wrap"><span class="sek-do-reset"><?php _e('Reset all spacing', 'nimble-builder' ); ?></span></div>

        </div><?php // sek-spacing-wrapper ?>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  TEXT
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___text">
        <# var input_data = data.input_data; #>
        <input data-czrtype="{{data.input_id}}" type="text" value="" placeholder="<# print(input_data.placeholder); #>"></input>
      </script>



      <?php
      /* ------------------------------------------------------------------------- *
       *  CONTENT PICKER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___content_picker">
        <span data-czrtype="{{data.input_id}}"></span>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  UPLOAD
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___upload">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <div class="{{serverControlParams.css_attr.img_upload_container}}"></div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  BORDERS
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___borders">
        <div class="sek-borders">
          <div class="sek-border-type-wrapper">
            <div aria-label="unit" class="sek-ui-button-group" role="group"><button type="button" aria-pressed="true" class="sek-ui-button is-selected" title="<?php _e('All', 'nimble-builder'); ?>" data-sek-border-type="_all_"><?php _e('All', 'nimble-builder'); ?></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Left', 'nimble-builder'); ?>" data-sek-border-type="left"><?php _e('Left', 'nimble-builder'); ?></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Top', 'nimble-builder'); ?>" data-sek-border-type="top"><?php _e('Top', 'nimble-builder'); ?></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Right', 'nimble-builder'); ?>" data-sek-border-type="right"><?php _e('Right', 'nimble-builder'); ?></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Bottom', 'nimble-builder'); ?>" data-sek-border-type="bottom"><?php _e('Bottom', 'nimble-builder'); ?></button></div>
          </div>
          <div class="sek-range-unit-wrapper">
            <#
              var range_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'range_number' );
              if ( _.isFunction( range_tmpl ) ) { print( range_tmpl( data ) ); }
              var unit_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'unit_picker' );
              if ( _.isFunction( unit_tmpl ) ) { print( unit_tmpl( data ) ); }
            #>
          </div>
          <div class="sek-color-wrapper">
              <div class="sek-color-picker"><input class="sek-alpha-color-input" data-alpha="true" type="text" value=""/></div>
              <div class="sek-reset-button"><button type="button" class="button sek-reset-button sek-float-right"><?php _e('Reset', 'nimble-builder'); ?></button></div>
          </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  BORDER RADIUS
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___border_radius">
        <div class="sek-borders">
          <div class="sek-border-type-wrapper">
            <div aria-label="unit" class="sek-ui-button-group sek-float-left" role="group"><button type="button" aria-pressed="true" class="sek-ui-button is-selected" title="<?php _e('All', 'nimble-builder'); ?>" data-sek-radius-type="_all_"><?php _e('All', 'nimble-builder'); ?></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Top left', 'nimble-builder'); ?>" data-sek-radius-type="top_left"><i class="material-icons">border_style</i></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Top right', 'nimble-builder'); ?>" data-sek-radius-type="top_right"><i class="material-icons">border_style</i></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Bottom right', 'nimble-builder'); ?>" data-sek-radius-type="bottom_right"><i class="material-icons">border_style</i></button><button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Bottom left', 'nimble-builder'); ?>" data-sek-radius-type="bottom_left"><i class="material-icons">border_style</i></button></div>
            <div class="sek-reset-button"><button type="button" class="button sek-reset-button sek-float-right"><?php _e('Reset', 'nimble-builder'); ?></button></div>
          </div>
          <div class="sek-range-unit-wrapper">
            <#
              var range_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'range_number' );
              if ( _.isFunction( range_tmpl ) ) { print( range_tmpl( data ) ); }
              var unit_tmpl = wp.customize.CZR_Helpers.getInputSubTemplate( 'unit_picker' );
              if ( _.isFunction( unit_tmpl ) ) { print( unit_tmpl( data ) ); }
            #>
          </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  MODULE OPTION SWITCHER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___module_option_switcher">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <div class="sek-content-type-wrapper">
            <div aria-label="<?php _e('Option type', 'nimble-builder'); ?>" class="sek-ui-button-group" role="group">
                <button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Module Content', 'nimble-builder'); ?>" data-sek-option-type="content"><span class="sek-wrap-opt-switch-btn"><i class="material-icons">create</i><span><?php _e('Module Content', 'nimble-builder'); ?></span></span></button>
                <button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Module Settings', 'nimble-builder'); ?>" data-sek-option-type="settings"><span class="sek-wrap-opt-switch-btn"><i class="material-icons">tune</i><span><?php _e('Module Settings', 'nimble-builder'); ?></span></span></button>
            </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  CONTENT SWITCHER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___content_type_switcher">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <div class="sek-content-type-wrapper">
            <div aria-label="<?php _e('Content type', 'nimble-builder'); ?>" class="sek-ui-button-group" role="group">
                <button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Pick a section', 'nimble-builder'); ?>" data-sek-content-type="section"><?php _e('Pick a section', 'nimble-builder'); ?></button>
                <button type="button" aria-pressed="false" class="sek-ui-button" title="<?php _e('Pick a module', 'nimble-builder'); ?>" data-sek-content-type="module"><?php _e('Pick a module', 'nimble-builder'); ?></button>
            </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  MODULE PICKER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___module_picker">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <div class="sek-content-type-wrapper">
            <#
            var icon_img_html = '<i style="color:red">Missing Icon</i>', icon_img_src;

            _.each( sektionsLocalizedData.moduleCollection, function( rawModData ) {
                //normalizes the module params
                var modData = jQuery.extend( true, {}, rawModData ),
                defaultModParams = {
                  'content-type' : 'module',
                  'content-id' : '',
                  'title' : '',
                  'icon' : '',
                  'font_icon' : '',
                  'active' : true
                },
                modData = jQuery.extend( defaultModParams, modData );

                if ( ! _.isEmpty( modData['icon'] ) ) {
                    icon_img_src = sektionsLocalizedData.moduleIconPath + modData['icon'];
                    icon_img_html = '<img draggable="false" title="' + modData['title'] + '" alt="' +  modData['title'] + '" class="nimble-module-icons" src="' + icon_img_src + '"/>';
                } else if ( ! _.isEmpty( modData['font_icon'] ) ) {
                    icon_img_html = modData['font_icon'];
                }
                var title_attr = "<?php _e('Drag and drop or double-click to insert in your chosen target element.', 'nimble-builder'); ?>",
                    font_icon_class = !_.isEmpty( modData['font_icon'] ) ? 'is-font-icon' : '',
                    is_draggable = true !== modData['active'] ? 'false' : 'true';
                if ( true !== modData['active'] ) {
                    title_attr = "<?php _e('Available soon ! This module is currently in beta, you can activate it in Site Wide Options > Beta features', 'nimble-builder'); ?>";
                }
                // "data-sek-eligible-for-module-dropzones" was introduced for https://github.com/presscustomizr/nimble-builder/issues/540
                #>
                <div draggable="{{is_draggable}}" data-sek-eligible-for-module-dropzones="true" data-sek-content-type="{{modData['content-type']}}" data-sek-content-id="{{modData['content-id']}}" title="{{title_attr}}"><div class="sek-module-icon {{font_icon_class}}"><# print(icon_img_html); #></div><div class="sek-module-title"><div class="sek-centered-module-title">{{modData['title']}}</div></div></div>
                <#
            });//_.each
            #>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  SECTION PICKER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___section_picker">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
        <div class="sek-content-type-wrapper">
            <#
            var section_collection = ( data.input_data && data.input_data.section_collection ) ? data.input_data.section_collection : [];
            if ( _.isEmpty( section_collection ) ) {
                wp.customize.errare('Error in js template tmpl-nimble-input___section_picker => missing section collection');
                return;
            }

            _.each( section_collection, function( rawSecParams ) {
                //normalizes the params
                var section_type = 'content',
                secParams = jQuery.extend( true, {}, rawSecParams ),
                defaultParams = {
                  'content-id' : '',
                  'thumb' : '',
                  'title' : '',
                  'section_type' : '',
                  'height': ''
                },
                modData = jQuery.extend( defaultParams, secParams );

                if ( ! _.isEmpty( secParams['section_type'] ) ) {
                    section_type = secParams['section_type'];
                }

                var thumbUrl = [ sektionsLocalizedData.baseUrl , '/assets/img/section_assets/thumbs/', secParams['thumb'] ,  '?ver=' , sektionsLocalizedData.nimbleVersion ].join(''),
                styleAttr = 'background: url(' + thumbUrl  + ') 50% 50% / cover no-repeat;';

                if ( !_.isEmpty(secParams['height']) ) {
                    styleAttr = styleAttr + 'height:' + secParams['height'] + ';';
                }

                #>
                <div draggable="true" data-sek-content-type="preset_section" data-sek-content-id="{{secParams['content-id']}}" style="<# print(styleAttr); #>" title="{{secParams['title']}}" data-sek-section-type="{{section_type}}"><div class="sek-overlay"></div></div>
                <#
            });//_.each
            #>
        </div>
      </script>



      <?php
      /* ------------------------------------------------------------------------- *
       *  BACKGROUND POSITION INPUT
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___bg_position">
        <div class="sek-bg-pos-wrapper">
          <input data-czrtype="{{data.input_id}}" type="hidden"/>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="top_left">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M14.96 16v-1h-1v-1h-1v-1h-1v-1h-1v-1.001h-1V14h-1v-4-1h5v1h-3v.938h1v.999h1v1h1v1.001h1v1h1V16h-1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="top">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M14.969 12v-1h-1v-1h-1v7h-1v-7h-1v1h-1v1h-1v-1.062h1V9.937h1v-1h1V8h1v.937h1v1h1v1.001h1V12h-1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="top_right">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M9.969 16v-1h1v-1h1v-1h1v-1h1v-1.001h1V14h1v-4-1h-1-4v1h3v.938h-1v.999h-1v1h-1v1.001h-1v1h-1V16h1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="left">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M11.469 9.5h-1v1h-1v1h7v1h-7v1h1v1h1v1h-1.063v-1h-1v-1h-1v-1h-.937v-1h.937v-1h1v-1h1v-1h1.063v1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="center">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="right">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M12.469 14.5h1v-1h1v-1h-7v-1h7v-1h-1v-1h-1v-1h1.062v1h1v1h1v1h.938v1h-.938v1h-1v1h-1v1h-1.062v-1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="bottom_left">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M14.969 9v1h-1v1h-1v1h-1v1h-1v1.001h-1V11h-1v5h5v-1h-3v-.938h1v-.999h1v-1h1v-1.001h1v-1h1V9h-1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="bottom">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M9.969 13v1h1v1h1V8h1v7h1v-1h1v-1h1v1.063h-1v.999h-1v1.001h-1V17h-1v-.937h-1v-1.001h-1v-.999h-1V13h1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
          <label class="sek-bg-pos">
            <input type="radio" name="sek-bg-pos" value="bottom_right">
            <span>
              <svg width="24" height="24">
                <path id="sek-pth" fill-rule="evenodd" d="M9.969 9v1h1v1h1v1h1v1h1v1.001h1V11h1v5h-1-4v-1h3v-.938h-1v-.999h-1v-1h-1v-1.001h-1v-1h-1V9h1z" class="sek-svg-bg-pos">
                </path>
              </svg>
            </span>
          </label>
        </div><?php // sek-bg-pos-wrapper ?>
      </script>

      <?php
      /* ------------------------------------------------------------------------- *
       *  BUTTON CHOICE
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___buttons_choice">
        <div class="sek-button-choice-wrapper">
          <input data-czrtype="{{data.input_id}}" type="hidden"/>
          <div aria-label="<?php _e('unit', 'nimble-builder'); ?>" class="sek-ui-button-group sek-float-right" role="group">
              <#
                var input_data = data.input_data;
                if ( _.isEmpty( input_data.choices ) || !_.isObject( input_data.choices ) ) {
                    wp.customize.errare( 'Error in buttons_choice js tmpl => missing or invalid input_data.choices');
                } else {
                    _.each( input_data.choices, function( label, choice ) {
                        #><button type="button" aria-pressed="false" class="sek-ui-button" title="{{label}}" data-sek-choice="{{choice}}">{{label}}</button><#
                    });
                }
              #>
          </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  MULTISELECT, CATEGORY PICKER
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___multiselect">
        <select multiple="multiple" data-czrtype="{{data.input_id}}"></select>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  GRID LAYOUT
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___grid_layout">
        <div class="sek-grid-layout-wrapper">
          <input data-czrtype="{{data.input_id}}" type="hidden"/>
          <div class="sek-grid-icons">
            <div data-sek-grid-layout="list" title="<?php _e('List layout', 'nimble-builder'); ?>"><i class="material-icons">view_list</i></div>
            <div data-sek-grid-layout="grid" title="<?php _e('Grid layout', 'nimble-builder'); ?>"><i class="material-icons">view_module</i></div>
          </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  VERTICAL ALIGNMENT
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___v_alignment">
        <div class="sek-v-align-wrapper">
          <input data-czrtype="{{data.input_id}}" type="hidden"/>
          <div class="sek-align-icons">
            <div data-sek-align="top" title="<?php _e('Align top', 'nimble-builder'); ?>"><i class="material-icons">vertical_align_top</i></div>
            <div data-sek-align="center" title="<?php _e('Align center', 'nimble-builder'); ?>"><i class="material-icons">vertical_align_center</i></div>
            <div data-sek-align="bottom" title="<?php _e('Align bottom', 'nimble-builder'); ?>"><i class="material-icons">vertical_align_bottom</i></div>
          </div>
        </div>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  RESET BUTTON
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___reset_button">
        <div class="sek-button-choice-wrapper">
          <input data-czrtype="{{data.input_id}}" type="hidden"/>
          <button type="button" aria-pressed="false" class="sek-ui-button sek-float-right" title="<?php _e('Reset', 'nimble-builder'); ?>" data-sek-reset-scope="{{data.input_data.scope}}"><?php _e('Reset', 'nimble-builder'); ?></button>
        </div>
      </script>

      <?php
      /* ------------------------------------------------------------------------- *
       *  REFRESH PREVIEW BUTTON
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___refresh_preview_button">
        <div class="sek-button-choice-wrapper">
          <input data-czrtype="{{data.input_id}}" type="hidden"/>
          <button type="button" aria-pressed="false" class="sek-refresh-button sek-float-right button button-primary" title="<?php _e('Refresh preview', 'nimble-builder'); ?>"><?php _e('Refresh preview', 'nimble-builder'); ?></button>
        </div>
      </script>

      <?php
      /* ------------------------------------------------------------------------- *
       *  REVISION HISTORY / HIDDEN
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___revision_history">
        <input data-czrtype="{{data.input_id}}" type="hidden"/>
      </script>


      <?php
      /* ------------------------------------------------------------------------- *
       *  IMPORT / EXPORT
      /* ------------------------------------------------------------------------- */
      ?>
      <script type="text/html" id="tmpl-nimble-input___import_export">
        <div class="sek-export-btn-wrap">
          <div class="customize-control-title width-100"><?php //_e('Export', 'text_doma'); ?></div>
          <button type="button" class="button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-export"><?php _e('Export', 'nimble-builder' ); ?></button>
        </div>
        <div class="sek-import-btn-wrap">
          <div class="customize-control-title width-100"><?php _e('IMPORT', 'nimble-builder'); ?></div>
          <span class="czr-notice"><?php _e('Select the file to import and click on Import button.', 'nimble-builder' ); ?></span>
          <span class="czr-notice"><?php _e('Be sure to import a file generated with Nimble Builder export system.', 'nimble-builder' ); ?></span>
          <?php // <DIALOG FOR LOCAL IMPORT> ?>
          <div class="czr-import-dialog czr-local-import notice notice-info">
              <div class="czr-import-message"><?php _e('Some of the imported sections need a location that is not active on this page. Sections in missing locations will not be rendered. You can continue importing or assign those sections to a contextually active location.', 'nimble-builder' ); ?></div>
              <button type="button" class="button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-import-as-is"><?php _e('Import without modification', 'nimble-builder' ); ?></button>
              <button type="button" class="button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-import-assign"><?php _e('Import in existing locations', 'nimble-builder' ); ?></button>
              <button type="button" class="button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-cancel-import"><?php _e('Cancel import', 'nimble-builder' ); ?></button>
          </div>
          <?php // </DIALOG FOR LOCAL IMPORT> ?>
          <?php // <DIALOG FOR GLOBAL IMPORT> ?>
          <div class="czr-import-dialog czr-global-import notice notice-info">
              <div class="czr-import-message"><?php _e('Some of the imported sections need a location that is not active on this page. For example, if you are importing a global header footer, you need to activate the Nimble site wide header and footer, in "Site wide header and footer" options.', 'nimble-builder' ); ?></div>
               <button type="button" class="button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-import-as-is"><?php _e('Import', 'nimble-builder' ); ?></button>
              <button type="button" class="button" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-cancel-import"><?php _e('Cancel import', 'nimble-builder' ); ?></button>
          </div>
          <?php // </DIALOG FOR GLOBAL IMPORT> ?>
          <div class="sek-uploading"><?php _e( 'Uploading...', 'nimble-builder' ); ?></div>
          <input type="file" name="sek-import-file" class="sek-import-file" />
          <input type="hidden" name="sek-skope" value="{{data.input_data.scope}}" />
          <button type="button" class="button disabled" data-czr-control-id="{{ data.control_id }}" data-czr-input-id="{{data.input_id}}" data-czr-action="sek-pre-import"><?php _e('Import', 'nimble-builder' ); ?></button>

        </div>
        <input data-czrtype="{{data.input_id}}" type="hidden" value="{{data.value}}"/>
      </script>
      <?php
}//sek_print_nimble_input_templates() @hook 'customize_controls_print_footer_scripts'



?><?php
/* ------------------------------------------------------------------------- *
 *  SETUP DYNAMIC SERVER REGISTRATION FOR SETTING
/* ------------------------------------------------------------------------- */
// Fired @'after_setup_theme:20'
if ( ! class_exists( 'SEK_CZR_Dyn_Register' ) ) :
    class SEK_CZR_Dyn_Register {
        static $instance;
        public $sanitize_callbacks = array();// <= will be populated to cache the callbacks when invoking sek_get_module_sanitize_callbacks().

        public static function get_instance( $params ) {
            if ( ! isset( self::$instance ) && ! ( self::$instance instanceof SEK_CZR_Dyn_Register ) )
              self::$instance = new SEK_CZR_Dyn_Register( $params );
            return self::$instance;
        }

        function __construct( $params = array() ) {
            // Schedule the loading the skoped settings class
            add_action( 'customize_register', array( $this, 'load_nimble_setting_class' ) );

            add_filter( 'customize_dynamic_setting_args', array( $this, 'set_dyn_setting_args' ), 10, 2 );
            add_filter( 'customize_dynamic_setting_class', array( $this, 'set_dyn_setting_class') , 10, 3 );
        }//__construct

        //@action 'customize_register'
        function load_nimble_setting_class() {
            require_once(  NIMBLE_BASE_PATH . '/inc/sektions/seks_setting_class.php' );
        }

        //@filter 'customize_dynamic_setting_args'
        function set_dyn_setting_args( $setting_args, $setting_id ) {
            // shall start with "nimble___" or "__nimble_options__"
            if ( 0 === strpos( $setting_id, NIMBLE_OPT_PREFIX_FOR_SEKTION_COLLECTION ) || 0 === strpos( $setting_id, NIMBLE_OPT_NAME_FOR_GLOBAL_OPTIONS ) ) {
                //sek_error_log( 'DYNAMICALLY REGISTERING SEK SETTING => ' . $setting_id,  $setting_args);
                return array(
                    'transport' => 'refresh',
                    'type' => 'option',
                    'default' => array(),
                    //'sanitize_callback'    => array( $this, 'sanitize_callback' )
                    //'validate_callback'    => array( $this, 'validate_callback' )
                );
            } else if ( 0 === strpos( $setting_id, NIMBLE_OPT_PREFIX_FOR_LEVEL_UI ) ) {
                //sek_error_log( 'DYNAMICALLY REGISTERING SEK SETTING => ' . $setting_id,  $setting_args);
                return array(
                    'transport' => 'refresh',
                    'type' => '_nimble_ui_',//won't be saved as is,
                    'default' => array(),
                    'sanitize_callback' => array( $this, 'sanitize_callback' ),
                    'validate_callback' => array( $this, 'validate_callback' )
                );
            }
            return $setting_args;
            //return wp_parse_args( array( 'default' => array() ), $setting_args );
        }


        //@filter 'customize_dynamic_setting_class'
        function set_dyn_setting_class( $class, $setting_id, $args ) {
            // shall start with 'nimble___'
            if ( 0 !== strpos( $setting_id, NIMBLE_OPT_PREFIX_FOR_SEKTION_COLLECTION ) )
              return $class;
            //sek_error_log( 'REGISTERING CLASS DYNAMICALLY for setting =>' . $setting_id );
            return '\Nimble\Nimble_Customizer_Setting';
        }


        // Uses the sanitize_callback function specified on module registration if any
        function sanitize_callback( $setting_data, $setting_instance ) {
            if ( isset( $_POST['location_skope_id'] ) ) {
                $sektionSettingValue = sek_get_skoped_seks( $_POST['location_skope_id'] );
                if ( is_array( $sektionSettingValue ) ) {
                    $sektion_collection = array_key_exists('collection', $sektionSettingValue) ? $sektionSettingValue['collection'] : array();
                    if ( is_array( $sektion_collection ) ) {
                        $model = sek_get_level_model( $setting_instance->id, $sektion_collection );
                        if ( is_array( $model ) && ! empty( $model['module_type'] ) ) {
                            $sanitize_callback = sek_get_registered_module_type_property( $model['module_type'], 'sanitize_callback' );
                            if ( ! empty( $sanitize_callback ) && is_string( $sanitize_callback ) && function_exists( $sanitize_callback ) ) {
                                $setting_data = $sanitize_callback( $setting_data );
                            }
                        }
                    }
                }
            }
            //return new \WP_Error( 'required', __( 'Error in a sektion', 'text_doma' ), $setting_data );
            return $setting_data;
        }

        // Uses the validate_callback function specified on module registration if any
        // @return validity object
        function validate_callback( $validity, $setting_data, $setting_instance ) {
            $validated = true;
            if ( isset( $_POST['location_skope_id'] ) ) {
                $sektionSettingValue = sek_get_skoped_seks( $_POST['location_skope_id'] );
                if ( is_array( $sektionSettingValue ) ) {
                    $sektion_collection = array_key_exists('collection', $sektionSettingValue) ? $sektionSettingValue['collection'] : array();
                    if ( is_array( $sektion_collection ) ) {
                        $model = sek_get_level_model( $setting_instance->id, $sektion_collection );
                        if ( is_array( $model ) && ! empty( $model['module_type'] ) ) {
                            $validate_callback = sek_get_registered_module_type_property( $model['module_type'], 'validate_callback' );
                            if ( ! empty( $validate_callback ) && is_string( $validate_callback ) && function_exists( $validate_callback ) ) {
                                $validated = $validate_callback( $setting_data );
                            }
                        }
                    }
                }
            }
            //return new \WP_Error( 'required', __( 'Error in a sektion', 'text_doma' ), $setting_data );
            if ( true !== $validated ) {
                if ( is_wp_error( $validated ) ) {
                    $validation_msg = $validation_msg->get_error_message();
                    $validity->add(
                        'nimble_validation_error_in_' . $setting_instance->id ,
                        $validation_msg
                    );
                }

            }
            return $validity;
        }


 }//class
endif;

?><?php
function sek_setup_nimble_editor( $content, $editor_id, $settings = array() ) {
  _NIMBLE_Editors::nimble_editor( $content, $editor_id, $settings );
}




/**
 * started from a copy of class-wp-editor.php as of March 2019
 * _NIMBLE_Editors::nimble_editor() is fired with sek_setup_nimble_editor() in hook 'customize_controls_print_footer_scripts'
 * the job of this class is to print the js parameters for the detached tinyMce editor for Nimble
 * the editor is then destroyed and re-instantiated each time a WP text editor module is customized
 * @see api.czrInputMap.detached_tinymce_editor
 */

final class _NIMBLE_Editors {
  public static $mce_locale;

  private static $mce_settings = array();
  private static $qt_settings  = array();
  private static $plugins      = array();
  private static $qt_buttons   = array();
  private static $ext_plugins;
  private static $baseurl;
  private static $first_init;
  private static $this_tinymce       = false;
  private static $this_quicktags     = false;
  private static $has_tinymce        = false;
  private static $has_quicktags      = false;
  private static $has_medialib       = false;
  private static $editor_buttons_css = true;
  private static $drag_drop_upload   = false;
  private static $old_dfw_compat     = false;
  private static $translation;
  private static $tinymce_scripts_printed = false;
  private static $link_dialog_printed     = false;

  private function __construct() {}

  /**
   * Parse default arguments for the editor instance.
   *
   * @param string $editor_id ID for the current editor instance.
   * @param array  $settings {
   *     Array of editor arguments.
   *
   *     @type bool       $wpautop           Whether to use wpautop(). Default true.
   *     @type bool       $media_buttons     Whether to show the Add Media/other media buttons.
   *     @type string     $default_editor    When both TinyMCE and Quicktags are used, set which
   *                                         editor is shown on page load. Default empty.
   *     @type bool       $drag_drop_upload  Whether to enable drag & drop on the editor uploading. Default false.
   *                                         Requires the media modal.
   *     @type string     $textarea_name     Give the textarea a unique name here. Square brackets
   *                                         can be used here. Default $editor_id.
   *     @type int        $textarea_rows     Number rows in the editor textarea. Default 20.
   *     @type string|int $tabindex          Tabindex value to use. Default empty.
   *     @type string     $tabfocus_elements The previous and next element ID to move the focus to
   *                                         when pressing the Tab key in TinyMCE. Default ':prev,:next'.
   *     @type string     $editor_css        Intended for extra styles for both Visual and Text editors.
   *                                         Should include `<style>` tags, and can use "scoped". Default empty.
   *     @type string     $editor_class      Extra classes to add to the editor textarea element. Default empty.
   *     @type bool       $teeny             Whether to output the minimal editor config. Examples include
   *                                         Press This and the Comment editor. Default false.
   *     @type bool       $dfw               Deprecated in 4.1. Since 4.3 used only to enqueue wp-fullscreen-stub.js
   *                                         for backward compatibility.
   *     @type bool|array $tinymce           Whether to load TinyMCE. Can be used to pass settings directly to
   *                                         TinyMCE using an array. Default true.
   *     @type bool|array $quicktags         Whether to load Quicktags. Can be used to pass settings directly to
   *                                         Quicktags using an array. Default true.
   * }
   * @return array Parsed arguments array.
   */
  public static function parse_settings( $editor_id, $settings ) {

    /**
     * Filters the wp_editor() settings.
     *
     * @since 4.0.0
     *
     * @see _NIMBLE_Editors::parse_settings()
     *
     * @param array  $settings  Array of editor arguments.
     * @param string $editor_id ID for the current editor instance.
     */
    $settings = apply_filters( 'nimble_editor_settings', $settings, $editor_id );

    $set = wp_parse_args(
      $settings,
      array(
        // Disable autop if the current post has blocks in it.
        'wpautop'             => ! has_blocks(),
        'media_buttons'       => true,
        'default_editor'      => '',
        'drag_drop_upload'    => false,
        'textarea_name'       => $editor_id,
        'textarea_rows'       => 20,
        'tabindex'            => '',
        'tabfocus_elements'   => ':prev,:next',
        'editor_css'          => '',
        'editor_class'        => '',
        'teeny'               => false,
        'dfw'                 => false,
        '_content_editor_dfw' => false,
        'tinymce'             => true,
        'quicktags'           => true,
      )
    );

    self::$this_tinymce = ( $set['tinymce'] && user_can_richedit() );

    if ( self::$this_tinymce ) {
      if ( false !== strpos( $editor_id, '[' ) ) {
        self::$this_tinymce = false;
        _deprecated_argument( 'wp_editor()', '3.9.0', 'TinyMCE editor IDs cannot have brackets.' );
      }
    }

    self::$this_quicktags = (bool) $set['quicktags'];

    if ( self::$this_tinymce ) {
      self::$has_tinymce = true;
    }

    if ( self::$this_quicktags ) {
      self::$has_quicktags = true;
    }

    if ( $set['dfw'] ) {
      self::$old_dfw_compat = true;
    }

    if ( empty( $set['editor_height'] ) ) {
      return $set;
    }

    if ( 'content' === $editor_id && empty( $set['tinymce']['wp_autoresize_on'] ) ) {
      // A cookie (set when a user resizes the editor) overrides the height.
      $cookie = (int) get_user_setting( 'ed_size' );

      if ( $cookie ) {
        $set['editor_height'] = $cookie;
      }
    }

    if ( $set['editor_height'] < 50 ) {
      $set['editor_height'] = 50;
    } elseif ( $set['editor_height'] > 5000 ) {
      $set['editor_height'] = 5000;
    }

    return $set;
  }

  /**
   * Outputs the HTML for a single instance of the editor.
   *
   * @param string $content The initial content of the editor.
   * @param string $editor_id ID for the textarea and TinyMCE and Quicktags instances (can contain only ASCII letters and numbers).
   * @param array $settings See _NIMBLE_Editors::parse_settings() for description.
   */
  public static function nimble_editor( $content, $editor_id, $settings = array() ) {
    $set            = self::parse_settings( $editor_id, $settings );
    $editor_class   = ' class="' . trim( esc_attr( $set['editor_class'] ) . ' wp-editor-area' ) . '"';
    $tabindex       = $set['tabindex'] ? ' tabindex="' . (int) $set['tabindex'] . '"' : '';
    $default_editor = 'html';
    $buttons        = $autocomplete = '';
    $editor_id_attr = esc_attr( $editor_id );

    if ( $set['drag_drop_upload'] ) {
      self::$drag_drop_upload = true;
    }

    if ( ! empty( $set['editor_height'] ) ) {
      $height = ' style="height: ' . (int) $set['editor_height'] . 'px"';
    } else {
      $height = ' rows="' . (int) $set['textarea_rows'] . '"';
    }

    if ( ! current_user_can( 'upload_files' ) ) {
      $set['media_buttons'] = false;
    }

    if ( self::$this_tinymce ) {
      $autocomplete = ' autocomplete="off"';

      if ( self::$this_quicktags ) {
        $default_editor = $set['default_editor'] ? $set['default_editor'] : wp_default_editor();
        // 'html' is used for the "Text" editor tab.
        if ( 'html' !== $default_editor ) {
          $default_editor = 'tinymce';
        }

        $buttons .= '<button type="button" id="' . $editor_id_attr . '-tmce" class="wp-switch-editor switch-tmce"' .
          ' data-wp-editor-id="' . $editor_id_attr . '">' . _x( 'Visual', 'Name for the Visual editor tab', 'nimble-builder' ) . "</button>\n";
        $buttons .= '<button type="button" id="' . $editor_id_attr . '-html" class="wp-switch-editor switch-html"' .
          ' data-wp-editor-id="' . $editor_id_attr . '">' . _x( 'Text', 'Name for the Text editor tab (formerly HTML)', 'nimble-builder' ) . "</button>\n";
      } else {
        $default_editor = 'tinymce';
      }
    }

    $switch_class = 'html' === $default_editor ? 'html-active' : 'tmce-active';
    $wrap_class   = 'wp-core-ui wp-editor-wrap ' . $switch_class;

    if ( $set['_content_editor_dfw'] ) {
      $wrap_class .= ' has-dfw';
    }

    echo '<div id="wp-' . $editor_id_attr . '-wrap" class="' . $wrap_class . '">';

    if ( self::$editor_buttons_css ) {
      wp_print_styles( 'editor-buttons' );
      self::$editor_buttons_css = false;
    }

    if ( ! empty( $set['editor_css'] ) ) {
      echo $set['editor_css'] . "\n";
    }

    if ( ! empty( $buttons ) || $set['media_buttons'] ) {
      echo '<div id="wp-' . $editor_id_attr . '-editor-tools" class="wp-editor-tools hide-if-no-js">';

      if ( $set['media_buttons'] ) {
        self::$has_medialib = true;

        if ( ! function_exists( 'media_buttons' ) ) {
          include( ABSPATH . 'wp-admin/includes/media.php' );
        }

        echo '<div id="wp-' . $editor_id_attr . '-media-buttons" class="wp-media-buttons">';

        /**
         * Fires after the default media button(s) are displayed.
         *
         * @since 2.5.0
         *
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        do_action( 'media_buttons', $editor_id );
        echo "</div>\n";
      }

      echo '<div class="wp-editor-tabs">' . $buttons . "</div>\n";
      echo "</div>\n";
    }

    $quicktags_toolbar = '';

    if ( self::$this_quicktags ) {
      if ( 'content' === $editor_id && ! empty( $GLOBALS['current_screen'] ) && $GLOBALS['current_screen']->base === 'post' ) {
        $toolbar_id = 'ed_toolbar';
      } else {
        $toolbar_id = 'qt_' . $editor_id_attr . '_toolbar';
      }

      $quicktags_toolbar = '<div id="' . $toolbar_id . '" class="quicktags-toolbar"></div>';
    }

    /**
     * Filters the HTML markup output that displays the editor.
     *
     * @since 2.1.0
     *
     * @param string $output Editor's HTML markup.
     */
    $the_editor = apply_filters(
      'the_nimble_editor',
      '<div id="wp-' . $editor_id_attr . '-editor-container" class="wp-editor-container">' .
      $quicktags_toolbar .
      '<textarea' . $editor_class . $height . $tabindex . $autocomplete . ' cols="40" name="' . esc_attr( $set['textarea_name'] ) . '" ' .
      'id="' . $editor_id_attr . '">%s</textarea></div>'
    );

    // Prepare the content for the Visual or Text editor, only when TinyMCE is used (back-compat).
    if ( self::$this_tinymce ) {
      add_filter( 'the_nimble_editor_content', 'format_for_editor', 10, 2 );
    }

    /**
     * Filters the default editor content.
     *
     * @since 2.1.0
     *
     * @param string $content        Default editor content.
     * @param string $default_editor The default editor for the current user.
     *                               Either 'html' or 'tinymce'.
     */
    $content = apply_filters( 'the_nimble_editor_content', $content, $default_editor );

    // Remove the filter as the next editor on the same page may not need it.
    if ( self::$this_tinymce ) {
      remove_filter( 'the_editor_content', 'format_for_editor' );
    }

    // Back-compat for the `htmledit_pre` and `richedit_pre` filters
    if ( 'html' === $default_editor && has_filter( 'htmledit_pre' ) ) {
      /** This filter is documented in wp-includes/deprecated.php */
      $content = apply_filters_deprecated( 'htmledit_pre', array( $content ), '4.3.0', 'format_for_editor' );
    } elseif ( 'tinymce' === $default_editor && has_filter( 'richedit_pre' ) ) {
      /** This filter is documented in wp-includes/deprecated.php */
      $content = apply_filters_deprecated( 'richedit_pre', array( $content ), '4.3.0', 'format_for_editor' );
    }

    if ( false !== stripos( $content, 'textarea' ) ) {
      $content = preg_replace( '%</textarea%i', '&lt;/textarea', $content );
    }

    printf( $the_editor, $content );
    echo "\n</div>\n\n";

    self::editor_settings( $editor_id, $set );
  }

  /**
   * @global string $tinymce_version
   *
   * @param string $editor_id
   * @param array  $set
   */
  public static function editor_settings( $editor_id, $set ) {
    global $tinymce_version;

    if ( empty( self::$first_init ) ) {
      add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'editor_js' ), 50 );
      add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'force_uncompressed_tinymce' ), 1 );
      add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'enqueue_scripts' ), 1 );
    }

    if ( self::$this_quicktags ) {

      $qtInit = array(
        'id'      => $editor_id,
        'buttons' => '',
      );

      if ( is_array( $set['quicktags'] ) ) {
        $qtInit = array_merge( $qtInit, $set['quicktags'] );
      }

      if ( empty( $qtInit['buttons'] ) ) {
        //$qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close';
        //@nikeo modif
        $qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,li,code';
      }

      if ( $set['_content_editor_dfw'] ) {
        $qtInit['buttons'] .= ',dfw';
      }

      /**
       * Filters the Quicktags settings.
       *
       * @since 3.3.0
       *
       * @param array  $qtInit    Quicktags settings.
       * @param string $editor_id The unique editor ID, e.g. 'content'.
       */
      $qtInit = apply_filters( 'nimble_quicktags_settings', $qtInit, $editor_id );

      self::$qt_settings[ $editor_id ] = $qtInit;

      self::$qt_buttons = array_merge( self::$qt_buttons, explode( ',', $qtInit['buttons'] ) );
    }

    if ( self::$this_tinymce ) {

      if ( empty( self::$first_init ) ) {
        $baseurl     = self::get_baseurl();
        $mce_locale  = self::get_mce_locale();
        $ext_plugins = '';

        if ( $set['teeny'] ) {

          /**
           * Filters the list of teenyMCE plugins.
           *
           * @since 2.7.0
           *
           * @param array  $plugins   An array of teenyMCE plugins.
           * @param string $editor_id Unique editor identifier, e.g. 'content'.
           */
          $plugins = apply_filters( 'nimble_teeny_mce_plugins', array( 'colorpicker', 'lists', 'fullscreen', 'image', 'wordpress', 'wpeditimage', 'wplink' ), $editor_id );
        } else {

          /**
           * Filters the list of TinyMCE external plugins.
           *
           * The filter takes an associative array of external plugins for
           * TinyMCE in the form 'plugin_name' => 'url'.
           *
           * The url should be absolute, and should include the js filename
           * to be loaded. For example:
           * 'myplugin' => 'http://mysite.com/wp-content/plugins/myfolder/mce_plugin.js'.
           *
           * If the external plugin adds a button, it should be added with
           * one of the 'mce_buttons' filters.
           *
           * @since 2.5.0
           *
           * @param array $external_plugins An array of external TinyMCE plugins.
           */
          $mce_external_plugins = apply_filters( 'nimble_mce_external_plugins', array() );

          $plugins = array(
            'charmap',
            'colorpicker',
            'hr',
            'lists',
            'media',
            'paste',
            'tabfocus',
            'textcolor',
            'fullscreen',
            'wordpress',
            'wpautoresize',
            'wpeditimage',
            'wpemoji',
            'wpgallery',
            'wplink',
            'wpdialogs',
            'wptextpattern',
            'wpview',
          );

          if ( ! self::$has_medialib ) {
            $plugins[] = 'image';
          }

          /**
           * Filters the list of default TinyMCE plugins.
           *
           * The filter specifies which of the default plugins included
           * in WordPress should be added to the TinyMCE instance.
           *
           * @since 3.3.0
           *
           * @param array $plugins An array of default TinyMCE plugins.
           */
          $plugins = array_unique( apply_filters( 'nimble_tiny_mce_plugins', $plugins ) );

          if ( ( $key = array_search( 'spellchecker', $plugins ) ) !== false ) {
            // Remove 'spellchecker' from the internal plugins if added with 'tiny_mce_plugins' filter to prevent errors.
            // It can be added with 'mce_external_plugins'.
            unset( $plugins[ $key ] );
          }

          if ( ! empty( $mce_external_plugins ) ) {

            /**
             * Filters the translations loaded for external TinyMCE 3.x plugins.
             *
             * The filter takes an associative array ('plugin_name' => 'path')
             * where 'path' is the include path to the file.
             *
             * The language file should follow the same format as wp_mce_translation(),
             * and should define a variable ($strings) that holds all translated strings.
             *
             * @since 2.5.0
             *
             * @param array $translations Translations for external TinyMCE plugins.
             */
            $mce_external_languages = apply_filters( 'nimble_mce_external_languages', array() );

            $loaded_langs = array();
            $strings      = '';

            if ( ! empty( $mce_external_languages ) ) {
              foreach ( $mce_external_languages as $name => $path ) {
                if ( @is_file( $path ) && @is_readable( $path ) ) {
                  include_once( $path );
                  $ext_plugins   .= $strings . "\n";
                  $loaded_langs[] = $name;
                }
              }
            }

            foreach ( $mce_external_plugins as $name => $url ) {
              if ( in_array( $name, $plugins, true ) ) {
                unset( $mce_external_plugins[ $name ] );
                continue;
              }

              $url                           = set_url_scheme( $url );
              $mce_external_plugins[ $name ] = $url;
              $plugurl                       = dirname( $url );
              $strings                       = '';

              // Try to load langs/[locale].js and langs/[locale]_dlg.js
              if ( ! in_array( $name, $loaded_langs, true ) ) {
                $path = str_replace( content_url(), '', $plugurl );
                $path = WP_CONTENT_DIR . $path . '/langs/';

                if ( function_exists( 'realpath' ) ) {
                  $path = trailingslashit( realpath( $path ) );
                }

                if ( @is_file( $path . $mce_locale . '.js' ) ) {
                  $strings .= @file_get_contents( $path . $mce_locale . '.js' ) . "\n";
                }

                if ( @is_file( $path . $mce_locale . '_dlg.js' ) ) {
                  $strings .= @file_get_contents( $path . $mce_locale . '_dlg.js' ) . "\n";
                }

                if ( 'en' != $mce_locale && empty( $strings ) ) {
                  if ( @is_file( $path . 'en.js' ) ) {
                    $str1     = @file_get_contents( $path . 'en.js' );
                    $strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str1, 1 ) . "\n";
                  }

                  if ( @is_file( $path . 'en_dlg.js' ) ) {
                    $str2     = @file_get_contents( $path . 'en_dlg.js' );
                    $strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str2, 1 ) . "\n";
                  }
                }

                if ( ! empty( $strings ) ) {
                  $ext_plugins .= "\n" . $strings . "\n";
                }
              }

              $ext_plugins .= 'nimbleTinyMCEPreInit.load_ext("' . $plugurl . '", "' . $mce_locale . '");' . "\n";
            }
          }
        }

        self::$plugins     = $plugins;
        self::$ext_plugins = $ext_plugins;

        $settings            = self::default_settings();
        $settings['plugins'] = implode( ',', $plugins );

        if ( ! empty( $mce_external_plugins ) ) {
          $settings['external_plugins'] = wp_json_encode( $mce_external_plugins );
        }

        /** This filter is documented in wp-admin/includes/media.php */
        if ( apply_filters( 'disable_captions', '' ) ) {
          $settings['wpeditimage_disable_captions'] = true;
        }

        $mce_css       = $settings['content_css'];
        $editor_styles = get_editor_stylesheets();

        if ( ! empty( $editor_styles ) ) {
          // Force urlencoding of commas.
          foreach ( $editor_styles as $key => $url ) {
            if ( strpos( $url, ',' ) !== false ) {
              $editor_styles[ $key ] = str_replace( ',', '%2C', $url );
            }
          }

          $mce_css .= ',' . implode( ',', $editor_styles );
        }

        /**
         * Filters the comma-delimited list of stylesheets to load in TinyMCE.
         *
         * @since 2.1.0
         *
         * @param string $stylesheets Comma-delimited list of stylesheets.
         */
        $mce_css = trim( apply_filters( 'nimble_mce_css', $mce_css ), ' ,' );

        if ( ! empty( $mce_css ) ) {
          $settings['content_css'] = $mce_css;
        } else {
          unset( $settings['content_css'] );
        }

        self::$first_init = $settings;
      }

      if ( $set['teeny'] ) {

        /**
         * Filters the list of teenyMCE buttons (Text tab).
         *
         * @since 2.7.0
         *
         * @param array  $buttons   An array of teenyMCE buttons.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mce_buttons   = apply_filters( 'nimble_teeny_mce_buttons', array( 'bold', 'italic', 'underline', 'blockquote', 'strikethrough', 'bullist', 'numlist', 'alignleft', 'aligncenter', 'alignright', 'undo', 'redo', 'link', 'fullscreen' ), $editor_id );
        $mce_buttons_2 = $mce_buttons_3 = $mce_buttons_4 = array();
      } else {
        //@nikeo modif
        //$mce_buttons = array( 'formatselect', 'bold', 'italic', 'bullist', 'numlist', 'blockquote', 'alignleft', 'aligncenter', 'alignright', 'link', 'wp_more', 'spellchecker' );
        $mce_buttons = array( 'formatselect', 'bold', 'italic', 'bullist', 'numlist', 'blockquote', 'alignleft', 'aligncenter', 'alignright', 'link', 'spellchecker' );

        if ( ! wp_is_mobile() ) {
          if ( $set['_content_editor_dfw'] ) {
            $mce_buttons[] = 'dfw';
          } else {
            $mce_buttons[] = 'fullscreen';
          }
        }

        $mce_buttons[] = 'wp_adv';

        /**
         * Filters the first-row list of TinyMCE buttons (Visual tab).
         *
         * @since 2.0.0
         *
         * @param array  $buttons   First-row list of buttons.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mce_buttons = apply_filters( 'nimble_mce_buttons', $mce_buttons, $editor_id );

        $mce_buttons_2 = array( 'strikethrough', 'hr', 'forecolor', 'pastetext', 'removeformat', 'charmap', 'outdent', 'indent', 'undo', 'redo' );

        // @nikeo modif
        // if ( ! wp_is_mobile() ) {
        //   $mce_buttons_2[] = 'wp_help';
        // }

        /**
         * Filters the second-row list of TinyMCE buttons (Visual tab).
         *
         * @since 2.0.0
         *
         * @param array  $buttons   Second-row list of buttons.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mce_buttons_2 = apply_filters( 'nimble_mce_buttons_2', $mce_buttons_2, $editor_id );

        /**
         * Filters the third-row list of TinyMCE buttons (Visual tab).
         *
         * @since 2.0.0
         *
         * @param array  $buttons   Third-row list of buttons.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mce_buttons_3 = apply_filters( 'nimble_mce_buttons_3', array(), $editor_id );

        /**
         * Filters the fourth-row list of TinyMCE buttons (Visual tab).
         *
         * @since 2.5.0
         *
         * @param array  $buttons   Fourth-row list of buttons.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mce_buttons_4 = apply_filters( 'nimble_mce_buttons_4', array(), $editor_id );
      }

      $body_class = $editor_id;

      if ( $post = get_post() ) {
        $body_class .= ' post-type-' . sanitize_html_class( $post->post_type ) . ' post-status-' . sanitize_html_class( $post->post_status );

        if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
          $post_format = get_post_format( $post );
          if ( $post_format && ! is_wp_error( $post_format ) ) {
            $body_class .= ' post-format-' . sanitize_html_class( $post_format );
          } else {
            $body_class .= ' post-format-standard';
          }
        }

        $page_template = get_page_template_slug( $post );

        if ( $page_template !== false ) {
          $page_template = empty( $page_template ) ? 'default' : str_replace( '.', '-', basename( $page_template, '.php' ) );
          $body_class   .= ' page-template-' . sanitize_html_class( $page_template );
        }
      }

      $body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_user_locale() ) ) );

      if ( ! empty( $set['tinymce']['body_class'] ) ) {
        $body_class .= ' ' . $set['tinymce']['body_class'];
        unset( $set['tinymce']['body_class'] );
      }

      $mceInit = array(
        'selector'          => "#$editor_id",
        'wpautop'           => (bool) $set['wpautop'],
        'indent'            => ! $set['wpautop'],
        'toolbar1'          => implode( ',', $mce_buttons ),
        'toolbar2'          => implode( ',', $mce_buttons_2 ),
        'toolbar3'          => implode( ',', $mce_buttons_3 ),
        'toolbar4'          => implode( ',', $mce_buttons_4 ),
        'tabfocus_elements' => $set['tabfocus_elements'],
        'body_class'        => $body_class,
      );

      // Merge with the first part of the init array
      $mceInit = array_merge( self::$first_init, $mceInit );

      if ( is_array( $set['tinymce'] ) ) {
        $mceInit = array_merge( $mceInit, $set['tinymce'] );
      }

      /*
       * For people who really REALLY know what they're doing with TinyMCE
       * You can modify $mceInit to add, remove, change elements of the config
       * before tinyMCE.init. Setting "valid_elements", "invalid_elements"
       * and "extended_valid_elements" can be done through this filter. Best
       * is to use the default cleanup by not specifying valid_elements,
       * as TinyMCE checks against the full set of HTML 5.0 elements and attributes.
       */
      if ( $set['teeny'] ) {

        /**
         * Filters the teenyMCE config before init.
         *
         * @since 2.7.0
         *
         * @param array  $mceInit   An array with teenyMCE config.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mceInit = apply_filters( 'teeny_mce_before_init', $mceInit, $editor_id );
      } else {

        /**
         * Filters the TinyMCE config before init.
         *
         * @since 2.5.0
         *
         * @param array  $mceInit   An array with TinyMCE config.
         * @param string $editor_id Unique editor identifier, e.g. 'content'.
         */
        $mceInit = apply_filters( 'tiny_mce_before_init', $mceInit, $editor_id );
      }

      if ( empty( $mceInit['toolbar3'] ) && ! empty( $mceInit['toolbar4'] ) ) {
        $mceInit['toolbar3'] = $mceInit['toolbar4'];
        $mceInit['toolbar4'] = '';
      }

      self::$mce_settings[ $editor_id ] = $mceInit;
    } // end if self::$this_tinymce
  }

  /**
   * @param array $init
   * @return string
   */
  private static function _parse_init( $init ) {
    $options = '';

    foreach ( $init as $key => $value ) {
      if ( is_bool( $value ) ) {
        $val      = $value ? 'true' : 'false';
        $options .= $key . ':' . $val . ',';
        continue;
      } elseif ( ! empty( $value ) && is_string( $value ) && (
        ( '{' == $value{0} && '}' == $value{strlen( $value ) - 1} ) ||
        ( '[' == $value{0} && ']' == $value{strlen( $value ) - 1} ) ||
        preg_match( '/^\(?function ?\(/', $value ) ) ) {

        $options .= $key . ':' . $value . ',';
        continue;
      }
      $options .= $key . ':"' . $value . '",';
    }

    return '{' . trim( $options, ' ,' ) . '}';
  }

  /**
   *
   * @static
   *
   * @param bool $default_scripts Optional. Whether default scripts should be enqueued. Default false.
   */
  public static function enqueue_scripts( $default_scripts = false ) {
    if ( $default_scripts || self::$has_tinymce ) {
      wp_enqueue_script( 'editor' );
    }

    if ( $default_scripts || self::$has_quicktags ) {
      wp_enqueue_script( 'quicktags' );
      wp_enqueue_style( 'buttons' );
    }

    if ( $default_scripts || in_array( 'wplink', self::$plugins, true ) || in_array( 'link', self::$qt_buttons, true ) ) {
      wp_enqueue_script( 'wplink' );
      wp_enqueue_script( 'jquery-ui-autocomplete' );
    }

    if ( self::$old_dfw_compat ) {
      wp_enqueue_script( 'wp-fullscreen-stub' );
    }

    if ( self::$has_medialib ) {
      add_thickbox();
      wp_enqueue_script( 'media-upload' );
      wp_enqueue_script( 'wp-embed' );
    } elseif ( $default_scripts ) {
      wp_enqueue_script( 'media-upload' );
    }

    /**
     * Fires when scripts and styles are enqueued for the editor.
     *
     * @since 3.9.0
     *
     * @param array $to_load An array containing boolean values whether TinyMCE
     *                       and Quicktags are being loaded.
     */
    do_action(
      'wp_enqueue_editor',
      array(
        'tinymce'   => ( $default_scripts || self::$has_tinymce ),
        'quicktags' => ( $default_scripts || self::$has_quicktags ),
      )
    );
  }

  /**
   * Enqueue all editor scripts.
   * For use when the editor is going to be initialized after page load.
   *
   * @since 4.8.0
   */
  public static function enqueue_default_editor() {
    // We are past the point where scripts can be enqueued properly.
    if ( did_action( 'wp_enqueue_editor' ) ) {
      return;
    }

    self::enqueue_scripts( true );

    // Also add wp-includes/css/editor.css
    wp_enqueue_style( 'editor-buttons' );

    add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'force_uncompressed_tinymce' ), 1 );
    add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'print_default_editor_scripts' ), 45 );

  }

  /**
   * Print (output) all editor scripts and default settings.
   * For use when the editor is going to be initialized after page load.
   *
   * @since 4.8.0
   */
  public static function print_default_editor_scripts() {
    $user_can_richedit = user_can_richedit();

    if ( $user_can_richedit ) {
      $settings = self::default_settings();

      $settings['toolbar1']    = 'bold,italic,bullist,numlist,link';
      $settings['wpautop']     = false;
      $settings['indent']      = true;
      $settings['elementpath'] = false;

      if ( is_rtl() ) {
        $settings['directionality'] = 'rtl';
      }

      // In production all plugins are loaded (they are in wp-editor.js.gz).
      // The 'wpview', 'wpdialogs', and 'media' TinyMCE plugins are not initialized by default.
      // Can be added from js by using the 'wp-before-tinymce-init' event.
      $settings['plugins'] = implode(
        ',',
        array(
          'charmap',
          'colorpicker',
          'hr',
          'lists',
          'paste',
          'tabfocus',
          'textcolor',
          'fullscreen',
          'wordpress',
          'wpautoresize',
          'wpeditimage',
          'wpemoji',
          'wpgallery',
          'wplink',
          'wptextpattern',
        )
      );

      $settings = self::_parse_init( $settings );
    } else {
      $settings = '{}';
    }

    ?>
    <script type="text/javascript">
    window.wp = window.wp || {};
    window.wp.editor = window.wp.editor || {};
    window.wp.editor.getDefaultSettings = function() {
      return {
        tinymce: <?php echo $settings; ?>,
        quicktags: {
          buttons: 'strong,em,link,ul,ol,li,code'
        }
      };
    };

    <?php

    if ( $user_can_richedit ) {
      $suffix  = SCRIPT_DEBUG ? '' : '.min';
      $baseurl = self::get_baseurl();

      ?>
      var nimbleTinyMCEPreInit = {
        baseURL: "<?php echo $baseurl; ?>",
        suffix: "<?php echo $suffix; ?>",
        mceInit: {},
        qtInit: {},
        load_ext: function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
      };
      <?php
    }
    ?>
    </script>
    <?php

    if ( $user_can_richedit ) {
      self::print_tinymce_scripts();
    }

    /**
     * Fires when the editor scripts are loaded for later initialization,
     * after all scripts and settings are printed.
     *
     * @since 4.8.0
     */
    do_action( 'print_default_editor_scripts' );

    self::wp_link_dialog();
  }

  public static function get_mce_locale() {
    if ( empty( self::$mce_locale ) ) {
      $mce_locale       = get_user_locale();
      self::$mce_locale = empty( $mce_locale ) ? 'en' : strtolower( substr( $mce_locale, 0, 2 ) ); // ISO 639-1
    }

    return self::$mce_locale;
  }

  public static function get_baseurl() {
    if ( empty( self::$baseurl ) ) {
      self::$baseurl = includes_url( 'js/tinymce' );
    }

    return self::$baseurl;
  }

  /**
   * Returns the default TinyMCE settings.
   * Doesn't include plugins, buttons, editor selector.
   *
   * @global string $tinymce_version
   *
   * @return array
   */
  private static function default_settings() {
    global $tinymce_version;

    $shortcut_labels = array();

    foreach ( self::get_translation() as $name => $value ) {
      if ( is_array( $value ) ) {
        $shortcut_labels[ $name ] = $value[1];
      }
    }

    $settings = array(
      'theme'                        => 'modern',
      'skin'                         => 'lightgray',
      'language'                     => self::get_mce_locale(),
      'formats'                      => '{' .
        'alignleft: [' .
          '{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"left"}},' .
          '{selector: "img,table,dl.wp-caption", classes: "alignleft"}' .
        '],' .
        'aligncenter: [' .
          '{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"center"}},' .
          '{selector: "img,table,dl.wp-caption", classes: "aligncenter"}' .
        '],' .
        'alignright: [' .
          '{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"right"}},' .
          '{selector: "img,table,dl.wp-caption", classes: "alignright"}' .
        '],' .
        'strikethrough: {inline: "del"}' .
      '}',
      'relative_urls'                => false,
      'remove_script_host'           => false,
      'convert_urls'                 => false,
      'browser_spellcheck'           => true,
      'fix_list_elements'            => true,
      'entities'                     => '38,amp,60,lt,62,gt',
      'entity_encoding'              => 'raw',
      'keep_styles'                  => false,
      'cache_suffix'                 => 'wp-mce-' . $tinymce_version,
      'resize'                       => 'vertical',
      'menubar'                      => false,
      'branding'                     => false,

      // Limit the preview styles in the menu/toolbar
      'preview_styles'               => 'font-family font-size font-weight font-style text-decoration text-transform',

      'end_container_on_empty_block' => true,
      'wpeditimage_html5_captions'   => true,
      'wp_lang_attr'                 => get_bloginfo( 'language' ),
      'wp_keep_scroll_position'      => false,
      'wp_shortcut_labels'           => wp_json_encode( $shortcut_labels ),
    );

    $suffix  = SCRIPT_DEBUG ? '' : '.min';
    $version = 'ver=' . get_bloginfo( 'version' );

    // Default stylesheets
    $settings['content_css'] = includes_url( "css/dashicons$suffix.css?$version" ) . ',' .
      includes_url( "js/tinymce/skins/wordpress/wp-content.css?$version" );

    return $settings;
  }

  private static function get_translation() {
    if ( empty( self::$translation ) ) {
      self::$translation = array(
        // Default TinyMCE strings
        'New document'                         => __( 'New document', 'nimble-builder' ),
        'Formats'                              => _x( 'Formats', 'TinyMCE', 'nimble-builder' ),

        'Headings'                             => _x( 'Headings', 'TinyMCE', 'nimble-builder' ),
        'Heading 1'                            => array( __( 'Heading 1', 'nimble-builder' ), 'access1' ),
        'Heading 2'                            => array( __( 'Heading 2', 'nimble-builder' ), 'access2' ),
        'Heading 3'                            => array( __( 'Heading 3', 'nimble-builder' ), 'access3' ),
        'Heading 4'                            => array( __( 'Heading 4', 'nimble-builder' ), 'access4' ),
        'Heading 5'                            => array( __( 'Heading 5', 'nimble-builder' ), 'access5' ),
        'Heading 6'                            => array( __( 'Heading 6', 'nimble-builder' ), 'access6' ),

        /* translators: block tags */
        'Blocks'                               => _x( 'Blocks', 'TinyMCE', 'nimble-builder' ),
        'Paragraph'                            => array( __( 'Paragraph', 'nimble-builder' ), 'access7' ),
        'Blockquote'                           => array( __( 'Blockquote', 'nimble-builder' ), 'accessQ' ),
        'Div'                                  => _x( 'Div', 'HTML tag', 'nimble-builder' ),
        'Pre'                                  => _x( 'Pre', 'HTML tag', 'nimble-builder' ),
        'Preformatted'                         => _x( 'Preformatted', 'HTML tag', 'nimble-builder' ),
        'Address'                              => _x( 'Address', 'HTML tag', 'nimble-builder' ),

        'Inline'                               => _x( 'Inline', 'HTML elements', 'nimble-builder' ),
        'Underline'                            => array( __( 'Underline', 'nimble-builder' ), 'metaU' ),
        'Strikethrough'                        => array( __( 'Strikethrough', 'nimble-builder' ), 'accessD' ),
        'Subscript'                            => __( 'Subscript', 'nimble-builder' ),
        'Superscript'                          => __( 'Superscript', 'nimble-builder' ),
        'Clear formatting'                     => __( 'Clear formatting', 'nimble-builder' ),
        'Bold'                                 => array( __( 'Bold', 'nimble-builder' ), 'metaB' ),
        'Italic'                               => array( __( 'Italic', 'nimble-builder' ), 'metaI' ),
        'Code'                                 => array( __( 'Code', 'nimble-builder' ), 'accessX' ),
        'Source code'                          => __( 'Source code', 'nimble-builder' ),
        'Font Family'                          => __( 'Font Family', 'nimble-builder' ),
        'Font Sizes'                           => __( 'Font Sizes', 'nimble-builder' ),

        'Align center'                         => array( __( 'Align center', 'nimble-builder' ), 'accessC' ),
        'Align right'                          => array( __( 'Align right', 'nimble-builder' ), 'accessR' ),
        'Align left'                           => array( __( 'Align left', 'nimble-builder' ), 'accessL' ),
        'Justify'                              => array( __( 'Justify', 'nimble-builder' ), 'accessJ' ),
        'Increase indent'                      => __( 'Increase indent', 'nimble-builder' ),
        'Decrease indent'                      => __( 'Decrease indent', 'nimble-builder' ),

        'Cut'                                  => array( __( 'Cut', 'nimble-builder' ), 'metaX' ),
        'Copy'                                 => array( __( 'Copy', 'nimble-builder' ), 'metaC' ),
        'Paste'                                => array( __( 'Paste', 'nimble-builder' ), 'metaV' ),
        'Select all'                           => array( __( 'Select all', 'nimble-builder' ), 'metaA' ),
        'Undo'                                 => array( __( 'Undo', 'nimble-builder' ), 'metaZ' ),
        'Redo'                                 => array( __( 'Redo', 'nimble-builder' ), 'metaY' ),

        'Ok'                                   => __( 'OK', 'nimble-builder' ),
        'Cancel'                               => __( 'Cancel', 'nimble-builder' ),
        'Close'                                => __( 'Close', 'nimble-builder' ),
        'Visual aids'                          => __( 'Visual aids', 'nimble-builder' ),

        'Bullet list'                          => array( __( 'Bulleted list', 'nimble-builder' ), 'accessU' ),
        'Numbered list'                        => array( __( 'Numbered list', 'nimble-builder' ), 'accessO' ),
        'Square'                               => _x( 'Square', 'list style', 'nimble-builder' ),
        'Default'                              => _x( 'Default', 'list style', 'nimble-builder' ),
        'Circle'                               => _x( 'Circle', 'list style', 'nimble-builder' ),
        'Disc'                                 => _x( 'Disc', 'list style', 'nimble-builder' ),
        'Lower Greek'                          => _x( 'Lower Greek', 'list style', 'nimble-builder' ),
        'Lower Alpha'                          => _x( 'Lower Alpha', 'list style', 'nimble-builder' ),
        'Upper Alpha'                          => _x( 'Upper Alpha', 'list style', 'nimble-builder' ),
        'Upper Roman'                          => _x( 'Upper Roman', 'list style', 'nimble-builder' ),
        'Lower Roman'                          => _x( 'Lower Roman', 'list style', 'nimble-builder' ),

        // Anchor plugin
        'Name'                                 => _x( 'Name', 'Name of link anchor (TinyMCE)', 'nimble-builder' ),
        'Anchor'                               => _x( 'Anchor', 'Link anchor (TinyMCE)', 'nimble-builder' ),
        'Anchors'                              => _x( 'Anchors', 'Link anchors (TinyMCE)', 'nimble-builder' ),
        'Id should start with a letter, followed only by letters, numbers, dashes, dots, colons or underscores.' =>
          __( 'Id should start with a letter, followed only by letters, numbers, dashes, dots, colons or underscores.', 'nimble-builder' ),
        'Id'                                   => _x( 'Id', 'Id for link anchor (TinyMCE)', 'nimble-builder' ),

        // Fullpage plugin
        'Document properties'                  => __( 'Document properties', 'nimble-builder' ),
        'Robots'                               => __( 'Robots', 'nimble-builder' ),
        'Title'                                => __( 'Title', 'nimble-builder' ),
        'Keywords'                             => __( 'Keywords', 'nimble-builder' ),
        'Encoding'                             => __( 'Encoding', 'nimble-builder' ),
        'Description'                          => __( 'Description', 'nimble-builder' ),
        'Author'                               => __( 'Author', 'nimble-builder' ),

        // Media, image plugins
        'Image'                                => __( 'Image', 'nimble-builder' ),
        'Insert/edit image'                    => array( __( 'Insert/edit image', 'nimble-builder' ), 'accessM' ),
        'General'                              => __( 'General', 'nimble-builder' ),
        'Advanced'                             => __( 'Advanced', 'nimble-builder' ),
        'Source'                               => __( 'Source', 'nimble-builder' ),
        'Border'                               => __( 'Border', 'nimble-builder' ),
        'Constrain proportions'                => __( 'Constrain proportions', 'nimble-builder' ),
        'Vertical space'                       => __( 'Vertical space', 'nimble-builder' ),
        'Image description'                    => __( 'Image description', 'nimble-builder' ),
        'Style'                                => __( 'Style', 'nimble-builder' ),
        'Dimensions'                           => __( 'Dimensions', 'nimble-builder' ),
        'Insert image'                         => __( 'Insert image', 'nimble-builder' ),
        'Date/time'                            => __( 'Date/time', 'nimble-builder' ),
        'Insert date/time'                     => __( 'Insert date/time', 'nimble-builder' ),
        'Table of Contents'                    => __( 'Table of Contents', 'nimble-builder' ),
        'Insert/Edit code sample'              => __( 'Insert/edit code sample', 'nimble-builder' ),
        'Language'                             => __( 'Language', 'nimble-builder' ),
        'Media'                                => __( 'Media', 'nimble-builder' ),
        'Insert/edit media'                    => __( 'Insert/edit media', 'nimble-builder' ),
        'Poster'                               => __( 'Poster', 'nimble-builder' ),
        'Alternative source'                   => __( 'Alternative source', 'nimble-builder' ),
        'Paste your embed code below:'         => __( 'Paste your embed code below:', 'nimble-builder' ),
        'Insert video'                         => __( 'Insert video', 'nimble-builder' ),
        'Embed'                                => __( 'Embed', 'nimble-builder' ),

        // Each of these have a corresponding plugin
        'Special character'                    => __( 'Special character', 'nimble-builder' ),
        'Right to left'                        => _x( 'Right to left', 'editor button', 'nimble-builder' ),
        'Left to right'                        => _x( 'Left to right', 'editor button', 'nimble-builder' ),
        'Emoticons'                            => __( 'Emoticons', 'nimble-builder' ),
        'Nonbreaking space'                    => __( 'Nonbreaking space', 'nimble-builder' ),
        'Page break'                           => __( 'Page break', 'nimble-builder' ),
        'Paste as text'                        => __( 'Paste as text', 'nimble-builder' ),
        'Preview'                              => __( 'Preview', 'nimble-builder' ),
        'Print'                                => __( 'Print', 'nimble-builder' ),
        'Save'                                 => __( 'Save', 'nimble-builder' ),
        'Fullscreen'                           => __( 'Fullscreen', 'nimble-builder' ),
        'Horizontal line'                      => __( 'Horizontal line', 'nimble-builder' ),
        'Horizontal space'                     => __( 'Horizontal space', 'nimble-builder' ),
        'Restore last draft'                   => __( 'Restore last draft', 'nimble-builder' ),
        'Insert/edit link'                     => array( __( 'Insert/edit link', 'nimble-builder' ), 'metaK' ),
        'Remove link'                          => array( __( 'Remove link', 'nimble-builder' ), 'accessS' ),

        // Link plugin
        'Link'                                 => __( 'Link', 'nimble-builder' ),
        'Insert link'                          => __( 'Insert link', 'nimble-builder' ),
        'Insert/edit link'                     => __( 'Insert/edit link', 'nimble-builder' ),
        'Target'                               => __( 'Target', 'nimble-builder' ),
        'New window'                           => __( 'New window', 'nimble-builder' ),
        'Text to display'                      => __( 'Text to display', 'nimble-builder' ),
        'Url'                                  => __( 'URL', 'nimble-builder' ),
        'The URL you entered seems to be an email address. Do you want to add the required mailto: prefix?' =>
          __( 'The URL you entered seems to be an email address. Do you want to add the required mailto: prefix?', 'nimble-builder' ),
        'The URL you entered seems to be an external link. Do you want to add the required http:// prefix?' =>
          __( 'The URL you entered seems to be an external link. Do you want to add the required http:// prefix?', 'nimble-builder' ),

        'Color'                                => __( 'Color', 'nimble-builder' ),
        'Custom color'                         => __( 'Custom color', 'nimble-builder' ),
        'Custom...'                            => _x( 'Custom...', 'label for custom color', 'nimble-builder' ), // no ellipsis
        'No color'                             => __( 'No color', 'nimble-builder' ),
        'R'                                    => _x( 'R', 'Short for red in RGB', 'nimble-builder' ),
        'G'                                    => _x( 'G', 'Short for green in RGB', 'nimble-builder' ),
        'B'                                    => _x( 'B', 'Short for blue in RGB', 'nimble-builder' ),

        // Spelling, search/replace plugins
        'Could not find the specified string.' => __( 'Could not find the specified string.', 'nimble-builder' ),
        'Replace'                              => _x( 'Replace', 'find/replace', 'nimble-builder' ),
        'Next'                                 => _x( 'Next', 'find/replace', 'nimble-builder' ),
        /* translators: previous */
        'Prev'                                 => _x( 'Prev', 'find/replace', 'nimble-builder' ),
        'Whole words'                          => _x( 'Whole words', 'find/replace', 'nimble-builder' ),
        'Find and replace'                     => __( 'Find and replace', 'nimble-builder' ),
        'Replace with'                         => _x( 'Replace with', 'find/replace', 'nimble-builder' ),
        'Find'                                 => _x( 'Find', 'find/replace', 'nimble-builder' ),
        'Replace all'                          => _x( 'Replace all', 'find/replace', 'nimble-builder' ),
        'Match case'                           => __( 'Match case', 'nimble-builder' ),
        'Spellcheck'                           => __( 'Check Spelling', 'nimble-builder' ),
        'Finish'                               => _x( 'Finish', 'spellcheck', 'nimble-builder' ),
        'Ignore all'                           => _x( 'Ignore all', 'spellcheck', 'nimble-builder' ),
        'Ignore'                               => _x( 'Ignore', 'spellcheck', 'nimble-builder' ),
        'Add to Dictionary'                    => __( 'Add to Dictionary', 'nimble-builder' ),

        // TinyMCE tables
        'Insert table'                         => __( 'Insert table', 'nimble-builder' ),
        'Delete table'                         => __( 'Delete table', 'nimble-builder' ),
        'Table properties'                     => __( 'Table properties', 'nimble-builder' ),
        'Row properties'                       => __( 'Table row properties', 'nimble-builder' ),
        'Cell properties'                      => __( 'Table cell properties', 'nimble-builder' ),
        'Border color'                         => __( 'Border color', 'nimble-builder' ),

        'Row'                                  => __( 'Row', 'nimble-builder' ),
        'Rows'                                 => __( 'Rows', 'nimble-builder' ),
        'Column'                               => _x( 'Column', 'table column', 'nimble-builder' ),
        'Cols'                                 => _x( 'Cols', 'table columns', 'nimble-builder' ),
        'Cell'                                 => _x( 'Cell', 'table cell', 'nimble-builder' ),
        'Header cell'                          => __( 'Header cell', 'nimble-builder' ),
        'Header'                               => _x( 'Header', 'table header', 'nimble-builder' ),
        'Body'                                 => _x( 'Body', 'table body', 'nimble-builder' ),
        'Footer'                               => _x( 'Footer', 'table footer', 'nimble-builder' ),

        'Insert row before'                    => __( 'Insert row before', 'nimble-builder' ),
        'Insert row after'                     => __( 'Insert row after', 'nimble-builder' ),
        'Insert column before'                 => __( 'Insert column before', 'nimble-builder' ),
        'Insert column after'                  => __( 'Insert column after', 'nimble-builder' ),
        'Paste row before'                     => __( 'Paste table row before', 'nimble-builder' ),
        'Paste row after'                      => __( 'Paste table row after', 'nimble-builder' ),
        'Delete row'                           => __( 'Delete row', 'nimble-builder' ),
        'Delete column'                        => __( 'Delete column', 'nimble-builder' ),
        'Cut row'                              => __( 'Cut table row', 'nimble-builder' ),
        'Copy row'                             => __( 'Copy table row', 'nimble-builder' ),
        'Merge cells'                          => __( 'Merge table cells', 'nimble-builder' ),
        'Split cell'                           => __( 'Split table cell', 'nimble-builder' ),

        'Height'                               => __( 'Height', 'nimble-builder' ),
        'Width'                                => __( 'Width', 'nimble-builder' ),
        'Caption'                              => __( 'Caption', 'nimble-builder' ),
        'Alignment'                            => __( 'Alignment', 'nimble-builder' ),
        'H Align'                              => _x( 'H Align', 'horizontal table cell alignment', 'nimble-builder' ),
        'Left'                                 => __( 'Left', 'nimble-builder' ),
        'Center'                               => __( 'Center', 'nimble-builder' ),
        'Right'                                => __( 'Right', 'nimble-builder' ),
        'None'                                 => _x( 'None', 'table cell alignment attribute', 'nimble-builder' ),
        'V Align'                              => _x( 'V Align', 'vertical table cell alignment', 'nimble-builder' ),
        'Top'                                  => __( 'Top', 'nimble-builder' ),
        'Middle'                               => __( 'Middle', 'nimble-builder' ),
        'Bottom'                               => __( 'Bottom', 'nimble-builder' ),

        'Row group'                            => __( 'Row group', 'nimble-builder' ),
        'Column group'                         => __( 'Column group', 'nimble-builder' ),
        'Row type'                             => __( 'Row type', 'nimble-builder' ),
        'Cell type'                            => __( 'Cell type', 'nimble-builder' ),
        'Cell padding'                         => __( 'Cell padding', 'nimble-builder' ),
        'Cell spacing'                         => __( 'Cell spacing', 'nimble-builder' ),
        'Scope'                                => _x( 'Scope', 'table cell scope attribute', 'nimble-builder' ),

        'Insert template'                      => _x( 'Insert template', 'TinyMCE', 'nimble-builder' ),
        'Templates'                            => _x( 'Templates', 'TinyMCE', 'nimble-builder' ),

        'Background color'                     => __( 'Background color', 'nimble-builder' ),
        'Text color'                           => __( 'Text color', 'nimble-builder' ),
        'Show blocks'                          => _x( 'Show blocks', 'editor button', 'nimble-builder' ),
        'Show invisible characters'            => __( 'Show invisible characters', 'nimble-builder' ),

        /* translators: word count */
        'Words: {0}'                           => sprintf( __( 'Words: %s', 'nimble-builder' ), '{0}' ),
        'Paste is now in plain text mode. Contents will now be pasted as plain text until you toggle this option off.' =>
          __( 'Paste is now in plain text mode. Contents will now be pasted as plain text until you toggle this option off.', 'nimble-builder' ) . "\n\n" .
          __( 'If you&#8217;re looking to paste rich content from Microsoft Word, try turning this option off. The editor will clean up text pasted from Word automatically.', 'nimble-builder' ),
        'Rich Text Area. Press ALT-F9 for menu. Press ALT-F10 for toolbar. Press ALT-0 for help' =>
          __( 'Rich Text Area. Press Alt-Shift-H for help.', 'nimble-builder' ),
        'Rich Text Area. Press Control-Option-H for help.' => __( 'Rich Text Area. Press Control-Option-H for help.', 'nimble-builder' ),
        'You have unsaved changes are you sure you want to navigate away?' =>
          __( 'The changes you made will be lost if you navigate away from this page.', 'nimble-builder' ),
        'Your browser doesn\'t support direct access to the clipboard. Please use the Ctrl+X/C/V keyboard shortcuts instead.' =>
          __( 'Your browser does not support direct access to the clipboard. Please use keyboard shortcuts or your browser&#8217;s edit menu instead.', 'nimble-builder' ),

        // TinyMCE menus
        'Insert'                               => _x( 'Insert', 'TinyMCE menu', 'nimble-builder' ),
        'File'                                 => _x( 'File', 'TinyMCE menu', 'nimble-builder' ),
        'Edit'                                 => _x( 'Edit', 'TinyMCE menu', 'nimble-builder' ),
        'Tools'                                => _x( 'Tools', 'TinyMCE menu', 'nimble-builder' ),
        'View'                                 => _x( 'View', 'TinyMCE menu', 'nimble-builder' ),
        'Table'                                => _x( 'Table', 'TinyMCE menu', 'nimble-builder' ),
        'Format'                               => _x( 'Format', 'TinyMCE menu', 'nimble-builder' ),

        // WordPress strings
        'Toolbar Toggle'                       => array( __( 'Toolbar Toggle', 'nimble-builder' ), 'accessZ' ),
        'Insert Read More tag'                 => array( __( 'Insert Read More tag', 'nimble-builder' ), 'accessT' ),
        'Insert Page Break tag'                => array( __( 'Insert Page Break tag', 'nimble-builder' ), 'accessP' ),
        'Read more...'                         => __( 'Read more...', 'nimble-builder' ), // Title on the placeholder inside the editor (no ellipsis)
        'Distraction-free writing mode'        => array( __( 'Distraction-free writing mode', 'nimble-builder' ), 'accessW' ),
        'No alignment'                         => __( 'No alignment', 'nimble-builder' ), // Tooltip for the 'alignnone' button in the image toolbar
        'Remove'                               => __( 'Remove', 'nimble-builder' ), // Tooltip for the 'remove' button in the image toolbar
        'Edit|button'                          => __( 'Edit', 'nimble-builder' ), // Tooltip for the 'edit' button in the image toolbar
        'Paste URL or type to search'          => __( 'Paste URL or type to search', 'nimble-builder' ), // Placeholder for the inline link dialog
        'Apply'                                => __( 'Apply', 'nimble-builder' ), // Tooltip for the 'apply' button in the inline link dialog
        'Link options'                         => __( 'Link options', 'nimble-builder' ), // Tooltip for the 'link options' button in the inline link dialog
        'Visual'                               => _x( 'Visual', 'Name for the Visual editor tab', 'nimble-builder' ), // Editor switch tab label
        'Text'                                 => _x( 'Text', 'Name for the Text editor tab (formerly HTML)', 'nimble-builder' ), // Editor switch tab label
        'Add Media'                            => array( __( 'Add Media', 'nimble-builder' ), 'accessM' ), // Tooltip for the 'Add Media' button in the Block Editor Classic block

        // Shortcuts help modal
        'Keyboard Shortcuts'                   => array( __( 'Keyboard Shortcuts', 'nimble-builder' ), 'accessH' ),
        'Classic Block Keyboard Shortcuts'     => __( 'Classic Block Keyboard Shortcuts', 'nimble-builder' ),
        'Default shortcuts,'                   => __( 'Default shortcuts,', 'nimble-builder' ),
        'Additional shortcuts,'                => __( 'Additional shortcuts,', 'nimble-builder' ),
        'Focus shortcuts:'                     => __( 'Focus shortcuts:', 'nimble-builder' ),
        'Inline toolbar (when an image, link or preview is selected)' => __( 'Inline toolbar (when an image, link or preview is selected)', 'nimble-builder' ),
        'Editor menu (when enabled)'           => __( 'Editor menu (when enabled)', 'nimble-builder' ),
        'Editor toolbar'                       => __( 'Editor toolbar', 'nimble-builder' ),
        'Elements path'                        => __( 'Elements path', 'nimble-builder' ),
        'Ctrl + Alt + letter:'                 => __( 'Ctrl + Alt + letter:', 'nimble-builder' ),
        'Shift + Alt + letter:'                => __( 'Shift + Alt + letter:', 'nimble-builder' ),
        'Cmd + letter:'                        => __( 'Cmd + letter:', 'nimble-builder' ),
        'Ctrl + letter:'                       => __( 'Ctrl + letter:', 'nimble-builder' ),
        'Letter'                               => __( 'Letter', 'nimble-builder' ),
        'Action'                               => __( 'Action', 'nimble-builder' ),
        'Warning: the link has been inserted but may have errors. Please test it.' => __( 'Warning: the link has been inserted but may have errors. Please test it.', 'nimble-builder' ),
        'To move focus to other buttons use Tab or the arrow keys. To return focus to the editor press Escape or use one of the buttons.' =>
          __( 'To move focus to other buttons use Tab or the arrow keys. To return focus to the editor press Escape or use one of the buttons.', 'nimble-builder' ),
        'When starting a new paragraph with one of these formatting shortcuts followed by a space, the formatting will be applied automatically. Press Backspace or Escape to undo.' =>
          __( 'When starting a new paragraph with one of these formatting shortcuts followed by a space, the formatting will be applied automatically. Press Backspace or Escape to undo.', 'nimble-builder' ),
        'The following formatting shortcuts are replaced when pressing Enter. Press Escape or the Undo button to undo.' =>
          __( 'The following formatting shortcuts are replaced when pressing Enter. Press Escape or the Undo button to undo.', 'nimble-builder' ),
        'The next group of formatting shortcuts are applied as you type or when you insert them around plain text in the same paragraph. Press Escape or the Undo button to undo.' =>
          __( 'The next group of formatting shortcuts are applied as you type or when you insert them around plain text in the same paragraph. Press Escape or the Undo button to undo.', 'nimble-builder' ),
      );
    }

    /*
    Imagetools plugin (not included):
      'Edit image' => __( 'Edit image' ),
      'Image options' => __( 'Image options' ),
      'Back' => __( 'Back' ),
      'Invert' => __( 'Invert' ),
      'Flip horizontally' => __( 'Flip horizontally' ),
      'Flip vertically' => __( 'Flip vertically' ),
      'Crop' => __( 'Crop' ),
      'Orientation' => __( 'Orientation' ),
      'Resize' => __( 'Resize' ),
      'Rotate clockwise' => __( 'Rotate clockwise' ),
      'Rotate counterclockwise' => __( 'Rotate counterclockwise' ),
      'Sharpen' => __( 'Sharpen' ),
      'Brightness' => __( 'Brightness' ),
      'Color levels' => __( 'Color levels' ),
      'Contrast' => __( 'Contrast' ),
      'Gamma' => __( 'Gamma' ),
      'Zoom in' => __( 'Zoom in' ),
      'Zoom out' => __( 'Zoom out' ),
    */

    return self::$translation;
  }

  /**
   * Translates the default TinyMCE strings and returns them as JSON encoded object ready to be loaded with tinymce.addI18n(),
   * or as JS snippet that should run after tinymce.js is loaded.
   *
   * @param string $mce_locale The locale used for the editor.
   * @param bool $json_only optional Whether to include the JavaScript calls to tinymce.addI18n() and tinymce.ScriptLoader.markDone().
   * @return string Translation object, JSON encoded.
   */
  public static function wp_mce_translation( $mce_locale = '', $json_only = false ) {
    if ( ! $mce_locale ) {
      $mce_locale = self::get_mce_locale();
    }

    $mce_translation = self::get_translation();

    foreach ( $mce_translation as $name => $value ) {
      if ( is_array( $value ) ) {
        $mce_translation[ $name ] = $value[0];
      }
    }

    /**
     * Filters translated strings prepared for TinyMCE.
     *
     * @since 3.9.0
     *
     * @param array  $mce_translation Key/value pairs of strings.
     * @param string $mce_locale      Locale.
     */
    $mce_translation = apply_filters( 'wp_mce_translation', $mce_translation, $mce_locale );

    foreach ( $mce_translation as $key => $value ) {
      // Remove strings that are not translated.
      if ( $key === $value ) {
        unset( $mce_translation[ $key ] );
        continue;
      }

      if ( false !== strpos( $value, '&' ) ) {
        $mce_translation[ $key ] = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
      }
    }

    // Set direction
    if ( is_rtl() ) {
      $mce_translation['_dir'] = 'rtl';
    }

    if ( $json_only ) {
      return wp_json_encode( $mce_translation );
    }

    $baseurl = self::get_baseurl();

    return "tinymce.addI18n( '$mce_locale', " . wp_json_encode( $mce_translation ) . ");\n" .
      "tinymce.ScriptLoader.markDone( '$baseurl/langs/$mce_locale.js' );\n";
  }

  /**
   * Force uncompressed TinyMCE when a custom theme has been defined.
   *
   * The compressed TinyMCE file cannot deal with custom themes, so this makes
   * sure that we use the uncompressed TinyMCE file if a theme is defined.
   * Even if we are on a production environment.
   */
  public static function force_uncompressed_tinymce() {
    $has_custom_theme = false;
    foreach ( self::$mce_settings as $init ) {
      if ( ! empty( $init['theme_url'] ) ) {
        $has_custom_theme = true;
        break;
      }
    }

    if ( ! $has_custom_theme ) {
      return;
    }

    $wp_scripts = wp_scripts();

    $wp_scripts->remove( 'wp-tinymce' );
    wp_register_tinymce_scripts( $wp_scripts, true );
  }

  /**
   * Print (output) the main TinyMCE scripts.
   *
   * @since 4.8.0
   *
   * @global string $tinymce_version
   * @global bool   $concatenate_scripts
   * @global bool   $compress_scripts
   */
  public static function print_tinymce_scripts() {
    global $concatenate_scripts;

    if ( self::$tinymce_scripts_printed ) {
      return;
    }

    self::$tinymce_scripts_printed = true;

    if ( ! isset( $concatenate_scripts ) ) {
      script_concat_settings();
    }

    wp_print_scripts( array( 'wp-tinymce' ) );

    echo "<script type='text/javascript'>\n" . self::wp_mce_translation() . "</script>\n";
  }

  /**
   * Print (output) the TinyMCE configuration and initialization scripts.
   *
   * @global string $tinymce_version
   */
  public static function editor_js() {
    global $tinymce_version;

    $tmce_on = ! empty( self::$mce_settings );
    $mceInit = $qtInit = '';

    if ( $tmce_on ) {
      foreach ( self::$mce_settings as $editor_id => $init ) {
        $options  = self::_parse_init( $init );
        $mceInit .= "'$editor_id':{$options},";
      }
      $mceInit = '{' . trim( $mceInit, ',' ) . '}';
    } else {
      $mceInit = '{}';
    }

    if ( ! empty( self::$qt_settings ) ) {
      foreach ( self::$qt_settings as $editor_id => $init ) {
        $options = self::_parse_init( $init );
        $qtInit .= "'$editor_id':{$options},";
      }
      $qtInit = '{' . trim( $qtInit, ',' ) . '}';
    } else {
      $qtInit = '{}';
    }

    $ref = array(
      'plugins'  => implode( ',', self::$plugins ),
      'theme'    => 'modern',
      'language' => self::$mce_locale,
    );

    $suffix  = SCRIPT_DEBUG ? '' : '.min';
    $baseurl = self::get_baseurl();
    $version = 'ver=' . $tinymce_version;

    /**
     * Fires immediately before the TinyMCE settings are printed.
     *
     * @since 3.2.0
     *
     * @param array $mce_settings TinyMCE settings array.
     */
    do_action( 'before_wp_tiny_mce', self::$mce_settings );
    ?>

    <script type="text/javascript">
    nimbleTinyMCEPreInit = {
      baseURL: "<?php echo $baseurl; ?>",
      suffix: "<?php echo $suffix; ?>",
      <?php

      if ( self::$drag_drop_upload ) {
        echo 'dragDropUpload: true,';
      }

      ?>
      mceInit: <?php echo $mceInit; ?>,
      qtInit: <?php echo $qtInit; ?>,
      ref: <?php echo self::_parse_init( $ref ); ?>,
      load_ext: function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
    };
    </script>
    <?php

    if ( $tmce_on ) {
      self::print_tinymce_scripts();

      if ( self::$ext_plugins ) {
        // Load the old-format English strings to prevent unsightly labels in old style popups
        echo "<script type='text/javascript' src='{$baseurl}/langs/wp-langs-en.js?$version'></script>\n";
      }
    }

    /**
     * Fires after tinymce.js is loaded, but before any TinyMCE editor
     * instances are created.
     *
     * @since 3.9.0
     *
     * @param array $mce_settings TinyMCE settings array.
     */
    do_action( 'wp_tiny_mce_init', self::$mce_settings );

    ?>
    <script type="text/javascript">
    <?php

    if ( self::$ext_plugins ) {
      echo self::$ext_plugins . "\n";
    }

    if ( ! is_admin() ) {
      echo 'var ajaxurl = "' . admin_url( 'admin-ajax.php', 'relative' ) . '";';
    }

    ?>

    ( function() {
      var init, id, $wrap;

      if ( typeof tinymce !== 'undefined' ) {
        if ( tinymce.Env.ie && tinymce.Env.ie < 11 ) {
          tinymce.$( '.wp-editor-wrap ' ).removeClass( 'tmce-active' ).addClass( 'html-active' );
          return;
        }

        for ( id in nimbleTinyMCEPreInit.mceInit ) {
          init = nimbleTinyMCEPreInit.mceInit[id];
          $wrap = tinymce.$( '#wp-' + id + '-wrap' );

          if ( ( $wrap.hasClass( 'tmce-active' ) || ! nimbleTinyMCEPreInit.qtInit.hasOwnProperty( id ) ) && ! init.wp_skip_init ) {
            tinymce.init( init );
            if ( ! window.wpActiveEditor ) {
              window.wpActiveEditor = id;//<= where is this used ?
            }
          }
        }
      }

      if ( typeof quicktags !== 'undefined' ) {
        for ( id in nimbleTinyMCEPreInit.qtInit ) {
          quicktags( nimbleTinyMCEPreInit.qtInit[id] );

          if ( ! window.wpActiveEditor ) {
            window.wpActiveEditor = id;//<= where is this used ?
          }
        }
      }
    }());
    </script>
    <?php

    if ( in_array( 'wplink', self::$plugins, true ) || in_array( 'link', self::$qt_buttons, true ) ) {
      self::wp_link_dialog();
    }

    /**
     * Fires after any core TinyMCE editor instances are created.
     *
     * @since 3.2.0
     *
     * @param array $mce_settings TinyMCE settings array.
     */
    do_action( 'after_wp_tiny_mce', self::$mce_settings );
  }

  /**
   * Outputs the HTML for distraction-free writing mode.
   *
   * @since 3.2.0
   * @deprecated 4.3.0
   */
  public static function wp_fullscreen_html() {
    _deprecated_function( __FUNCTION__, '4.3.0' );
  }

  /**
   * Performs post queries for internal linking.
   *
   * @since 3.1.0
   *
   * @param array $args Optional. Accepts 'pagenum' and 's' (search) arguments.
   * @return false|array Results.
   */
  public static function wp_link_query( $args = array() ) {
    $pts      = get_post_types( array( 'public' => true ), 'objects' );
    $pt_names = array_keys( $pts );

    $query = array(
      'post_type'              => $pt_names,
      'suppress_filters'       => true,
      'update_post_term_cache' => false,
      'update_post_meta_cache' => false,
      'post_status'            => 'publish',
      'posts_per_page'         => 20,
    );

    $args['pagenum'] = isset( $args['pagenum'] ) ? absint( $args['pagenum'] ) : 1;

    if ( isset( $args['s'] ) ) {
      $query['s'] = $args['s'];
    }

    $query['offset'] = $args['pagenum'] > 1 ? $query['posts_per_page'] * ( $args['pagenum'] - 1 ) : 0;

    /**
     * Filters the link query arguments.
     *
     * Allows modification of the link query arguments before querying.
     *
     * @see WP_Query for a full list of arguments
     *
     * @since 3.7.0
     *
     * @param array $query An array of WP_Query arguments.
     */
    $query = apply_filters( 'wp_link_query_args', $query );

    // Do main query.
    $get_posts = new WP_Query;
    $posts     = $get_posts->query( $query );

    // Build results.
    $results = array();
    foreach ( $posts as $post ) {
      if ( 'post' == $post->post_type ) {
        $info = mysql2date( __( 'Y/m/d', 'nimble-builder' ), $post->post_date );
      } else {
        $info = $pts[ $post->post_type ]->labels->singular_name;
      }

      $results[] = array(
        'ID'        => $post->ID,
        'title'     => trim( esc_html( strip_tags( get_the_title( $post ) ) ) ),
        'permalink' => get_permalink( $post->ID ),
        'info'      => $info,
      );
    }

    /**
     * Filters the link query results.
     *
     * Allows modification of the returned link query results.
     *
     * @since 3.7.0
     *
     * @see 'wp_link_query_args' filter
     *
     * @param array $results {
     *     An associative array of query results.
     *
     *     @type array {
     *         @type int    $ID        Post ID.
     *         @type string $title     The trimmed, escaped post title.
     *         @type string $permalink Post permalink.
     *         @type string $info      A 'Y/m/d'-formatted date for 'post' post type,
     *                                 the 'singular_name' post type label otherwise.
     *     }
     * }
     * @param array $query  An array of WP_Query arguments.
     */
    $results = apply_filters( 'wp_link_query', $results, $query );

    return ! empty( $results ) ? $results : false;
  }

  /**
   * Dialog for internal linking.
   *
   * @since 3.1.0
   */
  public static function wp_link_dialog() {
    // Run once
    if ( self::$link_dialog_printed ) {
      return;
    }

    self::$link_dialog_printed = true;

    // display: none is required here, see #WP27605
    ?>
    <div id="wp-link-backdrop" style="display: none"></div>
    <div id="wp-link-wrap" class="wp-core-ui" style="display: none" role="dialog" aria-labelledby="link-modal-title">
    <form id="wp-link" tabindex="-1">
    <?php wp_nonce_field( 'internal-linking', '_ajax_linking_nonce', false ); ?>
    <h1 id="link-modal-title"><?php _e( 'Insert/edit link', 'nimble-builder' ); ?></h1>
    <button type="button" id="wp-link-close"><span class="screen-reader-text"><?php _e( 'Close', 'nimble-builder' ); ?></span></button>
    <div id="link-selector">
      <div id="link-options">
        <p class="howto" id="wplink-enter-url"><?php _e( 'Enter the destination URL', 'nimble-builder' ); ?></p>
        <div>
          <label><span><?php _e( 'URL', 'nimble-builder' ); ?></span>
          <input id="wp-link-url" type="text" aria-describedby="wplink-enter-url" /></label>
        </div>
        <div class="wp-link-text-field">
          <label><span><?php _e( 'Link Text', 'nimble-builder' ); ?></span>
          <input id="wp-link-text" type="text" /></label>
        </div>
        <div class="link-target">
          <label><span></span>
          <input type="checkbox" id="wp-link-target" /> <?php _e( 'Open link in a new tab', 'nimble-builder' ); ?></label>
        </div>
      </div>
      <p class="howto" id="wplink-link-existing-content"><?php _e( 'Or link to existing content', 'nimble-builder' ); ?></p>
      <div id="search-panel">
        <div class="link-search-wrapper">
          <label>
            <span class="search-label"><?php _e( 'Search', 'nimble-builder' ); ?></span>
            <input type="search" id="wp-link-search" class="link-search-field" autocomplete="off" aria-describedby="wplink-link-existing-content" />
            <span class="spinner"></span>
          </label>
        </div>
        <div id="search-results" class="query-results" tabindex="0">
          <ul></ul>
          <div class="river-waiting">
            <span class="spinner"></span>
          </div>
        </div>
        <div id="most-recent-results" class="query-results" tabindex="0">
          <div class="query-notice" id="query-notice-message">
            <em class="query-notice-default"><?php _e( 'No search term specified. Showing recent items.', 'nimble-builder' ); ?></em>
            <em class="query-notice-hint screen-reader-text"><?php _e( 'Search or use up and down arrow keys to select an item.', 'nimble-builder' ); ?></em>
          </div>
          <ul></ul>
          <div class="river-waiting">
            <span class="spinner"></span>
          </div>
         </div>
       </div>
    </div>
    <div class="submitbox">
      <div id="wp-link-cancel">
        <button type="button" class="button"><?php _e( 'Cancel', 'nimble-builder' ); ?></button>
      </div>
      <div id="wp-link-update">
        <input type="submit" value="<?php esc_attr_e( 'Add Link', 'nimble-builder' ); ?>" class="button button-primary" id="wp-link-submit" name="wp-link-submit">
      </div>
    </div>
    </form>
    </div>
    <?php
  }
}
?><?php
add_action( 'customize_register', '\Nimble\sek_catch_export_action', PHP_INT_MAX );
function sek_catch_export_action( $wp_customize ) {
    if ( current_user_can( 'edit_theme_options' ) ) {
        if ( isset( $_REQUEST['sek_export_nonce'] ) ) {
            sek_maybe_export();
        }
    }
}

// fire from sek_catch_export_action() @hook 'customize_register'
function sek_maybe_export() {
    $nonce = 'save-customize_' . get_stylesheet();
    if ( ! isset( $_REQUEST['sek_export_nonce'] ) ) {
        sek_error_log( __FUNCTION__ . ' => missing nonce.');
        return;
    }
    if ( !isset( $_REQUEST['skope_id']) || empty( $_REQUEST['skope_id'] ) ) {
        sek_error_log( __FUNCTION__ . ' => missing or empty skope_id.');
        return;
    }
    if ( !isset( $_REQUEST['active_locations'] ) || empty( $_REQUEST['active_locations'] ) ) {
        sek_error_log( __FUNCTION__ . ' => missing active locations param.');
        return;
    }
    if ( ! wp_verify_nonce( $_REQUEST['sek_export_nonce'], $nonce ) ) {
        sek_error_log( __FUNCTION__ . ' => invalid none.');
        return;
    }
    if ( ! is_user_logged_in() ) {
        sek_error_log( __FUNCTION__ . ' => user not logged in.');
        return;
    }
    if ( ! current_user_can( 'customize' ) ) {
        sek_error_log( __FUNCTION__ . ' => missing customize capabilities.');
        return;
    }

    $seks_data = sek_get_skoped_seks( $_REQUEST['skope_id'] );

    //sek_error_log('EXPORT BEFORE FILTER ? ' . $_REQUEST['skope_id'] , $seks_data );
    // the filter 'nimble_pre_export' is used to :
    // replace image id by the absolute url
    // clean level ids and replace them with a placeholder string
    $seks_data = apply_filters( 'nimble_pre_export', $seks_data );
    $theme_name = sanitize_title_with_dashes( get_stylesheet() );

    //sek_error_log('EXPORT AFTER FILTER ?', $seks_data );
    $export = array(
        'data' => $seks_data,
        'metas' => array(
            'skope_id' => $_REQUEST['skope_id'],
            'version' => NIMBLE_VERSION,
            // is sent as a string : "__after_header,__before_main_wrapper,loop_start,__before_footer"
            'active_locations' => is_string( $_REQUEST['active_locations'] ) ? explode( ',', $_REQUEST['active_locations'] ) : array(),
            'date' => date("Y-m-d"),
            'theme' => $theme_name
        )
    );

    //sek_error_log('$export ?', $export );

    $skope_id = str_replace('skp__', '',  $_REQUEST['skope_id'] );
    $filename = $theme_name . '_' . $skope_id . '.nimblebuilder';

    // Set the download headers.
    header( 'Content-disposition: attachment; filename=' . $filename );
    header( 'Content-Type: application/octet-stream; charset=' . get_option( 'blog_charset' ) );

    // Serialize the export data.
    //echo serialize( $export );
    echo wp_json_encode( $export );

    // Start the download.
    die();
}

// Ajax action before processing the export
// control that all required fields are there
// This is to avoid a white screen when generating the download window afterwards
add_action( 'wp_ajax_sek_pre_export_checks', '\Nimble\sek_ajax_pre_export_checks' );
function sek_ajax_pre_export_checks() {
    //sek_error_log('PRE EXPORT CHECKS ?', $_POST );
    $action = 'save-customize_' . get_stylesheet();
    if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
        wp_send_json_error( 'check_ajax_referer_failed' );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'user_unauthenticated' );
    }
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_send_json_error( 'user_cant_edit_theme_options' );
    }
    if ( ! current_user_can( 'customize' ) ) {
        status_header( 403 );
        wp_send_json_error( 'customize_not_allowed' );
    } else if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        status_header( 405 );
        wp_send_json_error( 'bad_ajax_method' );
    }
    if ( ! isset( $_POST['skope_id'] ) || empty( $_POST['skope_id'] ) ) {
        wp_send_json_error( 'missing_skope_id' );
    }
    if ( ! isset( $_POST['active_locations'] ) || empty( $_POST['active_locations'] ) ) {
        wp_send_json_error( 'no_active_locations_to_export' );
    }
    wp_send_json_success();
}






// EXPORT FILTER
add_filter( 'nimble_pre_export', '\Nimble\sek_parse_img_and_clean_id' );
function sek_parse_img_and_clean_id( $seks_data ) {
    $new_seks_data = array();
    foreach ( $seks_data as $key => $value ) {
        if ( is_array($value) ) {
            $new_seks_data[$key] = sek_parse_img_and_clean_id( $value );
        } else {
            switch( $key ) {
                case 'bg-image' :
                case 'img' :
                    if ( is_int( $value ) && (int)$value > 0 ) {
                        $value = '__img_url__' . wp_get_attachment_url((int)$value);
                    }
                break;
                case 'id' :
                    if ( is_string( $value ) && false !== strpos( $value, '__nimble__' ) ) {
                        $value = '__rep__me__';
                    }
                break;
            }
            $new_seks_data[$key] = $value;
        }
    }
    return $new_seks_data;
}






// fetch the content from a user imported file
add_action( 'wp_ajax_sek_get_imported_file_content', '\Nimble\sek_ajax_get_imported_file_content' );
function sek_ajax_get_imported_file_content() {
    // sek_error_log(__FUNCTION__ . ' AJAX $_POST ?', $_POST );
    // sek_error_log(__FUNCTION__ . ' AJAX $_FILES ?', $_FILES );
    // sek_error_log(__FUNCTION__ . ' AJAX $_REQUEST ?', $_REQUEST );

    $action = 'save-customize_' . get_stylesheet();
    if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
        wp_send_json_error( 'check_ajax_referer_failed' );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'user_unauthenticated' );
    }
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_send_json_error( 'user_cant_edit_theme_options' );
    }
    if ( ! current_user_can( 'customize' ) ) {
        status_header( 403 );
        wp_send_json_error( 'customize_not_allowed' );
    } else if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        status_header( 405 );
        wp_send_json_error( 'bad_ajax_method' );
    }
    if ( ! isset( $_FILES['file_candidate'] ) || empty( $_FILES['file_candidate'] ) ) {
        wp_send_json_error( 'missing_file_candidate' );
    }
    if ( ! isset( $_POST['skope'] ) || empty( $_POST['skope'] ) ) {
        wp_send_json_error( 'missing_skope' );
    }

    // load WP upload if not done yet
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    // @see https://codex.wordpress.org/Function_Reference/wp_handle_upload
    // Important => always run unlink( $file['file'] ) before sending the json success or error
    // otherwise WP will write the file in the /wp-content folder
    $file = wp_handle_upload(
        $_FILES['file_candidate'],
        array(
            'test_form' => false,
            'test_type' => false,
            'mimes' => array(
                'text' => 'text/plain',
                //'nimblebuilder' => 'text/plain',
                'json' => 'application/json',
                'nimblebuilder' => 'application/json'
            )
        )
    );

    // Make sure we have an uploaded file.
    if ( isset( $file['error'] ) ) {
        unlink( $file['file'] );
        wp_send_json_error( 'import_file_error' );
        return;
    }
    if ( !file_exists( $file['file'] ) ) {
        unlink( $file['file'] );
        wp_send_json_error( 'import_file_do_not_exist' );
        return;
    }

    // Get the upload data.
    $raw = file_get_contents( $file['file'] );
    //$raw_unserialized_data = @unserialize( $raw );
    $raw_unserialized_data = json_decode( $raw, true );

    // VALIDATE IMPORTED CONTENT
    // data structure :
    // $raw_unserialized_data = array(
    //     'data' => $seks_data,
    //     'metas' => array(
    //         'skope_id' => $_REQUEST['skope_id'],
    //         'version' => NIMBLE_VERSION,
    //         // is sent as a string : "__after_header,__before_main_wrapper,loop_start,__before_footer"
    //         'active_locations' => is_string( $_REQUEST['active_locations'] ) ? explode( ',', $_REQUEST['active_locations'] ) : array(),
    //         'date' => date("Y-m-d")
    //     )
    // );
    // check import structure
    if ( ! is_array( $raw_unserialized_data ) || empty( $raw_unserialized_data['data']) || !is_array( $raw_unserialized_data['data'] ) || empty( $raw_unserialized_data['metas'] ) || !is_array( $raw_unserialized_data['metas'] ) ) {
        unlink( $file['file'] );
        wp_send_json_error(  'invalid_import_content' );
        return;
    }
    // check version
    // => current Nimble Version must be at least import version
    if ( !empty( $raw_unserialized_data['metas']['version'] ) && version_compare( NIMBLE_VERSION, $raw_unserialized_data['metas']['version'], '<' ) ) {
        unlink( $file['file'] );
        wp_send_json_error( 'nimble_builder_needs_update' );
        return;
    }

    //sek_error_log('IMPORT BEFORE FILTER ?', $raw_unserialized_data );

    // in a pre-import-check context, we don't need to sniff and upload images
    if ( isset( $_POST['pre_import_check'] ) && true == $_POST['pre_import_check'] ) {
        remove_filter( 'nimble_pre_import', '\Nimble\sek_sniff_imported_img_url' );
    }

    $imported_content = array(
        'data' => apply_filters( 'nimble_pre_import', $raw_unserialized_data['data'] ),
        'metas' => $raw_unserialized_data['metas'],
        // the image import errors won't block the import
        // they are used when notifying user in the customizer
        'img_errors' => !empty( Nimble_Manager()->img_import_errors ) ? implode(',', Nimble_Manager()->img_import_errors) : array()
    );

    // Remove the uploaded file
    // Important => always run unlink( $file['file'] ) before sending the json success or error
    // otherwise WP will write the file in the /wp-content folder
    unlink( $file['file'] );
    // Send
    wp_send_json_success( $imported_content );
}


// IMPORT FILTER
add_filter( 'nimble_pre_import', '\Nimble\sek_sniff_imported_img_url' );
function sek_sniff_imported_img_url( $seks_data ) {
    $new_seks_data = array();
    foreach ( $seks_data as $key => $value ) {
        if ( is_array($value) ) {
            $new_seks_data[$key] = sek_sniff_imported_img_url( $value );
        } else {
            if ( is_string( $value ) && false !== strpos( $value, '__img_url__' ) && sek_is_img_url( $value ) ) {
                $url = str_replace( '__img_url__', '', $value );
                //sek_error_log( __FUNCTION__ . ' URL?', $url );
                $id = sek_sideload_img_and_return_attachment_id( $url );
                if ( is_wp_error( $id ) ) {
                    $value = null;
                    $img_errors = Nimble_Manager()->img_import_errors;
                    $img_errors[] = $url;
                    Nimble_Manager()->img_import_errors = $img_errors;
                } else {
                    $value = $id;
                }
            }
            $new_seks_data[$key] = $value;
        }
    }
    return $new_seks_data;
}

// @return bool
function sek_is_img_url( $url = '' ) {
    if ( is_string( $url ) ) {
      if ( preg_match( '/\.(jpg|jpeg|png|gif)/i', $url ) ) {
        return true;
      }
    }
    return false;
}

?><?php
// WP 5.0.0 compat. until the bug is fixed
// this hook fires before the customize changeset is inserter / updated in database
// Removing the wp_targeted_link_rel callback from the 'content_save_pre' filter prevents corrupting the changeset JSON
// more details in this ticket : https://core.trac.wordpress.org/ticket/45292
add_action( 'customize_save_validation_before', '\Nimble\sek_remove_callback_wp_targeted_link_rel' );
function sek_remove_callback_wp_targeted_link_rel( $wp_customize ) {
    if ( false !== has_filter( 'content_save_pre', 'wp_targeted_link_rel' ) ) {
        remove_filter( 'content_save_pre', 'wp_targeted_link_rel' );
    }
};

?>
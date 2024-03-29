<?php
namespace Nimble;
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'plugins_loaded', '\Nimble\sek_versionning');
function sek_versionning() {
    $current_version = get_option( 'nimble_version' );
    if ( $current_version != NIMBLE_VERSION ) {
        update_option( 'nimble_version_upgraded_from', $current_version );
        update_option( 'nimble_version', NIMBLE_VERSION );
    }
    $started_with = get_option( 'nimble_started_with_version' );
    if ( empty( $started_with ) ) {
        update_option( 'nimble_started_with_version', $current_version );
    }
    $start_date = get_option( 'nimble_start_date' );
    if ( empty( $start_date ) ) {
        update_option( 'nimble_start_date', date("Y-m-d H:i:s") );
    }
}
add_action('admin_menu', '\Nimble\sek_plugin_menu');
function sek_plugin_menu() {
    if ( ! current_user_can( 'update_plugins' ) )
      return;
    add_plugins_page(__( 'System info', 'nimble-builder' ), __( 'System info', 'nimble-builder' ), 'read', 'nimble-builder', '\Nimble\sek_plugin_page');
}

function sek_plugin_page() {
    ?>
    <div class="wrap">
      <h3><?php _e( 'System Informations', 'nimble-builder' ); ?></h3>
      <h4 style="text-align: left"><?php _e( 'Please include your system informations when posting support requests.' , 'nimble-builder' ) ?></h4>
      <textarea readonly="readonly" onclick="this.focus();this.select()" id="system-info-textarea" name="tc-sysinfo" title="<?php _e( 'To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'nimble-builder' ); ?>" style="width: 800px;min-height: 800px;font-family: Menlo,Monaco,monospace;background: 0 0;white-space: pre;overflow: auto;display:block;"><?php echo sek_config_infos(); ?></textarea>
    </div>
    <?php
}





/**
 * Get system info
 * Inspired by the system infos page for Easy Digital Download plugin
 * @return      string $return A string containing the info to output
 */
function sek_config_infos() {
    global $wpdb;

    if ( !class_exists( 'Browser' ) ) {
        require_once( NIMBLE_BASE_PATH . '/inc/libs/browser.php' );
    }

    $browser = new \Browser();
    $theme_data   = wp_get_theme();
    $theme        = $theme_data->Name . ' ' . $theme_data->Version;
    $parent_theme = $theme_data->Template;
    if ( ! empty( $parent_theme ) ) {
      $parent_theme_data = wp_get_theme( $parent_theme );
      $parent_theme      = $parent_theme_data->Name . ' ' . $parent_theme_data->Version;
    }

    $return  = '### Begin System Info (Generated ' . date( 'Y-m-d H:i:s' ) . ') ###' . "";
    $return .= "\n" .'------------ SITE INFO' . "\n";
    $return .= 'Site URL:                 ' . site_url() . "\n";
    $return .= 'Home URL:                 ' . home_url() . "\n";
    $return .= 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";
    $return .= "\n\n" . '------------ USER BROWSER' . "\n";
    $return .= $browser;

    $locale = get_locale();
    $return .= "\n\n" . '------------ WORDPRESS CONFIG' . "\n";
    $return .= 'WP Version:               ' . get_bloginfo( 'version' ) . "\n";
    $return .= 'Language:                 ' . ( !empty( $locale ) ? $locale : 'en_US' ) . "\n";
    $return .= 'Permalink Structure:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
    $return .= 'Active Theme:             ' . $theme . "\n";
    if ( $parent_theme !== $theme ) {
      $return .= 'Parent Theme:             ' . $parent_theme . "\n";
    }
    $return .= 'Show On Front:            ' . get_option( 'show_on_front' ) . "\n";
    if( get_option( 'show_on_front' ) == 'page' ) {
      $front_page_id = get_option( 'page_on_front' );
      $blog_page_id = get_option( 'page_for_posts' );

      $return .= 'Page On Front:            ' . ( $front_page_id != 0 ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset' ) . "\n";
      $return .= 'Page For Posts:           ' . ( $blog_page_id != 0 ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset' ) . "\n";
    }

    $return .= 'ABSPATH:                  ' . ABSPATH . "\n";

    $return .= 'WP_DEBUG:                 ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
    $return .= 'WP Memory Limit:          ' . ( sek_let_to_num( WP_MEMORY_LIMIT )/( 1024 ) ) ."MB" . "\n";
    $return .= "\n\n" . '------------ NIMBLE CONFIGURATION' . "\n";
    $return .= 'Version:                  ' . NIMBLE_VERSION . "\n";
    $return .= 'Upgraded From:            ' . get_option( 'nimble_version_upgraded_from', 'None' ) . "\n";
    $return .= 'Started With:             ' . get_option( 'nimble_started_with_version', 'None' ) . "\n";
    $updates = get_plugin_updates();
    $muplugins = get_mu_plugins();
    if( count( $muplugins ) > 0 ) {
      $return .= "\n\n" . '------------ MU PLUGINS' . "\n";

      foreach( $muplugins as $plugin => $plugin_data ) {
        $return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
      }
    }
    $return .= "\n\n" . '------------ WP ACTIVE PLUGINS' . "\n";

    $plugins = get_plugins();
    $active_plugins = get_option( 'active_plugins', array() );

    foreach( $plugins as $plugin_path => $plugin ) {
      if( !in_array( $plugin_path, $active_plugins ) )
        continue;

      $update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
      $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
    }
    $return .= "\n\n" . '------------ WP INACTIVE PLUGINS' . "\n";

    foreach( $plugins as $plugin_path => $plugin ) {
      if( in_array( $plugin_path, $active_plugins ) )
        continue;

      $update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
      $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
    }

    if( is_multisite() ) {
      $return .= "\n\n" . '------------ NETWORK ACTIVE PLUGINS' . "\n";

      $plugins = wp_get_active_network_plugins();
      $active_plugins = get_site_option( 'active_sitewide_plugins', array() );

      foreach( $plugins as $plugin_path ) {
        $plugin_base = plugin_basename( $plugin_path );

        if( !array_key_exists( $plugin_base, $active_plugins ) )
          continue;

        $update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
        $plugin  = get_plugin_data( $plugin_path );
        $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
      }
    }
    $return .= "\n\n" . '------------ WEBSERVER CONFIG' . "\n";
    $return .= 'PHP Version:              ' . PHP_VERSION . "\n";
    $return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
    $return .= 'Webserver Info:           ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
    $return .= 'Writing Permissions:      ' . sek_get_write_permissions_status() . "\n";
    $return .= "\n\n" . '------------ PHP CONFIG' . "\n";
    $return .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
    $return .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
    $return .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
    $return .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
    $return .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
    $return .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
    $return .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";
    $return .= 'PHP Arg Separator:        ' . ini_get( 'arg_separator.output' ) . "\n";
    $return .= 'PHP Allow URL File Open:  ' . ini_get( 'allow_url_fopen' ) . "\n";

    $return .= "\n\n" . '### End System Info ###';

    return $return;
}


/**
 * Does Size Conversions
 */
function sek_let_to_num( $v ) {
    $l   = substr( $v, -1 );
    $ret = substr( $v, 0, -1 );

    switch ( strtoupper( $l ) ) {
      case 'P': // fall-through
      case 'T': // fall-through
      case 'G': // fall-through
      case 'M': // fall-through
      case 'K': // fall-through
        $ret *= 1024;
        break;
      default:
        break;
    }
    return $ret;
}



function sek_get_write_permissions_status() {
    $permission_issues = array();
    $writing_path_candidates = array();
    $wp_upload_dir = wp_upload_dir();
    if ( $wp_upload_dir['error'] ) {
        $permission_issues[] = 'WordPress root uploads folder';
    }

    $nimble_css_folder_path = $wp_upload_dir['basedir'] . '/' . NIMBLE_CSS_FOLDER_NAME;

    if ( is_dir( $nimble_css_folder_path ) ) {
        $writing_path_candidates[ $nimble_css_folder_path ] = 'Nimble uploads folder';
    }
    $writing_path_candidates[ ABSPATH ] = 'WP root directory';

    foreach ( $writing_path_candidates as $dir => $description ) {
        if ( ! is_writable( $dir ) ) {
            $permission_issues[] = $description;
        }
    }

    if ( $permission_issues ) {
        $message = 'NOK => issues with : ';
        $message .= implode( ' and ', $permission_issues );
    } else {
        $message = 'OK';
    }

    return $message;
}
add_action( 'admin_init' , '\Nimble\sek_admin_style' );
function sek_admin_style() {
    if ( skp_is_customizing() )
      return;
    wp_enqueue_style(
        'nimble-admin-css',
        sprintf(
            '%1$s/assets/admin/css/%2$s' ,
            NIMBLE_BASE_URL,
            'nimble-admin.css'
        ),
        array(),
        NIMBLE_ASSETS_VERSION,
        'all'
    );
}
add_action( 'admin_notices'                         , '\Nimble\sek_may_be_display_update_notice');
add_action( 'wp_ajax_dismiss_nimble_update_notice'  ,  '\Nimble\sek_dismiss_update_notice_action' );
foreach ( array( 'wptexturize', 'convert_smilies', 'wpautop') as $callback ) {
  if ( function_exists( $callback ) )
      add_filter( 'sek_update_notice', $callback );
}


/**
* @hook : admin_notices
*/
function sek_may_be_display_update_notice() {
    if ( defined('NIMBLE_SHOW_UPDATE_NOTICE_FOR_VERSION') && NIMBLE_SHOW_UPDATE_NOTICE_FOR_VERSION !== NIMBLE_VERSION )
      return;
    if ( ! sek_welcome_notice_is_dismissed() )
      return;

    $last_update_notice_values  = get_option( 'nimble_last_update_notice' );
    $show_new_notice = false;
    $display_ct = 5;

    if ( ! $last_update_notice_values || ! is_array($last_update_notice_values) ) {
        $last_update_notice_values = array( "version" => NIMBLE_VERSION, "display_count" => 0 );
        update_option( 'nimble_last_update_notice', $last_update_notice_values );
        if ( sek_user_started_before_version( NIMBLE_VERSION ) ) {
            $show_new_notice = true;
        }
    }

    $_db_version          = $last_update_notice_values["version"];
    $_db_displayed_count  = $last_update_notice_values["display_count"];
    if ( version_compare( NIMBLE_VERSION, $_db_version , '>' ) ) {
        if ( $_db_displayed_count < $display_ct ) {
            $show_new_notice = true;
            (int) $_db_displayed_count++;
            $last_update_notice_values["display_count"] = $_db_displayed_count;
            update_option( 'nimble_last_update_notice', $last_update_notice_values );
        }
        else {
            $new_val  = array( "version" => NIMBLE_VERSION, "display_count" => 0 );
            update_option('nimble_last_update_notice', $new_val );
        }//end else
    }//end if

    if ( ! $show_new_notice )
      return;

    ob_start();
      ?>
      <div class="updated czr-update-notice" style="position:relative;">
        <?php
          printf('<h3>%1$s %2$s %3$s %4$s :D</h3>',
              __( "Thanks, you successfully upgraded", 'nimble-builder'),
              'Nimble Builder',
              __( "to version", 'nimble-builder'),
              NIMBLE_VERSION
          );
        ?>
        <?php
          printf( '<h4>%1$s <a class="" href="%2$s" title="%3$s" target="_blank">%3$s &raquo;</a></h4>',
              '',//__( "Let us introduce the new features we've been working on.", 'text_doma'),
              NIMBLE_RELEASE_NOTE_URL,
              __( "Read the detailled release notes" , 'nimble-builder' )
          );
        ?>
        <p style="text-align:right;position: absolute;font-size: 1.1em;<?php echo is_rtl()? 'left' : 'right';?>: 7px;bottom: -6px;">
        <?php printf('<a href="#" title="%1$s" class="nimble-dismiss-update-notice"> ( %1$s <strong>X</strong> ) </a>',
            __('close' , 'nimble-builder')
          );
        ?>
        </p>
        <!-- <p>
          <?php
          ?>
        </p> -->
      </div>
      <?php
      $_html = ob_get_contents();
      if ($_html) ob_end_clean();
      echo apply_filters( 'sek_update_notice', $_html );
      ?>
      <script type="text/javascript" id="nimble-dismiss-update-notice">
        ( function($){
          var _ajax_action = function( $_el ) {
              var AjaxUrl = "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                  _query  = {
                      action  : 'dismiss_nimble_update_notice',
                      dismissUpdateNoticeNonce :  "<?php echo wp_create_nonce( 'dismiss-update-notice-nonce' ); ?>"
                  },
                  $ = jQuery,
                  request = $.post( AjaxUrl, _query );

              request.fail( function ( response ) {});
              request.done( function( response ) {
                if ( '0' === response )
                  return;
                if ( '-1' === response )
                  return;

                $_el.closest('.updated').slideToggle('fast');
              });
          };//end of fn
          $( function($) {
            $('.nimble-dismiss-update-notice').click( function( e ) {
              e.preventDefault();
              _ajax_action( $(this) );
            } );
          } );

        })( jQuery );
      </script>
      <?php
}


/**
* hook : wp_ajax_dismiss_nimble_update_notice
* => sets the last_update_notice to the current Nimble version when user click on dismiss notice link
*/
function sek_dismiss_update_notice_action() {
    check_ajax_referer( 'dismiss-update-notice-nonce', 'dismissUpdateNoticeNonce' );
    $new_val  = array( "version" => NIMBLE_VERSION, "display_count" => 0 );
    update_option( 'nimble_last_update_notice', $new_val );
    wp_die( 1 );
}
/* beautify admin notice text using some defaults the_content filter callbacks */
foreach ( array( 'wptexturize', 'convert_smilies' ) as $callback ) {
    add_filter( 'nimble_update_notice', $callback );
}
function sek_welcome_notice_is_dismissed() {
    $dismissed = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );
    $dismissed_array = array_filter( explode( ',', (string) $dismissed ) );
    if ( defined('NIMBLE_DEV') && NIMBLE_DEV )
      return false;
    return in_array( NIMBLE_WELCOME_NOTICE_ID, $dismissed_array );
}

add_action( 'admin_notices', '\Nimble\sek_render_welcome_notice' );
function sek_render_welcome_notice() {
    if ( ! current_user_can( 'customize' ) )
      return;

    if ( sek_welcome_notice_is_dismissed() )
      return;
    if ( sek_site_has_nimble_sections_created() && ! ( defined('NIMBLE_DEV') && NIMBLE_DEV ) ) {
        $dismissed = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );
        $dismissed_array = array_filter( explode( ',', (string) $dismissed ) );
        $dismissed_array[] = NIMBLE_WELCOME_NOTICE_ID;
        $dismissed = implode( ',', $dismissed_array );
        update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', $dismissed );
        return;
    }
    $notice_id = NIMBLE_WELCOME_NOTICE_ID;
    ?>
    <div class="nimble-welcome-notice notice notice-info is-dismissible" id="<?php echo esc_attr( $notice_id ); ?>">
      <div class="notice-dismiss"></div>
      <div class="nimble-welcome-icon-holder">
        <img class="nimble-welcome-icon" src="<?php echo NIMBLE_BASE_URL.'/assets/img/nimble/nimble_banner.svg?ver='.NIMBLE_VERSION; ?>" alt="<?php esc_html_e( 'Nimble Builder', 'nimble-builder' ); ?>" />
      </div>
      <h1><?php echo apply_filters( 'nimble_update_notice', __('Welcome to Nimble Builder for WordPress :D', 'nimble-builder' ) ); ?></h1>
      <h3><?php _e( 'Nimble allows you to drag and drop content modules, or pre-built section templates, into <u>any context</u> of your site, including search results or 404 pages. You can edit your pages in <i>real time</i> from the live customizer, and then publish when you are happy of the result, or save for later.', 'nimble-builder' ); ?></h3>
      <h3><?php _e( 'The plugin automatically creates fluid and responsive sections for a pixel-perfect rendering on smartphones and tablets, without the need to add complex code.', 'nimble-builder' ); ?></h3>
      <h3><?php _e( 'Nimble Builder takes the native WordPress customizer to a level you\'ve never seen before.', 'nimble-builder' ); ?></h3>
      <?php printf( '<a href="%1$s" target="_blank" class="button button-primary button-hero"><span class="dashicons dashicons-admin-appearance"></span> %2$s</a>',
          esc_url( add_query_arg(
              array(
                array( 'autofocus' => array( 'section' => '__content_picker__' ) ),
                'return' => urlencode( remove_query_arg( wp_removable_query_args(), wp_unslash( $_SERVER['REQUEST_URI'] ) ) )
              ),
              admin_url( 'customize.php' )
          ) ),
          __( 'Start creating content in live preview', 'nimble-builder' )
      ); ?>
      <div class="nimble-link-to-doc">
        <?php printf( '<div class="nimble-doc-link-wrap">%1$s <a href="%2$s" target="_blank" class="">%3$s</a>.</div>',
            __('Or', 'nimble-builder'),
            add_query_arg(
                array(
                  'utm_source' => 'usersite',
                  'utm_medium' => 'link',
                  'utm_campaign' => 'nimble-welcome-notice'
                ),
                'https://docs.presscustomizr.com/article/337-getting-started-with-the-nimble-builder-plugin'
            ),
            __( 'read the getting started guide', 'nimble-builder' )
        ); ?>
      </div>
    </div>

    <script>
    jQuery( function( $ ) {
      $( <?php echo wp_json_encode( "#$notice_id" ); ?> ).on( 'click', '.notice-dismiss', function() {
        $.post( ajaxurl, {
          pointer: <?php echo wp_json_encode( $notice_id ); ?>,
          action: 'dismiss-wp-pointer'
        } );
      } );
    } );
    </script>
    <style type="text/css">
      .nimble-welcome-notice {
        padding: 38px;
      }
      .nimble-welcome-notice .dashicons {
        line-height: 44px;
      }
      .nimble-welcome-icon-holder {
        width: 550px;
        height: 200px;
        float: left;
        margin: 0 38px 38px 0;
      }
      .nimble-welcome-icon {
        width: 100%;
        height: 100%;
        display: block;
      }
      .nimble-welcome-notice h1 {
        font-weight: bold;
      }
      .nimble-welcome-notice h3 {
        font-size: 16px;
        font-weight: 500;
      }
      .nimble-link-to-doc {
        position: relative;
        display: inline-block;
        width: 200px;
        height: 46px;
      }
      .nimble-link-to-doc .nimble-doc-link-wrap {
        position: absolute;
        bottom: 0;
      }

    </style>
    <?php
}
add_action( 'wp_dashboard_setup', '\Nimble\sek_register_dashboard_widgets' );
function sek_register_dashboard_widgets() {
    $theme_name = sek_get_parent_theme_slug();
    $title = __( 'Nimble Builder Overview', 'nimble-builder' );
    wp_add_dashboard_widget(
        'presscustomizr-dashboard',
        !sek_is_presscustomizr_theme( $theme_name ) ? $title : sprintf( __( 'Nimble Builder & %s Overview', 'nimble-builder' ), ucfirst($theme_name) ),
        '\Nimble\sek_nimble_dashboard_callback_fn'
    );

    global $wp_meta_boxes;
    $dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
    $nimble_widget = array( 'presscustomizr-dashboard' => $dashboard['presscustomizr-dashboard'] );
    $wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $nimble_widget, $dashboard );
}
function sek_nimble_dashboard_callback_fn() {
    $post_data = sek_get_latest_posts_api_data( false );
    $theme_name = sek_get_parent_theme_slug();
    ?>
    <div class="nimble-db-wrapper">
      <div class="nimble-db-header">
        <div class="nimble-logo-version">
          <div class="nimble-logo"><div class="sek-nimble-icon" title="<?php _e('Add sections in live preview with Nimble Builder', 'nimble-builder' );?>"><img src="<?php echo NIMBLE_BASE_URL.'/assets/img/nimble/nimble_icon.svg?ver='.NIMBLE_VERSION; ?>" alt="Nimble Builder"></div></div>
          <div class="nimble-version">
            <span class="nimble-version-text"><?php _e('Nimble Builder', 'nimble-builder'); ?> v<?php echo NIMBLE_VERSION; ?></span>
            <?php if ( sek_is_presscustomizr_theme( $theme_name ) ) : ?>
              <?php
                $theme_data = wp_get_theme();
                printf('<span class="nimble-version-text"> + %1$s theme v%2$s</span>', ucfirst($theme_name), $theme_data -> version );
              ?>
            <?php endif; ?>
          </div>
        </div>
        <?php printf( '<a href="%1$s" class="button button-primary button-hero"><span class="dashicons dashicons-admin-appearance"></span> %2$s</a>',
          esc_url( add_query_arg(
              array(
                array( 'autofocus' => array( 'section' => '__content_picker__' ) ),
                'return' => urlencode( remove_query_arg( wp_removable_query_args(), wp_unslash( $_SERVER['REQUEST_URI'] ) ) )
              ),
              admin_url( 'customize.php' )
          ) ),
          __( 'Start building', 'nimble-builder' )
      ); ?>
      </div>
      <?php if ( ! empty( $post_data ) ) : ?>
        <div class="nimble-post-list">
          <h3 class="nimble-post-list-title"><?php echo __( 'News & release notes', 'nimble-builder' ); ?></h3>
          <ul class="nimble-collection">
            <?php foreach ( $post_data as $single_post_data ) : ?>
              <li class="nimble-single-post">
                <a href="<?php echo esc_url( $single_post_data['url'] ); ?>" class="nimble-single-post-link" target="_blank">
                  <?php echo esc_html( $single_post_data['title'] ); ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php
        $footer_links = array(
          'news' => array(
            'title' => __( 'Blog', 'nimble-builder' ),
            'link' => 'https://presscustomizr.com/blog/?ref=a&amp;utm_source=usersite&amp;utm_medium=link&amp;utm_campaign=dashboard',
          ),
          'doc' => array(
            'title' => __( 'Help', 'nimble-builder' ),
            'link' => 'https://docs.presscustomizr.com/?ref=a&amp;utm_source=usersite&amp;utm_medium=link&amp;utm_campaign=dashboard',
          ),
        );
        $start_msg_array = array();
        $theme_name = sek_get_parent_theme_slug();

        if ( sek_is_presscustomizr_theme( $theme_name ) ) {
            $start_msg = sek_start_msg_from_api( $theme_name, false );
            if ( !empty( $start_msg ) ) {
              $start_msg_array = array(
                'start_msg' => array(
                  'html' => $start_msg,
                ),
              );
            }
        }
        $footer_links = array_merge($footer_links,$start_msg_array);
      ?>
      <div class="nimble-db-footer">
          <?php foreach ( $footer_links as $link_id => $link_data ) : ?>
            <div class="nimble-footer-link-<?php echo esc_attr( $link_id ); ?>">
              <?php if ( !empty( $link_data['html'] ) ) : ?>
                <?php echo $link_data['html']; ?>
              <?php else : ?>
              <a href="<?php echo esc_attr( $link_data['link'] ); ?>" target="_blank"><?php echo esc_html( $link_data['title'] ); ?> <span class="screen-reader-text"><?php echo __( '(opens in a new window)', 'nimble-builder' ); ?></span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

      </div>
    </div>
    <?php
}
function sek_is_forbidden_post_type_for_nimble_edit_button( $post_type = '' ) {
    $post_type_obj = get_post_type_object( $post_type );
    return is_object($post_type_obj) && true !== $post_type_obj->public;
}
add_action( 'edit_form_after_title', '\Nimble\sek_print_edit_with_nimble_btn_for_classic_editor' );
function sek_print_edit_with_nimble_btn_for_classic_editor( $post ) {
  if ( is_object($post) && sek_is_forbidden_post_type_for_nimble_edit_button( $post->post_type ) )
    return;
  $current_screen = get_current_screen();
  if ( 'post' !== $current_screen->base )
    return;
  if ( did_action( 'enqueue_block_editor_assets' ) ) {
    return;
  }
  if ( ! sek_current_user_can_edit( $post->ID ) || ! current_user_can( 'customize' ) ) {
    return;
  }
  sek_print_nb_btn_edit_with_nimble( 'classic' );
}
add_action( 'enqueue_block_editor_assets', '\Nimble\sek_enqueue_js_asset_for_gutenberg_edit_button');
function sek_enqueue_js_asset_for_gutenberg_edit_button() {
    $current_screen = get_current_screen();
    if ( 'post' !== $current_screen->base )
      return;
    $post = get_post();
    if ( ! sek_current_user_can_edit( $post->ID ) || ! current_user_can( 'customize' ) ) {
      return;
    }
    wp_enqueue_script(
      'nb-gutenberg',
      sprintf(
            '%1$s/assets/admin/js/%2$s' ,
            NIMBLE_BASE_URL,
            'nimble-gutenberg.js'
      ),
      array('jquery'),
      NIMBLE_ASSETS_VERSION,
      true
    );
}
add_action( 'admin_footer', '\Nimble\sek_print_js_for_nimble_edit_btn' );
function sek_print_js_for_nimble_edit_btn() {
  $current_screen = get_current_screen();
  if ( 'post' !== $current_screen->base )
    return;

  $post = get_post();
  if ( is_object($post) && sek_is_forbidden_post_type_for_nimble_edit_button( $post->post_type ) )
    return;
  if ( ! sek_current_user_can_edit( $post->ID ) || ! current_user_can( 'customize' ) ) {
    return;
  }
  ?>
  <?php if ( did_action( 'enqueue_block_editor_assets' ) ) : ?>
    <?php // Only printed when Gutenberg editor is enabled ?>
    <script id="sek-edit-with-nb" type="text/html">
      <?php sek_print_nb_btn_edit_with_nimble( 'gutenberg' ); ?>
    </script>
  <?php else : ?>
    <?php // Only printed when Gutenberg editor is NOT enabled ?>
      <script type="text/javascript">
      (function ($) {
          $('body').on( 'click', '#sek-edit-with-nimble', function(evt) {
              evt.preventDefault();
              var $clickedEl = $(this),
                  _url = $clickedEl.data('cust-url');
              if ( _.isEmpty( _url ) ) {
                  $clickedEl.addClass('sek-loading-customizer').removeClass('button-primary');
                  var post_id = $('#post_ID').val();
                  wp.ajax.post( 'sek_get_customize_url_for_nimble_edit_button', {
                      nimble_edit_post_id : post_id
                  }).done( function( resp ) {
                      location.href = resp;
                  }).fail( function( resp ) {
                      $clickedEl.removeClass('sek-loading-customizer').addClass('button-primary');
                      _.delay(function () {
                          $( window ).off( 'beforeunload' );
                          location.href = location.href; //wp-admin/post.php?post=70&action=edit
                      }, 300 );
                  });
              } else {
                  location.href = _url;
              }
          });
      })(jQuery);
    </script>
  <?php endif; ?>
  <?php
}
function sek_print_nb_btn_edit_with_nimble( $editor_type ) {
    $post = get_post();
    $manually_built_skope_id = strtolower( NIMBLE_SKOPE_ID_PREFIX . 'post_' . $post->post_type . '_' . $post->ID );
    $customize_url = sek_get_customize_url_when_is_admin( $post );
    if ( ! empty( $customize_url ) ) {
        $customize_url = add_query_arg(
            array( 'autofocus' => array( 'section' => '__content_picker__' ) ),
            $customize_url
        );
    }
    $btn_css_classes = 'classic' === $editor_type ? 'button button-primary button-hero classic-ed' : 'button button-primary button-large guten-ed';
    ?>
    <button id="sek-edit-with-nimble" type="button" class="<?php echo $btn_css_classes; ?>" data-cust-url="<?php echo esc_url( $customize_url ); ?>">
      <?php //_e( 'Edit with Nimble Builder', 'text_doma' ); ?>
      <?php printf( '<span class="sek-spinner"></span><span class="sek-nimble-icon" title="%3$s"><img src="%1$s" alt="%2$s"/><span class="sek-nimble-admin-bar-title">%2$s</span><span class="sek-nimble-mobile-admin-bar-title">%3$s</span></span>',
          NIMBLE_BASE_URL.'/assets/img/nimble/nimble_icon.svg?ver='.NIMBLE_VERSION,
          sek_local_skope_has_nimble_sections( $manually_built_skope_id ) ? __('Continue building with Nimble','nimble-builder') : __('Build with Nimble Builder','nimble-builder'),
          __('Build','nimble-builder'),
          __('Build sections in live preview with Nimble Builder', 'nimble-builder')
      ); ?>
    </button>
    <?php
}
function sek_current_user_can_edit( $post_id = 0 ) {
    $post = get_post( $post_id );

    if ( ! $post ) {
      return false;
    }
    if ( 'trash' === get_post_status( $post_id ) ) {
      return false;
    }
    $post_type_object = get_post_type_object( $post->post_type );

    if ( ! isset( $post_type_object->cap->edit_post ) ) {
      return false;
    }
    $edit_cap = $post_type_object->cap->edit_post;
    if ( ! current_user_can( $edit_cap, $post_id ) ) {
      return false;
    }
    if ( get_option( 'page_for_posts' ) === $post_id ) {
      return false;
    }
    return true;
}
add_filter( 'display_post_states', '\Nimble\sek_add_nimble_post_state', 10, 2 );
function sek_add_nimble_post_state( $post_states, $post ) {
    $manually_built_skope_id = strtolower( NIMBLE_SKOPE_ID_PREFIX . 'post_' . $post->post_type . '_' . $post->ID );
    if ( $post && current_user_can( 'edit_post', $post->ID ) && sek_local_skope_has_nimble_sections( $manually_built_skope_id ) ) {
        $post_states['nimble'] = __( 'Nimble Builder', 'nimble-builder' );
    }
    return $post_states;
}
add_filter( 'post_row_actions', '\Nimble\sek_filter_post_row_actions', 11, 2 );
add_filter( 'page_row_actions', '\Nimble\sek_filter_post_row_actions', 11, 2 );
function sek_filter_post_row_actions( $actions, $post ) {
    $manually_built_skope_id = strtolower( NIMBLE_SKOPE_ID_PREFIX . 'post_' . $post->post_type . '_' . $post->ID );
    if ( $post && current_user_can( 'edit_post', $post->ID ) && sek_local_skope_has_nimble_sections( $manually_built_skope_id ) ) {
        $actions['edit_with_nimble_builder'] = sprintf( '<a href="%1$s" title="%2$s">%2$s</a>',
            sek_get_customize_url_for_post_id( $post->ID ),
            __( 'Edit with Nimble Builder', 'nimble-builder' )
        );
    }
    return $actions;
}
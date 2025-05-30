<?php
/*
Plugin Name: care247 custom
Description: Custom PHP and Script added by Gopal
Version: 1.0.0
Author: Shashank Patel
Author URI: https://www.linkedin.com/in/shashank-patel-4a6264260/
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


 add_action('wp_enqueue_scripts', 'care247_enqueue_assets');

function care247_enqueue_assets() {
     $plugin_url = plugin_dir_url(__FILE__);

     wp_enqueue_style(
        'care247-style',
        $plugin_url . 'assets/style.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'care247-script',
        $plugin_url . 'assets/script.js',
        ['jquery'],  
        '1.0.0',
        true  
    );
}



function my_pmpro_add_form_enctype() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function(){
      var form = document.querySelector("form.pmpro_form");
      if(form) form.setAttribute("enctype","multipart/form-data");
    });
    </script>
    <?php
}
add_action( 'pmpro_show_user_profile', 'my_pmpro_add_form_enctype', 5 );


function my_pmpro_show_profile_file() {
    $user_id  = get_current_user_id();
    $meta_key = 'pmpro_profile_file';
    $file_url = get_user_meta( $user_id, $meta_key, true );

    echo '<div class="pmpro-profile-upload">';
      echo '<label for="pmpro_profile_file">Upload Your File (Max 10 MB)</label>';

      if ( $file_url ) {
        $filename = basename( $file_url );
        echo '<div class="uploaded-file">';
          echo '<strong>Your File:</strong> ';
          echo '<a href="' . esc_url( $file_url ) . '" target="_blank">'
               . esc_html( $filename ) . '</a>';
        echo '</div>';
        echo '<div class="delete-file">';
          echo '<label><input type="checkbox" name="pmpro_profile_file_delete" value="1"> Delete this file</label>';
        echo '</div>';
      } else {
        echo '<input type="file" name="pmpro_profile_file" id="pmpro_profile_file" />';
      }

    echo '</div>';
}
add_action( 'pmpro_show_user_profile', 'my_pmpro_show_profile_file' );


function my_pmpro_save_profile_file( $user_id ) {
    $meta_key = 'pmpro_profile_file';
    $existing = get_user_meta( $user_id, $meta_key, true );


    if ( $existing && ! empty( $_POST['pmpro_profile_file_delete'] ) ) {
        $upload_dir = wp_upload_dir();
        $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $existing );
        if ( file_exists( $file_path ) ) {
            @unlink( $file_path );
        }
        delete_user_meta( $user_id, $meta_key );
        pmpro_setMessage( 'Your file has been deleted.', 'success' );
        return;
    }

    // --- Upload ---
    if ( empty( $_FILES['pmpro_profile_file']['name'] ) ) {
        return;
    }
    // one‐per‐user guard
    if ( $existing ) {
        return;
    }
    // error & size checks
    if ( $_FILES['pmpro_profile_file']['error'] !== UPLOAD_ERR_OK ) {
        pmpro_setMessage( 'File upload error.', 'error' );
        return;
    }
    if ( $_FILES['pmpro_profile_file']['size'] > 10 * 1024 * 1024 ) {
        pmpro_setMessage( 'Please upload a file smaller than 10 MB.', 'error' );
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    // scope to /uploads/pmpro-profile-files/
    add_filter( 'upload_dir', function( $dirs ) {
      $dirs['subdir'] = '/pmpro-profile-files' . $dirs['subdir'];
      $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
      $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
      return $dirs;
    });
    $upload = wp_handle_upload( $_FILES['pmpro_profile_file'], [ 'test_form' => false ] );
    // clean up
    remove_filter( 'upload_dir', '__return_false' );

    if ( isset( $upload['url'] ) ) {
        update_user_meta( $user_id, $meta_key, esc_url_raw( $upload['url'] ) );
        pmpro_setMessage( 'File uploaded successfully.', 'success' );
    } else {
        pmpro_setMessage( 'Upload failed: ' . esc_html( $upload['error'] ), 'error' );
    }
}
add_action( 'pmpro_personal_options_update', 'my_pmpro_save_profile_file', 10, 1 );












	
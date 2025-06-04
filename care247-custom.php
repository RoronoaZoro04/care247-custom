<?php
/*
Plugin Name: Care247 Custom
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
        time()
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






function my_get_visitor_country() {
    if ( ! empty( $_GET['country'] ) ) {
        return strtoupper( preg_replace( '/[^A-Z]/', '', $_GET['country'] ) );
    }
    if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
        return sanitize_text_field( $_SERVER['HTTP_CF_IPCOUNTRY'] );
    }
    $ip   = $_SERVER['REMOTE_ADDR'];
    $resp = wp_remote_get( "https://freegeoip.app/json/{$ip}" );
    if ( is_wp_error( $resp ) ) {
        return 'IN';
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    return ! empty( $data['country_code'] ) ? $data['country_code'] : 'IN';
}

add_filter( 'pre_option_pmpro_currency', function( $pre, $option ) {
    if ( $option !== 'pmpro_currency' ) {
        return $pre;
    }
    if ( my_get_visitor_country() !== 'IN' ) {
        return 'USD';
    }
    return false; 
}, 10, 2 );

add_filter( 'pmpro_currencies', function( $currencies ) {
    if ( empty( $currencies['USD'] ) ) {
        $currencies['USD'] = [
            'name'                => 'US Dollar',
            'symbol'              => '$',
            'position'            => 'before',
            'decimals'            => 2,
            'thousands_separator' => ',',
            'decimal_separator'   => '.',
        ];
    }
    return $currencies;
}, 10 );

function my_static_inr_to_usd_rate() {
    return 1 / 1;
}

add_filter( 'pmpro_level_cost_text', function( $cost_text, $level ) {
    $country = my_get_visitor_country();
    $inr     = floatval( $level->initial_payment );
    if ( $country === 'IN' ) {
        return sprintf(
            'The price for membership is <strong>INR %.2f .</strong>',
            $inr
        );
    }
    $usd = round( $inr * my_static_inr_to_usd_rate(), 2 );
    return sprintf(
        'The price for membership is <strong>USD %.2f .</strong>',
        $usd
    );
}, 10, 2 );

add_action( 'wp_head', function() {
    if ( my_get_visitor_country() !== 'IN' && empty( $_GET['country'] ) ) {
        $cc = my_get_visitor_country();
        ?>
        <script>
        (function(){
            var url = new URL( window.location.href );
            url.searchParams.set( 'country', '<?php echo esc_js( $cc ); ?>' );
            console.log(<?php echo esc_js( $cc ); ?>)
            window.location.replace( url.toString() );
        })();
        </script>
        <?php
    }
}, 1 );
add_filter( 'knitpay_order_args', function( $args, $order ) {
    if ( my_get_visitor_country() !== 'IN' ) {
        $inr_amount = $args['amount'] / 100;
        $usd_amount = round( $inr_amount * my_static_inr_to_usd_rate(), 2 );
        $args['amount']   = intval( $usd_amount * 100 );
        $args['currency'] = 'USD';
    }
    return $args;
}, 10, 2 );








/**
 * Register the shortcode [wallet_form]
 */
add_shortcode( 'wallet_form', 'wallet_form_shortcode' );

function wallet_form_shortcode() {
	ob_start();

	if ( ! is_user_logged_in() ) {
		return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to submit your payment confirmation.</p>';
	}

	if ( isset( $_POST['wallet_form_nonce'] ) && wp_verify_nonce( $_POST['wallet_form_nonce'], 'wallet_form_submit' ) ) {

		$current_user_id = get_current_user_id();
		$full_name       = sanitize_text_field( $_POST['full_name'] );
		$amount          = sanitize_text_field( $_POST['amount'] );

		$new_post = array(
			'post_title'   => $full_name,
			'post_type'    => 'wallet',
			'post_status'  => 'publish',
			'post_author'  => $current_user_id,
		);

		$post_id = wp_insert_post( $new_post );

		if ( is_wp_error( $post_id ) ) {
			echo '<p class="wsf-error">There was an error creating your entry. Please try again.</p>';
		} else {
			if ( function_exists( 'update_field' ) ) {
				update_field( 'amount', $amount, $post_id );
			} else {
				update_post_meta( $post_id, 'amount', $amount );
			}

			if ( ! empty( $_FILES['receipt_file']['name'] ) ) {
				$file      = $_FILES['receipt_file'];
				$upload    = wp_handle_upload( $file, array( 'test_form' => false ) );
				$attach_id = 0;

				if ( isset( $upload['file'] ) ) {
					$file_path     = $upload['file'];
					$file_name     = basename( $file_path );
					$file_type     = wp_check_filetype( $file_name, null );
					$attachment    = array(
						'post_mime_type' => $file_type['type'],
						'post_title'     => sanitize_file_name( $file_name ),
						'post_content'   => '',
						'post_status'    => 'inherit',
					);
					$attach_id     = wp_insert_attachment( $attachment, $file_path, $post_id );
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
					wp_update_attachment_metadata( $attach_id, $attach_data );
					set_post_thumbnail( $post_id, $attach_id );
				}
			}

			echo '<p class="wsf-success">Thank you! Your payment confirmation has been submitted.</p>';
		}
	}

	?>

	<div class="wsf-container">
		<h2>Share Your Payment Confirmation</h2>
		<form method="post" enctype="multipart/form-data" id="walletSubmissionForm">
			<?php wp_nonce_field( 'wallet_form_submit', 'wallet_form_nonce' ); ?>

			<div class="wsf-field">
				<label for="full_name">Full Name <span style="color: #e53e3e;">*</span></label>
				<input
					type="text"
					id="full_name"
					name="full_name"
					class="required"
					placeholder="Full Name"
					required
				/>
				<p class="wsf-error-text" id="wsf-error-full_name">This field is required.</p>
			</div>

			<div class="wsf-field">
				<label for="amount">Amount</label>
				<input
					type="number"
					id="amount"
					name="amount"
					placeholder="Amount"
					step="0.01"
					min="0"
				/>
			</div>

			<div class="wsf-field">
				<label for="receipt_file">Choose a file</label>
				<input
					type="file"
					id="receipt_file"
					name="receipt_file"
					accept="image/*,application/pdf"
				/>
			</div>

			<button type="submit" class="wsf-submit-btn">SUBMIT NOW</button>
		</form>
	</div>

	<script>
		
		(function() {
			var form = document.getElementById('walletSubmissionForm');
			form.addEventListener('submit', function(e) {
				var fullName = document.getElementById('full_name');
				var errorFullName = document.getElementById('wsf-error-full_name');
				if ( fullName.value.trim() === '' ) {
					e.preventDefault();
					fullName.classList.add('required');
					errorFullName.style.display = 'block';
				} else {
					errorFullName.style.display = 'none';
				}
			});
		})();
	</script>
	<?php

	return ob_get_clean();
}


/**
 * Shortcode: [wallet_list]
 * Displays the logged-in user’s wallet entries with totals, a pending amount, an Add Funds button, and a paginated table.
 */

add_shortcode( 'wallet_list', 'wallet_list_shortcode' );

function wallet_list_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your wallet.</p>';
	}

	$current_user_id = get_current_user_id();
	$paged           = isset( $_GET['wallet_page'] ) ? max( 1, intval( $_GET['wallet_page'] ) ) : 1;

	$all_args = array(
		'post_type'      => 'wallet',
		'author'         => $current_user_id,
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
	);
	$all_wallet_posts = get_posts( $all_args );

	$total_submitted = 0;
	$total_paid      = 0;

	if ( $all_wallet_posts ) {
		foreach ( $all_wallet_posts as $wid ) {
			$amount = 0;
			if ( function_exists( 'get_field' ) ) {
				$amount = floatval( get_field( 'amount', $wid ) );
			}
			if ( ! $amount ) {
				$amount = floatval( get_post_meta( $wid, 'amount', true ) );
			}
			$total_submitted += $amount;

			$status = '';
			if ( function_exists( 'get_field' ) ) {
				$status = get_field( 'payment_status', $wid );
			}
			if ( ! $status ) {
				$status = get_post_meta( $wid, 'payment_status', true );
			}
			if ( strtolower( $status ) === 'approved' ) {
				$total_paid += $amount;
			}
		}
	}

	$total_pending = $total_submitted - $total_paid;

	
	$query_args = array(
		'post_type'      => 'wallet',
		'author'         => $current_user_id,
		'posts_per_page' => 10,
		'paged'          => $paged,
		'post_status'    => 'publish',
	);
	$wallet_query = new WP_Query( $query_args );

	ob_start();
	?>

	<div class="wlf-container">
		
		<div class="wlf-header">
			<h2>Your Wallet</h2>
			<a href="<?php echo esc_url( site_url( '/my-wallet/' ) ); ?>" class="wlf-add-btn">Add Funds</a>
			
		</div>

		<div class="wlf-totals">
			<span>Current Balance: ₹<?php echo esc_html( number_format_i18n( $total_submitted, 2 ) ); ?></span>
			<span>Pending Amount: ₹<?php echo esc_html( number_format_i18n( $total_pending, 2 ) ); ?></span>
		</div>
  <div class="wlf-table-responsive">
		<table class="wlf-table">
			<thead>
				<tr>
					<th>Payment Image</th>
					<th>Name</th>
					<th>Amount</th>
					<th>Status</th>
					<th>Date Added</th>
					<th>Date Updated</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( $wallet_query->have_posts() ) {
					while ( $wallet_query->have_posts() ) {
						$wallet_query->the_post();
						$wid    = get_the_ID();
						$title  = get_the_title( $wid );
						$amount = 0;
						if ( function_exists( 'get_field' ) ) {
							$amount = floatval( get_field( 'amount', $wid ) );
						}
						if ( ! $amount ) {
							$amount = floatval( get_post_meta( $wid, 'amount', true ) );
						}
						$status = '';
						if ( function_exists( 'get_field' ) ) {
							$status = get_field( 'payment_status', $wid );
						}
						if ( ! $status ) {
							$status = get_post_meta( $wid, 'payment_status', true );
						}
						if ( ! $status ) {
							$status = 'Pending';
						}
						$date_added   = get_the_date( 'Y-m-d H:i', $wid );
						$date_updated = get_the_modified_date( 'Y-m-d H:i', $wid );
						$thumb_id     = get_post_thumbnail_id( $wid );
						$thumb_url    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 80, 80 ) ) : '';
						?>
						<tr>
							<td>
								<?php if ( $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" class="wlf-thumb" />
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $title ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $amount, 2 ) ); ?></td>
							<td><?php echo esc_html( $status ); ?></td>
							<td><?php echo esc_html( $date_added ); ?></td>
							<td><?php echo esc_html( $date_updated ); ?></td>
						</tr>
						<?php
					}
					wp_reset_postdata();
				} else {
					?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 1rem;">No entries found.</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		</div>

		<div class="wlf-pagination">
			<?php
			$base   = esc_url_raw( add_query_arg( 'wallet_page', '%#%' ) );
			$format = '';
			echo paginate_links( array(
				'base'      => $base,
				'format'    => $format,
				'current'   => $paged,
				'total'     => $wallet_query->max_num_pages,
				'prev_text' => '«',
				'next_text' => '»',
			) );
			?>
		</div>
	</div>
	<?php

	return ob_get_clean();
}


/**
 * Add custom columns (Thumbnail, Amount, Payment Status) to the Wallet CPT admin list table
 */

add_filter( 'manage_wallet_posts_columns', 'wallet_reorder_columns' );
function wallet_reorder_columns( $columns ) {
    $orig = $columns;

    $new = array(
        'cb'     => $orig['cb'],     
        'title'  => $orig['title'],    
        'thumbnail'      => __( 'Image',  ' textdomain' ),
        'amount'         => __( 'Amount', ' textdomain' ),
        'payment_status' => __( 'Status', ' textdomain' ),
        'author' => $orig['author'],
        'date'   => $orig['date'],
    );

    return $new;
}



add_action( 'manage_wallet_posts_custom_column', 'wallet_custom_column_data', 10, 2 );
function wallet_custom_column_data( $column, $post_id ) {
    switch ( $column ) {


        case 'thumbnail':
            $thumb_id  = get_post_thumbnail_id( $post_id );
            if ( $thumb_id ) {
                echo wp_get_attachment_image( $thumb_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;border-radius:4px;' ) );
            } else {
                echo '&mdash;'; 
            }
            break;

        case 'amount':
            if ( function_exists( 'get_field' ) ) {
                $value = get_field( 'amount', $post_id );
            } else {
                $value = get_post_meta( $post_id, 'amount', true );
            }
            if ( $value === '' || $value === null ) {
                echo '<em>—</em>';
            } else {
                echo esc_html( number_format_i18n( floatval( $value ), 2 ) );
            }
            break;

        case 'payment_status':
            if ( function_exists( 'get_field' ) ) {
                $status = get_field( 'payment_status', $post_id );
            } else {
                $status = get_post_meta( $post_id, 'payment_status', true );
            }
            if ( ! $status ) {
                $status = 'Pending';
            }
            echo '<strong>' . esc_html( ucfirst( strtolower( $status ) ) ) . '</strong>';
            break;
    }
}

add_filter( 'manage_edit-wallet_sortable_columns', 'wallet_sortable_columns' );
function wallet_sortable_columns( $columns ) {
    $columns['amount']         = 'amount';
    $columns['payment_status'] = 'payment_status';
    return $columns;
}

add_action( 'pre_get_posts', 'wallet_orderby_columns' );
function wallet_orderby_columns( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $orderby = $query->get( 'orderby' );
    if ( $orderby === 'amount' ) {
        $query->set( 'meta_key', 'amount' );
        $query->set( 'orderby', 'meta_value_num' );
    }
    if ( $orderby === 'payment_status' ) {
        $query->set( 'meta_key', 'payment_status' );
        $query->set( 'orderby', 'meta_value' );
    }
}

/**
 * 1) Output file‐upload / file‐link on the wp-admin user edit screen
 */
function care247_show_profile_file_in_admin( $user ) {
    // Only show if current user can edit this user:
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return;
    }

    $meta_key = 'pmpro_profile_file';
    $file_url = get_user_meta( $user->ID, $meta_key, true );
    ?>
    <h2><?php esc_html_e( 'Profile File (Care247)', 'care247' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="pmpro_profile_file"><?php esc_html_e( 'Upload File', 'care247' ); ?>:</label></th>
            <td>
                <?php if ( $file_url ) : 
                    $filename = basename( $file_url ); ?>
                    <p>
                        <strong><?php esc_html_e( 'Current File:', 'care247' ); ?></strong><br/>
                        <a href="<?php echo esc_url( $file_url ); ?>" target="_blank">
                            <?php echo esc_html( $filename ); ?>
                        </a>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="pmpro_profile_file_delete" value="1">
                            <?php esc_html_e( 'Delete this file', 'care247' ); ?>
                        </label>
                    </p>
                <?php endif; ?>

                <?php if ( ! $file_url ) : ?>
                    <input type="file" name="pmpro_profile_file" id="pmpro_profile_file" /><br/>
                    <span class="description"><?php esc_html_e( 'Maximum size: 10 MB', 'care247' ); ?></span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile',  'care247_show_profile_file_in_admin' );
add_action( 'edit_user_profile',  'care247_show_profile_file_in_admin' );


/**
 * 2) Handle saving / deleting when the admin updates a user
 */
function care247_save_profile_file_from_admin( $user_id ) {
    // Only proceed if current user can edit this user:
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    $meta_key = 'pmpro_profile_file';
    $existing = get_user_meta( $user_id, $meta_key, true );

    // If “Delete this file” was checked:
    if ( $existing && ! empty( $_POST['pmpro_profile_file_delete'] ) ) {
        $upload_dir = wp_upload_dir();
        $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $existing );
        if ( file_exists( $file_path ) ) {
            @unlink( $file_path );
        }
        delete_user_meta( $user_id, $meta_key );
        return;
    }

    // If no new file submitted, bail:
    if ( empty( $_FILES['pmpro_profile_file']['name'] ) ) {
        return;
    }

    // Prevent more than one file per user:
    if ( $existing ) {
        return;
    }

    // Basic error & size checks:
    if ( $_FILES['pmpro_profile_file']['error'] !== UPLOAD_ERR_OK ) {
        // You could add admin notice here if you want.
        return;
    }
    if ( $_FILES['pmpro_profile_file']['size'] > 10 * 1024 * 1024 ) {
        // File too big >10 MB
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    // Force uploads into /uploads/pmpro-profile-files/ subfolder:
    add_filter( 'upload_dir', function( $dirs ) {
        $dirs['subdir'] = '/pmpro-profile-files' . $dirs['subdir'];
        $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
        return $dirs;
    });

    $upload = wp_handle_upload( $_FILES['pmpro_profile_file'], [ 'test_form' => false ] );

    // Remove our upload_dir filter so WordPress returns to normal:
    remove_filter( 'upload_dir', '__return_false' );

    if ( isset( $upload['url'] ) ) {
        update_user_meta( $user_id, $meta_key, esc_url_raw( $upload['url'] ) );
    }
}
add_action( 'personal_options_update',  'care247_save_profile_file_from_admin' );
add_action( 'edit_user_profile_update','care247_save_profile_file_from_admin' );


<?php
/*
Plugin Name: Like Plugin
Plugin URI: https://example.com/
Description: A plugin that adds likes to posts
Version: 1.0
Author: Serhii Odokiienko
Author URI: https://example.com/
Text Domain: like-plugin
*/

function like_plugin_enqueue_scripts() {
	wp_enqueue_script( 'like-plugin-script', plugin_dir_url( __FILE__ ) . 'assets/js/like-plugin.js', array( 'jquery' ), '1.0', true );
	wp_enqueue_style( 'like-plugin-style', plugin_dir_url( __FILE__ ) . 'assets/css/like-plugin.css', array(), '1.0', 'all' );
}
add_action( 'wp_enqueue_scripts', 'like_plugin_enqueue_scripts' );


// Add a like button to the post
function my_like_button( $content ) {
	if ( is_user_logged_in() ) {
		$post_id         = get_the_ID();
		$current_user_id = get_current_user_id();
		$likes           = get_post_meta( $post_id, '_likes', true );
		$liked           = get_post_meta( $post_id, '_liked_' . $current_user_id, true );
		$like_class      = $liked ? 'liked' : '';

		global $post;
		$like_text = 'Like:';
		$user_id   = get_current_user_id();
		if ( $user_id ) {
			$liked = get_post_meta( $post->ID, '_liked_' . $user_id, true );
			if ( $liked ) {
				$like_text = 'Liked:';
			}
		}
		if ( ! is_page() ) {
			$content .= '<div class="like-section">' . esc_html__( 'Social Media:', 'like-plugin' ) . '
							<button type="button" class="my-like-button ' . esc_attr( $like_class ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-user-id="' . esc_attr( $current_user_id ) . '">
								<span class="like-text">' . esc_html( $like_text ) . '</span>
								<span class="like-count">' . esc_html( $likes ) . '</span>
							</button>
						</div>';
		}
	} else {
		$post_id = get_the_ID();
		$likes   = get_post_meta( $post_id, '_likes', true );
		if ( ! is_page() || is_archive() || is_tag() || is_tax( 'category' ) ) {
			$content .= '<div class="like-section">' . esc_html__( 'Social Media:', 'like-plugin' ) . '
							<div class="my-like-button">
								<span class="like-count">' . esc_html( $likes ) . '</span>
							</div> 
						</div>';
		}
	}
	return $content;
}
add_filter( 'the_content', 'my_like_button' );
add_filter( 'the_excerpt', 'my_like_button' );


// Handle the AJAX request to like or unlike a post
function my_handle_like_request() {
	$post_id = ( ! wp_verify_nonce( isset( $_POST['post_id'] ) ) ? intval( $_POST['post_id'] ) : 0 );
	$user_id = ( ! wp_verify_nonce( isset( $_POST['user_id'] ) ) ? intval( $_POST['user_id'] ) : 0 );

	if ( $post_id && $user_id && is_user_logged_in() ) {
		$likes = intval( get_post_meta( $post_id, '_likes', true ) );
		$liked = get_post_meta( $post_id, '_liked_' . $user_id, true );
		if ( $liked ) {
			$likes --;
			delete_post_meta( $post_id, '_liked_' . $user_id );
		} else {
			$likes ++;
			update_post_meta( $post_id, '_liked_' . $user_id, true );
		}
		update_post_meta( $post_id, '_likes', $likes );
		wp_send_json_success(
			array(
				'likes' => $likes,
				'liked' => (bool) $liked,
			)
		);
	}
	wp_send_json_error( 'Invalid request' );
}
add_action( 'wp_ajax_my_like', 'my_handle_like_request' );


// Add the AJAX URL to the page
function my_add_ajax_url() {
	?>
	<script>
		let my_ajax_url = '<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>';
	</script>
	<?php
}
add_action( 'wp_head', 'my_add_ajax_url' );


// Add the script that handles the like button
function my_like_script() {
	?>
	<script>
		jQuery(document).ready(function($) {
			$('.my-like-button').click(function() {
				const button = $(this);
				const post_id = button.data('post-id');
				const user_id = button.data('user-id');
				$.post(my_ajax_url, {
					action: 'my_like',
					post_id: post_id,
					user_id: user_id
				}, function(response) {
					if ( response.success ) {
						const likes = response.data.likes;
						button.find('.like-count').text(likes);
						button.toggleClass('liked');

						if(button.hasClass('liked')) {
							button.find('.like-text').text('Liked:');
						} else {
							button.find('.like-text').text('Like:');
						}
					} else {
						alert( response.data );

					}
				});
			});
		});
	</script>
	<?php
}
add_action( 'wp_footer', 'my_like_script' );

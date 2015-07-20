<?php
/*
Plugin Name:  IONATA's Remove Post Revisions
Description:  Removes revisions for a particular post
Plugin URI:   http://ionata.com.au/
Version:      1.0.0
Author URI:   http://ionata.com.au/
Author:       Ivaylo (Evo) Stamatov @ IONATA
Author email: aviolit@gmail.com
*/

/**
 * Deletes all revisions of a particular post, leaving a set
 * number of revisions to keep.
 *
 * NOTE: Any autosaves will be deleted as well.
 *
 * Returns array($count = -1 , $revisions_to_keep = -1)
 */
function delete_revisions_for_post( $post_id, $just_count = false ) {
	$count = 0;

	$return = array(-1, -1);
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return $return;

	if ( ! $post = get_post( $post_id ) )
		return $return;

	if ( ! post_type_supports( $post->post_type, 'revisions' ) )
		return $return;

	if ( 'auto-draft' == $post->post_status )
		return $return;

	// NOTE: make sure we don't abort if revisions are not enabled
	// if ( ! wp_revisions_enabled( $post ) )
	// 	return $return;

	// If a limit for the number of revisions to keep has been set,
	// delete the oldest ones.
	$revisions_to_keep = wp_revisions_to_keep( $post );

	if ( $revisions_to_keep < 0 )
		return array(-1, $revisions_to_keep);

	$revisions_ids = get_post_revisions_ids( $post_id, array(
		'order'         => 'ASC',
		'check_enabled' => false // make sure not to allow the revisions enable check
		) );

	$delete = count($revisions_ids) - $revisions_to_keep;

	if ( $delete < 1 )
		return $count;

	$revisions_ids = array_slice( $revisions_ids, 0, $delete );

	for ( $i = 0; isset( $revisions_ids[$i] ); $i++ ) {
		// NOTE: get_post_revisions_ids() returns only ids so we cannot check the post_name prop
		// if ( false !== strpos( $revisions_ids[ $i ]->post_name, 'autosave' ) )
		// 	continue;

		if ( ! $just_count ) {
			wp_delete_post_revision( $revisions_ids[ $i ] );
		}

		$count++;
	}

	return array($count, $revisions_to_keep);
}

/**
 * A helper function, almost identical copy of wp_get_post_revisions(),
 * but faster, since it returns only IDs.
 */
function get_post_revisions_ids( $post_id, $args = null ) {
	$post = get_post( $post_id );
	if ( ! $post || empty( $post->ID ) )
		return array();

	$defaults = array( 'order' => 'DESC', 'orderby' => 'date ID', 'check_enabled' => true );
	$args = wp_parse_args( $args, $defaults );

	if ( $args['check_enabled'] && ! wp_revisions_enabled( $post ) )
		return array();

	$args = array_merge( $args, array(
		'post_parent' => $post->ID,
		'post_type'   => 'revision',
		'post_status' => 'inherit',
		'fields'      => 'ids'
		) );

	if ( ! $revisions_ids = get_children( $args ) )
		return array();

	return $revisions_ids;
}

if ( ! is_admin() ) {
	return;
}

add_action( 'init', '_revisions_cleaner_init', 11 );
function _revisions_cleaner_init() {
	//$current_user_id = get_current_user_id();
	_delete_post_revisions_hook();
	add_filter( 'post_row_actions', '_add_revisions_counter_action', 10, 2 );
	add_filter( 'page_row_actions', '_add_revisions_counter_action', 10, 2 );
	add_action( 'admin_notices', '_deleted_post_revisions_admin_notice' );
}

function _add_revisions_counter_action( $actions, $post ) {
	global $revisions_cleaner;
	if ( isset( $revisions_cleaner['allowed_post_types'] ) && ! in_array( $post->post_type, (array)$revisions_cleaner['allowed_post_types'] ) ) {
		return $actions;
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'delete_posts' ) ) {
		return $actions;
	}

	list($count, $revisions_to_keep) = delete_revisions_for_post( $post, $just_count = true );

	if ($count < 1) {
		return $actions;
	}

	$title = sprintf( _n( 'Delete %d revision for this item', 'Delete %d revisions for this item', $count ), $count );
	if ( $revisions_to_keep > 0 ) {
		$title .= __( '. ' );
		$title .= sprintf( _n( 'Keeping %d revision', 'Keeping %d revisions', $revisions_to_keep ), $revisions_to_keep );
	}

	$text = sprintf( _n( 'Delete %d revision', 'Delete %d revisions', $count ), $count );

	$current_url = add_query_arg( array(
		'delete_post_revisions'       => $post->ID,
		'delete_post_revisions_nonce' => wp_create_nonce( 'delete_post_revisions' . $post->ID ),
		), remove_query_arg( 'deleted_post_revisions' ) );
	$actions['delete-revisions'] = '<a href="' . $current_url . '" title="' . esc_attr( $title ) . '">' . esc_html( $text ) . '</a>';
	return $actions;
}

function _delete_post_revisions_hook() {
	$post_id = isset( $_GET['delete_post_revisions'] ) ? (int)$_GET['delete_post_revisions'] : 0;
	if ( ! $post_id ) {
		return;
	}
	$nonce = isset( $_GET['delete_post_revisions_nonce'] ) ? $_GET['delete_post_revisions_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'delete_post_revisions' . $post_id ) ) {
		die( 'Security check' );
	}

	$count = -1;
	if ( current_user_can( 'edit_post', $post_id ) && current_user_can( 'delete_posts' ) ) {
		list($count, $revisions_to_keep) = delete_revisions_for_post( $post_id );
	}

	$new_url = add_query_arg( 'deleted_post_revisions', $count, remove_query_arg( array(
		'delete_post_revisions',
		'delete_post_revisions_nonce',
		) ) );
	wp_redirect( $new_url );
	exit;
}

function _deleted_post_revisions_admin_notice() {
	$count = isset( $_GET['deleted_post_revisions'] ) ? (int)$_GET['deleted_post_revisions'] : 0;
	if ( $count < 1 ) {
		return;
	}

	?>

    <div class="updated">
        <p><?php printf( _n( 'Deleted %d revision!', 'Deleted %d revisions!', $count ), $count ); ?></p>
    </div><?php
}

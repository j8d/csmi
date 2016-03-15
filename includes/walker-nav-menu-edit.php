<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/* Create HTML list of nav menu items in Menus admin page. */
class CSMI_Walker_Nav_Menu_Edit extends Walker_Nav_Menu_Edit {
	function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
            $tempOutput = "";
            parent::start_el( $tempOutput, $item, $depth, $args, $id );
            $item_id = esc_attr( $item->ID );
            $custom_fields_arr = apply_filters( 'wp_nav_menu_item_custom_fields', array(), $item_id, $depth, $args, $id );
            $custom_fields = "";
            foreach ( $custom_fields_arr as $custom_field ) {
                $custom_fields .= $custom_field;
            }
            $position = stripos( $tempOutput, '<p class="field-move' );
            $output .= substr_replace( $tempOutput, $custom_fields, $position, 0 );
	}
}
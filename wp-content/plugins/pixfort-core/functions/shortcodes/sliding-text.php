<?php


/* ---------------------------------------------------------------------------
* Sliding text [sliding-text] [/sliding-text]
* --------------------------------------------------------------------------- */
if( ! function_exists( 'sc_sliding_text' ) ){
	function sc_sliding_text( $attr, $content = null ){
		extract(shortcode_atts(array(
			'position'  => 'left',
			'size'  => 'h1',
			'bold'  => 'font-weight-bold',
			'italic'  => '',
			'secondary_font'  => 'body-font',
			'text_color'  => 'heading-default',
			'text_custom_color'  => '',
			'display'  => '',
			'max_width'  => '',
			'remove_mb'  => false,
			'css'  => '',
		), $attr));

		$css_class = '';
		if(function_exists('vc_shortcode_custom_css_class')){
			$css_class = apply_filters( VC_SHORTCODE_CUSTOM_CSS_FILTER_TAG, vc_shortcode_custom_css_class( $css, ' ' ) );
		}
		if($secondary_font!='secondary-font'){$secondary_font= 'body-font';}
		$classes = array();
		if(!empty($bold)) array_push($classes, $bold );
		if(!empty($italic)) array_push($classes, $italic );
		if(!empty($secondary_font)) array_push($classes, $secondary_font );
		if(!empty($display)) array_push($classes, $display );




		$t_color = $secondary_font .' ';
		$t_custom_color = '';
		if(!empty($text_color)){
			if($text_color!='custom'){
				$t_color .= 'text-'.$text_color;
				// if($text_color!='gradient-primary2'){
				// 	$t_color .= 'text-'.$text_color;
				// }else{
				// 	array_push($classes, 'text-gradient-primary' );
				// }

			}else{
				$t_custom_color = 'color:'.$text_custom_color.';';
			}
		}
		$custom_style = '';

		$class_names = join( ' ', $classes );

		$pix_mb = '';
		if(!$remove_mb){
			$pix_mb = 'mb-3';
		}
		$output = '<div class="'.$pix_mb.' text-'.$position.' '.$css_class.'">';
			if(!empty($max_width)) {
				$custom_style = 'style="max-width:'.$max_width.';"';
				$output .= '<div class="d-inline-block" '.$custom_style.'>';
			}
			$output .= '<'.$size.' class="mb-32 pix-sliding-headline '.$class_names.'" data-class="'.$t_color.'" data-style="'.$t_custom_color.'">'. do_shortcode( $content ) .'</'.$size.'>';
			if(!empty($max_width)) {
				$output .= '</div>';
			}
		$output .= '</div>';


		return $output;
	}
}


add_shortcode( 'sliding-text', 'sc_sliding_text' );

?>

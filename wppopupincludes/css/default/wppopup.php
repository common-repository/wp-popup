<div id='<?php echo $wppopup_messagebox; ?>' class='visiblebox' style='<?php echo $style; ?>'>
	<a href='' id='closebox' title='Close this box'></a>
	<div id='message' style='<?php echo $box; ?>'>
		<?php echo do_shortcode($wppopup_content); ?>
		<div class='clear'></div>
		<?php if($wppopup_hideforever != 'yes') {
			?>
			<div class='claimbutton hide'><a href='#' id='clearforever'><?php _e('Never see this message again.','wppopup'); ?></a></div>
			<?php
		}
		?>
	</div>
	<div class='clear'></div>
</div>
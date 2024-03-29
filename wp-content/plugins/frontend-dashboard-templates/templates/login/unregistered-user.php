<?php
/**
 * Logged in User form
 */

$details = fed_login_form();

do_action( 'fed_before_login_form' );
?>
	<div class="bc_fed container-fluid fed_login_container fed_template1">
		<?php echo fed_loader(); ?>
		<div class="row  flex-center fed_template1_container">
			<div class="col-md-6">
				<div class="flex-center">
					<?php echo fedt_get_website_logo(); ?>
				</div>
				<div class="fed_login_menus">
					<div class="fed_login_wrapper">
						<?php
						$hide = '';
						foreach ( $details as $key => $detail ) {
							if ( isset( $_GET['action'] ) ) {
								if ( 'fed_reset' == $_GET['action'] && $key == 'Reset Password' ) {
									$detail['selected'] = true;
									$hide               = '';
								} elseif ( 'fed_forgot' == $_GET['action'] && $key == 'Forgot Password' ) {
									$detail['selected'] = true;
									$hide               = '';
								} else {
									$detail['selected'] = false;
									$hide               = 'hide';
								}
							} else {
								if ( $key == 'Reset Password' ) {
									$hide = 'hide';
								}
							}
							?>
							<div class="fed_tab_menus <?php
									echo $detail['selected'] == true ? 'fed_selected' : '';
									echo $hide;
									?>" id="<?php echo $detail['menu']['id'] ?>">
								<?php echo $detail['menu']['name'] ?>
							</div>
							<?php
						}
						?>

					</div>
				</div>
				<div class="fed_login_content">
					<?php foreach ( $details as $key => $detail ) {
						if ( isset( $_GET['action'] ) ) {
							if ( $key == 'Reset Password' && 'fed_reset' == $_GET['action'] ) {
								$detail['selected'] = true;
							} elseif ( 'fed_forgot' == $_GET['action'] && $key == 'Forgot Password' ) {
								$detail['selected'] = true;
							} else {
								$detail['selected'] = false;
							}
						}
						?>

						<div class="fed_tab_content <?php
						echo $detail['selected'] == false ? 'hide' : ''; ?>" data-id="<?php echo $detail['menu']['id'] ?>">
                            <?php do_action('fed_above_login_form'); ?>
							<form method="post"  class="fed_form_post">
								<?php
								fed_wp_nonce_field( 'fed_nonce', 'fed_nonce' );
								$contents = $detail['content'];
								uasort( $contents, 'fed_sort_by_order' );
                                foreach ($contents as $content) {
                                    $label = null;
                                    if ( ! empty($content['extended'])) {
                                        $extended = maybe_unserialize($content['extended']);
                                        if (isset($extended['label'])) {
                                            $label =  $extended['label'];
                                        }
                                    }
                                    ?>
                                    <div class="form-group">
                                        <?php echo ! empty($content['name']) && $label === null ? '<label>'.$content['name'].'</label>' : ''; ?>

                                        <?php echo $content['input'] ?>
                                        <?php
                                        echo $label !== null ? '<label>'.$content['name'].'</label>' : '';
                                        ?>
                                    </div>
                                    <?php
                                }
								?>
								<div class="form-group">
									<div class="text-center">
										<input type="hidden"
											   name="submit"
											   value="<?php echo $key ?>"/>
										<button class="btn btn-primary" type="submit"><?php echo $detail['button'] ?></button>
									</div>
								</div>
							</form>
                            <?php do_action('fed_below_login_form'); ?>
						</div>

						<?php
					} ?>
				</div>
			</div>
		</div>
	</div>
<?php
do_action( 'fed_after_login_form' );
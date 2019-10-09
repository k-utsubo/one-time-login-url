<?php
function one_time_login_url_settings_page() {
?>
  <div class="wrap">
    <h2>Your Plugin Page Title</h2>
    <form method="post" action="options.php">
      <?php settings_fields( 'one-time-login-url-settings-group' ); ?>
      <?php do_settings_sections( 'one-time-login-url-settings-group' ); ?>
      <table class="form-table">
        <tr valign="top">
        <th scope="row">New Option Name</th>
        <td><input type="text" name="new_option_name" value="<?php echo esc_attr( get_option('new_option_name') ); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row">Some Other Option</th>
        <td><input type="text" name="other_option_name" value="<?php echo esc_attr( get_option('some_other_option') ); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row">Options, Etc.</th>
        <td><input type="text" name="option_etc" value="<?php echo esc_attr( get_option('option_etc') ); ?>" /></td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>


<?php
}
?>

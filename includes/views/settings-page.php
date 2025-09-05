<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'LLM Visibility Monitor - Settings', 'llm-visibility-monitor' ); ?></h1>

    <p>
        <a class="button llmvm-button-margin" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>">
            <?php echo esc_html__( 'Open Dashboard', 'llm-visibility-monitor' ); ?>
        </a>
    </p>

    <?php
    // Check for run completion message with proper sanitization and nonce verification
    $run_completed = '';
    if ( isset( $_GET['llmvm_ran'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'llmvm_run_completed' ) ) {
        $run_completed = sanitize_text_field( wp_unslash( $_GET['llmvm_ran'] ) );
    }
    if ( '1' === $run_completed ) :
    ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Run completed. Latest responses are visible on the Dashboard.', 'llm-visibility-monitor' ) ?></p></div>
    <?php endif; ?>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'llmvm_settings' );
        do_settings_sections( 'llmvm-settings' );
        submit_button( __( 'Save Settings', 'llm-visibility-monitor' ) );
        ?>
    </form>

    <hr />
    
    <h3><?php echo esc_html__( 'Personal Settings', 'llm-visibility-monitor' ); ?></h3>
    
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="llmvm_save_timezone" />
        <?php wp_nonce_field( 'llmvm_user_timezone', 'llmvm_timezone_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="llmvm_timezone"><?php echo esc_html__( 'Timezone', 'llm-visibility-monitor' ); ?></label>
                </th>
                <td>
                    <?php
                    $current_timezone = get_user_meta( get_current_user_id(), 'llmvm_timezone', true );
                    if ( empty( $current_timezone ) ) {
                        $current_timezone = get_option( 'timezone_string' );
                        if ( empty( $current_timezone ) ) {
                            $gmt_offset = get_option( 'gmt_offset' );
                            $current_timezone = $gmt_offset !== false ? sprintf( '%+03d:00', $gmt_offset ) : 'UTC';
                        }
                    }
                    
                    // Get list of timezones
                    $timezones = timezone_identifiers_list();
                    ?>
                    <select name="llmvm_timezone" id="llmvm_timezone" class="regular-text">
                        <option value=""><?php echo esc_html__( 'Use site default', 'llm-visibility-monitor' ); ?></option>
                        <?php foreach ( $timezones as $timezone ) : ?>
                            <option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( $current_timezone, $timezone ); ?>>
                                <?php echo esc_html( $timezone ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php echo esc_html__( 'Choose your preferred timezone for displaying dates and times. If not set, the site default timezone will be used.', 'llm-visibility-monitor' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Save Timezone', 'llm-visibility-monitor' ), 'secondary', 'save_timezone' ); ?>
    </form>

    <?php if ( current_user_can( 'llmvm_manage_settings' ) ) : ?>
        <hr />
        
        <h3><?php echo esc_html__( 'Available Roles and Capabilities', 'llm-visibility-monitor' ); ?></h3>
        <p><?php echo esc_html__( 'To grant limited admin access to other users, assign them the "LLM Manager Free" or "LLM Manager Pro" role through the WordPress Users page.', 'llm-visibility-monitor' ); ?></p>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Role', 'llm-visibility-monitor' ); ?></th>
                    <th><?php echo esc_html__( 'Capabilities', 'llm-visibility-monitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html__( 'Administrator', 'llm-visibility-monitor' ); ?></strong></td>
                    <td><?php echo esc_html__( 'Full access: Settings, Prompts, Dashboard, Results (Unlimited usage)', 'llm-visibility-monitor' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'LLM Manager Pro', 'llm-visibility-monitor' ); ?></strong></td>
                    <td><?php echo esc_html__( 'Limited access: Prompts, Dashboard, Results (no Settings) - Pro plan with higher usage limits', 'llm-visibility-monitor' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'LLM Manager Free', 'llm-visibility-monitor' ); ?></strong></td>
                    <td><?php echo esc_html__( 'Limited access: Prompts, Dashboard, Results (no Settings) - Free plan with basic usage limits', 'llm-visibility-monitor' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Other Roles', 'llm-visibility-monitor' ); ?></strong></td>
                    <td><?php echo esc_html__( 'No LLM access', 'llm-visibility-monitor' ); ?></td>
                </tr>
            </tbody>
        </table>
        
        <p>
            <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-secondary">
                <?php echo esc_html__( 'Manage Users and Roles', 'llm-visibility-monitor' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-prompts' ) ); ?>" class="button button-secondary">
                <?php echo esc_html__( 'Manage Prompts', 'llm-visibility-monitor' ); ?>
            </a>
        </p>
    <?php endif; ?>
</div>



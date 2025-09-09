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
    

    <form action="options.php" method="post" id="llmvm-settings-form">
        <?php
        settings_fields( 'llmvm_settings' );
        do_settings_sections( 'llmvm-settings' );
        submit_button( __( 'Save Settings', 'llm-visibility-monitor' ) );
        ?>
    </form>


    <?php if ( current_user_can( 'llmvm_manage_settings' ) ) : ?>
        <hr />
        
        <h3><?php echo esc_html__( 'Available Roles and Capabilities', 'llm-visibility-monitor' ); ?></h3>
        <p><?php echo esc_html__( 'To grant limited admin access to other users, assign them the "LLM Manager Free", "LLM Manager Pro", or "SC Customer" role through the WordPress Users page.', 'llm-visibility-monitor' ); ?></p>
        
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
                    <td><strong><?php echo esc_html__( 'SC Customer', 'llm-visibility-monitor' ); ?></strong></td>
                    <td><?php echo esc_html__( 'Limited access: Prompts, Dashboard, Results (no Settings) - Free plan with basic usage limits. Can bypass /wp-admin/ restrictions for specific pages.', 'llm-visibility-monitor' ); ?></td>
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
        
        <?php if ( current_user_can( 'llmvm_manage_settings' ) ) : ?>
            <hr />
            <h3><?php echo esc_html__( 'Login Page Customization', 'llm-visibility-monitor' ); ?></h3>
            <p><?php echo esc_html__( 'Customize the WordPress login page with your own branding. The login page will show "LLM Visibility Monitor" instead of the WordPress logo, and you can add custom text below it.', 'llm-visibility-monitor' ); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Login Page Custom Text', 'llm-visibility-monitor' ); ?></th>
                    <td>
                        <?php
                        $options = get_option( 'llmvm_options', [] );
                        if ( ! is_array( $options ) ) {
                            $options = [];
                        }
                        $value = isset( $options['login_custom_text'] ) ? (string) $options['login_custom_text'] : '';
                        ?>
                        <textarea name="llmvm_options[login_custom_text]" id="llmvm_login_custom_text" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
                        <p class="description"><?php echo esc_html__( 'Enter custom text to display below the site name on the login page. You can use HTML tags like &lt;strong&gt;, &lt;em&gt;, and &lt;a&gt; for formatting.', 'llm-visibility-monitor' ); ?></p>
                        <p class="description"><?php echo esc_html__( 'Example: &lt;strong&gt;Welcome!&lt;/strong&gt; Please log in to access &lt;a href="https://docs.openstream.ch"&gt;documentation&lt;/a&gt;.', 'llm-visibility-monitor' ); ?></p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>



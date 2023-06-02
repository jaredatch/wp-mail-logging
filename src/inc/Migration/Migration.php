<?php

namespace No3x\WPML\Migration;

use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\WPML_Utils;

class Migration {

    /**
     * Version of the latest migration.
     *
     * @since {VERSION}
     *
     * @var int
     */
    const VERSION = 2;

    /**
     * Option key where we save the current DB version.
     *
     * @since {VERSION}
     *
     * @var string
     */
    const OPTION_NAME = 'wp_mail_logging_db_version';

    /**
     * Nonce for migration.
     *
     * @since {VERSION}
     *
     * @var string
     */
    const MIGRATION_NONCE = 'wp_mail_logging_migration_nonce';

    /**
     * Current migration version.
     *
     * @since {VERSION}
     *
     * @var int
     */
    private $current_version;

    /**
     * Flag to indicate if a migration is needed.
     *
     * @since {VERSION}
     *
     * @var bool
     */
    private $is_migration_needed = false;

    /**
     * Current migration's error.
     *
     * @since {VERSION}
     *
     * @var string
     */
    private $error;

    /**
     * Constructor
     *
     * @since {VERSION}
     */
    public function __construct() {


        $this->hooks();
    }

    /**
     * WP Hooks.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function hooks() {

        add_action( 'current_screen', [ $this, 'init'] );
        add_action( 'admin_notices', [ $this, 'display_migration_notices' ] );
        add_action( 'wp_mail_logging_admin_tab_content_before', [ $this, 'display_migration_button' ] );
    }

    /**
     * Init the migration UI and process if requested.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function init() {

        global $wp_logging_list_page;

        $current_screen = get_current_screen();

        if ( $current_screen->id !== $wp_logging_list_page || ! version_compare( $this->get_current_version(), self::VERSION, '<' ) ) {
            return;
        }

        $this->is_migration_needed = true;

        // Check if migration is requested.
        if ( ! empty( $_GET['migration'] ) && check_admin_referer( self::MIGRATION_NONCE, 'nonce' ) ) {
            $this->run( self::VERSION );
        }
    }

    /**
     * Get current DB version.
     *
     * @since {VERSION}
     *
     * @return int
     */
    private function get_current_version() {

        if ( is_null( $this->current_version ) ) {

            $this->current_version = (int) get_option( self::OPTION_NAME, 0 );
        }

        return $this->current_version;
    }

    /**
     * Run the migrations.
     *
     * @since {VERSION}
     *
     * @param int $version The version of migration to run.
     *
     * @return void
     */
    private function run( $version ) {

        if ( method_exists( $this, "migrate_to_{$version}" ) ) {
            $this->{"migrate_to_{$version}"}();

            return;
        }

        $this->error = "Unable to find migration to version {$version}.";
    }

    /**
     * Display the migration-related notices.
     *
     * @since {VERSION}
     *
     * @return void
     */
    public function display_migration_notices() {

        global $wp_logging_list_page;

        $current_screen = get_current_screen();

        if ( $current_screen->id === $wp_logging_list_page && ! empty( $_GET['tab'] ) && $_GET['tab'] === 'settings' ) {
            return;
        }

        if ( $this->is_migration_needed ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    printf(
                        wp_kses(
                            __( 'A database upgrade is available. Click <a href="%s">here</a> to start the upgrade.', 'wp-mail-logging' ),
                            [
                                'a' => [
                                    'href' => []
                                ],
                            ]
                        ),
                        esc_url( add_query_arg( 'tab', 'settings', WPML_Utils::get_admin_page_url() ) )
                    ); ?>
                </p>
            </div>
            <?php
        }

        if ( empty( $this->error ) ) {
            return;
        }

        // Show error.
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $this->error ); ?></p>
        </div>
        <?php
    }

    /**
     * Display the migration button in Settings.
     *
     * @since {VERSION}
     *
     * @param string $tab Current tab in WP Mail Logging page.
     *
     * @return void
     */
    public function display_migration_button( $tab ) {

        if ( ! $this->is_migration_needed || $tab !== 'settings' ) {
            return;
        }
        ?>
        <div class="wp-mail-logging-setting-row wp-mail-logging-setting-row-no-border wp-mail-logging-setting-row-content wp-mail-logging-clearfix section-heading">
            <div class="wp-mail-logging-setting-field">
                <h2><?php echo esc_html__( 'Database upgrade', 'wp-mail-logging' ) ?></h2>
            </div>

            <p>
                <?php
                    printf(
                        wp_kses(
                            __( '<strong>Important!</strong> Please secure a backup of your database before performing the upgrade.', 'wp-mail-logging' ),
                            [
                                'strong' => [],
                            ]
                        )
                    );
                ?>
            </p>

            <p>
                <?php
                $migration_button_url = add_query_arg(
                    [
                        'tab'       => 'settings',
                        'migration' => '1',
                        'nonce'     => wp_create_nonce( self::MIGRATION_NONCE ),
                    ],
                    WPML_Utils::get_admin_page_url()
                )
                ?>
                <a class="button button-primary" href="<?php echo esc_url( $migration_button_url ); ?>">Upgrade</a>
            </p>
        </div>
        <?php
    }

    /**
     * Migration from 0 to 1.
     * Convert the columns charset to utf8mb4.
     *
     * @since {VERSION}
     *
     * @return void
     */
    private function migrate_to_1() {

        global $wpdb;

        if ( strpos( $wpdb->collate, 'utf8mb4' ) !== false ) {
            $query = $wpdb->prepare(
                'ALTER TABLE %1$s
                        MODIFY `host` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %2$s,
                        MODIFY `receiver` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %3$s,
                        MODIFY `subject` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %4$s,
                        MODIFY `message` TEXT CHARACTER SET utf8mb4 COLLATE %5$s,
                        MODIFY `headers` TEXT CHARACTER SET utf8mb4 COLLATE %6$s,
                        MODIFY `attachments` VARCHAR(800) CHARACTER SET utf8mb4 COLLATE %7$s,
                        MODIFY `error` VARCHAR(400) CHARACTER SET utf8mb4 COLLATE %8$s,
                        MODIFY `plugin_version` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE %9$s;',
                WPML_Mail::get_table(),
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate,
                $wpdb->collate
            );

            if ( $wpdb->query( $query ) === false ) {
                $this->set_error_msg( $wpdb->last_error, 1 );
                return;
            }

            // Update the DB version.
            update_option( self::OPTION_NAME, 1, false );
        }
    }

    /**
     * Set the error message.
     *
     * @since {VERSION}
     *
     * @param string $error   Error occured during migration.
     * @param int    $version Version of migration.
     *
     * @return void
     */
    private function set_error_msg( $error, $version ) {

        $this->error = "Unable to complete migration to version {$version}. Error: {$error}";
    }
}

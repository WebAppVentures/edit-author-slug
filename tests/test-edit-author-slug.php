<?php
/**
 * Test the main class functionality.
 *
 * @package Edit_Author_Slug
 * @subpackage Tests
 */

/**
 * The Edit Author Slug unit test case.
 */
class BA_EAS_Tests_BA_Edit_Author_Slug extends WP_UnitTestCase {

	/**
	 * The admin `tearDown` method.
	 *
	 * @since 1.1.0
	 *
	 * Resets the current user and globals.
	 */
	public function tearDown() {
		parent::tearDown();
		ba_eas()->author_base   = 'author';
		ba_eas()->do_role_based = false;
	}

	/**
	 * Filter the text domain, so that something is loaded for testing.
	 *
	 * @since 1.5.0
	 *
	 * @todo When WP 4.6 is the minimum version, remove fallback.
	 *
	 * @param bool   $override Whether to override the .mo file loading. Default false.
	 * @param string $domain   Text domain. Unique identifier for retrieving translated strings.
	 * @param string $file     Path to the MO file.
	 *
	 * @return bool
	 */
	function _override_load_textdomain_filter( $override, $domain, $file ) {
		global $l10n, $wp_version;

		$file = WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo';
		if ( version_compare( $wp_version, '4.6', '<' ) ) {
			$file = DIR_TESTDATA . '/pomo/overload.mo';
		}

		if ( ! is_readable( $file ) ) {
			return false;
		}

		$mo = new MO();

		if ( ! $mo->import_from_file( $file ) ) {
			return false;
		}

		if ( isset( $l10n[ $domain ] ) ) {
			$mo->merge_with( $l10n[ $domain ] );
		}

		$l10n[ $domain ] = &$mo;

		return true;
	}

	/**
	 * Test for `BA_Edit_Author_Slug::setup_globals()`.
	 *
	 * @since 1.2.0
	 *
	 * @covers BA_Edit_Author_Slug::setup_globals
	 */
	public function test_setup_globals() {
		$file = dirname( dirname( __FILE__ ) ) . '/edit-author-slug.php';
		$this->assertEquals( $file, ba_eas()->file );
		$this->assertEquals( plugin_dir_path( $file ), ba_eas()->plugin_dir );
		$this->assertEquals( plugin_dir_url( $file ), ba_eas()->plugin_url );
		$this->assertEquals( plugin_basename( $file ), ba_eas()->plugin_basename );
		$this->assertEquals( 'author', ba_eas()->author_base );
		$this->assertEquals( 'username', ba_eas()->default_user_nicename );
		$this->assertEquals( false, ba_eas()->remove_front );
		$this->assertEquals( false, ba_eas()->do_auto_update );
		$this->assertEquals( false, ba_eas()->do_role_based );
		// $this->assertEquals( array(), ba_eas()->role_slugs );
	}

	/**
	 * Test for `BA_Edit_Author_Slug::setup_actions()`.
	 *
	 * @since 1.1.0
	 *
	 * @covers BA_Edit_Author_Slug::setup_actions
	 */
	public function test_setup_actions() {
		$this->assertEquals( 10, has_action( 'activate_' . ba_eas()->plugin_basename, 'ba_eas_activation' ) );
		$this->assertEquals( 10, has_action( 'deactivate_' . ba_eas()->plugin_basename, 'ba_eas_deactivation' ) );
		$this->assertEquals( 10, has_action( 'after_setup_theme', array( ba_eas(), 'set_role_slugs' ) ) );
		$this->assertEquals( 4,  has_action( 'init', 'ba_eas_wp_rewrite_overrides' ) );
		$this->assertEquals( 20, has_action( 'init', array( ba_eas(), 'add_rewrite_tags' ) ) );
		$this->assertEquals( 10, has_action( 'plugins_loaded', array( ba_eas(), 'load_textdomain' ) ) );
	}

	/**
	 * Test the `BA_Edit_Author_Slug::__get()`.
	 *
	 * @since 1.5.0
	 *
	 * @covers BA_Edit_Author_Slug::__get
	 * @expectedIncorrectUsage BA_Edit_Author_Slug::version
	 * @expectedIncorrectUsage BA_Edit_Author_Slug::db_version
	 */
	public function test__get() {

		$this->assertNull( ba_eas()->__get( 'fake_property' ) );

		$actual = ba_eas()->__get( 'version' );
		$this->assertSame( BA_Edit_Author_Slug::VERSION, $actual );

		$actual = ba_eas()->__get( 'db_version' );
		$this->assertSame( BA_Edit_Author_Slug::DB_VERSION, $actual );
	}

	/**
	 * Test for `BA_Edit_Author_Slug::load_textdomain()`.
	 *
	 * @since 1.2.0
	 *
	 * @covers BA_Edit_Author_Slug::load_textdomain
	 */
	public function test_load_textdomain() {

		// Make sure the text domain isn't already loaded.
		unload_textdomain( 'edit-author-slug' );
		$this->assertFalse( is_textdomain_loaded( 'edit-author-slug' ) );

		add_filter( 'override_load_textdomain', array( $this, '_override_load_textdomain_filter' ), 10, 3 );
		ba_eas()->load_textdomain();
		remove_filter( 'override_load_textdomain', array( $this, '_override_load_textdomain_filter' ) );

		$this->assertTrue( is_textdomain_loaded( 'edit-author-slug' ) );

		unload_textdomain( 'edit-author-slug' );
	}

	/**
	 * Test for `BA_Edit_Author_Slug::author_base_rewrite()`.
	 *
	 * @since 1.1.0
	 *
	 * @covers BA_Edit_Author_Slug::author_base_rewrite
	 *
	 * @expectedDeprecated BA_Edit_Author_Slug::author_base_rewrite
	 */
	public function test_author_base_rewrite() {
		$this->assertNull( ba_eas()->author_base_rewrite() );
	}

	/**
	 * Test for `BA_Edit_Author_Slug::set_role_slugs()`.
	 *
	 * @since 1.1.0
	 *
	 * @covers BA_Edit_Author_Slug::set_role_slugs
	 */
	public function test_set_role_slugs() {

		$default_role_slugs = ba_eas()->role_slugs;

		$role_slugs                       = $default_role_slugs;
		$role_slugs['subscriber']['slug'] = 'test';

		update_option( '_ba_eas_role_slugs', $role_slugs );
		ba_eas()->set_role_slugs();
		$this->assertEquals( ba_eas()->role_slugs, $role_slugs );
		update_option( '_ba_eas_role_slugs', $default_role_slugs );

		ba_eas()->role_slugs = $default_role_slugs;
	}

	/**
	 * Test for `BA_Edit_Author_Slug::set_role_slugs()` when a custom role exists.
	 *
	 * @since 1.6.0
	 *
	 * @covers BA_Edit_Author_Slug::set_role_slugs
	 */
	public function test_set_role_slugs_custom_role() {

		$default_role_slugs = ba_eas()->role_slugs;

		$role_slugs = $default_role_slugs + array(
			'foot-soldier' => array(
				'name' => 'Foot Soldier',
				'slug' => 'foot-soldier',
			),
		);

		add_role( 'foot-soldier', 'Foot Soldier' );
		ba_eas()->set_role_slugs();
		$this->assertEquals( ba_eas()->role_slugs, $role_slugs );
		remove_role( 'foot-soldier' );

		ba_eas()->role_slugs = $default_role_slugs;
	}

	/**
	 * Test for `BA_Edit_Author_Slug::add_rewrite_tags()`.
	 *
	 * @since 1.1.0
	 *
	 * @covers BA_Edit_Author_Slug::add_rewrite_tags
	 */
	public function test_add_rewrite_tags() {

		// Check for return when role-based author base is disabled.
		$this->assertNull( ba_eas()->add_rewrite_tags() );

		// Check that rewrite tags have been added when role-based author base is on.
		$wp_rewrite = $GLOBALS['wp_rewrite'];

		add_filter( 'ba_eas_do_role_based_author_base', '__return_true' );

		// Test for WP default roles/role slugs.
		ba_eas()->add_rewrite_tags();
		$slugs = '(administrator|editor|author|contributor|subscriber)';

		$this->assertTrue( in_array( '%ba_eas_author_role%', $wp_rewrite->rewritecode, true ) );
		$this->assertTrue( in_array( $slugs, $wp_rewrite->rewritereplace, true ) );

		$old_author_base = 'test/base';
		ba_eas()->author_base = '%ba_eas_author_role%';
		$this->assertTrue( in_array( '%ba_eas_author_role%', $wp_rewrite->rewritecode, true ) );
		$this->assertTrue( in_array( $slugs, $wp_rewrite->rewritereplace, true ) );
		ba_eas()->author_base = $old_author_base;

		// Test for WP custom roles/role slugs.
		ba_eas()->role_slugs = ba_eas_tests_slugs_custom();
		ba_eas()->add_rewrite_tags();
		$slugs = '(jonin|chunin|mystic|junior-genin|deshi|author)';

		$this->assertTrue( in_array( $slugs, $wp_rewrite->rewritereplace, true ) );

		// Test for WP custom roles/role slugs.
		ba_eas()->role_slugs = ba_eas_tests_slugs_extra();
		ba_eas()->add_rewrite_tags();
		$slugs = '(administrator|editor|author|contributor|subscriber|foot-soldier)';

		$this->assertTrue( in_array( $slugs, $wp_rewrite->rewritereplace, true ) );

		remove_filter( 'ba_eas_do_role_based_author_base', '__return_true' );
	}

	/**
	 * Test for `ba_eas_activation()`.
	 *
	 * @since 1.1.0
	 *
	 * @covers ::ba_eas_activation
	 */
	public function test_ba_eas_activation() {
		ba_eas_activation();
		$this->assertTrue( (bool) did_action( 'ba_eas_activation' ) );
	}

	/**
	 * Test for `ba_eas_deactivation()`.
	 *
	 * @since 1.1.0
	 *
	 * @covers ::ba_eas_deactivation
	 */
	public function test_ba_eas_deactivation() {
		ba_eas_deactivation();
		$this->assertTrue( (bool) did_action( 'ba_eas_deactivation' ) );
	}
}

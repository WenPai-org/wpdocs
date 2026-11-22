<?php
/**
 * WPMind integration unit tests (no WordPress bootstrap, no WPMind installed).
 *
 *   php tests/WPMindIntegrationTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'WPDOCS_VERSION' ) ) {
	define( 'WPDOCS_VERSION', '0.3.0' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

// Lightweight WordPress stubs for standalone test execution.
$GLOBALS['wpdocs_test_filters']    = array();
$GLOBALS['wpdocs_test_options']    = array();
$GLOBALS['wpdocs_test_posts']      = array();
$GLOBALS['wpdocs_test_post_meta']  = array();
$GLOBALS['wpdocs_test_bindings']   = array();
$GLOBALS['wpdocs_test_post_types'] = array();

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		public int $ID             = 0;
		public string $post_title  = '';
		public string $post_name   = '';
		public string $post_status = 'publish';
		public string $post_type   = 'post';
		public string $post_excerpt = '';
		public string $post_content = '';

		/** @param array<string,mixed> $data */
		public function __construct( array $data = array() ) {
			foreach ( $data as $k => $v ) {
				$this->$k = $v;
			}
		}
	}
}

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public string $code;
		public string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
	}
}

function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['wpdocs_test_filters'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
	return true;
}

function apply_filters( string $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['wpdocs_test_filters'][ $hook ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['wpdocs_test_filters'][ $hook ] as $item ) {
		$cb    = $item['callback'];
		$slice = array_slice( $args, 0, max( 0, (int) $item['accepted_args'] - 1 ) );
		$value = $cb( $value, ...$slice );
	}
	return $value;
}

function get_option( string $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['wpdocs_test_options'] )
		? $GLOBALS['wpdocs_test_options'][ $name ]
		: $default;
}

function sanitize_key( $key ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function untrailingslashit( $value ): string {
	return rtrim( (string) $value, '/\\' );
}

function esc_url_raw( $url ): string {
	return (string) $url;
}

function absint( $v ): int {
	return abs( (int) $v );
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function add_query_arg( array $args, string $base ): string {
	return $base . '?' . http_build_query( $args );
}

function get_post( $post = null ) {
	if ( $post instanceof WP_Post ) {
		return $post;
	}
	$id = (int) $post;
	return $GLOBALS['wpdocs_test_posts'][ $id ] ?? null;
}

function get_the_title( $post ): string {
	$p = get_post( $post );
	return $p instanceof WP_Post ? $p->post_title : '';
}

function get_permalink( $post ): string {
	$p = get_post( $post );
	if ( ! $p instanceof WP_Post ) {
		return '';
	}
	$slug = '' !== $p->post_name ? $p->post_name : 'post-' . $p->ID;
	return 'https://example.test/docs/' . $slug . '/';
}

function get_the_excerpt( $post ): string {
	$p = get_post( $post );
	return $p instanceof WP_Post ? $p->post_excerpt : '';
}

function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
	$meta = $GLOBALS['wpdocs_test_post_meta'][ $post_id ] ?? array();
	if ( '' === $key ) {
		return $meta;
	}
	if ( ! array_key_exists( $key, $meta ) ) {
		return $single ? '' : array();
	}
	return $single ? $meta[ $key ] : array( $meta[ $key ] );
}

function post_type_exists( string $pt ): bool {
	return in_array( $pt, $GLOBALS['wpdocs_test_post_types'], true );
}

function wp_count_posts( string $type = 'post' ): object {
	$publish = 0;
	foreach ( $GLOBALS['wpdocs_test_posts'] as $p ) {
		if ( $p->post_type === $type && 'publish' === $p->post_status ) {
			++$publish;
		}
	}
	return (object) array( 'publish' => $publish );
}

/**
 * Minimal get_posts() stub supporting post_type, post_status, posts_per_page, meta_query (OR != '').
 *
 * @param array<string,mixed> $args
 * @return WP_Post[]
 */
function get_posts( array $args = array() ): array {
	$types  = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
	$status = (string) ( $args['post_status'] ?? 'publish' );
	$limit  = (int) ( $args['posts_per_page'] ?? 50 );
	$mq     = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
	$out    = array();

	foreach ( $GLOBALS['wpdocs_test_posts'] as $post ) {
		if ( ! in_array( 'any', $types, true ) && ! in_array( $post->post_type, $types, true ) ) {
			continue;
		}
		if ( $post->post_status !== $status ) {
			continue;
		}
		if ( ! empty( $mq ) ) {
			$matched = false;
			foreach ( $mq as $clause ) {
				if ( ! is_array( $clause ) || empty( $clause['key'] ) ) {
					continue;
				}
				$val = get_post_meta( $post->ID, (string) $clause['key'], true );
				if ( is_string( $val ) && '' !== $val ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				continue;
			}
		}
		$out[] = $post;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * Minimal wpdb stub for Bind\Repository::list / find_by_post / count.
 */
final class WpdocsTestWpdb {
	public string $prefix = 'wp_';

	public function prepare( string $query, ...$args ): string {
		$out = $query;
		foreach ( $args as $arg ) {
			if ( false !== strpos( $out, '%d' ) ) {
				$out = (string) preg_replace( '/%d/', (string) (int) $arg, $out, 1 );
			} elseif ( false !== strpos( $out, '%s' ) ) {
				$out = (string) preg_replace( '/%s/', "'" . (string) $arg . "'", $out, 1 );
			}
		}
		return $out;
	}

	/** @return object|null */
	public function get_row( string $sql ) {
		if ( preg_match( '/WHERE wp_post_id = (\d+)/', $sql, $m ) ) {
			$pid = (int) $m[1];
			foreach ( $GLOBALS['wpdocs_test_bindings'] as $row ) {
				if ( (int) $row->wp_post_id === $pid ) {
					return $row;
				}
			}
		}
		return null;
	}

	/** @return object[] */
	public function get_results( string $sql ): array {
		$rows = array_values( $GLOBALS['wpdocs_test_bindings'] );
		if ( preg_match( '/LIMIT (\d+)/', $sql, $m ) ) {
			$rows = array_slice( $rows, 0, (int) $m[1] );
		}
		return $rows;
	}

	public function get_var( string $sql ): int {
		if ( preg_match( "/WHERE status = '([^']+)'/", $sql, $m ) ) {
			$status = $m[1];
			$n      = 0;
			foreach ( $GLOBALS['wpdocs_test_bindings'] as $row ) {
				if ( (string) ( $row->status ?? '' ) === $status ) {
					++$n;
				}
			}
			return $n;
		}
		return count( $GLOBALS['wpdocs_test_bindings'] );
	}
}

$GLOBALS['wpdb'] = new WpdocsTestWpdb();

require_once __DIR__ . '/../src/Compat/Legacy.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Bind/Repository.php';
require_once __DIR__ . '/../src/Admin/Menu.php';
require_once __DIR__ . '/../src/Integration/WPMind.php';

use Wpdocs\Integration\WPMind;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_wm_expect( string $label, $got, $want ): void {
	global $fails;
	if ( $got !== $want ) {
		fwrite( STDERR, "FAIL {$label}: got " . var_export( $got, true ) . ' want ' . var_export( $want, true ) . "\n" );
		++$fails;
		return;
	}
	echo "ok {$label}\n";
}

function wpdocs_wm_expect_true( string $label, bool $cond ): void {
	wpdocs_wm_expect( $label, $cond, true );
}

// =========================================================================
// 1. Hook registration & zero hard dependency on WPMind
// =========================================================================
WPMind::reset();
WPMind::register();
WPMind::register(); // Idempotent second call.

wpdocs_wm_expect( 'registers wpmind_llms_txt_sections once', count( $GLOBALS['wpdocs_test_filters']['wpmind_llms_txt_sections'] ?? array() ), 1 );
wpdocs_wm_expect( 'registers wpmind_raw_markdown_for_post once', count( $GLOBALS['wpdocs_test_filters']['wpmind_raw_markdown_for_post'] ?? array() ), 1 );
wpdocs_wm_expect( 'registers wpmind_ecosystem_integrations once', count( $GLOBALS['wpdocs_test_filters']['wpmind_ecosystem_integrations'] ?? array() ), 1 );
wpdocs_wm_expect( 'llms filter priority', $GLOBALS['wpdocs_test_filters']['wpmind_llms_txt_sections'][0]['priority'], 20 );
wpdocs_wm_expect( 'llms filter accepted_args', $GLOBALS['wpdocs_test_filters']['wpmind_llms_txt_sections'][0]['accepted_args'], 2 );

// =========================================================================
// 2. wpmind_llms_txt_sections: empty state returns sections unchanged
// =========================================================================
$GLOBALS['wpdocs_test_options']['wpdocs_settings'] = array(
	'content_mode' => 'git',
	'post_types'   => array( 'post', 'docs' ),
);
$GLOBALS['wpdocs_test_post_types']                 = array( 'post', 'docs' );

$existing_sections = array( 'recent_posts' => "## Recent Posts\n\n- [Hello](https://example.test/hello)\n" );
$empty_out         = WPMind::filter_llms_txt_sections( $existing_sections, 'default' );
wpdocs_wm_expect( 'empty docs leaves llms sections untouched', $empty_out, $existing_sections );

// =========================================================================
// 3. wpmind_llms_txt_sections: structured section, filtering draft & noai, cap
// =========================================================================
$GLOBALS['wpdocs_test_posts'] = array(
	10 => new WP_Post(
		array(
			'ID'           => 10,
			'post_title'   => '快速开始指南',
			'post_name'    => 'getting-started',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_excerpt' => '五分钟完成文派文档安装与站点绑定。',
		)
	),
	11 => new WP_Post(
		array(
			'ID'           => 11,
			'post_title'   => 'Draft Doc Should Skip',
			'post_name'    => 'draft-doc',
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_excerpt' => 'Hidden draft.',
		)
	),
	12 => new WP_Post(
		array(
			'ID'           => 12,
			'post_title'   => 'NoAI Doc Should Skip',
			'post_name'    => 'noai-doc',
			'post_status'  => 'publish',
			'post_type'    => 'docs',
			'post_excerpt' => 'Opted out of AI.',
		)
	),
	13 => new WP_Post(
		array(
			'ID'           => 13,
			'post_title'   => 'API 参考手册',
			'post_name'    => 'api-reference',
			'post_status'  => 'publish',
			'post_type'    => 'docs',
			'post_content' => '<p>提供 <strong>REST</strong> 端点与 Webhook 说明。</p>',
		)
	),
	14 => new WP_Post(
		array(
			'ID'           => 14,
			'post_title'   => 'Git 同步工作流',
			'post_name'    => 'git-workflow',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => '<!-- wp:paragraph --><p>从 Forgejo 自动同步 Markdown 文档。</p><!-- /wp:paragraph -->',
		)
	),
	15 => new WP_Post(
		array(
			'ID'           => 15,
			'post_title'   => '普通博客文章',
			'post_name'    => 'normal-blog-post',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => '<p>不是文档，不应进入文档区。</p>',
		)
	),
);

$GLOBALS['wpdocs_test_bindings'] = array(
	(object) array(
		'id'           => 1,
		'wp_post_id'   => 10,
		'docs_page_id' => 1001,
		'status'       => 'active',
	),
	(object) array(
		'id'           => 2,
		'wp_post_id'   => 11,
		'docs_page_id' => 1002,
		'status'       => 'active',
	),
);

$GLOBALS['wpdocs_test_post_meta'] = array(
	12 => array(
		'_wpmind_ai_indexing' => 'noai',
	),
	14 => array(
		'github_url' => 'https://feicode.com/WenPai-org/docs/src/branch/main/git-workflow.md',
	),
);

$out = WPMind::filter_llms_txt_sections( $existing_sections, 'full' );
wpdocs_wm_expect_true( 'keeps existing llms section', isset( $out['recent_posts'] ) );
wpdocs_wm_expect_true( 'adds wpdocs structured section', isset( $out['wpdocs'] ) && is_array( $out['wpdocs'] ) );
wpdocs_wm_expect( 'wpdocs section title', $out['wpdocs']['title'], 'Documentation' );
wpdocs_wm_expect( 'wpdocs section item count (excludes draft, noai, non-doc)', count( $out['wpdocs']['items'] ), 3 );

$titles = array_column( $out['wpdocs']['items'], 'title' );
wpdocs_wm_expect( 'exported doc titles order', $titles, array( '快速开始指南', 'API 参考手册', 'Git 同步工作流' ) );
wpdocs_wm_expect( 'item 0 url', $out['wpdocs']['items'][0]['url'], 'https://example.test/docs/getting-started/' );
wpdocs_wm_expect( 'item 0 desc', $out['wpdocs']['items'][0]['desc'], '五分钟完成文派文档安装与站点绑定。' );
wpdocs_wm_expect( 'item 1 strips HTML tags in desc', $out['wpdocs']['items'][1]['desc'], '提供 REST 端点与 Webhook 说明。' );
wpdocs_wm_expect( 'item 2 strips block comments in desc', $out['wpdocs']['items'][2]['desc'], '从 Forgejo 自动同步 Markdown 文档。' );

// Cap test: wpdocs_wpmind_llms_max_items = 2.
$GLOBALS['wpdocs_test_filters']['wpdocs_wpmind_llms_max_items'] = array(
	array(
		'callback'      => static function (): int {
			return 2;
		},
		'priority'      => 10,
		'accepted_args' => 2,
	),
);
$capped = WPMind::filter_llms_txt_sections( array(), 'default' );
wpdocs_wm_expect( 'llms section obeys max items cap', count( $capped['wpdocs']['items'] ), 2 );
unset( $GLOBALS['wpdocs_test_filters']['wpdocs_wpmind_llms_max_items'] );

// Long description truncation test.
$long_item = WPMind::format_llms_item( 'Long Desc', 'https://example.test/docs/long/', str_repeat( '文派文档测试摘要', 30 ) );
wpdocs_wm_expect_true( 'long desc truncated with ellipsis', is_array( $long_item ) && str_ends_with( $long_item['desc'], '...' ) );
wpdocs_wm_expect_true( 'long desc capped at <= 160 chars', is_array( $long_item ) && mb_strlen( $long_item['desc'], 'UTF-8' ) <= 160 );

// =========================================================================
// 4. wpmind_raw_markdown_for_post: raw meta, local Git, HTML fallback, non-doc passthrough
// =========================================================================

// 4a. Non-doc post passthrough (untouched).
$non_doc = $GLOBALS['wpdocs_test_posts'][15];
wpdocs_wm_expect( 'non-doc post returns null untouched', WPMind::filter_raw_markdown_for_post( null, $non_doc ), null );

// 4b. Pre-existing non-empty raw Markdown from earlier filter is preserved.
$doc_with_meta = new WP_Post(
	array(
		'ID'           => 20,
		'post_title'   => 'Raw Meta Doc',
		'post_type'    => 'post',
		'post_content' => '<p>Rendered HTML</p>',
	)
);
$GLOBALS['wpdocs_test_post_meta'][20] = array(
	'_wpdocs_raw_markdown' => "---\ntitle: Raw Meta Doc\n---\n\n## 原生 Markdown\n\n直接返回源文档。",
);
wpdocs_wm_expect(
	'existing non-empty raw preserved',
	WPMind::filter_raw_markdown_for_post( 'Already Provided', $doc_with_meta ),
	'Already Provided'
);

// 4c. Stored raw Markdown meta strips YAML frontmatter and returns clean Markdown.
$meta_md = WPMind::filter_raw_markdown_for_post( null, $doc_with_meta );
wpdocs_wm_expect( 'raw markdown meta returned with frontmatter stripped', $meta_md, "## 原生 Markdown\n\n直接返回源文档。" );

// 4d. Local Git Markdown file inside configured git_base_directory vs path traversal block.
$tmp_git_dir = sys_get_temp_dir() . '/wpdocs-wpmind-test-' . uniqid();
mkdir( $tmp_git_dir, 0755, true );
$safe_md_file = $tmp_git_dir . '/guide.md';
file_put_contents( $safe_md_file, "---\ntitle: Local Guide\n---\n\n### 本地 Git 源\n\n- 步骤 1\n- 步骤 2\n" );
$outside_file = sys_get_temp_dir() . '/wpdocs-outside-' . uniqid() . '.md';
file_put_contents( $outside_file, 'SECRET OUTSIDE CONTENT' );

$GLOBALS['wpdocs_test_options']['wpdocs_settings']['git_base_directory'] = $tmp_git_dir;

$git_local_post = new WP_Post(
	array(
		'ID'           => 21,
		'post_title'   => 'Local Git Doc',
		'post_type'    => 'post',
		'post_content' => '<p>Fallback HTML</p>',
	)
);
$GLOBALS['wpdocs_test_post_meta'][21] = array(
	'_source_url' => $safe_md_file,
);
wpdocs_wm_expect(
	'local git md read inside base_directory',
	WPMind::filter_raw_markdown_for_post( null, $git_local_post ),
	"### 本地 Git 源\n\n- 步骤 1\n- 步骤 2"
);

$traversal_post = new WP_Post(
	array(
		'ID'           => 22,
		'post_title'   => 'Traversal Attempt',
		'post_type'    => 'post',
		'post_content' => '<p>Safe fallback content</p>',
	)
);
$GLOBALS['wpdocs_test_post_meta'][22] = array(
	'_source_url' => $outside_file,
);
$traversal_md = WPMind::filter_raw_markdown_for_post( null, $traversal_post );
wpdocs_wm_expect( 'outside _source_url blocked and falls back to HTML conversion', $traversal_md, 'Safe fallback content' );

@unlink( $safe_md_file );
@unlink( $outside_file );
@rmdir( $tmp_git_dir );

// 4e. HTML → Markdown conversion for Git-imported & dedicated CPT doc posts.
$html_doc = new WP_Post(
	array(
		'ID'           => 23,
		'post_title'   => 'HTML Doc',
		'post_type'    => 'docs',
		'post_content' => '<!-- wp:heading --><h2>安装步骤</h2><!-- /wp:heading -->'
			. '<p>使用 <code>Array&lt;string&gt;</code> 访问 <a href="https://wpcy.com/docs">文档中心</a>。</p>'
			. '<pre><code class="language-php">echo "hi";</code></pre>'
			. '<blockquote><p>第一行</p><p>第二行</p></blockquote>'
			. '<ol><li>下载插件</li><li>启用设置</li></ol>'
			. '<p>导入自 CocoBook</p>',
	)
);
$converted = (string) WPMind::filter_raw_markdown_for_post( null, $html_doc );
wpdocs_wm_expect_true( 'converted has H2 heading', str_contains( $converted, '## 安装步骤' ) );
wpdocs_wm_expect_true( 'converted preserves inline code generics', str_contains( $converted, '`Array<string>`' ) );
wpdocs_wm_expect_true( 'converted preserves markdown link', str_contains( $converted, '[文档中心](https://wpcy.com/docs)' ) );
wpdocs_wm_expect_true( 'converted preserves fenced php code', str_contains( $converted, "```php\necho \"hi\";\n```" ) );
wpdocs_wm_expect_true( 'converted preserves multiline blockquote', str_contains( $converted, "> 第一行\n> 第二行" ) );
wpdocs_wm_expect_true( 'converted preserves ordered list', str_contains( $converted, "1. 下载插件\n2. 启用设置" ) );
wpdocs_wm_expect_true( 'converted drops BookStack import footnote', ! str_contains( $converted, '导入自' ) );

// =========================================================================
// 5. wpmind_ecosystem_integrations: register new & enrich existing in place
// =========================================================================

// 5a. Append when not yet present.
$eco_new = WPMind::filter_ecosystem_integrations(
	array(
		array(
			'slug'   => 'wpslug',
			'name'   => '文派别名（WPSlug）',
			'active' => true,
		),
	),
	array(
		'wpdocs' => array(
			'requests' => 18,
			'tokens'   => 4200,
		),
	)
);
wpdocs_wm_expect( 'ecosystem appends wpdocs without dropping others', count( $eco_new ), 2 );
wpdocs_wm_expect( 'ecosystem wpdocs slug', $eco_new[1]['slug'], 'wpdocs' );
wpdocs_wm_expect( 'ecosystem wpdocs active', $eco_new[1]['active'], true );
wpdocs_wm_expect( 'ecosystem wpdocs feature', $eco_new[1]['feature'], 'GEO / 文档库导出' );
wpdocs_wm_expect( 'ecosystem wpdocs version', $eco_new[1]['version'], WPDOCS_VERSION );
wpdocs_wm_expect( 'ecosystem wpdocs settings_url', $eco_new[1]['settings_url'], 'https://example.test/wp-admin/options-general.php?page=wpdocs' );
wpdocs_wm_expect( 'ecosystem wpdocs requests from by_caller', $eco_new[1]['requests'], 18 );
wpdocs_wm_expect( 'ecosystem wpdocs tokens from by_caller', $eco_new[1]['tokens'], 4200 );
wpdocs_wm_expect_true( 'ecosystem wpdocs live doc_count > 0', (int) $eco_new[1]['doc_count'] >= 2 );
wpdocs_wm_expect_true( 'ecosystem wpdocs desc mentions doc count', str_contains( (string) $eco_new[1]['desc'], (string) $eco_new[1]['doc_count'] ) );

// 5b. Enrich in place when SuiteIntegration already created a wpdocs entry (no duplicates!).
$eco_enriched = WPMind::filter_ecosystem_integrations(
	array(
		array(
			'slug'         => 'wpdocs',
			'name'         => '文派文档（WPDocs）',
			'desc'         => '旧描述',
			'feature'      => 'GEO / 文档 AI',
			'icon'         => 'doc',
			'version'      => '0.3.0',
			'settings_url' => 'https://example.test/old-url',
			'requests'     => 7,
			'tokens'       => 999,
			'active'       => true,
		),
	),
	array()
);
wpdocs_wm_expect( 'ecosystem enriches existing wpdocs without duplicating', count( $eco_enriched ), 1 );
wpdocs_wm_expect( 'ecosystem enriched feature updated', $eco_enriched[0]['feature'], 'GEO / 文档库导出' );
wpdocs_wm_expect( 'ecosystem enriched preserves requests', $eco_enriched[0]['requests'], 7 );
wpdocs_wm_expect( 'ecosystem enriched preserves tokens', $eco_enriched[0]['tokens'], 999 );
wpdocs_wm_expect( 'ecosystem enriched settings_url updated', $eco_enriched[0]['settings_url'], 'https://example.test/wp-admin/options-general.php?page=wpdocs' );

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}\n" );
	exit( 1 );
}
echo "ALL PASS\n";
exit( 0 );

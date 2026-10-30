# Async AI Review Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Decouple the AI review from the synchronous PHP check loop so the 56 static checks render instantly while the AI analysis runs asynchronously via AJAX.

**Architecture:** Remove `AI_Review_Check` from the `$themechecks` array. Instead, after the static checks render, inject a JS-driven container that fires a `wp_ajax_tc_run_ai_review` request. A new `AI_Ajax_Runner` PHP class handles the AJAX request: re-reads theme files, calls Cloud_Fetcher + LLM_Factory, and returns formatted HTML with highlighted snippet triggers. The JS replaces the loading spinner with the results.

**Tech Stack:** WordPress AJAX API, vanilla JavaScript (no jQuery dependency beyond what WP ships), existing PHP classes (Cloud_Fetcher, LLM_Factory, AI_Settings).

---

## File Structure

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `ai-review/ajax/class-ai-ajax-runner.php` | AJAX handler: nonce/cap check, theme file gathering, cloud fetch, LLM call, HTML formatting |
| Create | `ai-review/assets/ai-review.js` | Front-end: append container, show spinner, fire AJAX, render results |
| Create | `ai-review/assets/ai-review.css` | Styles for the AI review container, spinner, debug log, snippet triggers |
| Modify | `ai-review/class-ai-review-loader.php` | Load new AJAX handler, remove `AI_Review_Check` from sync loop, enqueue JS/CSS |
| Modify | `main.php` | Output the AI review container placeholder + pass localized data to JS |
| Keep | `ai-review/check/class-ai-review-check.php` | Kept for reference but no longer registered in `$themechecks` |

---

### Task 1: Create the AJAX Handler (`class-ai-ajax-runner.php`)

**Files:**
- Create: `ai-review/ajax/class-ai-ajax-runner.php`

This is the core backend piece. It registers the `wp_ajax_tc_run_ai_review` action, verifies security, gathers theme files, calls the LLM, and returns a JSON response with formatted HTML.

- [ ] **Step 1: Create the directory and file with class skeleton**

Create `ai-review/ajax/class-ai-ajax-runner.php`:

```php
<?php
/**
 * AI Review — Async AJAX Runner
 *
 * Handles the background AJAX request that runs the AI review
 * independently from the synchronous static checks.
 *
 * Registered action: wp_ajax_tc_run_ai_review
 *
 * @package Theme_Check
 * @subpackage AI_Review
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Ajax_Runner
 */
class AI_Ajax_Runner {

	/**
	 * Nonce action for the AI review AJAX request.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'tc_ai_review_nonce';

	/**
	 * Maximum number of files sent to the LLM per run.
	 *
	 * @var int
	 */
	const MAX_FILES = 20;

	/**
	 * Maximum total character length of the concatenated theme code.
	 *
	 * @var int
	 */
	const MAX_CHARS = 120000;

	/**
	 * Hook into WordPress AJAX.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_tc_run_ai_review', array( $this, 'handle' ) );
	}

	/**
	 * AJAX handler entry point.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function handle() {
		// --- Security checks ---
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'html' => '<p>' . esc_html__( 'Unauthorized. You need manage_options capability.', 'theme-check' ) . '</p>' )
			);
		}

		$theme_slug = isset( $_POST['theme_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) ) : '';

		if ( '' === $theme_slug ) {
			wp_send_json_error(
				array( 'html' => '<p>' . esc_html__( 'No theme specified.', 'theme-check' ) . '</p>' )
			);
		}

		// --- Validate theme exists ---
		$theme = wp_get_theme( $theme_slug );

		if ( ! $theme->exists() ) {
			wp_send_json_error(
				array(
					'html' => '<p>' . sprintf(
						/* translators: %s: theme slug */
						esc_html__( 'Theme "%s" not found.', 'theme-check' ),
						esc_html( $theme_slug )
					) . '</p>',
				)
			);
		}

		$debug      = AI_Settings::is_debug_enabled();
		$debug_data = array();
		$time_total = microtime( true );

		// --- Step 1: Create the LLM client ---
		$client = LLM_Factory::create();

		if ( is_wp_error( $client ) ) {
			wp_send_json_error(
				array( 'html' => '<p>' . esc_html( $client->get_error_message() ) . '</p>' )
			);
		}

		if ( $debug ) {
			$debug_data['Active provider'] = AI_Settings::get_active_provider();
		}

		// --- Step 2: Fetch cloud rules ---
		$fetcher        = new Cloud_Fetcher();
		$rules_yml      = $fetcher->get_rules_yml();
		$explanation_md  = $fetcher->get_explanation_md();

		if ( $debug ) {
			$debug_data['Cloud rules.yml'] = is_wp_error( $rules_yml )
				? 'FAILED: ' . $rules_yml->get_error_message()
				: 'OK (' . strlen( $rules_yml ) . ' bytes)';
			$debug_data['Cloud explanation.md'] = is_wp_error( $explanation_md )
				? 'FAILED: ' . $explanation_md->get_error_message()
				: 'OK (' . strlen( $explanation_md ) . ' bytes)';
		}

		if ( is_wp_error( $rules_yml ) ) {
			wp_send_json_error(
				array(
					'html'  => '<p>' . esc_html( $rules_yml->get_error_message() ) . '</p>',
					'debug' => $debug ? $debug_data : null,
				)
			);
		}

		if ( is_wp_error( $explanation_md ) ) {
			wp_send_json_error(
				array(
					'html'  => '<p>' . esc_html( $explanation_md->get_error_message() ) . '</p>',
					'debug' => $debug ? $debug_data : null,
				)
			);
		}

		// --- Step 3: Gather and concatenate theme files ---
		$theme_code = $this->gather_theme_code( $theme );

		if ( $debug ) {
			$debug_data['Concatenated payload'] = mb_strlen( $theme_code ) . ' chars';
		}

		if ( '' === $theme_code ) {
			wp_send_json_error(
				array(
					'html'  => '<p>' . esc_html__( 'No PHP or CSS files found in the theme.', 'theme-check' ) . '</p>',
					'debug' => $debug ? $debug_data : null,
				)
			);
		}

		// --- Step 4: Call the LLM ---
		$time_api  = microtime( true );
		$findings  = $client->analyze_theme( $theme_code, $rules_yml, $explanation_md );
		$time_api  = round( microtime( true ) - $time_api, 2 );

		if ( $debug ) {
			$debug_data['API response time'] = $time_api . 's';

			if ( is_wp_error( $findings ) ) {
				$debug_data['API error'] = $findings->get_error_message();
			} else {
				$debug_data['Findings returned'] = count( $findings );
			}
		}

		if ( is_wp_error( $findings ) ) {
			$error_msg = $findings->get_error_message();
			wp_send_json_error(
				array(
					'html'  => '<p>' . sprintf(
						/* translators: %s: error message */
						esc_html__( 'AI Review encountered an error: %s', 'theme-check' ),
						esc_html( $error_msg )
					) . '</p>',
					'debug' => $debug ? $debug_data : null,
				)
			);
		}

		// --- Step 5: Format findings as HTML ---
		$html = $this->format_findings_html( $findings );

		$debug_data['Total execution time'] = round( microtime( true ) - $time_total, 2 ) . 's';

		wp_send_json_success(
			array(
				'html'    => $html,
				'count'   => count( $findings ),
				'debug'   => $debug ? $debug_data : null,
			)
		);
	}

	/**
	 * Read theme files and concatenate into an LLM-friendly string.
	 *
	 * Mirrors the logic from the former AI_Review_Check::build_theme_code()
	 * but reads files directly from the WP_Theme object.
	 *
	 * @param WP_Theme $theme The theme to read files from.
	 * @return string Concatenated source code with file-boundary markers.
	 */
	private function gather_theme_code( $theme ) {
		$files = $theme->get_files(
			null,
			-1,
			true
		);
		unset( $files[0] );

		$all_files = array();

		foreach ( $files as $filename ) {
			if ( strpos( $filename, 'tgm-plugin-activation' ) !== false || strpos( $filename, 'class-merlin' ) !== false ) {
				continue;
			}

			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

			if ( 'php' === $ext || 'css' === $ext ) {
				$all_files[ $filename ] = file_get_contents( $filename );
			}
		}

		ksort( $all_files );

		$output      = '';
		$file_count  = 0;
		$char_budget = self::MAX_CHARS;

		foreach ( $all_files as $path => $content ) {
			if ( $file_count >= self::MAX_FILES ) {
				break;
			}

			// Get relative filename.
			$relative = $path;
			if ( preg_match( '!/themes/[^/]+/(.*)$!i', $path, $m ) ) {
				$relative = $m[1];
			}

			$header  = "\n--- FILE: " . $relative . " ---\n";
			$segment = $header . $content . "\n";

			if ( mb_strlen( $segment ) > $char_budget ) {
				$available = $char_budget - mb_strlen( $header ) - 30;

				if ( $available <= 0 ) {
					break;
				}

				$segment = $header . mb_substr( $content, 0, $available ) . "\n[... truncated ...]\n";
			}

			$output      .= $segment;
			$char_budget -= mb_strlen( $segment );
			$file_count++;

			if ( $char_budget <= 0 ) {
				break;
			}
		}

		return $output;
	}

	/**
	 * Format LLM findings into the HTML displayed in the AI review container.
	 *
	 * Each finding renders as a list item with severity badge, message,
	 * file reference, and a prominently highlighted snippet trigger.
	 *
	 * @param array $findings Validated findings from the LLM client.
	 * @return string HTML string.
	 */
	private function format_findings_html( $findings ) {
		if ( empty( $findings ) ) {
			return '<p class="tc-ai-no-findings">'
				. esc_html__( 'AI Review complete. No issues found.', 'theme-check' )
				. '</p>';
		}

		$html = '<ul class="tc-ai-findings">';

		foreach ( $findings as $finding ) {
			$severity = isset( $finding['severity'] ) ? $finding['severity'] : 'WARNING';
			$file     = isset( $finding['file'] ) ? $finding['file'] : '';
			$trigger  = isset( $finding['trigger'] ) ? $finding['trigger'] : '';
			$message  = isset( $finding['message'] ) ? $finding['message'] : '';

			$css_class = ( 'REQUIRED' === $severity ) ? 'tc-required' : 'tc-warning';

			$html .= '<li class="tc-ai-finding">';
			$html .= '<span class="tc-lead ' . esc_attr( $css_class ) . '">[AI] ' . esc_html( $severity ) . '</span>: ';
			$html .= esc_html( $message );

			if ( '' !== $file ) {
				$html .= ' in <strong>' . esc_html( $file ) . '</strong>';
			}

			if ( '' !== $trigger ) {
				$html .= ' <code class="tc-snippet-trigger">' . esc_html( $trigger ) . '</code>';
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add ai-review/ajax/class-ai-ajax-runner.php
git commit -m "feat: add AI_Ajax_Runner class for async AI review via AJAX"
```

---

### Task 2: Create the JavaScript file (`ai-review.js`)

**Files:**
- Create: `ai-review/assets/ai-review.js`

The JS detects a theme check submission, appends the AI container, fires the AJAX request, and renders results.

- [ ] **Step 1: Create the directory and JS file**

Create `ai-review/assets/ai-review.js`:

```javascript
/**
 * AI Review — Async Front-End Controller
 *
 * Fires after the static theme checks render. Appends the "AI Review Agent"
 * container, shows a loading spinner, polls the AJAX endpoint, and injects
 * the formatted results when complete.
 *
 * Depends on `tcAiReview` global set via wp_localize_script():
 *   - ajaxUrl:    admin-ajax.php URL
 *   - nonce:      wp_create_nonce('tc_ai_review_nonce')
 *   - themeSlug:  the theme that was just checked
 *   - debug:      whether debug mode is enabled
 */
(function () {
	'use strict';

	// Bail if the localized config object is missing (no check was run).
	if ( typeof window.tcAiReview === 'undefined' ) {
		return;
	}

	var config = window.tcAiReview;

	// Find the anchor point — the container we output from main.php.
	var anchor = document.getElementById( 'tc-ai-review-anchor' );
	if ( ! anchor ) {
		return;
	}

	// --- Build the UI container ---
	var container = document.createElement( 'div' );
	container.id = 'tc-ai-review-container';
	container.innerHTML =
		'<h3 class="tc-ai-title">AI Review Agent</h3>' +
		'<div id="tc-ai-loading" class="tc-ai-loading">' +
			'<div class="tc-ai-spinner"></div>' +
			'<span class="tc-ai-loading-text">AI Agent is analyzing the codebase...</span>' +
		'</div>' +
		'<div id="tc-ai-debug-log" class="tc-ai-debug-log" style="display:none;">' +
			'<strong>Debug Log</strong>' +
			'<pre id="tc-ai-debug-pre"></pre>' +
		'</div>' +
		'<div id="tc-ai-results" style="display:none;"></div>';

	anchor.appendChild( container );

	var debugLog    = document.getElementById( 'tc-ai-debug-log' );
	var debugPre    = document.getElementById( 'tc-ai-debug-pre' );
	var loading     = document.getElementById( 'tc-ai-loading' );
	var resultsDiv  = document.getElementById( 'tc-ai-results' );

	if ( config.debug ) {
		debugLog.style.display = 'block';
		appendDebug( 'Starting AI review for theme: ' + config.themeSlug );
		appendDebug( 'Provider: ' + ( config.provider || 'default' ) );
	}

	// --- Fire the AJAX request ---
	var xhr = new XMLHttpRequest();
	xhr.open( 'POST', config.ajaxUrl, true );
	xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
	xhr.timeout = 120000; // 2 minutes — LLM calls can be slow.

	xhr.onload = function () {
		var response;

		try {
			response = JSON.parse( xhr.responseText );
		} catch ( e ) {
			showError( 'Failed to parse server response.' );
			return;
		}

		// Render debug data if present.
		if ( response.data && response.data.debug ) {
			renderDebug( response.data.debug );
		}

		// Hide spinner.
		loading.style.display = 'none';

		if ( response.success ) {
			resultsDiv.innerHTML = response.data.html;
			resultsDiv.style.display = 'block';

			if ( config.debug && response.data.count !== undefined ) {
				appendDebug( 'Completed. ' + response.data.count + ' finding(s) returned.' );
			}
		} else {
			var errorHtml = ( response.data && response.data.html )
				? response.data.html
				: '<p>An unknown error occurred.</p>';
			resultsDiv.innerHTML = '<div class="tc-ai-error">' + errorHtml + '</div>';
			resultsDiv.style.display = 'block';
		}
	};

	xhr.onerror = function () {
		showError( 'Network error. Could not reach the server.' );
	};

	xhr.ontimeout = function () {
		showError( 'Request timed out. The AI provider took too long to respond.' );
	};

	var params = 'action=tc_run_ai_review'
		+ '&nonce=' + encodeURIComponent( config.nonce )
		+ '&theme_slug=' + encodeURIComponent( config.themeSlug );

	if ( config.debug ) {
		appendDebug( 'Sending AJAX request...' );
	}

	xhr.send( params );

	// --- Helper functions ---

	function appendDebug( message ) {
		if ( ! debugPre ) {
			return;
		}
		var timestamp = new Date().toLocaleTimeString();
		debugPre.textContent += '[' + timestamp + '] ' + message + '\n';
	}

	function renderDebug( debugData ) {
		if ( ! config.debug || ! debugPre ) {
			return;
		}
		debugLog.style.display = 'block';
		for ( var key in debugData ) {
			if ( debugData.hasOwnProperty( key ) ) {
				appendDebug( key + ': ' + debugData[ key ] );
			}
		}
	}

	function showError( message ) {
		loading.style.display = 'none';
		resultsDiv.innerHTML = '<div class="tc-ai-error"><p>' + escapeHtml( message ) + '</p></div>';
		resultsDiv.style.display = 'block';

		if ( config.debug ) {
			appendDebug( 'ERROR: ' + message );
		}
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}
})();
```

- [ ] **Step 2: Commit**

```bash
git add ai-review/assets/ai-review.js
git commit -m "feat: add front-end JS for async AI review container and AJAX handling"
```

---

### Task 3: Create the CSS file (`ai-review.css`)

**Files:**
- Create: `ai-review/assets/ai-review.css`

- [ ] **Step 1: Create the CSS file**

Create `ai-review/assets/ai-review.css`:

```css
/**
 * AI Review — Async Container Styles
 */

/* Container */
#tc-ai-review-container {
	margin: 20px 0 10px;
	padding: 15px 20px;
	background: #f9f9fb;
	border: 1px solid #c3c4c7;
	border-left: 4px solid #2271b1;
	border-radius: 3px;
}

.tc-ai-title {
	margin: 0 0 12px 0;
	padding: 0;
	font-size: 16px;
	color: #1d2327;
}

/* Loading state */
.tc-ai-loading {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 0;
}

.tc-ai-spinner {
	width: 20px;
	height: 20px;
	border: 3px solid #c3c4c7;
	border-top-color: #2271b1;
	border-radius: 50%;
	animation: tc-ai-spin 0.8s linear infinite;
}

@keyframes tc-ai-spin {
	to { transform: rotate(360deg); }
}

.tc-ai-loading-text {
	font-style: italic;
	color: #50575e;
}

/* Debug log */
.tc-ai-debug-log {
	margin: 10px 0;
	padding: 10px;
	background: #f0f0f1;
	border: 1px solid #dcdcde;
	border-radius: 2px;
	font-size: 12px;
}

.tc-ai-debug-log pre {
	margin: 6px 0 0;
	padding: 8px;
	background: #1d2327;
	color: #72aee6;
	font-family: monospace;
	font-size: 11px;
	max-height: 250px;
	overflow-y: auto;
	white-space: pre-wrap;
	word-break: break-all;
	border-radius: 2px;
}

/* Results */
.tc-ai-findings {
	margin: 0;
	padding: 0;
	list-style: none;
}

.tc-ai-finding {
	padding: 8px 0;
	border-bottom: 1px solid #f0f0f1;
	line-height: 1.6;
}

.tc-ai-finding:last-child {
	border-bottom: none;
}

.tc-ai-no-findings {
	color: green;
	font-weight: 600;
}

/* Snippet trigger highlight */
.tc-snippet-trigger {
	display: inline-block;
	margin-left: 6px;
	padding: 2px 8px;
	background: #fef3cd;
	border: 1px solid #ffc107;
	border-radius: 3px;
	font-family: monospace;
	font-size: 13px;
	font-weight: 700;
	color: #856404;
	cursor: pointer;
	user-select: all;
}

.tc-snippet-trigger:hover {
	background: #fff3cd;
	border-color: #e0a800;
}

/* Error state */
.tc-ai-error {
	padding: 10px;
	background: #fcf0f1;
	border: 1px solid #d63638;
	border-radius: 2px;
	color: #d63638;
}
```

- [ ] **Step 2: Commit**

```bash
git add ai-review/assets/ai-review.css
git commit -m "feat: add CSS styles for async AI review container"
```

---

### Task 4: Modify the Loader (`class-ai-review-loader.php`)

**Files:**
- Modify: `ai-review/class-ai-review-loader.php`

The loader must: (1) load the new `AI_Ajax_Runner` class, (2) initialize it, (3) remove `AI_Review_Check` from the sync check loop, and (4) enqueue JS/CSS assets on the Theme Check page.

- [ ] **Step 1: Update load_dependencies() to include the AJAX handler**

In `ai-review/class-ai-review-loader.php`, add the AJAX handler require after the Layer 4 diagnostics block (after line 121):

```php
		// --- Layer 5: AJAX background runner ----------------------------
		require_once $this->base_path . 'ajax/class-ai-ajax-runner.php';
```

- [ ] **Step 2: Update init_admin_ui() to initialize the AJAX runner**

In `ai-review/class-ai-review-loader.php`, add to the end of `init_admin_ui()` method (before the closing `}` of `init_admin_ui()` at line 175):

```php

		/*
		 * AI_Ajax_Runner — registers the wp_ajax_tc_run_ai_review handler
		 * for asynchronous AI reviews.
		 */
		if ( class_exists( 'AI_Ajax_Runner' ) ) {
			$ajax_runner = new AI_Ajax_Runner();
			$ajax_runner->init();
		}
```

- [ ] **Step 3: Remove the synchronous check engine registration**

In `ai-review/class-ai-review-loader.php`, replace the `register_check_engine()` method and `append_ai_check()` method (lines 197-223) with an asset enqueue method:

Replace the entire `register_check_engine()` method (lines 181-223) with:

```php
	/**
	 * Enqueue the AI Review JS and CSS on the Theme Check admin page.
	 *
	 * Hooked into `admin_enqueue_scripts` and gated to only load on
	 * the Theme Check page (themes.php?page=themecheck).
	 *
	 * @return void
	 */
	private function register_assets() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Callback for admin_enqueue_scripts.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Only load on the Theme Check page (Appearance > Theme Check).
		if ( 'appearance_page_themecheck' !== $hook_suffix ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __FILE__ ) ) . 'ai-review/';

		wp_enqueue_style(
			'tc-ai-review',
			$plugin_url . 'assets/ai-review.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'tc-ai-review',
			$plugin_url . 'assets/ai-review.js',
			array(),
			'1.0.0',
			true // Load in footer.
		);
	}
```

- [ ] **Step 4: Update init() to call register_assets() instead of register_check_engine()**

In `ai-review/class-ai-review-loader.php`, replace line 70 (`$this->register_check_engine();`) with:

```php
		$this->register_assets();
```

- [ ] **Step 5: Commit**

```bash
git add ai-review/class-ai-review-loader.php
git commit -m "refactor: decouple AI review from sync loop, add AJAX runner and asset enqueue"
```

---

### Task 5: Modify `main.php` to output the AI anchor and localize JS data

**Files:**
- Modify: `main.php:123-158`

After the static check results render, we output a hidden anchor div and pass the theme slug + nonce to the JS via `wp_localize_script()`.

- [ ] **Step 1: Add the AI review anchor and localized script data**

In `main.php`, replace the block at lines 123-158 (from `echo '<div class="tc-box">';` through `echo '</ul></div>';`) with:

```php
	echo '<div class="tc-box">';
	echo '<ul class="tc-result">';
	echo wp_kses(
		$results,
		array(
			'li'     => array(),
			'span'   => array(
				'class' => array(),
			),
			'strong' => array(),
			'code'   => array(),
			'pre'    => array(),
			'a'      => array(
				'href' => array(),
			),
		)
	);
	echo '</ul></div>';

	// --- AI Review async anchor ---
	echo '<div id="tc-ai-review-anchor"></div>';

	// Pass config to the enqueued ai-review.js.
	if ( wp_script_is( 'tc-ai-review', 'enqueued' ) ) {
		wp_localize_script( 'tc-ai-review', 'tcAiReview', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'tc_ai_review_nonce' ),
			'themeSlug' => sanitize_text_field( $theme_slug ),
			'debug'     => AI_Settings::is_debug_enabled(),
			'provider'  => AI_Settings::get_active_provider(),
		) );
	}
```

This removes the old AI debug HTML block (the `if ( class_exists( 'AI_Review_Check' ) )` section) since debug output is now handled by the AJAX response.

- [ ] **Step 2: Commit**

```bash
git add main.php
git commit -m "feat: add AI review anchor point and localized AJAX config to main.php"
```

---

### Task 6: Integration testing and cleanup

**Files:**
- Verify: all modified and created files

- [ ] **Step 1: Verify file structure exists**

```bash
ls -la ai-review/ajax/class-ai-ajax-runner.php
ls -la ai-review/assets/ai-review.js
ls -la ai-review/assets/ai-review.css
```

Expected: all three files exist.

- [ ] **Step 2: Verify PHP syntax on all modified/created files**

```bash
php -l ai-review/ajax/class-ai-ajax-runner.php
php -l ai-review/class-ai-review-loader.php
php -l main.php
```

Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Verify the loader no longer references AI_Review_Check registration**

```bash
grep -n 'append_ai_check\|register_check_engine\|themecheck_checks_loaded' ai-review/class-ai-review-loader.php
```

Expected: no matches (all sync check registration code has been removed).

- [ ] **Step 4: Verify the AJAX action is properly registered**

```bash
grep -n 'wp_ajax_tc_run_ai_review' ai-review/ajax/class-ai-ajax-runner.php
```

Expected: one match inside the `init()` method.

- [ ] **Step 5: Verify nonce consistency between PHP and JS**

```bash
grep -rn 'tc_ai_review_nonce' ai-review/ main.php
```

Expected: matches in `class-ai-ajax-runner.php` (NONCE_ACTION constant), `main.php` (wp_create_nonce), and `ai-review.js` (nonce parameter name in AJAX call).

- [ ] **Step 6: Final commit with all files**

```bash
git add -A
git status
git commit -m "feat: complete async AI review implementation — decoupled from sync checks"
```

<?php
/**
 * Shopping cart. Chosen products are kept in a cookie (product, variant, quantity) and are
 * checked again on the server whenever the cart is shown or ordered. The order goes to the
 * shop owner by email, who places it with xTool and sends the customer a payment link.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LASERY_XTOOL_CART_COOKIE    = 'lasery_cart';
const LASERY_XTOOL_CART_MAX_LINES = 30;
const LASERY_XTOOL_CART_PAGE      = 'koszyk';

/** The cart page in the given language, or '' before it exists. */
function lasery_xtool_cart_url( $language ) {
	$page = get_page_by_path( LASERY_XTOOL_CART_PAGE );
	return $page && 'publish' === $page->post_status ? add_query_arg( 'lang', $language, get_permalink( $page ) ) : '';
}

function lasery_xtool_cart_cookie() {
	return isset( $_COOKIE[ LASERY_XTOOL_CART_COOKIE ] ) && is_string( $_COOKIE[ LASERY_XTOOL_CART_COOKIE ] ) ? wp_unslash( $_COOKIE[ LASERY_XTOOL_CART_COOKIE ] ) : '';
}

/** The cart as [product ID, variant ID ('' for products without source variants), quantity]. */
function lasery_xtool_cart_items() {
	$items = array();
	foreach ( array_slice( explode( '|', lasery_xtool_cart_cookie() ), 0, LASERY_XTOOL_CART_MAX_LINES ) as $part ) {
		if ( preg_match( '/^([1-9][0-9]{0,19}):([1-9][0-9]{0,19})?:([1-9][0-9]?|100)$/D', $part, $match ) ) {
			$item = array( (int) $match[1], $match[2], (int) $match[3] );
			$items[ lasery_xtool_cart_key( $item ) ] = $item;
		}
	}
	return array_values( $items );
}

/** The key of an item or a line: "product ID:variant ID". */
function lasery_xtool_cart_key( $item ) {
	return $item[0] . ':' . $item[1];
}

/** The cookie value of cart items. */
function lasery_xtool_cart_value( $items ) {
	return implode( '|', array_map( function ( $item ) { return implode( ':', $item ); }, array_slice( $items, 0, LASERY_XTOOL_CART_MAX_LINES ) ) );
}

function lasery_xtool_cart_save( $items ) {
	$value = lasery_xtool_cart_value( $items );
	$_COOKIE[ LASERY_XTOOL_CART_COOKIE ] = $value;
	// A session cookie, as a strictly necessary one should be. Not HttpOnly: the header shows
	// the number of items from it. It holds no personal data.
	setcookie( LASERY_XTOOL_CART_COOKIE, $value, array( 'expires' => '' === $value ? 1 : 0, 'path' => '/', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax' ) );
}

/** The cart items of lines, i.e. without products and variants that no longer exist. */
function lasery_xtool_cart_line_items( $lines ) {
	return array_map( function ( $line ) { return array( $line['product'], $line['variant'] ? $line['variant']['id'] : '', $line['quantity'] ); }, $lines );
}

/** Whole cents of a decimal price string such as "1234.5". */
function lasery_xtool_cents( $price ) {
	return (int) round( (float) $price * 100 );
}

/**
 * Current data of cart items: key, product, variant (null without source variants), quantity,
 * price [amount, currency] or null, and an error ('stale' or 'unavailable') when the line
 * cannot be ordered now. Products and variants that no longer exist are left out.
 */
function lasery_xtool_cart_lines( $items ) {
	$lines = array();
	foreach ( $items as list( $post_id, $variant_id, $quantity ) ) {
		if ( LASERY_XTOOL_PRODUCT_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			continue;
		}
		$line = array( 'key' => lasery_xtool_cart_key( array( $post_id, $variant_id ) ), 'product' => $post_id, 'variant' => null, 'quantity' => $quantity, 'amount' => null, 'error' => '' );
		if ( lasery_xtool_uses_source_variants( $post_id ) ) {
			foreach ( lasery_xtool_order_variants( $post_id ) as $variant ) {
				if ( is_array( $variant ) && isset( $variant['id'], $variant['title'] ) && $variant['id'] === $variant_id ) {
					$line['variant'] = $variant;
					break;
				}
			}
			if ( ! $line['variant'] ) {
				continue;
			}
			if ( ! lasery_xtool_source_is_fresh( $post_id ) ) {
				$line['error'] = 'stale';
			} elseif ( ! isset( $line['variant']['available'] ) || true !== $line['variant']['available'] ) {
				$line['error'] = 'unavailable';
			} else {
				$line['amount'] = lasery_xtool_variant_amount( $post_id, $line['variant'] );
			}
		} elseif ( '' === $variant_id ) {
			$line['amount'] = lasery_xtool_order_price_amount( $post_id );
		} else {
			continue;
		}
		$lines[] = $line;
	}
	return $lines;
}

/** The total of all lines per currency ("1 234,00 € + 500,00 zł"), or the "to be confirmed" text. */
function lasery_xtool_cart_total( $lines, $text, $language ) {
	$totals = array();
	foreach ( $lines as $line ) {
		if ( ! $line['amount'] ) {
			return $text['no_price'];
		}
		$totals[ $line['amount'][1] ] = ( $totals[ $line['amount'][1] ] ?? 0 ) + lasery_xtool_cents( $line['amount'][0] ) * $line['quantity'];
	}
	$parts = array();
	foreach ( $totals as $currency => $cents ) {
		$parts[] = lasery_xtool_format_cents( $cents, $currency, $language );
	}
	return $parts ? implode( ' + ', $parts ) : $text['no_price'];
}

/** Ties the order form to the products and prices the customer saw; quantities come with the order. */
function lasery_xtool_cart_snapshot( $lines ) {
	$data = array_map( function ( $line ) { return array( $line['key'], $line['amount'], $line['error'] ); }, $lines );
	return hash_hmac( 'sha256', wp_json_encode( $data ), wp_salt( 'nonce' ) );
}

function lasery_xtool_format_cents( $cents, $currency, $language ) {
	return lasery_xtool_format_price( number_format( $cents / 100, 2, '.', '' ), $currency, $language );
}

function lasery_xtool_variant_label( $variant ) {
	return $variant ? str_replace( ' / ', ' · ', sanitize_text_field( html_entity_decode( $variant['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) : '';
}

/** The price of $quantity pieces of a line, or the "to be confirmed" text. */
function lasery_xtool_cart_line_value( $line, $quantity, $text, $language ) {
	return $line['amount'] ? lasery_xtool_format_cents( lasery_xtool_cents( $line['amount'][0] ) * $quantity, $line['amount'][1], $language ) : $text['no_price'];
}

function lasery_xtool_cart_redirect( $language, $args ) {
	$url = lasery_xtool_cart_url( $language );
	wp_safe_redirect( add_query_arg( $args, '' !== $url ? $url : home_url( '/' ) ) );
	exit;
}

function lasery_xtool_posted_language() {
	$language = isset( $_POST['language'] ) && is_string( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
	return isset( lasery_xtool_description_languages()[ $language ] ) ? $language : 'pl';
}

/** Another site's form must not empty or replace a visitor's cart (Origin covers browsers without Sec-Fetch-Site). */
function lasery_xtool_cart_check_site( $language ) {
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ? wp_parse_url( $_SERVER['HTTP_ORIGIN'], PHP_URL_HOST ) : null;
	if ( ( isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) && 'cross-site' === $_SERVER['HTTP_SEC_FETCH_SITE'] ) || ( is_string( $origin ) && strtolower( $origin ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) ) {
		lasery_xtool_cart_redirect( $language, array() );
	}
}

// Adding or changing items has no effect beyond the visitor's own cart, so no nonce is
// needed, and product pages can stay in the page cache.
add_action( 'admin_post_lasery_xtool_cart_add', 'lasery_xtool_cart_add' );
add_action( 'admin_post_nopriv_lasery_xtool_cart_add', 'lasery_xtool_cart_add' );
function lasery_xtool_cart_add() {
	$language = lasery_xtool_posted_language();
	lasery_xtool_cart_check_site( $language );
	$post_id = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$variant = isset( $_POST['variant'] ) && is_string( $_POST['variant'] ) ? sanitize_text_field( wp_unslash( $_POST['variant'] ) ) : '';
	$quantity = (int) lasery_xtool_cart_quantity( $_POST['quantity'] ?? null, 1 );
	$lines = $post_id && $quantity && preg_match( '/^(?:[1-9][0-9]{0,19})?$/D', $variant ) ? lasery_xtool_cart_lines( array( array( $post_id, $variant, $quantity ) ) ) : array();
	if ( ! $lines || '' !== $lines[0]['error'] ) {
		$url = $post_id && LASERY_XTOOL_PRODUCT_TYPE === get_post_type( $post_id ) ? get_permalink( $post_id ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( array( 'lang' => $language, 'cart' => 'unavailable' ), $url ) . '#zamow' );
		exit;
	}
	$items = lasery_xtool_cart_items();
	foreach ( $items as $index => $item ) {
		if ( $item[0] === $post_id && $item[1] === $variant ) {
			$items[ $index ][2] = min( 100, $item[2] + $quantity );
			$quantity = 0;
		}
	}
	if ( $quantity ) {
		if ( count( $items ) >= LASERY_XTOOL_CART_MAX_LINES ) {
			lasery_xtool_cart_redirect( $language, array( 'cart' => 'full' ) );
		}
		$items[] = array( $post_id, $variant, $quantity );
	}
	lasery_xtool_cart_save( $items );
	lasery_xtool_cart_redirect( $language, array( 'cart' => 'added' ) );
}

/** A posted quantity from $min to 100 as a whole number (leading zeros allowed), or null. */
function lasery_xtool_cart_quantity( $value, $min ) {
	if ( ! is_string( $value ) || ! preg_match( '/^\s*[0-9]{1,3}\s*$/D', $value ) ) {
		return null;
	}
	$quantity = (int) $value;
	return $quantity >= $min && $quantity <= 100 ? $quantity : null;
}

add_action( 'admin_post_lasery_xtool_cart_update', 'lasery_xtool_cart_update' );
add_action( 'admin_post_nopriv_lasery_xtool_cart_update', 'lasery_xtool_cart_update' );
/**
 * The cart with the posted quantities (quantity[key], 0 removes) and removal (remove=key). Lines are
 * referred to by key, so changes apply to the right line whatever the cookie holds now.
 */
function lasery_xtool_cart_posted_items() {
	$quantities = isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : array();
	$remove = isset( $_POST['remove'] ) && is_string( $_POST['remove'] ) ? wp_unslash( $_POST['remove'] ) : '';
	$items = array();
	foreach ( lasery_xtool_cart_items() as $item ) {
		$key = lasery_xtool_cart_key( $item );
		$quantity = lasery_xtool_cart_quantity( $quantities[ $key ] ?? null, 0 ) ?? $item[2];
		if ( $key !== $remove && $quantity > 0 ) {
			$items[] = array( $item[0], $item[1], $quantity );
		}
	}
	return $items;
}

function lasery_xtool_cart_update() {
	$language = lasery_xtool_posted_language();
	lasery_xtool_cart_check_site( $language );
	lasery_xtool_cart_save( lasery_xtool_cart_posted_items() );
	lasery_xtool_cart_redirect( $language, array( 'cart' => isset( $_POST['remove'] ) ? 'removed' : 'updated' ) );
}

// The cart page shows one visitor's cart and the order form with a nonce.
add_action( 'template_redirect', 'lasery_xtool_cart_page_headers', 0 );
function lasery_xtool_cart_page_headers() {
	if ( ! is_page( LASERY_XTOOL_CART_PAGE ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'Shopping cart' );
	// Products and variants that no longer exist leave the cart, also from the header's count.
	$items = lasery_xtool_cart_line_items( lasery_xtool_cart_lines( lasery_xtool_cart_items() ) );
	if ( lasery_xtool_cart_cookie() !== lasery_xtool_cart_value( $items ) ) {
		lasery_xtool_cart_save( $items );
	}
}

add_filter( 'wp_robots', 'lasery_xtool_cart_robots' );
function lasery_xtool_cart_robots( $robots ) {
	return is_page( LASERY_XTOOL_CART_PAGE ) ? array( 'noindex' => true, 'follow' => true ) : $robots;
}

add_filter( 'wp_sitemaps_posts_query_args', 'lasery_xtool_cart_sitemap', 10, 2 );
function lasery_xtool_cart_sitemap( $args, $post_type ) {
	$page = 'page' === $post_type ? get_page_by_path( LASERY_XTOOL_CART_PAGE ) : null;
	if ( $page ) {
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), array( $page->ID ) );
	}
	return $args;
}

function lasery_xtool_render_cart() {
	$language = lasery_xtool_order_language();
	$text = lasery_xtool_order_texts( $language );
	$lines = lasery_xtool_cart_lines( lasery_xtool_cart_items() );
	$orderable = $lines && ! array_filter( array_column( $lines, 'error' ) );
	$result = isset( $_GET['cart'] ) && is_string( $_GET['cart'] ) ? sanitize_key( wp_unslash( $_GET['cart'] ) ) : '';
	// Confirmations of cart changes pass by themselves (a toast); errors stay on the page.
	$toast = in_array( $result, array( 'added', 'updated', 'removed' ), true ) ? $text[ 'cart_' . $result ] : '';
	$error = in_array( $result, array( 'full', 'failed', 'invalid', 'busy', 'changed' ), true ) ? $text[ 'cart_' . $result ] ?? $text[ $result ] : '';
	$sent = in_array( $result, array( 'sent', 'sent_no_receipt' ), true ) ? $text[ $result ] : '';
	$archive = get_post_type_archive_link( LASERY_XTOOL_PRODUCT_TYPE );
	$archive = $archive ? add_query_arg( 'lang', $language, $archive ) : home_url( '/' );
	?>
	<section class="order-form cart" aria-labelledby="cart-title">
		<header class="order-form-header">
			<h1 id="cart-title" class="page-title"><?php echo esc_html( '' !== $sent && ! $lines ? $text['sent_title'] : $text['cart_title'] ); ?></h1>
			<?php if ( $lines ) : ?><p><?php echo esc_html( $text['cart_intro'] ); ?></p><?php endif; ?>
		</header>
		<p class="cart-toast" role="status" data-cart-toast><?php echo esc_html( $toast ); ?></p>
		<?php if ( '' !== $error ) : ?>
			<p class="order-form-notice order-form-notice--error" role="alert" data-cart-error><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $sent ) : ?>
			<p class="order-form-notice order-form-notice--success"><?php echo esc_html( $sent ); ?></p>
		<?php endif; ?>
		<?php if ( ! $lines ) : ?>
			<?php if ( '' === $sent ) : ?><p class="empty-state"><?php echo esc_html( $text['cart_empty'] ); ?></p><?php endif; ?>
			<p class="cart-empty-actions"><a class="btn btn-primary" href="<?php echo esc_url( $archive ); ?>"><?php echo esc_html( $text['browse'] ); ?></a></p>
		</section>
			<?php
			return;
		endif;
		$total = lasery_xtool_cart_total( $lines, $text, $language );
		?>
		<?php // Removal buttons belong to their own form, so they are never the default button below. ?>
		<form id="cart-remove" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_cart_update">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
		</form>
		<?php // One form, so the order includes changed quantities even when the cart was not updated. ?>
		<form class="order-form-grid" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'lasery_xtool_order', admin_url( 'admin-post.php' ) ) ); ?>" data-cart-form<?php echo lasery_xtool_price_format_attributes( $language, $text ); ?>>
			<?php // The first submit button is the default for Enter: it sends the order (the script makes Enter in a quantity field update the cart). ?>
			<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true" <?php disabled( ! $orderable ); ?> data-cart-send><?php echo esc_html( $text['submit'] ); ?></button>
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<input type="hidden" name="snapshot" value="<?php echo esc_attr( lasery_xtool_cart_snapshot( $lines ) ); ?>">
			<?php wp_nonce_field( 'lasery_xtool_order' ); ?>
			<div class="order-form-trap" aria-hidden="true"><label for="order-website">Website</label><input id="order-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
			<div class="order-form-steps">
				<?php // With JavaScript, prices follow a typed quantity at once; then the server's new cart page replaces the parts marked data-cart-update-part and data-cart-total. ?>
				<div class="cart-items">
					<ul class="cart-lines">
						<?php foreach ( $lines as $index => $line ) : ?>
							<?php $title = lasery_xtool_order_product_title( $line['product'], $language ); ?>
							<?php $link = add_query_arg( 'lang', $language, get_permalink( $line['product'] ) ); ?>
							<li class="cart-line<?php echo $line['error'] ? ' cart-line--error' : ''; ?>" data-cart-line="<?php echo esc_attr( $line['key'] ); ?>"<?php echo lasery_xtool_amount_attributes( $line['amount'] ); ?>>
								<a class="cart-line-media" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $line['product'], 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?></a>
								<div class="cart-line-body">
									<p class="cart-line-title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a></p>
									<?php if ( $line['variant'] ) : ?><p class="cart-line-variant"><?php echo esc_html( lasery_xtool_variant_label( $line['variant'] ) ); ?></p><?php endif; ?>
									<?php if ( $line['error'] ) : ?>
										<p class="cart-line-error" data-cart-update-part><?php echo esc_html( $text[ 'cart_' . $line['error'] ] ); ?></p>
									<?php else : ?>
										<p class="cart-line-unit" data-cart-update-part><?php echo esc_html( $line['amount'] ? sprintf( $text['each'], lasery_xtool_cart_line_value( $line, 1, $text, $language ) ) : $text['no_price'] ); ?></p>
									<?php endif; ?>
								</div>
								<div class="cart-line-actions">
									<label class="visually-hidden" for="cart-quantity-<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $text['quantity'] . ': ' . $title ); ?></label>
									<?php lasery_xtool_quantity_field( 'cart-quantity-' . $index, 'quantity[' . $line['key'] . ']', $line['quantity'], $text ); ?>
									<button type="submit" form="cart-remove" class="cart-line-remove" name="remove" value="<?php echo esc_attr( $line['key'] ); ?>"><?php echo lasery_xtool_order_icon( 'trash' ); ?><span class="visually-hidden"><?php echo esc_html( $text['remove'] . ': ' . $title ); ?></span></button>
									<p class="cart-line-total" data-cart-update-part><?php echo esc_html( $line['error'] || ! $line['amount'] ? '' : lasery_xtool_cart_line_value( $line, $line['quantity'], $text, $language ) ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="cart-items-total" data-cart-total><span><?php echo esc_html( $text['total'] ); ?></span> <strong data-cart-sum><?php echo esc_html( $total ); ?></strong></p>
					<p class="cart-items-actions">
						<a class="cart-continue" href="<?php echo esc_url( $archive ); ?>"><?php echo lasery_xtool_order_icon( 'chevron-left' ); ?><?php echo esc_html( $text['continue'] ); ?></a>
						<button type="submit" class="btn btn-secondary btn-sm" formaction="<?php echo esc_url( add_query_arg( 'action', 'lasery_xtool_cart_update', admin_url( 'admin-post.php' ) ) ); ?>" formnovalidate data-cart-update><?php echo esc_html( $text['update'] ); ?></button>
					</p>
				</div>
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">1</span><?php echo esc_html( $text['contact'] ); ?></legend>
					<div class="field-grid">
						<p class="field"><label for="order-first-name"><?php echo esc_html( $text['first_name'] ); ?></label><input id="order-first-name" name="first_name" type="text" maxlength="150" autocomplete="given-name" required></p>
						<p class="field"><label for="order-last-name"><?php echo esc_html( $text['last_name'] ); ?></label><input id="order-last-name" name="last_name" type="text" maxlength="150" autocomplete="family-name" required></p>
						<p class="field"><label for="order-email"><?php echo esc_html( $text['email'] ); ?></label><input id="order-email" name="email" type="email" maxlength="254" autocomplete="email" required></p>
						<p class="field"><label for="order-phone"><?php echo esc_html( $text['phone'] ); ?></label><input id="order-phone" name="phone" type="tel" maxlength="50" autocomplete="tel"></p>
					</div>
				</fieldset>
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">2</span><?php echo esc_html( $text['address'] ); ?></legend>
					<div class="field-grid">
						<p class="field field-full"><label for="order-address1"><?php echo esc_html( $text['address1'] ); ?></label><input id="order-address1" name="address1" type="text" maxlength="250" autocomplete="address-line1" required></p>
						<p class="field field-full"><label for="order-address2"><?php echo esc_html( $text['address2'] ); ?></label><input id="order-address2" name="address2" type="text" maxlength="250" autocomplete="address-line2"></p>
						<p class="field"><label for="order-zip"><?php echo esc_html( $text['zip'] ); ?></label><input id="order-zip" name="zip" type="text" maxlength="30" autocomplete="postal-code" required></p>
						<p class="field"><label for="order-city"><?php echo esc_html( $text['city'] ); ?></label><input id="order-city" name="city" type="text" maxlength="150" autocomplete="address-level2" required></p>
						<p class="field field-full"><label for="order-country"><?php echo esc_html( $text['country'] ); ?></label>
							<select id="order-country" name="country" autocomplete="country" required>
								<option value=""><?php echo esc_html( $text['select_country'] ); ?></option>
								<?php foreach ( lasery_xtool_country_options( $language ) as $code => $name ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					</div>
				</fieldset>
				<fieldset class="order-step">
					<legend><span class="order-step-number" aria-hidden="true">3</span><?php echo esc_html( $text['extras'] ); ?></legend>
					<div class="field-grid">
						<p class="field field-full"><label for="order-invoice"><?php echo esc_html( $text['invoice'] ); ?></label><textarea id="order-invoice" name="invoice" maxlength="1000" rows="3"></textarea></p>
						<p class="field field-full"><label for="order-notes"><?php echo esc_html( $text['notes'] ); ?></label><textarea id="order-notes" name="notes" maxlength="1000" rows="3"></textarea></p>
					</div>
				</fieldset>
			</div>
			<aside class="order-summary" aria-labelledby="order-summary-title">
				<h2 id="order-summary-title"><?php echo esc_html( $text['summary'] ); ?></h2>
				<dl class="order-summary-list" data-cart-total>
					<dt><?php echo esc_html( $text['items'] ); ?></dt>
					<dd data-cart-pieces><?php echo esc_html( sprintf( $text['pieces'], array_sum( array_column( $lines, 'quantity' ) ) ) ); ?></dd>
					<dt class="order-summary-total"><?php echo esc_html( $text['total'] ); ?></dt>
					<dd class="order-summary-price" data-cart-sum><?php echo esc_html( $total ); ?></dd>
				</dl>
				<p class="order-summary-note"><?php echo esc_html( $text['price_note'] ); ?></p>
				<button type="submit" class="btn btn-primary btn-block" <?php disabled( ! $orderable ); ?> data-cart-send><?php echo esc_html( $text['submit'] ); ?></button>
				<p class="order-summary-note"><?php echo esc_html( $text['next_steps'] ); ?></p>
				<p class="order-summary-privacy"><?php echo esc_html( $text['privacy'] ); ?> <?php $privacy_url = get_privacy_policy_url(); if ( $privacy_url ) : ?><a href="<?php echo esc_url( add_query_arg( 'lang', $language, $privacy_url ) ); ?>"><?php echo esc_html( $text['privacy_link'] ); ?></a><?php endif; ?></p>
			</aside>
		</form>
	</section>
	<?php
}

add_action( 'admin_post_lasery_xtool_order', 'lasery_xtool_handle_order' );
add_action( 'admin_post_nopriv_lasery_xtool_order', 'lasery_xtool_handle_order' );
function lasery_xtool_handle_order() {
	$language = lasery_xtool_posted_language();
	lasery_xtool_cart_check_site( $language );
	if ( ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lasery_xtool_order' ) ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'invalid' ) );
	}
	if ( ! isset( $_POST['website'] ) || ! is_string( $_POST['website'] ) || '' !== trim( wp_unslash( $_POST['website'] ) ) ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'failed' ) );
	}
	// The order uses the quantities in the form, and the cart keeps them if the order is refused.
	$items = lasery_xtool_cart_posted_items();
	lasery_xtool_cart_save( $items );
	$fields = array();
	$limits = array( 'first_name' => 600, 'last_name' => 600, 'email' => 254, 'phone' => 200, 'address1' => 1000, 'address2' => 1000, 'city' => 600, 'zip' => 120, 'country' => 600, 'invoice' => 4000, 'notes' => 4000 );
	foreach ( $limits as $field => $limit ) {
		if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) || strlen( $_POST[ $field ] ) > $limit ) {
			lasery_xtool_cart_redirect( $language, array( 'cart' => 'invalid' ) );
		}
		$value = trim( wp_unslash( $_POST[ $field ] ) );
		$fields[ $field ] = in_array( $field, array( 'invoice', 'notes' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
	$fields['email'] = sanitize_email( $fields['email'] );
	$countries = lasery_xtool_shipping_countries();
	if ( '' === $fields['first_name'] || '' === $fields['last_name'] || '' === $fields['address1'] || '' === $fields['city'] || '' === $fields['zip'] || ! isset( $countries[ $fields['country'] ] ) || ! is_email( $fields['email'] ) ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'invalid' ) );
	}
	// The order covers exactly what the customer saw: no line may have changed or become unavailable.
	$lines = lasery_xtool_cart_lines( $items );
	$snapshot = isset( $_POST['snapshot'] ) && is_string( $_POST['snapshot'] ) ? wp_unslash( $_POST['snapshot'] ) : '';
	if ( ! $lines || array_filter( array_column( $lines, 'error' ) ) || ! hash_equals( lasery_xtool_cart_snapshot( $lines ), $snapshot ) ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'changed' ) );
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	$reservation = '' !== $ip ? lasery_xtool_order_reserve_ip( $ip ) : array();
	if ( false === $reservation ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'busy' ) );
	}
	$owner = lasery_xtool_order_emails_owner( $lines, $fields, $language );
	$customer = lasery_xtool_order_emails_customer( $lines, $language );
	$recipient = lasery_xtool_order_recipient();
	if ( '' === $recipient || ! lasery_xtool_order_mail( $recipient, $owner, array( 'Reply-To: ' . $fields['email'] ) ) ) {
		if ( $reservation ) {
			lasery_xtool_order_release_ip( $reservation );
		}
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'failed' ) );
	}
	lasery_xtool_cart_save( array() );
	$receipt_sent = lasery_xtool_order_mail( $fields['email'], $customer, array( 'Reply-To: ' . lasery_xtool_order_contact_email() ) );
	lasery_xtool_cart_redirect( $language, array( 'cart' => $receipt_sent ? 'sent' : 'sent_no_receipt' ) );
}

/** "Product name" or "Product name + 2" for subjects. */
function lasery_xtool_cart_subject( $lines, $language ) {
	$first = lasery_xtool_order_product_title( $lines[0]['product'], $language );
	return count( $lines ) > 1 ? $first . ' + ' . ( count( $lines ) - 1 ) : $first;
}

/** One email row per line: "1. Product" and its variant, SKU, quantity and value. */
function lasery_xtool_cart_email_rows( $lines, $text, $language, $owner ) {
	$rows = array();
	foreach ( $lines as $index => $line ) {
		$details = array();
		$source = lasery_xtool_order_product_title( $line['product'], '' );
		if ( $owner && $source !== lasery_xtool_order_product_title( $line['product'], $language ) ) {
			$details[] = 'Nazwa w xTool: ' . $source;
		}
		if ( $line['variant'] ) {
			$details[] = $text['variant'] . ': ' . lasery_xtool_variant_label( $line['variant'] ) . ( $owner ? ' (ID ' . $line['variant']['id'] . ')' : '' );
			if ( $owner && ! empty( $line['variant']['sku'] ) ) {
				$details[] = 'SKU: ' . $line['variant']['sku'];
			}
		}
		$details[] = $text['quantity'] . ': ' . $line['quantity'];
		$details[] = $text['unit_price'] . ': ' . lasery_xtool_cart_line_value( $line, 1, $text, $language );
		if ( $line['amount'] ) {
			$details[] = $text['line_total'] . ': ' . lasery_xtool_cart_line_value( $line, $line['quantity'], $text, $language );
		}
		$rows[] = array( ( $index + 1 ) . '. ' . lasery_xtool_order_product_title( $line['product'], $language ), implode( "\n", $details ) );
	}
	$rows[] = array( $text['total'], lasery_xtool_cart_total( $lines, $text, $language ) );
	return $rows;
}

function lasery_xtool_order_emails_owner( $lines, $fields, $language ) {
	$text = lasery_xtool_order_texts( 'pl' );
	return array(
		'language' => 'pl',
		'title'    => sprintf( $text['owner_subject'], lasery_xtool_cart_subject( $lines, 'pl' ) ),
		'intro'    => 'Klient czeka na link do płatności: złóż to zamówienie w xTool i wyślij klientowi link. Odpowiedź na tę wiadomość trafi bezpośrednio do klienta.',
		'sections' => array(
			array( 'heading' => $text['items'], 'rows' => array_merge( lasery_xtool_cart_email_rows( $lines, $text, 'pl', true ), array( array( 'Język klienta', strtoupper( $language ) ) ) ) ),
			array( 'heading' => $text['contact'], 'rows' => array( array( $text['first_name'], $fields['first_name'] ), array( $text['last_name'], $fields['last_name'] ), array( $text['email'], $fields['email'] ), array( $text['phone'], $fields['phone'] ) ) ),
			array( 'heading' => $text['address'], 'rows' => array( array( $text['address1'], $fields['address1'] ), array( $text['address2'], $fields['address2'] ), array( $text['zip'], $fields['zip'] ), array( $text['city'], $fields['city'] ), array( $text['country'], lasery_xtool_country_name( $fields['country'], 'pl' ) . ' (' . $fields['country'] . ')' ) ) ),
			array( 'heading' => $text['extras'], 'rows' => array( array( $text['invoice'], $fields['invoice'] ), array( $text['notes'], $fields['notes'] ) ) ),
		),
		'note'     => $text['price_note'],
		'footer'   => lasery_xtool_mail_from_name(),
	);
}

// The address is unverified, so the receipt repeats only data from our side: it must not
// let anyone send their own text from this domain to an arbitrary mailbox.
function lasery_xtool_order_emails_customer( $lines, $language ) {
	$text = lasery_xtool_order_texts( $language );
	$company = function_exists( 'lasery_xtool_company' ) ? "\n" . lasery_xtool_company() : '';
	return array(
		'language' => $language,
		'title'    => sprintf( $text['customer_subject'], lasery_xtool_cart_subject( $lines, $language ) ),
		'intro'    => $text['customer_body'],
		'sections' => array( array( 'heading' => $text['items'], 'rows' => lasery_xtool_cart_email_rows( $lines, $text, $language, false ) ) ),
		'note'     => $text['price_note'] . "\n" . $text['reply_note'],
		'footer'   => lasery_xtool_mail_from_name() . ' · ' . lasery_xtool_order_contact_email() . $company,
	);
}

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

/** The cart page (slug "koszyk") in the given language, or '' before it exists. */
function lasery_xtool_cart_url( $language ) {
	$page = get_page_by_path( 'koszyk' );
	return $page && 'publish' === $page->post_status ? add_query_arg( 'lang', $language, get_permalink( $page ) ) : '';
}

/** The cart as [product ID, variant ID ('' for products without source variants), quantity]. */
function lasery_xtool_cart_items() {
	$raw = isset( $_COOKIE[ LASERY_XTOOL_CART_COOKIE ] ) && is_string( $_COOKIE[ LASERY_XTOOL_CART_COOKIE ] ) ? wp_unslash( $_COOKIE[ LASERY_XTOOL_CART_COOKIE ] ) : '';
	$items = array();
	foreach ( array_slice( explode( '|', $raw ), 0, LASERY_XTOOL_CART_MAX_LINES ) as $part ) {
		if ( preg_match( '/^([1-9][0-9]{0,19}):([1-9][0-9]{0,19})?:([1-9][0-9]?|100)$/D', $part, $match ) ) {
			$items[ $match[1] . ':' . $match[2] ] = array( (int) $match[1], $match[2], (int) $match[3] );
		}
	}
	return array_values( $items );
}

function lasery_xtool_cart_save( $items ) {
	$value = implode( '|', array_map( function ( $item ) { return implode( ':', $item ); }, array_slice( $items, 0, LASERY_XTOOL_CART_MAX_LINES ) ) );
	$_COOKIE[ LASERY_XTOOL_CART_COOKIE ] = $value;
	// Not HttpOnly: the header shows the number of items from it. It holds no personal data.
	setcookie( LASERY_XTOOL_CART_COOKIE, $value, array( 'expires' => '' === $value ? 1 : time() + 30 * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax' ) );
}

/** Whole cents of a decimal price string such as "1234.5". */
function lasery_xtool_cents( $price ) {
	return (int) round( (float) $price * 100 );
}

/**
 * Current data of cart items: product, variant (null without source variants), quantity,
 * price [amount, currency] or null, and an error ('stale' or 'unavailable') when the line
 * cannot be ordered now. Products and variants that no longer exist are left out.
 */
function lasery_xtool_cart_lines( $items ) {
	$lines = array();
	foreach ( $items as list( $post_id, $variant_id, $quantity ) ) {
		if ( LASERY_XTOOL_PRODUCT_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			continue;
		}
		$line = array( 'product' => $post_id, 'variant' => null, 'quantity' => $quantity, 'amount' => null, 'error' => '' );
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

/** [cents, currency] of all lines when every line has a price in one currency, else null. */
function lasery_xtool_cart_total( $lines ) {
	$cents = 0;
	$currency = '';
	foreach ( $lines as $line ) {
		if ( ! $line['amount'] || ( '' !== $currency && $currency !== $line['amount'][1] ) ) {
			return null;
		}
		$currency = $line['amount'][1];
		$cents += lasery_xtool_cents( $line['amount'][0] ) * $line['quantity'];
	}
	return '' === $currency ? null : array( $cents, $currency );
}

/** Ties the order form to the prices the customer saw. */
function lasery_xtool_cart_snapshot( $lines ) {
	$data = array_map( function ( $line ) { return array( $line['product'], $line['variant'] ? $line['variant']['id'] : '', $line['quantity'], $line['amount'], $line['error'] ); }, $lines );
	return hash_hmac( 'sha256', wp_json_encode( $data ), wp_salt( 'nonce' ) );
}

function lasery_xtool_format_cents( $cents, $currency, $language ) {
	return lasery_xtool_format_price( number_format( $cents / 100, 2, '.', '' ), $currency, $language );
}

function lasery_xtool_variant_label( $variant ) {
	return $variant ? str_replace( ' / ', ' · ', sanitize_text_field( html_entity_decode( $variant['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) : '';
}

/** "2 × 1 234,00 € = 2 468,00 €" for a line, or the "to be confirmed" text. */
function lasery_xtool_cart_line_price( $line, $text, $language ) {
	if ( ! $line['amount'] ) {
		return $line['quantity'] . ' × ' . $text['no_price'];
	}
	$unit = lasery_xtool_cents( $line['amount'][0] );
	return $line['quantity'] . ' × ' . lasery_xtool_format_cents( $unit, $line['amount'][1], $language ) . ' = ' . lasery_xtool_format_cents( $unit * $line['quantity'], $line['amount'][1], $language );
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

// Adding or changing items has no effect beyond the visitor's own cart, so no nonce is
// needed, and product pages can stay in the page cache.
add_action( 'admin_post_lasery_xtool_cart_add', 'lasery_xtool_cart_add' );
add_action( 'admin_post_nopriv_lasery_xtool_cart_add', 'lasery_xtool_cart_add' );
function lasery_xtool_cart_add() {
	$language = lasery_xtool_posted_language();
	$post_id = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$variant = isset( $_POST['variant'] ) && is_string( $_POST['variant'] ) ? sanitize_text_field( wp_unslash( $_POST['variant'] ) ) : '';
	$quantity = isset( $_POST['quantity'] ) && is_string( $_POST['quantity'] ) && preg_match( '/^(?:[1-9][0-9]?|100)$/D', $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0;
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

add_action( 'admin_post_lasery_xtool_cart_update', 'lasery_xtool_cart_update' );
add_action( 'admin_post_nopriv_lasery_xtool_cart_update', 'lasery_xtool_cart_update' );
function lasery_xtool_cart_update() {
	$quantities = isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : array();
	$remove = isset( $_POST['remove'] ) && is_string( $_POST['remove'] ) && ctype_digit( $_POST['remove'] ) ? (int) $_POST['remove'] : -1;
	$items = array();
	foreach ( lasery_xtool_cart_items() as $index => $item ) {
		$quantity = isset( $quantities[ $index ] ) && is_string( $quantities[ $index ] ) && preg_match( '/^(?:0|[1-9][0-9]?|100)$/D', trim( $quantities[ $index ] ) ) ? (int) $quantities[ $index ] : $item[2];
		if ( $index !== $remove && $quantity > 0 ) {
			$items[] = array( $item[0], $item[1], $quantity );
		}
	}
	lasery_xtool_cart_save( $items );
	lasery_xtool_cart_redirect( lasery_xtool_posted_language(), array() );
}

// The cart page shows one visitor's cart and the order form with a nonce.
add_action( 'template_redirect', 'lasery_xtool_cart_page_headers', 0 );
function lasery_xtool_cart_page_headers() {
	if ( ! is_page( 'koszyk' ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'Shopping cart' );
}

add_filter( 'wp_robots', 'lasery_xtool_cart_robots' );
function lasery_xtool_cart_robots( $robots ) {
	return is_page( 'koszyk' ) ? array( 'noindex' => true, 'follow' => true ) : $robots;
}

function lasery_xtool_render_cart() {
	$language = lasery_xtool_order_language();
	$text = lasery_xtool_order_texts( $language );
	$lines = lasery_xtool_cart_lines( lasery_xtool_cart_items() );
	$total = lasery_xtool_cart_total( $lines );
	$orderable = $lines && ! array_filter( array_column( $lines, 'error' ) );
	$result = isset( $_GET['cart'] ) && is_string( $_GET['cart'] ) ? sanitize_key( wp_unslash( $_GET['cart'] ) ) : '';
	$notices = array( 'added' => 'success', 'sent' => 'success', 'sent_no_receipt' => 'success', 'full' => 'error', 'failed' => 'error', 'invalid' => 'error', 'busy' => 'error', 'changed' => 'error' );
	$archive = get_post_type_archive_link( LASERY_XTOOL_PRODUCT_TYPE );
	$archive = $archive ? add_query_arg( 'lang', $language, $archive ) : home_url( '/' );
	?>
	<section class="order-form cart" aria-labelledby="cart-title">
		<header class="order-form-header">
			<h1 id="cart-title" class="page-title"><?php echo esc_html( $text['cart_title'] ); ?></h1>
			<?php if ( $lines ) : ?><p><?php echo esc_html( $text['cart_intro'] ); ?></p><?php endif; ?>
		</header>
		<?php if ( isset( $notices[ $result ] ) ) : ?>
			<p class="order-form-notice order-form-notice--<?php echo esc_attr( $notices[ $result ] ); ?>" role="status"><?php echo esc_html( $text[ 'cart_' . $result ] ?? $text[ $result ] ); ?></p>
		<?php endif; ?>
		<?php if ( ! $lines ) : ?>
			<p class="empty-state"><?php echo esc_html( $text['cart_empty'] ); ?></p>
			<p><a class="btn btn-primary" href="<?php echo esc_url( $archive ); ?>"><?php echo esc_html( $text['browse'] ); ?></a></p>
		</section>
			<?php
			return;
		endif;
		?>
		<form class="cart-items" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-cart-form>
			<input type="hidden" name="action" value="lasery_xtool_cart_update">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<ul class="cart-lines">
				<?php foreach ( $lines as $index => $line ) : ?>
					<?php $link = add_query_arg( 'lang', $language, get_permalink( $line['product'] ) ); ?>
					<li class="cart-line<?php echo $line['error'] ? ' cart-line--error' : ''; ?>">
						<a class="cart-line-media" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $line['product'], 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?></a>
						<div class="cart-line-body">
							<p class="cart-line-title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( lasery_xtool_order_product_title( $line['product'], $language ) ); ?></a></p>
							<?php if ( $line['variant'] ) : ?><p class="cart-line-variant"><?php echo esc_html( lasery_xtool_variant_label( $line['variant'] ) ); ?></p><?php endif; ?>
							<?php if ( $line['error'] ) : ?><p class="cart-line-error"><?php echo esc_html( $text[ 'cart_' . $line['error'] ] ); ?></p><?php endif; ?>
							<p class="cart-line-price"><?php echo esc_html( $line['error'] ? '' : lasery_xtool_cart_line_price( $line, $text, $language ) ); ?></p>
						</div>
						<div class="cart-line-actions">
							<label class="visually-hidden" for="cart-quantity-<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $text['quantity'] ); ?></label>
							<input id="cart-quantity-<?php echo esc_attr( $index ); ?>" name="quantity[<?php echo esc_attr( $index ); ?>]" type="number" min="0" max="100" value="<?php echo esc_attr( $line['quantity'] ); ?>" data-cart-quantity>
							<button type="submit" class="cart-line-remove" name="remove" value="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $text['remove'] ); ?></button>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="cart-items-actions">
				<button type="submit" class="btn btn-secondary" data-cart-update><?php echo esc_html( $text['update'] ); ?></button>
				<a class="btn btn-secondary" href="<?php echo esc_url( $archive ); ?>"><?php echo esc_html( $text['continue'] ); ?></a>
			</p>
		</form>
		<form class="order-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lasery_xtool_order">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<input type="hidden" name="snapshot" value="<?php echo esc_attr( lasery_xtool_cart_snapshot( $lines ) ); ?>">
			<?php wp_nonce_field( 'lasery_xtool_order' ); ?>
			<div class="order-form-trap" aria-hidden="true"><label for="order-website">Website</label><input id="order-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
			<div class="order-form-steps">
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
								<?php foreach ( array_keys( lasery_xtool_shipping_countries() ) as $code ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( lasery_xtool_country_name( $code, $language ) ); ?></option>
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
				<dl class="order-summary-list">
					<?php foreach ( $lines as $line ) : ?>
						<dt><?php echo esc_html( $line['quantity'] . ' × ' . lasery_xtool_order_product_title( $line['product'], $language ) ); ?></dt>
						<dd><?php echo esc_html( $line['amount'] ? lasery_xtool_format_cents( lasery_xtool_cents( $line['amount'][0] ) * $line['quantity'], $line['amount'][1], $language ) : $text['no_price'] ); ?></dd>
					<?php endforeach; ?>
					<dt class="order-summary-total"><?php echo esc_html( $text['total'] ); ?></dt>
					<dd class="order-summary-price"><?php echo esc_html( $total ? lasery_xtool_format_cents( $total[0], $total[1], $language ) : $text['no_price'] ); ?></dd>
				</dl>
				<p class="order-summary-note"><?php echo esc_html( $text['price_note'] ); ?></p>
				<button type="submit" class="btn btn-primary btn-block" <?php disabled( ! $orderable ); ?>><?php echo esc_html( $text['submit'] ); ?></button>
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
	if ( ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lasery_xtool_order' ) ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'invalid' ) );
	}
	if ( ! isset( $_POST['website'] ) || ! is_string( $_POST['website'] ) || '' !== trim( wp_unslash( $_POST['website'] ) ) ) {
		lasery_xtool_cart_redirect( $language, array( 'cart' => 'failed' ) );
	}
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
	$lines = lasery_xtool_cart_lines( lasery_xtool_cart_items() );
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
		$details[] = $text['quantity'] . ': ' . lasery_xtool_cart_line_price( $line, $text, $language );
		$rows[] = array( ( $index + 1 ) . '. ' . lasery_xtool_order_product_title( $line['product'], $language ), implode( "\n", $details ) );
	}
	$total = lasery_xtool_cart_total( $lines );
	$rows[] = array( $text['total'], $total ? lasery_xtool_format_cents( $total[0], $total[1], $language ) : $text['no_price'] );
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
		'button'   => null,
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
		'button'   => null,
		'note'     => $text['price_note'] . "\n" . $text['reply_note'],
		'footer'   => lasery_xtool_mail_from_name() . ' · ' . lasery_xtool_order_contact_email() . $company,
	);
}

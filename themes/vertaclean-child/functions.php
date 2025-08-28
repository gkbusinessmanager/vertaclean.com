<?php
/**
 * Vertaclean Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Vertaclean Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_VERTACLEAN_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'vertaclean-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_VERTACLEAN_CHILD_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );




//
add_shortcode('ajax_add_to_cart_variations', 'custom_ajax_add_to_cart_variations');

function custom_ajax_add_to_cart_variations($atts) {
    if (!isset($atts['id'])) return 'Product ID missing.';

    $product_id = intval($atts['id']);
    $product = wc_get_product($product_id);
	
    if (!$product || (!$product->is_type('variable') && !$product->is_type('fsb-variable-subscription'))) return 'Not a variable product.';

    ob_start();

    // Get available variations
    $available_variations = $product->get_available_variations();
    $attributes = $product->get_attributes();
    $variations_json = wp_json_encode($available_variations);
    $form_action = esc_url(admin_url('admin-ajax.php'));
    ?>
    <form class="ajax-variation-cart-form-<?php echo $product->get_type(); ?>" data-product_id="<?php echo esc_attr($product_id); ?>" action="<?php echo $form_action; ?>" method="POST">
		<?php
		/* if($product_id == 3208) {
			echo '<div class="single_variation"><h3>VertaClean System</h3><select ><option value="vertaclean-system">VertaClean System</option></select></div>';
		} */

		foreach ( $attributes as $attribute_name => $attribute_obj ) {

			$is_taxonomy = $attribute_obj->is_taxonomy();
			$taxonomy = $is_taxonomy ? $attribute_name : wc_attribute_taxonomy_name( $attribute_name );
			$attribute_label = wc_attribute_label( $attribute_name );

			// Get terms for taxonomy-based (global) attributes
			if ( $is_taxonomy ) {
				$terms = get_terms([
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]);
				
				$att_label = $attribute_label;
			} else {
				// For custom (non-taxonomy) attributes
				$terms = [];
				foreach ( $attribute_obj->get_options() as $option ) {
					$terms[] = (object) [
						'slug' => $option,
						'name' => $option,
					];
				}
				
				$att_label = $attribute_obj->get_name();
			}
			

			echo '<div class="single_variation">';
				if ($product->is_type('fsb-variable-subscription')) {
					if ($taxonomy == 'pa_select-your-scent-pack') {
						echo '<h3>Footwash</h3>';
					}
					elseif ($taxonomy == 'pa_select-your-brush-pack') {
						echo '<h3>Brushes</h3>';
					}
					elseif ($taxonomy == 'pa_vertaclean-system') {
						echo '<h3>VertaClean System</h3>';
					}
				}
				if ($taxonomy != 'pa_vertaclean-system') {
					echo '<label for="' . esc_attr( $taxonomy ) . '">' . esc_html( $att_label ) . '</label>';
				}
				echo '<select name="attributes[' . esc_attr( $attribute_name ) . ']" required>';
				
					if(count($terms) > 1) {
						echo '<option value="">Please Select</option>';
					}
						
					foreach ( $terms as $term ) {
						echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
					}
				echo '</select>';
			echo '</div>';
		}

		?>

		<input type="hidden" name="quantity" value="1" min="1" />
        <input type="hidden" name="action" value="custom_add_variation_to_cart" />
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />
		<div class="cart_buttons_container">
			<?php
				$cart_button_text = "Add to Cart";
				$view_button_text = "View Cart";
				$button_link = wc_get_cart_url();
				if ($product->is_type('fsb-variable-subscription')) {
					$cart_button_text = "Start Your Subscription";
					$view_button_text = "Proceed to Checkout";
					$button_link = wc_get_checkout_url();
				}
			?>
			<button type="submit" class="submit_button"><?php echo $cart_button_text; ?></button>
			<?php
			$view_cart_visibility = "hidden button";
			if (!$product->is_type('fsb-variable-subscription')) {
				$view_cart_visibility = "";
			}
			?>
			<a class="cart-link <?php echo $view_cart_visibility; ?>" href="<?php echo $button_link; ?>"><?php echo $view_button_text; ?></a>
		</div>
        <div class="ajax-cart-response"></div>
    </form>

    <script>
		jQuery(document).ready(function($){
			const product_type = "<?php echo $product->get_type(); ?>";
			$('.ajax-variation-cart-form-'+product_type).each(function(){
				var form = $(this);
				form.find('.submit_button').on('click', function(e){
					e.preventDefault();

					// Validation: Check all selects have a value
					let allSelected = true;
					form.find('select').each(function(){
						if ($(this).val() === '') {
							allSelected = false;
							$(this).addClass('input-error'); // Optional: add a class to highlight
						} else {
							$(this).removeClass('input-error');
						}
					});

					if (!allSelected) {
						form.find('.ajax-cart-response').html("Please select all options.");
						return; // Stop here if any select is not filled
					}

					var formData = form.serialize();
					
					$.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", formData, function(response){
						if(product_type == "fsb-variable-subscription") {
							form.find('.submit_button').hide();
							form.find('a.cart-link.button').removeClass("hidden");
							
							<?php if ($product->is_type('fsb-variable-subscription')) { ?>
								// redirect to checkout page
								window.location.href = "<?php echo wc_get_checkout_url(); ?>";
							<?php } else { ?>
								// redirect to cart page
								window.location.href = "<?php echo wc_get_cart_url(); ?>";
							<?php } ?>
							return;
						}
						if (response.success) {
							$(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, form]);
							form.find('.ajax-cart-response').html("Your Cart Has Been Updated");
						}
					});
				});
			});
		});
    </script>
    <?php

    return ob_get_clean();
}



add_action('wp_ajax_custom_add_variation_to_cart', 'handle_custom_add_variation_to_cart');
add_action('wp_ajax_nopriv_custom_add_variation_to_cart', 'handle_custom_add_variation_to_cart');

function handle_custom_add_variation_to_cart() {
    $product_id = intval($_POST['product_id']);
    $attributes = $_POST['attributes'] ?? [];
    $quantity   = intval($_POST['quantity']) ?: 1;

    $variation_id = find_matching_product_variation($product_id, $attributes);

    if (!$variation_id) {
        wp_send_json(['success' => false, 'message' => 'No matching variation found.']);
    }

    $added = WC()->cart->add_to_cart($variation_id, $quantity, 0, [], []);

    if ($added) {
        wp_send_json(['success' => true, 'message' => 'Product added to cart.']);
    } else {
        wp_send_json(['success' => false, 'message' => 'Could not add product to cart.']);
    }
}

function find_matching_product_variation($product_id, $attributes) {
    $product = wc_get_product($product_id);
	if (!$product || (!$product->is_type('variable') && !$product->is_type('fsb-variable-subscription'))) return 0;

    foreach ($product->get_available_variations() as $variation) {
        $match = true;
        foreach ($attributes as $key => $value) {
            if (!isset($variation['attributes']['attribute_' . sanitize_title($key)]) ||
                $variation['attributes']['attribute_' . sanitize_title($key)] !== $value) {
                $match = false;
                break;
            }
        }
        if ($match) return $variation['variation_id'];
    }
    return 0;
}






// show individual products into dropdown
function multiple_product_dropdown_shortcode( $atts ) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts( array(
        'heading' => '',
        'label' => '',
        'ids' => '',
    ), $atts );

    $product_ids = array_filter( array_map( 'absint', explode( ',', $atts['ids'] ) ) );

    if ( empty( $product_ids ) ) return '';

    ob_start();
    ?>
    <div class="product-dropdown-wrapper" data-dropdown-id="<?php echo esc_attr( $instance ); ?>">
		<div class="left_area">
			<h3><?php echo $atts['heading']; ?></h3>
			<label><?php echo $atts['label']; ?></label>
			<select class="product-dropdown" data-instance="<?php echo esc_attr( $instance ); ?>">
				<option value="">Please Select</option>
				<?php foreach ( $product_ids as $id ) :
					$product = wc_get_product( $id );
					
					if ( $product ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" data-price="<?php echo esc_attr( $product->get_price() ); ?>">
							<?php echo esc_html( $product->get_name() ); ?>
						</option>
					<?php endif;
				endforeach; ?>
			</select>
		</div>
		<div class="right_area">
			<h3><span class="product-price" data-instance="<?php echo esc_attr( $instance ); ?>">$0.00</span></h3>
			<label>Quantity</label>
			<input type="number" class="product-qty" min="1" value="1" data-instance="<?php echo esc_attr( $instance ); ?>" />
		</div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'product_dropdown', 'multiple_product_dropdown_shortcode' );


function product_dropdown_cart_summary_shortcode() {
    ob_start(); ?>
    <div id="dropdown-cart-summary" style="margin-top: 20px;">
        <div class="left_area">
			<h3>Total:</h3>
		</div>
        <div class="right_area">
			<h3>$<span id="dropdown-cart-total">0.00</span></h3>
		</div>
    </div>
	<div class="cart_buttons_container right_align">
		<a class="cart-link" href="<?php echo wc_get_cart_url(); ?>">View Cart</a>
        <button id="dropdown-add-to-cart" class="button">Add to Cart</button>
	</div>
    <div id="dropdown-cart-response"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'dropdown_cart_summary', 'product_dropdown_cart_summary_shortcode' );


add_action('wp_footer', 'multiple_product_dropdown_script');
function multiple_product_dropdown_script() {
	?>
	<script>
		jQuery(document).ready(function($) {
		function updateTotal() {
			let total = 0;
			document.querySelectorAll('.product-dropdown-wrapper').forEach(wrapper => {
				const select = wrapper.querySelector('.product-dropdown');
				const qty = wrapper.querySelector('.product-qty');
				const priceBox = wrapper.querySelector('.product-price');

				const selected = select.options[select.selectedIndex];
				const price = parseFloat(selected?.dataset.price || 0);
				const quantity = parseInt(qty?.value || 1);
				const lineTotal = price * quantity;

				if (!isNaN(lineTotal)) {
					priceBox.innerText = '$' + lineTotal.toFixed(2);
					total += lineTotal;
				}
			});

			const totalBox = document.getElementById('dropdown-cart-total');
			if (totalBox) totalBox.textContent = total.toFixed(2);
		}

		document.querySelectorAll('.product-dropdown, .product-qty').forEach(el => {
			el.addEventListener('change', updateTotal);
			el.addEventListener('input', updateTotal);
		});

		function calculateTotal() {
				let total = 0;
				$('.product-dropdown-wrapper').each(function() {
					const $dropdown = $(this).find('.product-dropdown');
					const $qty = $(this).find('.product-qty');
					const price = parseFloat($dropdown.find(':selected').data('price')) || 0;
					const qty = parseInt($qty.val()) || 1;
					if ($dropdown.val()) total += price * qty;
				});
				$('#dropdown-cart-total').text(total.toFixed(2));
			}

			$(document).on('change', '.product-dropdown, .product-qty', calculateTotal);

			$('#dropdown-add-to-cart').on('click', function(e) {
				e.preventDefault();

				let items = [];
				$('.product-dropdown-wrapper').each(function() {
					const product_id = $(this).find('.product-dropdown').val();
					const qty = $(this).find('.product-qty').val();
					if (product_id && qty > 0) {
						items.push({ product_id, qty });
					}
				});

				if (items.length === 0) {
					alert('Please select at least one product.');
					return;
				}

				$.ajax({
					url: '<?php echo admin_url('admin-ajax.php'); ?>',
					method: 'POST',
					data: {
						action: 'add_multiple_products_to_cart',
						items: items,
					},
					success: function(response) {
						if (response.success) {
							// alert('Products added to cart!');
							$('#dropdown-add-to-cart').text("Added to Cart");
							$('#dropdown-cart-response').text("Your Cart Has Been Updated");
							// Optionally, reload mini-cart or redirect to cart
						} else {
							alert(response.data || 'Something went wrong');
							$('#dropdown-cart-response').text("Something went wrong");
						}
					},
					error: function() {
						alert('Request failed.');
					}
				});
			});
		});
	</script>
	<?php
}

add_action( 'wp_ajax_add_multiple_products_to_cart', 'add_multiple_products_to_cart' );
add_action( 'wp_ajax_nopriv_add_multiple_products_to_cart', 'add_multiple_products_to_cart' );
function add_multiple_products_to_cart() {
    if ( ! isset( $_POST['items'] ) || ! is_array( $_POST['items'] ) ) {
        wp_send_json_error( 'No items found' );
    }

    foreach ( $_POST['items'] as $item ) {
        $product_id = absint( $item['product_id'] );
        $qty        = absint( $item['qty'] );

        if ( $product_id && $qty ) {
            WC()->cart->add_to_cart( $product_id, $qty );
        }
    }

    wp_send_json_success( 'Products added to cart' );
}




// show SKU in order summay on cart and checkout page
add_filter('woocommerce_cart_item_name', 'add_sku_to_cart_item_name', 10, 3);
function add_sku_to_cart_item_name($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['data']) && is_object($cart_item['data'])) {
        $product = $cart_item['data'];
        $sku = $product->get_sku();
		if ($product && ($product->is_type('variation'))) {
			$name .= '';
		}
		else if ($sku) {
            $name .= '<br><small>SKU: ' . esc_html($sku) . '</small>';
        }
    }
    return $name;
}
// Add SKU to product name in thank you and order details pages
add_filter('woocommerce_order_item_name', 'add_sku_to_order_item_name', 10, 2);
function add_sku_to_order_item_name($name, $item) {
    $product = $item->get_product();
	
	// fsb_subscription_variation
 	if ($product && ($product->is_type('variation'))) {
		$name .= '';
	}
    else if ($product && $product->get_sku()) {
        $name .= '<br><small this="sku"><strong>SKU:</strong> ' . esc_html($product->get_sku()) . '</small>';
    }
    return $name;
}


/* // Hide variations in order summary on Cart & Checkout pages
add_filter( 'woocommerce_get_item_data', 'hide_variation_data_from_order_summary', 10, 2 );
function hide_variation_data_from_order_summary( $item_data, $cart_item ) {
    // Return an empty array to hide all variation data
    return [];
}
// Hide variation data from Thank You page and emails
add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'hide_variation_data_from_order', 10, 2 );
function hide_variation_data_from_order( $formatted_meta, $order_item ) {
    return []; // Remove variation display
} */




// Replace variation attribute labels in cart/checkout/thank you/emails
add_filter('woocommerce_get_item_data', 'custom_replace_variation_labels', 10, 2);
function custom_replace_variation_labels($item_data, $cart_item) {
    $label_replacements = array(
        'select your scent pack' => 'Selected scent pack',
        'select your brush pack' => 'Selected brush pack',
        'select your footwash'   => 'Selected Footwash',
    );

    $updated_item_data = array();

	foreach ($item_data as $data) {
		if (isset($label_replacements[strtolower($data['key'])])) {
			$data['key'] = $label_replacements[strtolower($data['key'])];
		}
		$updated_item_data[] = $data;
	}

	return $updated_item_data;
}

/* // Replace variation labels in thank you page, emails, and admin order details
add_filter('woocommerce_order_item_display_meta_key', 'custom_replace_variation_labels_order_display', 10, 3);
function custom_replace_variation_labels_order_display($display_key, $meta, $order_item) {
	$label_replacements = array(
		'select your scent pack' => 'Selected scent pack',
		'select your brush pack' => 'Selected brush pack',
        'select your footwash' => 'Selected Footwash',
	);

	if (isset($label_replacements[strtolower($display_key)])) {
		return $label_replacements[strtolower($display_key)];
	}

	return $display_key;
} */



add_filter('woocommerce_order_item_display_meta_key', 'remove_variation_labels_from_emails', 10, 3);
function remove_variation_labels_from_emails($display_key, $meta, $order_item) {
    // Check if we're inside an email context
    if (is_email_context()) {
        return ''; // return empty to hide label
    }

    return $display_key;
}

// Helper function to detect email context
function is_email_context() {
    return did_action('woocommerce_email_header') > 0;
}




///// hide SKU from order Email
add_filter('woocommerce_order_item_get_formatted_meta_data', 'hide_sku_for_specific_product_type_in_email', 10, 2);
function hide_sku_for_specific_product_type_in_email($formatted_meta, $order_item) {
    // Only operate on real product line items; skip tax, shipping, fee, coupon, etc.
    if (!($order_item instanceof WC_Order_Item_Product)) {
        return $formatted_meta;
    }
    
    $product = $order_item->get_product();
    
    if (!$product) {
        return $formatted_meta;
    }

    // Change this to your desired product type
    $hide_for_type = 'variable'; // e.g., 'simple', 'variable', 'grouped', 'custom'

    if ($product->get_type() === $hide_for_type) {
        foreach ($formatted_meta as $key => $meta) {
            if (stripos($meta->display_key, 'SKU') !== false) {
                unset($formatted_meta[$key]);
            }
        }
    }

    return $formatted_meta;
}


add_action('woocommerce_order_item_meta_start', 'custom_variable_product_order_email_details', 10, 4);
function custom_variable_product_order_email_details($item_id, $item, $order, $plain_text) {
    $product = $item->get_product();

    // Only apply to variable products
    if ($product && $product->is_type('variation')) {
        // Get the parent variable product
        $parent_product = wc_get_product($product->get_parent_id());

        // Output SKU if needed
		$sku = $product->get_sku();
		$sku_exploded = explode(" - ", $sku);
        /* 
        if ($sku) {
            echo '<p><strong>SKU:</strong> ' . esc_html($sku) . '</p>';
        } */

        // Display selected variations
		$variation_attributes = [];

		// three SKUs combined in vertaclean syste base, First part is base product, other two are footwash and brushes
		$count = 0;
		if(count($sku_exploded) == 3) {
			$variation_attributes[$sku_exploded[$count]] = "<strong>".$item->get_name()."</strong>: SKU: ".$sku_exploded[$count];
			$count++;
		}
		foreach ( $item->get_meta_data() as $meta ) {
			$key = $meta->key;
			$value = $meta->value;

			// Exclude hidden meta fields (usually starting with _)
			if ( strpos( $key, '_' ) !== 0 ) {
				$term = get_term_by( 'slug', $value, $key );
				if ( $term && ! is_wp_error( $term ) ) {
					$label = $term->name; // This is the label you're looking for
				} else {
					$label = wc_attribute_label( $value, $product ); // Get the label
				}

				$variation_attributes[ $key ] = "<strong>".esc_html( $label )."</strong>: ".$sku_exploded[$count];
			}
			$count++;
		}
		
		if(!empty($variation_attributes)) {
			
			echo '<div class="email-order-item-meta" style="color: #3c3c3c; font-size: 14px; line-height: 140%;">';
				$pre = "";
				foreach($variation_attributes as $single_attribute) {
					echo $pre.$single_attribute;
					$pre = "<br>";
				}
			echo '</div>';
		}

    }
}

add_filter('woocommerce_order_item_get_formatted_meta_data', 'remove_variation_meta_email', 10, 2);
function remove_variation_meta_email($formatted_meta, $item) {
    // Only operate on real product line items; skip tax, shipping, fee, coupon, etc.
    if (!($item instanceof WC_Order_Item_Product)) {
        return $formatted_meta;
    }
    
    $product = $item->get_product();
    if ($product && $product->is_type('variation')) {
        return []; // Remove default meta for variations
    }
    return $formatted_meta;
}


// starts
// keep "Ship to a different address?" active, but still allow checkout if no shipping method is available:
// also made a change in template review-order.php
add_filter( 'woocommerce_cart_ready_to_checkout', function( $ready ) {
    return true;
}, 20 );

// Suppress "no shipping method selected" error during checkout
add_action( 'woocommerce_after_checkout_validation', function( $data, $errors ) {
    $errors->remove( 'shipping' ); // Remove shipping error group
}, 10, 2 );

// Optional: Hide the error notice (if it still appears visually)
add_filter( 'woocommerce_no_shipping_available_html', '__return_empty_string' );
add_filter( 'woocommerce_cart_no_shipping_available_html', '__return_empty_string' );
// ends




// force default payment gateway credit card
add_action( 'template_redirect', 'define_default_payment_gateway' );
function define_default_payment_gateway(){
    if( is_checkout() && ! is_wc_endpoint_url() ) {
        // HERE define the default payment gateway ID
        $default_payment_id = 'authnet';

        WC()->session->set( 'chosen_payment_method', $default_payment_id );
    }
}




// Remove "Have an Amazon account?" at the top of the checkout page
add_action('template_redirect', function () {
	if ( is_checkout() ) {
		ob_start(function ($content) {
			return preg_replace('/<div class="wc-amazon-checkout-message.*?<\/div>\s*<\/div>/si', '', $content);
		});
	}
});




// To Create Emal template for Subscription orders

// 1. Hook into Subscription Creation Event
add_action('woocommerce_checkout_order_processed', 'handle_new_fsb_subscription_order', 20, 1);
function handle_new_fsb_subscription_order($order_id) {
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_type() === 'fsb_subscription') {
            // Fire your custom email or flag for sending
            do_action('send_custom_subscription_email', $order, $product);
        }
    }
}


// 2. Send a Custom HTML Email
add_action('send_custom_subscription_email', 'send_new_subscription_email', 10, 2);
function send_new_subscription_email($order, $product) {
    $to = $order->get_billing_email();
    $subject = 'Your Subscription Has Started!';
    
    ob_start();
    ?>
    <html>
    <body>
        <h2 style="color:#444;">Thanks for Subscribing!</h2>
        <p>Hi <?php echo esc_html($order->get_billing_first_name()); ?>,</p>
        <p>Thank you for purchasing a subscription to <strong><?php echo esc_html($product->get_name()); ?></strong>.</p>
        <p>Your subscription is now active.</p>
        <hr>
        <p><strong>Order #<?php echo $order->get_order_number(); ?></strong></p>
        <p><a href="<?php echo esc_url($order->get_view_order_url()); ?>">View your order</a></p>
    </body>
    </html>
    <?php
    $message = ob_get_clean();

    wp_mail($to, $subject, $message, [
        'Content-Type: text/html; charset=UTF-8'
    ]);
}


// 3. (Optional) Add Email Template to WooCommerce Admin
add_action('woocommerce_email_after_order_table', 'insert_html_for_fsb_subscription', 10, 4);
function insert_html_for_fsb_subscription($order, $sent_to_admin, $plain_text, $email) {
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_type() === 'fsb_subscription') {
            echo '<div style="margin-top:20px; padding:10px; border:2px dashed #ccc;">';
            echo '<h3>Your Subscription is Active!</h3>';
            echo '<p>You’ve subscribed to <strong>' . esc_html($product->get_name()) . '</strong>.</p>';
            echo '</div>';
            break;
        }
    }
}

// To Add email Class for Woocommerce Email UI

// Register Your Custom Email Class
add_action('init', function() {
    add_filter('woocommerce_email_classes', 'register_custom_subscription_email');
});


function register_custom_subscription_email($email_classes) {
    require_once get_stylesheet_directory() . '/woocommerce/emails/class-customer-new-subscription-email.php';
    $email_classes['Customer_New_Subscription_Email'] = new Customer_New_Subscription_Email();
    return $email_classes;
}

// Trigger Email When fsb_subscription Is Purchased
add_action('woocommerce_checkout_order_processed', 'check_for_subscription_and_trigger_email', 20, 1);
function check_for_subscription_and_trigger_email($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_type() === 'fsb_subscription') {
            do_action('trigger_new_subscription_email', $order_id, $product);
        }
    }
}





// replace subscription string which shows after variations selection onf "Subscription Purchase" product / id: 3208
add_filter( 'woocommerce_available_variation', 'custom_variation_price_html', 9999, 3 );
function custom_variation_price_html( $variation_data, $product, $variation ) {
	
	if($product->get_id() == 3208) {
		
		// The original string comes from the plugin
		$original_string = $variation_data['price_html'];
		
		$modified_string = replace_subscription_string($original_string, $product->get_id());

		$variation_data['price_html'] = $modified_string;
	}

    return $variation_data;

}


// Customize cart item price display
add_filter( 'woocommerce_cart_item_price', function( $price_html, $cart_item, $cart_item_key ) {
	$sign_up_fee = get_post_meta( $cart_item['variation_id'], '_fsb_subscription_sign_up_fee', true );
	
	if($sign_up_fee != "") {
		$original_price = $sign_up_fee;
	} else {
		$original_price = $cart_item['data']->get_price();
	}
    
    $price_html  = wc_price( $original_price );
    
    return $price_html;
}, 10, 3 );



// replace subtotal text for "Subscription product" on chart page id: 3208
add_filter( 'woocommerce_cart_item_subtotal', 'custom_cart_item_subtotal', 9999, 3 );
function custom_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {

    // Example: Only replace for a specific product ID
    $target_product_id = 3208; // Change to your product ID

    if ( $cart_item['product_id'] === $target_product_id ) {
        // Replace subtotal entirely
		
		$replaced_string = replace_subscription_string($subtotal, $target_product_id);
		
        $subtotal = '<span class="custom-subtotal">'.$replaced_string.'</span>';

        // Or modify it (e.g., append text)
        // $subtotal .= '<br><small>(Discount Applied)</small>';
    }

    return $subtotal;
}

function replace_subscription_string($original_string, $product_id) {
	$modified_string = str_replace(
		array("with month", "free trial and a", "sign-up fee"), // find this
		array("after 1st month", "", "today"),  // replace with this
		$original_string
	);
	return $modified_string;
}









/// trigger subscription every 4th month
/**
 * Auto-create refill order every 4th renewal (Flexible Subscriptions)
 */

// Create refill order on every 4th renewal
add_action( 'flexible_subscriptions/renewal_order_created', function( $renewal_order_id, $subscription ) {
    if ( ! $subscription || ! $renewal_order_id ) {
        return;
    }
	
	
    // Count total completed renewals
    $renewal_count = $subscription->get_meta( '_renewal_count', true );
    $renewal_count = $renewal_count ? intval( $renewal_count ) : 0;
    $renewal_count++;

    // Save updated count back to subscription
    $subscription->update_meta_data( '_renewal_count', $renewal_count );
    $subscription->save();

    // Only trigger every 4th renewal
    if ( $renewal_count % 4 !== 0 ) {
        return;
    }

    // Create refill order
    $user_id = $subscription->get_user_id();
	
	/////////////////////////
	$order_id = $subscription->get_parent_id();
	$subscription_order_details = get_ordered_variation_id($order_id);
	$refill_product_id = $subscription_order_details["matching_variation_id"];
	$quantity = $subscription_order_details["quantity"];
	/////////////////////////
	
    $order = wc_create_order( [ 'customer_id' => $user_id ] );
    $order->add_product( wc_get_product( $refill_product_id ), $quantity );

    // Copy billing + shipping details from subscription
    $order->set_address( $subscription->get_address( 'billing' ), 'billing' );
    $order->set_address( $subscription->get_address( 'shipping' ), 'shipping' );

    $order->calculate_totals();
    $order->update_status( 'processing', 'Auto-created refill order (every 4th renewal)' );

}, 10, 2 );


/**
 * Manual test: simulate a renewal to test refill order logic
 * 
 * Usage: https://vertaclean.com/?test_refill=1&sub_id=3230
 */
add_action( 'init', function() {
    if ( isset( $_GET['test_refill'] ) && current_user_can( 'manage_woocommerce' ) ) {
        $sub_id = absint( $_GET['sub_id'] ?? 0 );
        if ( ! $sub_id ) {
            wp_die( 'Missing ?sub_id parameter.' );
        }

        $subscription = wcs_get_subscription( $sub_id ); // Flexible Subscriptions wrapper
        if ( ! $subscription ) {
            wp_die( 'Invalid subscription ID.' );
        }
		
        // Fake renewal order (empty order for testing)
        $test_order = wc_create_order( [ 'customer_id' => $subscription->get_user_id() ] );
        do_action( 'flexible_subscriptions/renewal_order_created', $test_order->get_id(), $subscription );

        wp_die( 'Test refill process triggered for subscription #' . $sub_id );
    }
});



////////////////////////////////////////////////////////////////////////////////////////////////
function get_ordered_variation_id($order_id) {
	
	////////////////////////////////////////////////
	/// get existing order for selected variations
	////////////////////////////////////////////////
	$order = wc_get_order( $order_id );

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		$variation_id = $item->get_variation_id();
		$variation_attributes = $item->get_meta_data(); // or use get_meta() for specific attributes
		$quantity = $item->get_quantity();
	}
	

	$attribute_data = [];
	foreach ( $variation_attributes as $meta ) {
		$key = $meta->get_data()['key'];
		$value = $meta->get_data()['value'];
		
		// Only keep attribute keys (skip non-attribute meta)
		if ( strpos( $key, 'pa_' ) === 0 ) {
			if($key != "pa_vertaclean-system") {
				$attribute_data[ $key ] = $value;
			}
		}
	}
	
	$subscription_only_product = 3248;
	$matching_variation_id = find_matching_variation_id( $subscription_only_product, $attribute_data );
	return array("matching_variation_id" => $matching_variation_id, "quantity" => $quantity);
}

////////////////////////////////////////////////
/// find matching variation in subscirpion only product
////////////////////////////////////////////////
function find_matching_variation_id( $product_b_id, $order_attributes ) {
    $product = wc_get_product( $product_b_id );

    if ( $product && $product->is_type( 'variable' ) ) {
        foreach ( $product->get_available_variations() as $variation ) {
            $variation_attributes = $variation['attributes'];
            $match = true;

            foreach ( $order_attributes as $key => $value ) {
                $formatted_key = 'attribute_' . $key;

                if (
                    ! isset( $variation_attributes[ $formatted_key ] ) ||
                    strtolower( $variation_attributes[ $formatted_key ] ) !== strtolower( $value )
                ) {
                    $match = false;
                    break;
                }
            }

            if ( $match ) {
                return $variation['variation_id'];
            }
        }
    }

    return false;
}


function subscription_only_product() {
	return $subscription_only_product = 3248;	
}
// disable purchase of refill order from the frontend
// Hide from Catalog/Search
add_action( 'pre_get_posts', function( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $query->set( 'post__not_in', array_merge(
        (array) $query->get( 'post__not_in' ),
        [ subscription_only_product() ]
    ) );
} );
// 2. Redirect Product Page if Accessed Directly
add_action( 'template_redirect', function() {
    if ( is_singular( 'product' ) && get_the_ID() === subscription_only_product() ) {
        wp_redirect( home_url() );
        exit;
    }
} );
// 3. Prevent Product B from Being Added to Cart
add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id ) {
    if ( $product_id == subscription_only_product() ) {
        return false;
    }
    return $passed;
}, 10, 2 );
// 4. Remove Product B from Cart if Somehow Added
add_action( 'woocommerce_before_checkout_process', function() {
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( $cart_item['product_id'] == subscription_only_product() || $cart_item['variation_id'] == subscription_only_product() ) {
            WC()->cart->remove_cart_item( $cart_item_key );
        }
    }
} );

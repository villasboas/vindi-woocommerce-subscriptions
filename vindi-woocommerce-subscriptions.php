<?php
/**
* Plugin Name: Vindi Woocommerce Subscriptions
* Plugin URI:
* Description: Adiciona o gateway de pagamentos da Vindi para o WooCommerce Subscriptions.
* Version: 1.0.0
* Author: Vindi
* Author URI: https://www.vindi.com.br
* Requires at least: 4.0
* Tested up to: 4.2
*
* Text Domain: vindi-woocommerce-subscriptions
* Domain Path: /languages/
*
* Copyright: © 2014-2015 Vindi Tecnologia e Marketing LTDA
* License: GPLv3 or later
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if (! defined('ABSPATH')) die('No script kiddies please!');

define('VINDI_IDENTIFIER', 'vindi_subscriptions');

require_once dirname(__FILE__)."/includes/class-vindi-dependencies.php";

/**
* Check all Vindi Dependencies
*/
if(false === Vindi_Dependencies::check()) {
	return ;
}

if (! class_exists('Vindi_WooCommerce_Subscriptions'))
{
	class Vindi_WooCommerce_Subscriptions
	{
	    /**
		 * @var string
		 */
		const VERSION = '1.0.0';

        /**
		 * @var string
		 */
		const VIEWS_DIR = '/templates/';

        /**
         * @var string
         */
        const INCLUDES_DIR = '/includes/';

        /**
         * @var string
         */
        const WC_API_CALLBACK = 'vindi_webhook';

        /**
		 * Instance of this class.
		 * @var Vindi_WooCommerce_Subscriptions
		 */
		protected static $instance = null;

        /**
		 * Instance of Vindi_Settings.
		 * @var Vindi_Settings
		 */
		protected $settings = null;

        /**
		 * Instance of Vindi_Settings.
		 * @var Vindi_Webhook_Handler
		 */
		private $webhook_handler = null;

		public function __construct()
		{
			$this->includes();

			$this->settings = new Vindi_Settings();
            $this->webhook_handler  = new Vindi_Webhook_Handler($this->settings);

            add_action('woocommerce_api_' . self::WC_API_CALLBACK, array(
                $this->webhook_handler, 'handle'
            ));
            add_action('woocommerce_add_to_cart_validation', array(
                &$this, 'validate_add_to_cart'
            ), 1, 3);
            add_action('woocommerce_update_cart_validation', array(
                &$this, 'validate_update_cart'
            ), 1, 4);

            add_filter('plugin_action_links_' . plugin_basename(__FILE__),
                array(&$this, 'action_links')
            );

            if(is_admin()) {
                add_action('woocommerce_product_options_general_product_data',
                    array(&$this, 'subscription_custom_fields')
                );
                add_action('save_post',
                    array(&$this, 'save_subscription_meta')
                );
            }
		}

        /**
		 * Show pricing fields at admin's product page.
		 */
        public function subscription_custom_fields()
        {
    		global $post;

    		echo '<div class="options_group vindi-subscription_pricing show_if_subscription">';

    		$plans         = [__('-- Selecione --', VINDI_IDENTIFIER)] + $this->settings->api->get_plans();
    		$selected_plan = get_post_meta($post->ID, 'vindi_subscription_plan', true);

    		woocommerce_wp_select( [
    				'id'          => 'vindi_subscription_plan',
    				'label'       => __('Plano da Vindi', VINDI_IDENTIFIER),
    				'options'     => $plans,
    				'description' => __('Selecione o plano da Vindi que deseja relacionar a esse produto', VINDI_IDENTIFIER),
    				'desc_tip'    => true,
    				'value'       => $selected_plan,
    			]
    		);

            echo '</div>';
    		echo '<div class="show_if_subscription clear"></div>';
    	}

        /**
         * @param int $post_id
         */
        public function save_subscription_meta($post_id)
        {
            if (! isset($_POST['product-type']) || ('subscription' !== $_POST['product-type']))
                return;

            $subscription_plan  = (int) stripslashes($_REQUEST['vindi_subscription_plan']);

            update_post_meta($post_id, 'vindi_subscription_plan', $subscription_plan);
        }

		/**
		 * Return an instance of this class.
		 * @return Vindi_WooCommerce_Subscriptions
		 */
		public static function get_instance()
		{
			// If the single instance hasn't been set, set it now.
			if (null === self::$instance)
                self::$instance = new self;

			return self::$instance;
		}

		/**
		 * Include the dependents classes
		 **/
		public function includes()
		{
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-logger.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-api.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-settings.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-base-gateway.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-bank-slip-gateway.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-creditcard-gateway.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-payment.php');
			include_once(dirname(__FILE__) . self::INCLUDES_DIR . 'class-vindi-webhook-handler.php');
		}

        /**
         * Generate assets URL
         * @param string $path
         **/
        public static function generate_assets_url($path)
        {
            return plugin_dir_url(__FILE__) . 'assets/' . $path;
        }

        /**
         * Include Settings link on the plugins administration screen
         * @param mixed $links
         */
        public function action_links($links)
        {
            $links[] = '<a href="admin.php?page=wc-settings&tab=settings_vindi">' . __('Configurações', VINDI_IDENTIFIER) . '</a>';
            return $links;
        }

        /**
		 * @param bool $valid
		 * @param int  $product_id
		 * @param int  $quantity
		 *
		 * @return bool
		 */
		public function validate_add_to_cart($valid, $product_id, $quantity)
        {
            $cart       = $this->settings->woocommerce->cart;
			$cart_items = $cart->get_cart();

			$product = wc_get_product($product_id);

			if (empty($cart_items)) {
				if ($product->is_type('subscription')) {
					return 1 === $quantity;
				}

				return $valid;
			}

			foreach($cart_items as $item)
            {
				if ('subscription' === $item['data']->product_type) {
					if ($product->is_type('subscription')) {
						$cart->empty_cart();
						wc_add_notice(__('Uma outra assinatura foi removida do carrinho. Você pode fazer apenas uma assinatura a cada vez.', VINDI_IDENTIFIER), 'notice');

						return $valid;
					}

					wc_add_notice(__('Você não pode ter produtos e assinaturas juntos na mesma compra. Conclua sua compra atual ou limpe o carrinho para adicionar este item.', VINDI_IDENTIFIER), 'error');

					return false;
				} elseif ($product->is_type('subscription')) {
					wc_add_notice(__('Você não pode ter produtos e assinaturas juntos na mesma compra. Conclua sua compra atual ou limpe o carrinho para adicionar este item.', VINDI_IDENTIFIER), 'error');

					return false;
				}
			}

			return $valid;
		}

		/**
		 * @param bool $valid
		 * @param      $cart_item_key
		 * @param      $values
		 * @param int  $quantity
		 *
		 * @return bool
		 */
		public function validate_update_cart($valid, $cart_item_key, $values, $quantity)
        {
            $cart    = $this->settings->woocommerce->cart;
            $item    = $cart->get_cart_item($cart_item_key);
			$product = $item['data'];

			if ($product->is_type('subscription') && 1 !== $quantity && 0 !== $quantity) {
				wc_add_notice(__('Você pode fazer apenas uma assinatura a cada vez.', VINDI_IDENTIFIER), 'error');

				return false;
			}

			return $valid;
		}
	}
}

add_action('wp_loaded', array('Vindi_WooCommerce_Subscriptions', 'get_instance'), 0);

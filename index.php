<?php
/*
Plugin Name: SudahTransfer
Plugin URI: https://sudahtransfer.com/
Description: WooCommerce SudahTransfer Checkout Gateway.
Version: 1.0
Author: SudahTransfer
Author URI: https://sudahtransfer.com/
*/
add_action('plugins_loaded', 'woocommerce_sudahtransfer_init', 0);
function woocommerce_sudahtransfer_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_Sudahtransfer extends WC_Payment_Gateway{

	// Setup our Gateway's id, description and other values
    public function __construct(){
      $this ->id = 'sudahtransfer';
      $this ->medthod_title = 'SudahTransfer';
      $this ->has_fields = true;
      $this ->init_form_fields();
      $this ->init_settings();
       $this->icon =  plugins_url( '/img/sudahtransfer-icon32.png', __FILE__ );
      $this ->title = $this ->settings['title'];
      $this ->description = $this ->settings['description'];
      $this ->merchant_id = $this ->settings['merchant_id'];
      $this ->merchant_api_key = $this ->settings['merchant_api_key'];
      $this ->merchant_secret_key = $this ->settings['merchant_secret_key'];
      $this ->salt = $this ->settings['salt'];
      $this ->redirect_page_id = $this ->settings['redirect_page_id'];
      $this ->liveurl = "https://sudahtransfer.com/panel/admin/index.php?r=orders/submitorder";
      $this ->msg['message'] = "";
      $this ->msg['class'] = "";

      add_action('init', array(&$this, 'check_sudahtransfer_response'));
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      } else {
        add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
      }
      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) ); 
     
    }
    function init_form_fields(){

      $this ->form_fields = array(
        'enabled' => array(
          'title' => __('Enable/Disable', 'sudah-transfer'),
          'type' => 'checkbox',
          'label' => __('Enable SudahTransfer Payment Module.', 'sudah-transfer'),
          'default' => 'no'),
        'merchant_id' => array(
          'title' => __('Merchant ID', 'sudah-transfer'),
          'type' => 'text',
          'desc_tip' => __('This id(Merchant Id) available at "Generate Working Key" of "Settings and Options at SudahTransfer."')),
        'success_url' => array(
                    'title' => __('Success Page (URL)','sudah-transfer'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "Where the customer goes when the transaction is verified."
                ),
        'cancel_url' => array(
                    'title' => __('Cancel Page (URL)','sudah-transfer'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "Where the customer goes when the transaction is cancelled."
                ),
        'title' => array(
          'title' => __('Title:', 'sudah-transfer'),
          'type'=> 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'sudah-transfer'),
          'default' => __('SudahTransfer', 'sudah-transfer')),
        'description' => array(
          'title' => __('Description:', 'sudah-transfer'),
          'type' => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'sudah-transfer'),
          'default' => __('Beli sekarang, bayar belakangan. Pay after delivery.', 'sudah-transfer')),
        
        'merchant_level' => array(
          'title' => __('Merchant Level', 'sudah-transfer'),
          'type' => 'select',
          'description' => __('Total order under IDR 300.000,- will be accepted using SudahTransfer.'),
          'options'     => array(
            '1' => __('Level 1', 'sudah-transfer')
            /*'2' => __('Level 2', 'sudah-transfer')*/
            )),

        'merchant_api_key' => array(
          'title' => __('API Key', 'sudah-transfer'),
          'type' => 'password',
          'description' => __('')),
        'merchant_secret_key' => array(
          'title' => __('Secret Key', 'sudah-transfer'),
          'type' => 'text',
          'description' => __('')),
        'merchant_public_key' => array(
          'title' => __('Public Key', 'sudah-transfer'),
          'type' => 'text',
          'description' => __('')),

        );
    }

    public function admin_options(){
      echo '<h3>'.__('SudahTransfer Payment Gateway', 'sudah-transfer').'</h3>';
      echo '<p>'.__('Increase sales by accepting “pay after delivery” method with SudahTransfer. Easy, fast, and secure').'</p>';
      echo '<table class="form-table">';
        // Generate the HTML For the settings form.
      $this ->generate_settings_html();
      echo '</table>';
       echo '<p>'.__('Enter your SudahTransfer API credentials to allow your customers pay via SudahTransfer. How to get API Credentials? Sign up for a merchant account <a href="https://sudahtransfer.com/merchant/" target="_blank">here</a>').'</p>';

    }

    /**
     *  There are no payment fields for SudahTransfer, but we want to show the description if set.
     **/
    function payment_fields(){
      if($this ->description) echo wpautop(wptexturize($this ->description));
    }
    /**
     * Receipt Page
     **/
    function receipt_page($order){
      /*echo "inn";
      exit;*/
      echo '<p>'.__('Thank you for your order, please click the button below to pay with SudahTransfer.', 'sudah-transfer').'</p>';
      echo $this -> generate_sudahtransfer_form($order);
    }
    /**
     * Generate button link
     **/
    public function generate_sudahtransfer_form($order_id){

      global $woocommerce;
      //exit('in side ');
      $order = new WC_Order( $order_id );

      $txnid = $order_id.'_'.date("ymds");

      $redirect_url = ($this ->redirect_page_id=="" || $this ->redirect_page_id==0)?get_site_url() . "/":get_permalink($this ->redirect_page_id);

      $productinfo = "Order $order_id";

      $str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
      $hash = hash('sha512', $str);

      $sudahtransfer_args = array(
        'key' => $this ->merchant_id,
        'txnid' => $txnid,
        'amount' => $order ->order_total,
        'productinfo' => $productinfo,
        'firstname' => $order ->billing_first_name,
        'lastname' => $order ->billing_last_name,
        'address1' => $order ->billing_address_1,
        'address2' => $order ->billing_address_2,
        'city' => $order ->billing_city,
        'state' => $order ->billing_state,
        'country' => $order ->billing_country,
        'zipcode' => $order ->billing_zip,
        'email' => $order ->billing_email,
        'phone' => $order ->billing_phone,
        'surl' => $redirect_url,
        'furl' => $redirect_url,
        'curl' => $redirect_url,
        'hash' => $hash,
        'pg' => 'NB'
        );

      $sudahtransfer_args_array = array();
      foreach($sudahtransfer_args as $key => $value){
        $sudahtransfer_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
      }

      return  '<form action="'.$this ->liveurl.'" method="post" id="sudahtransfer_payment_form">
      ' . implode('', $sudahtransfer_args_array) . '
      <input type="submit" class="button-alt" id="submit_SudahTransfer_payment_form" value="'.__('Pay via SudahTransfer', 'sudah-transfer').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'SudahTransfer').'</a>
      <script type="text/javascript">
        jQuery(function(){
          jQuery("body").block(
          {
            message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'sudah-transfer').'",
            overlayCSS:
            {
              background: "#fff",
              opacity: 0.6
            },
            css: {
              padding:        20,
              textAlign:      "center",
              color:          "#555",
              border:         "3px solid #aaa",
              backgroundColor:"#fff",
              cursor:         "wait",
              lineHeight:"32px"
            }
          });
          jQuery("#submit_SudahTransfer_payment_form").click();});</script>
        </form>';


      }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
      global $woocommerce;
      $order = new WC_Order( $order_id );

      /*print_r($order);
      exit;*/

      //print_r($order);
      /*return array('result' => 'success', 'redirect' => add_query_arg('order',
        $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
      );*/
      return array('result' => 'success', 'redirect' => add_query_arg('order',
        $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' )))));
    }

    /**
     * Check for valid  server callback
     **/
    function check_sudahtransfer_response(){
      global $woocommerce;
      if(isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])){
        $order_id_time = $_REQUEST['txnid'];
        $order_id = explode('_', $_REQUEST['txnid']);
        $order_id = (int)$order_id[0];
        if($order_id != ''){
          try{
            $order = new WC_Order( $order_id );
            $merchant_id = $_REQUEST['key'];
            $amount = $_REQUEST['Amount'];
            $hash = $_REQUEST['hash'];

            $status = $_REQUEST['status'];
            $productinfo = "Order $order_id";
            echo $hash;
            echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
            $checkhash = hash('sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}");
            $transauthorised = false;
            if($order ->status !=='completed'){
              if($hash == $checkhash)
              {

                $status = strtolower($status);

                if($status=="success"){
                  $transauthorised = true;
                  $this ->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                  $this ->msg['class'] = 'woocommerce_message';
                  if($order ->status == 'processing'){

                  }else{
                    $order -> payment_complete();
                    $order -> add_order_note('SudahTransfer payment successful<br/>Unnique Id from SudahTransfer: '.$_REQUEST['mihpayid']);
                    $order -> add_order_note($this->msg['message']);
                    $woocommerce ->cart -> empty_cart();
                  }
                }else if($status=="pending"){
                  $this ->msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
                  $this ->msg['class'] = 'woocommerce_message woocommerce_message_info';
                  $order -> add_order_note('SudahTransfer payment status is pending<br/>Unnique Id from SudahTransfer: '.$_REQUEST['mihpayid']);
                  $order -> add_order_note($this->msg['message']);
                  $order -> update_status('on-hold');
                  $woocommerce ->cart -> empty_cart();
                }
                else{
                  $this ->msg['class'] = 'woocommerce_error';
                  $this ->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                  $order -> add_order_note('Transaction Declined: '.$_REQUEST['Error']);
                                //Here you need to put in the routines for a failed
                                //transaction such as sending an email to customer
                                //setting database status etc etc
                }
              }else{
                $this ->msg['class'] = 'error';
                $this ->msg['message'] = "Security Error. Illegal access detected";

                            //Here you need to simply ignore this and dont need
                            //to perform any operation in this condition
              }
              if($transauthorised==false){
                $order -> update_status('failed');
                $order -> add_order_note('Failed');
                $order -> add_order_note($this->msg['message']);
              }
              add_action('the_content', array(&$this, 'showMessage'));
            }}catch(Exception $e){
                        // $errorOccurred = true;
              $msg = "Error";
            }

          }



        }

      }

      function showMessage($content){
        return '<div class="box '.$this ->msg['class'].'-box">'.$this ->msg['message'].'</div>'.$content;
      }
     // get all pages
      function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
          $prefix = '';
            // show indented child pages?
          if ($indent) {
            $has_parent = $page->post_parent;
            while($has_parent) {
              $prefix .=  ' - ';
              $next_page = get_page($has_parent);
              $has_parent = $next_page->post_parent;
            }
          }
            // add to page list array array
          $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
      }
    }
   /**
     * Add the Gateway to WooCommerce
     **/
   function woocommerce_add_sudahtransfer_gateway($methods) {
    $methods[] = 'WC_Sudahtransfer';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_sudahtransfer_gateway' );
}


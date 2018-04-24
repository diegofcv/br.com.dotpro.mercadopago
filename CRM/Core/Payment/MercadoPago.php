<?php

require_once 'CRM/Core/Payment.php';
require_once ('sdk-php/lib/mercadopago.php');

class CRM_Core_Payment_MercadoPago extends CRM_Core_Payment {
  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * mode of operation: live or test
   *
   * @var object
   * @static
   */
  static protected $_mode = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct( $mode, &$paymentProcessor ) {
    $this->_mode             = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName    = ts('MercadoPago');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton( $mode, &$paymentProcessor ) {
      $processorName = $paymentProcessor['name'];
      if (self::$_singleton[$processorName] === null ) {
          self::$_singleton[$processorName] = new br_com_dotpro_mercadopago( $mode, $paymentProcessor );
      }
      return self::$_singleton[$processorName];
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig( ) {
    $config = CRM_Core_Config::singleton();

    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('The "Bill To ID" is not set in the Administer CiviCRM Payment Processor.');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  /**
   * Sets appropriate parameters for checking out to UCM Payment Collection
   *
   * @param array $params  name value pair of contribution datat
   *
   * @return void
   * @access public
   *
   */
  function doTransferCheckout( &$params, $component ) {

            $config =& CRM_Core_Config::singleton( );

        if ( $component != 'contribute' && $component != 'event' ) {
            CRM_Core_Error::fatal( ts( 'Component is invalid' ) );
        }

        $notifyURL = 
            "reset|1||contactID|{$params['contactID']}" .
            "||contributionID|{$params['contributionID']}" .
            "||module|{$component}";


        if ( $component == 'event' ) {
            $notifyURL .= "||eventID|{$params['eventID']}||participantID|{$params['participantID']}";
        } else {
            $membershipID = CRM_Utils_Array::value( 'membershipID', $params );
            if ( $membershipID ) {
                $notifyURL .= "||membershipID|$membershipID";
            }
            
        }

        //Pega dados para o MercadoPagoPay
        $payParams =
            array( 'email_cobranca'     => $this->_paymentProcessor['user_name'],
                   'tipo'               => 'CP',
                   'moeda'              => 'BRL',
                   'item_id_1'          => 'CiviCRM',
                   'item_descr_1'       => 'CCRM-F- ' . utf8_decode($params['item_name']),
                   'item_quant_1'       => '1',
                   'item_valor_1'       => number_format(floatval($params['amount']), 2, "", ""),
                   'item_frete_1'       => '000',
                   'ref_transacao'      => $params['invoiceID']."|||".$notifyURL,
                   'cliente_nome'       => $params['first_name']." ".$params['last_name'],
//                   'cliente_cep'        => $zip,
//                   'cliente_end'        => $params['street_address'],
                   'cliente_num'        => '000',
                   'cliente_compl'      => ' . ',
                   'cliente_bairro'     => ' . ',
//                   'cliente_cidade'     => $params['city'],
//                   'cliente_uf'         => $stateName,
                   'cliente_pais'       => 'BRA',
                   'cliente_ddd'        => ' ',
                   'cliente_tel'        => ' ',
//                   'cliente_email'      => $params['email']
                );

        // add name and address if available, CRM-3130
        $otherVars = array( 'street_address' => 'cliente_end',
                            'city'           => 'cliente_cidade',
                            'state_province' => 'cliente_uf',
                            'postal_code'    => 'cliente_cep',
                            'email'          => 'cliente_email' 
						);

        foreach ( array_keys( $params ) as $p ) {
            // get the base name without the location type suffixed to it
            $parts = preg_split( '-', $p );
            $name  = count( $parts ) > 1 ? $parts[0] : $p;
            if ( isset( $otherVars[$name] ) ) {
                $value = $params[$p];
                if ( $value ) {
                    if ( $name == 'state_province' ) {
                        $stateName = CRM_Core_PseudoConstant::stateProvinceAbbreviation( $value );
                        $value     = $stateName;
                    }
                    //Remove símbolos indesejados do CEP (ex. 96840150)
					if ( $name == 'postal_code' ) {
                        $cep = preg_replace('[^0-9]', '', $value);
                        $value     = $cep;
                    }

                    // ensure value is not an array
                    // CRM-4174
                    if ( ! is_array( $value ) ) {
                        $payParams[$otherVars[$name]] = $value;
                    }
                }
            }
        }

        $uri = '';
        foreach ( $payParams as $key => $value ) {
            if ( $value === null ) {
                continue;
            }
            $value = urlencode( $value );
            $uri .= "&{$key}={$value}";
        }

        $uri = substr( $uri, 1 );
        $url = $this->_paymentProcessor['url_api'];
        $payURL = "{$url}?$uri";


$mp = new MP($this->_paymentProcessor['user_name'], $this->_paymentProcessor['password']);

$preference_data = array(
    "items" => array(
       array(
           "title" => $params['item_name'],

// "Pagamento de eventos PMI-DF",

           "quantity" => 1,
           "currency_id" => "BRL",
           "unit_price" => $params['amount'] 
       )
    ),
    "payer" => array(
       array(
           "name" => "xxxxxx",
           "surname" => "yyyyyyy",
           "email" => "duhduhd@duhdud.com",
       )
    )
);

$preference = $mp->create_preference ($preference_data);

        CRM_Utils_System::redirect( $preference['response']['init_point'] );
    
  }
}

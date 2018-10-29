<?php

namespace bawes\myfatoorah;

/**
 * Allows for processing of payments via MyFatoorah
 * For usage, need to set customer, add products, then request payment link
 *
 * Starting point for live:
 *
 * $pay = MyFatoorah::live($merchantCode, $username, $password);
 *
 * Starting point for testing:
 *
 * $pay = MyFatoorah::test();
 *
 * Then chain commands to generate your payment link:
 *
 * $pay->setPaymentMode(MyFatoorah::GATEWAY_ALL)
 *  ->setReturnUrl("https://google.com")
 *  ->setErrorReturnUrl("https://google.com")
 *  ->setCustomer($name, $email, $phone)
 *  ->setReferenceId()
 *  ->addProduct("iPhone", 9.750, 5)
 *  ->getPaymentLinkAndReference();
 *
 * $redirectLink = $pay['paymentUrl'];
 * $myfatoorahID = $pay['paymentRef'];
 *
 * @author Khalid Al-Mutawa <khalid@bawes.net>
 * @link http://www.bawes.net
 */
class MyFatoorah
{
    /**
     * @var string Generated link sends to MyFatoorah page with all payment methods
     */
    const GATEWAY_ALL = "BOTH";
    /**
     * @var string Generated link sends user directly to KNET portal
     */
    const GATEWAY_KNET = "KNET";
    /**
     * @var string Generated link sends user directly to VISA/MASTER portal
     */
    const GATEWAY_VISA_MASTERCARD = "VISA";
    /**
     * @var string Generated link sends user directly to Sadad Saudi portal
     */
    const GATEWAY_SAUDI_SADAD = "SADAD";
    /**
     * @var string Generated link sends user directly to BENEFIT BAHRAIN portal
     */
    const GATEWAY_BAHRAIN_BENEFIT = "BENEFITS";
    /**
     * @var string Generated link sends user directly to Qpay Qatar portal
     */
    const GATEWAY_QATAR_QPAY = "QPAY";
    /**
     * @var string Generated link sends user directly to UAE debit cards portal
     */
    const GATEWAY_UAECC = "UAECC";

    /**
     * @var integer Response from MyFatoorah when request for payment link is successful
     */
    const REQUEST_SUCCESSFUL = 0;

    /**
     * @var string MyFatoorah Merchant Username
     */
    public $merchantUsername;

    /**
     * @var string MyFatoorah Merchant Password
     */
    public $merchantPassword;

    /**
     * @var string MyFatoorah Merchant Code
     */
    public $merchantCode;

    /**
     * @var string Payment gateway url
     */
    public $gatewayUrl;

    /**
     * @var string Payment mode, as in which payment gateway we'll be generating links for
     * Available options:
     * - "BOTH" / MyFatoorah::GATEWAY_ALL
     * - "KNET" / MyFatoorah::GATEWAY_KNET
     * - "VISA" / MyFatoorah::GATEWAY_VISA_MASTERCARD
     * - "SADAD" / MyFatoorah::GATEWAY_SAUDI_SADAD
     * - "BENEFITS" / MyFatoorah::GATEWAY_BAHRAIN_BENEFIT
     * - "QPAY" / MyFatoorah::GATEWAY_QATAR_QPAY
     * - "UAECC" / MyFatoorah::GATEWAY_UAECC
     */
    private $_paymentMode;

    /**
     * @var string Return url once customer finishes payment
     */
    private $_customerReturnUrl;

    /**
     * @var string Return url once customer faces an error
     */
    private $_errorReturnUrl;

    /**
     * @var string Customer name
     */
    private $_customerName;

    /**
     * @var string Customer email
     */
    private $_customerEmail;

    /**
     * @var string Customer phone
     */
    private $_customerPhone;

    /**
     * @var string Reference id for the payment
     */
    private $_referenceId;

    /**
     * @var array Products, their quantities, and pricing we're charging
     */
    private $_products = [];

    /**
     * @var string the currency to bill in
     */
    private $_currency = "KWD";

    /**
     * @var array Parsing response from MyFatoorah using these codes
     */
    private $_responseCodeDetails = [
        0 => "Success",
        1000 => "Merchant ID not found",
        1001 => "Invalid username/password",
        1002 => "Transaction details not found",
        1003 => "Product details not found",
        1004 => "Customer details not found",
        1005 => "Reference details not found",
        9999 => "Unknown error",
        2009 => "Transaction Failed Messages (Not Captured, Voided, Cancelled, Failure)"
    ];

    /**
     * Starting point that returns an instance of this class for live usage
     * @param  string $merchantCode
     * @param  string $username
     * @param  string $password
     * @return static
     */
    public static function live($merchantCode, $username, $password)
    {
        $instance = new static;
        $instance->merchantUsername = $username;
        $instance->merchantPassword = $password;
        $instance->merchantCode = $merchantCode;
        $instance->gatewayUrl = "https://www.myfatoorah.com/pg/PayGatewayServiceV2.asmx";
        return $instance;
    }

    /**
     * Starting point that returns an instance of this class for test usage
     * @param  string $merchantCode
     * @param  string $username
     * @param  string $password
     * @return static
     */
    public static function test()
    {
        $instance = self::live("999999", "testapi@myfatoorah.com", "E55D0");
        $instance->gatewayUrl = "https://test.myfatoorah.com/pg/PayGatewayServiceV2.asmx";
        return $instance;
    }

    /**
     * Sets payment mode requested for generating MyFatoorah link
     * Preferably use one of the constants defined in this class
     * Eg: MyFatoorah::GATEWAY_ALL
     * @param string $mode
     * @return self
     */
    public function setPaymentMode($mode = self::GATEWAY_ALL)
    {
        $this->_paymentMode = $mode;

        return $this;
    }

    /**
     * Sets customer info for generating payment link
     * @param string $name
     * @param string $email
     * @param string $phone
     * @return self
     */
    public function setCustomer($name, $email, $phone)
    {
        $this->_customerName = $name;
        $this->_customerEmail = $email;
        $this->_customerPhone = $phone;

        return $this;
    }

    /**
     * Set the reference id for this payment
     * This should be a unique record referencing this payment attempt
     *
     * You can leave id param empty to use current time as reference
     *
     * @param string $id unique id for reference
     * @return self
     */
    public function setReferenceId($id = null)
    {
        // Set $id to current time for random unique value
        if(!$id) $id = time();

        $this->_referenceId = $id;

        return $this;
    }

    /**
     * Sets the return url that MyFatoorah will redirect to
     * @param string $url
     * @return self
     */
    public function setReturnUrl($url)
    {
        $this->_customerReturnUrl = $url;

        return $this;
    }

    /**
     * Sets the error return url that MyFatoorah will redirect to
     * when there is an error in processing
     * @param string $url
     * @return self
     */
    public function setErrorReturnUrl($url)
    {
        $this->_errorReturnUrl = $url;

        return $this;
    }

    /**
     * Adds product as process to payment request
     * @param string $productName
     * @param double $productPrice
     * @param integer $productQuantity
     * @return self
     */
    public function addProduct($productName, $productPrice, $productQuantity)
    {
        $this->_products[] = [
            'name' => $productName,
            'price' => floatval($productPrice),
            'quantity' => (int) $productQuantity
        ];

        return $this;
    }

    /**
     * Request payment link and its reference from MyFatoorah
     * return [
     *      'paymentUrl' => $paymentUrl,
     *      'paymentRef' => $referenceID
     * ];
     * @return array the payment url to redirect to and its ID on myfatoorah
     */
    public function getPaymentLinkAndReference()
    {
        $requiredAttributes[] = 'gatewayUrl';
        // Validate for payment mode and reference id available
        $requiredAttributes[] = '_paymentMode';
        $requiredAttributes[] = '_referenceId';
        // Validate that customer info is available
        $requiredAttributes[] = '_customerName';
        $requiredAttributes[] = '_customerEmail';
        $requiredAttributes[] = '_customerPhone';
        // Validate for success url
        $requiredAttributes[] = '_customerReturnUrl';
        // Validate for error url
        $requiredAttributes[] = '_errorReturnUrl';
        $this->_validateAttributes($requiredAttributes);

        if(count($this->_products) == 0){
            throw new \Exception('Product list cant be empty');
        }

        $totalPrice = 0;
        $productData = "";
        foreach($this->_products as $product){
            $totalPrice += $product['price'] * $product['quantity'];
            $productData .= '<ProductDC>';
            $productData .= '<product_name>' . htmlspecialchars($product['name']) . '</product_name>';
            $productData .= '<unitPrice>' . $product['price'] . '</unitPrice>';
            $productData .= '<qty>' . $product['quantity'] . '</qty>';
            $productData .= '</ProductDC>';
        }

        $post_string = '<?xml version="1.0" encoding="windows-1256"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
            <soap12:Body>
            <PaymentRequest xmlns="http://tempuri.org/">
              <req>
                <CustomerDC>
                  <Name>' . $this->_customerName . '</Name>
                  <Email>' . $this->_customerEmail . '</Email>
                  <Mobile>' . $this->_customerPhone . '</Mobile>
                </CustomerDC>
                <MerchantDC>
                  <merchant_code>' . $this->merchantCode . '</merchant_code>
                  <merchant_username>' . $this->merchantUsername . '</merchant_username>
                  <merchant_password>' . $this->merchantPassword . '</merchant_password>
                  <merchant_ReferenceID>' . $this->_referenceId . '</merchant_ReferenceID>
                  <ReturnURL>' . $this->_customerReturnUrl . '</ReturnURL>
                  <merchant_error_url>' . $this->_errorReturnUrl . '</merchant_error_url>
                </MerchantDC>
                <lstProductDC>' . $productData . '</lstProductDC>
                <totalDC>
                    <subtotal>' . $totalPrice . '</subtotal>
                </totalDC>
                <paymentModeDC>
                    <paymentMode>' . $this->_paymentMode . '</paymentMode>
                </paymentModeDC>
                <paymentCurrencyDC>
                  <paymentCurrrency>' . $this->_currency . '</paymentCurrrency>
                </paymentCurrencyDC>
              </req>
            </PaymentRequest>
          </soap12:Body>
        </soap12:Envelope>';

        $doc = $this->_makeApiCall($post_string);

        $responseCode = $doc->getElementsByTagName("ResponseCode")->item(0)->nodeValue;
        $responseMessage = $doc->getElementsByTagName("ResponseMessage")->item(0)->nodeValue;

        // On Failure
        if($responseCode != self::REQUEST_SUCCESSFUL){
            throw new \Exception("Error $responseCode: $responseMessage");
        }

        // On Success
        $referenceID = $doc->getElementsByTagName("referenceID")->item(0)->nodeValue;
        $paymentUrl = $doc->getElementsByTagName("paymentURL")->item(0)->nodeValue;

        return [
            'paymentUrl' => $paymentUrl,
            'paymentRef' => $referenceID
        ];
    }

    /**
     * Request order status from MyFatoorah by providing your reference id
     * which you initially received after generating the payment link via MyFatoorah::getPaymentLinkAndReference();
     *
     * Through order status you'll be able to know whether payment went through or not.
     * You can call this for a manual refresh or wait for a callback from MyFatoorah api after user pays or cancels payment process
     *
     * @param type $referenceId
     * @return array response
     */
    public function getOrderStatus($referenceId)
    {
        $post_string = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <GetOrderStatusRequest xmlns="http://tempuri.org/">
              <getOrderStatusRequestDC>
                <merchant_code>' . $this->merchantCode . '</merchant_code>
                <merchant_username>' . $this->merchantUsername . '</merchant_username>
                <merchant_password>' . $this->merchantPassword . '</merchant_password>
                <referenceID>' . $referenceId . '</referenceID>
              </getOrderStatusRequestDC>
            </GetOrderStatusRequest>
          </soap:Body>
        </soap:Envelope>';

        $doc = $this->_makeApiCall($post_string);

        $response = [];

        $response['responseCode'] = $doc->getElementsByTagName("ResponseCode")->item(0)->nodeValue;
        $response['responseMessage'] = $doc->getElementsByTagName("ResponseMessage")->item(0)->nodeValue;
        $response['result'] = $doc->getElementsByTagName("result")->item(0)->nodeValue; // If “CAPTURED” ONLY its successful rest all results are invalid

        // On Success
        if($response['responseCode'] == self::REQUEST_SUCCESSFUL){
            $response['orderId'] = $doc->getElementsByTagName("OrderID")->item(0)->nodeValue; // MyFatoorah Order ID
            $response['payTransactionId'] = $doc->getElementsByTagName("PayTxnID")->item(0)->nodeValue; // MyFatoorah Transaction ID

            $response['grossAmountPaid'] = $doc->getElementsByTagName("gross_amount")->item(0)->nodeValue; // The Gross Amount paid
            $response['netAmountToBeDeposited'] = $doc->getElementsByTagName("net_amount")->item(0)->nodeValue; // The Net Amount which will be deposited in the merchant account

            $response['payMode'] = $doc->getElementsByTagName("Paymode")->item(0)->nodeValue; // Payment Mode (KNET, Master or Visa)
        }

        // Append UDF values
        $response['udf1'] = $doc->getElementsByTagName("udf1")->item(0)->nodeValue;
        $response['udf2'] = $doc->getElementsByTagName("udf2")->item(0)->nodeValue;
        $response['udf3'] = $doc->getElementsByTagName("udf3")->item(0)->nodeValue;
        $response['udf4'] = $doc->getElementsByTagName("udf4")->item(0)->nodeValue;
        $response['udf5'] = $doc->getElementsByTagName("udf5")->item(0)->nodeValue;

        return $response;
    }

    /**
     * Validate that required attributes exist
     * @param string[] $requiredAttributes
     */
    private function _validateAttributes($requiredAttributes)
    {
        foreach ($requiredAttributes as $attribute) {
            if ($this->$attribute === null) {
                throw new \Exception(strtr('"{class}::{attribute}" cannot be empty.', [
                    '{class}' => static::class,
                    '{attribute}' => '$' . $attribute
                ]));
            }
        }
    }

    /**
     * Sends post string and returns api call results as a dom document for parsing
     * @param type $postString
     * @return \DOMDocument
     */
    private function _makeApiCall($postString)
    {
        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $this->gatewayUrl);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($soap_do, CURLOPT_TIMEOUT, 10);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap_do, CURLOPT_POST, true);
        curl_setopt($soap_do, CURLOPT_POSTFIELDS, $postString);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($postString)
        ));
        curl_setopt($soap_do, CURLOPT_USERPWD, $this->merchantUsername . ":" . $this->merchantPassword);
        $result = curl_exec($soap_do);
        $err = curl_error($soap_do);
        $file_contents = htmlspecialchars($result);
        curl_close($soap_do);

        $doc = new \DOMDocument();
        if($doc == null){
            throw new \Exception("Failed creating a new DOM document");
        }

        $doc->loadXML(html_entity_decode($file_contents));

        return $doc;
    }

}

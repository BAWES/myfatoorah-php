# PHP Library for the [MyFatoorah API](https://myfatoorah.readme.io/docs)

[![Latest Version](https://img.shields.io/github/tag/BAWES/myfatoorah-php.svg?style=flat-square&label=release)](https://github.com/BAWES/myfatoorah-php/tags)
[![Software License](https://img.shields.io/github/license/BAWES/myfatoorah-php.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/BAWES/myfatoorah-php.svg?style=flat-square)](https://packagist.org/packages/bawes/myfatoorah-php)


## Installation

The preferred way to install this library is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require bawes/myfatoorah-php
```
or add

```json
"bawes/myfatoorah-php": "^1.0"
```

to the require section of your application's `composer.json` file.


## Usage

First we need to decide which environment we want to use

### Step 1: Initialize based on the environment

#### Test Environment
```php
<?php
use bawes/myfatoorah/MyFatoorah;

$my = MyFatoorah::test();
```

#### Live Environment
```php
<?php
use bawes/myfatoorah/MyFatoorah;

$merchantCode = "[Your merchant code here]";
$username = "[Your merchant username here]";
$password = "[Your merchant password here]";
$my = MyFatoorah::live($merchantCode, $username, $password);
```

### Step 2: Request a payment link and redirect to it
```php
<?php
use bawes/myfatoorah/MyFatoorah;

$merchantCode = "[Your merchant code here]";
$username = "[Your merchant username here]";
$password = "[Your merchant password here]";
$my = MyFatoorah::live($merchantCode, $username, $password);

$my->setPaymentMode(MyFatoorah::GATEWAY_ALL)
->setReturnUrl("https://google.com")
->setErrorReturnUrl("https://google.com")
->setCustomer("Khalid", "customer@email.com", "97738271")
->setReferenceId() //Pass unique order number or leave empty to use time()
->addProduct("iPhone", 5.350, 3)
->addProduct("Samsung", 12.000, 1)
->getPaymentLinkAndReference();

$paymentUrl = $my['paymentUrl'];
$myfatoorahRefId = $my['paymentRef']; //good idea to store this for later status checks

// Redirect to payment url
header("Location: $paymentUrl");
die();

```

### Step 3: Request Order Status for Payment status confirmation

Use `MyFatoorah::getOrderStatus($referenceId)` to get an update on the status of the payment.
This is best called after receiving a callback from MyFatoorah's returnUrl or errorReturnUrl.
You can also manually call this function after an interval if you store the reference id locally.

#### Sample Order Status Request
```php
<?php
use bawes/myfatoorah/MyFatoorah;

// Example Ref ID
$myfatoorahRefId = $_GET['id'];

// Order status on Test environment
$orderStatus = MyFatoorah::test()
    ->getOrderStatus($myfatoorahRefId);

// Order status on Live environment
$merchantCode = "[Your merchant code here]";
$username = "[Your merchant username here]";
$password = "[Your merchant password here]";
$orderStatus = MyFatoorah::live($merchantCode, $username, $password)
    ->getOrderStatus($myfatoorahRefId);
```

#### Order Status Response (Success)

```php
<?php
$orderStatus = [
    'responseCode' => '0', //MyFatoorah::REQUEST_SUCCESSFUL
    'responseMessage' => 'SUCCESS',
    'result' => 'CAPTURED',

    // Successful payment fields
    'payMode' => 'KNET',
    'orderId' => '1085183',
    'payTransactionId' => '673386261283050',
    'grossAmountPaid' => '32.500',
    'netAmountToBeDeposited' => '32.300',

    // User defined fields
    'udf1' => '',
    'udf2' => '',
    'udf3' => '',
    'udf4' => '',
    'udf5' => ''
]
```

#### Order Status Response (Failure)

```php
<?php
$orderStatus = [
    'responseCode' => '2009',
    'responseMessage' => 'Transaction Failed Messages',
    'result' => 'Payment Server detected an error',

    // User defined fields
    'udf1' => '',
    'udf2' => '',
    'udf3' => '',
    'udf4' => '',
    'udf5' => ''
]
```

## Payment Gateways

Configure the gateway you wish to use by passing GATEWAY constants available on the `MyFatoorah` class to `MyFatoorah::setPaymentMode`.

* `MyFatoorah::GATEWAY_ALL` - Generated link sends to MyFatoorah page with all payment methods
* `MyFatoorah::GATEWAY_KNET` - Generated link sends user directly to KNET portal
* `MyFatoorah::GATEWAY_VISA_MASTERCARD` - Generated link sends user directly to VISA/MASTER portal
* `MyFatoorah::GATEWAY_SAUDI_SADAD` - Generated link sends user directly to Sadad Saudi portal
* `MyFatoorah::GATEWAY_BAHRAIN_BENEFIT` - Generated link sends user directly to BENEFIT BAHRAIN portal
* `MyFatoorah::GATEWAY_QATAR_QPAY` - Generated link sends user directly to Qpay Qatar portal
* `MyFatoorah::GATEWAY_UAECC` - Generated link sends user directly to UAE debit cards portal


Usage Example:
```php
<?php
use bawes/myfatoorah/MyFatoorah;

$my = MyFatoorah::live($merchantCode, $username, $password);
$my->setPaymentMode(MyFatoorah::GATEWAY_ALL)
```

## Test cards

These cards will only work if you initialize using `MyFatoorah::test()` environment.

### KNET

| Card Number   | Pin/Expiry     | Result  |
| ------------- |:-------------:| -----:|
| 8888880000000001     | anything      | CAPTURED |
| 8888880000000002     | anything      |   NOT CAPTURED |

### Benefits

| Card Number   | Expiry Date     | Pin  | Result  |
| ------------- |:-------------:| :-----:| --------:|
| 2222220123456789     | 12/27 |  1234    | CAPTURED |
| 7777770123456789     | 12/27 |  1234   |   NOT CAPTURED |
| 1111110123456789     | 12/27 |  1234   |   NOT CAPTURED |

### Visa

| Card Number   | Expiry Date     | CVV  |
| ------------- |:-------------:| -----:|
| 4005550000000001     | 05/18      | 123 |
| 4557012345678902     | 05/18      |   123 |

### Mastercard

| Card Number   | Expiry Date     | CVV  |
| ------------- |:-------------:| -----:|
| 5123456789012346     | 05/18      | 123 |
| 5313581000123430     | 05/18      | 123 |

### Amex

| Card Number   | Expiry Date     | Pin  |
| ------------- |:-------------:| -----:|
| 345678901234564     | 05/17      | 1234 |

### Sadad

| Payment Method   | Card Number   | Expiry Date     | CVV  |
| ------------- |:-------------:|:-------------:| -----:|
| Mastercard | 5271045423029111     | anything      | anything |
| Visa | 4012001037141112     | 01/2022      |  684 |


| Payment Method   | Account ID   | Password     |
| ------------- |:-------------:| -----:|
| Sadad account | arun123     | Aa123456      |

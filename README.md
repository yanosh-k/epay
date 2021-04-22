# Epay.php
A PHP class used for simplifying the work with the Bulgarian payment portal ePay.

# About ePay (excerpts from their website)
> The company is specialized in the making of payment systems, electronic trade and security of the information transmission through Internet. The company makes processing of payments with bank cards and of bank transactions in open webs. The main activity of the company is connected with operating with the payment systems ePay.bg®, ePayVoice® (payment by telephone), B-pay (payment at ATM).

# Usage

## Instlation

With composer:
1) Install package
```
composer require yanosh-k/epay
```
2) Require the composer autoloader:
```
<?php
    require_once('vendor/autoload.php'):
    $epay = new Yanoshk\Epay(array(/*...*/));
```

Manual:
1) Download or copy src/Epay.php inside your project
2) Require the library:
```
<?php
    require_once('your_project/Epay.php'):
    $epay = new Yanoshk\Epay(array(/*...*/));
```


## Initializing a transaction
Transactions are initialized by making a POST request to the ePay portal.
All the necessary data needed for completing the transaction (invoice ID, total amount of the transaction, etc.) is passed as POST parameters.
Usually a form with hidden input fields is used to guide the user to the payment portal while passing that information.

To create the form used for directing the user to the payment portal:
1. Initialize the Epay class with the merchant information you received after completing your merchant profile registration:

```
$epay = new Yanoshk\Epay(array(
    // the submit url for the form that will be generated. If not passed this defaults to the demo portal url.
    // After going live, you can change the default inside the class
    'submitURL'                 => 'https://demo.epay.bg/'
    // the client number (CIN) as seen in the merchant profile
    , 'clientNumber'              => 'D468695585'
    // the client secret used to create the HMAC
    , 'clientSecret'              => 'DSY6AJN6XOT7VEMV2H77LXVMC2D4B9X4JSDSCNEQ7WPYYJZ5JF1FR5JZS6P23M99'
    // the client email address - either this or the CIN should be passed
    , 'clientEmail'               => 'me@yanosh.net'
    // the expiration date of the transaction. After it the transaction will be automatically cancelled.
    // You can pass an exact date or a a number of seconds from now until the expiration
    // if you want to pass seconds you should append a plus sing to the passed string
    // 'transactionExpirationDate' => '+604800' - which is one week
    , 'transactionExpirationDate' => '25.05.2015 22:00:00'
));
```

2. Add the current transaction information:

```
 $epay->setTransactionData(array(
    // The url to which the user is redirected after successfully complete the transaction.
    // Redirecting the user to it doesn't mean that any money have been transferred.
    'successURL'  => 'http://example.com/successfull-payment'
    // The url to which the user is redirected if she cancels the payment process
    , 'cancelURL'   => 'http://example.com/canceled-payment'
    // The invoice ID from the merchant`s own store system
    , 'invocieID'   => '1003423'
    // The total amount of the purchase - the amount is in BGN
    , 'totalAmount' => 23.99
    // Optional field containing information about the purchase
    , 'description' => 'On-line ticket/s for live streaming'
    // If not passed this defaults to paylogin which means the user should use hers ePay profile
    // to complete the transaction
    // The possible values are: credit_paydirect, let_pay
    , 'paymentWindow' => 'paylogin'
));
```

3. Generate the form used to direct to user to payment portal.

```
// This function accepts only one parameter and that is the created form id attribute value
$from = $epay->createForm('epay-form');
```

The captured output from this function  should look like this:

```
<form id="epay-payment-form" method="POST" action="https://demo.epay.bg/"><input type="hidden" value="D468695585" name="MIN">
    <input type="hidden" name="PAGE" value="paylogin">
    <input type="hidden" name="INVOICE" value="1031">
    <input type="hidden" name="AMOUNT" value="15">
    <input type="hidden" name="EXP_TIME" value="29.08.2015 20:56:03">
    <input type="hidden" name="DESCR" value="Online ticket/s for live streaming">
    <input type="hidden" name="CURRENCY" value="BGN">
    <input type="hidden" name="LANG" value="bg">
    <input type="hidden" name="ENCODING" value="utf-8">
    <input type="hidden" name="URL_OK" value="http://example.com/successfull-payment">
    <input type="hidden" name="URL_CANCEL" value="http://example.com/canceled-payment">
    <input type="hidden" name="ENCODED" value="ASDFewfdsfh56134RQAWESFasdgfjFUOHmbvnBSQaSDFASF23434545DSFGGADSAfasdvzxcgdsfg">
    <input type="hidden" name="CHECKSUM" value="afdsf345tsfggjdyfu36443rasd">
</form>
```


4. Use any desired method to submit the form and direct the user to the payment portal.

## Capturing payments
The payment portal will notify a previously defined URL(set in the merchant profile) for the transaction status, once it was determined by the system.
The notification for a single invoice are send until a positive response is received from the URL, or until no positive response is recived for 30 days.

The scheme that ePay currently uses to send notification is as follows:

```
 1) 5 tries in an interval less than a minute
 2) 4 tries on every 15 minutes
 3) 5 tries on every hour
 4) 6 tries on every 3 hours
 5) 4 tries on every 6 hours
 6) 1 try a day
```


1. Parse the notification

```
// Only the client secret is needed for this operation
$epay = new Yanoshk\Epay(array('clientSecret' => 'DSY6AJN6XOT7VEMV2H77LXVMC2D4B9X4JSDSCNEQ7WPYYJZ5JF1FR5JZS6P23M99'));


// Will return an empty array if the passed that was incorrect
$data            = $epay->getNotificationData($_POST);


// Contained data (show for example only)
// This is the first of many (most times will be just one) transaction items
$transactionInfoFirst = isset($data[0]) ? $data[0] : array();
```

The `$transactionInfo` variable should contain information in the following format:

```
array(
    'INVOICE'  => '1234000234'
    'STATUS'   => 'PAID'
    'PAY_TIME' => '20150821113940'
    'STAN'     => '031173'
    'BCODE'    => '031173'
)
```



2. Generate an appropriate response

```
// One ore more transaction items might be present
$response = [];
foreach ($data as $transactionInfo) {
    $response[]   = Yanoshk\Epay::generateNotificationResponse([
            'error'     => empty($transactionInfo) ? 'Not valid CHECKSUM' : null,
            'status'    => $existsOrder ? 'OK' : 'ERR',
            'invoiceID' => $transactionInfo['INVOICE']
    ]);
}

echo implode('', $response);
```

An example response might look like this:
```
INVOICE=1027:STATUS=OK
INVOICE=5029:STATUS=OK
```
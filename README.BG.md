# Epay.php
PHP клас използван за улесняване на работата с портала за онлайн


# Използване
## Започване на транзакция
Транзакциите се инициализират чрез POST заявка към URL-то на портала за разплащане към ePay.
Необходимата информация за завършването на транзакцията се подава като POST параметри.
Това обикновено става като към уеб форма се добавят скрии полета със стойностите, които искаме да изпратим.

За да създадем формата за инициализиране на транзакцията:
1. Създаваме нова инстанция на Epay класа с информацията от търговския ни профил в ePay.

```
require_once('Epay.php');

$epay = new Epay(array(
    // the submit url for the form that will be generated. If not passed this defaults to the demo portal url.
    // After going live, you can change the default inside the class
    'submitURL'                 => 'https://devep2.datamax.bg/ep2/epay2_demo/'
    // the client number (CIN) as seen in the merchant profile
    , 'clientNumber'              => 'D468695585'
    // the client secret used to create the HMAC
    , 'clientSecret'              => 'DSY6AJN6XOT7VEMV2H77LXVMC2D4B9X4JSDSCNEQ7WPYYJZ5JF1FR5JZS6P23M99'
    // the client email address - either this or the CIN should be passed
    , 'clientEmail'               => 'me@yanosh.net'
    // the expiration date of the transaction. After it the transaction will be automatically canceled.
    // You can pass an exact date or a a number of seconds from now until the expiration
    // if you want to pass seconds you should append a plus sing to the passed string
    // 'transactionExpirationDate' => '+604800' - which is one week
    , 'transactionExpirationDate' => '25.05.2015 22:00:00'
));
```

2. Добавяме информацията за текущата транзакция

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

3. Генерираме формата използвана за редирект на потребителя към портала

```
// This function accepts only one parameter and that is the created form id attribute value
$from = $epay->createForm('epay-form');
```

Съдържанието на променливата `$from` изглежда по следния начин:

```
<form id="epay-payment-form" method="POST" action="https://devep2.datamax.bg/ep2/epay2_demo/"><input type="hidden" value="D468695585" name="MIN">
<input type="hidden" value="paylogin" name="PAGE">
<input type="hidden" value="1031" name="INVOICE">
<input type="hidden" value="15" name="AMOUNT">
<input type="hidden" value="29.08.2015 20:56:03" name="EXP_TIME">
<input type="hidden" value="Online ticket/s for live streaming" name="DESCR">
<input type="hidden" value="BGN" name="CURRENCY">
<input type="hidden" value="bg" name="LANG">
<input type="hidden" value="utf-8" name="ENCODING">
<input type="hidden" value="http://example.com/successfull-payment" name="URL_OK">
<input type="hidden" value="http://example.com/canceled-payment" name="URL_CANCEL">
<input type="hidden" value="ASDFewfdsfh56134RQAWESFasdgfjFUOHmbvnBSQaSDFASF23434545DSFGGADSAfasdvzxcgdsfg" name="ENCODED">
<input type="hidden" value="afdsf345tsfggjdyfu36443rasd" name="CHECKSUM">
</form>
```


4. Използваме най-предпочитания от нас начин за да събмитенм формата

## Получаване на информация за плащания
ePay автоматично изпраща заявки(известия) към предварително зададено URL в профила на търговеца със статус относно направените плащания.

Схема за изпращане на известия по дадена фактура
```
 1) 5 опита през < 1 минута
 2) 4 опита през 15 минути
 3) 5 опита през 1 час
 4) 6 опита през 3 часа
 5) 4 опита през 6 часа
 6) 1 опит на ден
```


1. Обработване на известията

```
require_once('Epay.php');

// Only the client secret is needed for this operation
$epay = new Epay(array('clientSecret' => 'DSY6AJN6XOT7VEMV2H77LXVMC2D4B9X4JSDSCNEQ7WPYYJZ5JF1FR5JZS6P23M99'));


// Will return an empty array if the passed that was incorrect
$data            = $epay->getNotificationData($_POST);


// The first item is contains the transaction status
$transactionInfo = isset($data[0]) ? $data[0] : array();
```

Променливата `$transactionInfo` има следното съдържание:

```
array(
    'INVOICE'  => '1234000234'
    'STATUS'   => 'PAID'
    'PAY_TIME' => '20150821113940'
    'STAN'     => '031173'
    'BCODE'    => '031173'
)
```



2. Генериране на отговор

```
$response = Epay::generateNotificationResponse(array(
    'error'     => empty($transactionInfo) ? 'Not valid CHECKSUM' : null
    , 'status'    => $someCondition ? 'OK' : 'ERR'
    , 'invoiceID' => $transactionInfo['INVOICE']
));
```

Примерен отговор генериран от функцията би изглеждал по следния начин:
```
INVOICE=1027:STATUS=OK
```
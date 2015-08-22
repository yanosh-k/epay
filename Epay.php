<?php

    class Epay
    {

        public $settings               = array();
        public static $defaultSettings = array(
            'submitURL'     => 'https://devep2.datamax.bg/ep2/epay2_demo/' // the url where the transaction data will be submited
            , 'clientNumber'  => null
            , 'clientSecret'  => null
            , 'clientEmail'   => null
            , 'merchantTitle' => null // the name of the organiztion or preson who is reciving the transaction
            , 'merchantIBAN'  => null // a valid IBAN
            , 'merchantBIC'   => null // a valid BIC
        );
        public $transactionData        = array(
            'paymentWindow'             => 'paylogin' // the type of payment window: paylogin, credit_paydirect
            , 'successURL'                => null
            , 'cancelURL'                 => null
            , 'transactionExpirationDate' => '+604800' // 7 days from now
            , 'invocieID'                 => null // the numeric ID that the trader is using to associate this transaction
            , 'totalAmount'               => null // the total amount of the transaction >= 0.01 (for example: 22, 22.80, 32.91)
            , 'description'               => null // description for the transaction
            , 'paymentReason'             => null // cyrillic letters, latin letters, digits, spaces, '-', ',', '.'
            , 'paymentType'               => null // six digits
            , 'language'                  => 'bg' // en, bg
            , 'currency'                  => 'BGN' // USD, BGN, EUR
            , 'encoding'                  => 'utf-8' // empty or utf-8 only
        );
        public $transactionFieldsMap   = array(
            'MIN'        => 'settings.clientNumber'
            , 'PAGE'       => 'transactionData.paymentWindow'
            , 'INVOICE'    => 'transactionData.invocieID'
            , 'AMOUNT'     => 'transactionData.totalAmount'
            , 'EXP_TIME'   => 'transactionData.transactionExpirationDate'
            , 'MERCHANT'   => 'settings.merchantTitle'
            , 'IBAN'       => 'settings.merchantIBAN'
            , 'BIC'        => 'settings.merchantBIC'
            , 'STATEMENT'  => 'transactionData.paymentReason'
            , 'PSTATEMENT' => 'transactionData.paymentType'
            , 'DESCR'      => 'transactionData.description'
            , 'CURRENCY'   => 'transactionData.currency'
            , 'LANG'       => 'transactionData.language'
            , 'ENCODING'   => 'transactionData.encoding'
            , 'URL_OK'     => 'transactionData.successURL'
            , 'URL_CANCEL' => 'transactionData.cancelURL'
        );

        /**
         * 
         * @param array $settings - the setting for the current Epay instance.
         * Those include the following:
         *      - submitURL
         *      - clientNumber
         *      - clientSecret
         *      - clientEmail
         *      - merchantTitle
         *      - merchantIBAN
         *      - merchantBIC
         */
        public function __construct($settings = array())
        {
            $this->settings = array_merge(self::$defaultSettings, $settings);
        }

        /**
         *  Sets the current transation data parameters
         * @param array $data - the transaction data
         */
        public function setTransactionData($data = array())
        {
            $this->transactionData = array_merge($this->transactionData, $data);
        }

        /**
         *  Generate the data array used for creating the checksum for the request
         * @return array
         */
        public function getDataArray()
        {
            $data = array();
            foreach ($this->transactionFieldsMap as $transField => $mappedField)
            {
                // Get the corresponding value
                $mappedFieldData = explode('.', $mappedField);
                $value           = $this->{$mappedFieldData[0]}[$mappedFieldData[1]];

                // Check if this is not the date field
                if ($transField === 'EXP_TIME')
                {
                    // Convert the dynamic passed time to solid date
                    $value = $this->getExpirationDate($value);
                }

                // Add the value to the data if the value is not empty
                if ($value)
                {
                    $data[$transField] = $value;
                }
            }

            return $data;
        }

        /**
         *  Generate the data string used for creating the checksum for the request
         * @return string
         */
        public function getDataString($encoded = true)
        {
            $data = '';
            foreach ($this->getDataArray() as $field => $value)
            {
                $data .= "$field=" . $value . "\n";
            }

            return $encoded ? base64_encode($data) : $data;
        }

        /**
         * Convert any dynamic exipiration date to a solid date
         * @param string $transactionExpirationDate
         * @return string
         */
        public function getExpirationDate($transactionExpirationDate)
        {
            return strpos($transactionExpirationDate, '+') === 0 ? date('d.m.Y H:i:s', time() + ltrim($transactionExpirationDate, '+')) : $transactionExpirationDate;
        }

        /**
         *  Generates the checksum for the transaction request
         * @return string
         */
        public function getChecksum()
        {
            return self::hmac('sha1', $this->getDataString(), $this->settings['clientSecret']);
        }

        /**
         * Creates the HTML for for used to submit and begin a transaction
         * @return string
         */
        public function createForm($formId = 'epay-payment-form')
        {
            $form = "<form action='{$this->settings['submitURL']}' method='POST' id='{$formId}'>";
            foreach ($this->getDataArray() as $field => $value)
            {
                $form .= "<input type='hidden' name='{$field}' value='{$value}' />\n";
            }

            $form .= "<input type='hidden' name='ENCODED' value='{$this->getDataString()}' />\n";
            $form .= "<input type='hidden' name='CHECKSUM' value='{$this->getChecksum()}' />\n";
            $form .= "</form>";

            return $form;
        }

        /**
         * Extracts notification data send from the ePay transaction notifier
         * 
         * @param array $notification The poseted data send to the application end point
         * responsible for recivining the notifications. This should be an array
         * in the following form:
         *      array(
         *          'encoded' => '...'
         *          , 'checksum' => '...'
         *      );
         * @return array()
         */
        public function getNotificationData($notification)
        {
            $encoded          = $notification['encoded'];
            $checksum         = $notification['checksum'];
            $hmac             = self::hmac('sha1', $encoded, $this->settings['clientSecret']);
            $notificationData = array();

            // Check if the received CHECKSUM is OK
            if ($hmac === $checksum)
            {
                $data  = base64_decode($encoded);
                $lines = explode("\n", $data);

                // Iterate trough all the lines in the encoded data
                foreach ($lines as $singleLine)
                {
                    // If any of the lines contains payment data add it to the notification data
                    $lineData = self::extractNotificationData($singleLine);
                    if ($lineData)
                    {
                        $notificationData[] = $lineData;
                    }
                }
            }

            return $notificationData;
        }

        /**
         * Extracts the parameters from a notification for a change in 
         *  a transaction status.
         * 
         * @param string $line
         * @return array
         */
        public static function extractNotificationData($line)
        {
            $data        = array();
            $matchedArgs = array();
            if (preg_match("/^INVOICE=(\d+):STATUS=(PAID|DENIED|EXPIRED)(:PAY_TIME=(\d+):STAN=(\d+):BCODE=([0-9a-zA-Z]+))?$/", $line, $matchedArgs))
            {
                $data['INVOICE']  = $matchedArgs[1];
                $data['STATUS']   = $matchedArgs[2];
                $data['PAY_TIME'] = $matchedArgs[4]; # if STATUS=PAID
                $data['STAN']     = $matchedArgs[5]; # if STATUS=PAID
                $data['BCODE']    = $matchedArgs[6]; # if STATUS=PAID
            }

            return $data;
        }

        public static function generateNotificationResponse($params)
        {
            $response = '';
            if (empty($params['error']))
            {
                $response .= "INVOICE={$params['invoiceID']}:STATUS={$params['status']}\n";
            }
            else
            {
                $response .= "ERR={$params['error']}\n";
            }

            return $response;
        }

        /**
         *  Generate a HMAC key for given data string
         * 
         * @param string $algo - the algorith used: md5 or sha1
         * @param string $data
         * @param string $passwd
         * @return string
         */
        public static function hmac($algo, $data, $passwd)
        {
            $p = array('md5' => 'H32', 'sha1' => 'H40');
            if (strlen($passwd) > 64)
            {
                $passwd = pack($p[$algo], $algo($passwd));
            }
            if (strlen($passwd) < 64)
            {
                $passwd = str_pad($passwd, 64, chr(0));
            }

            $ipad = substr($passwd, 0, 64) ^ str_repeat(chr(0x36), 64);
            $opad = substr($passwd, 0, 64) ^ str_repeat(chr(0x5C), 64);

            return($algo($opad . pack($p[$algo], $algo($ipad . $data))));
        }

    }
    
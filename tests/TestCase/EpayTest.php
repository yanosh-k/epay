<?php

declare(strict_types = 1);
use PHPUnit\Framework\TestCase;
use YanoshK\Epay;

final class EpayTest extends TestCase
{
    /**
     * Merchant information for a demo account
     * 
     * @var array
     */
    protected $epaySettings = [
        'submitURL'    => 'https://demo.epay.bg/',
        'clientNumber' => 'D468695585',
        'clientSecret' => 'DSY6AJN6XOT7VEMV2H77LXVMC2D4B9X4JSDSCNEQ7WPYYJZ5JF1FR5JZS6P23M99'
    ];
    /**
     * Instance of the YanoshK\Epay class
     * 
     * @var YanoshK\Epay
     */
    protected $epayInstance;

    ///////////////////////////////////////////////////////////////////////////
    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $this->epayInstance = new Epay($this->epaySettings);
    }

    ///////////////////////////////////////////////////////////////////////////
    public function testFormCreation()
    {
        // Inti epay with merchant data
        // Set transaction data
        $this->epayInstance->setTransactionData([
            'successURL'                => 'https://4acdc5a9389ffebd37ba15fe1b228b4f.m.pipedream.net',
            'cancelURL'                 => 'https://4acdc5a9389ffebd37ba15fe1b228b4f.m.pipedream.net',
            'invocieID'                 => time(),
            'totalAmount'               => number_format(22.59, 2, '.', ''),
            'description'               => 'Test transaction',
            'transactionExpirationDate' => date("d.m.Y H:i:s", strtotime("+1 day"))
        ]);

        // Generate the form
        $form = $this->epayInstance->createForm('epay_form', 'epay_form');

        // Do assertations
        $this->assertNotEmpty($form);
        $this->assertStringContainsString("<form action='", $form);
        $this->assertStringContainsString("name='MIN' value='{$this->epayInstance->getDataArray()['MIN']}'", $form);
        $this->assertStringContainsString("name='EXP_TIME' value='{$this->epayInstance->getDataArray()['EXP_TIME']}'", $form);
    }

    ///////////////////////////////////////////////////////////////////////////
    public function testNotificationParsingAndResponse()
    {
        // Prepare data for parsing (this is usually done by PHP when reaceving
        // POST request and the body is form-url-encoded)
        $inputDataArray  = [];
        $inputDataString = 'encoded=SU5WT0lDRT0xNjE5MDk5MTc2OlNUQVRVUz1QQUlEOlBB'
            . 'WV9USU1FPTIwMjEwNDIyMTY0ODA4OlNUQU49MDk0MzM4OkJDT0RFPTA5NDMzOApJT'
            . 'lZPSUNFPTE2MTkwOTg5MDg6U1RBVFVTPVBBSUQ6UEFZX1RJTUU9MjAyMTA0MjIxNj'
            . 'QyNTE6U1RBTj0wOTQzMzU6QkNPREU9MDk0MzM1Cg%3D%3D&checksum=361114074'
            . '41559cdb9d1aedafcdb2f4c93e3d017';

        // Parse the data into an array
        parse_str($inputDataString, $inputDataArray);

        // Do assertations
        $this->assertNotEmpty($inputDataArray);

        $notificationData = $this->epayInstance->getNotificationData($inputDataArray);
        $this->assertNotEmpty($notificationData);
        
        $this->assertEquals('PAID', $notificationData[0]['STATUS']);
        $this->assertEquals('PAID', $notificationData[1]['STATUS']);
        $this->assertEquals('20210422164808', $notificationData[0]['PAY_TIME']);
        $this->assertEquals('20210422164251', $notificationData[1]['PAY_TIME']);
        
        
        $response = [];
        foreach ($notificationData as $transactionInfo) {
            $response[]   = Epay::generateNotificationResponse([
                    'error'     => empty($transactionInfo) ? 'Not valid CHECKSUM' : null,
                    'status'    => 'OK',
                    'invoiceID' => $transactionInfo['INVOICE']
            ]);
        }
        $this->assertEquals("INVOICE=1619099176:STATUS=OK\nINVOICE=1619098908:STATUS=OK\n", implode('', $response));
    }
    
    

}

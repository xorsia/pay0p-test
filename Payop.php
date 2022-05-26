<?php

class Payop
{
    public $errors = []; //log

    public $secret_key = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9';
    public $public_key = 'Jsdi3j2kdj9csjJjer93jrkfsdkjcjJJKje2';
    public $jwt_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9';
    public $project_id = 'a36173a7823123';
    public $restMethodUrls = [
        'createInvoice' => 'https://payop.com/v1/invoices/create',
        'paymentMethods' => 'https://payop.com/v1/instrument-settings/payment-methods/available-for-application/',
        'createCardToken' => 'https://payop.com/v1/payment-tools/card-token/create',
        'checkInvoiceStatus' => 'https://payop.com/v1/checkout/check-invoice-status/',
    ];
    public $invoiceIdentifier = false;
    public $cardToken = false;

    public $order = [
        'id' => 123,
        'amount' => 15,
        'currency' => 'USD',
        'items' => [
            [
                'id' => 14,
                'name' => 'flag UK',
                'price' => 12
            ],
            [
                'id' => 54,
                'name' => 'flag DE',
                'price' => 3
            ]
        ],
        'description' => 'check',
    ];
    public $payer = [
        "email" => "test.user@payop.com",
        "phone" => "",
        "name" => "Oliver",
        "extraFields" => [
            'lang' => 'en',
        ]
    ];

    /**
     * Create Invoice
     */
    public function createInvoice()
    {
        $data = $this->generateInvoiceData();

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->restMethodUrls['createInvoice'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode(
                $data
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response);
        return $this->checkInvoiceResult($result);
    }

    public function checkInvoiceResult($result)
    {
        if (is_null($result) || isset($result->message)) {
            $this->errors[] = [
                'method' => 'createInvoice',
                'result' => $result,
            ];
            return false;
        } else {
            $this->invoiceIdentifier = $result->identifier;
            return true;
        }
    }

    /**
     * Generation Invoice Data
     */
    public function generateInvoiceData()
    {
        $payMethod = $this->getPaymentMethods();

        $data = [
            'publicKey' => $this->public_key,
            'order' => $this->order,
            'signature' => $this->generateSignature(),
            'payer' => $this->payer,
            'paymentMethod' => $payMethod, //добавить запрос на получения
            'language' => $this->payer['extraFields']['lang'],
            'resultUrl' => 'https://xor.site/result',
            "failPath" => "https://xor.site/fail",
        ];

        return $data;
    }

    public function generateSignature()
    {
        $data = [
            $this->order['amount'],
            $this->order['currency'],
            $this->order['id'],
            $this->secret_key,
        ];

        return hash('sha256', implode(':', $data)) . PHP_EOL;
    }

    public function getPaymentMethods()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->restMethodUrls['paymentMethods'].$this->project_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: Content-Type: application/json',
                "Authorization: Bearer $this->jwt_token"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response);
        if (is_null($result) || isset($result->message) || !isset($result->identifier)) {
            $this->errors[] = [
                'method' => 'paymentMethods',
                'result' => $result,
            ];
            return false;
        }
        return $result->identifier;
    }

    /**
     * create Card Token
     */
    public function createCardToken()
    {
        $data = [
          'invoiceIdentifier' => $this->invoiceIdentifier,
            'pan' => '5555555555554444',
            'expirationDate' => '12/23',
            'cvv' => '321',
            'holderName' => 'Xxx Xxx',
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->restMethodUrls['createCardToken'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode(
                $data
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response);

        if (is_null($result) || isset($result->message) || !isset($result->token)) {
            $this->errors[] = [
                'method' => 'createCardToken',
                'result' => $result,
            ];
            return false;
        }

        $this->cardToken = $result->token;
        return true;
    }

    /**
     * Check Invoice Status
     */
    public function checkInvoiceStatus()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->restMethodUrls['checkInvoiceStatus'].$this->invoiceIdentifier,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response);
        if (is_null($result) || isset($result->message) || !isset($result->status)) {
            $this->errors[] = [
                'method' => 'checkInvoiceStatus',
                'result' => $result,
            ];
            return false;
        }
        echo $result->url;
        return  false;
    }

    public function run()
    {
        $this->createInvoice();
        $this->createCardToken();
        $this->checkInvoiceStatus();
    }

    public function __destruct()
    {
        print_r($this->errors);
    }
}

$payOpObj = new Payop();
$payOpObj->run();
unset($payOpObj);


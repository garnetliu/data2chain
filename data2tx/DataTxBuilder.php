<?php

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Key\PublicKey;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Bitcoin\Key\PublicKeyFactory;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\Opcodes;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Tests\AbstractTestCase;
use BitWasp\Buffertools\Buffer;
use Mdanter\Ecc\Primitives\Point;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Key\PrivateKeyFactory;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Locktime;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;

include 'Wallet.php';
include 'APIServices.php';
require __DIR__ . "/../vendor/autoload.php";


class DataTxBuilder {
    // wallet variables
    private $passPhrase;
    private $wallet;
    private $nodeIndex;
    private $payerAddr;
    private $recipientAddr;
    private $payerPrivateKey;

    private $tx_fee;
    private $priority; # 0:fastest, 1:halfhour, 2:hour, 3: lowest
    private $recFeeJson;


    // output array
    private $data2Chain;
    private $outputs;
    // utxo
    private $outpoint;
    private $utxo_TxOut;
    //tx
    private $tx;
    // api links
    private $api;

    public function __construct($passPhrase, $priority=null, $wallet_index=null) {
        // wallet initialization
        $this->passPhrase = $passPhrase;
        $this->nodeIndex = $wallet_index ?: 0;
        $this->wallet = new Wallet($this->passPhrase, $this->nodeIndex, null, null);
        

        $this->priority = $priority ?: 0;
        // initalize api
        $this->api = new APIServices();
        $this->recFeeJson = $this->api->getRecFee();
    }

    public function publishData ($data2Chain) {
        // crepto info initialization
        $this->payerAddr = $this->wallet->getPayerAddress();
        $this->recipientAddr = $this->wallet->getRecipientAddress();
        $this->payerPrivateKey = $this->wallet->getCurrentPrivateKey();
        // chain data intialization
        $this->$data2Chain = $data2Chain;
        $this->makeOutPuts($data2Chain);
        $this->makeInput();
        $raw_tx = $this->makeTx()->getHex();

        $unhexTx = pack("H*", $raw_tx);
        $firstHash = hash('sha256', $unhexTx);
        $unhexTx = pack("H*", $firstHash);
        $txid = hash('sha256', $unhexTx);
        $formattedTxid = implode('', array_reverse(str_split($txid, 2)));
        $response = $this->api->pushTx($raw_tx);
        $code = $response[0];
        $msg = $response[1];


        if ($code == 200) {
            $this->wallet->setNextIndexes();
            $this->nodeIndex = $this->wallet->getNodeIndex();
            echo "<BR/> successfully submitted tx: ". $formattedTxid;
            // echo " <BR/>";print_r($response);
            // echo " <BR/>raw_tx: " . $raw_tx;
            // echo " <BR/>txid: ". $formattedTxid;
            // echo " <BR/>nodeIndex: ". $this->nodeIndex;
            return array($formattedTxid, $this->tx_fee);
        } else {
            echo " <BR/> "; print_r($response);
            return -1;
        }

    }

    
    public function makeOutPuts($data) {
    
        //------------------------- construct payment script
        $tx_size_byte = 243;
        $lowest_fee = 10000;

        // echo "fastestFee: ". ($recFeeJson["fastestFee"]*243). ", halfHourFee: ". ($recFeeJson["halfHourFee"]*243) . ", hourFee". ($recFeeJson["hourFee"]*243). PHP_EOL;
        // $fastestFee = $recFeeJson["fastestFee"]*$tx_size_byte;
        // $halfHourFee = $recFeeJson["halfHourFee"]*$tx_size_byte;
        // $hourFee = $recFeeJson["hourFee"]*$tx_size_byte;

        if ($this->recFeeJson == -1) {
            $fee = $lowest_fee;
        } else {
            if ($this->priority == 0) {
                $fee = ($this->recFeeJson["fastestFee"])*$tx_size_byte;
            } else if ($this->priority == 1) {
                $fee = $this->recFeeJson["halfHourFee"]*$tx_size_byte;
            } else if ($this->priority == 2) {
                $fee = $this->recFeeJson["hourFee"]*$tx_size_byte;
            } else {
                $fee = $lowest_fee;
            }            
        }
        echo "<BR/>tx fee is: ". $fee . PHP_EOL;
        $this->tx_fee = $fee;
        $paymentAddr = $this->payerAddr->getAddress();

        $pay_value = $this->api->getMaxCoinValue($paymentAddr) - $fee;

        $address = $this->recipientAddr;

        if ($pay_value <= 0) {
            echo "<BR/> balance is too low, need recharge greater than: ". (($pay_value*-1)* 0.00000001) ."btc to address: ". $paymentAddr. PHP_EOL;
            return -1;
        } else {
            echo "<BR/>". $paymentAddr. " paying: " . $pay_value . " to address: ". $address->getAddress();
        }
        
        $paymentScript = ScriptFactory::scriptPubKey()->payToAddress($address); // class: script
        $paymentTxOut = new TransactionOutput($pay_value, $paymentScript);

        //------------------------- construct op_return script

        $unhexHash = pack("H*", $data);

        $dataScript = ScriptFactory::create()
            ->op('OP_RETURN')
            ->push(new Buffer($unhexHash))
            ->getScript();

        $dataTxOut = new TransactionOutput(0, $dataScript);

        //------------------------- put output together

        $outputs = array($paymentTxOut, $dataTxOut);
        $this->outputs = $outputs;
        return $outputs;

    }

    public function makeInput() {
        //------------------------- construct input script

        // payer's keys
        $address = $this->payerAddr;
        $privateKey = $this->payerPrivateKey;

        $url = $this->utxo_url_blk;
        // echo "balance: ". $this->api->getMaxCoinValue($address, $url);
        $json = $this->api->getUtxoJson($address->getAddress());

        // get the coin with most value
        if ($json != -1) {
            $max_value = 0;
            foreach ($json['unspent_outputs'] as $utxo) {
                // get the coin with most value
                if ($utxo['value'] > $max_value) {
                    $txid = $utxo['tx_hash'];
                    $txid_big_endian = Buffer::hex($utxo['tx_hash_big_endian']);
                    $vout = $utxo['value'];
                    $vout_btc = $vout * 0.00000001; // in btc (wth???)
                    $outpoint = new OutPoint($txid_big_endian, $vout_btc);
                    // $outputScript = new Script(Buffer::hex($utxo['script']));
                    $outputScript = ScriptFactory::scriptPubKey()->payToPubKeyHash($privateKey->getPubKeyHash());
                    // $input = new TransactionInput($outpoint, $outputScript);
                    $txOut = new TransactionOutput($vout, $outputScript);
                    $max_value = $utxo['value'];
                }
            }
            // echo "input value: ". $vout;
            $this->outpoint = $outpoint;
            $this->utxo_TxOut = $txOut;
            return array($outpoint, $txOut);
        } else {

                echo "<BR/> no coin available" . PHP_EOL;
                return -1;
        }
    }

    public function makeTx() {
        //PHP Warning:  gmp_init(): Unable to convert variable to GMP
        $transaction = TransactionFactory::build()
            ->version(1)
            ->spendOutPoint($this->outpoint)
            ->outputs($this->outputs)
            ->get();

        $privateKey = $this->wallet->getCurrentPrivateKey();

        $signed = (new Signer($transaction))
            ->sign(0, $privateKey, $this->utxo_TxOut)
            ->get();

        $this->tx = $signed;
        return $signed;
    }
}


// $passPhrase = new Buffer('fengyu');
// $builder = new DataTxBuilder($passPhrase, 3, 0);
// echo '<pre>'; print_r($builder->makeOutPuts('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b8551234567890123456'));echo '</pre>';
// echo '<pre>'; print_r($builder->makeInput());echo '</pre>';

// $builder->makeOutPuts('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b8551234567890123456');
// $builder->makeInput();
// echo '<pre>'; print_r($builder->makeTx());echo '</pre>';
// echo "Witness serialized transaction: " . $builder->makeTx()->getHex() . PHP_EOL. PHP_EOL;

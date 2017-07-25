<?php

// usage

use BitWasp\Buffertools\Buffer;
include 'DataTxBuilder.php';
require __DIR__ . "/../vendor/autoload.php";

// master entropy passphrase
$passPhrase = new Buffer('fengyu');
// 0:fastest, 1:halfhour, 2:hour, 3: lowest
$priority = 3;
// start at wallet_index (default is 0), index will increment after each tx for anonymity
$wallet_index = 0;

// initialize the txbuilder
$builder = new DataTxBuilder($passPhrase, $priority, $wallet_index);

// data to be attached to tx
$hash_CertSn = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b8551234567890123456';

// return txid
$array = $builder->publishData($hash_CertSn);
$builder->publishData($hash_CertSn);
$builder->publishData($hash_CertSn);
$builder->publishData($hash_CertSn);
$builder->publishData($hash_CertSn);

$formattedTxid = $array[0];
$txFee = $array[1];

// check # of confirmations, if the response is -1, the tx is not on chain yet
$api = new APIServices();
$confirmationNum = $api->getConfirmationNum($formattedTxid);
echo "<BR/>confirmationNum: ". $confirmationNum;
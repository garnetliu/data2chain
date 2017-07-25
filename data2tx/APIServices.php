<?php

use BitWasp\Buffertools\Buffer;


class APIServices {


	public function __construct() {
	}

	public function pushTx($raw_tx) {
		$response = $this->pushTx_blkchn($raw_tx);
		if ($response[0] != 200) {
			$response = $this->putshTx_blockr($raw_tx);
		} 
		return $response;
	}

	private function pushTx_blkchn($raw_tx) {
		$url = 'http://blockchain.info/pushtx';
		$post_data = array ('tx' => $raw_tx);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// post数据
		curl_setopt($ch, CURLOPT_POST, 1);
		// post的变量
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return array($code, $response);
	}

	private function putshTx_blockr($raw_tx) {
		$url = 'http://btc.blockr.io/api/v1/tx/push';
		$post_data = json_encode(array ('hex' => $raw_tx));

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// post数据
		curl_setopt($ch, CURLOPT_POST, 1);
		// post的变量
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return array($code, $response);
	}

	// # cannot use yet
	// private function putshTx_insight($raw_tx) {
	// 	$url = 'https://insight.bitpay.com/api/tx/send';
	// 	$post_data = json_encode(array ('rawtx' => $raw_tx));

	// 	$ch = curl_init();
	// 	curl_setopt($ch, CURLOPT_URL, $url);
	// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	// 	// post数据
	// 	curl_setopt($ch, CURLOPT_POST, 1);
	// 	// post的变量
	// 	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	// 	$response = curl_exec($ch);
	// 	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	// 	curl_close($ch);
	// 	return array($code, $response);
	// }

	public function getUtxoJson($address) {
		$url = 'https://blockchain.info/unspent?';
		$vars = array('active' => $address, 'format' => 'json');
		$querystring = http_build_query($vars);
		$url = $url . $querystring;
		// using curl
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		// echo $response;
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
			$json = json_decode($response, true);
			curl_close($ch);
			return $json;

		} else {
			// curl_getinfo($ch, CURLINFO_HTTP_CODE); // 200
			// echo "<BR/>". $response; // json or invalid msg
			curl_close($ch);
			return -1;
		}

	}

	public function getMaxCoinValue($address) {
		$json = $this->getUtxoJson($address);
		if ($json != -1) {
			$max_value = 0;
			foreach ($json['unspent_outputs'] as $utxo) {
				// get the coin with most value
				if ($utxo['value'] > $max_value) {
					$max_value = $utxo['value'];
				}
			}
			return $max_value;
		} else {
				//echo "<BR/>no coin available" . PHP_EOL;
				return -1;
		}
	}

	public function getRecFee() {
		$url = 'https://bitcoinfees.21.co/api/v1/fees/recommended';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code == 200) {
			$json = json_decode($response, true);
			return $json;
		} else {
			return -1;
		}
		
	}

	public function getConfirmationNum($txid) {
		$url = 'https://blockchain.info/q/getblockcount';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		$latest = (int) $response;
		curl_close($ch);

		$url = 'https://blockchain.info/rawtx/'.$txid;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$json = json_decode($response, true);
		curl_close($ch);
		$height = $json['block_height'];
		if ($height) {
			return $latest - $height + 1;
		} else {
			#echo "not on chain yet";
			return -1;
		}
	}

}

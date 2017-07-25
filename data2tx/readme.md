源代码：

https://github.com/Bit-Wasp/BitWasp.git

路径：

BitWasp/data2tx/

目前支持功能：

1. $builder = new DataTxBuilder($passPhrase, $priority, $wallet_index);
	a. 使用一个总密码（$passPhrase），来生成一个hierarchical钱包
	b. $priority 可以根据预测confirmation的时间来设置交易费 0:fastest, 1:halfhour, 2:hour 前三个从api提取, 3: lowest （设定为10000 satoshi)
	c. 每个index对应着一个node，每个node使用它的地址和密钥来完成和下一个node(nodeIndex+1)的交易。可以初始化$wallet_index，交易便从$wallet_index开始

2. $builder->publishData($hash_CertSn);
	a. 提供的hash_CertSn作为string parameter
	b. 每次tx包含$hash_CertSn信息，并发布到网上等待确认
	c. 每次交易都会使用从$wallet_index开始的node，每次交易使用不同的地址
	d. 若钱包中当前node的余额小于交易费，会发布失败并以echo的形式提示余额不足（需充值多少以上）
	e. 若发布成功，publishData（）会返回array(txid, txFee)

3. $confirmationNum = $api->getConfirmationNum($formattedTxid);
	APIServices 可以检查确认次数
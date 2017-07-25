<?php

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

require __DIR__ . "/../vendor/autoload.php";

class Wallet
{
    private $master;
    private $currentNodeIndex;
    private $math; 
	private $network;



    public function __construct($entropy, $i=null, $net=null, $math=null)
    {
        $this->master = HierarchicalKeyFactory::fromEntropy($entropy);
        $this->network = $net ?: Bitcoin::getNetwork();
        $this->math = $math ?:Bitcoin::getMath();
        $this->currentNodeIndex = $i ?:0;
    }

    public function getExtendedPrivateKey() {
    	return $this->master->toExtendedPrivateKey($this->network);
    }

    public function getMasterPrivateKey() {
    	return $this->master->getPrivateKey();
    }

    public function getMasterPublicKey() {
    	return $this->master->getPublicKey();
    }

    public function getMasterWif() {
    	return $this->getMasterPrivateKey()->toWif($this->network);
    }

    // return an addr string
    public function getMasterAddr() {
    	return $this->getMasterPublicKey()->getAddress();
    }


    public function getNode($nodeIndex) {
    	return $this->master->deriveChild($nodeIndex);
    }

    public function getNodeIndex() {
    	return $this->currentNodeIndex;
    }


    // return an node object
    public function setNode($nodeIndex) {
    	$this->currentNodeIndex = $nodeIndex;
    	return $this->getNode($this->currentNodeIndex);
    }



    public function getAddress($node) {
    	return $node->getPublicKey()->getAddress();
    }

    public function getWif($node) {
    	return $node->getPrivateKey()->toWif($this->network);
    }

    public function getCurrentAddress() {
    	return $this->getAddress($this->getNode($this->currentNodeIndex));
    }

    public function getCurrentPrivateKey() {
    	return $this->getNode($this->currentNodeIndex)->getPrivateKey();
    }

	public function setNextIndexes() {
		$paymentIndex = $this->getNodeIndex();
		$this->setNode($this->getNodeIndex()+1);
		$receiveIndex = $this->getNodeIndex();
		return array($paymentIndex, $receiveIndex);
	}

	public function getCurrentIndexes() {
		$paymentIndex = $this->getNodeIndex();
		return array($paymentIndex, $paymentIndex+1);
	}

	public function getPayerAddress() {
		return $this->getCurrentAddress();
	}

	public function getRecipientAddress() {
		return $this->getAddress($this->getNode($this->getNodeIndex()+1));
	}



}


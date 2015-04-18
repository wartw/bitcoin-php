<?php

namespace BitWasp\Bitcoin\Script\Classifier;

use BitWasp\Bitcoin\Key\PublicKey;
use BitWasp\Bitcoin\Script\RedeemScript;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Buffertools\Buffer;

class OutputClassifier implements ScriptClassifierInterface
{

    /**
     * @var ScriptInterface
     */
    private $script;

    /**
     * @var array
     */
    private $evalScript;

    /**
     * @param ScriptInterface $script
     */
    public function __construct(ScriptInterface $script)
    {
        $this->script = $script;
        $this->evalScript = $script->getScriptParser()->parse();
    }

    /**
     * @return bool
     */
    public function isPayToPublicKey()
    {
        $script = $this->script->getBuffer()->getBinary();
        if (!$this->evalScript[0] instanceof Buffer) {
            return false;
        }

        if (strlen($script) == 35
            && $this->evalScript[0]->getSize() == 33
            && $this->evalScript[1] == 'OP_CHECKSIG'
            && (in_array(ord($script[1]), array(PublicKey::KEY_COMPRESSED_EVEN, PublicKey::KEY_COMPRESSED_ODD)))
        ) {
            return true;
        }

        if (strlen($script) == 67
            && $this->evalScript[0]->getSize() == 65
            && $this->evalScript[1] == 'OP_CHECKSIG'
            && bin2hex($script[1]) == PublicKey::KEY_UNCOMPRESSED
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPayToPublicKeyHash()
    {
        return (
            count($this->evalScript) == 5
            && $this->evalScript[0] == 'OP_DUP'
            && $this->evalScript[1] == 'OP_HASH160'
            && $this->evalScript[2]->getSize() == 20 // hex string
            && ($this->evalScript[3] == 'OP_EQUALVERIFY')
            && $this->evalScript[4] == 'OP_CHECKSIG'
        );
    }

    /**
     * @return bool
     */
    public function isPayToScriptHash()
    {
        return (
            strlen($this->script->getBuffer()->getBinary()) == 23
            && count($this->evalScript) == 3
            && $this->evalScript[0] == 'OP_HASH160'
            && $this->evalScript[1]->getSize() == 20
            && $this->evalScript[2] == 'OP_EQUAL'
        );
    }

    /**
     * @return bool
     */
    public function isMultisig()
    {
        // @TODO: hmm?
        $opcodes = $this->script->getOpcodes();
        $count = count($this->evalScript);
        return (
            $count >= 2
            //&& $opcodes->cmp($opcodes->getOpByName('OP_0'), $this->evalScript[0])
            && $this->evalScript[$count - 1] == 'OP_CHECKMULTISIG'
            //&&
        );
    }

    /**
     * @return string
     */
    public function classify()
    {
        if ($this->isPayToPublicKey()) {
            return self::PAYTOPUBKEY;
        } elseif ($this->isPayToPublicKeyHash()) {
            return self::PAYTOPUBKEYHASH;
        } elseif ($this->isPayToScriptHash()) {
            return self::PAYTOSCRIPTHASH;
        } elseif ($this->isMultisig()) {
            return self::MULTISIG;
        }

        return self::NONSTANDARD;
    }
}

<?php

namespace Omnipay\Etherscan\Message;

class JsonRpcResponse extends Response
{
    public function isSuccessful()
    {
        return isset($this->data->result) && !isset($this->data->error);
    }

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getMessage()
    {
        return $this->data->error->message;
    }
}

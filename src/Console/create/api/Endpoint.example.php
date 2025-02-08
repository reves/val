<?php

use Val\Api;

Final Class {name} Extends Api
{
    public function __invoke()
    {
        $this->onlyGET();

        return $this->respondData(['status' => 'OK']);
    }

    public function ping()
    {
        $this->onlyPOST();

        return $this->respondSuccess();
    }

}

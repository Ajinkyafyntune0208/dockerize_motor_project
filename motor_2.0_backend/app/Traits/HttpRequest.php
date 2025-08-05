<?php

/**
 * 
 */
trait HttpRequest
{
    protected $timeout = 300;

    protected $options = [];
    protected $headers = [];
    protected $basicAuth = '';

    protected function makeRequest($url, $body, $headers)
    {
        # code...
    }
}

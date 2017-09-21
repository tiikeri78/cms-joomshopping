<?php

namespace YaMoney\Client;

/**
 * Interface ApiClientInterface
 * @package YaMoney\Client
 */
interface ApiClientInterface
{
    /**
     * @param $path
     * @param $method
     * @param $queryParams
     * @param $httpBody
     * @param $headers
     * @return mixed
     */
    public function call($path, $method, $queryParams, $httpBody = null, $headers = array());
}
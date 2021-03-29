<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

use Psr\Http\Message\ResponseInterface;

/**
 * Description of SparqlRequestException
 *
 * @author zozlak
 */
class SparqlRequestException extends SparqlException {

    private $response;

    public function __construct(ResponseInterface $response) {
        $this->response = $response;
        parent::__construct($response->getReasonPhrase(), $response->getStatusCode());
    }

    public function getReqponse(): ResponseInterface {
        return $this->response;
    }
}

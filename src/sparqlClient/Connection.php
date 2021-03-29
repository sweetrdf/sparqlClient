<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use rdfInterface\DataFactory;

/**
 * Description of Connection
 *
 * @author zozlak
 */
class Connection {

    private ClientInterface $client;
    private DataFactory $dataFactory;

    public function __construct(ClientInterface $client,
                                DataFactory $dataFactory) {
        $this->client      = $client;
        $this->dataFactory = $dataFactory;
    }

    public function query(RequestInterface $request): Statement {
        $request  = $request->withHeader('Accept', 'application/json');
        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() !== 200) {
            throw new SparqlException("Query execution failed with HTTP " . $response->getStatusCode() . " " . $response->getReasonPhrase());
        }
        return new Statement($response, $this->dataFactory);
    }

    public function askQuery(RequestInterface $request): bool {
        $request  = $request->withHeader('Accept', 'application/json');
        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() !== 200) {
            throw new SparqlException("Query execution failed with HTTP " . $response->getStatusCode() . " " . $response->getReasonPhrase());
        }
        // https://www.w3.org/TR/sparql11-results-json/
        $body   = (string) $response->getBody();
        $result = json_decode($body);
        if (!isset($result->boolean)) {
            throw new SparqlException("Not an ASK query response: " . $body);
        }
        return $result->boolean;
    }
}

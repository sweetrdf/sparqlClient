<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use rdfInterface\DataFactory;
use GuzzleHttp\Psr7\Utils;

/**
 * SPARQL endpoint connection class assuming query can be passed as a "query"
 * parameter of a POST request (which isn't guaranteed by any SPARQL standard
 * but is a de facto standard for SPARQL database REST API implementations).
 *
 * @author zozlak
 */
class StandardConnection {

    private string $url;
    private Connection $connection;
    private RequestFactoryInterface $requestFactory;

    public function __construct(string $url, DataFactory $dataFactory,
                                ClientInterface $client = null,
                                RequestFactoryInterface $requestFactory = null) {
        $client               ??= new \GuzzleHttp\Client();
        $requestFactory       ??= new \Http\Factory\Guzzle\RequestFactory();
        $this->connection     = new Connection($client, $dataFactory);
        $this->url            = $url;
        $this->requestFactory = $requestFactory;
    }

    public function query(string $query): Statement {
        return $this->connection->query($this->getRequest($query));
    }

    public function askQuery(string $query): bool {
        return $this->connection->askQuery($this->getRequest($query));
    }

    private function getRequest(string $query): RequestInterface {
        return $this->requestFactory->createRequest('POST', $this->url)->
                withBody(Utils::streamFor(http_build_query(['query' => $query])))->
                withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
}

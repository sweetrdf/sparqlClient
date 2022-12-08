<?php

/*
 * The MIT License
 *
 * Copyright 2021 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace sparqlClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use rdfInterface\DataFactoryInterface as DataFactory;
use GuzzleHttp\Psr7\Utils;

/**
 * SPARQL endpoint connection class assuming query can be passed as a "query"
 * parameter of a POST request (which isn't guaranteed by any SPARQL standard
 * but is a de facto standard for SPARQL database REST API implementations).
 *
 * @author zozlak
 */
class StandardConnection implements SimpleConnectionInterface {

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

    public function prepare(string $query): PreparedStatement {
        return new PreparedStatement($query, $this);
    }

    private function getRequest(string $query): RequestInterface {
        return $this->requestFactory->createRequest('POST', $this->url)->
                withBody(Utils::streamFor(http_build_query(['query' => $query])))->
                withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
}

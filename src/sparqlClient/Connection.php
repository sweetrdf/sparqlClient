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
use Psr\Http\Message\RequestInterface;
use rdfInterface\DataFactoryInterface as DataFactory;

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

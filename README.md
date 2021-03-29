# sparqlClient

[![Latest Stable Version](https://poser.pugx.org/sweetrdf/sparql-client/v/stable)](https://packagist.org/packages/sweetrdf/sparql-client)
![Build status](https://github.com/sweetrdf/sparqlClient/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/sweetrdf/sparqlClient/badge.svg?branch=master)](https://coveralls.io/github/sweetrdf/sparqlClient?branch=master)
[![License](https://poser.pugx.org/sweetrdf/sparql-client/license)](https://packagist.org/packages/sweetrdf/sparql-client)


A SPARQL client library for the [rdfInterface](https://github.com/sweetrdf/rdfInterface/) ecosystem.

* It can work with any PSR-7 and PSR-15 compliant HTTP library.
* It can work with huge query results.\
  The response is parsed parsed as a stream and the parsing is done in a lazy way
  (the next row is parsed only when you try to read it).
  This assures iterating trough results without acumulating them in an array has neglectable memory footprint.

## Installation

* Obtain the [Composer](https://getcomposer.org)
* Run `composer require sweetrdf/sparql-client`
* Run `composer require guzzlehttp/guzzle` to install an HTTP client (you can use any client supporting PSR-7 and PSR-15).
* Run `composer require sweetrdf/quick-rdf` to install RDF terms factory 
  (you can use any terms factory compatible with the [rdfInterface](https://github.com/sweetrdf/rdfInterface/)).

## Automatically generated documentation

https://sweetrdf.github.io/sparqlClient/namespaces/sparqlclient.html

It's very incomplete but better than nothing.\
[RdfInterface](https://github.com/sweetrdf/rdfInterface/) documentation is included which provides documentation for terms (objects representing RDF named nodes, literals, etc.).

## Usage

```php
include 'vendor/autoload.php';

$httpClient  = new \GuzzleHttp\Client();
$dataFactory = new \simpleRdf\DataFactory();
$connection  = new \sparqlClient\Connection($gc, $df);
$query       = 'select * where {?a ?b ?c} limit 10';
$query       = new \GuzzleHttp\Psr7\Request('GET', 'https://arche-sparql.acdh-dev.oeaw.ac.at/sparql?query=' . rawurlencode($query));
$results     = $connection->query($query);
foreach ($results as $i) {
    print_r($i);
}
```

## FAQ

* **Why so much code is needed to make a simple SPARQL query?**\
  First, to allow you to choose HTTP client and RDF terms factory.
  Second, see the next question.
* **Why I have to prepare the HTTP request by hand?**\
  Because both [SPARQL specification](https://www.w3.org/TR/rdf-sparql-query/) 
  and [SPARQL results format specification](https://www.w3.org/TR/sparql11-results-json/)
  tell nothing about the SPARQL endpoint API.
  It's only an unwritten convention that SPARQL endpoints accept SELECT and ASK queries as a `query` GET/POST parameter.\
  Anyway this will be addressed in the future by providin a specialized connection class which will assume sane defaults and make running queries easier.
* **What about parameterized queries?**\
  They are expected to be added in the future.
* **What about integration of INSERT/UPDATE/DELETE queries with the \rdfInterface\Dataset?**\
  This is expected to be added in the future.

# sparqlClient

[![Latest Stable Version](https://poser.pugx.org/sweetrdf/sparql-client/v/stable)](https://packagist.org/packages/sweetrdf/sparql-client)
![Build status](https://github.com/sweetrdf/sparqlClient/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/sweetrdf/sparqlClient/badge.svg?branch=master)](https://coveralls.io/github/sweetrdf/sparqlClient?branch=master)
[![License](https://poser.pugx.org/sweetrdf/sparql-client/license)](https://packagist.org/packages/sweetrdf/sparql-client)


A SPARQL client library for the [rdfInterface](https://github.com/sweetrdf/rdfInterface/) ecosystem with the API inspired by the PDO.

* It can work with any PSR-17 / PSR-18 compliant HTTP libraries.
* It can work with huge query results.\
  The response is parsed in a lazy way as a stream (the next row is parsed only when you try to read it).
  This assures iterating trough results without acumulating them in an array has neglectable memory footprint.

## Installation

* Obtain the [Composer](https://getcomposer.org)
* Run `composer require sweetrdf/sparql-client`
* Run `composer require guzzlehttp/guzzle` to install an HTTP client (you can use any client supporting PSR-18).
* Run `composer require http-interop/http-factory-guzzle` to install PSR-17 bindinds for Guzzle (you can use any other PSR-17 implementation as well)
* Run `composer require sweetrdf/quick-rdf` to install RDF terms factory 
  (you can use any terms factory compatible with the [rdfInterface](https://github.com/sweetrdf/rdfInterface/)).

## Automatically generated documentation

https://sweetrdf.github.io/sparqlClient/namespaces/sparqlclient.html

It's very incomplete but better than nothing.\
[RdfInterface](https://github.com/sweetrdf/rdfInterface/) documentation is included which provides documentation for terms (objects representing RDF named nodes, literals, etc.).

## Usage

```php
include 'vendor/autoload.php';
$connection = new \sparqlClient\StandardConnection('https://query.wikidata.org/sparql', new \quickRdf\DataFactory());
$results    = $connection->query('select * where {?a ?b ?c} limit 10');
foreach ($results as $i) {
    print_r($i);
}
```

### Parameterized queries

The `StandardConnection` class provides a [PDO](https://www.php.net/manual/en/book.pdo.php)-like API for parameterized queries (aka prepared statements).
Parameterized queries:

* Are the right way to assure all named nodes/blank nodes/literals/quads/etc. in the SPARQL query are properly escaped.
* Protect against [SPARQL injections](https://www.google.com/search?q=sparql+injection).
* Don't provide any speedup (in contrary to SQL parameterized queries).

```php
include 'vendor/autoload.php';
$factory    = new \quickRdf\DataFactory();
$connection = new \sparqlClient\StandardConnection('https://query.wikidata.org/sparql', $factory);

// pass parameters using execute()
$query      = $connection->prepare('SELECT * WHERE {?a ? ?c . ?a :sf ?d .} LIMIT 10');
$query->execute([
    $factory->namedNode('http://creativecommons.org/ns#license'),
    'sf' => $factory->namedNode('http://schema.org/softwareVersion'),
]);
foreach ($query as $i) {
    print_r($i);
}

// bind parameter to a variable
$query = $connection->prepare('SELECT * WHERE {?a ? ?c .} LIMIT 2');
$value = $factory->namedNode('http://creativecommons.org/ns#license');
$query->bindParam(0, $value);
$query->execute();
foreach ($query as $i) {
    print_r($i);
}
$value = $factory->namedNode('http://schema.org/softwareVersion');
$query->execute();
foreach ($query as $i) {
    print_r($i);
}
```

There are some differences comparing to the PDO API:

* Mixing positional and named parameters in one query is allowed (see the example above).
* Mixing `bindParam()`/`bindValue()` and passing (some or all) parameters trough `execute()` is allowed.
  * The only constraint is that all parameters have to be set.
  * Parameters passed to `execute()` overwrite ones set with `bindParam()`/`bindValue()`.
* As strong typing is used parameter values have to be of type `rdfInterface\Term`.
  * It makes unnecessary specifying the type in `bindParam()`/`bindvalue()`.

### Advanced usage

* You may also provide any PSR-18 HTTP client and/or PSR-17 HTTP request factory to the `\sparqlClient\StandardConnection` constructor.
  E.g. let's assume your SPARQL endpoint requires authorization and you want to benefit from Guzzle connection allowing to set global request options:
  ```php
  $connection = new \sparqlClient\StandardConnection(
      'https://query.wikidata.org/sparql', 
      new \quickRdf\DataFactory(),
      new \GuzzleHttp\Client(['auth' => ['login', 'pswd']])
  );
  ```
* If your SPARQL endpoint doesn't follow the de facto standard of accepting the SPARQL query as the `query` request parameter,
  you may use the `\sparqlClient\Connection` class which takes PSR-7 requests instead of a query string and allows you to use any specific HTTP request you need.

## FAQ

* **What about integration of INSERT/UPDATE/DELETE queries with the \rdfInterface\Dataset or \rdfInterface\QuadIterator?**\
  Will be added in the future.

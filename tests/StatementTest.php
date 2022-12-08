<?php

/*
 * The MIT License
 *
 * Copyright 2022 zozlak.
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

use PDO;
use Psr\Http\Message\MessageInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use quickRdf\DataFactory;
use zozlak\RdfConstants;
use rdfInterface\TermInterface;
use rdfInterface\QuadInterface;

/**
 * Description of TestStatement
 *
 * @author zozlak
 */
class StatementTest extends \PHPUnit\Framework\TestCase {

    private function testWikidata(string $format): void {
        $df     = new DataFactory();
        $client = new Client();
        $query  = rawurlencode('select * where {?a ?b ?c} limit 5');
        $url    = 'https://query.wikidata.org/sparql?query=' . $query;

        $headers  = ['Accept' => $format];
        $request  = new Request('GET', $url, $headers);
        $response = $client->sendRequest($request);
        $stmt     = new Statement($response, $df);

        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($r);
        $this->assertCount(3, $r);
        $this->assertArrayHasKey('a', $r);
        $this->assertArrayHasKey('b', $r);
        $this->assertArrayHasKey('c', $r);
        if ($format === 'text/csv') {
            $this->assertIsString($r['a']);
            $this->assertIsString($r['b']);
            $this->assertIsString($r['c']);
        } else {
            $this->assertInstanceOf(TermInterface::class, $r['a']);
            $this->assertInstanceOf(TermInterface::class, $r['b']);
            $this->assertInstanceOf(TermInterface::class, $r['c']);
        }

        $r = $stmt->fetch(PDO::FETCH_NUM);
        $this->assertIsArray($r);
        $this->assertCount(3, $r);
        $this->assertArrayHasKey(0, $r);
        $this->assertArrayHasKey(1, $r);
        $this->assertArrayHasKey(2, $r);

        $r = $stmt->fetch(PDO::FETCH_BOTH);
        $this->assertIsArray($r);
        $this->assertCount(6, $r);
        $this->assertArrayHasKey('a', $r);
        $this->assertArrayHasKey('b', $r);
        $this->assertArrayHasKey('c', $r);
        $this->assertArrayHasKey(0, $r);
        $this->assertArrayHasKey(1, $r);
        $this->assertArrayHasKey(2, $r);
        if ($format === 'text/csv') {
            $this->assertEquals($r[0], $r['a']);
            $this->assertEquals($r[1], $r['b']);
            $this->assertEquals($r[2], $r['c']);
        } else {
            $this->assertTrue($r[0]->equals($r['a']));
            $this->assertTrue($r[1]->equals($r['b']));
            $this->assertTrue($r[2]->equals($r['c']));
        }

        $r = $stmt->fetch(PDO::FETCH_OBJ);
        $this->assertIsObject($r);
        $this->assertCount(3, get_object_vars($r));
        $this->assertObjectHasAttribute('a', $r);
        $this->assertObjectHasAttribute('b', $r);
        $this->assertObjectHasAttribute('c', $r);

        $r = $stmt->fetch(PDO::FETCH_COLUMN);
        if ($format === 'text/csv') {
            $this->assertIsString($r);
        } else {
            $this->assertInstanceOf(TermInterface::class, $r);
        }
        $this->assertFalse($stmt->fetch());
    }

    public function testWikidataJson(): void {
        $this->testWikidata('application/sparql-results+json');
    }

    public function testWikidataCsv(): void {
        $this->testWikidata('text/csv');
    }

    public function testWikidataXml(): void {
        $this->testWikidata('application/sparql-results+xml');
    }

    public function testAsk(): void {
        $df     = new DataFactory();
        $client = new Client();

        $query   = rawurlencode('ask {?a ?b ?c}');
        $url     = 'https://query.wikidata.org/sparql?query=' . $query;
        // wikidata doesn't implement text/csv for ASK queries
        $formats = ['application/sparql-results+json', 'application/sparql-results+xml'];
        foreach ($formats as $format) {
            $headers = ['Accept' => $format];
            $request = new Request('GET', $url, $headers);

            $response = $client->sendRequest($request);
            $stmt     = new Statement($response, $df);
            $this->assertTrue($stmt->fetchColumn(), $format);

            $response = $client->sendRequest($request);
            $stmt     = new Statement($response, $df);
            $r        = $stmt->fetch(PDO::FETCH_OBJ);
            $this->assertIsObject($r);
            $this->assertTrue($r->boolean, $format);
        }

        $query   = rawurlencode('ask {<http://foo.bar> ?b ?c}');
        $url     = 'https://query.wikidata.org/sparql?query=' . $query;
        // wikidata doesn't implement text/csv for ASK queries
        $formats = ['application/sparql-results+json', 'application/sparql-results+xml'];
        foreach ($formats as $format) {
            $headers = ['Accept' => $format];
            $request = new Request('GET', $url, $headers);

            $response = $client->sendRequest($request);
            $stmt     = new Statement($response, $df);
            $this->assertFalse($stmt->fetchColumn(), $format);

            $response = $client->sendRequest($request);
            $stmt     = new Statement($response, $df);
            $r        = $stmt->fetch(PDO::FETCH_OBJ);
            $this->assertIsObject($r);
            $this->assertFalse($r->boolean, $format);
        }
    }

    public function testJsonStar(): void {
        $df       = new DataFactory();
        $uri1     = ['type' => 'uri', 'value' => 'http://foo'];
        $uri2     = ['type' => 'uri', 'value' => 'http://bar'];
        $uri3     = ['type' => 'uri', 'value' => 'http://baz'];
        $lit1     = ['type' => 'literal', 'value' => 'foo', 'xml:lang' => 'en'];
        $lit2     = ['type' => 'literal', 'value' => 'bar', 'datatype' => 'https://type'];
        $blnk     = ['type' => 'bnode', 'value' => '_:blank'];
        $body     = json_encode([
            'head'    => ['vars' => ['a', 'b']],
            'results' => [
                'bindings' => [
                    [
                        'a' => $uri1,
                        'b' => [
                            'type'      => 'triple',
                            'subject'   => $uri2,
                            'predicate' => $uri3,
                            'object'    => $lit1,
                        ]
                    ],
                    [
                        'a' => [
                            'type'      => 'triple',
                            'subject'   => $uri3,
                            'predicate' => $uri1,
                            'object'    => [
                                'type'      => 'triple',
                                'subject'   => $uri1,
                                'predicate' => $uri2,
                                'object'    => $blnk
                            ],
                        ],
                        "b" => $lit2,
                    ]
                ]
            ]
        ]);
        $response = new Response(200, ['Content-Type' => 'application/sparql-results+json'], $body);
        $stmt     = new Statement($response, $df);
        $r1       = $stmt->fetch(PDO::FETCH_ASSOC);
        $r2       = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertFalse($stmt->fetch());
        $this->assertEquals($uri1['value'], $r1['a']->getValue());
        $this->assertInstanceOf(QuadInterface::class, $r1['b']);
        $this->assertEquals($uri2['value'], $r1['b']->getSubject()->getValue());
        $this->assertEquals($uri3['value'], $r1['b']->getPredicate()->getValue());
        /**
         * @var \rdfInterface\LiteralInterface $v
         */
        $v        = $r1['b']->getObject();
        $this->assertEquals($lit1['value'], $v->getValue());
        $this->assertEquals($lit1['xml:lang'], $v->getLang());
        $this->assertEquals(RdfConstants::RDF_LANG_STRING, $v->getDatatype());
        $this->assertInstanceOf(QuadInterface::class, $r1['b']);
        $this->assertEquals($uri3['value'], $r2['a']->getSubject()->getValue());
        $this->assertEquals($uri1['value'], $r2['a']->getPredicate()->getValue());
        $this->assertInstanceOf(QuadInterface::class, $r2['a']->getObject());
        $this->assertEquals($uri1['value'], $r2['a']->getObject()->getSubject()->getValue());
        $this->assertEquals($uri2['value'], $r2['a']->getObject()->getPredicate()->getValue());
        $this->assertEquals($blnk['value'], $r2['a']->getObject()->getObject()->getValue());
        $this->assertEquals($lit2['value'], $r2['b']->getValue());
        $this->assertEquals($lit2['datatype'], $r2['b']->getDatatype());
    }

    public function testXmlStar(): void {
        $df       = new DataFactory();
        $uri1     = '<uri>http://foo</uri>';
        $uri2     = '<uri>http://bar</uri>';
        $uri3     = '<uri>http://baz</uri>';
        $lit1     = '<literal xml:lang="en">foo</literal>';
        $lit2     = '<literal datatype="https://type">bar</literal>';
        $blnk     = '<bnode>_:blank</bnode>';
        $body     = "<?xml version='1.0' encoding='UTF-8'?>
<sparql xmlns='http://www.w3.org/2005/sparql-results#'>
  <head>
    <variable name='a'/>
    <variable name='b'/>
  </head>
  <results>
    <result>
      <binding name='a'>$uri1</binding>
      <binding name='b'>
        <triple>
          <subject>$uri2</subject>
          <predicate>$uri3</predicate>
          <object>$lit1</object>
        </triple>
      </binding>
    </result>
    <result>
      <binding name='a'>
        <triple>
          <subject>$uri3</subject>
          <predicate>$uri1</predicate>
          <object>
            <triple>
              <subject>$uri1</subject>
              <predicate>$uri2</predicate>
              <object>$blnk</object>
            </triple>
          </object>
        </triple>
      </binding>
      <binding name='b'>$lit2</binding>
    </result>
  </results>
</sparql>";
        $response = new Response(200, ['Content-Type' => 'application/sparql-results+xml'], $body);
        $stmt     = new Statement($response, $df);
        $r1       = $stmt->fetch(PDO::FETCH_ASSOC);
        $r2       = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertFalse($stmt->fetch());
        $this->assertEquals('http://foo', $r1['a']->getValue());
        $this->assertInstanceOf(QuadInterface::class, $r1['b']);
        $this->assertEquals('http://bar', $r1['b']->getSubject()->getValue());
        $this->assertEquals('http://baz', $r1['b']->getPredicate()->getValue());
        /**
         * @var \rdfInterface\LiteralInterface $v
         */
        $v        = $r1['b']->getObject();
        $this->assertEquals('foo', $v->getValue());
        $this->assertEquals('en', $v->getLang());
        $this->assertEquals(RdfConstants::RDF_LANG_STRING, $v->getDatatype());
        $this->assertInstanceOf(QuadInterface::class, $r1['b']);
        $this->assertEquals('http://baz', $r2['a']->getSubject()->getValue());
        $this->assertEquals('http://foo', $r2['a']->getPredicate()->getValue());
        $this->assertInstanceOf(QuadInterface::class, $r2['a']->getObject());
        $this->assertEquals('http://foo', $r2['a']->getObject()->getSubject()->getValue());
        $this->assertEquals('http://bar', $r2['a']->getObject()->getPredicate()->getValue());
        $this->assertEquals('_:blank', $r2['a']->getObject()->getObject()->getValue());
        $this->assertEquals('bar', $r2['b']->getValue());
        $this->assertEquals('https://type', $r2['b']->getDatatype());
    }

    public function testFetchMode(): void {
        $df       = new DataFactory();
        $c        = new StandardConnection('https://query.wikidata.org/sparql', $df);
        $query    = rawurlencode('select ?a ?b ?c where {?a ?b ?c} limit 10');
        $client   = new Client();
        $request  = new Request('GET', 'https://query.wikidata.org/sparql?query=' . $query);
        $response = $client->sendRequest($request);
        $body     = $response->getBody();

        $body->seek(0);
        $s  = new Statement($response, $df);
        $d1 = iterator_to_array($s);

        $body->seek(0);
        $s  = new Statement($response, $df);
        $d2 = $s->fetchAll();

        $body->seek(0);
        $s   = new Statement($response, $df);
        $d3  = [];
        while ($row = $s->fetch()) {
            $d3[] = $row;
        }

        $body->seek(0);
        $s  = new Statement($response, $df);
        $c1 = $s->fetchAll(PDO::FETCH_COLUMN);

        $body->seek(0);
        $s   = new Statement($response, $df);
        $c2  = [];
        while ($col = $s->fetchColumn()) {
            $c2[] = $col;
        }

        $this->assertCount(10, $d1);
        $this->assertCount(10, $d2);
        $this->assertCount(10, $d3);
        $this->assertCount(10, $c1);
        $this->assertCount(10, $c2);
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($d1[$i]->a->equals($d2[$i]->a));
            $this->assertTrue($d1[$i]->b->equals($d2[$i]->b));
            $this->assertTrue($d1[$i]->c->equals($d2[$i]->c));

            $this->assertTrue($d1[$i]->a->equals($d3[$i]->a));
            $this->assertTrue($d1[$i]->b->equals($d3[$i]->b));
            $this->assertTrue($d1[$i]->c->equals($d3[$i]->c));

            $this->assertTrue($d1[$i]->a->equals($c1[$i]));
            $this->assertTrue($d1[$i]->a->equals($c2[$i]));
        }
    }
}

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

use PDO;
use quickRdf\DataFactory;
use rdfInterface\TermInterface;

/**
 * Description of IntegrationTest
 *
 * @author zozlak
 */
class SparqlTest extends \PHPUnit\Framework\TestCase {

    public function testSelect(): void {
        $df    = new DataFactory();
        $c     = new StandardConnection('https://query.wikidata.org/sparql', $df);
        $query = 'select ?a ?b ?c where {?a ?b ?c} limit 10';

        $s  = $c->query($query);
        $d = iterator_to_array($s);
        $this->assertCount(10, $d);
        foreach ($d as $i) {
            $this->assertInstanceOf(TermInterface::class, $i->a);
            $this->assertInstanceOf(TermInterface::class, $i->b);
            $this->assertInstanceOf(TermInterface::class, $i->c);
        }
    }

    public function testAsk(): void {
        $df    = new DataFactory();
        $c     = new StandardConnection('https://query.wikidata.org/sparql', $df);
        $query = 'ask {<http://foo> <http://bar> <http://baz>}';
        $this->assertFalse($c->query($query)->fetchColumn());
    }

    public function testPrepare(): void {
        $df = new DataFactory();
        $c  = new StandardConnection('https://query.wikidata.org/sparql', $df);
        $q  = $c->prepare('SELECT * WHERE {?a ? ?c . ?a :sf ?d . ?a ? ?e} LIMIT 1');
        $q->execute([
            $df->namedNode('http://creativecommons.org/ns#license'),
            $df->namedNode('http://schema.org/dateModified'),
            'sf' => $df->namedNode('http://schema.org/softwareVersion'),
        ]);
        $r  = $q->fetchAll();
        $this->assertCount(1, $r);
    }

    public function testExceptions(): void {
        $df = new DataFactory();
        $c  = new StandardConnection('https://query.wikidata.org/sparql', $df);

        $query = 'wrongQuery';
        try {
            $c->query($query);
            $this->assertTrue(false);
        } catch (SparqlException $ex) {
            $this->assertStringStartsWith('Query execution failed with HTTP', $ex->getMessage());
        }

        $query = "";
        try {
            $q = $c->prepare($query);
            $q->execute([$df->literal('foo')]);
        } catch (SparqlException $ex) {
            $this->assertEquals('Unknown parameter 0', $ex->getMessage());
        }

        $query = "?";
        try {
            $q = $c->prepare($query);
            $q->execute();
        } catch (SparqlException $ex) {
            $this->assertEquals('Parameter 0 value missing', $ex->getMessage());
        }
        
        $query = "? :p ";
        try {
            $q = $c->prepare($query);
            $q->execute([$df->literal('foo')]);
        } catch (SparqlException $ex) {
            $this->assertEquals('Parameter p value missing', $ex->getMessage());
        }
    }
}

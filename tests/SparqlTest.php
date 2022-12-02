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
        $d1 = iterator_to_array($s);

        $s  = $c->query($query);
        $d2 = $s->fetchAll();

        $s   = $c->query($query);
        $d3  = [];
        while ($row = $s->fetch()) {
            $d3[] = $row;
        }

        $s  = $c->query($query);
        $c1 = $s->fetchAll(PDO::FETCH_COLUMN);

        $s   = $c->query($query);
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

    public function testAsk(): void {
        $df    = new DataFactory();
        $c     = new StandardConnection('https://query.wikidata.org/sparql', $df);
        $query = 'ask {<http://foo> <http://bar> <http://baz>}';
        $this->assertFalse($c->askQuery($query));
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

        $query = 'select ?a ?b ?c where {?a ?b ?c} limit 10';
        try {
            $c->askQuery($query);
            $this->assertTrue(false);
        } catch (SparqlException $ex) {
            $this->assertStringStartsWith('Not an ASK query response', $ex->getMessage());
        }

        $query = 'wrongQuery';
        try {
            $c->askQuery($query);
            $this->assertTrue(false);
        } catch (SparqlException $ex) {
            $this->assertStringStartsWith('Query execution failed with HTTP', $ex->getMessage());
        }
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

<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
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
        $gc  = new \GuzzleHttp\Client();
        $df  = new DataFactory();
        $c   = new Connection($gc, $df);
        $url = 'https://arche-sparql.acdh-dev.oeaw.ac.at/sparql?query=select%20%3Fa%20%3Fb%20%3Fc%20where%20%7B%3Fa%20%3Fb%20%3Fc%7D%20limit%2010';

        $s  = $c->query(new \GuzzleHttp\Psr7\Request('GET', $url));
        $d1 = iterator_to_array($s);

        $s  = $c->query(new \GuzzleHttp\Psr7\Request('GET', $url));
        $d2 = $s->fetchAll();

        $s   = $c->query(new \GuzzleHttp\Psr7\Request('GET', $url));
        $d3  = [];
        while ($row = $s->fetch()) {
            $d3[] = $row;
        }

        $s  = $c->query(new \GuzzleHttp\Psr7\Request('GET', $url));
        $c1 = $s->fetchAll(PDO::FETCH_COLUMN);

        $s   = $c->query(new \GuzzleHttp\Psr7\Request('GET', $url));
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
        $gc  = new \GuzzleHttp\Client();
        $df  = new DataFactory();
        $c   = new Connection($gc, $df);
        $url = 'https://arche-sparql.acdh-dev.oeaw.ac.at/sparql?query=ask%20%7B%3Chttp%3A%2F%2Ffoo%3E%20%3Chttp%3A%2F%2Fbar%3E%20%3Chttp%3A%2F%2Fbaz%3E%7D';
        $this->assertFalse($c->askQuery(new \GuzzleHttp\Psr7\Request('GET', $url)));

        $url = 'https://arche-sparql.acdh-dev.oeaw.ac.at/sparql?query=select%20%3Fa%20%3Fb%20%3Fc%20where%20%7B%3Fa%20%3Fb%20%3Fc%7D%20limit%2010';
        try {
            $c->askQuery(new \GuzzleHttp\Psr7\Request('GET', $url));
            $this->assertTrue(false);
        } catch (SparqlException $ex) {
            $this->assertStringStartsWith('Not an ASK query response', $ex->getMessage());
        }
    }
}

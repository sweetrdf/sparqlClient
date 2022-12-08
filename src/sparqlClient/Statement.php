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

use Generator;
use Iterator;
use IteratorAggregate;
use PDO;
use SplQueue;
use SplStack;
use stdClass;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use JsonMachine\Items;
use JsonMachine\Exception\PathNotFoundException;
use rdfInterface\DataFactoryInterface as DataFactory;
use rdfInterface\TermInterface as iTerm;

/**
 * For results data reference see https://www.w3.org/TR/sparql11-results-json/
 * and https://w3c.github.io/rdf-star/cg-spec/editors_draft.html#sparql-star-query-results-json-format
 *
 * @author zozlak
 */
class Statement implements StatementInterface {

    const JSON_LAN       = 'xml:lang';
    const XML_LANG       = 'http://www.w3.org/XML/1998/namespacelang';
    const XML_NMSP       = 'http://www.w3.org/2005/sparql-results#';
    const XML_CHUNK_SIZE = 1048576; // 1MB

    static public function getAcceptHeader(): string {
        // CSV/TSV as the last as it skips information on the variable type, lang and datatype
        return 'application/sparql-results+json, ' .
            'application/json;q=0.9, ' .
            'application/sparql-results+xml;q=0.8, ' .
            'application/xml;q=0.7, ' .
            'text/xml;q=0.6, ' .
            'text/csv;q=0.5, ' .
            'text/tab-separated-values;q=0.4, ' .
            '*/*;q=0.1';
    }

    private MessageInterface $response;
    private DataFactory $dataFactory;
    private Iterator $iterator;
    private object | null $currentRow = null;
    private ?int $rowNumber;

    public function __construct(MessageInterface $response,
                                DataFactory $dataFactory) {
        $this->response    = $response;
        $this->dataFactory = $dataFactory;

        $contentType    = $response->getHeader('Content-Type')[0] ?? '';
        $contentType    = trim(explode(';', $contentType)[0]); // skip encoding, etc. suplementary info
        $body           = $response->getBody();
        $this->iterator = match ($contentType) {
            'application/sparql-results+json' => $this->getJsonIterator($body),
            'application/json' => $this->getJsonIterator($body),
            'text/csv' => $this->getSepIterator($body, ','),
            'text/tab-separated-values' => $this->getSepIterator($body, "\t"),
            'application/sparql-results+xml' => $this->getXmlIterator($body),
            'application/xml' => $this->getXmlIterator($body),
            'text/xml' => $this->getXmlIterator($body),
            default => $this->getJsonIterator($body),
        };
    }

    public function fetchAll(int $fetchStyle = PDO::FETCH_OBJ): array {
        $ret = [];
        while ($row = $this->fetch($fetchStyle)) {
            $ret[] = $row;
        }
        return $ret;
    }

    public function fetch(int $fetchStyle = PDO::FETCH_OBJ): object | array | string | false {
        $this->next();
        if ($this->valid()) {
            $row = $this->current();
            switch ($fetchStyle) {
                case PDO::FETCH_OBJ:
                    return $row;
                case PDO::FETCH_ASSOC:
                    return (array) $row;
                case PDO::FETCH_NUM:
                    return array_values((array) $row);
                case PDO::FETCH_BOTH:
                    $row = (array) $row;
                    return array_merge($row, array_values($row));
                case PDO::FETCH_COLUMN:
                    $row = get_object_vars($row);
                    return $row[array_keys($row)[0]];
                default:
                    throw new \BadMethodCallException('Unsupported fetchStyle parameter value');
            }
        } else {
            return false;
        }
    }

    public function fetchColumn(): object | bool {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    private function makeTerm(object $var): iTerm {
        // https://www.w3.org/TR/sparql11-results-json/
        // https://w3c.github.io/rdf-star/cg-spec/2021-02-18.html#sparql-star-query-results-json-format
        switch ($var->type) {
            case 'uri':
                return $this->dataFactory::namedNode($var->value);
            case 'literal':
                return $this->dataFactory::literal($var->value, $var->{self::JSON_LAN} ?? null, $var->datatype ?? null);
            case 'bnode':
                return $this->dataFactory::blankNode($var->value);
            case 'triple':
                $sbj  = $this->makeTerm($var->subject);
                $pred = $this->makeTerm($var->predicate);
                $obj  = $this->makeTerm($var->object);
                return $this->dataFactory::quad($sbj, $pred, $obj);
            default:
                throw new SparqlException("Unsupported return variable type $var->type");
        }
    }

    public function current(): mixed {
        return $this->currentRow;
    }

    public function key(): int | null {
        return $this->rowNumber;
    }

    public function next(): void {
        if ($this->iterator->valid()) {
            $row = $this->iterator->current();
            if (is_object($row)) {
                // SELECT query
                $this->rowNumber = $this->iterator->key();
                foreach ($row as $p => &$pv) {
                    if (is_object($pv)) {
                        $pv = $this->makeTerm($pv);
                    }
                }
                unset($pv);
                $this->currentRow = $row;
            } else {
                // ASK query
                $this->rowNumber  = 0;
                $this->currentRow = (object) ['boolean' => (bool) $row];
            }
            try {
                $this->iterator->next();
            } catch (PathNotFoundException $e) {
                // RFC 6901 the JsonMachine library bases on requires an error
                // to be thrown when not all pointers are found but we by definition
                // search for mutually exclusive pointers being results of ASK 
                // and SELECT queries so we will always get this exception at the
                // end of parsing
            }
        } else {
            $this->currentRow = null;
            $this->rowNumber  = null;
        }
    }

    public function rewind(): void {
        $this->next();
    }

    public function valid(): bool {
        return $this->currentRow !== null;
    }

    public function bindParam(int | string $parameter, iTerm &$variable): bool {
        return false;
    }

    public function bindValue(int | string $parameter, iTerm $value): bool {
        return false;
    }

    public function execute(array $parameters = []): bool {
        return false;
    }

    /**
     * Parses a JSON SPARQL response into rows.
     * 
     * Reference:
     * - https://www.w3.org/TR/2013/REC-sparql11-results-json-20130321/
     * - https://w3c.github.io/rdf-star/cg-spec/editors_draft.html#sparql-star-query-results-json-format
     * 
     * @param StreamInterface $body
     * @return Iterator
     */
    private function getJsonIterator(StreamInterface $body): Iterator {
        $handle   = \GuzzleHttp\Psr7\StreamWrapper::getResource($body);
        $options  = ['pointer' => ['/results/bindings', '/boolean']];
        $parser   = Items::fromStream($handle, $options);
        $iterator = $parser->getIterator();
        while ($iterator instanceof IteratorAggregate) {
            $iterator = $iterator->getIterator();
        }
        return $iterator;
    }

    /**
     * Parses a CSV/TSV SPARQL response into rows.
     * 
     * Reference:
     * - https://www.w3.org/TR/2013/REC-sparql11-results-csv-tsv-20130321/
     * 
     * @param StreamInterface $body
     * @param string $separator
     * @return Generator
     */
    private function getSepIterator(StreamInterface $body, string $separator): Generator {
        $handle = \GuzzleHttp\Psr7\StreamWrapper::getResource($body);
        $header = fgetcsv($handle, null, $separator, '"', '"');
        while (!feof($handle)) {
            $row = fgetcsv($handle, null, $separator, '"', '"');
            yield (object) array_combine($header, $row);
        }
    }

    /**
     * Parses an XML SPARQL response into rows.
     * 
     * Reference:
     * - https://www.w3.org/TR/2013/REC-rdf-sparql-XMLres-20130321/
     * - https://w3c.github.io/rdf-star/cg-spec/editors_draft.html#sparql-star-query-results-xml-format
     * 
     * @param StreamInterface $body
     * @return Generator
     */
    private function getXmlIterator(StreamInterface $body): Generator {
        $rows    = new SplQueue();
        $row     = null;
        $binding = null;
        $value   = null;
        $values  = null;

        $parser  = xml_parser_create_ns('UTF-8', '');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_TAGSTART, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        $onStart = function ($x, $tag, $attr) use (&$row, &$binding, &$value,
                                                   &$values) {
            $tag = str_replace(self::XML_NMSP, '', $tag);
            switch ($tag) {
                case 'boolean':
                    $value                   = new BindingElement();
                    break;
                case 'result':
                    $row                     = new stdClass();
                    break;
                case 'binding':
                    $binding                 = new BindingElement();
                    $binding->name           = $attr['name'];
                    $value                   = $binding;
                    $values                  = new SplStack();
                    break;
                case 'bnode':
                case 'literal':
                case 'uri':
                case 'triple':
                    $value->type             = $tag;
                    $value->{self::JSON_LAN} = $attr[self::XML_LANG] ?? '';
                    $value->datatype         = $attr['datatype'] ?? '';
                    break;
                case 'subject':
                case 'predicate':
                case 'object':
                    $values->push($value);
                    $value->$tag             = new BindingElement();
                    $value                   = $value->$tag;
                    break;
            }
        };
        $onEnd = function ($x, $tag) use ($rows, &$values, &$row, &$binding,
                                          &$value) {
            $tag = str_replace(self::XML_NMSP, '', $tag);
            switch ($tag) {
                case 'boolean':
                    $rows->enqueue($value->value === 'true');
                    break;
                case 'result':
                    $rows->enqueue($row);
                    $row                   = null;
                    break;
                case 'binding':
                    $row->{$binding->name} = $binding;
                    $binding               = null;
                    break;
                case 'subject':
                case 'predicate':
                case 'object':
                    $value                 = $values->pop();
                    break;
            }
        };
        xml_set_element_handler($parser, $onStart, $onEnd);
        $onCData = function ($x, $data) use (&$value) {
            if (is_object($value) && empty($value->value)) {
                $value->value = $data;
            }
        };
        xml_set_character_data_handler($parser, $onCData);
        while (!$body->eof()) {
            while (!$rows->isEmpty()) {
                yield $rows->dequeue();
            }
            xml_parse($parser, $body->read(self::XML_CHUNK_SIZE) ?: '', false);
        }
        xml_parse($parser, '', true);
        while (!$rows->isEmpty()) {
            yield $rows->dequeue();
        }
    }
}

class BindingElement {

    public string $name;
    public string $type;
    public string $value;
    public BindingElement $subject;
    public BindingElement $predicate;
    public BindingElement $object;
}

<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

use Iterator;
use IteratorAggregate;
use PDO;
use Psr\Http\Message\MessageInterface;
use JsonMachine\JsonMachine;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use rdfInterface\DataFactory;
use rdfInterface\Term as iTerm;

/**
 * For results data reference see https://www.w3.org/TR/sparql11-results-json/
 * and https://w3c.github.io/rdf-star/cg-spec/editors_draft.html#sparql-star-query-results-json-format
 *
 * @author zozlak
 */
class Statement implements StatementInterface {

    const LANG_PROP = 'xml:lang';

    private MessageInterface $response;
    private DataFactory $dataFactory;
    private Iterator $iterator;
    private object | null $currentRow = null;
    private ?int $rowNumber;

    public function __construct(MessageInterface $response,
                                DataFactory $dataFactory) {
        $this->response    = $response;
        $this->dataFactory = $dataFactory;

        $stream   = \GuzzleHttp\Psr7\StreamWrapper::getResource($response->getBody());
        $parser   = JsonMachine::fromStream($stream, '/results/bindings', new ExtJsonDecoder(false));
        $iterator = $parser->getIterator();
        while ($iterator instanceof IteratorAggregate) {
            $iterator = $iterator->getIterator();
        }
        $this->iterator = $iterator;
    }

    public function fetchAll(int $fetchStyle = PDO::FETCH_OBJ): array {
        $ret = [];
        while ($row = $this->fetch($fetchStyle)) {
            $ret[] = $row;
        }
        return $ret;
    }

    public function fetch(int $fetchStyle = PDO::FETCH_OBJ): object | array | false {
        $this->next();
        if ($this->valid()) {
            $row = $this->current();
            switch ($fetchStyle) {
                case PDO::FETCH_OBJ:
                    return $row;
                case PDO::FETCH_ASSOC:
                    return (array) $row;
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

    public function fetchColumn(): object | false {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    private function makeTerm(object $var): iTerm {
        // https://www.w3.org/TR/sparql11-results-json/
        // https://w3c.github.io/rdf-star/cg-spec/2021-02-18.html#sparql-star-query-results-json-format
        switch ($var->type) {
            case 'uri':
                return $this->dataFactory::namedNode($var->value);
            case 'literal':
                return $this->dataFactory::literal($var->value, $var->{self::LANG_PROP} ?? null, $var->datatype ?? null);
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
            $this->rowNumber = $this->iterator->key();
            $row             = $this->iterator->current();
            foreach ($row as $p => $pv) {
                $row->$p = $this->makeTerm($pv);
            }
            $this->currentRow = $row;
            $this->iterator->next();
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
}

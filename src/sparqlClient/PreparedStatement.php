<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

use PDO;
use rdfInterface\Term as iTerm;
use rdfInterface\Quad as iQuad;
use rdfHelpers\NtriplesUtil;

/**
 * Description of PreparedStatement
 *
 * @author zozlak
 */
class PreparedStatement implements StatementInterface {

    const PH_POSIT = '(?>\\G|[^\\\\])([?])(?>$|[^a-zA-Z0-9_\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{02FF}\x{0370}-\x{037D}\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}])';
    const PH_NAMED = '(?>\\G|[^\\\\]):([a-zA-Z0-9]+)';

    private Statement $statement;

    /**
     * 
     * @var array<iTerm>
     */
    private array $param = [];

    public function __construct(private string $query,
                                private SimpleConnectionInterface $connection,
                                private bool $ask = false) {
        $this->parseQuery();
    }

    public function bindParam(int | string $parameter, iTerm &$variable): bool {
        if (!array_key_exists($parameter, $this->param)) {
            throw new SparqlException("Unknown parameter $parameter");
        }
        $this->param[$parameter] = $variable;
        return true;
    }

    public function bindValue(int | string $parameter, iTerm $value): bool {
        if (!array_key_exists($parameter, $this->param)) {
            throw new SparqlException("Unknown parameter $parameter");
        }
        $this->param[$parameter] = $value;
        return true;
    }

    public function execute(array $parameters = []): bool {
        foreach ($parameters as $n => $v) {
            $this->bindValue($n, $v);
        }
        $query = $this->getQuery();
        if ($this->ask) {
            $this->statement = $this->connection->askQuery($query);
        } else {
            $this->statement = $this->connection->query($query);
        }
        return true;
    }

    public function fetch(int $fetchStyle = PDO::FETCH_OBJ): object | array | false {
        return $this->statement->fetch($fetchStyle);
    }

    public function fetchAll(int $fetchStyle = PDO::FETCH_OBJ): array {
        return $this->statement->fetchAll($fetchStyle);
    }

    public function fetchColumn(): object | false {
        return $this->statement->fetchColumn();
    }

    public function current(): mixed {
        return $this->statement->current();
    }

    public function key(): \scalar {
        return $this->statement->key();
    }

    public function next(): void {
        $this->statement->next();
    }

    public function rewind(): void {
        $this->statement->rewind();
    }

    public function valid(): bool {
        return $this->statement->valid();
    }

    private function parseQuery(): void {
        $matches = null;
        preg_match_all($this->getPlaceholderRegex(), $this->query, $matches);
        $n       = 0;
        foreach ($matches[1] ?? [] as $i) {
            if (!empty($i)) {
                $this->param[$i] = null;
            } else {
                $this->param[$n] = null;
                $n++;
            }
        }
    }

    private function getQuery(): string {
        $param = $this->param;
        $query = preg_replace_callback($this->getPlaceholderRegex(), function (array $matches) use ($param) {
            static $n = 0;
            if (isset($matches[2])) {
                $pn   = $n;
                $from = '?';
                $to   = $this->param[$pn] ?? null;
                $n++;
            } else {
                $pn   = $matches[1];
                $from = ":$pn";
                $to   = $this->escapeValue($pn);
            }
            if ($to === null) {
                throw new SparqlException("Parameter $pn value missing");
            }
            return str_replace($from, $to, $matches[0]);
        }, $this->query);
        return $query;
    }

    private function getPlaceholderRegex(): string {
        return '/' . self::PH_NAMED . '|' . self::PH_POSIT . '/u';
    }

    private function escapeValue(string $paramName): string | null {
        if (!isset($this->param[$paramName])) {
            return null;
        }
        return NtriplesUtil::serialize($this->param[$paramName]);
    }
}

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
use rdfInterface\TermInterface as iTerm;
use rdfInterface\QuadInterface as iQuad;
use rdfHelpers\NtriplesUtil;

/**
 * Description of PreparedStatement
 *
 * @author zozlak
 */
class PreparedStatement implements StatementInterface {

    const PH_POSIT = '(?>\\G|[^\\\\])([?])(?>$|[^a-zA-Z0-9_\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{02FF}\x{0370}-\x{037D}\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}])';
    const PH_NAMED = '(?>\\G|[^\\\\]):([a-zA-Z0-9]+)';

    private StatementInterface $statement;

    /**
     * 
     * @var array<iTerm>
     */
    private array $param = [];

    public function __construct(private string $query,
                                private SimpleConnectionInterface $connection) {
        $this->parseQuery();
    }

    public function bindParam(int | string $parameter, iTerm &$variable): bool {
        if (!array_key_exists($parameter, $this->param)) {
            throw new SparqlException("Unknown parameter $parameter");
        }
        $this->param[$parameter] = &$variable;
        return true;
    }

    public function bindValue(int | string $parameter, iTerm $value): bool {
        if (!array_key_exists($parameter, $this->param)) {
            throw new SparqlException("Unknown parameter $parameter");
        }
        $this->param[$parameter] = $value;
        return true;
    }

    /**
     * 
     * @param array<mixed> $parameters
     * @return bool
     */
    public function execute(array $parameters = []): bool {
        foreach ($parameters as $n => $v) {
            $this->bindValue($n, $v);
        }
        $query           = $this->getQuery();
        $this->statement = $this->connection->query($query);
        return true;
    }

    /**
     * 
     * @param int $fetchStyle
     * @return object|array<mixed>|string|false
     */
    public function fetch(int $fetchStyle = PDO::FETCH_OBJ): object | array | string | false {
        return $this->statement->fetch($fetchStyle);
    }

    /**
     * 
     * @param int $fetchStyle
     * @return array<mixed>
     */
    public function fetchAll(int $fetchStyle = PDO::FETCH_OBJ): array {
        return $this->statement->fetchAll($fetchStyle);
    }

    public function fetchColumn(): object | string | bool {
        return $this->statement->fetchColumn();
    }

    public function current(): mixed {
        return $this->statement->current();
    }

    /**
     * 
     * @return scalar
     */
    public function key(): mixed {
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
                $to   = $param[$pn] ?? null;
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

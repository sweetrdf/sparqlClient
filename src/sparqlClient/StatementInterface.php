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

/**
 *
 * @author zozlak
 */
interface StatementInterface extends \Iterator {

    public function bindParam(int | string $parameter, iTerm &$variable): bool;

    public function bindValue(int | string $parameter, iTerm $value): bool;

    /**
     * 
     * @param array<mixed> $parameters
     * @return bool
     */
    public function execute(array $parameters = []): bool;

    /**
     * 
     * @param int $fetchStyle
     * @return array<mixed>
     */
    public function fetchAll(int $fetchStyle = PDO::FETCH_OBJ): array;

    /**
     * 
     * @param int $fetchStyle
     * @return object|array<mixed>|string|false
     */
    public function fetch(int $fetchStyle = PDO::FETCH_OBJ): object | array | string | bool;

    /**
     * Returns a first column of the next results row.
     * 
     * If there are no more results returns `false.
     * 
     * Please note `false` can be also a valid value of the ASK query.
     * 
     * @return object|bool
     */
    public function fetchColumn(): object | string | bool;
}

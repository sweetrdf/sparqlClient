<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

use rdfInterface\Term as iTerm;

/**
 *
 * @author zozlak
 */
interface StatementInterface extends \Iterator {

    public function bindParam(int | string $parameter, iTerm &$variable): bool;

    public function bindValue(int | string $parameter, iTerm $value): bool;

    public function execute(array $parameters = []): bool;

    public function fetchAll(int $fetchStyle = PDO::FETCH_OBJ): array;

    public function fetch(int $fetchStyle = PDO::FETCH_OBJ): object | array | false;

    public function fetchColumn(): object | false;
}

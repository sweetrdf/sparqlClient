<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace sparqlClient;

/**
 *
 * @author zozlak
 */
interface SimpleConnectionInterface {

    public function query(string $query): StatementInterface;

    public function askQuery(string $query): bool;

    public function prepare(string $query): StatementInterface;

    public function prepareAsk(string $query): StatementInterface;
}

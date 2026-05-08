<?php

namespace App\Repository;

class Repository
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }
}
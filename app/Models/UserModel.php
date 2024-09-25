<?php

namespace App\Models;

/**
 * User Model — Table mirror
 *
 * Defines the table structure (name, primary key, fillable columns).
 * No SQL here — all queries live in the Repository layer.
 */
class UserModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_name',
        'user_email',
        'user_password',
    ];

    public function getTable()
    {
        return $this->table;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getFillable()
    {
        return $this->fillable;
    }
}

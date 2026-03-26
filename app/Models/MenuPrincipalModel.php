<?php

namespace App\Models;

use App\Models\Model;

/**
 * User Model — Table mirror
 *
 *  id                   INT   NOT NULL  AUTO_INCREMENT  PRIMARY KEY
 *  id_mp_fk             INT
 *  nm_menu              VARCHAR(50)   NOT NULL
 *  ds_observacoes       TEXT
 *  is_public            INT    DEFAULT (0)
 *  created_at           DATETIME   NOT NULL
 *  updated_at           DATETIME  ON UPDATE current_timestamp
 *  deleted_at           DATETIME
 *  created_by           INT   NOT NULL
 */
class MenuPrincipalModel extends Model
{
    protected $table = 'sysfat_menu_principal';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nm_menu',
        'ds_observacoes',
        'is_public',
        'created_by',
    ];

    public function submenus(): array
    {
        return $this->hasMany(SubmenusModel::class, 'id_mp_fk', 'id_submenu_fk IS NULL');
    }
}

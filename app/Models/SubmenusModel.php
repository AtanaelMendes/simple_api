<?php

namespace App\Models;

use App\Models\Model;

/**
 *
 * id                   INT   NOT NULL  AUTO_INCREMENT  PRIMARY KEY,
 * id_mp_fk             INT   NOT NULL    ,
 * id_submenu_fk        INT       ,
 * ds_observacoes       TEXT       ,
 * created_at           DATETIME   NOT NULL DEFAULT (current_timestamp())   ,
 * is_public            INT       ,
 * nm_submenu           VARCHAR(50)   NOT NULL    ,
 * updated_at           DATETIME  ON UPDATE current_timestamp     ,
 * deleted_at           DATETIME       ,
 * created_by           INT   NOT NULL
 */
class SubmenusModel extends Model
{
    protected $table = 'sysfat_submenus';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_mp_fk',
        'id_submenu_fk',
        'nm_submenu',
        'ds_observacoes',
        'is_public',
        'created_by',
    ];

    public function menuPrincipal(): ?Model
    {
        return $this->belongsTo(MenuPrincipalModel::class, 'id_mp_fk');
    }
}

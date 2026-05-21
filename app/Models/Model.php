<?php

namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $data = [];

    public function fill(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * hasMany — this model has many related records.
     * e.g. MenuPrincipal hasMany Submenus where submenus.id_mp_fk = this.id
     *
     * @return array Array of related model instances
     */
    protected function hasMany(string $relatedClass, string $foreignKey, string $extraWhere = ''): array
    {
        $related = new $relatedClass();
        $pkValue = $this->data[$this->primaryKey] ?? null;

        if ($pkValue === null) {
            return [];
        }

        $extra = $extraWhere !== '' ? " AND {$extraWhere}" : '';

        $rows = Database::getInstance()->select(
            "SELECT * FROM {$related->getTable()} WHERE {$foreignKey} = :fk AND deleted_at IS NULL{$extra}",
            ['fk' => $pkValue]
        );

        return array_map(function ($row) use ($relatedClass) {
            return (new $relatedClass())->fill($row);
        }, $rows);
    }

    /**
     * belongsTo — this model belongs to a parent record.
     * e.g. Submenu belongsTo MenuPrincipal where menu_principal.id = this.id_mp_fk
     *
     * @return static|null The parent model instance, or null if not found
     */
    protected function belongsTo(string $parentClass, string $foreignKey): ?self
    {
        $parent = new $parentClass();
        $fkValue = $this->data[$foreignKey] ?? null;

        if ($fkValue === null) {
            return null;
        }

        $rows = Database::getInstance()->select(
            "SELECT * FROM {$parent->getTable()} WHERE {$parent->getPrimaryKey()} = :pk AND deleted_at IS NULL",
            ['pk' => $fkValue]
        );

        if (empty($rows)) {
            return null;
        }

        return (new $parentClass())->fill($rows[0]);
    }
}

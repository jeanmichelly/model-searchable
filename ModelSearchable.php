<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin \Eloquent
 * @mixin Model
 * @method self search(string $attribute, $value = null)
 * @method self scout(array $attributes, $value = null)
 * @method self order($order, $direction)
 * @method self nestedSelect($select)
 * @method self whereDateBetween($attribute, $from, $to)
 * @method self whenWhereIn($attribute, $values)
 */
trait ModelSearchable
{
    /**
     * @param Builder $query
     * @param string|\Closure|array $field
     * @param $operator
     * @param null $value
     * @return mixed
     */
    public function scopeWhenWhere(Builder $query, $field, $operator = null, $value = null)
    {
        return $query->when($value, function (Builder $query, $value) use ($field, $operator) {
            return $query->where($field, $operator, $value);
        });
    }

    /**
     * @param Builder $query
     * @param string $attribute
     * @param $values
     * @return mixed
     */
    public function scopeWhenWhereIn(Builder $query, string $attribute, $values)
    {
        return $query->when($values, function (Builder $query, $values) use ($attribute) {
            $nested = explode('.', $attribute, 2);

            if (!empty($nested[1])) {
                return $query->whereHas($nested[0], function (Builder $query) use ($nested, $values) {
                    return $this->scopeWhenWhereIn($query, $nested[1], $values);
                });
            }

            return $query->whereIn($nested[0], $values);
        });
    }

    /**
     * @param Builder $query
     * @param $attribute
     * @param $from
     * @param $to
     * @return mixed
     */
    public function scopeWhereDateBetween(Builder $query, $attribute, $from, $to)
    {
        return $query->when($from, function (Builder $query, $from) use ($attribute) {
            return $query->whereDate($attribute, '>=', $from);
        })->when($to, function (Builder $query, $to) use ($attribute) {
            return $query->whereDate($attribute, '<=', $to);
        });
    }

    /**
     * @param Builder $query
     * @param string $attribute
     * @param string|null $value
     * @return mixed
     */
    public function scopeSearch(Builder $query, string $attribute, $value = null)
    {
        return $query->when($value, function (Builder $query, $value) use ($attribute) {
            $nested = explode('.', $attribute, 2);

            if (!empty($nested[1])) {
                return $query->whereHas($nested[0], function (Builder $query) use ($nested, $value) {
                    return $this->scopeSearch($query, $nested[1], $value);
                });
            }

            return $query->where($nested[0], 'like', "%$value%");
        });
    }

    /**
     * @param Builder $query
     * @param array $attributes
     * @param string|null $value
     * @return Builder
     */
    public function scopeScout(Builder $query, array $attributes, $value = null)
    {
        return $query->when($value, function (Builder $query, $value) use ($attributes) {
            $query->where(function (Builder $query) use ($attributes, $value) {
                foreach ($attributes as $attribute) {
                    $query->orWhere(function (Builder $query) use ($attribute, $value) {
                        $this->scopeSearch($query, $attribute, $value);
                    });
                }
            });
        });
    }

    /**
     * @param Builder $query
     * @param $select
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    public function scopeNestedSelect(Builder $query, $select)
    {
        $nested = explode('.', $select, 2);

        /** @var Relation $relation */
        $relation = $query->getModel()->{$nested[0]}();

        $query = $relation->getRelationExistenceQuery($relation->getRelated()->newQuery(), $query, []);

        if (isset($nested[1]) && !empty(explode('.', $nested[1])[1])) {
            $nestedQuery = $this->scopeNestedSelect($query, $nested[1]);

            return $query->selectRaw("({$nestedQuery->toSql()})", $nestedQuery->getBindings());
        }

        return $query->select($nested[1]);
    }

    /**
     * @param Builder $query
     * @param $order
     * @param $direction
     * @return Builder
     */
    public function scopeOrder(Builder $query, $order, $direction)
    {
        return $query->when($order && in_array($direction, ['desc', 'asc']), function (Builder $query) use ($order, $direction) {
            $nested = explode('.', $order, 2);

            if (!empty($nested[1])) {
                $orderByQuery = $this->scopeNestedSelect($query, $order);

                return $query->orderByRaw("({$orderByQuery->toSql()}) $direction", $orderByQuery->getBindings());
            }

            return $query->orderBy($order, $direction);
        });
    }
}

<?php namespace Mpociot\Couchbase\Eloquent;

use Mpociot\Couchbase\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;

/**
 * Class Builder
 * @package Mpociot\Couchbase\Eloquent
 *
 * @property \Mpociot\Couchbase\Query\Builder query
 */
class Builder extends EloquentBuilder
{
    /**
     * Builder constructor.
     * @param \Mpociot\Couchbase\Query\Builder $query
     */
    public function __construct(QueryBuilder $query)
    {
        parent::__construct($query);
        $this->passthru = array_merge($this->passthru, ['pluck', 'push', 'pull']);
    }


    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        return $this->query->update($this->addUpdatedAtColumn($values));
    }
    
    /**
     * Add a where clause on the primary key to the query.
     *
     * @param  mixed  $id
     * @return $this
     */
    public function whereKey($id)
    {
        $this->query->useKeys($id);
            
        return $this;
    }
    
    /**
     * Add a basic where clause to the query.
     *
     * @param  string|\Closure  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if($column === $this->model->getKeyName() || $column === '_id') {
            $value = func_num_args() == 2 ? $operator : $value;
            $this->whereKey($value);
            return $this;
        }
        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add the "has" condition where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $hasQuery
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @param  string  $operator
     * @param  int  $count
     * @param  string  $boolean
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addHasWhere(EloquentBuilder $hasQuery, Relation $relation, $operator, $count, $boolean)
    {
        $query = $hasQuery->getQuery();

        // Get the number of related objects for each possible parent.
        $relations = $query->pluck($relation->getHasCompareKey());
        $relationCount = array_count_values(array_map(function ($id) {
            return (string) $id; // Convert Back ObjectIds to Strings
        }, is_array($relations) ? $relations : $relations->toArray()));

        // Remove unwanted related objects based on the operator and count.
        $relationCount = array_filter($relationCount, function ($counted) use ($count, $operator) {
            // If we are comparing to 0, we always need all results.
            if ($count == 0) {
                return true;
            }

            switch ($operator) {
                case '>=':
                case '<':
                    return $counted >= $count;
                case '>':
                case '<=':
                    return $counted > $count;
                case '=':
                case '!=':
                    return $counted == $count;

                default:
                    return false;
            }
        });

        // If the operator is <, <= or !=, we will use whereNotIn.
        $not = in_array($operator, ['<', '<=', '!=']);

        // If we are comparing to 0, we need an additional $not flip.
        if ($count == 0) {
            $not = !$not;
        }

        // All related ids.
        $relatedIds = array_keys($relationCount);

        // Add whereIn to the query.
        return $this->whereIn($this->model->getKeyName(), $relatedIds, $boolean, $not);
    }



    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereAnyIn($column, $values, $boolean = 'and')
    {
        $type = 'AnyIn';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            if (! $value instanceof Expression) {
                $this->addBinding($value, 'where');
            }
        }

        return $this;
    }

    /**
     * Create a raw database expression.
     *
     * @param  callable  $expression
     * @return mixed
     */
    public function raw($expression = null)
    {
        // Get raw results from the query builder.
        $results = $this->query->raw($expression);

        // The result is a single object.
        if (is_array($results) and array_key_exists('_id', $results)) {
            return $this->model->newFromBuilder((array) $results);
        }

        return $results;
    }
}

<?php namespace Mpociot\Couchbase\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Mpociot\Couchbase\Helper;

class Grammar extends BaseGrammar
{
    const RESERVED_WORDS = [
        'ALL',
        'ALTER',
        'ANALYZE',
        'AND',
        'ANY',
        'ARRAY',
        'AS',
        'ASC',
        'BEGIN',
        'BETWEEN',
        'BINARY',
        'BOOLEAN',
        'BREAK',
        'BUCKET',
        'BUILD',
        'BY',
        'CALL',
        'CASE',
        'CAST',
        'CLUSTER',
        'COLLATE',
        'COLLECTION',
        'COMMIT',
        'CONNECT',
        'CONTINUE',
        'CORRELATE',
        'COVER',
        'CREATE',
        'DATABASE',
        'DATASET',
        'DATASTORE',
        'DECLARE',
        'DECREMENT',
        'DELETE',
        'DERIVED',
        'DESC',
        'DESCRIBE',
        'DISTINCT',
        'DO',
        'DROP',
        'EACH',
        'ELEMENT',
        'ELSE',
        'END',
        'EVERY',
        'EXCEPT',
        'EXCLUDE',
        'EXECUTE',
        'EXISTS',
        'EXPLAIN',
        'FALSE',
        'FETCH',
        'FIRST',
        'FLATTEN',
        'FOR',
        'FORCE',
        'FROM',
        'FUNCTION',
        'GRANT',
        'GROUP',
        'GSI',
        'HAVING',
        'IF',
        'IGNORE',
        'ILIKE',
        'IN',
        'INCLUDE',
        'INCREMENT',
        'INDEX',
        'INFER',
        'INLINE',
        'INNER',
        'INSERT',
        'INTERSECT',
        'INTO',
        'IS',
        'JOIN',
        'KEY',
        'KEYS',
        'KEYSPACE',
        'KNOWN',
        'LAST',
        'LEFT',
        'LET',
        'LETTING',
        'LIKE',
        'LIMIT',
        'LSM',
        'MAP',
        'MAPPING',
        'MATCHED',
        'MATERIALIZED',
        'MERGE',
        'MINUS',
        'MISSING',
        'NAMESPACE',
        'NEST',
        'NOT',
        'NULL',
        'NUMBER',
        'OBJECT',
        'OFFSET',
        'ON',
        'OPTION',
        'OR',
        'ORDER',
        'OUTER',
        'OVER',
        'PARSE',
        'PARTITION',
        'PASSWORD',
        'PATH',
        'POOL',
        'PREPARE',
        'PRIMARY',
        'PRIVATE',
        'PRIVILEGE',
        'PROCEDURE',
        'PUBLIC',
        'RAW',
        'REALM',
        'REDUCE',
        'RENAME',
        'RETURN',
        'RETURNING',
        'REVOKE',
        'RIGHT',
        'ROLE',
        'ROLLBACK',
        'SATISFIES',
        'SCHEMA',
        'SELECT',
        'SELF',
        'SEMI',
        'SET',
        'SHOW',
        'SOME',
        'START',
        'STATISTICS',
        'STRING',
        'SYSTEM',
        'THEN',
        'TO',
        'TRANSACTION',
        'TRIGGER',
        'TRUE',
        'TRUNCATE',
        'UNDER',
        'UNION',
        'UNIQUE',
        'UNKNOWN',
        'UNNEST',
        'UNSET',
        'UPDATE',
        'UPSERT',
        'USE',
        'USER',
        'USING',
        'VALIDATE',
        'VALUE',
        'VALUED',
        'VALUES',
        'VIA',
        'VIEW',
        'WHEN',
        'WHERE',
        'WHILE',
        'WITH',
        'WITHIN',
        'WORK',
        'XOR',
    ];

    /**
     * The components that make up a select clause.
     *
     * Note: We added "key"
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'keys',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * Attempt to escape value in backticks if not a wildcard and not already done
     *
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        switch (gettype($value)) {
            case 'double':
            case 'integer':
                $value = (string)$value;
                break;
            case 'NULL':
                $value = 'null';
                break;
            case 'boolean':
                $value = $value ? 'true' : 'false';
                break;
            case 'object':
                if (method_exists($value, '__toString')) {
                    $value = (string)$value;
                    break;
                }
            case 'array':
                throw new \InvalidArgumentException('Cannot set value of type array or object.  Please serialize first.');
        }

        // TODO: Really hate "is_numeric".  Maybe find better way?
        if (is_numeric($value)) {
            return $value;
        }
        $escaped = $value[0] === '`' || $value[0] === '"' || $value[0] === '\'';
        if ($escaped) {
            if (substr($value, -1) === $value[0]) {
                return $value;
            }
        } else if (preg_match('/^[a-zA-Z_]+\(/', $value)) {
            return $value;
        }

        if (strlen($value) === 4) {
            $l = strtolower($value);
            if ($l === 'true' || $l === 'false' || $l === 'null') {
                return $value;
            }
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereNull(BaseBuilder $query, $where)
    {
        return '(' . $this->wrap($where['column']) . ' is null OR ' . $this->wrap($where['column']) . ' is MISSING )';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        if ($where['column'] === '_id') {
            $column = new Expression('meta(`' . $query->getConnection()->getBucketName() . '`).`id`');
            return $column . ' in [' . $values . ']';
        } else {
            return $this->wrap($where['column']) . ' in [' . $values . ']';
            $colIdentifier = str_random(5);
            return 'ANY ' .
                $this->wrap($colIdentifier) .
                ' IN ' .
                $this->wrap(($where['column'])) .
                ' SATISFIES ' .
                $this->wrap($colIdentifier) .
                ' IN ["' .
                $values .
                '"]';
        }
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereNotIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        if ($where['column'] === '_id') {
            $column = new Expression('meta(`' . $query->getConnection()->getBucketName() . '`).`id`');
            return $column . ' not in [' . $values . ']';
        } else {
            return $where['column'] . ' not in [' . $values . ']';
            $colIdentifier = str_random(5);
            return 'ANY ' .
                $colIdentifier .
                ' IN ' .
                $this->wrap($where['column']) .
                ' SATISFIES ' .
                $colIdentifier .
                ' IN ["' .
                $values .
                '"]';
        }
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereAnyIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        $colIdentifier = str_random(5);
        return 'ANY ' .
            $colIdentifier .
            ' IN ' .
            $this->wrap($where['column']) .
            ' SATISFIES ' .
            $colIdentifier .
            ' IN ["' .
            $values .
            '"]';
    }

    /**
     * @param \Mpociot\Couchbase\Query\Builder $query
     * @param array                            $values
     * @return string
     */
    public function compileUnset(Builder $query, array $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', $query->returning);

        $columns = [];

        foreach ($values as $key) {
            $columns[] = $this->wrap($key);
        }

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);
        return trim("update {$table} {$keyClause} unset {$columns} {$where} RETURNING {$returning}");
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $values
     * @return string
     */
    public function compileInsert(BaseBuilder $query, array $values)
    {

        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        if (is_null($query->keys)) {
            $query->useKeys(Helper::getUniqueId($values[Helper::TYPE_NAME]));
        }
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', $query->returning);

        if (!is_array(reset($values))) {
            $values = [$values];
        }
        $parameters = [];

        foreach ($values as $record) {
            $parameters[] = '(' . $this->parameterize($record) . ')';
        }
        $parameters = collect($parameters)->transform(function ($parameter) use ($keyClause) {
            return "({$keyClause}, ?)";
        });
        $parameters = implode(', ', array_fill(0, count($parameters), '?'));
        $keyValue = '(KEY, VALUE)';

        return "insert into {$table} {$keyValue} values {$parameters} RETURNING {$returning}";
    }

    /**
     * notice: supported set query only
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $values
     * @return string
     */
    public function compileUpdate(BaseBuilder $query, $values)
    {
        /** @var \Mpociot\Couchbase\Query\Builder $query */
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', array_map([$this, 'wrap'], $query->returning));

        $columns = [];

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) . ' = ' . $this->parameter($value);
        }

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);

        $forIns = [];
        foreach ($query->forIns as $forIn) {
            foreach ($forIn['values'] as $key => $value) {
                $forIns[] = $this->wrap($key) .
                    ' = ' .
                    $this->wrap($value) .
                    ' FOR ' .
                    $this->wrap(str_singular($forIn['alias'])) .
                    ' IN ' .
                    $this->wrap($forIn['alias']) .
                    ' WHEN ' .
                    $this->wrap($forIn['column']) .
                    '= ' . $this->parameter($forIn['value']) .
                    ' END';
            }
        }
        $forIns = implode(', ', $forIns);

        return trim("update {$table} {$keyClause} set {$columns} {$forIns} {$where} RETURNING {$returning}");
    }

    /**
     * {@inheritdoc}
     *
     * @see http://developer.couchbase.com/documentation/server/4.1/n1ql/n1ql-language-reference/delete.html
     */
    public function compileDelete(BaseBuilder $query)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', array_map([$this, 'wrap'], $query->returning));
        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$table} {$keyClause} {$where} RETURNING {$returning}");
    }

    /**
     * @param BaseBuilder $query
     * @return string
     */
    public function compileKeys(BaseBuilder $query)
    {
        if (is_array($query->keys)) {
            if (0 === count($query->keys)) {
                return 'USE KEYS []';
            }
            return 'USE KEYS ["' . implode('","', $query->keys) . '"]';
        }
        return "USE KEYS \"{$query->keys}\"";
    }
}

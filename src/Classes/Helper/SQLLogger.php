<?php

namespace Helio\Panel\Helper;

class SQLLogger implements \Doctrine\DBAL\Logging\SQLLogger
{
    /**
     * Logs a SQL statement somewhere.
     *
     * @param string              $sql    the SQL to be executed
     * @param mixed[]|null        $params the SQL parameters
     * @param int[]|string[]|null $types  the SQL parameter types
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        LogHelper::debug(sprintf('%s with params %s', $sql, print_r($params, true)));
    }

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     */
    public function stopQuery()
    {
        // TODO: Implement stopQuery() method.
    }
}

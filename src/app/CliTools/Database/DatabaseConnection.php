<?php

namespace CliTools\Database;

/*
 * CliTools Command
 * Copyright (C) 2015 Markus Blaschke <markus@familie-blaschke.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use CliTools\Utility\ConsoleUtility;

class DatabaseConnection {

    /**
     * Database connectiond dsn
     *
     * @var null|string
     */
    static protected $dbDsn;

    /**
     * Database connection username
     *
     * @var null|string
     */
    static protected $dbUsername;

    /**
     * Database connection password
     *
     * @var null|string
     */
    static protected $dbPassword;

    /**
     * PDO connection
     *
     * @var null|\PDO
     */
    static protected $connection;

    /**
     * Set dns
     *
     * @param string      $dsn      DSN
     * @param string|null $username Username
     * @param string|null $password Password
     */
    public static function setDsn($dsn, $username = null, $password = null) {

        if ($dsn !== null) {
            self::$dbDsn = $dsn;
        }

        if ($username !== null) {
            self::$dbUsername = $username;
        }

        if ($password !== null) {
            self::$dbPassword = $password;
        }

        self::$connection = null;
    }

    /**
     * Get Db Username
     *
     * @return string
     */
    public static function getDbUsername() {
        return self::$dbUsername;
    }

    /**
     * Get Db Password
     *
     * @return string
     */
    public static function getDbPassword() {
        return self::$dbPassword;
    }

    /**
     * Get connection
     *
     * @return \PDO
     * @throws \PDOException
     */
    public static function getConnection() {

        if (self::$connection === null) {
            try {
                $con = new \PDO(self::$dbDsn, self::$dbUsername, self::$dbPassword);
                $con->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $con->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
                $con->exec('SET NAMES utf8');
                $con->exec('SET CHARACTER SET utf8');
                // SET SESSION sql_mode = 'STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE'
                $con->exec('SET SESSION sql_mode = \'ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE\'');

                self::$connection = $con;
            } catch (\Exception $e) {
                throw new \PDOException('Cannot connect to "' . self::$dbDsn . '" with user "' . self::$dbUsername . '" and password "' . self::$dbPassword . '", error was:' . $e->getMessage());
            }
        }

        return self::$connection;
    }


    /**
     * Ping server
     *
     * @return bool
     */
    public static function ping() {
        ConsoleUtility::verboseWriteln('DB::PING', null);
        try {
            self::getConnection()->query('SELECT 1');
        } catch (\PDOException $e) {
            ConsoleUtility::verboseWriteln('DB::QUERY::EXCEPTION', $e);
            throw $e;
        }

        return true;
    }

    /**
     * Execute SELECT query
     *
     * @param  string $query SQL query
     *
     * @return \PDOStatement
     * @throws \PDOException
     */
    public static function query($query) {
        ConsoleUtility::verboseWriteln('DB::QUERY', $query);

        try {
            $ret = self::getConnection()->query($query);
        } catch (\PDOException $e) {
            ConsoleUtility::verboseWriteln('DB::QUERY::EXCEPTION', $e);
            throw $e;
        }

        return $ret;
    }

    /**
     * Switch database
     *
     * @param  string $database Database
     *
     * @throws \PDOException
     */
    public static function switchDatabase($database) {
        self::exec('USE ' . self::sanitizeSqlDatabase($database));
    }

    /**
     * Execute INSERT/DELETE/UPDATE query
     *
     * @param  string $query SQL query
     *
     * @return int
     * @throws \PDOException
     */
    public static function exec($query) {
        ConsoleUtility::verboseWriteln('DB::EXEC', $query);

        try {
            $ret = self::getConnection()->exec($query);
        } catch (\PDOException $e) {
            ConsoleUtility::verboseWriteln('DB::EXEC::EXCEPTION', $e);
            throw $e;
        }

        return $ret;
    }

    /**
     * Quote
     *
     * @param   mixed $value Quot value
     *
     * @return string
     * @throws \PDOException
     */
    public static function quote($value) {
        return self::getConnection()->quote($value);
    }


    /**
     * Quote array with values
     *
     * @param   array $valueList Values
     *
     * @return  array
     */
    public static function quoteArray($valueList) {
        $ret = array();
        foreach ($valueList as $k => $v) {
            $ret[$k] = self::quote($v);
        }

        return $ret;
    }

    /**
     * Get one (first value of first row)
     *
     * @param  string $query SQL query
     *
     * @return mixed|null
     */
    public static function getOne($query) {
        $ret = null;

        $res = self::query($query);

        if ($row = $res->fetch()) {
            $ret = reset($row);
        }

        return $ret;
    }

    /**
     * Get first row
     *
     * @param  string $query SQL query
     *
     * @return mixed|null
     * @throws \PDOException
     */
    public static function getRow($query) {
        $ret = null;

        $res = self::query($query);

        if ($row = $res->fetch()) {
            $ret = $row;
        }

        return $ret;
    }

    /**
     * Get all rows
     *
     * @param  string $query SQL query
     *
     * @return array
     * @throws \PDOException
     */
    public static function getAll($query) {
        $ret = array();

        $res = self::query($query);

        foreach ($res as $row) {
            $ret[] = $row;
        }

        return $ret;
    }


    /**
     * Get All with index (first value)
     *
     * @param  string $query    SQL query
     * @param  string $indexCol Index column name
     *
     * @return array
     * @throws \PDOException
     */
    public static function getAllWithIndex($query, $indexCol = null) {
        $ret = array();

        $res = self::query($query);
        if ($res) {
            foreach ($res as $row) {
                if ($indexCol === null) {
                    // use first key as index
                    $index = reset($row);
                } else {
                    $index = $row[$indexCol];
                }

                $ret[$index] = $row;
            }
        }

        return $ret;
    }

    /**
     * Get column
     *
     * @param  string $query SQL uery
     *
     * @return array
     * @throws \PDOException
     */
    public static function getCol($query) {
        $ret = array();

        $res = self::query($query);

        foreach ($res as $row) {
            $ret[] = reset($row);
        }

        return $ret;
    }

    /**
     * Get column (with index)
     *
     * @param  string $query SQL query
     *
     * @return array
     * @throws \PDOException
     */
    public static function getColWithIndex($query) {
        $ret = array();

        $res = self::query($query);

        foreach ($res as $row) {
            $value       = reset($row);
            $ret[$value] = $value;
        }

        return $ret;
    }

    /**
     * Get list
     *
     * @param  string $query SQL query
     *
     * @return array
     * @throws \PDOException
     */
    public static function getList($query) {
        $ret = array();

        $res = self::query($query);

        foreach ($res as $row) {
            $key       = reset($row);
            $value     = next($row);
            $ret[$key] = $value;
        }

        return $ret;
    }

    /**
     * Check if database exists
     *
     * @param string $database Database name
     * @return boolean
     */
    public static function databaseExists($database) {
        $query = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = %s';
        $query = sprintf($query, self::quote($database));
        $ret   = (int)self::getOne($query);

        return ($ret === 1 );
    }

    /**
     * Return list of databases
     *
     * @return array
     */
    public static function databaseList() {
        // Get list of databases
        $query = 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA';
        $ret   = DatabaseConnection::getCol($query);

        // Filter mysql specific databases
        $ret = array_diff($ret, array('mysql', 'information_schema', 'performance_schema'));

        return $ret;
    }

    /**
     * Return list of tables of one database
     *
     * @param string $database Database name
     * @return array
     */
    public static function tableList($database) {
        $query = 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s';
        $query = sprintf($query, self::quote($database));
        $ret   = self::getCol($query);

        return $ret;
    }


    /**
     * Check if table exists in database
     *
     * @param string $database Database name
     * @param string $table    Table name
     * @return boolean
     */
    public static function tableExists($database, $table) {
        $query = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s';
        $query = sprintf($query, self::quote($database), self::quote($table) );
        $ret   = (bool)self::getOne($query);

        return $ret;
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction() {
        self::exec('BEGIN TRANSACTION');
    }

    /**
     * Commit transaction
     */
    public static function commit() {
        self::exec('COMMIT');
    }

    /**
     * Rollback transaction
     */
    public static function rollback() {
        self::exec('ROLLBACK');
    }

    ###########################################################################
    # Helper functions
    ###########################################################################

    /**
     * Add condition to query
     *
     * @param  array|string $condition Condition
     *
     * @return string
     */
    public static function addCondition($condition) {
        $ret = ' ';

        if (!empty($condition)) {
            if (is_array($condition)) {
                $ret .= ' AND (( ' . implode(" )\nAND (", $condition) . ' ))';
            } else {
                $ret .= ' AND ( ' . $condition . ' )';
            }
        }

        return $ret;
    }

    /**
     * Create condition WHERE field IN (1,2,3,4)
     *
     * @param  string  $field    SQL field
     * @param  array   $values   Values
     * @param  boolean $required Required
     *
     * @return string
     */
    public static function conditionIn($field, $values, $required = true) {
        if (!empty($values)) {
            $quotedValues = self::quoteArray($values);

            $ret = $field . ' IN (' . implode(',', $quotedValues) . ')';
        } else {
            if ($required) {
                $ret = '1=0';
            } else {
                $ret = '1=1';
            }
        }

        return $ret;
    }

    /**
     * Create condition WHERE field NOT IN (1,2,3,4)
     *
     * @param  string  $field    SQL field
     * @param  array   $values   Values
     * @param  boolean $required Required
     *
     * @return string
     */
    public static function conditionNotIn($field, $values, $required = true) {
        if (!empty($values)) {
            $quotedValues = self::quoteArray($values);

            $ret = $field . ' NOT IN (' . implode(',', $quotedValues) . ')';
        } else {
            if ($required) {
                $ret = '1=0';
            } else {
                $ret = '1=1';
            }
        }

        return $ret;
    }

    /**
     * Sanitize field for sql usage
     *
     * @param   string $field SQL Field/Attribut
     *
     * @return  string
     */
    public static function sanitizeSqlField($field) {
        return preg_replace('/[^_a-zA-Z0-9\.]/', '', $field);
    }

    /**
     * Sanitize table for sql usage
     *
     * @param  string $table SQL Table
     *
     * @return string
     */
    public static function sanitizeSqlTable($table) {
        return '`' . preg_replace('/[^_a-zA-Z0-9]/', '', $table) . '`';
    }

    /**
     * Sanitize database for sql usage
     *
     * @param  string $database SQL Database
     *
     * @return string
     */
    public static function sanitizeSqlDatabase($database) {
        return '`' . preg_replace('/[^_a-zA-Z0-9]/', '', $database) . '`';
    }
}

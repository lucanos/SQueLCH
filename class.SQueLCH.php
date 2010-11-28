<?php

/*

  SQueLCH v3.3.0

  Copyright 2010 Luke Stevenson <lucanos@gmail.com>

  Any associated materials, used under licence from other sources are governed
    by the terms and conditions of those licences, unless superceded by this
    copyright.
  This work is exclusively owned by Luke Stevenson and reproduction of it in
    whole or in part may constitute a copyright infringement.

  == Version History ==
  ---------------------
  2010/11/25 - v3.3.0
    Added functionality to allow for Cleaning of Old Cachefiles from the
      Cache Directory, based on the Cache Lifetime. Using $db->cache_clean()
    This is expected to be performed as part of Housekeeping duties within
      a CRON Job.
  2010/11/24 - v3.2.0
    Added functionality to record Queries and Error Messages into a specified
      debug file.
  2010/11/20 - v3.1.1
    Modified escape() to use mysql_real_escape_string() in preference to
      mysql_escape_string(). Slight modification of Syntax in same function.
    Extended Query Functions to allow for Arrayed Parameters.
    Created Arrayed Parameter "ignoreCache" allowing per-query override of
      any cache, when queried via a sub-function (get_results, get_row,
      get_col, get_var).
  2010/11/10 - v3.1.0
    Added feature to allow DB Details to be passed to the SQueLCH constructor
      as an array - array( 'type'=>'mysql' , 'user'=>'username' , ...
    Keys are: 'type', 'user', 'pass', 'base' , 'host' , 'port'
    Might look at allowing abbreviations of these in a later release
      (ie the first letters only, for shorthand)
  2010/07/11 - v3.0.0
    Basic Revision by Luke

*/



// The CLASS ******************************************************************
//=============================================================================

class SQueLCH {

  // Class Variables **********************************************************
  //===========================================================================

   # Class Details
    var $version          = '3.3.0';
    var $classname        = 'SQueLCH';

   # Action Flags
    var $debug_called     = false;
    var $vardump_called   = false;

   # SQL Variables
    var $dbType           = false;
    var $dbTypes          = array( 'mssql' , 'mysql' , 'oracle' , 'pdo' , 'postgresql' , 'sqlite' );
    var $dbHandle         = false;
    var $last_result      = false;
    var $last_query       = false;
    var $last_error       = null;
    var $col_info         = false;
    var $cache_used       = false;

    var $debug_all        = false; // same as $trace
    var $debug_echo_is_on = true;
    var $show_errors      = true;
    var $error_file       = 'SQueLCH_log.txt'; // Located within the Cache Directory

    var $captured_errors  = array();

   # Statistics
    var $stats            = array(
                              'actions' => array(
                                'insert'  => 0 ,
                                'update'  => 0 ,
                                'replace' => 0 ,
                                'select'  => 0 ,
                                'delete'  => 0
                                ) ,
                              'time'    => 0.0
                              );
    var $num_queries      = 0;
    var $querylog         = array();

   # Caching
    var $cache            = true;
    var $cache_dir        = 'SQueLCH_cache';
    var $cache_lifetime   = '2 minutes'; // Time in format readable by strtotime()
    var $cache_actions    = array( 'select' , 'show' , 'desc' );
    var $cache_threshold  = 10; // (ms) Queries which take longer that this will be cached



  //===========================================================================
  // Class Subfunctions *******************************************************
  //===========================================================================


  // SQL Subfunctions ********************************************* Constructor
  //===========================================================================

   # Connect to SQL Database Source
    function SQueLCH( $dbType=false , $dbUser=false , $dbPass=false , $dbBase=false , $dbHost='localhost' , $dbPort=false ) {
     # Check for Compiled DB Details
      if( is_array( $dbType ) ){
        $dbArr = $dbType;
        if( isset( $dbArr['type'] ) ) $dbType = $dbArr['type'];
        if( isset( $dbArr['user'] ) ) $dbUser = $dbArr['user'];
        if( isset( $dbArr['pass'] ) ) $dbPass = $dbArr['pass'];
        if( isset( $dbArr['base'] ) ) $dbBase = $dbArr['base'];
        if( isset( $dbArr['host'] ) ) $dbHost = $dbArr['host'];
        if( isset( $dbArr['port'] ) ) $dbPort = $dbArr['port'];
      }
     # Check Database Type
      if( $dbType!==false && !in_array( $dbType=strtolower( $dbType ) , $this->dbTypes ) ) {
       // Invalid Database Type Selected.
        $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ );
        return false;
      } else {
        if( $dbType!==false ) {
         // New Database Type Selected
          $this->dbType = $dbType;
        }
      }
     # Check Dependancies
      $this->checkDependancy();
     # Perform Connection Action
      switch( $this->dbType ) {
        case 'mssql' :
         # Must have a user and a password
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' , __FILE__ , __LINE__ );
            return false;
          }
         # Try to establish Database Handle
          if( !$this->dbHandle = @mssql_connect( $dbHost , $dbUser , $dbPass ) ) {
            $this->register_error( 'Unable to connect to MSSQL Source' , __FILE__ , __LINE__ );
            return false;
          }
         # Connect to Database
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' , __FILE__ , __LINE__ );
            return false;
          }
          return (bool) $this->selectBase( $dbBase );
          break;
        case 'mysql' :
         # Must have a user and a password
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' , __FILE__ , __LINE__ );
            return false;
          }
         # Try to establish Database Handle
          if( !$this->dbHandle = @mysql_connect( $dbHost.( $inPort ? ":$inPort" : '' ) , $dbUser , $dbPass , true ) ) {
            $this->register_error( 'Unable to connect to MySQL Source' , __FILE__ , __LINE__ );
            return false;
          }
         # Connect to Database
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' , __FILE__ , __LINE__ );
            return false;
          }
          return (bool) $this->selectBase( $dbBase );
          break;
        case 'oracle' :
         # Must have a username, a password and database namse
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' , __FILE__ , __LINE__ );
            return false;
          }
         # Must have Database Name
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' , __FILE__ , __LINE__ );
            return false;
          }
         # Try to establish the server database handle
          if( !$this->dbHandle = @OCILogon( $dbUser , $dbPass , $dbBase ) ) {
            $this->register_error( 'Unable to connect to Oracle Source' , __FILE__ , __LINE__ );
            return false;
          }
          return true;
          break;
        case 'postgresql' :
         # Must have a username, a password and database namse
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' , __FILE__ , __LINE__ );
            return false;
          }
         # Must have Database Name
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' , __FILE__ , __LINE__ );
            return false;
          }
         # Try to establish the server database handle
          if( !$this->dbHandle = @pg_connect( "host=$dbHost user=$dbUser password=$dbPass dbname=$dbBase".($inPort?" port=$inPort":'') , true ) ) {
            $this->register_error( 'Unable to connect to PostgreSQL Source' , __FILE__ , __LINE__ );
            return false;
          }
          return true;
          break;
        case 'sqlite' :
         # Turn on track errors
          ini_set( 'track_errors' , 1 );
         # Must have a username, a password and database name
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' , __FILE__ , __LINE__ );
            return false;
          }
         # Try to establish the server database handle
          if( !$this->dbHandle = @sqlite_open( $dbBase ) ) {
            $this->register_error( 'Unable to connect to SQLite Source' , __FILE__ , __LINE__ );
            return false;
          }
          return true;
          break;
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ );
      }
    }

   # Select Database
    function selectBase( $inDBName ) {
      switch( $this->dbType ) {
        case 'mssql' :
         # Check for Database Name
          if( !$inDBName ) {
            $this->register_error( 'Missing Database Name' , __FILE__ , __LINE__ );
            return false;
          }
         # Check for Database Handle
          if( !$this->dbHandle ) {
            $this->register_error( 'No Database Connection Available' , __FILE__ , __LINE__ );
            return false;
          }
         # Attempt to Connect to Database
          if( !@mssql_select_db( $inDBName , $this->dbHandle ) ) {
            $this->register_error( 'Unexpected error while trying to select database' , __FILE__ , __LINE__ );
            return false;
          }
          return true;
          break;
        case 'mysql' :
         # Check for Database Name
          if( !$inDBName ) {
            $this->register_error( 'Missing Database Name' , __FILE__ , __LINE__ );
            return false;
          }
         # Check for Database Handle
          if( !$this->dbHandle ) {
            $this->register_error( 'No Database Connection Available' , __FILE__ , __LINE__ );
            return false;
          }
         # Attempt to Connect to Database
          if( !@mysql_select_db( $inDBName , $this->dbHandle ) ) {
            $errMsg = ( @mysql_error( $this->dbHandle ) ? mysql_error( $this->dbHandle ) : 'Unexpected error while trying to select database' );
            $this->register_error( $errMsg , __FILE__ , __LINE__ );
            return false;
          }
          return true;
          break;
        case 'oracle' :
          $this->register_error( 'No Select Function Available for Oracle' , __FILE__ , __LINE__ );
          break;
        case 'postgresql' :
          $this->register_error( 'No Select Function Available for Oracle' , __FILE__ , __LINE__ );
          break;
        case 'sqlite' :
          $this->register_error( 'No Select Function Available for Oracle' , __FILE__ , __LINE__ );
          break;
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ );
      }
    }


  // SQL Subfunctions ****************************************** Administrative
  //===========================================================================

   # Check Dependancies
    function checkDependancy() {
      switch( $this->dbType ) {
        case 'mssql' :
          if( !function_exists( 'mssql_connect' ) ) {
            $this->register_error( '<b>SQueLCH Fatal Error:</b> Requires ntwdblib.dll to be present in your winowds\system32 folder. Also enable MSSQL extenstion in PHP.ini file' , __FILE__ , __LINE__ );
            die();
          }
          break;
        case 'mysql' :
          if( !function_exists ('mysql_connect') ) {
            $this->register_error( '<b>SQueLCH Fatal Error:</b> Requires mySQL Lib to be compiled and or linked in to the PHP engine' , __FILE__ , __LINE__ );
            die();
          }
          break;
        case 'oracle' :
          if( !function_exists( 'OCILogon' ) ) {
            $this->register_error( '<b>SQueLCH Fatal Error:</b> Requires Oracle OCI Lib to be compiled and/or linked in to the PHP engine' , __FILE__ , __LINE__ );
            die();
          }
          break;
        case 'postgresql' :
          if( !function_exists( 'pg_connect' ) ) {
            $this->register_error( '<b>SQueLCH Fatal Error:</b> Requires PostgreSQL Lib to be compiled and or linked in to the PHP engine' , __FILE__ , __LINE__ );
            die();
          }
          break;
        case 'sqlite' :
          if( !function_exists( 'sqlite_open' ) ) {
            $this->register_error( '<b>SQueLCH Fatal Error:</b> Requires SQLite Lib to be compiled and or linked in to the PHP engine' , __FILE__ , __LINE__ );
            die();
          }
          break;
        default :
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ );
          die();
      }
    }


  // SQL Subfunctions ********************************************* Basic Query
  //===========================================================================

   # Basic Query
    function query( $query , $ignoreCache=false ) {
     # Flush cached values..
      $this->flush();
     # Tidy up the Query
      $query = trim( $query );
     # Keep track of the last query for debug..
      $this->last_query = $query;
     # Count how many queries there have been by type
      $queryType = explode( ' ' , $query , 2 );$queryType = strtolower( $queryType[0] );
      $this->stats['actions'][$queryType]++;
     # Log how the function was called
      $this->func_call = '$db->query( "'.$query.'" )';
     # Init a Log Element
      $log = array(
        'query'  => $query ,
        'source' => $this->dbType
      );
     # Use Cache, if Available
      if( !$ignoreCache
          && $this->cache
          && in_array( $queryType , $this->cache_actions )
          && $cache=$this->cache_get( $query ) ) {
       # Add to Query Log
        $log['source'] = 'cache';
        $log['result'] = 'success';
        $log['time']   = $cache['time'];
        $this->querylog[] = $log;
       # Return the Extracted Value
        return $cache['data'];
      }
     # If there is no existing database connection then try to connect
      if( !$this->dbHandle ) {
        $this->register_error( 'No Database Connection Available' , __FILE__ , __LINE__ );
       # Add to Query Log
        $log['source'] = $this->dbType;
        $log['result'] = 'failure';
        $log['time']   = null;
        $this->querylog[] = $log;
        return false;
      }
     # Perform Query based on Database Type
      switch( $this->dbType ) {
        case 'mssql' :
         # Adjust the SQL Syntax
          $query = $this->convertMySqlToMSSql( $query );
         # Perform the query via std mssql_query function..
          $start = array_sum( explode( ' ' , microtime( true ) ) );
          $this->result = @mssql_query( $query );
          $log['time'] = array_sum( explode( ' ' , microtime( true ) ) )-$start;
         # If there is an error then take note of it..
          if( $this->result==false ) {
            $get_errorcodeSql = 'SELECT @@ERROR as errorcode';
            $error_res = @mssql_query( $get_errorcodeSql , $this->dbHandle );
            $errorCode = @mssql_result( $error_res , 0 , 'errorcode' );
            $get_errorMessageSql = 'SELECT severity as errorSeverity, text as errorText FROM sys.messages WHERE message_id = '.$errorCode  ;
            if( $errormessage_res =  @mssql_query( $get_errorMessageSql , $this->dbHandle ) ) {
              $errorMessage_Row = @mssql_fetch_row( $errormessage_res );
              $errorSeverity = $errorMessage_Row[0];
              $errorMessage = $errorMessage_Row[1];
            }
            $this->register_error( 'ErrorCode: '.$errorCode.' ### Error Severity: '.$errorSeverity.' ### Error Message: '.$errorMessage.' ### Query: '.$query );
           # Add to Query Log
            $log['result'] = 'error';
            $this->querylog[] = $log;
            return false;
          }
         # Query was an insert, delete, update, replace
          if( in_array( $queryType , array( 'insert' , 'delete' , 'update' , 'replace' ) ) ) {
            $this->rows_affected = @mssql_rows_affected( $this->dbh );
           # Take note of the insert_id
            if( in_array( $queryType , array( 'insert' , 'replace' ) ) ) {
              if( ( $identityresultset = @mssql_query( 'SELECT SCOPE_IDENTITY()' ) )!=false ) {
                $identityrow = @mssql_fetch_row( $identityresultset );
                $this->insert_id = $identityrow[0];
              }
            }
           # Return number of rows affected
            $return_val = $this->rows_affected;
          } else {
           # Query was a select
           # Take note of column info
            for( $i = 0 ; $i<@mssql_num_fields( $this->result ) ; $i++ )
              $this->col_info[$i] = @mssql_fetch_field( $this->result );
           # Store Query Results
            $num_rows = 0;
            while( $row = @mssql_fetch_object( $this->result ) ) {
             # Store relults as an objects within main array
              $this->last_result[$num_rows] = $row;
              $num_rows++;
            }
            @mssql_free_result( $this->result );
           # Log number of rows the query returned
            $this->num_rows = $num_rows;
           # Return number of rows selected
            $return_val = $this->num_rows;
          }
          break;
        case 'mysql' :
         # Perform the query via std mysql_query function..
          $start = array_sum( explode( ' ' , microtime( true ) ) );
          $this->result = @mysql_query( $query , $this->dbHandle );
          $log['time'] = array_sum( explode( ' ' , microtime( true ) ) )-$start;
         # If there is an error then take note of it..
          if( $str = @mysql_error( $this->dbHandle ) ) {
            $this->register_error( $str , __FILE__ , __LINE__ );
           # Add to Query Log
            $log['result'] = 'error';
            $this->querylog[] = $log;
            return false;
          }
          if( in_array( $queryType , array( 'insert' , 'delete' , 'update' , 'replace' ) ) ) {
           // Query was an insert, delete, update, replace
            $this->rows_affected = @mysql_affected_rows();
           # Take note of the insert_id
            if( in_array( $queryType , array( 'insert' , 'replace' ) ) )
              $this->insert_id = @mysql_insert_id( $this->dbHandle );
           # If Only One Row Affected, and was an Insert
            if( $this->rows_affected==1 && $queryType=='insert' ){
             # Return the ID of the Inserted Row
              $return_val = $this->insert_id;
            }else{
             # Return number of rows affected
              $return_val = $this->rows_affected;
            }
          } else {
           // Query was a select
           # Take note of column info
            for( $i = 0 ; $i<@mysql_num_fields( $this->result ) ; $i++ )
              $this->col_info[$i] = @mysql_fetch_field( $this->result );
           # Store Query Results
            $num_rows = 0;
            while( $row = @mysql_fetch_object( $this->result ) ) {
             # Store relults as an objects within main array
              $this->last_result[$num_rows] = $row;
              $num_rows++;
            }
            @mysql_free_result( $this->result );
           # Log number of rows the query returned
            $this->num_rows = $num_rows;
           # Return number of rows selected
            $return_val = $this->num_rows;
          }
          break;
        case 'oracle' :
         # Parses the query and returns a statement..
          if( !$stmt = OCIParse( $this->dbHandle , $query ) ) {
            $error = OCIError( $this->dbHandle );
            $this->register_error( $error['message'] , __FILE__ , __LINE__ );
            $log['result'] = 'error';
            $this->querylog[] = $log;
            return false;
          }
         # Execute the query..
          if( !$this->result = OCIExecute( $stmt ) ) {
            $error = OCIError( $stmt );
            $this->register_error( $error['message'] , __FILE__ , __LINE__ );
            $log['result'] = 'error';
            $this->querylog[] = $log;
            return false;
          }
          if( in_array( $queryType , array( 'insert' , 'delete' , 'update' , 'replace' ) ) ) {
           // Query was an insert
           # Return number of affected rows
            $return_value = $this->rows_affected = @OCIRowCount( $stmt );
          } else {
           // Query was a select
           # Get column information
            if( $num_cols = @OCINumCols( $stmt ) ) {
             # Fetch the column meta data
              for( $i = 0 ; $i<$num_cols ; $i++ ) {
                $this->col_info[$i]->name = @OCIColumnName( $stmt , ($i+1) );
                $this->col_info[$i]->type = @OCIColumnType( $stmt , ($i+1) );
                $this->col_info[$i]->size = @OCIColumnSize( $stmt , ($i+1) );
              }
            }
           # If there are any results then get them
            if( $this->num_rows = @OCIFetchStatement( $stmt , $results ) ) {
             # Convert results into object orientated results..
              foreach( (array) $results as $col_title => $col_contents ) {
                $row_num = 0;
               # then - loop through rows
                foreach( (array) $col_contents as $col_content ) {
                  $this->last_result[$row_num]->{$col_title} = $col_content;
                  $row_num++;
                }
              }
            }
           # num result rows
            $return_value = $this->num_rows;
          }
          break;
        case 'postgresql' :
         # Perform the query via std postgresql_query function..
          $this->result = @pg_query( $this->dbHandle , $query );
         # If there is an error then take note of it..
          if( $str = @pg_last_error( $this->dbHandle ) ) {
            $this->register_error( $str , __FILE__ , __LINE__ );
            $log['result'] = 'error';
            $this->querylog[] = $log;
            return false;
          }
          if( in_array( $queryType , array( 'insert' , 'delete' , 'update' , 'replace' ) ) ) {
           // Query was an insert, delete, update, replace
            $this->rows_affected = @pg_affected_rows( $this->result );
           # Take note of the insert_id
            if( in_array( $queryType , array( 'insert' , 'replace' ) ) )
              $this->insert_id = pg_last_oid( $this->result );
           # Return number fo rows affected
            $return_val = $this->rows_affected;
          } else {
           // Query was a select
            $num_rows = 0;
           # Take note of column info
            $i=0;
            while( $i<@pg_num_fields( $this->result ) ) {
              $this->col_info[$i]->name = pg_field_name( $this->result , $i );
              $this->col_info[$i]->type = pg_field_type( $this->result , $i );
              $this->col_info[$i]->size = pg_field_size( $this->result , $i );
              $i++;
            }
           # Store Query Results
            while( $row = @pg_fetch_object( $this->result ) ) {
             # Store results as an objects within main array
              $this->last_result[$num_rows] = $row ;
              $num_rows++;
            }
            @pg_free_result( $this->result );
           # Log number of rows the query returned
            $this->num_rows = $num_rows;
           # Return number of rows selected
            $return_val = $this->num_rows;
          }
          break;
        case 'sqlite' :
         # For reg expressions
          $query = str_replace( "/[\n\r]/" , '' , trim( $query ) );
         # Perform the query via std mysql_query function..
          $this->result = @sqlite_query( $this->dbHandle , $query );
         # If there is an error then take note of it..
          if( @sqlite_last_error( $this->dbHandle ) ) {
            $err_str = sqlite_error_string( sqlite_last_error( $this->dbHandle ) );
            $this->register_error( $err_str , __FILE__ , __LINE__ );
            $log['result'] = 'error';
            $this->querylog[] = $log;
            return false;
          }
          if( in_array( $queryType , array( 'insert' , 'delete' , 'update' , 'replace' ) ) ) {
           // Query was an insert, delete, update, replace
            $this->rows_affected = @sqlite_changes( $this->dbHandle );
           # Take note of the insert_id
            if( in_array( $queryType , array( 'insert' , 'replace' ) ) )
              $this->insert_id = @sqlite_last_insert_rowid( $this->dbHandle );
           # Return number fo rows affected
            $return_val = $this->rows_affected;
          } else {
           // Query was an select
           # Take note of column info
            $i=0;
            for( $i = 0 ; $i<@sqlite_num_fields( $this->result ) ; $i++ ) {
              $this->col_info[$i]->name       = sqlite_field_name( $this->result , $i );
              $this->col_info[$i]->type       = null;
              $this->col_info[$i]->max_length = null;
            }
           # Store Query Results
            $num_rows = 0;
            while( $row = @sqlite_fetch_array( $this->result , SQLITE_ASSOC ) ) {
             # Store relults as an objects within main array
              $obj = (object) $row; //convert to object
              $this->last_result[$num_rows] = $obj;
              $num_rows++;
            }
           # Log number of rows the query returned
            $this->num_rows = $num_rows;
           # Return number of rows selected
            $return_val = $this->num_rows;
          }
          break;
        default :
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ );
      }
     # Disk caching of queries
      if( $this->cache
          && $this->cache_threshold/1000 < $log['time']
          && in_array( $queryType , $this->cache_actions ) ) {
        $this->cache_put( $query );
      }
     # If debug ALL queries
      if( $this->debug_all )
        $this->debug();
     # Add to Query Log
      $log['result'] = 'success';
      $this->querylog[] = $log;
     # Return response
      return $return_val;
    }


  // PHP Subfunctions *************************************** Variable Handling
  //===========================================================================

   # Flush SQL Variables
    function flush() {
     # Get rid of these
      $this->last_result     = null;
      $this->col_info        = null;
      $this->last_query      = null;
      $this->cache_used      = false;
    }

   # Get one variable
    function get_var( $query=null , $x=0 , $y=0 ) {
     # Allow for Arrayed Parameters
      if( is_array( $x ) ){
        $paramArr = $x;
        if( isset( $paramArr['x'] ) );           $x           = $paramArr['x'];
        if( isset( $paramArr['y'] ) );           $y           = $paramArr['y'];
        if( isset( $paramArr['ignoreCache'] ) ); $ignoreCache = $paramArr['ignoreCache'];
      }
     # Log how the function was called
      $this->func_call = '$db->get_var( "'.$query.'" , '.$x.' , '.$y.' )';
     # If there is a query then perform it if not then use cached results..
      if( $query )
        $this->query( $query , $ignoreCache );
     # Extract var out of cached results based x,y vals
      if( $this->last_result[$y] )
        $values = array_values( get_object_vars( $this->last_result[$y] ) );
     # If there is a value return it else return null
      return ( ( isset( $values[$x] ) && $values[$x]!=='' ) ? $values[$x] : null );
    }

   # Get one row
    function get_row( $query=null , $output='OBJECT' , $y=0 ) {
     # Allow for Arrayed Parameters
      if( is_array( $output ) ){
        $paramArr = $output;
        if( isset( $paramArr['output'] ) );      $output       = $paramArr['output'];
        if( isset( $paramArr['y'] ) );           $y            = $paramArr['y'];
        if( isset( $paramArr['ignoreCache'] ) ); $ignoreCache  = $paramArr['ignoreCache'];
      }
     # Log how the function was called
      $this->func_call = '$db->get_row( "'.$query.'" , '.$output.' , '.$y.' )';
     # If there is a query then perform it if not then use cached results..
      if( $query )
        $this->query( $query , $use_cache );
      if( !$this->last_result[$y] )
        return null;
     # Format Output dependent on Requested Object
      switch ( $output ) {
        case 'OBJECT' :
         # If the output is an object then return object using the row offset..
          return $this->last_result[$y];
        case 'ARRAY_A' :
         # If the output is an associative array then return row as such..
          return (array) get_object_vars( $this->last_result[$y] );
        case 'ARRAY_N' :
         # If the output is an numerical array then return row as such..
          return (array) array_values( get_object_vars( $this->last_result[$y] ) );
        default :
         # If invalid output type was specified..
          $this->print_error( '$db->get_row( string query , output type , int offset ) -- Output type must be one of: "OBJECT", "ARRAY_A", "ARRAY_N"' );
          return null;
      }
    }

   # Get one column
    function get_col( $query=null , $x=0 ) {
     # Allow for Arrayed Parameters
      if( is_array( $output ) ){
        $paramArr = $x;
        if( isset( $paramArr['x'] ) );           $x           = $paramArr['x'];
        if( isset( $paramArr['ignoreCache'] ) ); $ignoreCache = $paramArr['ignoreCache'];
      }
     # Log how the function was called
      $this->func_call = '$db->get_col( "'.$query.'" , '.$x.' )';
     # If there is a query then perform it if not then use cached results..
      if( $query )
        $this->query( $query , $use_cache );
     # Extract the column values
      for( $i=0 ; $i<count( $this->last_result ) ; $i++ )
        $new_array[$i] = $this->get_var( null , $x , $i );
     # Return value array
      return $new_array;
    }

   # Return the the query as a result set
    function get_results( $query=null , $output='OBJECT' ) {
     # Allow for Arrayed Parameters
      if( is_array( $output ) ){
        $paramArr = $output;
        if( isset( $paramArr['output'] ) );      $output      = $paramArr['output'];
        if( isset( $paramArr['ignoreCache'] ) ); $ignoreCache = $paramArr['ignoreCache'];
      }
     # Log how the function was called
      $this->func_call = '$db->get_results( "'.$query.'" , '.$output.' )';
     # If there is a query then perform it if not then use cached results..
      if( $query )
        $this->query( $query , $use_cache );
     # Format Output dependent on Requested Object
      switch( $output ) {
        case 'OBJECT' :
         # Send back array of objects. Each row is an object
          return $this->last_result;
        case 'ARRAY_A' :
        case 'ARRAY_N' :
          if( $this->last_result ) {
            $i=0;
            foreach( (array) $this->last_result as $row ) {
              $new_array[$i] = get_object_vars( $row );
              if( $output=='ARRAY_N' )
                $new_array[$i] = array_values( $new_array[$i] );
              $i++;
            }
            return $new_array;
          } else {
            return array();
          }
        default :
          return null;
      }
    }

   # Get column meta data info pertaining to the last query
    function get_col_info( $info_type='name' , $col_offset=-1 ) {
      if( $this->col_info ) {
        if( $col_offset == -1 ) {
          $i=0;
          foreach( (array) $this->col_info as $col ) {
            $new_array[$i] = $col->{$info_type};
            $i++;
          }
          return $new_array;
        } else {
          return $this->col_info[$col_offset]->{$info_type};
        }
      }
    }


  // SQL Subfunctions *************************************** Syntax Adjustment
  //===========================================================================

   # Format a string correctly for safe insert
    function escape( $inString ) {
      switch( $this->dbType ) {
        case 'mssql' :
          return str_ireplace( "'" , "''" , $inString );
        case 'mysql' :
          if( function_exists( 'mysql_real_escape_string' ) )
            return mysql_real_escape_string( stripslashes( $inString ) , $this->dbHandle );
          return mysql_escape_string( stripslashes( $inString ) );
        case 'oracle' :
          return str_replace( "'" , "''" , str_replace( "''" , "'" , stripslashes( $inString ) ) );
        case 'postgresql' :
          return pg_escape_string( stripslashes( $inString ) );
        case 'sqlite' :
          return sqlite_escape_string( stripslashes( preg_replace( "/[\r\n]/" , '' , $inString ) ) );
        default :
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ );
          return $inString;
      }
    }

   # Return database specific system date syntax
    function sysdate() {
      switch( $this->dbType ) {
        case 'mssql'      : return 'getDate()';
        case 'mysql'      : return 'NOW()';
        case 'oracle'     : return 'SYSDATE';
        case 'postgresql' : return 'NOW()';
        case 'sqlite'     : return 'now';
        default           : $this->register_error( 'Invalid Database Type "'.$dbType.'"' , __FILE__ , __LINE__ ); return date( 'Y/m/d H:i:s' );
      }
    }

   # Convert MySQL syntax to MSSQL
    function convertMySqlToMSSql( $query ) {
     # Replace the '`' character used for MySql queries, but not supported in MSSql
      $query = str_replace( '`' , '' , $query );
     # Replace From UnixTime command in MSSql, doesn't work
      $pattern = 'FROM_UNIXTIME\(([^/]{0,})\)';
      $replacement = 'getdate()';
      $query = eregi_replace( $pattern , $replacement , $query );
     # Replace LIMIT keyword with TOP keyword. Works only on MySql not on MS-Sql
      $pattern = 'LIMIT[^\w]{1,}([0-9]{1,})([\,]{0,})([0-9]{0,})';
      $replacement = '';
      eregi( $pattern , $query , $regs );
      $query = eregi_replace( $pattern , $replacement , $query );
      if( $regs[2] )
        $query = str_ireplace( 'SELECT ' , 'SELECT TOP '.$regs[3].' ' , $query );
      elseif( $regs[1] )
        $query  = str_ireplace( 'SELECT ' , 'SELECT TOP '.$regs[1].' ' , $query );
     # Replace unix_timestamp function. Doesn't work in MS-Sql
      $pattern = 'unix_timestamp\(([^/]{0,})\)';
      $replacement = '\\1';
      $query = eregi_replace( $pattern , $replacement , $query );
      return $query;
    }

   # Determine the Insert ID for Oracle
    function oracle_insert_id( $seq_name ) {
      $return_val = $this->get_var( "SELECT $seq_name.nextVal id FROM Dual" );
      // If no return value then try to create the sequence
      if( !$return_val ) {
        $this->query( "CREATE SEQUENCE $seq_name maxValue 9999999999 INCREMENT BY 1 START WITH 1 CACHE 20 CYCLE" );
        $return_val = $this->get_var( "SELECT $seq_name.nextVal id FROM Dual" );
        $this->register_error( $SQueLCH_oracle8_9_str[2].": $seq_name"  , __FILE__ , __LINE__ );
        $this->show_errors ? trigger_error( $SQueLCH_oracle8_9_str[2].": $seq_name" , E_USER_NOTICE ) : null;
      }
      return $return_val;
    }


  // PHP Subfunctions ****************************************** Error Handling
  //===========================================================================

   # Print SQL/DB error - over-ridden by specific DB class
    function register_error( $errMsg , $errFile , $errLine , $errLevel=E_USER_WARNING ) {
     # Keep track of last error
      $this->last_error = $err_str;
     # Capture all errors to an error array no matter what happens
      $this->captured_errors[] = array (
        'error_str' => $errMsg.' in '.$errFile.' at Line #'.$errLine ,
        'query'     => $this->last_query
        );
     # Output to PHP Native Error Handler
      if( $this->show_errors )
        trigger_error( $errMsg , $errLevel );
     # Record to Logfile
      if( $this->error_file )
        @file_put_contents( $this->cache_dir.'/'.$this->error_file , date('Y/m/d H:i:s')."\n  File:  ".$_SERVER['REQUEST_URI']."\n  Query: {$this->last_query}\n  Error: {$errMsg}\n\n" , FILE_APPEND );
    }

   # Turn error handling on or off..
    function show_errors() { $this->show_errors = true;  }
    function hide_errors() { $this->show_errors = false; }


  // PHP Subfunctions **************************************** Cache Management
  //===========================================================================

   # Cache - Filename
    function cache_filename( $query ) {
      $filename = array(
        md5( $this->dbType . $this->dbHost . $this->dbPort . $this->dbBase ) ,
        md5( $query )
      );
      return ( $this->cache_dir ? $this->cache_dir.'/' : '' ).join( '_' , $filename ).'.ser';
    }

   # Cache - Store
    function cache_put( $query ) {
     # The would be cache file for this query
      $cache_file = $this->cache_filename( $query );
     # disk caching of queries
      if( $this->cache_dir && !is_dir( $this->cache_dir ) && !@mkdir( $this->cache_dir ) ) {
        $this->register_error( 'Could not open or create cache dir: '.$this->cache_dir  , __FILE__ , __LINE__ );
        $this->show_errors ? trigger_error( 'Could not open or create cache dir: '.$this->cache_dir , E_USER_WARNING ) : null;
        return false;
      } else {
       # Cache all result values
        $result_cache = array (
          'col_info'     => $this->col_info ,
          'last_result'  => $this->last_result ,
          'num_rows'     => $this->num_rows ,
          'return_value' => $this->num_rows
        );
        if( !function_exists( 'file_put_contents' ) ) {
          error_log( serialize( $result_cache ) , 3 , $cache_file );
        } else {
          file_put_contents( $cache_file , serialize( $result_cache ) );
        }
        return file_exists( $cache_file );
      }
    }

   # Cache - Retrieve
    function cache_get( $query ) {
     # The would be cache file for this query
      $cache_file = $this->cache_filename( $query );
     # Try to get previously cached version
      if( file_exists( $cache_file ) ) {
       # Check Age of Cache
        if( filemtime( $cache_file ) < strtotime( '-'.$this->cache_lifetime ) ) {
         # Cache is Expired - Removing
          $this->cache_expire( $cache_file );
          return false;
        }
       # Cache is Valid
        $start = array_sum( explode( ' ' , microtime( true ) ) );
        $result_cache = unserialize( file_get_contents( $cache_file ) );
        $time = array_sum( explode( ' ' , microtime( true ) ) )-$start;
        $this->col_info        = $result_cache['col_info'];
        $this->last_result     = $result_cache['last_result'];
        $this->num_rows        = $result_cache['num_rows'];
        $this->cache_used      = true;
       # If debug ALL queries
        if( $this->debug_all )
          $this->debug();
        return array( 'data' => $result_cache['return_value'] , 'time' => $time );
      }
      return false;
    }

   # Cache - Expire
    function cache_expire( $queryORfilename ) {
      if( file_exists( $queryORfilename ) )
        return @unlink( $queryORfilename );
      return @unlink( $this->cache_filename( $queryORfilename ) );
    }

   # Cache - Remove Old Files
    function cache_clean() {
     # Get all the Cachefiles
      $cachefiles = glob( $this->cache_dir.'/*.ser' );
     # Determine the Cutoff Time for Cachefiles, according to the Lifetime
      $cutofftime = strtotime( '-'.$this->cache_lifetime );
     # Loop through the Cachefiles
      foreach( $cachefiles as $k => $v ){
       # If the File was Modified before the Cutoff Time
        if( filemtime( $v )<$cutofftime ){
         # Remove it.
          unlink( $v );
        }
      }
    }

   # Turn Caching on or off..
    function cache_on()  { $this->cache = true;  }
    function cache_off() { $this->cache = false; }

  // PHP Subfunctions ********************************** Debugging and Analytic
  //===========================================================================

   # Dump the contents of any input variable
    function vardump( $mixed ) { $this->debug( $mixed ); }
    function dumpvar( $mixed ) { $this->debug( $mixed ); }

   # Displays the last query string that was sent to the database & lists results (if there were any).
    function debug() {
     # Start outup buffering
      ob_start();
      echo '<div class="SQueLCH" style="border:2px solid #EEC900;background-color:#FFEC8B;font:12px Arial;padding:0 10px;">';
      if( func_num_args() ) {
       // VAR DUMP
        $this->vardump_called = true;
       # Only show SQueLCH credits once..
        if( !$this->vardump_called || true )
          echo '<h2 style="color:#808;">SQueLCH (v'.$this->version.') Variable Dump..</h2>';
        echo '<dl style="color:#009;padding-left:20px;">';
        $mixed = func_get_arg( 0 );
        $var_type = gettype( $mixed );
        echo '<dt style="font-weight:bold;">Variable Value</dt>';
          echo '<dd style="color:#000;">';
            print_r( ( $mixed ? '<pre>'.$mixed.'</pre>' : '<span style="color:red;">No Value / False</span>' ) );
          echo '</dd>';
        echo '<dt style="font-weight:bold;">Type</dt>';
          echo '<dd style="color:#000;">'.ucfirst( $var_type ).'</dd>';
        echo '</dl>';
      } else {
       // DEBUG
        $this->debug_called = true;
       # Only show SQueLCH credits once..
        if( !$this->debug_called || true )
          echo '<h2 style="color:#808;">SQueLCH (v'.$this->version.') Debug..</h2>';
        echo '<dl style="color:#009;padding-left:20px;">';
        echo '<dt style="font-weight:bold;">Last Error</dt>';
          echo '<dd style="color:#000;">';
            echo ( $this->last_error ? $this->last_error : '<em>No Last Error</em>' );
          echo '</dd>';
        echo '<dt style="font-weight:bold;">Disk Cache</dt>';
          echo '<dd style="color:#000;">'.( $this->cache_used ? 'Used' : '<em>Not Used</em>' ).'</dd>';
        echo '<dt style="font-weight:bold;">Query ['.( count( $this->querylog )-1 ).']</dt>';
          echo '<dd style="color:#000;">'.$this->last_query.'</dd>';
        echo '</dl>';
        echo '<table style="background-color:#555;margin-left:40px;" cellpadding="5" cellspacing="1">';
        echo '<caption style="white-space:nowrap;color:#009;font-weight:bold;font-size:12px;">Query Results</caption>';
        if( $this->col_info ) {
         # Results top rows
          echo '<thead><tr style="background-color:#EEE;">';
          echo '<td style="color:#559;font:bold 12px Arial;white-space:nowrap;text-align:center;" valign="bottom">(row)</td>';
          for ( $i=0 ; $i<count( $this->col_info ) ; $i++ ) {
            echo '<td nowrap align=left valign=top>';
              echo '<span style="color:#559;font-size:9px;">'.$this->col_info[$i]->type.' '.$this->col_info[$i]->max_length.'</span><br>';
              echo '<span style="color:#000;font-size:12px;font-weight:bold;">`'.$this->col_info[$i]->name.'`</span>';
            echo '</td>';
          }
          echo '</tr></thead>';
         # Print main results
          if( $this->last_result ) {
            echo '<tfoot><tr>';
              echo '<td colspan="'.( count( $this->col_info )+1 ).'" style="background-color:#EEE;text-align:center;font-size:9px;color:#559;">'.$this->num_rows.' Rows Returned</td>';
            echo '</tr></tfoot>';
            echo '<tbody style="background-color:#FFF;">';
            $i=0;
            foreach( (array) $this->get_results( null , 'ARRAY_N' ) as $one_row ) {
              $i++;
              echo '<tr><th style="background-color:#EEE;text-align:center;font-size:14px;color:#559;" nowrap>'.$i.'</th>';
              foreach( (array) $one_row as $item )
                echo '<td style="font-size:13px;color:#559;" nowrap>'.htmlentities( $item ).'</td>';
              echo '</tr>';
            }
            echo '</tbody>';
          } else {
            echo '<tfoot><tr>';
              echo '<td colspan="'.( count( $this->col_info )+1 ).'" style="background-color:#EEE;text-align:center;font-size:9px;color:#559;"><em>No Rows Returned</em></td>';
            echo '</tr></tfoot>';
          }
        } else {
            echo '<tfoot><tr>';
              echo '<td style="background-color:#EEE;text-align:center;font-size:9px;color:#559;"><em>No Rows Returned</em></td>';
            echo '</tr></tfoot>';
        }
        echo '</table>';
      }
      echo '</div>';
     # Stop output buffering and capture debug HTML
      $html = ob_get_contents();
      ob_end_clean();
     # Only echo output if it is turned on
      if( $this->debug_echo_is_on )
        echo $html;
      return $html;
    }

   # Display a Breakdown of All Queries Performed
    function show_querylog() {
      echo '<div class="SQueLCH" style="border:2px solid #EEC900;background-color:#FFEC8B;font:12px Arial;padding:0 10px;">';
      echo '<h2 style="color:#808;">SQueLCH (v'.$this->version.') Query Log..</h2>';
      echo '<table style="background-color:#555;margin-left:40px;" cellpadding="5" cellspacing="1">';
        echo '<caption style="white-space:nowrap;color:#009;font-weight:bold;font-size:12px;">Query Log</caption>';
        echo '<thead style="background-color:#EEE;color:#000;font-size:12px;font-weight:bold;"><tr><th style="color:#559;" valign="bottom">(ID)</th><td><span style="color:#559;font-size:9px;">string</span><br><span style="color:#000;font-size:12px;font-weight:bold;">SQL Query</span></td><td><span style="color:#559;font-size:9px;">milliseconds</span><br><span style="color:#000;font-size:12px;font-weight:bold;">Response</span></td><td><span style="color:#000;font-size:12px;font-weight:bold;">Source</span></td></tr></thead>';
        if( !count( $this->querylog ) ) {
          echo '<tfoot style="background-color:#EEE;text-align:center;font-size:9px;color:#559;font-family:Arial;"><tr><th colspan="3" style="background-color:#EEE;text-align:center;font-size:9px;color:#559;">No Queries Logged</th></tr></tfoot>';
        } else {
          $out = array();
          $totaltime = 0;
          $rowback = array(
            'success' => '#CFC' ,
            'error'   => '#EDD' ,
            'failure' => '#EBB'
          );
          foreach( $this->querylog as $id => $event ) {
            $out[] = sprintf( '<tr style="background-color:%s" title="SQL Query was %s">'.
                                '<th style="background-color:#EEE;text-align:center;font-size:14px;">%s</th>'.
                                '<td>%s</td>'.
                                '<td style="text-align:right;">%s</td>'.
                                '<td>%s</td>'.
                              '</tr>' ,
                       $rowback[ strtolower( $event['result'] ) ] ,
                       $event['result'] ,
                       $id ,
                       $event['query'] ,
                       number_format( ( float ) $event['time']*1000 , 5 ) ,
                       $event['source'] );
            $totaltime += $event['time'];
          }
          echo '<tfoot style="background-color:#EEE;text-align:center;font-size:9px;color:#559;">';
            echo sprintf( '<tr><th colspan="4">%s Queries Logged<br>(%s ms)</th></tr>' ,
                   count( $this->querylog ) ,
                   number_format( ( float ) $totaltime*1000 , 5 ) );
          echo '</tfoot>';
          echo '<tbody style="background-color:#FFF;font-size:13px;color:#559;">';
            echo implode( "\n" , $out );
          echo '</tbody>';
        }
      echo '</table>';
      echo '</div>';
    }
    function console_querylog() {
      echo '<script language="javascript">';
        echo 'if( typeof console == "object" ){';
          echo 'console.groupCollapsed("SQueLCH (v'.$this->version.') Query Log");';
          if( !count( $this->querylog ) ) {
            echo 'console.warn( \'"SQueLCH::console_querylog()" called - No Queries\' );';
          } else {
            foreach( $this->querylog as $id => $event ) {
              echo sprintf( 'console.%s( \'%2s: "%s" (%s ms)\' );' ,
                         ( $event['result']=='success' ? 'info' : 'error' ) ,
                         $id ,
                         str_replace( '"' , '\"' , $event['query'] ) ,
                         number_format( ( float ) $event['time']*1000 , 5 ) );
            }
          }
          echo 'console.groupEnd();';
        echo '}';
      echo '</script>';
    }

}

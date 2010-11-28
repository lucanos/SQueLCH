<?php

/*

  SQueLCH v1.0.3

  Copyright 2008 Luke Stevenson <lucanos@gmail.com>

  Any associated materials, used under licence from other sources are governed
    by the terms and conditions of those licences, unless superceded by this
    copyright.
  This work is exclusively owned by Luke Stevenson and reproduction of it in
    whole or in part may constitute a copyright infringement.

*/


// Global Defined Variables ***************************************************
//=============================================================================

 # Initiate Defined Variables
  define( 'SQueLCH_VERSION' , '1.0.3' );
  define( 'OBJECT'          , 'OBJECT'  , true );
  define( 'ARRAY_A'         , 'ARRAY_A' , true );
  define( 'ARRAY_N'         , 'ARRAY_N' , true );


// The CLASS ******************************************************************
//=============================================================================

class SQueLCH {

  // Class Variables **********************************************************
  //=============================================================================

   # Action Flags
    var $debug_called     = false;
    var $vardump_called   = false;
   # SQL Variables
    var $dbType           = false;
    var $dbTypes          = array( 'mssql' , 'mysql' , 'oracle' , 'pdo' , 'postgresql' , 'sqlite' );
    var $dbHandle         = false;
    var $last_result      = false;
    var $last_query       = false;
    var $col_info         = false;
    var $from_disk_cache  = false;
    
    var $trace            = false; // same as $debug_all
    var $debug_all        = false; // same as $trace
    var $debug_echo_is_on = true;
    var $show_errors      = true;
    var $captured_errors  = array();
    var $last_error       = null;
   # Statistics
    var $stats            = array(
                              'actions' => array(
                                'insert'  => 0 ,
                                'update'  => 0 ,
                                'replace' => 0 ,
                                'select'  => 0 ,
                                'delete'  => 0 ,
                                'other'   => 0
                                ) ,
                              'time'    => 0.0
                              );
    
    var $cache_dir        = false;
    var $cache_queries    = false;
    var $cache_inserts    = false;
    var $use_disk_cache   = false;
    var $cache_timeout    = 24; // hours




  // Class Subfunctions *********************************************************
  //=============================================================================

  // SQL Subfunctions *********************************************************
  //===========================================================================

   # Check Dependancies
    function checkDependancy() {
      switch( $this->dbType ) {
        case 'mssql' :
          if( !function_exists( 'mssql_connect' ) )
            die( '<b>Fatal Error:</b>
                  SQueLCH requires ntwdblib.dll to be present in your winowds\system32 folder. Also enable MSSQL extenstion in PHP.ini file ' );
          break;
        case 'mysql' :
          if ( !function_exists ('mysql_connect') )
            die( '<b>Fatal Error:</b>
                  SQueLCH requires mySQL Lib to be compiled and or linked in to the PHP engine' );
          break;
        case 'oracle' :
          if ( !function_exists( 'OCILogon' ) )
            die( '<b>Fatal Error:</b>
                  SQueLCH requires Oracle OCI Lib to be compiled and/or linked in to the PHP engine' );
          break;
        case 'postgresql' :
          if ( !function_exists( 'pg_connect' ) )
            die( '<b>Fatal Error:</b>
                  SQueLCH requires PostgreSQL Lib to be compiled and or linked in to the PHP engine' );
          break;
        case 'sqlite' :
          if ( !function_exists( 'sqlite_open' ) )
            die( '<b>Fatal Error:</b>
                  SQueLCH requires SQLite Lib to be compiled and or linked in to the PHP engine' );
          break;
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
      }
    }

   # Connect to SQL Database Source
    function SQueLCH( $dbType=false , $dbUser=false , $dbPass=false , $dbBase=false , $dbHost=false , $dbPort=false ) {
     # Check Database Type
      if( $dbType!==false && !in_array( $dbType=strtolower( $dbType ) , $this->dbTypes ) ) {
       // Invalid Database Type Selected.
        $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
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
            $this->register_error( 'Missing Username or Password' );
            return false;
          }
         # Try to establish Database Handle
          if( !$dbHost ) $dbHost = 'localhost';
          if( !$this->dbHandle = @mssql_connect( $dbHost , $dbUser , $dbPass ) ) {
            $this->register_error( 'Unable to connect to MSSQL Source' );
            return false;
          }
         # Connect to Database
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' );
            return false;
          }
          return (bool) $this->select( $dbBase );
          break;
        case 'mysql' :
         # Must have a user and a password
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' );
            return false;
          }
         # Try to establish Database Handle
          if( !$dbHost ) $dbHost = 'localhost';
          if( !$this->dbHandle = @mysql_connect( $dbHost.($inPort?":$inPort":'') , $dbUser , $dbPass , true ) ) {
            $this->register_error( 'Unable to connect to MySQL Source' );
            return false;
          }
         # Connect to Database
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' );
            return false;
          }
          return (bool) $this->select( $dbBase );
          break;
        case 'oracle' :
         # Must have a username, a password and database namse
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' );
            return false;
          }
         # Must have Database Name
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' );
            return false;
          }
         # Try to establish the server database handle
          if ( !$this->dbHandle = @OCILogon( $dbUser, $dbpassword, $dbname) ) {
            $this->register_error( 'Unable to connect to Oracle Source' );
            return false;
          }
          return true;
          break;
        case 'postgresql' :
         # Must have a username, a password and database namse
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' );
            return false;
          }
         # Must have Database Name
          if( !$dbBase ) {
            $this->register_error( 'Missing Database Name' );
            return false;
          }
         # Try to establish the server database handle
          if( !$this->dbHandle = @pg_connect( "host=$dbHost user=$dbUser password=$dbPass dbname=$dbBase".($inPort?" port=$inPort":'') , true ) ) {       
            $this->register_error( 'Unable to connect to PostgreSQL Source' );
            return false;
          }
          return true;
          break;
        case 'sqlite' :
         # Turn on track errors 
          ini_set( 'track_errors' , 1 );
         # Must have a username, a password and database name
          if( !$dbUser || !$dbPass ) {
            $this->register_error( 'Missing Username or Password' );
            return false;
          }
         # Try to establish the server database handle
          if( !$this->dbHandle = @sqlite_open( $dbHost.$dbBase ) ) {
            $this->register_error( 'Unable to connect to SQLite Source' );
            return false;
          }
          return true;
          break;
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
      }
    }

   # Select Database
    function select( $inDBName ) {
      switch( $this->dbType ) {
        case 'mssql' :
         # Check for Database Name
          if ( !$inDBName ) {
            $this->register_error( 'Missing Database Name' ); return false;
          }
         # Check for Database Handle
          if ( !$this->dbHandle ) {
            $this->register_error( 'No Database Connection Available' ); return false;
          }
         # Attempt to Connect to Database
          if ( !@mssql_select_db( $inDBName , $this->dbHandle ) ) {
            $this->register_error( 'Unexpected error while trying to select database' ); return false;
          }
          return true;
          break;
        case 'mysql' :
         # Check for Database Name
          if ( !$inDBName ) {
            $this->register_error( 'Missing Database Name' ); return false;
          }
         # Check for Database Handle
          if ( !$this->dbHandle ) {
            $this->register_error( 'No Database Connection Available' ); return false;
          }
         # Attempt to Connect to Database
          if( !@mysql_select_db( $inDBName , $this->dbHandle ) ) {
            $errMsg = ( @mysql_error( $this->dbHandle ) ? mysql_error( $this->dbHandle ) : 'Unexpected error while trying to select database' );
            $this->register_error( $errMsg ); return false;
          }
          return true;
          break;
        case 'oracle' :
          $this->register_error( 'No Select Function Available for Oracle' );
          break;
        case 'postgresql' :
          $this->register_error( 'No Select Function Available for PostgreSQL' );
          break;
        case 'sqlite' :
          $this->register_error( 'No Select Function Available for SQLite' );
          break;
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
      }
    }

   # Basic Query
    function query( $query ) {
     # Flush cached values..
      $this->flush();
     # For reg expressions
      $query = trim( $query );
     # Log how the function was called
      $this->func_call = "\$db->query( \"$query\" )";
     # Keep track of the last query for debug..
      $this->last_query = $query;
     # Determine the Query Type
      preg_match( "/^(insert|delete|update|replace|select)\s+/i" , $query , $sqlAction );
      $sqlAction = strtolower( $sqlAction ? $sqlAction[1] : 'other' );
     # Count how many queries there have been
      $this->stats['actions'][$sqlAction]++;
     # Use core file cache function
      if( $cache = $this->get_cache( $query ) )
        return $cache;
     # If there is no existing database connection then try to connect
      if( !$this->dbHandle ) {
        $this->register_error( 'No Database Connection Available' ); return false;
      }
     # Process the Query dependent on the Database Type
      switch( $this->dbType ) {
        case 'mssql' :
         # Adjust the SQL Syntax
          $query = $this->convertMySqlToMSSql( $query );
         # Perform the query via std mssql_query function..
          $this->result = @mssql_query( $query );
         # If there is an error then take note of it..
          if( $this->result==false ) {
            $get_errorcodeSql = "SELECT @@ERROR as errorcode";
            $error_res = @mssql_query( $get_errorcodeSql , $this->dbHandle );
            $errorCode = @mssql_result( $error_res , 0 , "errorcode" );
            $get_errorMessageSql = "SELECT severity as errorSeverity, text as errorText FROM sys.messages  WHERE message_id = ".$errorCode  ;
            if( $errormessage_res =  @mssql_query( $get_errorMessageSql , $this->dbHandle ) ) {
              $errorMessage_Row = @mssql_fetch_row( $errormessage_res );
              $errorSeverity = $errorMessage_Row[0];
              $errorMessage = $errorMessage_Row[1];
            }
            $is_insert = true;
            $this->register_error( "ErrorCode: ".$errorCode." ### Error Severity: ".$errorSeverity." ### Error Message: ".$errorMessage." ### Query: ".$query );
            return false;
          }
         # Query was an insert, delete, update, replace
          $is_insert = false;
          if( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
            $this->rows_affected = @mssql_rows_affected( $this->dbh );
           # Take note of the insert_id
            if( preg_match("/^(insert|replace)\s+/i" , $query ) ) {
              if( ( $identityresultset = @mssql_query( "select SCOPE_IDENTITY()" ) )!=false ) {
                $identityrow = @mssql_fetch_row( $identityresultset );
                $this->insert_id = $identityrow[0];
              }
            }
           # Return number of rows affected
            $return_val = $this->rows_affected;
          } else {
           # Query was a select
           # Take note of column info
            $i=0;
            while ( $i<@mssql_num_fields( $this->result ) ) {
              $this->col_info[$i] = @mssql_fetch_field( $this->result );
              $i++;
            }
           # Store Query Results
            $num_rows=0;
            while ( $row = @mssql_fetch_object( $this->result ) ) {
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
          $this->result = @mysql_query( $query , $this->dbHandle );
         # If there is an error then take note of it..
          if ( $str = @mysql_error( $this->dbHandle ) ) {
            $is_insert = true;
            $this->register_error( $str );
            return false;
          }
          $is_insert = false;
          if( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
           // Query was an insert, delete, update, replace
            $this->rows_affected = @mysql_affected_rows();
           # Take note of the insert_id
            if( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
              $this->insert_id = @mysql_insert_id( $this->dbHandle );
            }
           # Return number fo rows affected
            $return_val = $this->rows_affected;
          } else {
           // Query was a select
           # Take note of column info
            $i=0;
            while ( $i<@mysql_num_fields( $this->result ) ) {
              $this->col_info[$i] = @mysql_fetch_field($this->result);
              $i++;
            }
           # Store Query Results
            $num_rows=0;
            while ( $row = @mysql_fetch_object( $this->result ) ) {
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
            $this->register_error( $error['message'] );
            return false;
          }
         # Execute the query..
          if( !$this->result = OCIExecute( $stmt ) ) {
            $error = OCIError( $stmt );
            $this->register_error( $error['message'] );
            return false;
          }
          $is_insert = false;
          if( preg_match( '/^(insert|delete|update|create) /i' , $query) ) {
           // Query was an insert
            $is_insert = true;
           # Num afected rows
            $return_value = $this->rows_affected = @OCIRowCount( $stmt );
          } else {
           // Query was a select
           # Get column information
            if( $num_cols = @OCINumCols( $stmt ) ) {
             # Fetch the column meta data
              for( $i=1 ; $i<=$num_cols ; $i++ ) {
                $this->col_info[($i-1)]->name = @OCIColumnName($stmt,$i);
                $this->col_info[($i-1)]->type = @OCIColumnType($stmt,$i);
                $this->col_info[($i-1)]->size = @OCIColumnSize($stmt,$i);
              }
            }
           # If there are any results then get them
            if( $this->num_rows = @OCIFetchStatement( $stmt , $results ) ) {
             # Convert results into object orientated results..
              foreach( $results as $col_title => $col_contents ) {
                $row_num=0;
               # then - loop through rows
                foreach( $col_contents as $col_content ) {
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
            $is_insert = true;
            $this->register_error( $str );
            return false;
          }
          $is_insert = false;
          if( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
           // Query was an insert, delete, update, replace
            $this->rows_affected = @pg_affected_rows($this->result);
           # Take note of the insert_id
            if( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
              $this->insert_id = pg_last_oid($this->result);
            }
           # Return number fo rows affected
            $return_val = $this->rows_affected;
          } else {
           // Query was a select
            $num_rows=0;
           # Take note of column info
            $i=0;
            while ( $i<@pg_num_fields( $this->result ) ) {
              $this->col_info[$i]->name = pg_field_name($this->result,$i);
              $this->col_info[$i]->type = pg_field_type($this->result,$i);
              $this->col_info[$i]->size = pg_field_size($this->result,$i);
              $i++;
            }
           # Store Query Results
            while ( $row = @pg_fetch_object( $this->result ) ) {
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
            $this->register_error( $err_str );
            return false;
          }
          if( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
           // Query was an insert, delete, update, replace
            $this->rows_affected = @sqlite_changes( $this->dbHandle );
           # Take note of the insert_id
            if( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
              $this->insert_id = @sqlite_last_insert_rowid($this->dbh);  
            }
           # Return number fo rows affected
            $return_val = $this->rows_affected;
          } else {
           // Query was an select
           # Take note of column info  
            $i=0;
            while( $i<@sqlite_num_fields( $this->result ) ) {
              $this->col_info[$i]->name       = sqlite_field_name( $this->result , $i );
              $this->col_info[$i]->type       = null;
              $this->col_info[$i]->max_length = null;
              $i++;
            }
           # Store Query Results
            $num_rows=0;
            while ($row = @sqlite_fetch_array( $this->result , SQLITE_ASSOC ) ) {
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
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
      }
     # Disk caching of queries
      $this->store_cache( $query , $is_insert );
     # If debug ALL queries
      $this->trace || $this->debug_all ? $this->debug() : null ;
     # Return response
      return $return_val;
    }

   # Format a string correctly for safe insert
    function escape( $inString ) {
      switch( $this->dbType ) {
        case 'mssql' :
          return str_ireplace( "'" , "''" , $inString );
          break;
        case 'mysql' :
          return mysql_escape_string( stripslashes( $inString ) );
          break;
        case 'oracle' :
          return str_replace( "'" , "''" , str_replace( "''" , "'" , stripslashes( $inString ) ) );
          break;
        case 'postgresql' :
          return pg_escape_string( stripslashes( $inString ) );
          break;
        case 'sqlite' :
          return sqlite_escape_string( stripslashes( preg_replace( "/[\r\n]/" , '' , $inString ) ) );
          break;
        default:
          $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
      }
    }

   # Return database specific system date syntax
    function sysdate() {
      switch( $this->dbType ) {
        case 'mssql'      : return 'getDate()'; break;
        case 'mysql'      : return 'NOW()';     break;
        case 'oracle'     : return "SYSDATE";   break;
        case 'postgresql' : return 'NOW()';     break;
        case 'sqlite'     : return 'now';       break;
        default           : $this->register_error( 'Invalid Database Type "'.$dbType.'"' );
      }
    }

   # Convert MySQL syntax to MSSQL
    function convertMySqlToMSSql( $query ) {
     # Replace the '`' character used for MySql queries, but not supported in MSSql
      $query = str_replace("`", "", $query);
     # Replace From UnixTime command in MSSql, doesn't work
      $pattern = "FROM_UNIXTIME\(([^/]{0,})\)";
      $replacement = "getdate()";
      $query = eregi_replace($pattern, $replacement, $query);
     # Replace LIMIT keyword with TOP keyword. Works only on MySql not on MS-Sql
      $pattern = "LIMIT[^\w]{1,}([0-9]{1,})([\,]{0,})([0-9]{0,})";
      $replacement = "";
      eregi($pattern, $query, $regs);
      $query = eregi_replace($pattern, $replacement, $query);
      if( $regs[2] )
        $query = str_ireplace("SELECT ", "SELECT TOP ".$regs[3]." ", $query);
      else {
        if( $regs[1] )
          $query  = str_ireplace("SELECT ", "SELECT TOP ".$regs[1]." ", $query);
      }
     # Replace unix_timestamp function. Doesn't work in MS-Sql
      $pattern = "unix_timestamp\(([^/]{0,})\)";
      $replacement = "\\1";
      $query = eregi_replace( $pattern , $replacement , $query );
      return $query;
    }

   # Determine the Insert ID for Oracle
    function oracle_insert_id( $seq_name ) {
      $return_val = $this->get_var( "SELECT $seq_name.nextVal id FROM Dual" );
      // If no return value then try to create the sequence
      if ( ! $return_val ) {
        $this->query("CREATE SEQUENCE $seq_name maxValue 9999999999 INCREMENT BY 1 START WITH 1 CACHE 20 CYCLE");
        $return_val = $this->get_var("SELECT $seq_name.nextVal id FROM Dual");
        register_error( $SQueLCH_oracle8_9_str[2].": $seq_name" , E_USER_NOTICE );
      }
      return $return_val;
    }


  // PHP Subfunctions *********************************************************
  //=============================================================================

   # Print SQL/DB error - over-ridden by specific DB class
    function register_error( $errMsg , $errLevel=E_USER_WARNING , $errFile=false , $errLine=false ) {
     # Keep track of last error
      $this->last_error = $errMsg;
     # Capture all errors to an error array no matter what happens
      $this->captured_errors[] = array (
        'error_str' => $errMsg.( $errFile ? ' in '.$errFile : '' ).( $errLine ? ' at Line #'.$errLine : '' ),
        'query'     => $this->last_query
        );
     # Output to PHP Native Error Handler
      if( $this->show_errors )
        trigger_error( $errMsg , $errLevel );
    }

   # Turn error handling on or off..
    function show_errors() { $this->show_errors = true;  }
    function hide_errors() { $this->show_errors = false; }

   # Flush SQL Variables
    function flush() {
     # Get rid of these
      $this->last_result = null;
      $this->col_info = null;
      $this->last_query = null;
      $this->from_disk_cache = false;
    }

   # Get one variable
    function get_var( $query=null , $x=0 , $y=0 ) {
     # Log how the function was called
      $this->func_call = "\$db->get_var( \"$query\" , $x , $y )";
     # If there is a query then perform it if not then use cached results..
      if ( $query )
        $this->query( $query );
     # Extract var out of cached results based x,y vals
      if ( $this->last_result[$y] )
        $values = array_values( get_object_vars( $this->last_result[$y] ) );
     # If there is a value return it else return null
      return ( ( isset( $values[$x] ) && $values[$x]!=='' ) ? $values[$x] : null );
    }

   # Get one row
    function get_row( $query=null , $output=OBJECT , $y=0 ) {
     # Log how the function was called
      $this->func_call = "\$db->get_row( \"$query\" , $output , $y )";
     # If there is a query then perform it if not then use cached results..
      if ( $query )
        $this->query( $query );
      if ( !$this->last_result[$y] )
        return null;
     # Format Output dependent on Requested Object
      switch ( $output ) {
        case OBJECT :
         # If the output is an object then return object using the row offset..
          return $this->last_result[$y];
        case ARRAY_A :
         # If the output is an associative array then return row as such..
          return get_object_vars( $this->last_result[$y] );
        case ARRAY_N :
         # If the output is an numerical array then return row as such..
          return array_values( get_object_vars( $this->last_result[$y] ) );
        default :
         # If invalid output type was specified..
          $this->print_error( "\$db->get_row( string query , output type , int offset ) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N" );
          return null;
      }
    }

   # Get one column
    function get_col( $query=null , $x=0 ) {
     # If there is a query then perform it if not then use cached results..
      if ( $query )
        $this->query( $query );
     # Extract the column values
      for ( $i=0 ; $i<count( $this->last_result ) ; $i++ )
        $new_array[$i] = $this->get_var( null , $x , $i );
     # Return value array
      return $new_array;
    }

   # Return the the query as a result set 
    function get_results( $query=null , $output=OBJECT ) {
     # Log how the function was called
      $this->func_call = "\$db->get_results( \"$query\" , $output )";
     # If there is a query then perform it if not then use cached results..
      if ( $query )
        $this->query( $query );
     # Format Output dependent on Requested Object
      switch ( $output ) {
        case OBJECT :
         # Send back array of objects. Each row is an object
          return $this->last_result;
        case ARRAY_A :
        case ARRAY_N :
          if ( $this->last_result ) {
            $i=0;
            foreach( $this->last_result as $row ) {
              $new_array[$i] = get_object_vars( $row );
              if ( $output==ARRAY_N )
                $new_array[$i] = array_values( $new_array[$i] );
              $i++;
            }
            return $new_array;
          } else {
            return null;
          }
        default :
          return null;
      }
    }

   # Get column meta data info pertaining to the last query
    function get_col_info( $info_type="name" , $col_offset=-1 ) {
      if ( $this->col_info ) {
        if ( $col_offset == -1 ) {
          $i=0;
          foreach( $this->col_info as $col ) {
            $new_array[$i] = $col->{$info_type};
            $i++;
          }
          return $new_array;
        } else {
          return $this->col_info[$col_offset]->{$info_type};
        }
      }
    }

   # Cache - Store
    function store_cache( $query , $is_insert ) {
     # Check is Cache Directory is set
      if( !$this->cache_dir ) {
        $this->register_error( 'Cache directory is not set' , E_USER_NOTICE );
        return false;
      }
     # The would be cache file for this query
      $cache_file = $this->cache_dir.'/'.md5( $this->dbType.$query ).'.ser';
     # disk caching of queries
      if ( $this->use_disk_cache
            && ( ( $this->cache_queries && ! $is_insert )
              || ( $this->cache_inserts && $is_insert ) ) ) {
        if ( !is_dir( $this->cache_dir ) ) {
          $this->register_error( "Could not open cache dir: $this->cache_dir" , E_USER_NOTICE );
          return false;
        }
       # Cache all result values
        $result_cache = array (
          'col_info' => $this->col_info,
          'last_result' => $this->last_result,
          'num_rows' => $this->num_rows,
          'return_value' => $this->num_rows,
          );
       # Write to Cache
        error_log( serialize( $result_cache ) , 3 , $cache_file );
      }
    }

   # Cache - Retrieve
    function get_cache( $query ) {
     # Check is Cache Directory is set
      if( !$this->cache_dir ) {
        $this->register_error( 'Cache directory is not set' , E_USER_NOTICE );
        return false;
      }
     # The would be cache file for this query
      $cache_file = $this->cache_dir.'/'.md5( $this->dbType.$query ).'.ser';
     # Try to get previously cached version
      if ( $this->use_disk_cache
           && file_exists( $cache_file ) ) {
       # Only use this cache file if less than 'cache_timeout' (hours)
        if ( ( time() - filemtime( $cache_file ) ) > ( $this->cache_timeout*3600 ) ) {
          unlink( $cache_file );
        } else {
          $result_cache = unserialize( file_get_contents( $cache_file ) );
          $this->col_info = $result_cache['col_info'];
          $this->last_result = $result_cache['last_result'];
          $this->num_rows = $result_cache['num_rows'];
          $this->from_disk_cache = true;
         # If debug ALL queries
          $this->trace || $this->debug_all ? $this->debug() : null ;
          return $result_cache['return_value'];
        }
      }
      if ( !file_exists( $cache_file ) )
        $this->register_error( "Cache file: $cache_file does not exist" , E_USER_NOTICE );
    }

   # Dump the contents of any input variable
    function vardump( $mixed='' ) {
     # Start outup buffering
      ob_start();
      echo "<p><table><tr><td bgcolor=ffffff><blockquote><font color=000090>";
      echo "<pre><font face=arial>";
      if ( ! $this->vardump_called )
        echo "<font color=800080><b>SQueLCH</b> (v".SQueLCH_VERSION.") <b>Variable Dump..</b></font>\n\n";
      $var_type = gettype( $mixed );
      print_r( ( $mixed ? $mixed : "<font color=red>No Value / False</font>" ) );
      echo "\n\n<b>Type:</b> ".ucfirst( $var_type )."\n";
      echo "<b>Last Query</b> [".$array_sum( $this->stats['actions'] )."]<b>:</b> ".( $this->last_query ? $this->last_query : "NULL" )."\n";
      echo "<b>Last Function Call:</b> ".( $this->func_call ? $this->func_call : "None" )."\n";
      echo "<b>Last Rows Returned:</b> ".count( $this->last_result )."\n";
      echo "</font></pre></font></blockquote></td></tr></table>");
      echo "\n<hr size=1 noshade color=dddddd>";
     # Stop output buffering and capture debug HTML
      $html = ob_get_contents();
      ob_end_clean();
     # Only echo output if it is turned on
      if ( $this->debug_echo_is_on )
        echo $html;
      $this->vardump_called = true;
      return $html;
    }
   # Alias for vardump
    function dumpvar( $mixed ) {
      $this->vardump( $mixed );
    }

   # Displays the last query string that was sent to the database & lists results (if there were any).
    function debug() {
     # Start outup buffering
      ob_start();
      echo "<blockquote class=\"SQueLCH_debug\" style=\"font-face:Arial;\">";
       # Only show SQueLCH credits once..
        if ( !$this->debug_called )
          echo "<div style=\"color:#800080;\"><strong>SQueLCH</strong> (v".SQueLCH_VERSION.") <strong>Debug..</strong></div>\n";
        if ( $this->last_error )
          echo "<div style=\"color:#009;\"><strong>Last Error --</strong> [<strong style=\"color:#000;\">$this->last_error</strong>]</div>\n";
        if ( $this->from_disk_cache )
          echo "<div style=\"color:#009;font-weight:bold;\">Results retrieved from disk cache</div>";
        echo "<div style=\"color:#009;\">";
          echo "<strong>Query</strong> [".array_sum( $this->stats['actions'] )."] <strong>--</strong> ";
          echo "[<strong style=\"color:#000;\">$this->last_query</strong>]";
        echo "</div>";
        echo "<div style=\"color:#009;font-weight:bold;\">Query Result..</div>";
        echo "<blockquote class=\"SQueLCH_output\" style=\"font-size:0.8em;\">";
          echo "<table cellpadding=5 cellspacing=1 bgcolor=555555>";
         # Results top rows
          if ( $this->col_info ) {
            echo "<tr bgcolor=eeeeee>";
              echo "<td style=\"color:#559;font-weight:bold;\" nowrap valign=bottom>(row)</td>";
              for ( $i=0 ; $i<count( $this->col_info ) ; $i++ ) {
                echo "<td nowrap align=left valign=top>";
                  echo "<span style=\"color:#559;\">{$this->col_info[$i]->type} {$this->col_info[$i]->max_length}</span>";
                  echo "<br>";
                  echo "<strong>{$this->col_info[$i]->name}</strong>";
                echo "</td>";
              }
            echo "</tr>";
          }
         # Print main results
          if ( $this->last_result ) {
            $i=0;
            foreach ( $this->get_results( null , ARRAY_N ) as $one_row ) {
              $i++;
              echo "<tr style=\"background-color:#FFF;\"".( !$i%2 ? ' class="alt"' : '' ).">";
                echo "<th style=\"background-color:#EEE;font-weight:normal;color:#559;\" nowrap>$i</th>";
                foreach ( $one_row as $item )
                  echo "<td style=\"font-size:1.2em;\" nowrap>$item</td>";
              echo "</tr>";
            }
          } else {
         # If last result
            echo "<tr style=\"background-color:#FFF;\">";
              echo "<td colspan=".( count( $this->col_info )+1 ).">No Results</td>";
            echo "</tr>";
          }
          echo "</table>";
        echo "</blockquote>";
      echo "</blockquote>";
      echo "<hr noshade style=\"color:#DDD;\">";
     # Stop output buffering and capture debug HTML
      $html = ob_get_contents();
      ob_end_clean();
     # Only echo output if it is turned on
      if ( $this->debug_echo_is_on )
        echo $html;
      $this->debug_called = true;
      return $html;
    }

}

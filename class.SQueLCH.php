<?php
/*
** SQueLCH - Universal Version
** Based on
** ezSQL by Justin Vincent (justin@visunet.ie)
*/

// SQueLCH Constants
//=============================================================================
define( 'SQueLCH_VERSION'  , '1.0.1' );
define( 'OBJECT'           , 'OBJECT'  , true );
define( 'ARRAY_A'          , 'ARRAY_A' , true );
define( 'ARRAY_N'          , 'ARRAY_N' , true );

// SQueLCH Class
//=============================================================================
class SQueLCH {

 # Debug Settings and Error Handling
  var $debugFilter      = array(
    0 => array( 'Debug'    , 'debug' ) ,
    1 => array( 'Notice'   , 'debug' , 'file' ) ,
    2 => array( 'Warning'  , 'debug' , 'file' , 'screen' ) ,
    3 => array( 'Error'    , 'debug' , 'file' , 'screen' ) ,
    4 => array( 'Critical' , 'debug' , 'file' , 'screen' )
  );
  var $debugFile        = null;

 # Action Flags
  var $debug_called     = false;
  var $vardump_called   = false;

 # Statistics
  var $num_queries      = 0;
  var $last_time        = 0;
  var $all_time         = 0;

 # History
  var $last_query       = null;
  var $last_error       = null;
  var $last_insert      = null;

 # Database Values
  var $dbParameters     = array();
  var $col_info         = null;
  var $dbType           = null;
  var $dbHandle         = null;

 # Cache Variables
  var $use_disk_cache   = false;
  var $cache_dir        = false;
  var $cache_queries    = false;
  var $cache_inserts    = false;
  var $cache_timeout    = 24; // hours



 // Debug and Error Handling Functions
 //============================================================================

   // Set Handler for Debug File (if Used)
    function setDebugFile( $fileLocation ) {
      if ( !file_exists( $fileLocation ) ) {
       // File does not yet exist
        if ( file_put_contents( '' , $fileLocation ) ) {
         // File created
          $this->debugFile = $fileLocation;
          return true;
        }
        $this->handleError( 2 , 'Debugfile does not exist, and cannot be created.' );
        return false;
      }
      if ( !is_writable( $fileLocation ) ) {
        $this->handleError( 2 , 'Debugfile exists, but cannot be written to.' );
        return false;
      }
      $this->debugFile = $fileLocation;
      return true;
    }

   // Perform Error Handling
    function handleError( $errorType , $errorMessage=false , $terminateExec=false ) {
      if ( in_array( 'screen' , $this->debugFilter ) )
        echo $errorMessage.'<br>';
      if ( $this->debugFile && in_array( 'file' , $this->debugFilter ) )
        file_put_contents( date( 'Y-m-d H:i:s' ).' [Client '.$_SERVER['REMOTE_ADDR'].'] '.$errorMessage."\n" );
      if ( $terminateExec )
        die( 'An Error was Encountered' );
      return true;
    }

   // Record Internal Errors
    function recordError( $errorID , $errorMessage ) {
     # Get the Database Type-Specific Error Message
      $errorID = $this->getError( $errorID );
     # Keep track of last error
      $this->last_error = $errorMessage = $errorID.' '.$errorMessage;
     # Capture all errors to an error array no matter what happens
      $this->captured_errors[] = array (
        'error_str' => $errorMessage ,
        'query'     => $this->last_query
      );
    }

   // Get the Exact Time - 1/100sec
    function getMicro() {
      return round( array_sum( explode( ' ' , microtime(1) ) ) , 2 );
    }


 // Utility Functions
 //============================================================================
 
   // checkCompatibility
    function checkCompatibility() {
      switch ( $this->dbType ) {
        case 'mysql' :
          if ( !function_exists( 'mysql_connect' ) )
            $this->handleError( 3 , 'SQueLCH requires mySQL Lib to be compiled and or linked in to the PHP engine.' , true );
          break;
        case 'postgresql' :
          if ( !function_exists( 'pg_connect' ) )
            $this->handleError( 3 , 'SQueLCH requires PostgreSQL Lib to be compiled and or linked in to the PHP engine.' , true );
          break;
        case 'oracle8' :
        case 'oracle9' :
          if ( !function_exists( 'OCILogon' ) )
            $this->handleError( 3 , 'SQueLCH requires Oracle OCI Lib to be compiled and/or linked in to the PHP engine.' , true );
          break;
        case 'mssql' :
          if ( !function_exists('mssql_connect') )
            $this->handleError( 3 , 'SQueLCH requires ntwdblib.dll to be present in your windows\system32 folder. Also enable MS-SQL extenstion in PHP.ini file.' , true );
          break;
        case 'pdo' :
          if ( !class_exists( 'PDO' ) )
            $this->handleError( 3 , 'SQueLCH requires PDO Lib to be compiled and or linked in to the PHP engine.' , true );
          break;
        case 'sqlite' :
          if ( !function_exists( 'sqlite_open' ) )
            $this->handleError( 3 , 'exSQL requires SQLite Lib to be compiled and or linked in to the PHP engine.' , true );
          break;
        default :
        # Unknown Database Type
          $this->handleError( 2 , 'Unknown Database Type' );
    }
    return true;
  }



 // Database Interaction Functions
 //============================================================================

 // Constructor
  function SQueLCH() {
   # Get the Functions Arguments as a Numeric Array
    $args = func_get_args();
   # If the are No Arguments, Fail
    if ( !count( $args ) ) {
      $this->handleError( 3 , 'No arguments passed to "connect" function' );return false;
    }
   # If there is One Argument and that Value is an Array, Use That Array
      if ( count( $args )==1 && is_array( $args[0] ) ) {
        $args = $args[0];
      }
     # Save the Arguments
      $this->dbParameters = $args;
     # Set the Database Type
      $this->dbType = strtolower( $args[0] );
     # Check for Required Extensions, etc. base on Database Type
      $this->checkCompatibility();
     # Attempt to Connect to the Requested Database
      switch( $this->dbType ) {
        case 'mysql' :
         # Format for call is "SQueLCH( 'mysql' , USERNAME , PASSWORD , DATABASE [ , HOSTNAME [, PORT ] ] )"
          if ( count( $args )<4 || count( $args )>6 ) {
           // Insufficient, or too many, arguments provided
            $this->handleError( 3 , 'Incorrect arguments passed to "connect" function' );return false;
          }
          if ( !$this->dbHandle = @mysql_connect( ( count($args)>4 ? $args[4].':'.$args[5] : 'localhost' ) , $args[1] , $args[2] , true ) ) {
           // Failed to Connect to Server
            $this->handleError( 3 , 'Error establishing mySQL database connection. Correct user/password? Correct hostname? Database server running?' ); return false;
          }
          if ( !$this->select( $args[3] ) ) {
           // Failed to Select Database
            $this->handleError( 3 , 'Unexpected error while trying to select database.' ); return false;
          }
          break;
        case 'postgresql' :
         # Format for call is "SQueLCH( 'postgresql' , USERNAME , PASSWORD , DATABASE [ , HOSTNAME [, PORT ] ] )"
          if ( count( $args )<4 || count( $args )>6 ) {
           // Insufficient, or too many, arguments provided
            $this->handleError( 3 , 'Incorrect arguments passed to "connect" function' );return false;
          }
          if ( !$this->dbHandle = @pg_connect( 'host='.( $args[4] ? $args[4] : 'localhost' )." user={$args[1]} password={$args[2]} dbname={$args[3]}".( $args[5] ? " port=$args[5]" : '' ) , true ) ) {
           // Failed to Connect to Server or Database
            $this->handleError( 3 , 'Error establishing PostgreSQL database connection. Correct user/password? Correct hostname? Database server running?' ); return false;
          }
          break;
        case 'oracle' :
        case 'oracle8' :
        case 'oracle9' :
         # Format for call is "SQueLCH( 'oracle' , USERNAME , PASSWORD , DATABASE )"
          if ( count( $args )!=4 ) {
           // Insufficient, or too many, arguments provided
            $this->handleError( 3 , 'Incorrect arguments passed to "connect" function' );return false;
          }
          ini_set( 'track_errors' , 1 );
          if ( !$this->dbHandle = @OCILogon( $args[1] , $args[2] , $args[3] ) ) {
           // Failed to Connect to Server or Database
            $this->handleError( 3 , 'SQueLCH auto created the following Oracle sequence' ); return false;
          }
          break;
        case 'mssql' :
         # Format for call is "SQueLCH( 'mssql' , USERNAME , PASSWORD , DATABASE , HOSTNAME )"
          if ( count( $args )!=4 ) {
           // Insufficient, or too many, arguments provided
            $this->handleError( 3 , 'Incorrect arguments passed to "connect" function' ); return false;
          }
          if ( !$this->dbHandle = @mssql_connect( $args[4] , $args[1] , $args[2], true ) ) {
           // Failed to Connect to Server
            $this->handleError( 3 , 'Error establishing mssql database connection. Correct user/password? Correct hostname? Database server running?' ); return false;
          }
          if ( !$this->select( $args[3] ) ) {
           // Failed to Connect to Database
            $this->handleError( 3 , 'Unexpected error while trying to select database.' ); return false;
          }
          break;

/*
        case 'pdo' :
         # Format for call is "SQueLCH( 'pdo' , USERNAME , PASSWORD , DSN )"
          if ( count( $args )!=4 ) {
           // Insufficient, or too many, arguments provided
            $this->handleError( 3 , 'Incorrect arguments passed to "connect" function' ); return false;
          }
          ini_set( 'track_errors' , 1 );
          try {
           // Attempt to Connect to PDO
            $this->dbHandle = new PDO( $args[3] , $args[1] , $args[2] );
          } 
          catch ( PDOException $e ) {
           // Failed to Connect to PDO
            $this->handleError( 3 , $e->getMessage() ); return false;
          }
          break;
        case 'sqlite' :
         # Format for call is "SQueLCH( 'sqlite' , DATABASEPATH , DATABASENAME )"
          if ( count( $args )!=3 ) {
           // Insufficient, or too many, arguments provided
            $this->handleError( 3 , 'Incorrect arguments passed to "connect" function' ); return false;
          }
          ini_set( 'track_errors' , 1 );
          if ( !$this->dbHandle = @sqlite_open( $args[1].$args[2] ) ) {
           // Failed to Connect to Database
            $this->handleError( 3 , $php_errormsg ); return false;
          }
          break;
*/

        default :
          $this->handleError( 3 , "Unrecognised Database type of \"{$this->dbType}\" passed to \"connect\" function" );
          return false;
    }
  }

  // Select Database
  function select( $databaseName ) {
    switch( $this->dbType ) {
      case 'mysql' :
       // Attempt to Select the mySQL Database
        return @mysql_select_db( $databaseName , $this->dbHandle );
        break;
      case 'postgresql' :
      case 'oracle' :
      case 'oracle8' :
      case 'oracle9' :
      case 'pdo' :
       // Refresh the Connection, pointing at the New Database
       # Get the Previous Connection Parameters
        $recallConn = $this->dbParameters;
       # Replace the Database Parameter
        $recallConn[3] = $databaseName;
       # Execute the Connection
        return $this->SQueLCH( $recallConn );
        break;
      case 'mssql' :
       // Attempt to Select the MSSQL Database
        return @mssql_select_db( $databaseName , $this->dbHandle );
        break;
      case 'sqlite' :
       // Refresh the Connection, pointing at the New Database
       # Get the Previous Connection Parameters
        $recallConn = $this->dbParameters;
       # Replace the Database Parameter
        $recallConn[2] = $databaseName;
       # Execute the Connection
        return $this->SQueLCH( $recallConn );
        break;
      default :
        return false;
    }
  }

  // Basic Query
  function query() {

    # Flush cached values..
      $this->flush();
    # For reg expressions
      $query = trim( $query );
    #  Log how the function was called
      $this->func_call = "\$db->query(\"$query\")";
    # Keep track of the last query for debug..
      $this->last_query = $query;
     # Count how many queries there have been
      $this->num_queries++;
     # Start the Clock
      $startTime = $this->getMicro();
     # Use core file cache function
      if ( $cache = $this->get_cache( $query ) ) {
        return $cache;
      }
     # If there is no existing database connection then try to connect
      if ( !$this->dbHandle ){
        $this->handleError( 3 , 'No Database Connection Available' ); return false;
      }

    switch( $this->dbType ) {

      case 'mysql' :
       # Perform the query via std mysql_query function..
        $this->result = @mysql_query( $query , $this->dbHandle );
       # If there is an error then take note of it..
        if ( $str = @mysql_error( $this->dbHandle ) ) {
          $is_insert = true;
          $this->handleError( 2 , $str );
          return false;
        }
       # Query was an insert, delete, update, replace
        $is_insert = false;
        if ( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
          $this->rows_affected = @mysql_affected_rows();
         # Take note of the insert_id
          if ( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
            $this->insert_id = @mysql_insert_id($this->dbHandle);
          }
         # Return number fo rows affected
          $return_val = $this->rows_affected;
        } else {
         # Take note of column info
          $i=0;
          while ($i < @mysql_num_fields( $this->result ) ) {
            $this->col_info[$i] = @mysql_fetch_field($this->result);
            $i++;
          }
         # Store Query Results
          $num_rows=0;
          while ( $row = @mysql_fetch_object( $this->result ) ) {
           # Store results as an objects within main array
            $this->last_result[$num_rows] = $row;
            $num_rows++;
          }
         # Free the mySQL Resource
          @mysql_free_result( $this->result );
         # Log number of rows the query returned
          $this->num_rows = $num_rows;
         # Return number of rows selected
          $return_val = $this->num_rows;
        }
        break;

      case 'postgresql' :
       # Perform the query via std postgresql_query function..
        $this->result = @pg_query( $this->dbHandle , $query );
       # If there is an error then take note of it..
        if ( $str = @pg_last_error( $this->dbHandle ) ) {
          $is_insert = true;
          $this->handleError( 2 , $str );
          return false;
        }
       # Query was an insert, delete, update, replace
        $is_insert = false;
        if ( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
          $this->rows_affected = @pg_affected_rows( $this->result );
         # Take note of the insert_id
          if ( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
            $this->insert_id = pg_last_oid($this->result);
          }
         # Return number fo rows affected
          $return_val = $this->rows_affected;
        } else {
          $num_rows=0;
         # Take note of column info
          $i=0;
          while ( $i<@pg_num_fields( $this->result ) ) {
            $this->col_info[$i]->name = pg_field_name( $this->result , $i );
            $this->col_info[$i]->type = pg_field_type( $this->result , $i );
            $this->col_info[$i]->size = pg_field_size( $this->result , $i );
            $i++;
          }
         # Store Query Results
          while ( $row = @pg_fetch_object( $this->result ) ) {
           # Store results as an objects within main array
            $this->last_result[$num_rows] = $row ;
            $num_rows++;
          }
         # Free the PostgreSQL Resource
          @pg_free_result( $this->result );
         # Log number of rows the query returned
          $this->num_rows = $num_rows;
         # Return number of rows selected
          $return_val = $this->num_rows;
        }
        break;

      case 'oracle' :
      case 'oracle8' :
      case 'oracle9' :
       # Parses the query and returns a statement..
        if ( !$stmt = OCIParse( $this->dbHandle , $query ) ) {
          $error = OCIError( $this->dbHandle );
          $this->handleError( 2 , $error["message"] );
          return false;
        }
      # Execute the query..
        if ( !$this->result = OCIExecute( $stmt ) ) {
          $error = OCIError( $stmt );
          $this->handleError( 2 , $error["message"] );
          return false;
        }
      # If query was an insert
        $is_insert = false;
        if ( preg_match( '/^(insert|delete|update|create) /i' , $query ) ) {
          $is_insert = true;
         # Num of affected rows
          $return_value = $this->rows_affected = @OCIRowCount( $stmt );
        } else {
         # Get column information
          if ( $num_cols = @OCINumCols( $stmt ) ) {
           # Fetch the column meta data
            for ( $i=0 ; $i<$num_cols ; $i++ ) {
              $this->col_info[$i]->name = @OCIColumnName( $stmt , $i+1 );
              $this->col_info[$i]->type = @OCIColumnType( $stmt , $i+1 );
              $this->col_info[$i]->size = @OCIColumnSize( $stmt , $i+1 );
            }
          }
         # If there are any results then get them
          if ( $this->num_rows = @OCIFetchStatement( $stmt , $results ) ) {
           # Convert results into object orientated results..
           // Due to Oracle strange return structure - loop through columns
            foreach ( $results as $col_title => $col_contents ) {
              $row_num=0;
              foreach ( $col_contents as $col_content ) {
                $this->last_result[$row_num]->{$col_title} = $col_content;
                $row_num++;
              }
            }
          }
         # Num result rows
          $return_value = $this->num_rows;
        }
        break;

/*
      case 'pdo' :
       # Query was an insert, delete, update, replace
        if ( preg_match( "/^(insert|delete|update|replace|drop|create)\s+/i" , $query ) ) {		
         # Perform the query and log number of affected rows
          $this->rows_affected = $this->dbHandle->exec( $query );
         # If there is an error then take note of it..
			    $err_array = $this->dbHandle->errorInfo();
			   # Note: Ignoring error - bind or column index out of range
			    if ( isset( $err_array[1] ) && $err_array[1]!=25 ) {
				    $this->handleError( 3 , implode( ', ' , $err_array ) );
				    return false;
			    }
          $is_insert = true;
         # Take note of the insert_id
          if ( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
            $this->insert_id = @$this->dbHandle->lastInsertId();	
          }
         # Return number of rows affected
          $return_val = $this->rows_affected;
        } else {
         # Perform the query and log number of affected rows
          $sth = $this->dbHandle->query( $query );
         # If there is an error then take note of it..
          if ( $this->catch_error() ) return false;
          $is_insert = false;
          $col_count = $sth->columnCount();
          for ( $i=0 ; $i<$col_count ; $i++ ) {
            if ( $meta = $sth->getColumnMeta( $i ) ) {					
              $this->col_info[$i]->name =  $meta['name'];
              $this->col_info[$i]->type =  $meta['native_type'];
              $this->col_info[$i]->max_length =  '';
            } else {
              $this->col_info[$i]->name =  'undefined';
              $this->col_info[$i]->type =  'undefined';
              $this->col_info[$i]->max_length = '';
            }
          }
         # Store Query Results
          $num_rows=0;
          while ( $row = @$sth->fetch( PDO::FETCH_ASSOC ) ) {
           # Store relults as an objects within main array
            $this->last_result[$num_rows] = (object) $row;
            $num_rows++;
          }
         # Log number of rows the query returned
          $this->num_rows = $num_rows;
         # Return number of rows selected
          $return_val = $this->num_rows;
        }
        break;
*/

      case 'mssql' :
       # Perform the query via std mssql_query function.. If there is an error then take note of it..
        if ( !$this->result = @mssql_query( $query ) ) {
          $get_errorcodeSql = "SELECT @@ERROR as errorcode";
          $error_res = @mssql_query( $get_errorcodeSql , $this->dbHandle );
          $errorCode = @mssql_result( $error_res , 0 , "errorcode" );
          $get_errorMessageSql = "SELECT severity as errorSeverity, text as errorText FROM sys.messages  WHERE message_id = $errorCode" ;
          $errormessage_res =  @mssql_query( $get_errorMessageSql , $this->dbHandle );
          if ( $errormessage_res ) {
            $errorMessage_Row = @mssql_fetch_row( $errormessage_res );
            $errorSeverity = $errorMessage_Row[0];
            $errorMessage = $errorMessage_Row[1];
          }
          $sqlError = "ErrorCode: $errorCode ### Error Severity: $errorSeverity ### Error Message: $errorMessage ### Query: $query";
          $is_insert = true;
          $this->handleError( 2 , $sqlError );
          return false;
        }
       # Query was an insert, delete, update, replace
        $is_insert = false;
        if ( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
          $this->rows_affected = @mssql_rows_affected( $this->dbHandle );
         # Take note of the insert_id
          if ( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
            if ( $identityresultset = @mssql_query( "select SCOPE_IDENTITY()" ) ) {
              $identityrow = @mssql_fetch_row( $identityresultset );
              $this->insert_id = $identityrow[0];
            }
          }
         # Return number of rows affected
          $return_val = $this->rows_affected;
        } else {
         # Take note of column info
          $i=0;
          while ( $i<@mssql_num_fields( $this->result ) ) {
            $this->col_info[$i] = @mssql_fetch_field( $this->result );
            $i++;
          }
         # Store Query Results
          $num_rows=0;
          while ( $row = @mssql_fetch_object($this->result) ) {
           # Store relults as an objects within main array
            $this->last_result[$num_rows] = $row;
            $num_rows++;
          }
         #Free the MSSQL Resource
          @mssql_free_result( $this->result );
         # Log number of rows the query returned
          $this->num_rows = $num_rows;
         # Return number of rows selected
          $return_val = $this->num_rows;
        }
        break;

      case 'sqlite' :
       # Perform the query via std mysql_query function..
        $this->result = @sqlite_query( $this->dbHandle , $query );
        $this->num_queries++;
       # If there is an error then take note of it..
        if ( @sqlite_last_error( $this->dbHandle ) ) {
          $err_str = sqlite_error_string( sqlite_last_error( $this->dbHandle ) );
          $this->handleError( 2 , $err_str );
          return false;
        }
       # Query was an insert, delete, update, replace
        if ( preg_match( "/^(insert|delete|update|replace)\s+/i" , $query ) ) {
          $this->rows_affected = @sqlite_changes( $this->dbHandle );
         # Take note of the insert_id
          if ( preg_match( "/^(insert|replace)\s+/i" , $query ) ) {
            $this->insert_id = @sqlite_last_insert_rowid( $this->dbHandle );	
          }
         # Return number fo rows affected
          $return_val = $this->rows_affected;
        } else {
         # Take note of column info	
          $i=0;
          while ( $i<@sqlite_num_fields( $this->result ) ) {
            $this->col_info[$i]->name       = sqlite_field_name ( $this->result , $i );
            $this->col_info[$i]->type       = null;
            $this->col_info[$i]->max_length = null;
            $i++;
          }
         # Store Query Results
          $num_rows=0;
          while ( $row =  @sqlite_fetch_array( $this->result , SQLITE_ASSOC ) ) {
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
        return false;
    }
    
   # Disk caching of queries
    $this->store_cache( $query , $is_insert );
   # If debug ALL queries
    $this->debug_all ? $this->debug() : null ;
   # Catch the End Time
    $this->last_time = $startTime - getMicro();
    $this->all_time += $this->last_time;
   # Return response
    return $return_val;
  }

  // Format a string correctly for safe insert
  function escape( $str ) {
    switch( $this->dbType ) {
      case 'mysql' :
        return mysql_escape_string( stripslashes( $str ) );
        break;
      case 'postgresql' :
        return pg_escape_string( stripslashes( $str ) );
        break;
      case 'oracle' :
      case 'oracle8' :
      case 'oracle9' :
        return str_replace( "'" , "''" , str_replace( "''" , "'" , stripslashes( $str ) ) );
        break;
      case 'pdo' :
        switch ( gettype( $str ) ) {
          case 'string' :  return addslashes( stripslashes( $str ) ); break;
          case 'boolean' : return ( $str===FALSE ? 0 : 1 ); break;
          default :        return ( $str===NULL ? 'NULL' : $str );
        }
        break;
      case 'mssql' :
        return str_ireplace( "'" , "''" , $str );
        break;
      case 'sqlite' :
        return sqlite_escape_string( stripslashes( preg_replace( "/[\r\n]/" , '' , $str ) ) );
        break;
      default :
        return false;
    }
  }

  // Return database specific system date syntax
  function sysdate() {
    switch( $this->dbType ) {
      case 'mysql' :
      case 'postgresql' :
        return 'NOW()';
        break;
      case 'oracle' :
      case 'oracle8' :
      case 'oracle9' :
        return 'SYSDATE';
        break;
      case 'mssql' :
        return 'getDate()';
        break;
      case 'pdo' :
        return "datetime('now')";
        break;
      case 'sqlite' :
        return 'now';
        break;
      default :
        return false;
    }
  }



 // Data Retrieval and Management Functions
 //============================================================================

  // Kill cached query results
  function flush() {
   # Get rid of these
    $this->last_result = null;
    $this->col_info = null;
    $this->last_query = null;
    $this->from_disk_cache = false;
  }

  // Get one variable from the DB
  // See docs for more detail
  function get_var( $query=null , $x=0 , $y=0 ) {
   # Log how the function was called
    $this->func_call = "\$db->get_var(\"$query\",$x,$y)";
   # If there is a query then perform it if not then use cached results..
    if ( $query ) {
      $this->query( $query );
    }
   # Extract var out of cached results based x,y vals
    if ( $this->last_result[$y] ) {
      $values = array_values( get_object_vars( $this->last_result[$y] ) );
    }
   # If there is a value return it else return null
    return ( ( isset( $values[$x] ) && $values[$x]!=='' ) ? $values[$x] : null );
  }

  // Get one row from the DB
  // See docs for more detail
  function get_row( $query=null , $output=OBJECT , $y=0 ) {
   # Log how the function was called
    $this->func_call = "\$db->get_row(\"$query\",$output,$y)";
   # If there is a query then perform it if not then use cached results..
    if ( $query ) {
      $this->query( $query );
    }
   # Return data based on output selection
    switch ( $output ) {
      case OBJECT :  return ( $this->last_result[$y] ? $this->last_result[$y] : null );
      case ARRAY_A : return ( $this->last_result[$y] ?get_object_vars( $this->last_result[$y] ) : null );
      case ARRAY_N : return ( $this->last_result[$y] ? array_values( get_object_vars( $this->last_result[$y] ) ) : null );
      default :      $this->print_error( "\$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N" );
    }
  }

  // Get 1 column from the cached result set based in X index
  // See docs for usage and info
  function get_col( $query=null , $x=0 ) {
   # If there is a query then perform it if not then use cached results..
    if ( $query ) {
     $this->query( $query );
    }
   # Extract the column values
    for ( $i=0 ; $i<count( $this->last_result ) ; $i++ ) {
      $new_array[$i] = $this->get_var( null , $x , $i );
    }
    return $new_array;
  }

  // Return the the query as a result set
  // See docs for more details
  function get_results( $query=null , $output=OBJECT ) {
   # Log how the function was called
    $this->func_call = "\$db->get_results(\"$query\", $output)";
   # If there is a query then perform it if not then use cached results..
    if ( $query ) {
      $this->query( $query );
    }
   # Send back array of objects. Each row is an object
    if ( $output==OBJECT ) {
      return $this->last_result;
    }
    if ( $output==ARRAY_A || $output==ARRAY_N ) {
      if ( $this->last_result ) {
        $i=0;
        foreach( $this->last_result as $row ) {
          $new_array[$i] = get_object_vars( $row );
          if ( $output==ARRAY_N ) {
            $new_array[$i] = array_values($new_array[$i]);
          }
          $i++;
        }
        return $new_array;
      }
    }
    return null;
  }

  // Function to get column meta data info pertaining to the last query
  // See docs for more info and usage
  function get_col_info( $info_type="name" , $col_offset=-1 ) {
    if ( $this->col_info ) {
      if ( $col_offset==-1 ) {
        $i=0;
        foreach( $this->col_info as $col ) {
          $new_array[$i] = $col->{$info_type};
          $i++;
        }
        return $new_array;
      }
      return $this->col_info[$col_offset]->{$info_type};
    }
    return null;
  }



 // Cache Management Functions
 //============================================================================

 // Store last result in Cache
  function store_cache( $query , $is_insert ) {
   # The would be cache file for this query
    $cache_file = $this->cache_dir.'/'.md5($query);
   # Disk caching of queries
    if ( $this->use_disk_cache && ( $this->cache_queries && !$is_insert ) || ( $this->cache_inserts && $is_insert ) ) {
      if ( !is_dir( $this->cache_dir ) ) {
        handleError( 2 , "Could not open cache dir: $this->cache_dir" );
      } else {
       # Cache all result values
        $result_cache = array (
          'col_info'     => $this->col_info,
          'last_result'  => $this->last_result,
          'num_rows'     => $this->num_rows,
          'return_value' => $this->num_rows,
        );
        file_put_contents( $cache_file , serialize( $result_cache ) );
      }
    }
  }

  // Extract result from Cache
  function get_cache( $query ) {
   # The would be cache file for this query
    $cache_file = $this->cache_dir.'/'.md5($query);
   # Try to get previously cached version
    if ( $this->use_disk_cache && file_exists( $cache_file ) ) {
     # Only use this cache file if less than 'cache_timeout' (hours)
      if ( ( time()-filemtime( $cache_file ) )>( $this->cache_timeout*3600 ) ) {
       # Destory Cache as Too Old
        handleError( 1 , "Cache file $cache_file destoryed as too old" );
        unlink( $cache_file );
        return null;
      }
      $result_cache = unserialize( file_get_contents( $cache_file ) );
      $this->col_info = $result_cache['col_info'];
      $this->last_result = $result_cache['last_result'];
      $this->num_rows = $result_cache['num_rows'];
      $this->from_disk_cache = true;
     # If debug ALL queries
      $this->trace || $this->debug_all ? $this->debug() : null ;
      return $result_cache['return_value'];
    }
    return null;
  }

  // Dumps the contents of any input variable
  function vardump( $mixed='' ) {
   # Start outup buffering
    ob_start();
    echo '<p><table><tr><td bgcolor="#ffffff"><blockquote><font color="#000090">';
    echo '<pre><font face="Arial">';
    if ( !$this->vardump_called ) {
      echo '<font color="#800080"><b>SQueLCH</b> (v'.SQueLCH_VERSION.') <b>Variable Dump..</b></font>'."\n\n";
    }
    $var_type = gettype( $mixed );
    print_r( ( $mixed ? $mixed : '<font color="red">No Value / False</font>' ) );
    echo "\n\n".'<b>Type:</b> '.ucfirst( $var_type )."\n";
    echo "<b>Last Query</b> [$this->num_queries]<b>:</b> ".( $this->last_query ? $this->last_query : 'NULL' )."\n";
    echo '<b>Last Function Call:</b> ' .( $this->func_call ? $this->func_call : 'None' )."\n";
    echo '<b>Last Rows Returned:</b> '.count( $this->last_result )."\n";
    echo '</font></pre></font></blockquote></td></tr></table>';
    echo "\n".'<hr size="1" noshade color="#dddddd">';
   # Stop output buffering and capture debug HTML
    $html = ob_get_contents();
    ob_end_clean();
   # Only echo output if it is turned on
    handleError( 0 , $html );
    $this->vardump_called = true;
    return $html;
  }

  // Alias for the above function
  function dumpvar( $mixed ) {
    $this->vardump( $mixed );
  }

  // Displays the last query string that was sent to the database & a table listing results (if there were any).
  function debug() {
   # Start outup buffering
    ob_start();
    echo "<blockquote>";
   # Only show SQueLCH credits once..
    if ( !$this->debug_called ) {
      echo '<font color="#800080" face="Arial" size="2"><b>SQueLCH</b> (v'.SQueLCH_VERSION.') <b>Debug..</b></font><p>'."\n";
    }
    if ( $this->last_error ) {
      echo "<font face=\"Arial\" size=\"2\" color=\"#000099\"><b>Last Error --</b> [<font color=\"#000000\"><b>$this->last_error</b></font>]<p>";
    }
    if ( $this->from_disk_cache ) {
      echo '<font face="Arial" size="2" color="#000099"><b>Results retrieved from disk cache</b></font><p>';
    }
    echo "<font face=\"Arial\" size=\"2\" color=\"#000099\"><b>Query</b> [$this->num_queries] <b>--</b> ";
    echo "[<font color=\"#000000\"><b>$this->last_query</b></font>]</font><p>";
    echo '<font face="Arial" size="2" color="#000099"><b>Query Result..</b></font>';
    echo '<blockquote>';
    if ( $this->col_info ) {
     # Results top rows
      echo '<table cellpadding="5" cellspacing="1" bgcolor="#555555">';
      echo '<tr bgcolor="#EEEEEE"><td nowrap valign="bottom"><font color="#555599" face="Arial" size="2"><b>(row)</b></font></td>';
      for ( $i=0 ; $i<count( $this->col_info ); $i++ ) {
        echo "<td nowrap align=\"left\" valign=\"top\"><font size=\"1\" color=\"#555599\" face=\"Arial\">{$this->col_info[$i]->type} {$this->col_info[$i]->max_length}</font><br><span style=\"font-family:Arial;font-size:10pt; font-weight:bold;\">{$this->col_info[$i]->name}</span></td>";
      }
      echo "</tr>";
     # Print main results
      if ( $this->last_result ) {
        $i=0;
        foreach ( $this->get_results(null,ARRAY_N) as $one_row ) {
          $i++;
          echo "<tr bgcolor=\"#ffffff\"><td bgcolor=\"#eeeeee\" nowrap align=\"middle\"><font size=\"2\" color=\"#555599\" face=\"Arial\">$i</font></td>";
          foreach ( $one_row as $item ) {
            echo "<td nowrap><font face=\"Arial\" size=\"2\">$item</font></td>";
          }
          echo '</tr>';
        }
      } else {
        echo '<tr bgcolor="#ffffff"><td colspan='.(count($this->col_info)+1).'><font face="Arial" size="2">No Results</font></td></tr>';
      }
      echo '</table>';
    } else {
      echo '<font face="Arial" size="2">No Results</font>';
    }
    echo '</blockquote></blockquote><hr noshade color="#dddddd" size="1">';
   # Stop output buffering and capture debug HTML
    $html = ob_get_contents();
    ob_end_clean();
   # Only echo output if it is turned on
    handleError( 0 , $html );
    $this->debug_called = true;
    return $html;
  }

}

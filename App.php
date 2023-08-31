<?php

	/**
	* Application Interface
	* @package	FluidPhp MVC
	* @author	Carlo Pietrobattista
	*/
	
	namespace fluidphp\framework;
	
	use phptoolcase\HandyMan as HandyMan;
	use phptoolcase\Debug as Debug;
	use phptoolcase\Router as Router;
	use phptoolcase\Db as DB;
	use phptoolcase\Event as Event;
	use fluidphp\framework\Module\Manager as Module;

	class App
	{
		/**
		* Application start event
		* @param	mixed	$callback		a valid callback
		*/
		public static function start( $callback ){ return ptc_listen( 'app.start' , $callback ); }
		/**
		* Application stop event
		* @param	mixed	$callback		a valid callback
		*/
		public static function stop( $callback ){ return ptc_listen( 'app.stop' , $callback ); }
		/**
		*
		*/
		public function __construct( )
		{
			$class = get_called_class( );
			$event = Event::get( 'app' );
			if ( is_array( $event ) && ptc_array_get( $event , 'start' , false ) )
			{ 
				ptc_fire( 'app.start' , array( &$class::$_config , &$this ) ); 
			}
			$this->_appConfig = $class::$_config;
			ptc_log( $this->_appConfig , 'Started the application' , 'App Config' );
		}
		/**
		*
		*/
		public function run( $print = true )
		{
			$check_config = ptc_array_get( $this->_appConfig , 'app.check_router_config' );
			$this->_modules = Module::all( );
			$uri = Router::getUri( );
			$location = Module::location( );
			$raw = str_replace( static::option( 'modules.env' ) , '' , $uri[ 'path' ] );
			if ( $location && 0 === strpos( $raw , $location ) )
			{
				$raw = substr( $raw , strlen( $location ) );
				$uri[ 'path' ] = static::option( 'modules.env' ) . $raw;
			}
			$response = Router::run( $check_config , $uri ); 
			$class = get_called_class( );
			return $class::_shutdown( $response , $print , $this );
		}
		/**
		*
		*/
		public function cli( $arguments , $print = true )
		{
			$flags = getopt( 'm:c:' );
			if ( !isset( $flags[ 'c' ] ) )
			{
				throw new \Exception( 'Please use the "-c" flag followed by the process name' );
			}
			foreach ( $flags as $k => $v )
			{	
				unset( $arguments[ array_search( '-' . $k , $arguments ) ] );
				unset( $arguments[ array_search( $v , $arguments ) ] );
			}
			unset( $arguments[ 0 ] );
			$arguments = array_values( $arguments );
			$response = Cli::run( $flags[ 'c' ] , $arguments ); 
			$class = get_called_class( );
			return $class::_shutdown( $response , $print , $this );
		}
		/**
		*
		*/
		public static function env( )
		{
			return $env = ( '/' === substr( App::option( 'app.env' ) , -1 ) ) ? 
					substr( App::option( 'app.env' ) , 0 , -1 ) : App::option( 'app.env' );
		}
		/**
		* Alias of @ref App::options( )
		*/
		public static function option( $key = null , $option = null )
		{ 
			return static::options( $key , $option );
		}
		/**
		*
		*/
		public static function options( $key = null , $options = null )
		{
			if ( is_null( $options ) )
			{
				if ( !$key ){ return static::$_config; }
				return ptc_array_get( static::$_config , $key );
			}
			else if ( ptc_array_get( static::$_config , $key ) )
			{
				trigger_error( 'Option name ' . $key . ' already exists!' , E_USER_ERROR );
				return false;
			}
			return ptc_array_set( static::$_config , $key , $options );
		}
		/**
		*
		*/
		public static function storage( $key = null , $value = null )
		{
			if ( is_null( $value ) )
			{
				if ( !$key ){ return static::$_storage; }
				return ptc_array_get( static::$_storage , $key );
			}
			else if ( ptc_array_get( static::$_storage , $key ) )
			{
				trigger_error( 'Storage name ' . $key . ' already exists!' , E_USER_ERROR );
				return false;
			}
			return ptc_array_set( static::$_storage , $key , $value );
		}
		/**
		*
		*/
		public static function configure( )
		{
			static::_loadEnvVars();
			static::_registerModules( );
			static::_setPhpInit( );
			static::_setDebug( );
			static::_setAppConfig( );
			static::_setCustomConfigFiles( );
		}
		/**
		*
		*/
		protected $_appConfig = array( );
		/**
		*
		*/
		protected $_modules = array( );
		/**
		*
		*/
		protected static $_config = array( );
		/**
		*
		*/
		protected static $_storage = array( );
		/**
		*
		*/
		protected static $_debugLevels = array
		( 
			'only_system_actions'	=>	array
			(
				'Autoloader Config' , 'Paths Manager' , 'Router Config' , 'App Config' ,
				'View Config' , 'Translator Helper Config' , 'Website Helper Config' ,
				'StreamWrapper Config' , 'Module Manager Config'
			) ,
			'no_system_messages'	=>	array
			(
				'Router Action' , 'View Config' , 'View Action' , 
				'Autoloader Config' , 'Autoloader Action' ,'Paths Manager' , 
				'App Config' , 'Website Helper Config' , 'Website Helper Action' , 
				'Router Config' , 'Translator Helper Config' , 'Session Manager' ,
				'Event Manager' , 'StreamWrapper Action' , 'StreamWrapper Config' , 
				'Modules Manager Config' , 'Modules Manager Action'
			)
		);
		/**
		*
		*/
		protected static function _shutdown( $response , $print , $obj )
		{
			$event = Event::getEvent( 'app');
			if ( is_array( $event ) && ptc_array_get( $event , 'stop' , false ) )
			{ 
				ptc_fire( 'app.stop' , array( $obj , &$response ) ); 
			}
			if ( !$print ){ return $response; }  
			echo $response;
		}
		/**
		* Php configuration at runtime
		*/
		protected static function _setPhpInit( )
		{
			$init = Module::merge( ptc_path( 'root' ) . '/app/config/init.php' );
			foreach ( $init as $k => $v )
			{
				if ( is_array( $v ) )
				{
					foreach ( $v as $key => $val )
					{
						ini_set( $k . '.' . $key , $val );
					}
				}
				else
				{
					ini_set( $k , $v );
				}
			}
			static::option( 'init' , $init );
		}
		/**
		* Set debug at runtime
		*/
		protected static function _setDebug( )
		{
			$debug =  Module::merge( ptc_path( 'root' ) . '/app/config/debug.php' ); 
			if ( file_exists( ptc_path( 'root' ) . '/app/config/debug_ajax.php' ) )
			{
				if ( !empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && 
					( 'xmlhttprequest' === strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) || 
								'ajax' === strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ]) ) ) 
				{
					$debug_ajax =  require_once( ptc_path( 'root' ) . '/app/config/debug_ajax.php' ); 
					$debug = array_merge( $debug , $debug_ajax );
				}
			}
			static::option( 'debug' , $debug );
			require_once( ptc_path( 'root' ) . '/vendor/phptoolcase/phptoolcase/lib/Debug.php' );
			if ( !is_null( ptc_array_get( static::$_config , 'debug.replace_error_handler' , null ) ) )
			{
				$die_on_error = ( ptc_array_get( static::$_config , 'debug.die_on_error' ) ) ? true : false;
				Debug::setErrorHandler( $die_on_error );
				ptc_array_set( static::$_config , 'debug.replace_error_handler' , false , true );
			}
			if ( ptc_array_get( static::$_config , 'debug.start' ) )
			{ 
				$exclude = ptc_array_get( static::$_config , 'debug.exclude_categories' );
				if ( is_string( $exclude ) && array_key_exists( $exclude , static::$_debugLevels ) )
				{
					ptc_array_set( static::$_config , 'debug.exclude_categories' , 
										static::$_debugLevels[ $exclude ], true );
				}
				if ( isset( $_GET[ 'debug_level' ] ) )
				{
					ptc_array_set( static::$_config , 'debug.exclude_categories' , 
									static::$_debugLevels[ $_GET[ 'debug_level' ] ] , true );
				}
				Debug::load( static::$_config[ 'debug' ] ); 
			}
		}
		/**
		* Registers the Modules Manager
		*/
		protected static function _registerModules( )
		{
			static::option( 'modules' , Module::merge( ptc_path( 'root' ) . '/app/config/modules.php' ) );
			Module::check( );
		}
		/**
		* Application bootstrap configuration
		*/
		protected static function _setAppConfig( )
		{
			/* paths */
			static::option( 'paths' , Module::merge( ptc_path( 'root' ) . '/app/config/paths.php' ) );
			ptc_add_path( ptc_array_get( static::$_config , 'paths' ) );
			/* application */
			static::option( 'app' , Module::merge( ptc_path( 'root' ) . '/app/config/app.php' ) );
			HandyMan::addAlias( ptc_array_get( static::$_config , 'app.aliases' ) );
			if ( $locale = ptc_array_get( static::$_config , 'app.locale' ) )
			{ 
				setlocale( LC_ALL , $locale . 'UTF-8' ); 
			} 
			if ( $timezone = ptc_array_get( static::$_config , 'app.timezone' ) )
			{ 
				date_default_timezone_set( $timezone ); 
			}
			ptc_add_file( ptc_array_get( static::$_config , 'app.files' ) );
			ptc_add_dir( ptc_array_get( static::$_config , 'app.directories' ) );
			ptc_add_dir( ptc_array_get( static::$_config , 'app.namespaces' ) );
			if ( $sep = ptc_array_get( static::$_config , 'app.separators' ) )
			{
				HandyMan::addSeparators( $sep );
			}
			if ( $conv = ptc_array_get( static::$_config , 'app.conventions' ) )
			{
				HandyMan::addConventions( $conv );
			}
			/* database */
			static::option( 'db' , Module::merge( ptc_path( 'root' ) . '/app/config/db.php' ) );
			if ( $db = ptc_array_get( static::$_config , 'db' ) )
			{
				$loop = ( static::option( 'app.test_env' ) ) ? $db[ 'develop' ] : $db[ 'prod' ];
				foreach ( $loop as $k => $v )
				{ 
					if ( ptc_array_get( $v , 'user' ) )
					{ 
						DB::add( $v , $k );
					}
				}
			}
			/* auth */
			static::option( 'auth' , Module::merge( ptc_path( 'root' ) . '/app/config/auth.php' ) );
		}
		/**
		* Load custom config files
		*/
		protected static function _setCustomConfigFiles( )
		{	
			/* custom config files */
			$files = ['..' , '.' , 'app.php' , 'db.php' , 'debug.php' , 'init.php' , 
						'modules.php' ,'paths.php' , 'auth.php' , 'debug_ajax.php'];
			$scanned_directory = array_diff( scandir( ptc_path( 'root' ) . '/app/config' ) , $files );
			if ( !empty( $scanned_directory ) )
			{
				foreach ( $scanned_directory as $file )
				{
					$option_name = str_replace( '.php' , '' , $file );
					$options = Module::merge( ptc_path( 'root' ) . '/app/config/' . $file );
					if ( ptc_array_get( $options , '_load' ) )
					{
						$options = call_user_func( ptc_array_get( $options , '_load' ) , $options );
					}
					static::option( $option_name , $options );
				}
			}
			static::_loadModulesConfig( );
		}
		/**
		* Load module config files that are not in root config
		*/
		protected static function _loadModulesConfig( )
		{
			$config = array( '..' , '.' );
			foreach ( static::options( ) as $key => $val )
			{
				$config[ ] = $key . '.php';
			}
			foreach ( Module::all( ) as $k => $module )
			{
				$scanned_directory = array_diff( scandir( ptc_path( 'root' ) . '/modules/' . $k . '/config' ) , $config );
				if ( !empty( $scanned_directory ) )
				{
					foreach ( $scanned_directory as $file )
					{
						$option_name = str_replace( '.php' , '' , $file );
						if ( !static::option( $option_name ) )
						{
							$options = require( ptc_path( 'root' ) . '/modules/' . $k . '/config/' . $file );
							if ( ptc_array_get( $options , '_load' ) )
							{
								$options = call_user_func( ptc_array_get( $options , '_load' ) , $options );
							}
							static::option( $option_name , $options );
						}
					}
				}
			}
		}
		/**
		* Load environment variables
		*/
		protected static function _loadEnvVars()
		{
			$env_file_path = ptc_path( 'root' ) . '/.env';
			if (file_exists($env_file_path))
			{
				$env_data = file_get_contents($env_file_path);
				$data = [ ];
				foreach( preg_split( "/((\r?\n)|(\r\n?))/" , $env_data ) as $line )
				{
					if ( false !== strpos( $line , 'SetEnv' ) )	// old variables
					{
						$l = preg_replace( '/\s+/' , ' ' , $line );
						$env_var = explode(' ', $l, 3);
						putenv($env_var[1] . "=" . $env_var[2]);
						$_ENV[$env_var[1]] = $env_var[2];
						$_SERVER[$env_var[1]] = $env_var[2];
					}
					else
					{
						$env_var = explode('=', $line, 2);
						$env_var[0] = trim($env_var[0]);
						$env_var[1] = trim($env_var[1]);
						if (false === strpos($env_var[1], "'") && false === strpos($env_var[1], '"'))
						{
							if (is_numeric($env_var[1]))
							{
								$env_var[1] = (float)$env_var[1];
							}
							else
							{
								switch($env_var[1])
								{
									case 'true':
										$env_var[1] = true;
									break;
									case 'false':
										$env_var[1] = false;
									break;
									case 'null':
										$env_var[1] = null;
									break;
									default:
										$env_var[1] = $env_var[1];
								}
							}
						}
						else
						{
							$env_var[1] = preg_replace('~^[\'"]?(.*?)[\'"]?$~', '$1', $env_var[1]);
						}
					}
					$data[$env_var[0]] = $env_var; 
				} 
				foreach ($data as $env_var)
				{
					if (preg_match_all('/\${(.*?)}/', $env_var[1], $output))
					{
						foreach ($output[1] as $key => $value)
						{
							$env_var[1] = str_replace($output[0][$key], $data[$value][1], $env_var[1]);
						}
					}
					putenv($env_var[0] . "=" . $env_var[1]);
					$_ENV[$env_var[0]] = $env_var[1];
					$_SERVER[$env_var[0]] = $env_var[1];	
				}
			}
		}
	}

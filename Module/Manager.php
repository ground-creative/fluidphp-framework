<?php

	/**
	* Modules Manager
	* @package	FluidPhp MVC
	* @author	Carlo Pietrobattista
	*/
	
	namespace fluidphp\framework\Module;
	
	use fluidphp\framework\App as App;
	use PtcEvent as Event;

	class Manager
	{
		public static function register( )
		{
			Event::listen( 'autoloader.realpath' , function( &$path )
			{
				$path = str_replace( '//' , '/' , $path );
				$path = str_replace( array( '/' , '\\' ) , DIRECTORY_SEPARATOR , $path );
				$path = \system\Core\Module\StreamWrapper::intercept( $path , 'realpath' );
			} );
			StreamWrapper::wrap( );
		}
		
		public static function all( )
		{
			return static::$_modules;
		}
		
		public static function get( $name )
		{
			return static::$_modules[ $name ];
		}
		
		public static function location( )
		{
			if ( !static::$_currentLocation ){ return null; }
			return static::_env( static::$_currentLocation );
		}
		
		public static function domain( )
		{
			return static::$_currentDomain;
		}
		
		public static function isLoaded( $name )
		{
			return ( array_key_exists( $name , static::$_modules ) ) ? true : false;
		}
		
		public static function merge( $file )
		{
			if ( empty( static::$_modules ) )
			{
				return require_once( $file );
			}
			$option_name = @end( explode( '/' , str_replace( '.php' , '' , $file ) ) );
			$options = ( file_exists( $file ) ) ? require_once( $file ) : array( );
			foreach ( static::$_modules as $module )
			{
				$m_path = $module->path( );
				$newfile = str_replace( ptc_path( 'root' ) . 
							'/app/config' , $m_path . '/config' , $file );	
				$module_options = @$module->option( $option_name );
				if ( $newfile != $file && $module_options )
				{
					foreach ( $module_options  as $k => $v )
					{
						if ( is_array( $v ) && array_key_exists( $k , $options ) )
						{
							$val = array_merge( $options[ $k ] , $v );
							//$options[ $k ] = array_unique( $val );
							$options[ $k ] = array_map( 'unserialize' , 
								array_unique( array_map( 'serialize' , $val ) ) );
						}
						else { $options[ $k ] = $v; }
					}
					ptc_log( [ $newfile , $options ] , 'Module "' . $module->name( ) . 
								'" merged config file!' , 'Modules Manager Action' );
				}
			}
			return $options;
		}
		
		public static function check( )
		{
			$options = App::option( 'modules' );
			$modules = ( $options[ 'test_env' ] ) ? $options[ 'develop' ] : $options[ 'prod' ];
			if ( 'cli' === PHP_SAPI ) 
			{
				$module = getopt( 'm:' );
				if ( isset( $module[ 'm' ] ) )
				{	
					static::_create( $module[ 'm' ] );
				}
			}
			else
			{
				if ( isset( $modules[ 'domains' ] ) )
				{			
					static::_checkDomains( $modules[ 'domains' ] );
				}
				if ( isset( $modules[ 'locations' ] ) && empty( static::$_modules ) )
				{
					static::_checkLocations( $modules[ 'locations' ] );
				}
			}
			if ( !empty ( static::$_modules ) ){ static::register( ); }
		}
		
		protected static $_modules = array( );
		
		protected static $_currentLocation = null;
		
		protected static $_currentDomain = null;
		
		protected static function _checkDomains( $domains )
		{
			if ( isset( $domains[ '*' ] ) ) // wildcard
			{
				static::_create( $domains[ '*' ] );
			}
			foreach ( $domains as $k => $v )
			{
				if ( $k === $_SERVER[ 'HTTP_HOST' ] )
				{
					static::$_currentDomain = $k;
					static::_create( $v );
					break; // 1 domain only
				}
			}
		}
		
		protected static function _checkLocations( $locations )
		{
			$env = App::option( 'modules.env' );
			$uri = str_replace( $env , '' , $_SERVER[ 'REQUEST_URI' ] );
			foreach ( $locations as $k => $v )
			{
				if ( 0 === strpos( $uri , $k ) )
				{
					static::$_currentLocation = $k;
					static::_create( $v );
					break; // only 1 location
				}
			}
		}
		
		protected static function _create( $modules )
		{
			$modules = ( is_array( $modules ) ) ? $modules : array( $modules );
			foreach ( $modules as $name )
			{
				if ( array_key_exists( $name , static::$_modules ) )
				{
					trigger_error( 'Module "' . $name . 
						'" already present!' , E_USER_ERROR );
					return;
				}
				static::_addModule( $name );
			}
		}
		
		protected static function _addModule( $name )
		{
			$path = ptc_path( 'root' ) . '/modules/' .  $name;
			if ( !realpath( $path ) )
			{
				trigger_error( 'Module "' . $name . 
					'" is not accessible!' , E_USER_ERROR );
				return;
			}
			static::$_modules[ $name ] = new Instance( $name , $path );
			ptc_add_path( [ 'module_' . $name => $path ] );
		}

		protected static function _env( $string )
		{
			return ( '/' === substr( $string , -1 ) ) ? substr( $string , 0 , -1 ) : $string;
		}
	}
<?php

	/**
	* CLI Interface
	* @package	FluidPhp MVC
	* @author	Carlo Pietrobattista
	*/
	
	namespace fluidphp\framework;
	
	Class Cli
	{
		public static function listen( $name , $callback )
		{
			static::$_calls[ $name ] = $callback;
		}
		
		public static function run( $name , $args )
		{
			if ( !isset( static::$_calls[ $name ] ) )
			{
				throw new \Exception( 'No CLI callback found with name "' . $name . '"' );
				return false;
			}
			return call_user_func_array( static::$_calls[ $name ] , $args );
		}
		
		protected static $_calls = array( );
	}
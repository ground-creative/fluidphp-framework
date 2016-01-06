<?php

	/**
	* Module Instance
	* @package	FluidPhp MVC
	* @author	Carlo Pietrobattista
	*/
	
	namespace fluidphp\framework\Module;

	class Instance
	{		
		public function __construct( $name , $path )
		{
			$this->_path = $path;
			$this->_name = $name;
			$this->_getConfigFiles( );
		}
		
		public function name( )
		{
			return $this->_name;
		}
		
		public function path( )
		{
			return $this->_path;
		}
		
		public function option( $key = null )
		{
			return $this->options( $key );
		}
		
		public function options( $key = null )
		{
			return ( $key ) ? $this->_config[ $key ] : $this->_config;
		}
		
		protected function _getConfigFiles( )
		{
			if ( !$directory = @scandir( $this->path( ) . '/config' ) )
			{ 
				return array( ); 
			}
			foreach ( $directory as $file )
			{
				if ( '.' === $file || '..' === $file ){ continue; }
				$option_name = str_replace( '.php' , '' , $file );
				$this->_config[ $option_name ] = 
					require_once( $this->path( ) . '/config/' . $file );
			}
		}
		
		protected $_path = null;
		
		protected $_config = array( );
		
		protected $_name = null;
	}
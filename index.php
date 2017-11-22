<?php

require( 'defines.php' );

session_start();

require( 'functions.php' );
require( 'conad.classes.php' );


$infoMsg    = "";
$errorMsg   = "";
$validUser  = ( isset( $_SESSION[ "logged" ] ) && $_SESSION[ "logged" ] === true ) ? true : false ;

if( isset( $_POST["action"] ) ) {

	switch( $_POST["action"] ){

		//
		// LOGIN PAGE
		//
		case "login":

			//
			// are we good?
			//
			$validUser = ( $_POST[ "username" ] == USERNAME ) && ( $_POST[ "password" ] == PASSWORD );

			if( !$validUser ){
				$errorMsg   = "Invalid username or password.";
			}else{
				$_SESSION[ "logged" ] = true;
				$infoMsg    = "Welcome!";
			}

			break;


		//
		// SUBMIT DATA PAGE
		//
		case "submit_data":

			try{

				if( ! $validUser ){
					throw new Exception( "Sei brutto perche` non sei loggato." );
				}

				// check parameters
				if(
					! isset( $_FILES['file_fattura'] ) ||
					! isset( $_FILES['file_ddt'] )
				){
					throw new Exception( "Hai sbagliato i parametri, minchione." );
				}

				//
				// now let's do the dirty work
				//
				$conad_exporter = new ConadExporter();
				$conad_exporter->getThatDamnTXT();

				// ... and die full of joy
				exit();

			}catch( Exception $e ){
				$errorMsg = $e->getMessage() ;
			}

			break;

		//
		// unknown action, kill it!
		//
		default:
			dump( 'Mammt!' );

	}

}


if( $validUser ) {

	include( 'inc/view/form_export.phtml');

}else{

	include( 'inc/view/login.phtml');

}

<?php

class NdC extends Invoice{

	public function __construct() {

		parent::__construct();
		$this->document_type = 'N';

	}

	public function isNdc(){ return true; }

}

class Invoice{

	public $number;
	public $document_type;
	public $date_timestamp;
	private $ddts;

	// Valid DANEA document types
	public static $accepted_document_types = array( 'I' , 'J' , 'N' );

	public function __construct() {

		$this->ddts = array();
		$this->document_type = 'F';

	}

	public function addDDT( DDT $ddt ){
		$this->ddts[] = $ddt;
	}

	public function getDDTs(){
		return $this->ddts;
	}

	public function isNdc(){ return false; }

}



class DDT{

	public $customer_code;
	public $number;
	public $date_timestamp;
	private $rows;

	// Valid DANEA document types
	public static $accepted_document_types = array( 'D' );

	public function __construct() {

		$this->rows = array();

	}

	public function addRow( DDT_Row $row ){
		$this->rows[] = $row;
	}

	public function getRows(){
		return $this->rows;
	}

}



class DDT_Row{

	public $code;
	public $description;
	public $um;
	public $qty;
	public $price_single;
	public $price_total;
	public $vat_perc;

}



class ConadExporter {

	private $txt = "";

	private $accepted_extensions  = array( "xml" , "defxml" );
	private $xml_invoice, $xml_ddt ;
	private $invoice ;

	public function __construct() {

		$this->init();

		$this->importInvoice();
		$this->importDDTs();

		$this->createTXT();

	}


	/**
	* Check and Import XML data from POST data
	*
	*/
	private function init(){


		//
		// CHECK POST PARAMS
		//

		if(
			! isset( $_FILES['file_fattura'] ) ||
			! isset( $_FILES['file_ddt'] )
		){
			throw new Exception( 'Non hai caricato entrambi i file' );
		}

		//
		// check fattura file extension
		//
		$file_fattura_parts = pathinfo( $_FILES["file_fattura"]["name"]);
		$fattura_extension = strtolower( $file_fattura_parts['extension'] );

		if( in_array( $fattura_extension , $this->accepted_extensions ) === false ){
			throw new Exception( 'Estensione del file Fattura errato, non e` XML/DefXml ma ' . $fattura_extension );
		}

		//
		// check DDT file extension
		//
		$file_ddt_parts = pathinfo( $_FILES["file_ddt"]["name"]);
		$ddt_extension = strtolower( $file_ddt_parts['extension'] );

		if( in_array( $ddt_extension , $this->accepted_extensions ) === false ){
			throw new Exception( 'Estensione del file DDT errato, non e` XML/DefXml ma ' . $ddt_extension );
		}

		//
		// LOAD XML
		//
		$this->xml_invoice  = simplexml_load_file( $_FILES["file_fattura"]["tmp_name"] );
		$this->xml_ddt      = simplexml_load_file( $_FILES["file_ddt"]["tmp_name"] );

		if( ! $this->xml_invoice ) throw new Exception( 'Contenuto del file Fattura NON XML valido' );
		if( ! $this->xml_ddt ) throw new Exception( 'Contenuto del file DDT NON XML valido' );



	}


	/**
	* Import Invoice Data
	*
	*/
	private function importInvoice(){

		//
		// Check if this is the right type of document
		//
		$xml_doc_type = $this->xml_invoice->Documents->Document->DocumentType ;
		if( ! in_array( $xml_doc_type , Invoice::$accepted_document_types ) ){
			throw new Exception( 'Il documento della fattura che hai caricato NON E` QUELLO DI UNA FATTURA O DI UNA NdC!' );
		}

		//
		// IMPORT INVOICE
		//
		if( $xml_doc_type == 'N' ){

			//
			// initialize empty NdC object
			//
			$this->invoice  = new NdC();

		}else{

			//
			// initialize empty Invoice object
			//
			$this->invoice  = new Invoice();

		}

		$this->invoice->number          = (string) $this->xml_invoice->Documents->Document->Number;
		$this->invoice->date_timestamp  = strtotime( $this->xml_invoice->Documents->Document->Date );

	}


	/**
	* Import DDT data
	*
	*/
	private function importDDTs(){

		if( ! is_a( $this->invoice , 'Invoice' ) ) throw new Exception( 'Oggetto Invoice inesistente [DDT]' );

		//
		// Check if this is the right type of document
		//
		$xml_doc_type = $this->xml_ddt->Documents->Document->DocumentType ;
		if( ! in_array( $xml_doc_type , DDT::$accepted_document_types ) ){
			throw new Exception( 'Il documento dei DDT che hai caricato NON E` QUELLO DEI DDT!' );
		}


		//
		// IMPORT DDTs
		//
		$DDTS = $this->xml_ddt->Documents->Document;

		foreach( $DDTS AS $DDT ){

			//
			// create new DDT
			//
			$new_ddt = new DDT();

			$new_ddt->customer_code     = (string) $DDT->CustomerCode;
			$new_ddt->number            = (string) $DDT->Number;
			$new_ddt->date_timestamp    = strtotime( $DDT->Date );

			//
			// cycle through each Row
			//
			foreach( $DDT->Rows->Row AS $DDT_ROW ){

				$new_ddt_row = new DDT_Row();

				$new_ddt_row->code          = (string) $DDT_ROW->Code;
				$new_ddt_row->description   = (string) $DDT_ROW->Description;
				$new_ddt_row->um            = (string) $DDT_ROW->Um;
				$new_ddt_row->qty           = (string) $DDT_ROW->Qty;
				$new_ddt_row->price_single  = (string) $DDT_ROW->Price;
				$new_ddt_row->price_total   = (string) $DDT_ROW->Total;
				$new_ddt_row->vat_perc      = (string) $DDT_ROW->VatCode['Perc'];

				$new_ddt->addRow( $new_ddt_row );

			}

			//
			// add row to invoice
			//
			$this->invoice->addDDT( $new_ddt );
		}

	}

	/**
	* Let's create TXT with imported data
	*
	*/
	public function createTXT(){

		$DDTs = $this->invoice->getDDTs();

		if( empty( $DDTs ) ){
			return;
		}

		$ricorsivo_testata = 0;

		// cycle each DDT
		foreach( $DDTs AS $ddt ){

			$ricorsivo_testata++;

			// print main header line
			$this->printTestata( $ddt , $ricorsivo_testata );

			$rows = $ddt->getRows();
			if( empty( $rows ) ){
				continue;
			}

			//print each movement line
			foreach( $rows AS $row ){

				$this->printMovimento( $row , $ricorsivo_testata );
			}

		}

	}

	private function printTestata( DDT $ddt , $ricorsivo_testata ){

		//S(2) 1 - 2 tipo record 01
		$this->padNumber( 1 , 2 );

		//S(5) 3 - 7 numero progressivo, unisce ' testata' a ' righe'
		$this->padNumber( $ricorsivo_testata , 5 );

		//A(6) 8 - 13 numero fattura
		$this->padString( $this->invoice->number , 6 );

		//A(6) 14 - 19 data fattura in formato AAMMGG
		$this->padString( $this->formatDate( $this->invoice->date_timestamp ) , 6 );

		//A(6) 20 - 25 numero bolla
		$this->padNumber( $ddt->number , 6 );

		//A(6) 26 - 31 data bolla in formato AAMMGG
		$this->padString( $this->formatDate( $ddt->date_timestamp ) , 6 );

		//A(15) 32 - 46 codice fornitore che consegna la merce (concordato con ricevente) NON OBBLIGATORIO
		$this->padString( '' , 15 );

		//A(1) 47 - 47 blank (a 1 per rifatturazione interna ricevente)
		$this->padString( '' , 1 );

		//A(15) 48 - 62 cod. cliente intestatario della fattura (CODICE s/sconto) NON OBBLIGATORIO
		$this->padString( '' , 15 );

		//A(15) 63 - 77 cod. cooper. a cui viene consegnata la merce. In caso di consegna a punto vendita
		// è il cod. gruppo a cui il p. vendita appartiene (CODICE s/sconto) NON OBBLIGATORIO
		$this->padString( '' , 15 );

		//A(15) 78 - 92 cod. socio, se la consegna è al p. vendita contiene il codice socio a cui la merce
		// è stata consegnata, e può essere:
		// - CODICE cliente
		// - codice filiale, se il cliente è con filiali (OrtFil)
		// - codice di trascodifica da apposita tabella
		$this->padString( $ddt->customer_code , 15 );

		//A(1) 93 - 93 tipo socio blank= 78 - 92 codice cliente
		// 1 = 78 - 92 codice trascodificato
		$this->padString( '' , 1 );

		//A(1) 94 - 94 F = per fatture N = per note credito
		$this->padString( $this->invoice->document_type , 1 );

		//A(3) 95 - 97 EUR - Codice Divisa
		$this->padString( 'EUR' , 3 );

		//A(31) 98 - 128 blank
		$this->padString( '' , 31 );

		$this->addNL();

	}

	private function printMovimento( DDT_Row $row , $ricorsivo_movimento ){

		//S(2) 1 - 2 tipo record 02
		$this->padNumber( 2 , 2 );

		//S(5) 3 - 7 numero progressivo, unisce ' testata ' a ' righe '
		$this->padNumber( $ricorsivo_movimento , 5 );

		//A(15) 8 - 22 codice articolo (numerazione del fornitore)
		$this->padString( $row->code , 15 );

		//A(30) 23 - 52 descrizione merce stampata su fattura ALLINEATO A SINISTRA
		$this->padDescrizioneMerce( $row->description , 30 );

		//A(2) 53 - 54 unità di misura
		$this->padString( $row->um , 2 );

		//S(7) 55 - 61 quantità da fatturare senza la virgola (5 + 2) POSITIVA
		$this->padFloat( $row->qty , 5 , 2 );

		//S(9) 62 - 70 prezzo unitario senza la virgola (6 + 3)
		//6 INTERI E 3 DECIMALI
		$this->padFloat( $row->price_single , 6 , 3 );

		//S(9) 71 - 79 importo totale riga senza la virgola (6 + 3)
		//6 INTERI E 3 DECIMALI
		$this->padFloat( $row->price_total , 6 , 3 );

		//A(4) 80 - 83 numero pezzi della riga, se articolo a pezzi
		$this->padString( '0000' , 4 );

		//A(1) 84 - 84 tipo IVA: blank = soggetto ad aliquota
		//1 = esente (tutti i codici IVA con aliquota o)
		//2 = escluso
		$this->padString( '' , 1 );

		//A(2) 85 - 86 aliquota IVA
		$this->padString( $row->vat_perc , 2 );

		//A(1) 87 - 87 tipo movimento: blank = merce
		//1 = consegna cauzione
		//2 = reso cauzione
		$this->padString( '' , 1 );

		//A(1) 88 - 88 tipo cessione
		//1 = vendita merce (2 = vend. attrezzatura, 3 = servizi)
		//6 = omaggio (7 = sconto merce con IVA 8 = sconto no IVA )
		//9 = cauzione
		$this->padString( '1' , 1 );

		//A(17) 89 - 105 blank
		$this->padString( '' , 17 );

		/**
		*
		* I SEGUENTI SOLO PER NDC
		*
		*/
		if( $this->invoice->isNdc() ){

			//A(1) 106 - 106 tipo reso
			//1 = reso merce (usato sempre e solo 1 per righe negative)
			//Si può scegliere che il campo tipo-reso sia sempre BLANK, e le righe negative
			//NON vengano trasferite
			$this->padString( '' , 1 );

			//A(16) 107 - 122 blank
			$this->padString( '' , 16 );

			//A(6) 123 - 128 blank
			$this->padString( '' , 6 );

		}

		$this->addNL();

	}


	private function formatDate( $ts ){
		return date( 'ymd' , $ts );
	}

	private function padString( $string , $length){

		//always truncate string to be sure it's not too long to fit in field
		$string = $this->truncate( $string , $length );

		$this->txt .= str_pad( $string , $length , " " , STR_PAD_LEFT );
	}

	private function padDescrizioneMerce( $string , $length){

		//always truncate string to be sure it's not too long to fit in field
		$string = $this->truncate( $string , $length );

		$this->txt .= str_pad( $string , $length , " " , STR_PAD_RIGHT );
	}

	private function padNumber( $number , $length){

		$this->txt .= str_pad( $number , $length , "0" , STR_PAD_LEFT );
	}

	private function padFloat( $number , $length_int , $length_decimals ){

		$total_length = $length_int + $length_decimals;

		// format number with '$length_decimals' decimals AND no doctation
		$number = (string) number_format( $number , $length_decimals , '' , '' );

		$this->txt .= str_pad( $number , $total_length , "0" , STR_PAD_LEFT );

	}

	private function addNL(){
		$this->txt .= "\r\n";
	}

	private function truncate( $string , $length ) {
		return ( strlen($string) > $length ) ? substr( $string, 0, $length  ) : $string ;
	}


	/**
	* Prints TXT for download
	*
	*/
	public function getThatDamnTXT(){

		$filename = 'Fattura_' . $this->invoice->number . '-conadfile.txt';

		header( "Content-type: text/plain" );
		header( "Content-Disposition: attachment; filename=" . $filename );

		echo $this->txt;

	}


}



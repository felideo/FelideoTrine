<?php
namespace  Felideo\FelideoTrine;

class Fail extends \Exception{
	private $backtrace;
	private $error;

    public function __construct($message, $code = 0, Exception $previous = null){
        parent::__construct($message, $code, $previous);

        $this->backtrace = $this->getTrace();

    	$this->error = [
            'exception_msg' => $this->getMessage(),
            'code'          => $this->getCode(),
            'localizador'   => "Class => " . $this->backtrace[0]['class']  . " - Function => " . $this->backtrace[0]['function'] . "() - Line => " . $this->getLine(),
            'file'          => $this->getFile(),
            'class'         => $this->backtrace[0]['class'],
            'function'      => $this->backtrace[0]['function'],
            'line'          => $this->getLine(),
            'backtrace'     => $this->getTraceAsString(),
        ];

        // $this->show_error();
    }

    public function get_error(){
        return $this->error;
    }

    public function show_error($exit = false){
	    echo "\n<pre style='width: 100%; z-index: 9999; position: relative;'>";
	    echo "=============================== EXCEPTION =============================\n\n";
        echo utf8_encode(print_r($this->error, true));

	    echo "\n";
	    echo "</pre>";

	    exit;

        if(!empty($exit)){
            exit;
        }
    }
}
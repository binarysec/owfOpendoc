<?php

class opendoc_system_report {

	private $a_opendoc;
	
	
	public function __construct($ini) {
		$this->wf = new web_framework($ini);

		
		$this->a_opendoc = $this->wf->opendoc();
		
		$idoc = $this->a_opendoc->instance("opendoc/system_report.odt");
		
		$tpl = $idoc->set_template("content.xml");
		
		if(function_exists("apache_get_version"))
			$server = apache_get_version();
		else
			$server = $_SERVER["SERVER_SOFTWARE"];
			
		$in = array(
			"version" => WF_VERSION,
			"os" => php_uname("s")." (".php_uname("r").")",
			"machine" => php_uname("m"),
			"php" => phpversion(),
			"zend" => zend_version(),
			"db" => $this->wf->db->get_driver_banner(),
			"cache" => $this->wf->core_cacher()->get_banner(),
			"server" => $server,
			"modules" => &$this->wf->modules,
		);

		$tpl->set_vars($in);


		$idoc->save(dirname(__file__)."/hehe.odt");
	
	}

}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * 
 * Launch the engine
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
try {
	$wf = new opendoc_system_report($ini);
}
catch (wf_exception $e) {
	echo "/!\\ Exception:\n";
	if(is_array($e->messages)) {
		$i = 0;
		foreach($e->messages as $v) {
			echo "* ($i) ".$v."\n";
			$i++;
		}
	}
	else {
		echo $e->messages."\n";
	}
}









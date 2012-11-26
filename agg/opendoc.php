<?php

class opendoc extends wf_agg {
	
	private $instances = array();
	private $core_pref;

	public $ziper;
	public $unziper;
	public $opendocument;
	public $python;
	public $od_host;
	public $od_port;
	
	public function loader($wf) {
		$this->wf = $wf;
		
		$this->core_pref = $this->wf->core_pref()->register_group(
			"opendoc", 
			"OpenDocument preferences"
		);
	
		$this->ziper = $this->core_pref->register(
			"zip_bin",
			"ZIP Binary",
			CORE_PREF_VARCHAR,
			"/usr/bin/zip"
		);
		
		$this->unziper = $this->core_pref->register(
			"unzip_bin",
			"UNZIP Binary",
			CORE_PREF_VARCHAR,
			"/usr/bin/unzip"
		);
		
		$this->opendocument = $this->core_pref->register(
			"opendocument_bin",
			"OpenDocument service drawer",
			CORE_PREF_VARCHAR,
			"/usr/bin/libreoffice"
		);
		
		$this->python = $this->core_pref->register(
			"python_bin",
			"Python language binary",
			CORE_PREF_VARCHAR,
			"/usr/bin/python"
		);
		
		$this->od_host = $this->core_pref->register(
			"od_host",
			"OpenDocument export service host (listening IP)",
			CORE_PREF_VARCHAR,
			"localhost"
		);
		
		$this->od_port = $this->core_pref->register(
			"od_port",
			"OpenDocument export service port (listening IP port)",
			CORE_PREF_VARCHAR,
			"8100"
		);
		
	}
	
	public function instance($docname, $force_new = false) {

		/* must be in var/od */
		$name = "var/$docname";
		
		/* get real filename */
		$file = $this->wf->locate_file($name);
		if(!$file) 
			return(NULL);
		
		/* get object from cache */
		if(!$force_new && array_key_exists($docname, $this->instances))
			return($this->instances[$docname]);
		
		/* create the object */
		$o = new opendoc_file($this->wf, $docname, $file);
		
		/* set object in cache */
		$this->instances[$docname] = $o;
		
		return($o);
	}
	
	
	
}

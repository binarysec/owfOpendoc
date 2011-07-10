<?php

class opendoc_file {
	private $wf;
	private $od_name;
	private $od_file;
	private $template = array();
	private $random;
	private $opendoc;
	
	public function __construct($wf, $odn, $odfile) {
		$this->wf = $wf;
		$this->od_name = $odn;
		$this->od_file = $odfile;
		$this->random = rand();
		$this->opendoc = $this->wf->opendoc();
	}
	
	public function __destruct() {
		$this->wf->remove_dir("/tmp/".$this->random);
	}
	
	
	public function set_template($infile, $entities=true) {
		if(array_key_exists($infile, $this->template))
			return($this->template[$infile][0]);
		
		$o = new core_tpl($this->wf);
		
		$o->src_dir = "/tmp/".$this->random;
		$o->src_ext = '';
		
		$this->template[$infile] = array(
			$o,
			$entities
		);
		
		return($this->template[$infile][0]);
	}
	
	public function copy_file($from, $to) {
	
	}
	
	public function save($to) {

		/* create template context */
		$ctx = "/tmp/".$this->random;
		var_dump($ctx);
		
		if(!is_dir($ctx)) {
			/* must create the directory */
			$this->wf->create_dir("$ctx/null.null");
		}
		else {
			$this->wf->remove_dir($ctx);
			mkdir($ctx);
		}
		$od = "od/".$this->od_name;
		$this->wf->create_dir("$ctx/$od/null.null");
		
		/* extract the source */
		system("cd $ctx/$od; unzip ".$this->od_file);
		
		/* apply template */
		foreach($this->template as $infile => $dao) {
			$tfile = "$ctx/$od/$infile";
			
			/* file need to be html entities */
			if($dao[1] == true) {
				file_put_contents(
					$tfile, 
					html_entity_decode(file_get_contents($tfile))
				);
			}

			/* patch the file */
			file_put_contents(
				$tfile, 
				html_entity_decode($dao[0]->fetch("$od/$infile"))
			);
		}
		
		system("cd $ctx/$od; zip -r $to *");

	}
	
	public function export() {
	
	}
		

 
	
}


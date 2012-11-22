<?php

define("OD_FILE_PDF",  0);
define("OD_FILE_HTML", 1);

class opendoc_file {
	var $quiet = false;
	
	private $wf;
	private $od_name;
	private $od_file;
	private $template = array();
	private $random;
	private $opendoc;
	private $file_type;
	private $save_to = false;
	private $files = array();
	
	public function __construct($wf, $odn, $odfile) {
		$this->wf = $wf;
		$this->od_name = $odn;
		$this->od_file = $odfile;
		$this->random = rand();
		$this->opendoc = $this->wf->opendoc();
		$this->opendoc_client = $this->wf->opendoc_client();
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
		$this->files[] = array($from, $to);
	}
	
	public function save($to) {
		/* create template context */
		$ctx = "/tmp/".$this->random;
		
		$quiet = ($this->quiet ? "-q" : "");

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
		@system("cd $ctx/$od; unzip $quiet ".$this->od_file);
		
		/* add new files */
		foreach($this->files as $k => $v) {
			$dest = "$ctx/$od/$v[1]";
			@unlink($dest);
			$this->wf->create_dir($dest);
			@copy($v[0], $dest);
		}
		
		/* apply template */
		foreach($this->template as $infile => $dao) {
			$tfile = "$ctx/$od/$infile";
			
			/* file need to be html entities */
			if($dao[1] == true) {
				/* Encode tpl vars */
				foreach($dao[0]->get_vars() as $key => $var)
					if(is_string($var))
						$dao[0]->set($key, html_entity_decode(mb_detect_encoding($var, 'UTF-8', true) != 'UTF-8' ? utf8_encode($var) : $var, ENT_COMPAT, 'UTF-8'));
				
				/* Encode file */
				$contents = file_get_contents($tfile);
				$enc = mb_detect_encoding($contents, 'UTF-8', true);
				file_put_contents(
					$tfile,
					html_entity_decode($enc != 'UTF-8' ? utf8_encode($contents) : $contents, ENT_COMPAT, 'UTF-8')
				);
			}
			
			// sometimes throw a silent exception > it comes from the fetch on the dao
			/* patch the file */
			file_put_contents(
				$tfile,
				html_entity_decode($dao[0]->fetch("$od/$infile"), ENT_COMPAT, 'UTF-8')
			);
		}
		
		if(file_exists($to))
			unlink($to);
		
		system("cd $ctx/$od; zip -r $quiet $to *");
		
		$this->save_to = $to;

	}
	
	public function export($to, $type=OD_FILE_PDF) {
		if(!$this->save_to) {
			echo "you must save the document before\n";
			return(false);
		}
		
		$this->opendoc_client->connect();
		$this->file_type = $type;
		
		
		$this->opendoc_client->export(
			$this->save_to,
			$to,
			$type
		);

		return(true);
	}
}

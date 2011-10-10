<?php

define("OD_FILE_PDF",  0);
define("OD_FILE_HTML", 1);

class opendoc_file {
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
		@system("cd $ctx/$od; unzip ".$this->od_file);
		
		/* add new files */
		foreach($this->files as $k => $v) {
			$to = "$ctx/$od/$v[1]";
			@unlink($to);
			$this->wf->create_dir($to);
			@copy($v[0], $to);
		}
		
		/* apply template */
		foreach($this->template as $infile => $dao) {
			$tfile = "$ctx/$od/$infile";
			
			/* file need to be html entities */
			if($dao[1] == true) {
				$file_contents = file_get_contents($tfile);
				$fenc = mb_detect_encoding($file_contents, 'UTF-8', true);
				$file_contents = html_entity_decode($fenc != 'UTF-8' ? utf8_encode($file_contents) : $file_contents);
				file_put_contents($tfile, $file_contents);
			}
			$contents = $dao[0]->fetch("$od/$infile");
			$enc = mb_detect_encoding($contents, 'UTF-8', true);
			
			if($enc != 'UTF-8' && $dao[1] == true && $fenc == 'UTF-8') {
				file_put_contents($tfile, utf8_decode($file_contents));
				$contents = $dao[0]->fetch("$od/$infile");
				$enc = mb_detect_encoding($contents, 'UTF-8', true);
			}
			
			/* patch the file */
			file_put_contents(
				$tfile, 
				html_entity_decode($enc != 'UTF-8' ? utf8_encode($contents) : $contents)
			);
		}
		
		unlink($to);
		system("cd $ctx/$od; zip -r $to *");
		
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


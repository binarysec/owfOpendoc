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
	private $metadata = array();
	
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
		
		if(!isset($this->template["META-INF/manifest.xml"]) && !empty($this->metadata))
			$this->set_template("META-INF/manifest.xml");
		
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
			/* fetch template */
			$content = $dao[0]->fetch("$od/$infile");
			
			/* remove "opendoc" scripts tags */
			$content = preg_replace("#<text:script script:language=\"opendoc\">(.*?)</text:script>#", '$1', $content);
			
			/* if this is the manifest, add meta data */
			if($infile == "META-INF/manifest.xml")
				$content = $this->__add_meta($content);
			
			/* patch the file */
			file_put_contents(
				$tfile,
				html_entity_decode($content, ENT_COMPAT, 'UTF-8')
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
	
	public function add_meta($data) {
		if(!isset($data["path"], $data["mime"]))
			return false;
		$this->metadata[] = $data;
		return true;
	}
	
	private function __add_meta($content) {
		$moar_meta_data = "";
		foreach($this->metadata as $datum)
			$moar_meta_data .= " <manifest:file-entry manifest:full-path=\"$datum[path]\" manifest:media-type=\"$datum[mime]\"/>\n";
		return preg_replace("#</manifest:manifest>#", "$moar_meta_data</manifest:manifest>", $content);
	}
	
	/* add_img : to simply add an image to the document (size are given in inches) */
	public function add_img($filepath, $name, $varname, $width, $height, $template = "content.xml", $mime = "image/png") {
		
		/* sanatize */
		if(!file_exists($filepath))
			return false;
		
		/* add entry to manifest */
		$this->add_meta(array(
			"path" => "Pictures/$name.png",
			"mime" => $mime
		));
		
		/* add file */
		$this->copy_file($filepath, "Pictures/$name.png");
		
		/* content */
		$content =
			'<draw:frame draw:style-name="fr1" draw:name="'.$name.'" text:anchor-type="paragraph" svg:x="0.0535in" svg:y="0.1181in" svg:width="'.$width.'in" svg:height="'.$height.'in" draw:z-index="11">'.
				'<draw:image xlink:href="Pictures/'.$name.'.png" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/>'.
			'</draw:frame>'
		;
		
		/* add variable */
		$tpl = $this->set_template($template);
		$tpl->merge_vars(array($varname => $content));
		
		return $content;
	}
}

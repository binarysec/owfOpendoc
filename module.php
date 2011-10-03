<?php
 
class wfm_opendoc extends wf_module {
	public function __construct($wf) {
		$this->wf = $wf;
	}
	
	public function get_name() { return("opendoc"); }
	public function get_description()  { return("OWF Native OpenDocument manager"); }
	public function get_banner()  { return("opendoc/1.0.1"); }
	public function get_version() { return("1.0.1"); }
	public function get_authors() { return("Michael VERGOZ"); }
	public function get_depends() { return(NULL); }
	
	public function get_actions() {
		return(array(
		));
	}
	
}


<?php

class opendoc_service {
	private $a_opendoc;
	private $a_opendoc_server;
	
	public function __construct($ini) {
		$this->wf = new web_framework($ini);
		
		/* */
		$this->a_opendoc = $this->wf->opendoc();
		$this->a_opendoc_server = $this->wf->opendoc_server();
		
		/* build the OD cmd */
		$cmd = $this->a_opendoc->opendocument.' '.
			'"-accept=socket,host='.$this->a_opendoc->od_host.
			',port='.$this->a_opendoc->od_port.
			';urp;StarOffice.ServiceManager"'.
			' -norestore -nofirststartwizard -nologo -headless';
		
		/* find converter */
		$converter = $this->wf->locate_file("bin/opendoc_convert.py");
		
		
		$pid = 0;
		
		/* fork the server */
		$pid = pcntl_fork();
		if($pid == -1)
			die('fork() impossible');
		else if($pid) {
			$status = null;
			
			/* god father */
			echo posix_getppid()." speaking, father wait $pid\n";
			
			/* bind the server */
			$r = $this->a_opendoc_server->listen();
			if(!$r)
				break;
	
			while(1) {
				/* rotate network */
				$this->a_opendoc_server->rotation();
				 
				/* check sub process statement */
				$ret =  pcntl_waitpid($pid, $status, WNOHANG);
				if($ret == -1)
					break;
			}
		} 
		else {
			echo posix_getppid()." speaking, $cmd\n";
			$pid = posix_getppid();
			exec($cmd);
// 			while(1) sleep(1);
		}

	}


}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * 
 * Launch the engine
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
try {
	$wf = new opendoc_service($ini);
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









<?php

class opendoc_client extends wf_agg {
	private $client_addr;
	private $client_port;
	private $client_fd = null;
	private $client_try;
	private $client_timeout;
	private $client_connected = false;
	
	public function loader($wf) {
		$this->wf = $wf;
		
		$this->core_pref = $this->wf->core_pref()->register_group("opendoc");
		
		$this->client_addr = $this->core_pref->register(
			"od_client_addr",
			"OD Client address",
			CORE_PREF_VARCHAR,
			"127.0.0.1"
		);
		
		$this->client_port = $this->core_pref->register(
			"od_client_port",
			"OD Client port",
			CORE_PREF_NUM,
			9100
		);
		
		$this->client_try = $this->core_pref->register(
			"od_client_try",
			"OD Client if connection missed how many try before failing",
			CORE_PREF_NUM,
			3
		);
		
		$this->client_timeout = $this->core_pref->register(
			"od_client_timeout",
			"OD Client connection timeout",
			CORE_PREF_NUM,
			3
		);
		$this->client_connected = false;
	}
	
	public function __destruct() {
		if($this->client_fd)
			fclose($this->client_fd);
	}
	
	public function connect() {
		/* already connected */
		if($this->client_connected)
			return(true);
		
		/* try to connect */
		for($a=0;$a<$this->client_try; $a++) {
			/* try to make a socket */
			$fp = @stream_socket_client(
				"tcp://".$this->client_addr.":".$this->client_port, 
				$errno, $errstr, 
				$this->client_timeout
			);
			if(!$fp) {
				echo "Can not connect to OD ".
					$this->client_addr.":".
					$this->client_port.
					" : $errstr ($errno)<br />\n";
				sleep(1);
			} 
			else {
				$this->client_connected = true;
				$this->client_fd = $fp;
				break;
			}
		}
		
		return($this->client_connected);
	}
	
	
	public function export($from, $to, $type=OD_FILE_PDF) {
		if(!$this->client_connected)
			return(false);
			
		if(!file_exists($from))
			return(false);
			
		if($type == OD_FILE_PDF)
			$fexport = "pdf";
		else if($type == OD_FILE_HTML)
			$fexport = "html";
		else
			return(false);
		
		/* try export */
		$cmd = "export:$fexport:".filesize($from)."\n";
		$this->wf->safe_write($this->client_fd, $cmd);
		
		/* wait transfert */
		$tr = $this->read();
		if(!$tr)
			return(false);
			
		if(
			$tr[0] == 'transfert' && 
			$tr[1] == 'accepted' && 
			$tr[2] == filesize($from)) {
			
			/* transfert data */
			$sz = filesize($from);
			$f = fopen($from, "r");
			while(!feof($f)) {
				$r = fread($f, 1024);
				$this->wf->safe_write($this->client_fd, $r);
			}
			fclose($f);
			fflush($this->client_fd);
		}
		else {
			echo "Client OD transfert failed\n";
			$this->disconnect();
			return(false);
		}
		
		/* wait queue message */
		$tr = $this->read();
		if(!$tr)
			return(false);
		if(
			$tr[0] != 'transfert' ||
			$tr[1] != 'inqueue') {
			echo "Client OD can not add queue\n";
			$this->disconnect();
			return(false);
		}
		
// 		echo "Task in queue please wait\n";
		
		/* wait for return message */
		$tr = $this->read();
		if(!$tr)
			return(false);
		if(
			$tr[0] != 'transfert' ||
			$tr[1] != 'finish' ||
			$tr[2] <= 0) {
			echo "Client OD can not transfert rendered data\n";
			$this->disconnect();
			return(false);
		}
		$to_size = $tr[2];

		$cmd = "export:ok\n";
		$this->wf->safe_write($this->client_fd, $cmd);
		
		/* open for writing exported file */
		$efd = fopen($to, "w+");
		if(!$efd) {
			echo "Can not open $to\n";
			$this->disconnect();
			return(false);
		}
		
		/* transfert file */
		$readed = 0;
		$fds = array($this->client_fd);
		$n = array();
		socket_set_blocking($this->client_fd, 0);
		while(1) {
			if(false === ($num = stream_select($fds, $n, $n, 1))) {
				echo "Massive select() problemos\n";
				$this->disconnect();
				return(false);
			} 
			elseif($num > 0) {
				$data = fread($this->client_fd, 1024);
				if(strlen($data) == 0) {
					$this->disconnect();
					return(false);
				}
				$readed += strlen($data);
				$this->wf->safe_write($efd, $data);
				if($readed == $to_size) {
// 					echo "transfert finished\n";
					break;
				}
			}
			$fds = array($this->client_fd);
		}
		socket_set_blocking($this->client_fd, 1);

	}
	
	private function read() {
		$read = fread($this->client_fd, 1024);
		
		if(strlen($read) == 0) {
			$this->disconnect();
			return(false);
		}

		$scmd = explode(":", chop($read));
		return($scmd);
	}
	
	private function disconnect() {
		$this->client_connected = false;
		if($this->client_fd)
			fclose($this->client_fd);
		$this->client_fd = false;
	}

	

	
	
}

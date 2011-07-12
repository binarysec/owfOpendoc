<?php

define("OD_SERVER_INT",  0);
define("OD_SERVER_UNIT", 1);

class opendoc_server extends wf_agg {
	
	private $core_pref;
	private $server_addr;
	private $server_port;
	
	private $socket = null;
	private $fds = array();
	private $select_fds = array();
	
	public function __destruct() {
		foreach($this->fds as $k => $v)
			fclose($k);
	}
	
	public function loader($wf) {
		$this->wf = $wf;
		
		$this->core_pref = $this->wf->core_pref()->register_group("opendoc");
		
		$this->server_addr = $this->core_pref->register(
			"od_server_addr",
			"OD Server address binding",
			CORE_PREF_VARCHAR,
			"0.0.0.0"
		);
		
		$this->server_port = $this->core_pref->register(
			"od_server_port",
			"OD Server address binding",
			CORE_PREF_NUM,
			9100
		);
	}
	
	public function listen() {
		/* create the socket */
		$fd = stream_socket_server(
			"tcp://".$this->server_addr.":".$this->server_port, 
			$errno, 
			$errstr
		);
		if (!$fd) {
			echo "Can't bind opendoc $errstr ($errno)";
			return(false);
		}
		
		/* add socket to manager */
		$this->select_fds[] = $fd;
		$this->fds[$fd] = array($fd, OD_SERVER_INT);
		$this->socket = &$this->fds[$fd];
		return(true);
	}
	
	public function rotation() {
		/* wait for new connection */
		$n = array();
		
		if(false === ($num = stream_select($this->select_fds, $n, $n, 1))) {
			echo "Massive problem\n";
			return(false);
		} 
		elseif ($num > 0) {
			foreach($this->select_fds as $v) {
				$flux = &$this->fds[$v];
				
				/* check server events */
				if($flux[1] == OD_SERVER_INT) {
					$fd = stream_socket_accept($flux[0]);
					if($fd) {
						$this->fds[$fd] = array($fd, OD_SERVER_UNIT);
						echo "New client connection";
					}
				}
				else if($flux[1] == OD_SERVER_UNIT) {
				
					$read = fread($flux[0], 1024);
					if(strlen($read) == 0) {
						/* disconnection */
						unset($this->fds[$flux[0]]);
						echo "Client disconnection\n";
					}
					else
						var_dump($read);
					
				}
			}
		}
		
		unset($this->select_fds);
		$this->select_fds = array();
		
		/* add select fd */
		foreach($this->fds as $k => $v)
			$this->select_fds[] = $v[0];
			
		return(true);
	}
	
	
}

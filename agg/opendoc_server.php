<?php

define("OD_SERVER_INT",  0);
define("OD_SERVER_UNIT", 1);

define("OD_SERVER_ST_AUTH",       0);
define("OD_SERVER_ST_WAITEXPORT", 1);
define("OD_SERVER_ST_EXPORT",     2);
define("OD_SERVER_ST_QUEUE",      3);
define("OD_SERVER_ST_RENDER",     4);

class opendoc_server_user {
	private $wf;
	public $sock;
	public $state = OD_SERVER_ST_WAITEXPORT;
	public $format;
	public $opendoc_server;
	
	public $od_file = null;
	public $export_file = null;
	public $od_file_fd = null;
	public $od_size;
	public $od_readed = 0;
	
	public function __construct($wf, $server, $sock) {
		$this->wf = $wf;
		$this->opendoc_server = $server;
		$this->sock = $sock;
		
		
		$this->display("client connection");
	}
	
	public function __destruct() {
		$this->display("client disconnection");
		
		if($this->od_file_fd)
			fclose($this->od_file_fd);
		
		if(file_exists($this->od_file))
			unlink($this->od_file);
		
		if(file_exists($this->export_file))
			unlink($this->export_file);
		
	}

	public function read($data) {
	
		/* manage exportation */
		if($this->state == OD_SERVER_ST_WAITEXPORT) {
			$t = explode(":", $data);
			if($t[0] == "export") {
				$this->od_format = $t[1];
				$this->od_size = $t[2];

				/* create TMP name */
				$this->od_file = "/tmp/".rand().".odt";
				$this->export_file = "/tmp/".rand().".".$this->od_format;
				
				/* open for writing od file */
				$this->od_file_fd = fopen($this->od_file, "w+");
				if(!$this->od_file_fd)
					return(false);
					
				$this->display("Creating TMP file ".$this->od_file); 
				
				$cmd = "transfert:accepted:".$this->od_size."\n";
				$this->wf->safe_sockwrite($this->sock, $cmd);
				$this->state = OD_SERVER_ST_EXPORT;
				$this->od_readed = 0;
			}
		}
		
		/* transfert file */
		else if($this->state == OD_SERVER_ST_EXPORT) {
			$this->od_readed += strlen($data);
			fwrite($this->od_file_fd, $data);
			
			if($this->od_readed == $this->od_size) {
				/* transfert inqueue */
				$cmd = "transfert:inqueue\n";
				$this->wf->safe_sockwrite($this->sock, $cmd);
				
				$this->display("transfert finished, inqueue");
				$this->state = OD_SERVER_ST_QUEUE;
				
				if(!$this->convert())
					return false;
			}
		}
		
		/* manage exportation */
		else if($this->state == OD_SERVER_ST_RENDER) {
			socket_set_block($this->sock);
			$a = 0;
			
			/* transfert data */
			$sz = filesize($this->export_file);
			$f = fopen($this->export_file, "r");
			while(!feof($f)) {
				$r = fread($f, 1024);
				$this->wf->safe_sockwrite($this->sock, $r);
				$a += strlen($r);
			}
			fclose($f);
			
			socket_set_nonblock($this->sock);
			
			$this->state = OD_SERVER_ST_WAITEXPORT;
		}
		
		return(true);
	}
	
	private function convert() {
		$cmd = $this->opendoc_server->convert." ".$this->od_file." ".$this->export_file;
		system($cmd);
		
		if(file_exists($this->export_file)) {
			/* got response transfert file */
			$cmd = "transfert:finish:".filesize($this->export_file)."\n";
			$this->wf->safe_sockwrite($this->sock, $cmd);
		
			$this->state = OD_SERVER_ST_RENDER;
		}
		else {
			$this->display("PDF convert failed: file ".$this->export_file." missing (maybe odt syntax error)");
			return false;
		}
		
		return true;
	}
	
	private function display($msg) {
		$addr = null;
		$port = 0;
		socket_getsockname($this->sock, $addr, $port);
		echo "$addr:$port/$msg\n";
	}
	
}

class opendoc_server extends wf_agg {
	
	private $core_pref;
	private $server_addr;
	private $server_port;
	private $opendoc;
	
	private $socket = null;
	private $fds = array();
	private $select_fds = array();
	
	public $convert;

	public function loader($wf) {
		$this->wf = $wf;
		$this->opendoc = $this->wf->opendoc();
		
		$this->core_pref = $this->wf->core_pref()->register_group("opendoc");
		
		$this->server_addr = $this->core_pref->register(
			"od_server_addr",
			"OD Server address binding",
			CORE_PREF_VARCHAR,
			"0.0.0.0"
		);
		
		$this->server_port = $this->core_pref->register(
			"od_server_port",
			"OD Server port binding",
			CORE_PREF_NUM,
			9100
		);
		
		$this->convert = 
			$this->opendoc->python." ".
			$this->wf->locate_file("bin/opendoc_convert.py");
	}
	
	public function __destruct() {
		foreach($this->fds as $k => $v)
			socket_close($v[0]);
	}
	
	public function listen() {
		/* create sock */
		$fd = @socket_create(
			AF_INET,
			SOCK_STREAM,
			SOL_TCP
		);
		if(!$fd) {
			echo "Can't create OpenDoc server socket $errstr ($errno)";
			return(false);
		}
		
		if(!@socket_set_option($fd, SOL_SOCKET, SO_REUSEADDR, 1)) {
			echo "Can't REUSEADDR OpenDoc server socket $errstr ($errno)";
			socket_close($fd);
			return(false);
		}

		/* bind */
		$ret = @socket_bind(
			$fd,
			gethostbyname($this->server_addr),
			$this->server_port
		);
		if (!$ret) {
			echo "Can't bind OpenDoc server socket $errstr ($errno)";
			socket_close($fd);
			return(false);
		}
		
		/* listen */
		$ret = @socket_listen($fd);
		if (!$ret) {
			echo "Can't listen OpenDoc server socket $errstr ($errno)";
			socket_close($fd);
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
		
		if(false === ($num = @socket_select($this->select_fds, $n, $n, 1))) {
			echo "Massive problemos\n";
			return(false);
		} 
		elseif ($num > 0) {
			foreach($this->select_fds as $v) {
				$flux = &$this->fds[$v];
				
				/* check server events */
				if($flux[1] == OD_SERVER_INT) {
					$fd = socket_accept($flux[0]);
					if($fd) {
						socket_set_nonblock($fd);
						$this->fds[$fd] = array(
							$fd, 
							OD_SERVER_UNIT,
							new opendoc_server_user($this->wf, $this, $fd)
						);
					}
				}
				else if($flux[1] == OD_SERVER_UNIT) {
					$read = socket_read($flux[0], 1024);
					if(strlen($read) == 0) {
						/* disconnection */
						unset($this->fds[$flux[0]]);
					}
					else {
						$r = $flux[2]->read($read);
						if($r == false)
							unset($this->fds[$flux[0]]);
						
					}
					
				}
			}
		}
		
		/* recreate select tab */
		unset($this->select_fds);
		$this->select_fds = array();
		
		/* add select fd */
		foreach($this->fds as $k => $v) {
			/* manage tasks */
			$this->check_export($v);
			
			/* add fd */
			$this->select_fds[] = $v[0];
		}
		
		return(true);
	}
	
	
	public function check_export($v) {
		if($v[1] == OD_SERVER_UNIT) {
			$o = &$v[2];
			if($o->state == OD_SERVER_ST_QUEUE) {
// 				echo "task here !!\n";
			}
		}
	}
	
	
}

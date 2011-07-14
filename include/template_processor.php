<?php

	class Template {
		var $r;
		var $search;
		var $replace;
		var $stack = array();
		var $nodes = array();
		var $done = false;
		
		private function _preg_error(){
			switch(preg_last_error()){
			    case PREG_INTERNAL_ERROR: die('PREG_INTERNAL_ERROR'); break;
			    case PREG_BACKTRACK_LIMIT_ERROR: die('PREG_BACKTRACK_LIMIT_ERROR'); break;
			    case PREG_RECURSION_LIMIT_ERROR: die('PREG_RECURSION_LIMIT_ERROR'); break;
			    case PREG_BAD_UTF8_ERROR: die('PREG_BAD_UTF8_ERROR'); break;
			    case PREG_BAD_UTF8_OFFSET_ERROR: die('PREG_BAD_UTF8_OFFSET_ERROR'); break;
			    default:
					//die("Unknown preg error.");
					return;
			}
		}
		
		private function _build_replace($r, $prefix){
	
			if(is_array($r) && count($r)) {
				foreach ($r as $k => $v ) {
					if (is_array($v))
						$this->_build_replace($v, "$prefix$k.");
					
					$this->search[] =  $prefix . $k;
					$this->replace[] = $v;
				}
			}
		} 
		
		private function _push_stack(){
			$this->stack[] = array($this->r, $this->search, $this->replace, $this->nodes);
		}
		private function _pop_stack(){
			list($this->r, $this->search, $this->replace, $this->nodes) = array_pop($this->stack);
		}
		
		private function _get_var($name){
			$keys = array_map('trim',explode(".",$name));			
			$val = $this->r;
			foreach($keys as $k) {
				$val = $val[$k];
			}
			return $val;
		}
		
		/**
		 * IF node
		 * 
		 * {{ if <$var> }}...[{{ else }} ...] {{ endif }}
		 * {{ if <$var>==<val|$var> }}...[{{ else }} ...]{{ endif }}
		 * {{ if <$var>!=<val|$var> }}...[{{ else }} ...]{{ endif }}
		 */
		private function _replcb_if($args){
			
			if (strpos($args[2],"==")>0){
				list($a,$b) = array_map("trim",explode("==",$args[2]));
				$a = $this->_get_var($a);
				if ($b[0]=="$") $b =  $this->_get_var($b);
				$val = ($a == $b);
			} else if (strpos($args[2],"!=")>0){
				list($a,$b) = explode("!=",$args[2]);
				$a = $this->_get_var($a);
				if ($b[0]=="$") $b =  $this->_get_var($b);
				$val = ($a != $b);
			} else {
				$val = $this->_get_var($args[2]);
			}
			list($strue, $sfalse)= preg_split("|{{ *else *}}|", $args[3]);
			
			return ($val?$strue:$sfalse);
		}
		
		/**
		 * FOR node
		 * 
		 * {{ for <$var> as $name }}...{{ endfor }}
		 * {{ for <$var> as $key=>$name }}...{{ endfor }}
		 */
		private function _replcb_for($args){
			$m = array_map('trim', explode(" as ", $args[2]));
			list($keyname, $varname) = explode("=>",$m[1]);
			if (is_null($varname)) { $varname=$keyname; $keyname=""; }
			if ($m[0]=="" || $varname=="" || is_null($varname)) die("template error: 'for ".$m[0]." as ".$varname."'") ;
			//$vals = $this->r[$m[0]];
			$vals = $this->_get_var($m[0]);
			$ret="";
			if (!is_array($vals)) return $ret; 
			foreach ($vals as $k=>$v){
				$this->_push_stack();
				$r = $this->r;
				$r[$varname] = $v;
				if ($keyname!='') $r[$keyname] = $k;
				$ret .=  $this->replace($args[3], $r);
				$this->_pop_stack();
			}
			return $ret;
		}

		/**
		 * INC node
		 * 
		 * {{ inc <templatefile> [with $var1=$var2] }}{{ endinc }}
		 */
		private function _replcb_inc($args){
			list($tplfile, $newctx) = array_map('trim', explode("with",$args[2]));
			$this->_push_stack();
			$r = $this->r;
			if (!is_null($newctx)) {
				list($a,$b) = array_map('trim', explode("=",$newctx));
				$r[$a] = $this->_get_var($b); 
			}
			$this->nodes = Array();
			$tpl = get_markup_template($tplfile);
			$ret = $this->replace($tpl, $r);
			$this->_pop_stack();
			return $ret;
			
		}

		private function _replcb_node($m) {
			$node = $this->nodes[$m[1]];
			if (method_exists($this, "_replcb_".$node[1])){
				$s = call_user_func(array($this, "_replcb_".$node[1]),  $node);
			} else {
				$s = "";
			}
			$s = preg_replace_callback('/\|\|([0-9]+)\|\|/', array($this, "_replcb_node"), $s);
			return $s;
		}
						
		private function _replcb($m){
			//var_dump(array_map('htmlspecialchars', $m));
			$this->done = false;	
			$this->nodes[] = (array) $m;
			return "||". (count($this->nodes)-1) ."||";
		}
		
		private function _build_nodes($s){
			$this->done = false;
			while (!$this->done){
				$this->done=true;
				$s = preg_replace_callback('|{{ *([a-z]*) *([^}]*)}}([^{]*({{ *else *}}[^{]*)?){{ *end\1 *}}|', array($this, "_replcb"), $s);
				if ($s==Null) $this->_preg_error();
			}
			//({{ *else *}}[^{]*)?
			krsort($this->nodes);
			return $s;
		}
		
		public function replace($s, $r) {
			$this->r = $r;
			$this->search = array();
			$this->replace = array();
	
			$this->_build_replace($r, "");
			
			#$s = str_replace(array("\n","\r"),array("§n§","§r§"),$s);
			$s = $this->_build_nodes($s);
			$s = preg_replace_callback('/\|\|([0-9]+)\|\|/', array($this, "_replcb_node"), $s);
			if ($s==Null) $this->_preg_error();
			$s = str_replace($this->search,$this->replace, $s);
			
			return $s;
		}
	}
	
	$t = new Template;

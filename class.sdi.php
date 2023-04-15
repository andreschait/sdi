<?php
/******************************************************************************
*                            Smart Database Interface
*******************************************************************************
*      Author:     Andres Chait
*      Email:      contact@andres.co.il
*      Website:    https://www.andres.co.il
*
*      Version:    2.0.0
*      Copyright:  (c) 2020 - Andres Chait
*                  You are free to use, distribute, and modify this software 
*                  under the terms of the GNU General Public License. See the
*                  included LICENCE file.
*    
/******************************************************************************/
class SDI{
	private $db = null;
	private $error = '';

	public function __construct($host,$usr,$pw,$db){
		$this->db = new mysqli($host,$usr,$pw,$db);
	}

	public function setCharset($chrst){
		$this->db->set_charset($chrst);
	}

	public function getError(){
		return $this->error;
	}

	private function escape($str){
		return $this->db->real_escape_string($str);
	}

	public function query($tbl,$prms=[],$queryOne=false){
		$res = [];
		$flds = isset($prms['fields'])?$prms['fields']:'*';
		$whr = '';
		$ord = isset($prms['order'])?('ORDER BY `'.array_keys($prms['order'])[0].'` '.end($prms['order'])):'';
		$lmt = isset($prms['limit'])?'LIMIT '.$prms['limit']:'';
		$join = isset($prms['join'])?(',(SELECT '.$prms['join']['field'].' FROM '.$prms['join']['table'].' WHERE `'.$prms['join']['outkey'].'`=r.'.$prms['join']['inkey'].')'.(isset($prms['join']['name'])?' AS '.$prms['join']['name']:'')):'';

		$whereItems = [];

		if(isset($prms['where'])){
			$prms['where'] = array_values($prms['where']);
			foreach($prms['where'] as $c=>$cond){
				if(isset($cond['field']) && isset($cond['term'])){
					$whereItem = '';
					if(isset($cond['func'])){
						$whereItem.=$cond['func'].'(`'.$cond['field'].'`) ';
					}else{
						$whereItem.='`'.$cond['field'].'` ';
					}

					if(isset($cond['op']) && in_array(strtoupper($cond['op']),['IN','LIKE'])){
						switch(strtoupper($cond['op'])){
							case 'IN':
								if(is_array($cond['term'])){
									$whereItem.="IN ('".join("','",$cond['term'])."')";
								}
							break;
							case 'LIKE':
								$whereItem.="LIKE '".$cond['term']."'";
							break;
						}
					}else{
						$whereItem.=(isset($cond['op'])?$cond['op']:'=')."'".$this->escape($cond['term'])."'";
					}
					$whereItems[] = $whereItem;
				}
			}
		}

		if($whereItems){
			$whr = 'WHERE ';
			if(isset($prms['logic'])){
				for($cc=count($whereItems); $cc>0; $cc--){
					$prms['logic'] = str_replace(' '.$cc.' ',' {{SWITCH-PARAM-'.$cc.'}} ',$prms['logic']);
					$prms['logic'] = str_replace('('.$cc.' ','({{SWITCH-PARAM-'.$cc.'}} ',$prms['logic']);
					$prms['logic'] = str_replace(' '.$cc.')',' {{SWITCH-PARAM-'.$cc.'}})',$prms['logic']);
				}

				for($cc=count($whereItems); $cc>0; $cc--){
					$prms['logic'] = str_replace('{{SWITCH-PARAM-'.$cc.'}}',$whereItems[$cc-1],$prms['logic']);
				}
				$whr.= $prms['logic'];
			}else{
				$whr.= join(' AND ',$whereItems);	
			}
		}

		$query = "SELECT ".$flds.$join." FROM `$tbl` r $whr $ord $lmt";

		echo $query;

		if(isset($prms['count']) && $prms['count']){
			return $this->db->query($query)->num_rows;
		}

		$queryRows = $this->db->query($query);
		while($queryRow = $queryRows->fetch_assoc()){
			$res[] = $queryRow;
		}

		return $res?($queryOne?$res[0]:$res):[];
	}

	public function insert($tbl,$payload){
		foreach($payload as $k=>$v){
			$payload[$k] = is_null($v)?'NULL':"'".$this->escape($v)."'";
		}

		$query = $this->db->query("INSERT INTO `$tbl` (`".join('`,`',array_keys($payload))."`) VALUES (".join(',',$payload).")");
		if(!$query){
			$this->error = $this->db->error;
		}
		return $query?$this->db->insert_id:false;
	}

	public function upsert($tbl,$payload,$key=[]){
		$exists = $this->db->query("SELECT * FROM $tbl WHERE `".array_keys($key)[0]."`='".end($key)."'")->num_rows;

		if($exists){
			return $this->update($tbl,$payload,$key);
		}else{
			return $this->insert($tbl,$payload);
		}
	}

	public function update($tbl,$payload,$key=[]){
		$updateQuery = [];

		foreach($payload as $k=>$v){
			$updateQuery[] = "`$k`=".(is_null($v)?'NULL':"'".$this->escape($v)."'");
		}
		$q = "UPDATE `$tbl` SET ".join(',',$updateQuery).($key?" WHERE `".array_keys($key)[0]."`='".end($key)."'":'');
		$query = $this->db->query($q);

		if(!$query){
			$this->error = $this->db->error;
		}
		return $query;
	}

	public function delete($tbl,$where=[]){
		$whr = '';

		if($where){
			foreach($where as $c=>$cond){
				$whr.=($c>0?'AND `':'WHERE `').$cond['field'].'`'.(isset($cond['op'])?$cond['op']:'=')."'".$this->escape($cond['term'])."' ";
			}
		}
		$query = $this->db->query("DELETE FROM `$tbl` $whr");
		return $query;
	}
}
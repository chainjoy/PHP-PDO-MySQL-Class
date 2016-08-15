<?php

class DB
{
    private static $instance;
	private $pdo;
    private $sQuery;
    private $settings;
    private $bConnected = false;
    private $parameters;
	private $querycount = 0;
    
    protected  function __construct(){
        $this->Connect();
        $this->parameters = array();
    }
    
    private function Connect(){
        $dsn = DB_TYPE.':dbname='.DB_NAME.';host='.DB_HOST;
		$option = array( 
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
						PDO::ATTR_EMULATE_PREPARES => false, 
						PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
				  );
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $option);
            $this->bConnected = true;
        }
        catch (PDOException $e) {
            echo $this->ExceptionLog($e->getMessage());
            die();
        }
    }
	
	public static function getInstance(){
		$class = get_called_class();
        if(!static::$instance)
            static::$instance = new $class();

        return static::$instance;
	}
	
	public function __clone(){
        return false;
    }
	
    public function __wakeup(){
        return false;
    }
    
	public function closeConnection(){
        $this->pdo = null;
    }
    
   private function init($query, $parameters = "")
	{
		if (!$this->bConnected) {
			$this->Connect();
		}
		try {
			$this->parameters = $parameters;
			$this->sQuery     = $this->pdo->prepare($this->buildParams($query, $this->parameters));
			
			if (!empty($this->parameters)) {
				if (array_key_exists(0, $parameters)) {
					$parametersType = true;
					array_unshift($this->parameters, "");
					unset($this->parameters[0]);
				} else {
					$parametersType = false;
				}
				foreach ($this->parameters as $column => $value) {
					$this->sQuery->bindParam($parametersType ? intval($column) : ":" . $column, $this->parameters[$column]); //It would be query after loop end(before 'sQuery->execute()').It is wrong to use $value.
				}
			}
			
			$this->succes = $this->sQuery->execute();
			$this->querycount++;
		}
		catch (PDOException $e) {
			echo $this->ExceptionLog($e->getMessage(), $query);
			die();
		}
		
		$this->parameters = array();
	}
    
	
	private function buildParams($query, $params = null)
	{
		if (!empty($params)) {
			$rawStatement = explode(" ", $query);
			foreach ($rawStatement as $value) {
				if (strtolower($value) == 'in') {
					return str_replace("(?)", "(" . implode(",", array_fill(0, count($params), "?")) . ")", $query);
				}
			}
		}
		return $query;
	}
	
    
    public function query($query, $params = null, $fetchmode = PDO::FETCH_OBJ){
		
        $query = trim(str_replace("\r", " ", $query));
        
        $this->Init($query, $params);
        
        $rawStatement = explode(" ", preg_replace("/\s+|\t+|\n+/", " ", $query));

        $statement = strtolower($rawStatement[0]);
        
        if ($statement === 'select' || $statement === 'show') {
            return $this->sQuery->fetchAll($fetchmode);
        } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
            return $this->sQuery->rowCount();
        } else {
            return NULL;
        }
    }
    
    public function lastInsertId(){
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction(){
        return $this->pdo->beginTransaction();
    }
    
    
    public function executeTransaction(){
        return $this->pdo->commit();
    }
    
    public function rollBack(){
        return $this->pdo->rollBack();
    }
    
    public function column($query, $params = null){
        $this->Init($query, $params);
        $Columns = $this->sQuery->fetchAll(PDO::FETCH_NUM);
        
        $column = null;
        
        foreach ($Columns as $cells) {
            $column[] = $cells[0];
        }
        
        return $column;
        
    }
    
    public function row($query, $params = null, $fetchmode = PDO::FETCH_OBJ){
        $this->Init($query, $params);
        $result = $this->sQuery->fetch($fetchmode);
        $this->sQuery->closeCursor(); 
        return $result;
    }
   
    public function single($query, $params = null){
        $this->Init($query, $params);
        $result = $this->sQuery->fetchColumn();
        $this->sQuery->closeCursor();
        return $result;
    }
	
	public function queryCount(){
        return $this->querycount;
    }
    
   private function ExceptionLog($message , $sql = ""){
		$exception .= $message;
		if(!empty($sql))
			$exception .= "\r\nRaw SQL : "  . $sql;
		if (DEBUG == TRUE) 
			echo $exception;
		else
			error_log($exception,0);
	}
}

<?php

abstract class AbstractRequest
{
    protected static $tableName;

    /**
     * @var array параметры поиска запроса
     */
    protected $params;

    public function __construct($params = [])
    {
        $this->params = $params;
    }


    public function find()
    {
        $query = 'SELECT * FROM '.static::$tableName;

        if($this->params) {
            $queryWhere = [];
            $queryValues = [];

            foreach($this->params as $name => $value) {
                switch($name) {
                    case 'id':
                        $queryWhere[] = 'id = %i';
                        $queryValues[] = $value;
                        break;
                }
            }

            $query .= ' WHERE '.implode(" AND ",  $queryWhere);
        }

        return call_user_func_array('DB::query', array_merge([$query], $queryValues));
    }


    public static function findAll()
    {
        return DB::query('SELECT * FROM '.static::$tableName);
    }


    public function save()
    {
        $id = @$this->params['id'];

        if($id) {
            DB::update(static::$tableName, $this->params, 'id = %i', $id);
            $result = $this->find();
        } else {
            DB::insert(static::$tableName, $this->params);
            $result = (new static(['id' => mysqli_insert_id(DB::get())]))->find()[0];
        }

        return $result;
    }
}

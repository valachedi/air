<?php

class AirplaneRequest extends AbstractRequest
{
    protected static $tableName = 'airplane';


    public function find()
    {
        $query = 'SELECT * FROM airplane';
        $queryValues = [];

        if($this->params) {
            $queryWhere = [];

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
}

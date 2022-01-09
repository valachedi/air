<?php

class DevRequest extends AbstractRequest
{
    protected static $tableName = 'flight';

    public function find()
    {
        $query = 'SELECT * FROM '.static::$tableName;
        $queryValues = [];

        if($this->params) {
            $queryWhere = [];

            foreach($this->params as $name => $value) {
                switch($name) {
                    case 'id':
                        $queryWhere[] = 'id = %i';
                        $queryValues[] = $value;
                        break;
                    case 'city_id_from':
                        $queryWhere[] = 'airport_id_from IN (SELECT id FROM airport WHERE city_id = %i)';
                        $queryValues[] = $value;
                        break;
                    case 'city_id_to':
                        $queryWhere[] = 'airport_id_to IN (SELECT id FROM airport WHERE city_id = %i)';
                        $queryValues[] = $value;
                        break;
                    case 'airport_ids_from':
                        $queryWhere[] = 'airport_id_from IN %li';
                        $queryValues[] = $value;
                        break;
                    case 'airport_ids_to':
                        $queryWhere[] = 'airport_id_to IN %li';
                        $queryValues[] = $value;
                        break;
                    case 'date_departure':
                        $queryWhere[] = 'time_departure LIKE %s';
                        $queryValues[] = $value.'%';
                        break;
                }
            }

            $query .= ' WHERE '.implode(" AND ",  $queryWhere);
        }

        $flights = call_user_func_array('DB::query', array_merge([$query], $queryValues));

        if(@$this->params['reseats_max']) {
            $flights = array_merge($flights, $this->findWithReseats());
        }

        $this->fillFlightsRelations($flights);

        return $flights;
    }


    private function findWithReseats()
    {
        return (new SearchReseat($this->params['city_id_from'], $this->params['city_id_to'], $this->params['date_departure']??null))
                    ->findReseats($this->params['reseats_max']);
    }


    public function save()
    {
        $id = @$this->params['id'];

        if($id) {
            DB::update(static::$tableName, $this->params, 'id = %i', $id);
            $result = $this->find();
        } else {
            DB::insert(static::$tableName, $this->params);
            $result = (new $this(['id' => DB::insertId()]))->find()[0];
        }

        return $result;
    }

    public static function fillFlightExtraData(&$flight)
    {
        $flight['airport_from'] = (new AirportRequest(['id' => $flight['airport_id_from']]))->find()[0];
        $flight['airport_to'] = (new AirportRequest(['id' => $flight['airport_id_to']]))->find()[0];
        $flight['airplane'] = DB::queryFirstRow('SELECT * FROM airplane WHERE id = %i', $flight['airplane_id']);
        $totalTickets = DB::queryFirstField('SELECT COUNT(*) FROM ticket WHERE flight_id = %i', $flight['id']);
        $flight['places_available'] = $flight['airplane']['capacity'] - $totalTickets;
    }


    private function fillFlightsRelations(array &$flights)
    {
        array_walk($flights, [$this, 'fillFlightExtraData']);
    }
}

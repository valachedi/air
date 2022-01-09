<?php

class AirportRequest extends AbstractRequest
{
    public static function findAll()
    {
        $airports = DB::query('SELECT * FROM airport');
        array_walk($airports, ['static','fillRelations']);

        return $airports;
    }


    public function find()
    {
        $query = 'SELECT * FROM airport';

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

        $airports = call_user_func_array('DB::query', array_merge([$query], $queryValues));
        array_walk($airports, ['static', 'fillRelations']);

        return $airports;
    }


    private static function fillRelations(&$airport)
    {
        $city = DB::queryFirstRow('SELECT country.name country_name, city.name city_name
                                                FROM country
                                                JOIN city ON city.country_id = country.id
                                                WHERE city.id = %i', $airport['city_id']);

        $airport['country_name'] = $city['country_name'];
        $airport['city_name'] = $city['city_name'];
    }
}

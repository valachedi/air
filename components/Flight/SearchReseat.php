<?php
class SearchReseat
{
    const
        MAX_RESEATS_FLIGHTS = 4, // Максимальное количество доп. перелётов
        MAX_RESEAT_TIME_MINUTES = 60*12; // Максимальное ожидание пересадки


    private static $FIELD_NAMES = [
        'id',
        'airport_id_from',
        'airport_id_to',
        'time_departure',
        'time_arrival',
        'airplane_id',
        'comfort_category_id',
        'price',
    ];

    private $cityIdFrom;
    private $cityIdTo;
    private $dateDeparture;
    private $comfortCategoryId;
    private $params;

    public function __construct($params = [])
    {
        $this->params = $params;
        $this->cityIdFrom = $this->params['city_id_from'];
        $this->cityIdTo = $this->params['city_id_to'];
        $this->dateDeparture = $this->params['date_departure']??null;

        if(isset($this->params['comfort_category_id'])) {
            $this->comfortCategoryId = intval($this->params['comfort_category_id']);
        }
    }


    public function findReseats()
    {
        $airportIdsFrom = DB::queryFirstColumn('SELECT id FROM airport WHERE city_id = %i', $this->cityIdFrom);
        $airportIdsTo = DB::queryFirstColumn('SELECT id FROM airport WHERE city_id = %i', $this->cityIdTo);
        $flights = [];
        $sqlWhereComfortCategoryParts = [];

        if($this->comfortCategoryId) {
            $sqlWhereComfortCategoryParts[] = "f0.comfort_category_id = ".intval($this->comfortCategoryId);
        }

        for($reseatsMaxIteration = 1; $reseatsMaxIteration < self::MAX_RESEATS_FLIGHTS; $reseatsMaxIteration++) {
            $sqlJoins = [];
            $tableAliasIndex = 1;
            $fieldNamesForSumPrice[] = "f0.price";
            $fieldNamesLists = [implode(',', $this->getColumnNamesWithPrefix('f0'))];

            for ($i = 0; $i < $reseatsMaxIteration; $i++) {
                $fieldNamesLists[] = implode(',', $this->getColumnNamesWithPrefix('f' . $tableAliasIndex));
                $fieldNamesForSumPrice[] = "f{$tableAliasIndex}.price";
                $sqlJoins[] = "JOIN flight f{$tableAliasIndex} ON
                    f" . ($tableAliasIndex - 1) . ".airport_id_to = f{$tableAliasIndex}.airport_id_from
                    AND f" . ($tableAliasIndex - 1) . ".airport_id_from != f" . ($tableAliasIndex) . ".airport_id_to 
                    AND f" . ($tableAliasIndex - 1) . ".time_arrival < f" . ($tableAliasIndex) . ".time_departure 
                    AND TIMESTAMPDIFF(minute, f" . ($tableAliasIndex - 1) . ".time_arrival, f{$tableAliasIndex}.time_departure) < ". static::MAX_RESEAT_TIME_MINUTES;

                if($this->comfortCategoryId) {
                    $sqlWhereComfortCategoryParts[] = "f{$tableAliasIndex}.comfort_category_id = ".$this->comfortCategoryId;
                }

                $tableAliasIndex++;
            }

            $sqlHaving = [];
            $sqlHavingValues = [];

            if(@$this->params['price_min']) {
                $sqlHaving[] = 'sum_price >= %i';
                $sqlHavingValues[] = $this->params['price_min'];
            }

            if(@$this->params['price_max']) {
                $sqlHaving[] = 'sum_price <= %i';
                $sqlHavingValues[] = $this->params['price_max'];
            }

            $sqlWhereAirportTo = " AND f{$reseatsMaxIteration}.airport_id_to IN %li";

            $flightsFound = DB::query('SELECT 
            ' . implode(',', $fieldNamesLists) . ',
            ('. implode('+', $fieldNamesForSumPrice) .') sum_price 
            FROM flight f0
            ' . implode("\n", $sqlJoins) . '
            WHERE f0.airport_id_from IN %li
                AND f0.time_departure LIKE %s' . $sqlWhereAirportTo .'
                ' . ($sqlWhereComfortCategoryParts ? ' AND ' . implode(' AND ', $sqlWhereComfortCategoryParts) : ''). '
                ' . ($sqlHaving ? ('HAVING '.implode(' AND ', $sqlHaving)) : ''),
                $airportIdsFrom,
                $this->dateDeparture ? $this->dateDeparture.'%' : '%',
                $airportIdsTo,
                array_shift($sqlHavingValues),
                array_shift($sqlHavingValues)
            );

            $flights = array_merge($flights, $flightsFound);
        }

        return $this->createFlightsFromSqlResponse($flights);
    }


    private function getColumnNamesWithPrefix($tableAlias)
    {
        return array_map(function($fieldName) use($tableAlias) {
            return "{$tableAlias}.{$fieldName} {$tableAlias}_{$fieldName}";
        }, self::$FIELD_NAMES);
    }


    /**
     * Создание перелётов из массивов с элементами вида:
     * ["f0_airport_id_to"]=>
    string(1) "1"
    ["f0_time_departure"]=>
    string(19) "2022-02-22 18:34:00"
    ["f0_time_arrival"]=>
    string(19) "2022-02-22 22:48:00"
    ["f0_airplane_id"]=>
    string(1) "1"
    ["f1_id"]=>
    string(1) "1"
    ["f1_airport_id_from"]=>
    string(1) "1"
    ["f1_airport_id_to"]=>
    string(1) "2"
    ["f1_time_departure"]=>
    string(19) "2022-01-20 13:56:00"
    ["f1_time_arrival"]=>
    string(19) "2022-02-22 18:04:00"
    ["f1_airplane_id"]=>
    string(1) "1"
     * где f0_, f1_ = это данные пересадочных перелётов
     * @param $sqlFlightsData
     */
    private function createFlightsFromSqlResponse($sqlFlightsData)
    {
        $flights = [];

        foreach($sqlFlightsData as $flightsData) {
            foreach($flightsData as $key => $value) {
                if($key == 'sum_price') {
                    $priceSum = $value;
                } else {
                    $flightIndex = substr(strstr($key, '_', true), 1);
                    $flightParam = substr(strstr($key, '_'), 1);
                    $flightReseats[$flightIndex][$flightParam] = $value;
                }
            }

            $firstFlight = $flightReseats[0];
            $lastFlight = $flightReseats[count($flightReseats)-1];
            FlightRequest::fillFlightsRelations($flightReseats);

            $flights[] = array_merge(
                $firstFlight,
                [
                    'time_arrival' => $lastFlight['time_arrival'],
                    'airport_id_to' => $lastFlight['airport_id_to'],
                    'reseats' => $flightReseats,
                    'price' => $priceSum
                ]
            );
        }

        return $flights;
    }
}

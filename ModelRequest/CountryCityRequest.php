<?php

class CountryCityRequest extends AbstractRequest
{
    /**
     * Получение стран с городами в ключе cities
     * @return array
     */
    public static function findAll()
    {
        $countries = DB::query('SELECT * FROM country');
        $cities = DB::query('SELECT * FROM city');

        foreach($countries as &$country) {
            $countryId = $country['id'];

            $country['cities'] = array_values(array_filter($cities, function($eachCity) use($countryId){
                return $eachCity['country_id'] == $countryId;
            }));
        }

        return $countries;
    }
}

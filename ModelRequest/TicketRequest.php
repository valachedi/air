<?php

class TicketRequest extends AbstractRequest
{
    protected static $tableName = 'ticket';


    public static function findAll()
    {
        $tickets = DB::query('SELECT * FROM ticket WHERE user_id = %i', CurrentUser::getInstance()->getId());

        return $tickets
                ? array_map(['self','fillTicketData'], $tickets)
                : [];
    }


    public function buy()
    {
        if(!($result = $this->getBoughtTicket())) {
            DB::insert(static::$tableName, $this->getTicketData());
            $result = $this->getBoughtTicket();
        }

        return $result;
    }


    public function delete()
    {
        $removed = DB::query('DELETE FROM '.static::$tableName.' WHERE id = %i', $this->params['id']);
        return ['removed' => $removed];
    }


    private function getTicketData()
    {
        return [
            'flight_id' => $this->params['flight_id'],
            'user_id' => CurrentUser::getInstance()->getId(),
        ];
    }


    private function getBoughtTicket()
    {
        $ticket = DB::queryFirstRow('SELECT * FROM ticket WHERE user_id = %i AND flight_id =%i',
            CurrentUser::getInstance()->getId(),
            $this->params['flight_id']);

        if($ticket) {
            $this->fillTicketData($ticket);
        }

        return $ticket;
    }


    private static function fillTicketData(&$ticket)
    {
        $flight = (new FlightRequest(['id' => $ticket['flight_id']]))->find()[0];
        FlightRequest::fillFlightExtraData($flight);
        $ticket['flight'] = $flight;

        return $ticket;
    }
}

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
            $moneyNeedToPay = $this->getFlightToBuy()['price'];

            if(CurrentUser::getInstance()->canSpend($moneyNeedToPay)) {
                DB::insert(static::$tableName, $this->getTicketData());
                CurrentUser::getInstance()->spendMoney($moneyNeedToPay);
                $result = $this->getBoughtTicket();
            } else {
                $result = ['error' => 'Недостаточно денег для покупки билета'];
            }
        }

        return $result;
    }


    public function delete()
    {
        $ticketPrice = DB::queryFirstField('SELECT price FROM ticket WHERE id = %i', $this->params['id']);
        UserRequest::increaseUserMoney(CurrentUser::getInstance()->getId(), $ticketPrice);
        $removed = DB::query('DELETE FROM '.static::$tableName.' WHERE id = %i', $this->params['id']);
        return ['removed' => $removed];
    }


    private function getTicketData()
    {
        $flight = (new FlightRequest(['id' => $this->params['flight_id']]))->find()[0];

        return [
            'flight_id' => $flight['id'],
            'price' => $flight['price'],
            'user_id' => CurrentUser::getInstance()->getId(),
        ];
    }


    private function getFlightToBuy()
    {
        return DB::queryFirstRow('SELECT * FROM flight WHERE id = %i', $this->params['flight_id']);
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

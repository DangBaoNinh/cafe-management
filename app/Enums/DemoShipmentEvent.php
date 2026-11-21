<?php

namespace App\Enums;

enum DemoShipmentEvent: string
{
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
}

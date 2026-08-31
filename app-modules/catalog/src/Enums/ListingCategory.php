<?php

namespace Kinkoza\Catalog\Enums;

enum ListingCategory: string
{
    case MachineryEquipment = 'machinery-equipment';
    case VehiclesFleet = 'vehicles-fleet';
    case CommercialProperty = 'commercial-property';
    case IntangibleAssets = 'intangible-assets';

    public function label(): string
    {
        return match ($this) {
            self::MachineryEquipment => 'Machinery & equipment',
            self::VehiclesFleet => 'Vehicles & fleet',
            self::CommercialProperty => 'Commercial property',
            self::IntangibleAssets => 'Intangible assets',
        };
    }
}

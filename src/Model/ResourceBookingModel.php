<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Model;

/**
 * Class ResourceBookingModel.
 */
class ResourceBookingModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking';

    /**
     * @param $objResource
     * @param $starttime
     * @param $endtime
     *
     * @return ResourceBookingModel
     */
    public static function findOneByResourceIdStarttimeAndEndtime($objResource, $starttime, $endtime)
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?'];
        $arrValues = [$objResource->id, $starttime, $endtime];

        return self::findOneBy($arrColumn, $arrValues);
    }

    /**
     * @param $objResource
     * @param $starttime
     * @param $endtime
     * @param $memberid
     *
     * @return ResourceBookingModel
     */
    public static function findOneByResourceIdStarttimeEndtimeAndMember($objResource, $starttime, $endtime, $memberid)
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?', 'member=?'];
        $arrValues = [$objResource->id, $starttime, $endtime, $memberid];

        return self::findOneBy($arrColumn, $arrValues);
    }
}

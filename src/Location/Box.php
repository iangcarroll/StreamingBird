<?php

namespace OwlyCode\StreamingBird\Location;

class Box implements LocationInterface
{
    /**
     * @var float
     */
    private $minLon;

    /**
     * @var float
     */
    private $minLat;

    /**
     * @var float
     */
    private $maxLon;

    /**
     * @var float
     */
    private $maxLat;

    /**
     * @param float $minLon
     * @param float $minLat
     * @param float $laxLon
     * @param float $maxLat
     */
    public function __construct($minLon, $minLat, $laxLon, $maxLat)
    {
        $this->minLon = $minLon;
        $this->minLat = $minLat;
        $this->maxLon = $maxLon;
        $this->maxLat = $maxLat;
    }

    /**
     * {@inheritDoc}
     */
    public function getBoundingBox()
    {
        // Calc bounding boxes
        $maxLat = round($this->latitude + rad2deg($this->radius / self::EARTH_RADIUS_KM), 2);
        $minLat = round($this->latitude - rad2deg($this->radius / self::EARTH_RADIUS_KM), 2);

        // Compensate for degrees longitude getting smaller with increasing latitude
        $maxLon = round($this->longitude + rad2deg($this->radius / self::EARTH_RADIUS_KM / cos(deg2rad($this->latitude))), 2);
        $minLon = round($this->longitude - rad2deg($this->radius / self::EARTH_RADIUS_KM / cos(deg2rad($this->latitude))), 2);

        return array($minLon, $minLat, $maxLon, $maxLat);
    }
}

<?php

namespace OwlyCode\StreamingBird\Location;

interface LocationInterface
{
    /**
     * @return float[]
     */
    public function getBoundingBox();
}

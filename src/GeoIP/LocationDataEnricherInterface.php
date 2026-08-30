<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

/** Optional host-owned enrichment seam; implementations must return a new snapshot. */
interface LocationDataEnricherInterface {
  public function enrich(LocationData $location):LocationData;
}

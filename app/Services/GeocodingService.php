<?php

namespace App\Services;

class GeocodingService
{
    /**
     * Maps the same lat/lng anchors used by EventSeeder to human-readable city names.
     * Using Euclidean distance (not Haversine) — fast and accurate enough for nearest-anchor lookup.
     *
     * @var array<int, array{0: float, 1: float, 2: string}>
     */
    private const CITIES = [
        // United States
        [40.7128, -74.0060, 'New York, US'],
        [34.0522, -118.2437, 'Los Angeles, US'],
        [41.8781, -87.6298, 'Chicago, US'],
        [29.7604, -95.3698, 'Houston, US'],
        [33.4484, -112.0740, 'Phoenix, US'],
        [39.9526, -75.1652, 'Philadelphia, US'],
        [29.4241, -98.4936, 'San Antonio, US'],
        [32.7157, -117.1611, 'San Diego, US'],
        [32.7767, -96.7970, 'Dallas, US'],
        [37.3382, -121.8863, 'San Jose, US'],
        [30.2672, -97.7431, 'Austin, US'],
        [37.7749, -122.4194, 'San Francisco, US'],
        [47.6062, -122.3321, 'Seattle, US'],
        [39.7392, -104.9903, 'Denver, US'],
        [42.3601, -71.0589, 'Boston, US'],
        [36.1699, -115.1398, 'Las Vegas, US'],
        [25.7617, -80.1918, 'Miami, US'],
        [33.7490, -84.3880, 'Atlanta, US'],
        [38.9072, -77.0369, 'Washington D.C., US'],
        [36.1627, -86.7816, 'Nashville, US'],
        [45.5152, -122.6784, 'Portland, US'],
        [29.9511, -90.0715, 'New Orleans, US'],
        // Canada
        [43.6532, -79.3832, 'Toronto, CA'],
        [45.5019, -73.5674, 'Montreal, CA'],
        [49.2827, -123.1207, 'Vancouver, CA'],
        [51.0447, -114.0719, 'Calgary, CA'],
        [45.4215, -75.6972, 'Ottawa, CA'],
        [53.5461, -113.4938, 'Edmonton, CA'],
        [46.8139, -71.2080, 'Quebec City, CA'],
        [49.8951, -97.1384, 'Winnipeg, CA'],
        // Mexico
        [19.4326, -99.1332, 'Mexico City, MX'],
        [20.6597, -103.3496, 'Guadalajara, MX'],
        [25.6866, -100.3161, 'Monterrey, MX'],
        [19.0414, -98.2063, 'Puebla, MX'],
        [32.5149, -117.0382, 'Tijuana, MX'],
        [21.1619, -86.8515, 'Cancún, MX'],
        [20.9674, -89.5926, 'Mérida, MX'],
        // Europe
        [51.5074, -0.1278, 'London, GB'],
        [48.8566, 2.3522, 'Paris, FR'],
        [52.5200, 13.4050, 'Berlin, DE'],
        [40.4168, -3.7038, 'Madrid, ES'],
        [41.9028, 12.4964, 'Rome, IT'],
        [52.3676, 4.9041, 'Amsterdam, NL'],
        [41.3851, 2.1734, 'Barcelona, ES'],
        [48.1351, 11.5820, 'Munich, DE'],
        [45.4642, 9.1900, 'Milan, IT'],
        [48.2082, 16.3738, 'Vienna, AT'],
        [50.0755, 14.4378, 'Prague, CZ'],
        [38.7223, -9.1393, 'Lisbon, PT'],
        [53.3498, -6.2603, 'Dublin, IE'],
        [55.6761, 12.5683, 'Copenhagen, DK'],
        [59.3293, 18.0686, 'Stockholm, SE'],
        [59.9139, 10.7522, 'Oslo, NO'],
        [60.1699, 24.9384, 'Helsinki, FI'],
        [50.8503, 4.3517, 'Brussels, BE'],
        [47.3769, 8.5417, 'Zurich, CH'],
        [52.2297, 21.0122, 'Warsaw, PL'],
        [47.4979, 19.0402, 'Budapest, HU'],
        [37.9838, 23.7275, 'Athens, GR'],
        [45.7640, 4.8357, 'Lyon, FR'],
        [53.5511, 9.9937, 'Hamburg, DE'],
        [53.4808, -2.2426, 'Manchester, GB'],
        [55.9533, -3.1883, 'Edinburgh, GB'],
        [50.1109, 8.6821, 'Frankfurt, DE'],
        [50.0647, 19.9450, 'Kraków, PL'],
        [41.1579, -8.6291, 'Porto, PT'],
        [40.8518, 14.2681, 'Naples, IT'],
        // Global hubs
        [35.6762, 139.6503, 'Tokyo, JP'],
        [37.5665, 126.9780, 'Seoul, KR'],
        [1.3521, 103.8198, 'Singapore, SG'],
        [-33.8688, 151.2093, 'Sydney, AU'],
        [-37.8136, 144.9631, 'Melbourne, AU'],
        [25.2048, 55.2708, 'Dubai, AE'],
        [-23.5505, -46.6333, 'São Paulo, BR'],
        [-34.6037, -58.3816, 'Buenos Aires, AR'],
    ];

    public function nearestCity(float $lat, float $lng): string
    {
        $nearest = 'Unknown';
        $minDist = PHP_FLOAT_MAX;

        foreach (self::CITIES as [$clat, $clng, $name]) {
            $dist = ($lat - $clat) ** 2 + ($lng - $clng) ** 2;
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $name;
            }
        }

        return $nearest;
    }

    /** @return string[] */
    public function allCityNames(): array
    {
        return array_column(self::CITIES, 2);
    }
}

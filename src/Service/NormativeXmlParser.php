<?php


namespace App\Service;


use App\Service\Interfaces\ParserXml;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

class NormativeXmlParser implements ParserXml
{
    /**@var array All the lines in xml-file */
    private $polylines = [];

    /**@var array All the points in xml-file */
    private $points = [];

    private $errors = [];

    /**
     * Setting tree-object that we used
     * @var array
     */

    private $settingFields = [
        'boundary' => ['InfoPart', 'TerritorialZoneInfo', 'Objects', 'Lands', 'LandsValuation', 'LandsValuationType', 'MunicipalUnitNormativValuation'],
        'zony' => ['InfoPart', 'TerritorialZoneInfo', 'Objects', 'Lands', 'LandsValuation', 'LandsValuationType', 'MunicipalUnitNormativValuation', 'EconPlanZones', 'EconPlanZone'],
        /*'localni' => ['InfoPart', 'TerritorialZoneInfo', 'Objects', 'Lands', 'LandsValuation', 'LandsValuationType', 'MunicipalUnitNormativValuation', 'LocalFactors', 'LocalFactor'],*/
    ];


    public function parse(\SimpleXMLElement $simpleXMLElement)
    {
        $array = json_decode(json_encode($simpleXMLElement), true);
        if ($this->getPolyline($array) && $this->getPoints($array)) {
            $result = $this->parseDataXml($array);
            return $result;
        }
        return false;
    }

    public function findNode(array $dataXml, array $keys): array
    {
        foreach ($keys as $key) {
            if (is_array($dataXml) && array_key_exists($key, $dataXml)) {
                $dataXml = $dataXml[$key];
            } else {
                return [];
            }
        }

        return $dataXml;
    }

    private function getNodeString()
    {
        $str = '[InfoPart]';
        return $str;
    }

    public function parseDataXml(array $dataXml)
    {
        $currentPoints = [];
        foreach ($this->settingFields as $value => $key) {
            $currentPoints[$value] = $this->findNode($dataXml, $key);
            if ($this->ifArrayOrList($currentPoints[$value])) {
                foreach ($currentPoints[$value] as $item => $node) {
                    $currentPoints[$value][$item]['coordinates'] = $this->getGeometry($node);
                }
            } else {
                $currentPoints[$value] = $this->getGeometry($currentPoints[$value]);
            }

        }

        return $currentPoints;
    }

    private function getGeometry(array $data)
    {
        if (array_key_exists('Externals', $data)) {
            $valueUlid = $this->getUlid($data['Externals']);
            if ($valueUlid !== '' && array_key_exists((int)$valueUlid, $this->polylines)) {
                return $this->getCurrentPoints($this->polylines[(int)$valueUlid]);
            }
        } else {
            return array();
        }
    }

    /**
     * Перевіряє чи є Node кінцевим, чи скрадовим
     * @param array $data
     * @return bool
     */
    private function ifArrayOrList(array $data): bool
    {
        if (array_key_last($data) > 0) {
            return true;
        }
        return false;

    }

    private function getUlid($externals)
    {
        $userdata = '';
        array_walk_recursive($externals, function ($item, $key) use (&$userdata) {
            if ($key === 'ULID') {
                $userdata = $item;
            }
        }, $userdata);

        return $userdata;
    }


    /**
     * @param array $data
     * @return array|bool
     */
    private function getPolyline(array $data)
    {
        try {
            foreach ($data['InfoPart']['MetricInfo']['Polyline']['PL'] as $value) {
                $this->polylines[$value['ULID']] = $value['Points']['P'];
            }
            return $this->polylines;
        } catch (\Exception $exception) {
            $this->errors[] = $exception->getMessage();
            return false;
        }
    }


    /**
     * @param array $data
     * @return array|bool
     */
    private function getPoints(array $data)
    {
        try {
            foreach ($data['InfoPart']['MetricInfo']['PointInfo']['Point'] as $value) {
                $this->points[$value['UIDP']]['X'] = $value['X'];
                $this->points[$value['UIDP']]['Y'] = $value['Y'];
            }
            return $this->points;
        } catch (\Exception $exception) {
            $this->errors[] = $exception->getMessage();
            return false;
        }
    }

    private function getCurrentPoints(array $data)
    {
        array_pop($data);
        $dataCoordinate = $this->array_intersect_key_withoutSort($data);
        $dataCoordinate[] = reset($dataCoordinate);

        return $dataCoordinate;
    }

    private function array_intersect_key_withoutSort(array $data)
    {
        $dataCoordinate = array_map(function ($value) {
            if (array_key_exists((int)$value, $this->points)) {
                return $this->points[$value];
            }
        }, $data);

        return $dataCoordinate;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }




}
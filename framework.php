<?php

// interfaces

interface Positioned { public function coordinates(); }
interface HasRadius { public function radius(); }
interface HasId { public function id(); }

// traits

trait universalGetterSetter {
    public function __get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        } elseif(method_exists($this, $property)) {
            return $this->{$property}();
        }
    }
    
    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
        return $this;
    }
}

trait coordinates {
    private $x;
    private $y;
    public function coordinates() { return [$this->x, $this->y]; }
}

trait id {
    private $id;
    public function id() { return $this->id; }
}

// classes

class Point implements Positioned
{
    use coordinates;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }
}

class Collection
{
    private $objects = [];

    public function __construct(array $array = []) {
        foreach($array as $object) {
            $this->add($object);
        }
    }

    public function get($key) {
        return $this->objects[$key];
    }
    
    public function add(HasId $object) {
        $this->objects[$object->id()] = $object;
    }

    public function update(HasId $object) {
        $this->remove($object->id());
        $this->add($object);
    }

    public function remove($key) {
        unset($this->objects[$key]);
    }

    public function where($prop, $comp = '=', $value = true) {
        $func = function($obj) use ($prop, $comp, $value) {

            switch($comp) {
                case '=':
                    return $obj->__get($prop) == $value;
                case '!=':
                    return $obj->__get($prop) != $value;
                case '>':
                    return $obj->__get($prop) > $value;
                case '>=':
                    return $obj->__get($prop) >= $value;
                case '<':
                    return $obj->__get($prop) < $value;
                case '<=':
                    return $obj->__get($prop) <= $value;
                case 'has':
                    return in_array($value, $obj->__get($prop));
                case 'not has':
                    return !in_array($value, $obj->__get($prop));
                case 'in':
                    return in_array($obj->__get($prop), $value);
                case 'not in':
                    return !in_array($obj->__get($prop), $value);
            }
            
        };
        $objects = array_filter($this->objects, $func);
        $res = new Collection($objects);
        return $res;
    }

    public function all() : array
    {
        return array_values($this->objects);
    }

    public function count() : int
    {
        return count($this->objects);
    }

    public function ids() : array
    {
        return array_keys($this->objects);
    }

}

class Map
{
    private static $maxX;
    private static $maxY;
    
    public function __construct(int $maxX, int $maxY) {
        self::$maxX = $maxX;
        self::$maxY = $maxY;
    }

    public static function normX($x) : int
    {
        $x = intval($x);
        if($x < 0) {$x = 0;}
        if($x > self::$maxX) {$x = self::$maxX;}
        return $x;
    }

    public static function normY($y) : int
    {
        $y = intval($y);
        if($y < 0) {$y = 0;}
        if($y > self::$maxY) {$y = self::$maxY;}
        return $y;
    }

    public static function center() : Point
    {
        $centerX = round(self::$maxX / 2);
        $centerY = round(self::$maxY / 2);
        return new Point($centerX, $centerY);
    }

    public static function corners() : array
    {
        $corners = [
            new Point(0, 0),
            new Point(self::$maxX, 0),
            new Point(0, self::$maxY),
            new Point(self::$maxX, self::$maxY),
        ];
        return $corners;
    }

    public static function nearestCorner(Positioned $center) {
        return nearest($center, self::corners());
    }

    public static function nearest(Positioned $center, array $objects): object
    {
        $res = [];
        foreach ($objects as $object) {
            $res[self::distance($center, $object)] = $object;
        }
        ksort($res);
        $res = array_slice($res, 0, 1)[0];
        return $res;
    }
    
    public static function nearestObjects(Positioned $center, array $objects, int $quantity = 1): Collection
    {
        $res = [];
        foreach ($objects as $object) {
            $res[self::distance($center, $object)] = $object;
        }
        ksort($res);
        $res = array_slice($res, 0, $quantity);
        return new Collection($res);
    }

    public static function distance(Positioned $object1, Positioned $object2): int
    {
        [$x1, $y1] = $object1->coordinates();
        [$x2, $y2] = $object2->coordinates();

        $x = abs($x2 - $x1);
        $y = abs($y2 - $y1);

        $absRes = sqrt(pow($x, 2) + pow($y, 2));
        if (method_exists($object1, 'radius')) {
            $absRes -= $object1->radius();
        }
        if (method_exists($object2, 'radius')) {
            $absRes -= $object2->radius();
        }
        return $absRes;
    }

    public static function objectsInRadius(Positioned $center, array $objects, int $radius) : Collection
    {
        $res = [];
        foreach($objects as $object) {
            if(self::distance($center, $object) <= $radius) {
                $res[] = $object;
            }
        }
        return new Collection($res);
    }

    public static function reverseDirection(Positioned $start, Positioned $end) : int
    {
        $direction = self::direction($start, $end);
        $res = $direction >= 180 ? $direction - 180 : $direction + 180;
        return intval($res);
    }
    
    public static function direction(Positioned $start, Positioned $end) : int
    {
        $firstVectorEnd = new Point(self::$maxX, $start->coordinates()[1]);
        $vector1 = self::vectorCoordinates($start, $firstVectorEnd);
        $vector2 = self::vectorCoordinates($start, $end);
        $scalar = self::scalarProduct($vector1, $vector2);
        $moduleV1 = self::vectorModule($vector1);
        $moduleV2 = self::vectorModule($vector2);
        $modulesProduct = $moduleV1 * $moduleV2;

        if($modulesProduct != 0) {
            $cos = $scalar / $modulesProduct;
        } else {
            $cos = 0;
        }
        $angle = acos($cos);
        $angle = rad2deg($angle);

        // если конечная точка выше
        if($end->coordinates()[1] < $start->coordinates()[1]) {
           $angle = 360-$angle;
        }

        return intval($angle);
    }

    public static function directionBetweenPoints(Positioned $start, Positioned $end1, Positioned $end2) : Int
    {
        $angle1 = self::direction($start, $end1);
        $angle2 = self::direction($start, $end2);
        return intval(($angle1+$angle2)/2);
    }

    public static function directionBetweenAngles(int $angle1, int $angle2) : Int
    {
        return intval(($angle1+$angle2)/2);
    }

    // рассчитывает точку в направлении angle на расстоянии distance от center
    public static function pointInDirection(Positioned $center, $angle, $distance) : Point
    {
        if(is_string($angle)) {
            $angle = self::rose2deg($angle);
        }
        
        $angle = deg2rad($angle);
        [$centerX, $centerY] = $center->coordinates();
        $x = $centerX + $distance * cos($angle);
        $y = $centerY + $distance * sin($angle);
        return new Point($x, $y);
    }

    // private: vectors
    private static function vectorCoordinates(Positioned $start, Positioned $end) {
        [$x1, $y1] = $start->coordinates();
        [$x2, $y2] = $end->coordinates();
        return [$x2-$x1, $y2-$y1];
    }
    
    private static function scalarProduct(array $vector1, array $vector2) {
        return $vector1[0]*$vector2[0] + $vector1[1]*$vector2[1];
    }
    
    private static function vectorModule(array $vector) {
        return sqrt(pow($vector[0], 2)+pow($vector[1], 2));
    }

}

// functions

function rose2deg(string $letters) : Int
{
        $aliases = ['E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW','N','NNE','NE','ENE'];
        $turn = 360/count($aliases);
        $angleForAlias = 0;
        $rose = [];
        foreach($aliases as $alias) {
            $rose[$alias] = $angleForAlias;
            $angleForAlias += $turn;
        }
       return $rose[$letters];
}

function dump($var, string $msg = '') {
    error_log("= $msg ====\n".var_export($var, true));
}

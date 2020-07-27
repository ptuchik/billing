<?php

namespace Ptuchik\Billing;

use Illuminate\Support\Arr;
use Ptuchik\Billing\Exceptions\BillingException;
use ReflectionClass;

/**
 * Class Factory - provides the overridable instance or class name of entities.
 *
 * @package Ptuchik\Billing
 */
class Factory
{
    protected static $solvedClasses = [];

    /**
     * Container for already created instances
     *
     * @var array
     */
    protected static $instanceContainer = [];

    /**
     * Gets the instance of class.
     * It asks for the class from getClass function.
     *
     * @param string $className        Class Name for which object need to be created
     * @param bool   $forceNewInstance Force to return new instance of
     * @param mixed  $params           Parameters to be loaded in class object
     *
     * @return mixed
     */
    public static function get(string $className, bool $forceNewInstance = false, ...$params)
    {
        // Get the class name
        $class = self::getClass($className);

        // Check if it must act as singleton or not and return new instance or already saved instance
        if ($forceNewInstance || !isset(self::$instanceContainer[$class])) {
            self::$instanceContainer[$class] = (new ReflectionClass($class))->newInstanceArgs($params);
        }

        return self::$instanceContainer[$class];
    }

    /**
     * Gets class name after checking in Addons.
     * If this class exists in Addons, then returns that class, otherwise returns the same as requested
     *
     * @param string $className
     *
     * @return string
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public static function getClass(string $className) : string
    {
        if ($class = Arr::get(static::$solvedClasses, $className)) {
            return $class;
        }

        // Get overrided class name
        $override = config('ptuchik-billing.class_overrides.'.$className);

        // Check if overrided class name exists return it, if not return native class name
        if (class_exists($override)) {
            return static::$solvedClasses[$className] = $override;
        } elseif (class_exists($className)) {
            return static::$solvedClasses[$className] = $className;
        } else {
            throw new BillingException('Invalid Class Name: '.$className);
        }
    }
}
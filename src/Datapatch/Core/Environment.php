<?php

namespace Datapatch\Core;

use Datapatch\Datapatch;
use Datapatch\Lang\DataBag;
use Datapatch\Lang\Asserter;

class Environment
{
    const COLOR_RED     = 'red';
    const COLOR_GREEN   = 'green';
    const COLOR_BLUE    = 'blue';
    const COLOR_YELLOW  = 'yellow';
    const COLOR_MAGENTA = 'magenta';
    const COLOR_CYAN    = 'cyan';
    const COLOR_WHITE   = 'white';

    public static $COLORS = [
        'red',
        'green',
        'blue',
        'yellow',
        'magenta',
        'cyan',
        'white'
    ];

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $color;

    /**
     * @var bool
     */
    private $protected;

    /**
     * @param $name string
     * @param $data DataBag
     */
    public function __construct($name, $data)
    {
        $this->name = $name;

        $this->color = $data->extract('color', 'white', function ($color, Asserter $a) {

            if (in_array($color, static::$COLORS)) {
                return $color;
            } else {
                $a->raise("Invalid color \":color\" on \"{$this}\" env! Valid colors are: :colors.", [
                    'color' => $color,
                    'colors' => implode(', ', static::$COLORS)
                ]);
            }
        });

        $this->protected = $data->extract('protected', FALSE, function ($protected, Asserter $a) {

            if (in_array($this->name, [ 'live', 'production' ])) {
                return TRUE;
            } elseif (is_bool($protected)) {
                return $protected;
            } elseif (is_string($protected)) {
                $protected = trim(strtolower($protected));
                return $protected == 'true' ? TRUE : FALSE;
            }else {
                $a->raise("Invalid 'protected' flag on \"{$this}\" env!");
            }
        });
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @return boolean
     */
    public function isProtected()
    {
        return $this->protected;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}

<?php

/*
 * This file is part of ImageCompare.
 *
 * (c) 2015 Philipp Steingrebe <philipp@steingrebe.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 */


/**
 * ValueObject as representation of an Image.
 *
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 * 
 */

Class Image {

    /**
     * The size of the square that is used to estimate the average color of this image
     */
    
    const AVG_SIZE = 10;

    /**
     * The underlying image-resource
     * @var resource
     */
    
    private $img = null;

    /**
     * The average color of this image
     * @var Color
     */
    
    private $avg = null;

    /**
     * Constructor
     *
     * @param resource the image-resource this object builds ib
     * 
     */

    public function __construct($resource)
    {
        $this->img = $resource;
    }

    /**
     * Destructor
     * Frees the memory used by the image-resource
     * 
     */

    public function __destruct()
    {
        imagedestroy($this->img);
    }

    public function difference(Image $comp)
    {
        // Equalize image size
        if ($comp->size() != $this->size()) {
            $comp = $comp->resize($this->size());
        }

        $avgComp = array();
        $avgOrg  = array();

        foreach(ImagePixelMatrix::fromImage($this)->getHotSpots() as $image) {
            $avgComp[] = $image->avg();
        }



        foreach(ImagePixelMatrix::fromImage($comp)->getHotSpots() as $image) {
            $avgOrg[] = $image->avg();
        }

        $factor = sizeof($avgComp) / sizeof($avgOrg);
        $factor = $factor < 0 ? 1 / $factor : $factor;
        
        $deviation = 1 - Color::avg($avgComp)->deviation(Color::avg($avgOrg));

        return $deviation * $factor;
    }

    public function compare(Image $comp, $tolerance = 20) 
    {
        $tolerance /= 100;
        return $this->difference($comp) < $tolerance;
    }

    /**
     * Substracts a given mask from this image.
     * The mask is resized to the curent image size and the color
     * of each pixel is compared within a given tolerance. 
     * Similar colored pixels are turned into white. 
     *
     * @param  Image   $mask      the image mask to substract
     * @param  integer $tolerance the color tolerance in percent
     *
     * @return Image              the substracted image
     * 
     */
    
    public function substract(Image $mask, $tolerance = 0)
    {
        $size = $this->size();

        if ($mask->size() != $size) {
            $mask = $mask->resize($size);
        }

        $target = imagecreatetruecolor($size[0], $size[1]);

        for ($x = 0; $x < $size[0]; $x++) {
            for ($y = 0; $y < $size[1]; $y++) {
                if ($this->colorat($x, $y)->compare($mask->colorat($x, $y), $tolerance)) {
                    imagesetpixel($target, $x, $y, 0xFFFFFF);
                }
                else {
                    imagesetpixel($target, $x, $y, $this->colorat($x, $y)->toInt());
                }
            }
        }

        return self::fromResource($target);
    }

    public function avg()
    {
        if ($this->avg === null) {
            $image = $this->resize([self::AVG_SIZE, self::AVG_SIZE]);
            $colors = array();

            for ($x = 0; $x < self::AVG_SIZE; $x++) {
                for ($y = 0; $y < self::AVG_SIZE; $y++) {
                    $color = $image->colorat($x, $y);

                    if (!$color->compare(Color::white())) {
                        $colors[] = $color;
                    }
                }
            }

            $this->avg = Color::avg($colors);
        }

        return $this->avg;
    }

    public function colorat($x, $y)
    {
        return Color::fromInt(imagecolorat($this->img, $x, $y));
    }


    public function slice(array $rect)
    {
        // This does not work in all PHP versions due to a bug
        //return Image::fromResource(imagecrop($this->img, $rect));
        
        $target = imagecreatetruecolor($rect['width'], $rect['height']);
        imagecopy($target, $this->img, 0, 0, $rect['x'], $rect['y'], $rect['width'], $rect['height']);

        return self::fromResource($target);
    }

    public function size()
    {
        return [imagesx($this->img), imagesy($this->img)];
    }

    public function resize(array $size = array(100, 100))
    {
        $target = imagecreatetruecolor($size[0], $size[1]);
        imagecopyresized($target, $this->img, 0, 0, 0, 0, $size[0], $size[1], imagesx($this->img), imagesy($this->img));

        return self::fromResource($target);
    }

    public function show()
    {
        header("Content-type: image/png");
        imagepng($this->img);
        die();
    }


    /**
     * Saves this image to png file.
     * If no name is provied the hash value of this image will be used.
     *
     * @param  string $name (optional) the name of the image
     * @param  string $path (optional) the path where to save the image
     *
     * @return self         this object
     */
    
    public function save($name = null, $path = '') {
        if ($name === null) {
            $name = $this->hash();
        }

        imagepng($this->img, $path . $name . '.png');

        return $this;
    }


    /**
     * Generates the MD5 hash of this image.
     * Optionaly a salt can be provided to generate more enthropy.
     *
     * @param String $salt  the salt
     *
     * @return String       the MD5 hash
     * 
     */
    
    public function hash($salt = '')
    {
        $hash = null;

        ob_start(function($buffer) use (&$hash, $salt){
            $hash = md5($buffer . $salt);
        });

        imagepng($this->img);
        ob_end_clean();

        return $hash;
    }


    /**
     * Factory method: From Resource
     *
     * @param resource $resource the image-resource
     *
     * @return Image             the new instance of Image
     * 
     */
    
    public static function fromResource($resource)
    {
        return new self($resource);
    }


    /**
     * Factory method: From binary
     *
     * @param resource $resource the image-binary content
     *
     * @return Image             the new instance of Image
     * 
     */
    
    public static function fromBin($binf)
    {
        return new self(imagecreatefromstring($bin));
    }


    /**
     * Factory method: From Resource
     *
     * @param resource $resource the path or url to an image
     *
     * @return Image             the new instance of Image
     * 
     */

    public static function fromFile($path)
    {
        return new self(imagecreatefromstring(file_get_contents($path)));
    }
}
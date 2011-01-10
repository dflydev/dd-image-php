<?php

class dd_image_ImageResizer {

    /**
    * Get an image resource from a file.
    * @param string $fileName File name on disk.
    * @param array $imageInfo getimagesize image information.
    * @return resource
    */
    static public function resourceFrom($fileName, $imageInfo = null)
    {

        $image = null;

        if ( $imageInfo === null )
            $imageInfo = getimagesize($fileName);

        switch($imageInfo['mime'])
        {

            case 'image/gif':
                if ( imagetypes() & IMG_GIF)
                {
                    $image = imagecreatefromgif($fileName);
                }
                break;

            case 'image/jpeg':
                if ( imagetypes() & IMG_JPG)
                {
                    $image = imagecreatefromjpeg($fileName);
                }
                break;

            case 'image/png':
                if ( imagetypes() & IMG_PNG)
                {
                    $image = imagecreatefrompng($fileName);
                }
                break;

        }

        return $image;

    }

    static private function outputPrep($input, $targetType = null, $targetFileName = null, $targetWidth = null, $targetHeight = null)
    {

        // Default to JPEG.
        if ( $targetType === null )
            $targetType = IMG_JPG;

        // If no target filename is specified, create one.
        if ( $targetFileName === null )
            $targetFileName = tempnam( '/tmp', 'dd_image_ImageResizer-' );

        // Assume we have been given a filename.
        $sourceImageInfo = getimagesize($input);
        $source = self::resourceFrom($input, $sourceImageInfo);

        list($sourceWidth, $sourceHeight, $sourceType) = $sourceImageInfo;

        // If no target width or height is specified, use the source
        // image width and height.
        if ( ! isset($targetWidth) ) $targetWidth = $sourceWidth;
        if ( ! isset($targetHeight) ) $targetHeight = $sourceHeight;

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        return array($target, $targetType, $targetFileName, $targetWidth, $targetHeight, $source, $sourceWidth, $sourceHeight, $sourceType);

    }

    /**
    * Copy an image.
    * @param mixed $input File name
    * @param int $targetType Image type.
    * @param string $targetFileName Target file name.
    * @param int $targetWidth Target width.
    * @param int $targetHeight Target height.
    * @return HC_Image_File
    */
    static public function copy($input, $targetType = null, $targetFileName = null, $targetWidth = null, $targetHeight = null, $quality = null)
    {

        $oldTargetFileName = $targetFileName;

        list(
            $target, $targetType, $targetFileName, $targetWidth, $targetHeight,
            $source, $sourceWidth, $sourceHeight, $sourceType
        ) = self::outputPrep($input, $targetType, $targetFileName, $targetWidth, $targetHeight);

        self::copyResource(
            $source, $sourceWidth, $sourceHeight,
            $target, $targetWidth, $targetHeight
        );

        if ( $targetFileName !== null )
        {
            // If a target file name was specified, we should store out to that filename.
            self::storeTo($target, $targetType, $targetFileName, $quality);
        }

    }

    /**
    * Copy a source resource into a target resource.
    * @param resource $source Source resource.
    * @param int $sourceWidth Source width.
    * @param int $sourceHeight Source height.
    * @param resource $target Source resource.
    * @param int $targetWidth Source width.
    * @param int $targetHeight Source height.
    */
    static public function copyResource($source, $sourceWidth, $sourceHeight, $target, $targetWidth, $targetHeight)
    {

        if ( $sourceHeight != $targetHeight )
        {

            // We resample if there is any change at all in height.

            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );

        }
        else
        {

            // If there is no change in size, we can just do an image
            // copy.
            imagecopy(
                $target,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight
            );

        }

    }

    /**
    * Store an image out to a file on disk.
    * @param resource $target Target image.
    * @param int $targetType Type of image.
    * @param string $targetFileName Target file name on disk.
    */
    static public function storeTo($target, $targetType, $targetFileName, $quality = null)
    {

        $args = array($target, $targetFileName);
        if ( $quality !== null ) $args[] = $quality;

        switch($targetType)
        {

            case IMG_GIF:
                imagegif($target, $targetFileName);
                break;

            case IMG_JPG:
                call_user_func_array('imagejpeg', $args);
                break;

            case IMG_PNG:
                imagepng($target, $targetFileName);
                break;

            default:
                //error_log('Unknown target type!');
                break;

        }

    }

    static public function scaleTo($input, $type, $targetFileName, $maxWidth = null, $maxHeight = null, $quality = null, $shrinkOnly = null) {
        $target = self::copyScaled($input, $maxWidth, $maxHeight, $shrinkOnly);
        self::storeTo($target, $type, $targetFileName, $quality);
    }
    static public function copyScaled($input, $maxWidth = null, $maxHeight = null, $shrinkOnly = null)
    {

        $sourceImageInfo = getimagesize($input);

        if ( $shrinkOnly === null ) $shrinkOnly = true;

        if ( $maxWidth === null ) $maxWidth = $sourceImageInfo['0'];
        if ( $maxHeight === null ) $maxHeight = $sourceImageInfo['1'];

        list($targetWidth, $targetHeight) = self::fitToBox(
            $sourceImageInfo['0'], $sourceImageInfo['1'],
            $maxWidth, $maxHeight,
            $shrinkOnly
        );

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        self::copyResource(
            self::resourceFrom($input, $sourceImageInfo), $sourceImageInfo['0'], $sourceImageInfo['1'],
            $target, $targetWidth, $targetHeight
        );

        return $target;

    }

    static public function cropTo($input, $type, $targetFileName, $maxWidth = null, $maxHeight = null, $quality = null, $shrinkOnly = null) {
        $target = self::copyCropped($input, $maxWidth, $maxHeight, $shrinkOnly);
        self::storeTo($target, $type, $targetFileName, $quality);
    }

    public function copyCropped($input, $maxWidth = null, $maxHeight = null, $shrinkOnly = null)
    {

        $sourceImageInfo = getimagesize($input);

        if ( $shrinkOnly === null ) $shrinkOnly = true;

        if ( $maxWidth === null ) $maxWidth = $sourceImageInfo['0'];
        if ( $maxHeight === null ) $maxHeight = $sourceImageInfo['1'];

        list($targetWidth, $targetHeight, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH) = self::cropToBox(
            $sourceImageInfo['0'], $sourceImageInfo['1'],
            $maxWidth, $maxHeight,
            $shrinkOnly
        );

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled(
            $target,
            self::resourceFrom($input, $sourceImageInfo),
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $dstW,
            $dstH,
            $srcW,
            $srcH
        );

        return $target;

    }

    /**
    * Fit a box inside another box.
    * @param int $origWidth Width of source.
    * @param int $origHeight Height of source.
    * @param int $maxWidth Largest allowed width of image.
    * @param int $maxHeight Largest allowed height of image.
    * @param bool $shrinkOnly We should only shrink, never enlarge.
    * @return array Width and height of image.
    */
    static public function fitToBox( $origWidth, $origHeight, $maxWidth, $maxHeight, $shrinkOnly = null )
    {

        if ( $shrinkOnly === null ) $shrinkOnly = true;

        /**
        * This math was borrowed from a couple of random PHP tutorials.
        **/

        $widthRatio = $maxWidth / $origWidth;
        $heightRatio = $maxHeight / $origHeight;

        if ( ( $origWidth <= $maxWidth ) and ( $origHeight <= $maxHeight ) )
        {
            if ( $shrinkOnly )
            {
                //error_log('Shrinking only...');
                $targetWidth = $origWidth;
                $targetHeight = $origHeight;
            }
            else
            {
                //error_log('Enlarging...');
                $multiplier = $maxWidth + $maxHeight;
                list($targetWidth, $targetHeight) = self::fitToBox(
                    $origWidth * $multiplier, $origHeight * $multiplier ,
                    $maxWidth, $maxHeight,
                    false
                );
            }
        }
        elseif ( ( $widthRatio * $origHeight ) < $maxHeight )
        {
            $targetWidth = $maxWidth;
            $targetHeight = ceil($widthRatio * $origHeight);
        }
        else
        {
            $targetWidth = ceil($heightRatio * $origWidth);
            $targetHeight = $maxHeight;
        }

        return array($targetWidth, $targetHeight);

    }

    /**
    * Crop a box inside another box.
    * @param int $origWidth Width of source.
    * @param int $origHeight Height of source.
    * @param int $maxWidth Largest allowed width of image.
    * @param int $maxHeight Largest allowed height of image.
    * @param bool $shrinkOnly We should only shrink, never enlarge.
    * @return array Width and height of image.
    */
    static public function cropToBox( $origWidth, $origHeight, $maxWidth, $maxHeight, $shrinkOnly = null )
    {

        if ( $shrinkOnly === null ) $shrinkOnly = true;

        $widthRatio = $maxWidth / $origWidth;
        $heightRatio = $maxHeight / $origHeight;

        //echo " [ origWidth ( $origWidth <= $maxWidth ) and ( $origHeight <= $maxHeight ) ]\n";
        if ( ( $origWidth <= $maxWidth ) and ( $origHeight <= $maxHeight ) )
        {
            if ( $shrinkOnly )
            {
                //echo " [ we are only going to shrink! ]\n";
                $targetWidth = $origWidth;
                $targetHeight = $origHeight;
                $dstX = 0;
                $dstY = 0;
                $srcX = 0;
                $srcY = 0;
                $dstW = $origWidth;
                $dstH = $origHeight;
                $srcW = $origWidth;
                $srcH = $origHeight;
            }
            else
            {
                error_log('Enlarging...');
                error_log('Not sure how we will do this yet!');
                exit();
            }
        }
        else
        {

            $targetHeight = $origHeight;
            $targetWidth = $origWidth;
            $dstX = 0;
            $dstY = 0;
            $srcX = 0;
            $srcY = 0;
            $dstW = $origWidth;
            $dstH = $origHeight;
            $srcW = $origWidth;
            $srcH = $origHeight;

            $overallRatio = ( $heightRatio > $widthRatio ) ? $heightRatio : $widthRatio;

            $dstW = $origWidth * $overallRatio;
            $dstH = $origHeight * $overallRatio;

            //echo " [ ( ($dstW > $origWidth) or ($dstH > $origHeight) ) ]\n";
            if ( ($dstW > $origWidth) or ($dstH > $origHeight) ) {
                if ( $shrinkOnly ) {
                    $dstW = $origWidth;
                    $dstH = $origHeight;
                }
            }

            $targetWidth = $dstW > $maxWidth ? $maxWidth : $dstW;
            $targetHeight = $dstH > $maxHeight ? $maxHeight : $dstH;

            if ( $targetWidth < $dstW )
            {
                $dstX = ( ($dstW - $targetWidth) / 2 ) * -1;
            }

            if ( $targetHeight < $dstH )
            {
                $dstY = ( ($dstH - $targetHeight) / 2 ) * -1;
            }

        }

        return array($targetWidth, $targetHeight, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);

    }

}

?>

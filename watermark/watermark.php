<?php

    if ( !defined('K_COUCH_DIR') ) die(); // cannot be loaded directly

    class KWatermark{

        static function watermark_handler( $params, $node ){
            global $FUNCS, $Config;
            if( count($node->children) ) {die("ERROR: Tag \"".$node->name."\" is a self closing tag");}

            extract( $FUNCS->get_named_vars(
                array(
                       'image'=>'',
                       'with'=>'', /* watermark */
                       'at'=>'', /* position */
                       'suffix'=>'',
                       'check_exists'=>'1',
                      ),
                $params)
            );

            // sanitize params
            $image = trim( $image );
            if( !strlen($image) ) return;

            $with = trim( $with );
            if( !strlen($with) ) $with='watermark.png';
            $with = K_COUCH_DIR.'addons/watermark/'.$with;

            $at = strtolower( trim($at) );
            if( !in_array($at, array('top_left', 'top_center', 'top_right', 'middle_left', 'middle', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right')) ){
                $at = 'bottom_right';
            }

            $suffix = trim( $suffix );
            if( !strlen($suffix) ) $suffix='__wm__';

            $check_exists = ( $check_exists==0 ) ? 0 : 1;

            // Make sure the target image lies within our upload image folder
            $domain_prefix = $Config['k_append_url'] . $Config['UserFilesPath'] . 'image/';
            if( strpos($image, $domain_prefix)===0 ){ // process image only if local
                $orig_src =  $image;
                $image = substr( $image, strlen($domain_prefix) );
                if( $image ){
                    $image = $Config['UserFilesAbsolutePath'] . 'image/' . $image;

                    // check if a watermarked image already exists
                    $path_parts = $FUNCS->pathinfo( $image );
                    $wm_name = $path_parts['filename'] . $suffix . '.' . $path_parts['extension'];
                    $wm = $path_parts['dirname'] . '/' . $wm_name;
                    if( !(file_exists($wm) && $check_exists) ){

                        // Create watermarked image
                        $res = KWatermark::_create_watermarked_image( $image, $with, $at, $wm );
                        if( $FUNCS->is_error($res) ){
                            return 'ERROR: ' . $res->err_msg;
                        }
                    }

                    $path_parts = $FUNCS->pathinfo( $orig_src );
                    return  $path_parts['dirname'] . '/' . $wm_name;
                }
            }
            else{
                return 'ERROR: Can watermark only images that are found within or below '. $domain_prefix;
            }

        }

        static function _create_watermarked_image( $image, $with, $at, $save_as ){
            global $FUNCS;

            ini_set('memory_limit', "128M");
            require_once( K_COUCH_DIR.'includes/timthumb.php' );

            // the watermark (has to be png)
            $src = @imagecreatefrompng( $with );
            if( $src === false ){
                return displayError( 'Unable to open watermark image : ' . $with );
            }
            $srcW = imagesx( $src );
            $srcH = imagesy( $src );

            // the destination image
            $mime_type = mime_type( $image );
            if( !valid_src_mime_type($mime_type) ){
                return displayError( "Invalid src mime type: " .$mime_type );
            }
            $dest = open_image( $mime_type, $image );
            if($dest === false) {
                return displayError( 'Unable to open image : ' . $image );
            }
            $destW = imagesx( $dest );
            $destH = imagesy( $dest );

            // coordinates to display watermark at
            switch( $at ){
                case 'top_left':
                    $X = 0;
                    $Y = 0;
                    break;
                case 'top_center':
                    $X = floor( ($destW - $srcW) / 2 );
                    $Y = 0;
                    break;
                case 'top_right':
                    $X = $destW - $srcW;
                    $Y = 0;
                    break;
                case 'middle_left':
                    $X = 0;
                    $Y = floor( ($destH - $srcH) / 2 );
                    break;
                case 'middle':
                    $X = floor( ($destW - $srcW) / 2 );
                    $Y = floor( ($destH - $srcH) / 2 );
                    break;
                case 'middle_right':
                    $X = $destW - $srcW;
                    $Y = floor( ($destH - $srcH) / 2 );
                    break;
                case 'bottom_left':
                    $X = 0;
                    $Y = $destH - $srcH;
                    break;
                case 'bottom_center':
                    $X = floor( ($destW - $srcW) / 2 );
                    $Y = $destH - $srcH;
                    break;
                case 'bottom_right':
                default:
                    $X = $destW - $srcW;
                    $Y = $destH - $srcH;
            }

            // get down to business
            $tmp = imagecreatetruecolor( $srcW, $srcH );
            imagecopy( $tmp, $dest, 0, 0, $X, $Y, $srcW, $srcH );
            imagecopy( $tmp, $src, 0, 0, 0, 0, $srcW, $srcH );
            imagecopymerge( $dest, $tmp, $X, $Y, 0, 0, $srcW, $srcH, 100 );

            save_image( $mime_type, $dest, $save_as, 100 );

            // cleanup
            imagedestroy( $src );
            imagedestroy( $dest );
            imagedestroy( $tmp );

            return;
        }

    }

    $FUNCS->register_tag( 'watermark', array('KWatermark', 'watermark_handler') );
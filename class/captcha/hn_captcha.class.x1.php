<?php
/**
 * PHP-Class hn_captcha_X1 Version 1.0, released 19-Apr-2004
 * is an extension for PHP-Class hn_captcha.
 * It adds a garbage-collector. (Useful, if you cannot use cronjobs.)
 * Author: Horst Nogajski, horst@nogajski.de
 *
 * License: GNU GPL (http://www.opensource.org/licenses/gpl-license.html)
 * Download: http://hn273.users.phpclasses.org/browse/package/1569.html
 *
 * If you find it useful, you might rate it on http://www.phpclasses.org/rate.html
 * If you use this class in a productional environment, you might drop me a note, so I can add a link to the page.
 **/

/**
 * License: GNU GPL (http://www.opensource.org/licenses/gpl-license.html)
 *
 * This program is free software;
 *
 * you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 **/

/**
 * Tabsize: 4
 **/
require_once __DIR__ . '/hn_captcha.class.php';

/**
 * This class is an extension for hn_captcha-class. It adds a garbage-collector!
 *
 * Normally all used images will be deleted automatically. But everytime a user
 * doesn't finish a request one image stays as garbage in tempfolder.
 * With this extension you can collect & trash this.
 *
 * You can specify:
 * - when the garbage-collector should run, (default = after 100 calls)
 * - the maxlifetime for images, (default is 600, = 10 minutes)
 * - a filename-prefix for the captcha-images (default = 'hn_captcha_')
 * - absolute filename for a textfile which stores the current counter-value
 *   (default is $tempfolder.'hn_captcha_counter.txt')
 *
 * The classextension needs the filename-prefix to identify lost images
 * also if the tempfolder is shared with other scripts.
 *
 * If an error occures (with counting or trash-file-deleting), the class sets
 * the variable $classhandle->garbage_collector_error to TRUE.
 * You can check this in your scripts and if is TRUE, you might execute
 * an email-notification or something else.
 *
 *
 * @shortdesc Class that adds a garbage-collector to the class hn_captcha
 * @public
 * @author Horst Nogajski, (mail: horst@nogajski.de)
 * @version 1.0
 * @date 2004-April-19
 **/
class hn_captcha_X1 extends hn_captcha
{
    ////////////////////////////////

    //

    //	PUBLIC PARAMS

    //

    /**
     * @shortdesc You optionally can specify an absolute filename for the counter. If is not specified, the class use the tempfolder and the default_basename.
     * @public
     * @type string
     **/

    public $counter_filename = '';

    /**
     * @shortdesc This is used as prefix for the picture filenames, so we can identify them also if we share the tempfolder with other programs.
     * @public
     * @type string
     **/

    public $prefix = 'hn_captcha_';

    /**
     * @shortdesc The garbage-collector will started once when the class was called that number times.
     * @public
     * @type int
     **/

    public $collect_garbage_after = 100;

    /**
     * @shortdesc Only trash files which are older than this number of seconds.
     * @public
     * @type int
     **/

    public $maxlifetime = 600;

    /**
     * @shortdesc This becomes TRUE if the counter doesn't work or if trashfiles couldn't be deleted.
     * @public
     * @type bool
     **/

    public $garbage_collector_error = false;

    ////////////////////////////////

    //

    //	PRIVATE PARAMS

    //

    /** @private **/

    public $counter_fn_default_basename = 'hn_captcha_counter.txt';

    ////////////////////////////////

    //

    //	CONSTRUCTOR

    //

    /**
     * @shortdesc This calls the constructor of main-class for extracting the config array and generating all needed params. Additionally it control the garbage-collector.
     * @public
     *
     * @param mixed $config
     * @param mixed $secure
     */

    public function __construct($config, $secure = true)
    {
        // Call Constructor of main-class

        parent::__construct($config, $secure);

        // specify counter-filename

        if ('' == $this->counter_filename) {
            $this->counter_filename = $this->tempfolder . $this->counter_fn_default_basename;
        }

        if ($this->debug) {
            echo "\n<br>-Captcha-Debug: The counterfilename is (" . $this->counter_filename . ')';
        }

        // retrieve last counter-value

        $test = $this->txt_counter($this->counter_filename);

        // set and retrieve current counter-value

        $counter = $this->txt_counter($this->counter_filename, true);

        // check if counter works correct

        if ((false !== $counter) && (1 == $counter - $test)) {
            // Counter works perfect, =:)

            if ($this->debug) {
                echo "\n<br>-Captcha-Debug: Current counter-value is ($counter). Garbage-collector should start at (" . $this->collect_garbage_after . ')';
            }

            // check if garbage-collector should run

            if ($counter >= $this->collect_garbage_after) {
                // Reset counter

                if ($this->debug) {
                    echo "\n<br>-Captcha-Debug: Reset the counter-value. (0)";
                }

                $this->txt_counter($this->counter_filename, true, 0);

                // start garbage-collector

                $this->garbage_collector_error = $this->collect_garbage() ? false : true;

                if ($this->debug && $this->garbage_collector_error) {
                    echo "\n<br>-Captcha-Debug: ERROR! SOME TRASHFILES COULD NOT BE DELETED! (Set the garbage_collector_error to TRUE)";
                }
            }
        } else {
            // Counter-ERROR!

            if ($this->debug) {
                echo "\n<br>-Captcha-Debug: ERROR! NO COUNTER-VALUE AVAILABLE! (Set the garbage_collector_error to TRUE)";
            }

            $this->garbage_collector_error = true;
        }
    }

    ////////////////////////////////

    //

    //	PRIVATE METHODS

    //

    /**
     * @shortdesc Store/Retrieve a counter-value in/from a textfile. Optionally count it up or store a (as third param) specified value.
     * @private
     *
     * @param mixed $filename
     * @param mixed $add
     * @param mixed $fixvalue
     * @return false|int counter-value
     */

    public function txt_counter($filename, $add = false, $fixvalue = false)
    {
        if (is_file($filename) ? true : touch($filename)) {
            if (is_readable($filename) && is_writable($filename)) {
                $fp = @fopen($filename, 'rb');

                if ($fp) {
                    $counter = (int)trim(fgets($fp));

                    fclose($fp);

                    if ($add) {
                        if (false !== $fixvalue) {
                            $counter = (int)$fixvalue;
                        } else {
                            $counter++;
                        }

                        $fp = @fopen($filename, 'wb');

                        if ($fp) {
                            fwrite($fp, $counter);

                            fclose($fp);

                            return $counter;
                        }
  

                        return false;
                    }
  

                    return $counter;
                }
  

                return false;
            }
  

            return false;
        }
  

        return false;
    }

    /**
     * @shortdesc Scanns the tempfolder for jpeg-files with nameprefix used by the class and trash them if they are older than maxlifetime.
     * @private
     **/

    public function collect_garbage()
    {
        $OK = false;

        $captchas = 0;

        $trashed = 0;

        if ($handle = @opendir($this->tempfolder)) {
            $OK = true;

            while (false !== ($file = readdir($handle))) {
                if (!is_file($this->tempfolder . $file)) {
                    continue;
                }

                // check for name-prefix, extension and filetime

                if (mb_substr($file, 0, mb_strlen($this->prefix)) == $this->prefix) {
                    if ('.jpg' == mb_strrchr($file, '.')) {
                        $captchas++;

                        if ((time() - filemtime($this->tempfolder . $file)) >= $this->maxlifetime) {
                            $trashed++;

                            $res = @unlink($this->tempfolder . $file);

                            if (!$res) {
                                $OK = false;
                            }
                        }
                    }
                }
            }

            closedir($handle);
        }

        if ($this->debug) {
            echo "\n<br>-Captcha-Debug: There are ($captchas) captcha-images in tempfolder, where ($trashed) are seems to be lost.";
        }

        return $OK;
    }

    /** @private *
     * @param string $public
     * @return string
     */

    public function get_filename($public = '')
    {
        if ('' == $public) {
            $public = $this->public_key;
        }

        return $this->tempfolder . $this->prefix . $public . '.jpg';
    }

    /** @private *
     * @param string $public
     * @return string
     */

    public function get_filename_url($public = '')
    {
        if ('' == $public) {
            $public = $this->public_key;
        }

        return str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->tempfolder) . $this->prefix . $public . '.jpg';
    }
} // END CLASS hn_CAPTCHA_X1

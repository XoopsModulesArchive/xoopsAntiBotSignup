<?php
// $Id: register.php 2 2005-11-02 18:23:29Z skalpa $
//  ------------------------------------------------------------------------ //
//                XOOPS - PHP Content Management System                      //
//                    Copyright (c) 2000 XOOPS.org                           //
//                       <https://www.xoops.org>                             //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //

$xoopsOption['pagetype'] = 'user';

require __DIR__ . '/mainfile.php';
$myts = MyTextSanitizer::getInstance();
error_reporting(E_ALL);
require_once XOOPS_ROOT_PATH . '/class/captcha/hn_captcha.class.php';

$configHandler = xoops_getHandler('config');
$xoopsConfigUser = $configHandler->getConfigsByCat(XOOPS_CONF_USER);

    // ConfigArray
    $CAPTCHA_INIT = [
            'tempfolder' => XOOPS_ROOT_PATH . '/uploads/_captcha_tmp/',      // string: absolute path (with trailing slash!) to a writeable tempfolder which is also accessible via HTTP!
            'TTF_folder' => XOOPS_ROOT_PATH . '/include/fonts/', //_rsrc/TTF/', // string: absolute path (with trailing slash!) to folder which contains your TrueType-Fontfiles.
                                // mixed (array or string): basename(s) of TrueType-Fontfiles
            'TTF_RANGE' => ['ariblk.ttf', 'BAUHS93.TTF', 'BROADW.TTF', 'CHILLER.TTF', 'ELEPHNT.TTF'], //,'MREARL.TTF','RUBBERSTAMP.TTF','ZINJARON.TTF'),
        //	'TTF_RANGE'      => 'COMIC.TTF',

            'chars' => 6,       // integer: number of chars to use for ID
            'minsize' => 13,      // integer: minimal size of chars
            'maxsize' => 16,      // integer: maximal size of chars
            'maxrotation' => 17,      // integer: define the maximal angle for char-rotation, good results are between 0 and 30

            'noise' => true,    // boolean: TRUE = noisy chars | FALSE = grid
            'websafecolors' => false,   // boolean
            'refreshlink' => true,    // boolean
            'lang' => 'en',    // string:  ['en'|'de']
            'maxtry' => 3,       // integer: [1-9]

            'badguys_url' => '/',     // string: URL
            'secretstring' => 'A very, very secret string which is used to generate a md5-key! Jesus!!',
            'secretposition' => 12,      // integer: [1-32]

            'debug' => false,
    ];

global $captcha;
$captcha = new hn_captcha($CAPTCHA_INIT);

if (empty($xoopsConfigUser['allow_register'])) {
    redirect_header('index.php', 6, _US_NOREGISTER);

    exit();
}

function userCheck($uname, $email, $pass, $vpass)
{
    global $xoopsConfigUser;

    $xoopsDB = XoopsDatabaseFactory::getDatabaseConnection();

    $myts = MyTextSanitizer::getInstance();

    $stop = '';

    if (!checkEmail($email)) {
        $stop .= _US_INVALIDMAIL . '<br>';
    }

    foreach ($xoopsConfigUser['bad_emails'] as $be) {
        if (!empty($be) && preg_match('/' . $be . '/i', $email)) {
            $stop .= _US_INVALIDMAIL . '<br>';

            break;
        }
    }

    if (mb_strrpos($email, ' ') > 0) {
        $stop .= _US_EMAILNOSPACES . '<br>';
    }

    $uname = xoops_trim($uname);

    switch ($xoopsConfigUser['uname_test_level']) {
    case 0:
        // strict
        $restriction = '/[^a-zA-Z0-9\_\-]/';
        break;
    case 1:
        // medium
        $restriction = '/[^a-zA-Z0-9\_\-\<\>\,\.\$\%\#\@\!\\\'\"]/';
        break;
    case 2:
        // loose
        $restriction = '/[\000-\040]/';
        break;
    }

    if (empty($uname) || preg_match($restriction, $uname)) {
        $stop .= _US_INVALIDNICKNAME . '<br>';
    }

    if (mb_strlen($uname) > $xoopsConfigUser['maxuname']) {
        $stop .= sprintf(_US_NICKNAMETOOLONG, $xoopsConfigUser['maxuname']) . '<br>';
    }

    if (mb_strlen($uname) < $xoopsConfigUser['minuname']) {
        $stop .= sprintf(_US_NICKNAMETOOSHORT, $xoopsConfigUser['minuname']) . '<br>';
    }

    foreach ($xoopsConfigUser['bad_unames'] as $bu) {
        if (!empty($bu) && preg_match('/' . $bu . '/i', $uname)) {
            $stop .= _US_NAMERESERVED . '<br>';

            break;
        }
    }

    if (mb_strrpos($uname, ' ') > 0) {
        $stop .= _US_NICKNAMENOSPACES . '<br>';
    }

    $sql = sprintf('SELECT COUNT(*) FROM %s WHERE uname = %s', $xoopsDB->prefix('users'), $xoopsDB->quoteString(addslashes($uname)));

    $result = $xoopsDB->query($sql);

    [$count] = $xoopsDB->fetchRow($result);

    if ($count > 0) {
        $stop .= _US_NICKNAMETAKEN . '<br>';
    }

    $count = 0;

    if ($email) {
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE email = %s', $xoopsDB->prefix('users'), $xoopsDB->quoteString(addslashes($email)));

        $result = $xoopsDB->query($sql);

        [$count] = $xoopsDB->fetchRow($result);

        if ($count > 0) {
            $stop .= _US_EMAILTAKEN . '<br>';
        }
    }

    if (!isset($pass) || '' == $pass || !isset($vpass) || '' == $vpass) {
        $stop .= _US_ENTERPWD . '<br>';
    }

    if ((isset($pass)) && ($pass != $vpass)) {
        $stop .= _US_PASSNOTSAME . '<br>';
    } elseif (('' != $pass) && (mb_strlen($pass) < $xoopsConfigUser['minpass'])) {
        $stop .= sprintf(_US_PWDTOOSHORT, $xoopsConfigUser['minpass']) . '<br>';
    }

    return $stop;
}
$op = $_POST['op'] ?? 'register';
$uname = isset($_POST['uname']) ? $myts->stripSlashesGPC($_POST['uname']) : '';
$email = isset($_POST['email']) ? trim($myts->stripSlashesGPC($_POST['email'])) : '';
$url = isset($_POST['url']) ? trim($myts->stripSlashesGPC($_POST['url'])) : '';
$pass = isset($_POST['pass']) ? $myts->stripSlashesGPC($_POST['pass']) : '';
$vpass = isset($_POST['vpass']) ? $myts->stripSlashesGPC($_POST['vpass']) : '';
$timezone_offset = isset($_POST['timezone_offset']) ? (int)$_POST['timezone_offset'] : $xoopsConfig['default_TZ'];
$user_viewemail = (isset($_POST['user_viewemail']) && (int)$_POST['user_viewemail']) ? 1 : 0;
$user_mailok = (isset($_POST['user_mailok']) && (int)$_POST['user_mailok']) ? 1 : 0;
$agree_disc = (isset($_POST['agree_disc']) && (int)$_POST['agree_disc']) ? 1 : 0;
switch ($op) {
case 'newuser':
    if (!$GLOBALS['xoopsSecurity']->check()) {
        echo implode('<br>', $GLOBALS['xoopsSecurity']->getErrors());

        exit();
    }
    require __DIR__ . '/header.php';
    $stop = '';
    if (0 != $xoopsConfigUser['reg_dispdsclmr'] && '' != $xoopsConfigUser['reg_disclaimer']) {
        if (empty($agree_disc)) {
            $stop .= _US_UNEEDAGREE . '<br>';
        }
    }
    $stop .= userCheck($uname, $email, $pass, $vpass);
    switch ($captcha->validate_submit()) {
     case 2:
        $stop .= 'You did not input the correct validation string!';
        break;
     case 3:
        $stop .= 'Maximum tries for validation, you must reset the form!';
        break;
    }

    if (empty($stop)) {
        echo _US_USERNAME . ': ' . htmlspecialchars($uname, ENT_QUOTES | ENT_HTML5) . '<br>';

        echo _US_EMAIL . ': ' . htmlspecialchars($email, ENT_QUOTES | ENT_HTML5) . '<br>';

        if ('' != $url) {
            $url = formatURL($url);

            echo _US_WEBSITE . ': ' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) . '<br>';
        }

        $f_timezone = ($timezone_offset < 0) ? 'GMT ' . $timezone_offset : 'GMT +' . $timezone_offset;

        echo _US_TIMEZONE . ": $f_timezone<br>";

        echo "<form action='register.php' method='post'>
		<input type='hidden' name='uname' value='" . htmlspecialchars($uname, ENT_QUOTES | ENT_HTML5) . "'>
		<input type='hidden' name='email' value='" . htmlspecialchars($email, ENT_QUOTES | ENT_HTML5) . "'>";

        echo "<input type='hidden' name='user_viewemail' value='" . $user_viewemail . "'>
		<input type='hidden' name='timezone_offset' value='" . (float)$timezone_offset . "'>
		<input type='hidden' name='url' value='" . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) . "'>
		<input type='hidden' name='pass' value='" . htmlspecialchars($pass, ENT_QUOTES | ENT_HTML5) . "'>
		<input type='hidden' name='vpass' value='" . htmlspecialchars($vpass, ENT_QUOTES | ENT_HTML5) . "'>
		<input type='hidden' name='user_mailok' value='" . $user_mailok . "'>
		<br><br><input type='hidden' name='op' value='finish'>" . $GLOBALS['xoopsSecurity']->getTokenHTML() . "<input type='submit' value='" . _US_FINISH . "'></form>";
    } else {
        echo "<span style='color:#ff0000;'>$stop</span>";

        require __DIR__ . '/include/registerform.php';

        $reg_form->display();
    }
    require __DIR__ . '/footer.php';
    break;
case 'finish':
    if (!$GLOBALS['xoopsSecurity']->check()) {
        echo implode('<br>', $GLOBALS['xoopsSecurity']->getErrors());

        exit();
    }
    require __DIR__ . '/header.php';
    $stop = userCheck($uname, $email, $pass, $vpass);
    if (empty($stop)) {
        $memberHandler = xoops_getHandler('member');

        $newuser = $memberHandler->createUser();

        $newuser->setVar('user_viewemail', $user_viewemail, true);

        $newuser->setVar('uname', $uname, true);

        $newuser->setVar('email', $email, true);

        if ('' != $url) {
            $newuser->setVar('url', formatURL($url), true);
        }

        $newuser->setVar('user_avatar', 'blank.gif', true);

        $actkey = mb_substr(md5(uniqid(mt_rand(), 1)), 0, 8);

        $newuser->setVar('actkey', $actkey, true);

        $newuser->setVar('pass', md5($pass), true);

        $newuser->setVar('timezone_offset', $timezone_offset, true);

        $newuser->setVar('user_regdate', time(), true);

        $newuser->setVar('uorder', $xoopsConfig['com_order'], true);

        $newuser->setVar('umode', $xoopsConfig['com_mode'], true);

        $newuser->setVar('user_mailok', $user_mailok, true);

        if (1 == $xoopsConfigUser['activation_type']) {
            $newuser->setVar('level', 1, true);
        }

        if (!$memberHandler->insertUser($newuser)) {
            echo _US_REGISTERNG;

            require __DIR__ . '/footer.php';

            exit();
        }

        $newid = $newuser->getVar('uid');

        if (!$memberHandler->addUserToGroup(XOOPS_GROUP_USERS, $newid)) {
            echo _US_REGISTERNG;

            require __DIR__ . '/footer.php';

            exit();
        }

        if (1 == $xoopsConfigUser['activation_type']) {
            redirect_header('index.php', 4, _US_ACTLOGIN);

            exit();
        }

        if (0 == $xoopsConfigUser['activation_type']) {
            $xoopsMailer = getMailer();

            $xoopsMailer->useMail();

            $xoopsMailer->setTemplate('register.tpl');

            $xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);

            $xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);

            $xoopsMailer->assign('SITEURL', XOOPS_URL . '/');

            $xoopsMailer->setToUsers(new XoopsUser($newid));

            $xoopsMailer->setFromEmail($xoopsConfig['adminmail']);

            $xoopsMailer->setFromName($xoopsConfig['sitename']);

            $xoopsMailer->setSubject(sprintf(_US_USERKEYFOR, $uname));

            if (!$xoopsMailer->send()) {
                echo _US_YOURREGMAILNG;
            } else {
                echo _US_YOURREGISTERED;
            }
        } elseif (2 == $xoopsConfigUser['activation_type']) {
            $xoopsMailer = getMailer();

            $xoopsMailer->useMail();

            $xoopsMailer->setTemplate('adminactivate.tpl');

            $xoopsMailer->assign('USERNAME', $uname);

            $xoopsMailer->assign('USEREMAIL', $email);

            $xoopsMailer->assign('USERACTLINK', XOOPS_URL . '/user.php?op=actv&id=' . $newid . '&actkey=' . $actkey);

            $xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);

            $xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);

            $xoopsMailer->assign('SITEURL', XOOPS_URL . '/');

            $memberHandler = xoops_getHandler('member');

            $xoopsMailer->setToGroups($memberHandler->getGroup($xoopsConfigUser['activation_group']));

            $xoopsMailer->setFromEmail($xoopsConfig['adminmail']);

            $xoopsMailer->setFromName($xoopsConfig['sitename']);

            $xoopsMailer->setSubject(sprintf(_US_USERKEYFOR, $uname));

            if (!$xoopsMailer->send()) {
                echo _US_YOURREGMAILNG;
            } else {
                echo _US_YOURREGISTERED2;
            }
        }

        if (1 == $xoopsConfigUser['new_user_notify'] && !empty($xoopsConfigUser['new_user_notify_group'])) {
            $xoopsMailer = getMailer();

            $xoopsMailer->useMail();

            $memberHandler = xoops_getHandler('member');

            $xoopsMailer->setToGroups($memberHandler->getGroup($xoopsConfigUser['new_user_notify_group']));

            $xoopsMailer->setFromEmail($xoopsConfig['adminmail']);

            $xoopsMailer->setFromName($xoopsConfig['sitename']);

            $xoopsMailer->setSubject(sprintf(_US_NEWUSERREGAT, $xoopsConfig['sitename']));

            $xoopsMailer->setBody(sprintf(_US_HASJUSTREG, $uname));

            $xoopsMailer->send();
        }
    } else {
        echo "<span style='color:#ff0000; font-weight:bold;'>$stop</span>";

        require __DIR__ . '/include/registerform.php';

        $reg_form->display();
    }
    require __DIR__ . '/footer.php';
    break;
case 'register':
default:
    require __DIR__ . '/header.php';
    require __DIR__ . '/include/registerform.php';
    $reg_form->display();
    require __DIR__ . '/footer.php';
    break;
}

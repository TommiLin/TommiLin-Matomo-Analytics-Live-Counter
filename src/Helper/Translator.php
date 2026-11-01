<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_matomo_counter
 *
 * @copyright   Copyright (C) 2026 TommiLin. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace TommiLin\Module\MatomoCounter\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;

/**
 * Reads the module's own language files directly, so any language can be forced
 * (and AJAX refreshes keep the page language) without touching Joomla's language internals.
 */
class Translator
{
    /** @var array<string, array<string,string>> */
    protected static $files = [];

    /** @var string */
    protected $tag;

    /** @var array */
    protected $strings;

    /** @var array */
    protected $fallback;

    public function __construct($tag)
    {
        $this->tag      = static::isAvailable($tag) ? $tag : 'en-GB';
        $this->strings  = static::load($this->tag);
        $this->fallback = $this->tag === 'en-GB' ? [] : static::load('en-GB');
    }

    /**
     * Picks the language tag: forced language > requested language > current site language.
     */
    public static function resolveTag($forced = 'site', $requested = null)
    {
        foreach ([$forced, $requested] as $candidate) {
            if ($candidate && $candidate !== 'site' && static::isAvailable($candidate)) {
                return $candidate;
            }
        }

        $tag = Factory::getApplication()->getLanguage()->getTag();

        return static::isAvailable($tag) ? $tag : 'en-GB';
    }

    public static function isAvailable($tag)
    {
        return is_string($tag)
            && preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $tag)
            && static::path($tag) !== null;
    }

    public function getTag()
    {
        return $this->tag;
    }

    public function getShortTag()
    {
        return strtolower(substr($this->tag, 0, 2));
    }

    /**
     * Translates a key; extra arguments are passed to vsprintf().
     */
    public function t($key, ...$args)
    {
        $text = $this->strings[$key] ?? $this->fallback[$key] ?? $key;

        return $args ? vsprintf($text, $args) : $text;
    }

    protected static function path($tag)
    {
        $base = dirname(__DIR__, 2) . '/language/' . $tag;

        foreach ([$base . '/mod_matomo_counter.ini', $base . '/' . $tag . '.mod_matomo_counter.ini'] as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    protected static function load($tag)
    {
        if (!isset(static::$files[$tag])) {
            $file = static::path($tag);

            static::$files[$tag] = $file ? LanguageHelper::parseIniFile($file) : [];
        }

        return static::$files[$tag];
    }
}

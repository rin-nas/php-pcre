<?php
/**
 *
 * Additional PCRE functions for PHP
 *
 * Thanks to Jeffrey E.F. Friedl, http://regex.info/
 *
 * TODO методы *Error() нужно доработать
 */

/*
"Missing" Preg functions
------------------------

PHP's built-in preg functions provide a good range of functionality, but there have been times that I've found certain aspects a bit lacking. One example we've already seen is my special version of preg_match (? 454).
Another area where I've felt the need to build my own support functions involves situations where regular expressions are not provided directly in the program via literal pattern-argument strings, but brought in from outside the program (e.g., read from a file, or provided by a user via a web form). As we'll see in the next section, converting from a raw regular-expression string to a preg-appropriate patter n-argument can be tricky.
Also, before using such a regular expression, it's generally a good idea to validate that it's syntactically correct. We'll look into that as well.
As with all the code samples in this book, the functions on the coming pages are all available for download at my web site: http://regex.info/.
If you have a raw regular expression in a string (perhaps read from a configuration file, or submitted via a web form) that you'd like to use with a preg function, you must first wrap it in delimiters to make a preg-appropriate pattern argument.

The problem
-----------
In many cases, converting a regular expression into a pattern argument is as simple as wrapping the regex with forward slashes. This would convert, for example, a regular-expression string '[a-z]+' to '/[a-z]+/', a string appropriate for use as a preg pattern argument.
However, the conversion becomes more complex if the regular expression actually contains the delimiter in which you choose to wrap it. For example, if the regex string is '^http://([^/:]+)', simply wrapping it in forward slashes yields '/^http://([^/:]+)/', which results in an "Unknown modifier /" error when used as a pattern modifier.
As described in the sidebar on page 448, the odd error message is generated because the first and second forward slashes in the string are taken as the delimiters, and whatever follows (in this case, the third forward slash in the string) is taken as the start of the pattern-modifier list.

The solution
------------

There are two ways to avoid the embedded-delimiter conflict. One is to choose a delimiter character that doesn't appear within the regular expression, and this is certainly the recommend way when you're composing a pattern-modifier string by hand. That's why I used {?} as the delimiters in the examples on pages 444, 449, and 450 (to name only a few).
It may not be easy (or even possible) to choose a delimiter that doesn't appear in the regex, because the text could contain every delimiter, or you may not know in advance what text you have to work with. This becomes a particular concern when working programatically with a regex in a string, so it's easier to simply use a second approach: select a delimiter character, then escape any occurrence of that character within the regex string.
It's actually quite a bit trickier than it might seem at first, because you must pay attention to some important details. For example, an escape at the end of the target text requires special handling so it won't escape the appended delimiter.
*/

namespace Cms\Utils;

class PCRE
{
    /**
     * Given a raw regex in a string (and, optionally, a pattern-modifiers string), return a string suitable
     * for use as a preg pattern. The regex is wrapped in delimiters, with the modifiers (if any) appended.
     *
     * @param string $regex
     * @param string $modifiers
     *
     * @return string
     */
	public static function regexToPattern(string $regex, string $modifiers = '') : string
	{
		/*
		To convert a regex to a pattern, we must wrap the pattern in delimiters (we'll use a pair of
		forward slashes) and append the modifiers. We must also be sure to escape any unescaped
		occurrences of the delimiter within the regex, and to escape a regex-ending escape
		(which, if left alone, would end up escaping the delimiter we append).

		We can't just blindly escape embedded delimiters, because it would break a regex containing
		an already-escaped delimiter. For example, if the regex is '\/', a blind escape results
		in '\\/' which would not work when eventually wrapped with delimiters: '/\\//'.

		Rather, we'll break down the regex into sections: escaped characters, unescaped forward
		slashes (which we'll need to escape), and everything else. As a special case, we also look out
		for, and escape, a regex-ending escape.
		*/
		if (! preg_match('~\\\\(?:/|$)~s', $regex)) /* '/' followed by '\' or EOS */
		{
			/*
			There are no already-escaped forward slashes, and no escape at the end,
			so it's safe to blindly escape forward slashes.
			*/
			$cooked = str_replace('/', '\/', $regex);
		}
		else
		{
			/*
			This is the pattern we'll use to parse $regex.
			The two parts whose matches we'll need to escape are within capturing parens.
			*/
			$pattern = '~		(?> [^\\\\/]+ | \\\\. )*
								\K
							|	( / | \\\\$ )
						~sx';
			/*
			Our callback function is called upon each successful match of $pattern in $regex.
			If $matches[0] is not empty, we return an escaped version of it.
			Otherwise, we simply return what was matched unmodified.
			*/
			/* Actually apply $pattern to $regex, yielding $cooked */
			$cooked = preg_replace_callback($pattern, function (array $m) : string {
                return strlen($m[0]) ? '\\' . $m[0] : '';
            }, $regex);
		}
		/* $cooked is now safe to wrap -- do so, append the modifiers, and return */
		return '/' . $cooked . '/' . $modifiers;
	}

	public static function quoteClass(string $class, $delimiter = null) : string
	{
		$quoteTable = array(
			'\\' => '\\\\',
			'-'  => '\-',
			']'  => '\]',
		);
		if (is_string($delimiter)) {
            $quoteTable[$delimiter] = '\\' . $delimiter;
        }
		return strtr($class, $quoteTable);
	}

    /**
     * Вырезает все комментарии и пробельные символы из рег. выражения (чтобы привести его к стандарту ECMA-262)
     *
     * @param string $pattern
     *
     * @return string
     */
    public static function unExtended(string $pattern) : string {
        //вырезаем флаг 'x'
        $pattern = preg_replace('/x(?=[a-zA-Z]*+$)/s', '', $pattern);
	    //TODO пока сделано по простому: знаки # нужно отдельно квотировать слэшом (\), если они не являются началом комменнтария
        //https://github.com/DmitrySoshnikov/babel-plugin-transform-modern-regexp
        //https://babeljs.io/docs/en/next/babel-plugin-transform-dotall-regex.html
        //https://stackoverflow.com/questions/12127463/convert-perl-regular-expression-to-equivalent-ecmascript-regular-expression
        return preg_replace('/(?<!\\\\)#[^\\r\\n]++|\\s++/s','', $pattern);
    }

    /**
     * Syntax-Checking an Unknown Pattern Argument
     *
     * Return an error message if the given pattern argument or its underlying regular expression
     * are not syntactically valid. Otherwise (if they are valid), FALSE is returned.
     *
     * @param string $pattern
     *
     * @return bool
     */
    public static function patternError(string $pattern) : bool
    {
        /*
        To tell if the pattern has errors, we simply try to use it.
        To detect and capture the error is not so simple, especially if we want to be sociable and not
        tramp on global state (e.g., the value of $php_errormsg). So, if 'track_errors' is on, we preserve
        the $php_errormsg value and restore it later. If 'track_errors' is not on, we turn it on (because
        we need it) but turn it off when we're done.
        */
        if ($old_track = ini_get('track_errors')) $old_message = isset($php_errormsg) ? $php_errormsg : false;
        else ini_set('track_errors', 1);
        /* We're now sure that track_errors is on. */

        unset($php_errormsg);
        @preg_match($pattern, ''); /*actually give the pattern a try! */
        $return_value = isset($php_errormsg) ? $php_errormsg : false;

        /* We've now captured what we need; restore global state to what it was. */
        if ($old_track) $php_errormsg = isset($old_message) ? $old_message : false;
        else ini_set('track_errors', 0);
        return $return_value;
    }

    /**
     * Syntax-Checking an Unknown Regex
     *
     * Return a descriptive error message if the given regular expression is invalid.
     * If it's valid, false is returned.
     */
    public static function regexError(string $regex) : bool
    {
        return self::patternError(self::regexToPattern($regex));
    }

    /**
     * Выполняет поиск и замену по регулярному выражению с использованием строк или функций обратного вызова
     *
     * @param array           $replacePairs     Ассоциативный массив, связывающий шаблоны регулярного выражения (ключи)
     *                                          и функции обратного вызова (значения).
     * @param string|string[] $subject          Строка или массив строк для поиска и замены.
     *
     * @return null|string|string[]
     * @throws \TypeError
     */
    public static function pregReplacePairs(array $replacePairs, $subject) {
        foreach ($replacePairs as $pattern => $replacement) {
            if (! is_string($pattern)) throw new \TypeError();
            if (is_string($replacement))   	   $subject = preg_replace($pattern, $replacement, $subject);
            elseif (is_callable($replacement)) $subject = preg_replace_callback($pattern, $replacement, $subject);
            else throw new \TypeError();
        }
        return $subject;
    }


}

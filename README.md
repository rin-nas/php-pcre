# Additional PCRE functions for PHP

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


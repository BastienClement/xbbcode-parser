<?php
//
//  Copyright (C) 2012 Unnamed Lab <http://www.unnamed.eu/>
//
//  Permission is hereby granted, free of charge, to any person obtaining
//  a copy of this software and associated documentation files (the
//  "Software"), to deal in the Software without restriction, including
//  without limitation the rights to use, copy, modify, merge, publish,
//  distribute, sublicense, and/or sell copies of the Software, and to
//  permit persons to whom the Software is furnished to do so, subject to
//  the following conditions:
//
//  The above copyright notice and this permission notice shall be included
//  in all copies or substantial portions of the Software.
//
//  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
//  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
//  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
//  IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
//  CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
//  TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
//  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
//

namespace XBBC;

class StdTags {
	public static function import($parser) {
		// Defining standards tags
		$parser->DefineTag('b',    new SimpleTag('<strong>', '</strong>'));
		$parser->DefineTag('i',    new SimpleTag('<em>', '</em>'));
		$parser->DefineTag('u',    new SimpleTag('<span style="font-style: italic;">', '</span>'));
		
		$parser->DefineTag('url',  new LinkTag);
		$parser->DefineTag('img',  new ImageTag);
		
		$parser->DefineTag('c',    new SimpleTag('<code>', '</code>', false, true));
		$parser->DefineTag('code', new SimpleTag('<pre><code>', '</code></pre>', true, true));
		
		$parser->DefineTag('hr',   new SingleTag('<div class="hr"></div>', true));
		
		$parser->DefineTag('list', new ListTag);
		$parser->DefineTag('*', new ListItemTag);
	}
}

class StdSmilies {
	public static function import($parser) {
		// TODO ...
	}
}

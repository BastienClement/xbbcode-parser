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

// === Main tags ===============================================================

//
// The [url] tag, supports embedded images
//
class LinkTag extends SimpleTag {
	protected $embedded_img = null;
	
	public function __construct() {
		parent::__construct('<a href="#">', '</a>');
	}
	
	public function __create() {
		if($this->arg)
			$this->arg = TagTools::SanitizeURL($this->arg);
	}
	
	public function CanShift($tag) {
		// We can't shift more than one image (with embedded mode)
		if($this->embedded_img)
			return false;
		
		// Hook ourself on the first [img] tag if arg is undefined
		if($tag instanceof ImageTag && !$this->arg) {
			$this->embedded_img = $tag;
			return true;
		}
		
		return $this->arg ? parent::CanShift($tag) : false;
	}
	
	public function Reduce() {
		if($this->embedded_img)
			$url = htmlspecialchars($this->embedded_img->GetURL());
		else
			$url = $this->arg ? htmlspecialchars($this->arg) : TagTools::SanitizeURL($this->content, true);
		
		if($url)
			$this->before = '<a href="'.$url.'">';
		
		return parent::Reduce();
	}
}

//
// The [img] tag
//
class ImageTag extends ArgAsContentTag {
	public function __construct() {
		parent::__construct();
		$this->buffer_escape = false;
	}
	
	public function Reduce() {
		// Image URL
		$url = $this->GetURL();
		
		// Alt text
		$alt = isset($this->xargs['alt']) ? $this->xargs['alt'] : basename($url);
		
		// Image styles
		$styles = array();
		
		if(isset($this->xargs['width']) && $size = TagTools::FormatSize($this->xargs['width']))
			$styles[] = 'width:'.$size;
		
		if(isset($this->xargs['height']) && $size = TagTools::FormatSize($this->xargs['height']))
			$styles[] = 'height:'.$size;
		
		$styles = !empty($styles) ? 'style="'.implode(';', $styles).'" ' : '';
		
		// Final tag
		return '<img src="'.htmlspecialchars($url).'" alt="'.htmlspecialchars($alt).'" '.$styles.'/>';
	}
	
	//
	// Return the unescaped URL for this image
	//
	public function GetURL() {
		return TagTools::SanitizeURL(parent::Reduce());
	}
}

//
// The [quote] tag, emulate a new document root
//
class QuoteTag extends RootTag {
	// How many times can quote-tags be nested
	public static $MAX_NESTING = 3;
	
	public function __construct() {
		parent::__construct();
		$this->before = '<blockquote>';
		$this->after = '</blockquote>';
	}
	
	public function MaxNesting() {
		return self::$MAX_NESTING;
	}
	
	public function Reduce() {
		if($author = $this->GetAuthorString()) {
			$this->content = '<div class="quote-author">'.htmlspecialchars($author).'</div>'.$this->content;
		}
		
		return parent::Reduce();
	}
	
	public function GetAuthorString() {
		return $this->arg ? $this->arg.' a écrit :' : false;
	}
}

// Stdlib interface

class StdTags {
	public static function import($parser) {
		// Defining standards tags
		$parser->DefineTag('b',     new SimpleTag('<strong>', '</strong>'));
		$parser->DefineTag('i',     new SimpleTag('<em>', '</em>'));
		$parser->DefineTag('u',     new SimpleTag('<span style="text-decoration: underline;">', '</span>'));
		$parser->DefineTag('url',   new LinkTag());
		$parser->DefineTag('img',   new ImageTag());
		$parser->DefineTag('c',     new LeafTag('<code>', '</code>', false, false));
		$parser->DefineTag('code',  new LeafTag('<pre><code>', '</code></pre>', true, false));
		$parser->DefineTag('quote', new QuoteTag());
	}
}

class StdSmilies {
	public static function import($parser) {
		$parser->DefineSmiley(':)', 'smile.png');
		$parser->DefineSmiley('=)', 'smile.png');
		$parser->DefineSmiley(':|', 'neutral.png');
		$parser->DefineSmiley('=|', 'neutral.png');
		$parser->DefineSmiley(':(', 'sad.png');
		$parser->DefineSmiley('=(', 'sad.png');
		$parser->DefineSmiley(':D', 'big_smile.png');
		$parser->DefineSmiley('=D', 'big_smile.png');
		$parser->DefineSmiley(':O', 'yikes.png');
		$parser->DefineSmiley(';)', 'wink.png');
		$parser->DefineSmiley(':/', 'hmm.png');
		$parser->DefineSmiley(':P', 'tongue.png');
		$parser->DefineSmiley(':lol:', 'lol.png');
		$parser->DefineSmiley(':mad:', 'mad.png');
		$parser->DefineSmiley(':rolleyes:', 'roll.png');
		$parser->DefineSmiley(':cool:', 'cool.png');
	}
}

